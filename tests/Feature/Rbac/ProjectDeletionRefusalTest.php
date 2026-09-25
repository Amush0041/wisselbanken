<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 4 rule 4 (B3): organization and user deletion are refused while the organization has projects,
 * counting soft-deleted ones, and nothing is deleted on a refusal. The three delete paths are
 * OrgSettingsController::destroy (owner), RbacController::destroyOrganization and ::destroyUser (platform admin).
 */
class ProjectDeletionRefusalTest extends ProjectTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    /** @return array{0:Organization,1:User} an organization with one owner and the given projects (null = none, 'trashed' = one soft-deleted) */
    private function orgWith(?string $projects, string $name = 'Org X'): array
    {
        $org = $this->mkOrg($name);
        $owner = $this->mkUser($org, 'organization_owner');
        if ($projects === 'live') {
            $this->mkProject($org, $owner, 'live');
        } elseif ($projects === 'trashed') {
            $this->mkProject($org, $owner, 'gone', now()->toDateTimeString());
        }

        return [$org, $owner];
    }

    private function orgExists(int $id): bool
    {
        return DB::table('organizations')->where('id', $id)->exists();
    }

    private function assertNothingDeleted(Organization $org, User $owner, int $projects): void
    {
        $this->assertTrue($this->orgExists($org->id), 'organization survives');
        $this->assertTrue(User::whereKey($owner->id)->exists(), 'user survives');
        $this->assertSame(1, DB::table('user_org_roles')->where('org_id', $org->id)->where('user_id', $owner->id)->count(), 'role rows survive');
        $this->assertSame($projects, DB::table('projects')->where('org_id', $org->id)->count(), 'projects survive');
    }

    // ---- OrgSettingsController::destroy (org owner) -----------------------------

    #[DataProvider('modes')]
    public function test_owner_delete_is_refused_with_a_live_project(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->orgWith('live');

        $this->actingAs($owner)->from('/settings')->delete(route('org-admin.settings.destroy'))
            ->assertRedirect('/settings')->assertSessionHas('error', 'This organization still has projects and cannot be deleted.');

        $this->assertNothingDeleted($org, $owner, 1);
    }

    #[DataProvider('modes')]
    public function test_owner_delete_is_refused_when_only_a_soft_deleted_project_remains(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->orgWith('trashed');

        $this->actingAs($owner)->from('/settings')->delete(route('org-admin.settings.destroy'))
            ->assertRedirect('/settings')->assertSessionHas('error');

        $this->assertNothingDeleted($org, $owner, 1);
    }

    #[DataProvider('modes')]
    public function test_owner_delete_succeeds_for_an_organization_without_projects(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->orgWith(null);

        $this->actingAs($owner)->delete(route('org-admin.settings.destroy'))
            ->assertRedirect(route('user.dashboard'))->assertSessionMissing('error');

        $this->assertFalse($this->orgExists($org->id));
        $this->assertSame(0, DB::table('user_org_roles')->where('org_id', $org->id)->count());
    }

    #[DataProvider('modes')]
    public function test_owner_delete_only_counts_the_callers_own_organization(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->orgWith(null, 'Empty org');
        $this->mkProject($this->orgA, $this->owner, 'elsewhere');

        $this->actingAs($owner)->delete(route('org-admin.settings.destroy'))->assertRedirect(route('user.dashboard'));

        $this->assertFalse($this->orgExists($org->id), 'projects of other organizations do not block it');
    }

    #[DataProvider('modes')]
    public function test_a_non_owner_cannot_delete_the_organization(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->orgWith(null);
        $member = $this->mkUser($org, 'estimator');

        $this->actingAs($member)->delete(route('org-admin.settings.destroy'))->assertStatus(403);

        $this->assertNothingDeleted($org, $owner, 0);
    }

    // ---- RbacController::destroyOrganization (platform admin) -------------------

    public function test_admin_org_delete_is_refused_with_live_and_with_soft_deleted_projects(): void
    {
        foreach (['live', 'trashed'] as $kind) {
            [$org, $owner] = $this->orgWith($kind, "Org $kind");

            $this->actingAs($this->admin)->from('/admin/rbac/organizations')->delete(route('admin.rbac.organizations.destroy', $org))
                ->assertRedirect('/admin/rbac/organizations')->assertSessionHas('error', 'This organization still has projects and cannot be deleted.');

            $this->assertNothingDeleted($org, $owner, 1);
        }
    }

    public function test_admin_org_delete_succeeds_without_projects_and_ignores_other_orgs_projects(): void
    {
        [$org, $owner] = $this->orgWith(null);

        $this->actingAs($this->admin)->delete(route('admin.rbac.organizations.destroy', $org))
            ->assertRedirect(route('admin.rbac.organizations'))->assertSessionMissing('error');

        $this->assertFalse($this->orgExists($org->id));
        $this->assertTrue($this->orgExists($this->orgA->id), 'org A with projects is untouched');
    }

    public function test_admin_org_delete_is_closed_to_a_normal_user(): void
    {
        [$org, $owner] = $this->orgWith(null);

        $this->actingAs($owner)->deleteJson(route('admin.rbac.organizations.destroy', $org))->assertForbidden();

        $this->assertNothingDeleted($org, $owner, 0);
    }

    // ---- RbacController::destroyUser (platform admin) ---------------------------

    public function test_admin_user_delete_is_refused_when_the_user_solely_holds_an_org_with_projects(): void
    {
        foreach (['live', 'trashed'] as $kind) {
            [$org, $owner] = $this->orgWith($kind, "Solo $kind");

            $this->actingAs($this->admin)->from('/admin/rbac/users')->delete(route('admin.rbac.users.destroy', $owner))
                ->assertRedirect('/admin/rbac/users')->assertSessionHas('error', 'This user solely owns an organization that still has projects and cannot be deleted.');

            $this->assertNothingDeleted($org, $owner, 1);
        }
    }

    public function test_admin_user_delete_treats_an_org_with_only_inactive_other_members_as_solely_owned(): void
    {
        [$org, $owner] = $this->orgWith('live');
        $ghost = $this->mkUser();
        $this->assignRole($ghost, $org, 'estimator', false);

        $this->actingAs($this->admin)->delete(route('admin.rbac.users.destroy', $owner))->assertSessionHas('error');

        $this->assertNothingDeleted($org, $owner, 1);
    }

    public function test_admin_user_delete_is_refused_if_any_one_of_the_users_orgs_is_solely_held_with_projects(): void
    {
        [$org, $owner] = $this->orgWith('live');
        $this->assignRole($owner, $this->orgA, 'estimator');

        $this->actingAs($this->admin)->delete(route('admin.rbac.users.destroy', $owner))->assertSessionHas('error');

        $this->assertNothingDeleted($org, $owner, 1);
        $this->assertTrue($this->orgExists($this->orgA->id));
    }

    public function test_admin_user_delete_succeeds_when_the_solely_held_org_has_no_projects(): void
    {
        [$org, $owner] = $this->orgWith(null);

        $this->actingAs($this->admin)->delete(route('admin.rbac.users.destroy', $owner))
            ->assertRedirect(route('admin.rbac.users'))->assertSessionMissing('error');

        $this->assertFalse(User::whereKey($owner->id)->exists());
        $this->assertFalse($this->orgExists($org->id), 'the solely owned empty org goes with the user');
    }

    public function test_admin_user_delete_succeeds_for_a_member_of_an_org_that_has_other_active_members(): void
    {
        // org A has projects but est is not its only member, so the org and its projects are not lost with him
        $projectsBefore = DB::table('projects')->count();

        $this->actingAs($this->admin)->delete(route('admin.rbac.users.destroy', $this->est))->assertSessionMissing('error');

        $this->assertFalse(User::whereKey($this->est->id)->exists());
        $this->assertTrue($this->orgExists($this->orgA->id));
        $this->assertSame($projectsBefore, DB::table('projects')->count());
    }

    public function test_admin_user_delete_is_closed_to_a_normal_user(): void
    {
        [$org, $owner] = $this->orgWith(null);

        $this->actingAs($this->est)->deleteJson(route('admin.rbac.users.destroy', $owner))->assertForbidden();

        $this->assertNothingDeleted($org, $owner, 0);
    }
}

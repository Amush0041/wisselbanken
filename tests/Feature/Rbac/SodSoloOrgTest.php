<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\SodService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, N01: Separation of Duties is skipped when the target is the only active member of the org (a solo
 * owner holds the whole onboarding bundle), and applies again to every new assignment once a second member exists.
 */
class SodSoloOrgTest extends ProjectTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    private function assignVia(User $actor, Organization $org, int $targetId, string $roleSlug)
    {
        return $this->actingAs($actor)
            ->withSession([config('rbac.current_org_session_key') => $org->id])
            ->postJson('org-admin/roles', ['user_id' => $targetId, 'role_id' => $this->roleId($roleSlug)]);
    }

    private function holds(User $u, Organization $org, string $roleSlug): bool
    {
        return DB::table('user_org_roles')
            ->where('user_id', $u->id)->where('org_id', $org->id)->where('role_id', $this->roleId($roleSlug))->where('is_active', true)
            ->exists();
    }

    private function soloOrg(string $name = 'Solo Org'): array
    {
        $org = $this->mkOrg($name);

        return [$org, $this->mkUser($org, 'organization_owner')];
    }

    #[DataProvider('modes')]
    public function test_a_solo_owner_can_hold_the_estimator_and_executive_approver_bundle(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->soloOrg();

        $this->assignVia($owner, $org, $owner->id, 'estimator')->assertOk()->assertJson(['success' => true]);
        $this->assignVia($owner, $org, $owner->id, 'executive_approver')->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($this->holds($owner, $org, 'estimator'));
        $this->assertTrue($this->holds($owner, $org, 'executive_approver'));
    }

    #[DataProvider('modes')]
    public function test_a_solo_owner_can_hold_the_project_manager_and_executive_approver_bundle(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->soloOrg();

        $this->assignVia($owner, $org, $owner->id, 'project_manager')->assertOk();
        $this->assignVia($owner, $org, $owner->id, 'executive_approver')->assertOk();
        $this->assignVia($owner, $org, $owner->id, 'requisitioner')->assertOk();

        foreach (['project_manager', 'executive_approver', 'requisitioner'] as $slug) {
            $this->assertTrue($this->holds($owner, $org, $slug), $slug);
        }
        $this->assertSame(1, DB::table('user_org_roles')->where('org_id', $org->id)->where('is_active', true)->distinct()->count('user_id'));
    }

    #[DataProvider('modes')]
    public function test_a_sole_member_is_exempt_for_every_active_rule_in_both_directions(string $mode): void
    {
        $this->setMode($mode);
        $rules = DB::table('sod_conflict_rules')->where('is_active', true)->get();
        $this->assertGreaterThanOrEqual(8, $rules->count());

        foreach ($rules as $rule) {
            $a = Role::findOrFail($rule->role_id_a);
            $b = Role::findOrFail($rule->role_id_b);

            foreach ([[$a, $b], [$b, $a]] as [$held, $incoming]) {
                [$org, $owner] = $this->soloOrg("Solo $held->slug $incoming->slug");
                $this->assignRole($owner, $org, $held->slug);

                $this->assignVia($owner, $org, $owner->id, $incoming->slug)->assertOk();

                $this->assertTrue($this->holds($owner, $org, $held->slug), "$held->slug then $incoming->slug");
                $this->assertTrue($this->holds($owner, $org, $incoming->slug), "$held->slug then $incoming->slug");
            }
        }
    }

    #[DataProvider('modes')]
    public function test_once_a_second_member_joins_separation_of_duties_applies_again(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->soloOrg();
        $this->assignVia($owner, $org, $owner->id, 'estimator')->assertOk();
        $this->assignVia($owner, $org, $owner->id, 'executive_approver')->assertOk();

        $hire = $this->mkUser($org, 'viewer_read_only');

        $r = $this->assignVia($owner, $org, $owner->id, 'requisitioner')->assertStatus(422);
        $this->assertTrue($r->json('sod_conflict'));
        $this->assertStringContainsString('SoD conflict', (string) $r->json('message'));
        $this->assertFalse($this->holds($owner, $org, 'requisitioner'));

        $this->assertTrue($this->holds($owner, $org, 'estimator'), 'nothing is revoked retroactively');
        $this->assertTrue($this->holds($owner, $org, 'executive_approver'), 'nothing is revoked retroactively');
        $this->assertTrue($this->holds($hire, $org, 'viewer_read_only'));
    }

    #[DataProvider('modes')]
    public function test_the_new_member_is_not_exempt_either_way_round(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->soloOrg();
        $first = $this->mkUser(null, null);
        $this->assignRole($first, $org, 'procurement_manager');
        $second = $this->mkUser(null, null);
        $this->assignRole($second, $org, 'executive_approver');

        $r1 = $this->assignVia($owner, $org, $first->id, 'executive_approver')->assertStatus(422);
        $r2 = $this->assignVia($owner, $org, $second->id, 'procurement_manager')->assertStatus(422);

        $this->assertTrue($r1->json('sod_conflict'));
        $this->assertTrue($r2->json('sod_conflict'));
        $this->assertFalse($this->holds($first, $org, 'executive_approver'));
        $this->assertFalse($this->holds($second, $org, 'procurement_manager'));
    }

    #[DataProvider('modes')]
    public function test_being_the_sole_member_of_another_org_does_not_exempt(string $mode): void
    {
        $this->setMode($mode);
        [$soloOrg, $soloOwner] = $this->soloOrg('Own Solo Org');
        $busyOrg = $this->mkOrg('Busy Org');
        $busyOwner = $this->mkUser($busyOrg, 'organization_owner');
        $this->mkUser($busyOrg, 'viewer_read_only');
        $this->assignRole($soloOwner, $busyOrg, 'procurement_manager');

        $this->assignVia($busyOwner, $busyOrg, $soloOwner->id, 'executive_approver')->assertStatus(422)->assertJson(['sod_conflict' => true]);

        $this->assertFalse($this->holds($soloOwner, $busyOrg, 'executive_approver'));

        $this->assignVia($soloOwner, $soloOrg, $soloOwner->id, 'procurement_manager')->assertOk();
        $this->assignVia($soloOwner, $soloOrg, $soloOwner->id, 'executive_approver')->assertOk();
        $this->assertTrue($this->holds($soloOwner, $soloOrg, 'executive_approver'));
    }

    #[DataProvider('modes')]
    public function test_inactive_rows_of_other_people_do_not_count_as_members(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->soloOrg();
        $left = $this->mkUser($org, 'viewer_read_only');
        DB::table('user_org_roles')->where('user_id', $left->id)->update(['is_active' => false]);

        $this->assignVia($owner, $org, $owner->id, 'estimator')->assertOk();
        $this->assignVia($owner, $org, $owner->id, 'executive_approver')->assertOk();

        $this->assertTrue($this->holds($owner, $org, 'executive_approver'));
    }

    #[DataProvider('modes')]
    public function test_a_member_with_any_active_row_ends_the_exemption(string $mode): void
    {
        $this->setMode($mode);
        [$org, $owner] = $this->soloOrg();
        $this->assignVia($owner, $org, $owner->id, 'estimator')->assertOk();
        $other = $this->mkUser($org, 'viewer_read_only');
        $this->assignRole($other, $org, 'foreman', false);

        $this->assignVia($owner, $org, $owner->id, 'executive_approver')->assertStatus(422);

        $this->assertFalse($this->holds($owner, $org, 'executive_approver'));
    }

    #[DataProvider('modes')]
    public function test_a_multi_member_org_is_unchanged(string $mode): void
    {
        $this->setMode($mode);
        $owner = $this->mkUser($this->orgA, 'organization_owner');
        $holder = $this->mkUser($this->orgA, 'procurement_manager');

        $this->assignVia($owner, $this->orgA, $holder->id, 'executive_approver')->assertStatus(422)->assertJson(['sod_conflict' => true]);

        $free = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->assignVia($owner, $this->orgA, $free->id, 'executive_approver')->assertOk();
        $this->assertTrue($this->holds($free, $this->orgA, 'executive_approver'));
    }

    public function test_the_service_reports_no_conflict_for_a_sole_member_and_names_them_for_a_second_member(): void
    {
        [$org, $owner] = $this->soloOrg();
        $this->assignRole($owner, $org, 'estimator');
        $ea = $this->roleId('executive_approver');
        $service = app(SodService::class);

        $this->assertSame([], $service->conflictsFor($owner->id, $org->id, $ea));

        $this->mkUser($org, 'viewer_read_only');

        $this->assertSame(['Estimator'], $service->conflictsFor($owner->id, $org->id, $ea));
    }

    public function test_an_org_with_no_active_member_has_no_exemption_and_no_conflict(): void
    {
        $org = $this->mkOrg('Empty Org');
        $ghost = $this->mkUser(null, null);
        $this->assignRole($ghost, $org, 'estimator', false);

        $this->assertSame([], app(SodService::class)->conflictsFor($ghost->id, $org->id, $this->roleId('executive_approver')));
    }
}

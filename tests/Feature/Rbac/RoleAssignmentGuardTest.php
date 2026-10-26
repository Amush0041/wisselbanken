<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, O10 / S03 (org screens): POST org-admin/roles hands a role only to an active member of the acting
 * organization and never a role the organization may not assign. The platform-admin screen keeps assigning anything.
 */
class RoleAssignmentGuardTest extends ProjectTestCase
{
    private const NOT_MEMBER = 'Only existing members of your organization can receive a role. Invite the person first.';
    private const NO_PLATFORM = 'Platform roles can only be assigned by a platform administrator.';
    private const NO_SELLER = 'Seller roles can only be assigned in manufacturer or distributor organizations.';
    private const PLATFORM_SLUGS = [
        'platform_super_admin', 'platform_operations', 'platform_support', 'platform_data_reviewer',
        'platform_catalog_admin', 'platform_compliance_officer', 'platform_finance_billing',
    ];

    private User $ownerA;
    private User $member;
    private User $ua;
    private $orgC;
    private User $ownerC;
    private User $removed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->member = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->orgC = $this->mkOrg('Org C');
        $this->ownerC = $this->mkUser($this->orgC, 'organization_owner');
        $this->removed = $this->mkUser($this->orgA, 'viewer_read_only');
        DB::table('user_org_roles')->where('user_id', $this->removed->id)->update(['is_active' => false]);
        $this->ua = User::factory()->create(['role' => 'user']);
        (require database_path('migrations/2026_08_03_135044_create_org_invites_table.php'))->up();
        Mail::fake();

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

    private function assign(User $actor, int $targetId, string $roleSlug, ?int $orgId = null)
    {
        return $this->actingAs($actor)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->postJson('org-admin/roles', ['user_id' => $targetId, 'role_id' => $this->roleId($roleSlug)]);
    }

    private function holds(User $u, int $orgId, string $roleSlug): bool
    {
        return DB::table('user_org_roles')
            ->where('user_id', $u->id)->where('org_id', $orgId)->where('role_id', $this->roleId($roleSlug))->where('is_active', true)
            ->exists();
    }

    private function assertBlockedControllerDenial(User $actor, string $reason = 'controller_denied'): void
    {
        $row = AuditLog::where('user_id', $actor->id)->where('route_uri', 'org-admin/roles')->latest('id')->first();
        $this->assertNotNull($row, 'denial must leave an audit row');
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame($reason, $row->reason);
    }

    // ---- A1: membership --------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_owner_assigns_a_role_to_an_active_member(string $mode): void
    {
        $this->setMode($mode);

        $this->assign($this->ownerA, $this->member->id, 'superintendent')
            ->assertOk()->assertJson(['success' => true, 'role_name' => 'Superintendent']);

        $this->assertTrue($this->holds($this->member, $this->orgA->id, 'superintendent'));
        $this->assertSame(1, DB::table('role_assignment_logs')->where('target_user_id', $this->member->id)->where('action', 'assigned')->count());
        $this->assertSame(0, AuditLog::where('outcome', 'blocked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_user_who_owns_another_org_and_has_no_row_here_is_refused(string $mode): void
    {
        $this->setMode($mode);
        $before = DB::table('user_org_roles')->count();

        $this->assign($this->ownerA, $this->ownerC->id, 'superintendent')
            ->assertForbidden()->assertJson(['message' => self::NOT_MEMBER]);

        $this->assertFalse($this->holds($this->ownerC, $this->orgA->id, 'superintendent'));
        $this->assertSame($before, DB::table('user_org_roles')->count());
        $this->assertSame(0, DB::table('role_assignment_logs')->where('target_user_id', $this->ownerC->id)->count());
        $this->assertBlockedControllerDenial($this->ownerA);
    }

    #[DataProvider('modes')]
    public function test_a_user_whose_rows_here_are_all_inactive_is_refused(string $mode): void
    {
        $this->setMode($mode);
        $before = DB::table('user_org_roles')->count();

        $this->assign($this->ownerA, $this->removed->id, 'superintendent')->assertForbidden()->assertJson(['message' => self::NOT_MEMBER]);

        $this->assertSame($before, DB::table('user_org_roles')->count());
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $this->removed->id)->where('is_active', true)->count());
        $this->assertBlockedControllerDenial($this->ownerA);
    }

    #[DataProvider('modes')]
    public function test_a_member_of_org_b_only_is_refused_while_acting_in_org_a(string $mode): void
    {
        $this->setMode($mode);
        $before = DB::table('user_org_roles')->count();

        $this->assign($this->ownerA, $this->outsider->id, 'superintendent')->assertForbidden()->assertJson(['message' => self::NOT_MEMBER]);

        $this->assertFalse($this->holds($this->outsider, $this->orgA->id, 'superintendent'));
        $this->assertSame($before, DB::table('user_org_roles')->count());
        $this->assertBlockedControllerDenial($this->ownerA);
    }

    #[DataProvider('modes')]
    public function test_the_owner_of_another_org_cannot_reach_a_member_of_org_a_from_their_own_org(string $mode): void
    {
        $this->setMode($mode);

        $this->assign($this->ownerC, $this->member->id, 'superintendent', $this->orgC->id)->assertForbidden();

        $this->assertFalse($this->holds($this->member, $this->orgC->id, 'superintendent'));
        $this->assertFalse($this->holds($this->member, $this->orgA->id, 'superintendent'));
    }

    #[DataProvider('modes')]
    public function test_a_caller_without_user_management_f_is_still_refused(string $mode): void
    {
        $this->setMode($mode);

        $this->assign($this->viewer, $this->member->id, 'superintendent')->assertForbidden();

        $this->assertFalse($this->holds($this->member, $this->orgA->id, 'superintendent'));
        $this->assertBlockedControllerDenial($this->viewer, 'no_grant');
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_may_assign_to_a_non_member_and_it_is_logged(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->assign($this->ua, $this->ownerC->id, 'superintendent')->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($this->holds($this->ownerC, $this->orgA->id, 'superintendent'));
        $row = AuditLog::where('outcome', 'allowed_universal_admin')->where('reason', 'universal_assign_non_member')->first();
        $this->assertNotNull($row);
        $this->assertSame($this->ua->id, (int) $row->user_id);
        $this->assertSame($this->orgA->id, (int) $row->org_id);
        $this->assertSame('user_management', $row->permission_group);
        $this->assertSame('F', $row->required_level);
    }

    public function test_a_universal_admin_assigning_to_a_member_leaves_no_non_member_row(): void
    {
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->assign($this->ua, $this->member->id, 'superintendent')->assertOk();

        $this->assertSame(0, AuditLog::where('reason', 'universal_assign_non_member')->count());
    }

    public function test_a_user_who_is_not_on_the_universal_list_gets_no_bypass(): void
    {
        config(['rbac.universal_admin_user_ids' => [$this->ownerC->id]]);

        $this->assign($this->ownerA, $this->ownerC->id, 'superintendent')->assertForbidden();

        $this->assertSame(0, AuditLog::where('outcome', 'allowed_universal_admin')->count());
        $this->assertFalse($this->holds($this->ownerC, $this->orgA->id, 'superintendent'));
    }

    // ---- A2: Platform and Seller roles ---------------------------------------------------------

    private function typedOrg(string $name, string $type): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => $type, 'team_size' => 'solo']);
    }

    private function invite(User $actor, array $body, ?int $orgId = null)
    {
        return $this->actingAs($actor)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->postJson('org-admin/invite', $body + ['send_email' => false]);
    }

    private function inviteRows(): int
    {
        return DB::table('org_invites')->count();
    }

    #[DataProvider('modes')]
    public function test_every_platform_role_is_refused_on_assign(string $mode): void
    {
        $this->setMode($mode);
        $before = DB::table('user_org_roles')->count();

        foreach (self::PLATFORM_SLUGS as $slug) {
            $this->assign($this->ownerA, $this->member->id, $slug)->assertForbidden()->assertJson(['message' => self::NO_PLATFORM]);
            $this->assertFalse($this->holds($this->member, $this->orgA->id, $slug), $slug);
            $this->assertBlockedControllerDenial($this->ownerA);
        }

        $this->assertSame($before, DB::table('user_org_roles')->count());
        $this->assertSame(0, DB::table('role_assignment_logs')->where('target_user_id', $this->member->id)->count());
    }

    #[DataProvider('modes')]
    public function test_every_platform_role_is_refused_on_invite_for_a_new_and_an_existing_account(string $mode): void
    {
        $this->setMode($mode);
        $before = DB::table('user_org_roles')->count();

        foreach (self::PLATFORM_SLUGS as $slug) {
            $role = $this->roleId($slug);
            $this->invite($this->ownerA, ['email' => 'fresh@example.test', 'name' => 'Fresh', 'role_id' => $role])
                ->assertForbidden()->assertJson(['message' => self::NO_PLATFORM]);
            $this->invite($this->ownerA, ['email' => $this->ownerC->email, 'name' => 'Existing', 'role_id' => $role])
                ->assertForbidden()->assertJson(['message' => self::NO_PLATFORM]);
            $this->invite($this->ownerA, ['email' => $this->ownerC->email, 'name' => 'Existing', 'role_id' => $role, 'confirmed' => true])
                ->assertForbidden()->assertJson(['message' => self::NO_PLATFORM]);
        }

        $this->assertSame(0, $this->inviteRows());
        $this->assertSame($before, DB::table('user_org_roles')->count());
        $this->assertFalse($this->holds($this->ownerC, $this->orgA->id, 'platform_super_admin'));
    }

    #[DataProvider('modes')]
    public function test_a_seller_role_is_refused_in_a_buyer_org_on_assign_and_invite(string $mode): void
    {
        $this->setMode($mode);

        $this->assign($this->ownerA, $this->member->id, 'sales_rep')->assertForbidden()->assertJson(['message' => self::NO_SELLER]);
        $this->invite($this->ownerA, ['email' => 'fresh@example.test', 'name' => 'Fresh', 'role_id' => $this->roleId('sales_rep')])
            ->assertForbidden()->assertJson(['message' => self::NO_SELLER]);
        $this->invite($this->ownerA, ['email' => $this->ownerC->email, 'name' => 'Existing', 'role_id' => $this->roleId('sales_rep'), 'confirmed' => true])
            ->assertForbidden()->assertJson(['message' => self::NO_SELLER]);

        $this->assertFalse($this->holds($this->member, $this->orgA->id, 'sales_rep'));
        $this->assertFalse($this->holds($this->ownerC, $this->orgA->id, 'sales_rep'));
        $this->assertSame(0, $this->inviteRows());
    }

    #[DataProvider('modes')]
    public function test_a_seller_role_is_allowed_in_manufacturer_and_distributor_orgs(string $mode): void
    {
        $this->setMode($mode);

        foreach (['manufacturer', 'distributor_stocking', 'distributor_non_stocking'] as $type) {
            $org = $this->typedOrg("Org $type", $type);
            $owner = $this->mkUser($org, 'organization_owner');
            $staff = $this->mkUser($org, 'viewer_read_only');

            $this->assign($owner, $staff->id, 'sales_rep', $org->id)->assertOk()->assertJson(['success' => true]);
            $this->assertTrue($this->holds($staff, $org->id, 'sales_rep'), $type);

            $this->invite($owner, ['email' => "new-$type@example.test", 'name' => 'New', 'role_id' => $this->roleId('pricing_manager')], $org->id)
                ->assertOk()->assertJson(['type' => 'invite']);
        }

        $this->assertSame(3, $this->inviteRows());
    }

    #[DataProvider('modes')]
    public function test_a_platform_role_is_refused_even_in_a_manufacturer_org(string $mode): void
    {
        $this->setMode($mode);
        $org = $this->typedOrg('Mfr', 'manufacturer');
        $owner = $this->mkUser($org, 'organization_owner');
        $staff = $this->mkUser($org, 'viewer_read_only');

        $this->assign($owner, $staff->id, 'platform_operations', $org->id)->assertForbidden();
        $this->invite($owner, ['email' => 'x@example.test', 'name' => 'X', 'role_id' => $this->roleId('platform_operations')], $org->id)->assertForbidden();

        $this->assertFalse($this->holds($staff, $org->id, 'platform_operations'));
        $this->assertSame(0, $this->inviteRows());
    }

    #[DataProvider('modes')]
    public function test_organization_procurement_project_cross_functional_and_other_roles_stay_assignable(string $mode): void
    {
        $this->setMode($mode);

        foreach (['organization_admin', 'financial_admin', 'procurement_coordinator', 'project_manager', 'architect', 'foreman', 'auditor_read_all', 'delegate_proxy'] as $slug) {
            $this->assign($this->ownerA, $this->member->id, $slug)->assertOk();
            $this->assertTrue($this->holds($this->member, $this->orgA->id, $slug), $slug);
        }

        $this->invite($this->ownerA, ['email' => 'fresh@example.test', 'name' => 'Fresh', 'role_id' => $this->roleId('estimator')])
            ->assertOk()->assertJson(['type' => 'invite']);
        $this->assertSame(1, $this->inviteRows());
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_may_assign_and_invite_restricted_roles_and_it_is_logged(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->assign($this->ua, $this->member->id, 'platform_operations')->assertOk();
        $this->assign($this->ua, $this->member->id, 'sales_rep')->assertOk();
        $this->invite($this->ua, ['email' => 'fresh@example.test', 'name' => 'Fresh', 'role_id' => $this->roleId('sales_rep')])->assertOk();

        $this->assertTrue($this->holds($this->member, $this->orgA->id, 'platform_operations'));
        $this->assertTrue($this->holds($this->member, $this->orgA->id, 'sales_rep'));
        $this->assertGreaterThanOrEqual(1, AuditLog::where('outcome', 'allowed_universal_admin')->where('reason', 'universal_assign_restricted_role')->count());
    }

    public function test_the_platform_admin_screen_still_assigns_a_platform_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.rbac.roles.assign', $this->orgA), [
            'user_id' => $this->member->id, 'role_id' => $this->roleId('platform_support'),
        ])->assertSessionHas('success', 'Role assigned.');

        $this->assertTrue($this->holds($this->member, $this->orgA->id, 'platform_support'));
    }

    #[DataProvider('modes')]
    public function test_an_org_owner_can_still_remove_a_stray_platform_assignment(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->member, $this->orgA, 'platform_support');
        $rowId = (int) DB::table('user_org_roles')->where('user_id', $this->member->id)->where('role_id', $this->roleId('platform_support'))->value('id');

        $this->actingAs($this->ownerA)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->delete("org-admin/roles/$rowId")->assertRedirect();

        $this->assertFalse($this->holds($this->member, $this->orgA->id, 'platform_support'));
    }

    // ---- A2: stored invites are re-validated when they are accepted -----------------------------

    private function storedInvite(string $roleSlug, ?Organization $org = null, string $token = 'tok-a2'): string
    {
        DB::table('org_invites')->insert([
            'token' => $token, 'email' => 'newbie@example.test', 'name' => 'Newbie', 'org_id' => ($org ?? $this->orgA)->id,
            'role_id' => $this->roleId($roleSlug), 'invited_by' => $this->ownerA->id, 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    private function acceptAsNewUser(string $token)
    {
        return $this->post("register/invite/$token", ['name' => 'Newbie', 'password' => 'password123', 'password_confirmation' => 'password123']);
    }

    public function test_a_valid_stored_invite_still_creates_the_user_and_assigns_the_role(): void
    {
        $token = $this->storedInvite('estimator');

        $this->acceptAsNewUser($token)->assertRedirect(route('user.dashboard'));

        $user = User::where('email', 'newbie@example.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($this->holds($user, $this->orgA->id, 'estimator'));
        $this->assertNotNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    public function test_a_stored_platform_role_invite_is_refused_at_accept(): void
    {
        foreach (self::PLATFORM_SLUGS as $i => $slug) {
            $token = $this->storedInvite($slug, null, "tok-$i");

            $this->acceptAsNewUser($token)->assertRedirect(route('login'))
                ->assertSessionHas('error', 'This invite link is invalid or has expired.');

            $this->assertNull(DB::table('users')->where('email', 'newbie@example.test')->first(), $slug);
            $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'), $slug);
        }
    }

    public function test_a_stored_seller_invite_is_refused_in_a_buyer_org_and_accepted_in_a_manufacturer_org(): void
    {
        $token = $this->storedInvite('sales_rep');
        $this->acceptAsNewUser($token)->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertNull(DB::table('users')->where('email', 'newbie@example.test')->first());

        $mfr = $this->typedOrg('Mfr', 'manufacturer');
        $token = $this->storedInvite('sales_rep', $mfr, 'tok-mfr');
        $this->acceptAsNewUser($token)->assertRedirect(route('user.dashboard'));
        $user = User::where('email', 'newbie@example.test')->first();
        $this->assertTrue($this->holds($user, $mfr->id, 'sales_rep'));
    }

    // ---- service helpers -------------------------------------------------------------------

    public function test_is_active_member_is_scoped_to_the_org_and_to_active_rows(): void
    {
        $svc = app(RoleAssignmentService::class);

        $this->assertTrue($svc->isActiveMember($this->member->id, $this->orgA->id));
        $this->assertFalse($svc->isActiveMember($this->member->id, $this->orgB->id));
        $this->assertFalse($svc->isActiveMember($this->removed->id, $this->orgA->id));
        $this->assertFalse($svc->isActiveMember($this->ownerC->id, $this->orgA->id));
        $this->assertTrue($svc->isActiveMember($this->multi->id, $this->orgB->id));
    }

    public function test_assign_itself_stays_unguarded_for_non_members(): void
    {
        $svc = app(RoleAssignmentService::class);

        $svc->assign($this->ownerA->id, $this->ownerC->id, $this->orgA->id, $this->roleId('superintendent'));

        $this->assertTrue($this->holds($this->ownerC, $this->orgA->id, 'superintendent'));
    }
}

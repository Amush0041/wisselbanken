<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Models\Rbac\Role;
use App\Services\Rbac\PermissionMatrix;
use App\Services\Rbac\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class InviteInviterAuthorityTest extends ProjectTestCase
{
    private const INVALID_PAGE = 'Invite link invalid or expired';

    private User $ownerA;
    private User $inviter;
    private User $invitee;
    private User $third;

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_08_03_135044_create_org_invites_table.php'))->up();
        Mail::fake();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->inviter = $this->mkUser($this->orgA, 'organization_owner');
        $orgC = $this->mkOrg('Org C');
        $this->invitee = $this->mkUser($orgC, 'viewer_read_only');
        $this->third = $this->mkUser($orgC, 'viewer_read_only');

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function demotedRoles(): array
    {
        $cases = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach (['financial_admin', 'project_manager', 'executive_approver'] as $slug) {
                $cases["$slug $mode"] = [$slug, $mode];
            }
        }

        return $cases;
    }

    public static function universalAdminLosses(): array
    {
        $cases = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach (['removed from the list', 'unverified'] as $variant) {
                $cases["$variant $mode"] = [$variant, $mode];
            }
        }

        return $cases;
    }

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    private function holds(User $u, string $slug, ?int $orgId = null): bool
    {
        return DB::table('user_org_roles')->where('user_id', $u->id)->where('org_id', $orgId ?? $this->orgA->id)
            ->where('role_id', $this->roleId($slug))->where('is_active', true)->exists();
    }

    private function invite(User $inviter, ?string $email = null, string $roleSlug = 'estimator'): string
    {
        $email ??= $this->invitee->email;

        $this->actingAs($inviter)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->postJson('org-admin/invite', ['email' => $email, 'name' => 'Invitee', 'role_id' => $this->roleId($roleSlug)])
            ->assertOk()->assertJson(['type' => 'invite']);

        $row = DB::table('org_invites')->where('email', $email)->orderByDesc('id')->first();
        $this->assertSame($inviter->id, (int) $row->invited_by);

        return $row->token;
    }

    private function storedInvite(string $email, int $invitedBy, string $token = 'tok-stored', string $roleSlug = 'estimator', array $over = []): string
    {
        DB::table('org_invites')->insert($over + [
            'token' => $token, 'email' => $email, 'name' => 'Invitee', 'org_id' => $this->orgA->id,
            'role_id' => $this->roleId($roleSlug), 'invited_by' => $invitedBy, 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }

    private function accept(User $u, string $token, string $method = 'post', ?int $sessionOrg = null)
    {
        $request = $sessionOrg === null ? $this->actingAs($u) : $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $sessionOrg]);

        return $request->{$method}("invite/$token/accept");
    }

    private function leaveOrg(User $u, ?int $orgId = null): void
    {
        DB::table('user_org_roles')->where('user_id', $u->id)->where('org_id', $orgId ?? $this->orgA->id)->update(['is_active' => false]);
    }

    private function assertRefused(User $invitee, string $token, bool $invalidPage = true, ?int $sessionOrg = null): void
    {
        $before = [
            DB::table('user_org_roles')->count(),
            DB::table('user_org_roles')->where('user_id', $invitee->id)->count(),
            DB::table('role_assignment_logs')->count(),
            DB::table('rbac_audit_logs')->count(),
        ];

        foreach (['get', 'post'] as $method) {
            $response = $this->accept($invitee, $token, $method, $sessionOrg)->assertNotFound();
            if ($invalidPage) {
                $response->assertSee(self::INVALID_PAGE);
            }
        }

        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $this->assertSame($before, [
            DB::table('user_org_roles')->count(),
            DB::table('user_org_roles')->where('user_id', $invitee->id)->count(),
            DB::table('role_assignment_logs')->count(),
            DB::table('rbac_audit_logs')->count(),
        ]);
        $this->assertFalse($this->holds($invitee, 'estimator'));
    }

    private function assertUsable(User $invitee, string $token, User $performedBy): void
    {
        $this->accept($invitee, $token, 'get')->assertOk()->assertSee('Accept invitation');
        $this->accept($invitee, $token)->assertRedirect(route('user.dashboard'));

        $this->assertTrue($this->holds($invitee, 'estimator'));
        $this->assertNotNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $log = DB::table('role_assignment_logs')->where('target_user_id', $invitee->id)->where('org_id', $this->orgA->id)->where('action', 'assigned')->first();
        $this->assertSame($performedBy->id, (int) $log->performed_by);
    }

    #[DataProvider('modes')]
    public function test_a_valid_inviter_leaves_the_invite_usable(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);

        $this->assertUsable($this->invitee, $token, $this->inviter);
    }

    #[DataProvider('modes')]
    public function test_an_inviter_who_changed_to_another_full_user_management_role_is_still_valid(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        $this->leaveOrg($this->inviter);
        $this->assignRole($this->inviter, $this->orgA, 'organization_admin');

        $this->assertUsable($this->invitee, $token, $this->inviter);
    }

    #[DataProvider('modes')]
    public function test_a_removed_inviter_invalidates_the_invite_on_review_and_accept(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        $this->leaveOrg($this->inviter);

        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('demotedRoles')]
    public function test_an_inviter_demoted_below_full_user_management_invalidates_the_invite(string $roleSlug, string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        $this->leaveOrg($this->inviter);
        $this->assignRole($this->inviter, $this->orgA, $roleSlug);

        $this->assertTrue(DB::table('user_org_roles')->where('user_id', $this->inviter->id)->where('org_id', $this->orgA->id)->where('is_active', true)->exists());
        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('modes')]
    public function test_an_inviter_whose_authority_is_in_another_org_invalidates_the_invite(string $mode): void
    {
        $this->setMode($mode);
        $ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->assignRole($this->invitee, $this->orgB, 'viewer_read_only');
        $token = $this->storedInvite($this->invitee->email, $ownerB->id);

        $this->assertRefused($this->invitee, $token, true, $this->orgB->id);
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_inviter_with_no_role_in_the_org_keeps_a_usable_invite(string $mode): void
    {
        $this->setMode($mode);
        $ua = User::factory()->create(['role' => 'user']);
        config(['rbac.universal_admin_user_ids' => [$ua->id]]);
        $token = $this->invite($ua);
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $ua->id)->count());
        $bypassRows = fn () => DB::table('rbac_audit_logs')->where('outcome', 'allowed_universal_admin')->count();
        $afterCreation = $bypassRows();
        $this->assertGreaterThan(0, $afterCreation);

        $this->assertUsable($this->invitee, $token, $ua);

        $this->assertSame($afterCreation, $bypassRows());
    }

    #[DataProvider('universalAdminLosses')]
    public function test_a_universal_admin_inviter_who_is_no_longer_listed_or_verified_invalidates_the_invite(string $variant, string $mode): void
    {
        $this->setMode($mode);
        $ua = User::factory()->create(['role' => 'user']);
        config(['rbac.universal_admin_user_ids' => [$ua->id]]);
        $token = $this->invite($ua);

        if ($variant === 'unverified') {
            DB::table('users')->where('id', $ua->id)->update(['email_verified_at' => null]);
        } else {
            config(['rbac.universal_admin_user_ids' => []]);
        }

        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('modes')]
    public function test_a_deleted_inviter_leaves_no_usable_invite(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        DB::table('user_org_roles')->where('user_id', $this->inviter->id)->delete();
        DB::table('users')->where('id', $this->inviter->id)->delete();
        $roles = DB::table('user_org_roles')->count();

        $this->accept($this->invitee, $token, 'get')->assertNotFound();
        $this->accept($this->invitee, $token)->assertNotFound();

        $this->assertFalse($this->holds($this->invitee, 'estimator'));
        $this->assertSame($roles, DB::table('user_org_roles')->count());
        $this->assertSame(0, DB::table('role_assignment_logs')->where('target_user_id', $this->invitee->id)->count());
    }

    #[DataProvider('modes')]
    public function test_a_delegate_inviter_is_valid_only_while_the_delegation_and_own_membership_last(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'viewer_read_only');
        $delegationId = DB::table('delegations')->insertGetId([
            'from_user_id' => $this->inviter->id, 'to_user_id' => $delegate->id, 'org_id' => $this->orgA->id,
            'granted_by' => $this->inviter->id, 'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $token = $this->invite($delegate);

        $this->accept($this->invitee, $token, 'get')->assertOk()->assertSee('Accept invitation');

        DB::table('delegations')->where('id', $delegationId)->update(['is_active' => false]);
        $this->assertRefused($this->invitee, $token);

        DB::table('delegations')->where('id', $delegationId)->update(['is_active' => true]);
        $this->accept($this->invitee, $token, 'get')->assertOk();

        $this->leaveOrg($delegate);
        $this->assertRefused($this->invitee, $token);

        DB::table('user_org_roles')->where('user_id', $delegate->id)->where('org_id', $this->orgA->id)->update(['is_active' => true]);
        $this->assertUsable($this->invitee, $token, $delegate);
    }

    #[DataProvider('modes')]
    public function test_the_refusal_is_indistinguishable_from_an_expired_invite_and_hidden_from_other_accounts(string $mode): void
    {
        $this->setMode($mode);
        $expired = $this->storedInvite($this->invitee->email, $this->ownerA->id, 'tok-expired', 'estimator', ['expires_at' => now()->subMinute()]);
        $orphaned = $this->storedInvite($this->invitee->email, $this->inviter->id, 'tok-orphaned');
        $this->leaveOrg($this->inviter);

        foreach (['get', 'post'] as $method) {
            $expiredResponse = $this->accept($this->invitee, $expired, $method)->assertNotFound();
            $orphanedResponse = $this->accept($this->invitee, $orphaned, $method)->assertNotFound();
            $this->assertSame($expiredResponse->getContent(), $orphanedResponse->getContent());
            $this->assertStringContainsString(self::INVALID_PAGE, $orphanedResponse->getContent());
        }

        DB::table('user_org_roles')->where('user_id', $this->inviter->id)->where('org_id', $this->orgA->id)->update(['is_active' => true]);
        $beforeRemoval = [];
        foreach (['get', 'post'] as $method) {
            $beforeRemoval[$method] = $this->accept($this->third, $orphaned, $method)->assertNotFound()->getContent();
        }
        $this->leaveOrg($this->inviter);
        foreach (['get', 'post'] as $method) {
            $afterRemoval = $this->accept($this->third, $orphaned, $method)->assertNotFound()->getContent();
            $this->assertSame($beforeRemoval[$method], $afterRemoval);
            $this->assertStringNotContainsString(self::INVALID_PAGE, $afterRemoval);
        }
        $this->assertNull(DB::table('org_invites')->where('token', $orphaned)->value('used_at'));
        $this->assertFalse($this->holds($this->third, 'estimator'));
    }

    #[DataProvider('modes')]
    public function test_a_current_admin_can_re_invite_after_the_inviter_left(string $mode): void
    {
        $this->setMode($mode);
        $old = $this->invite($this->inviter);
        $this->leaveOrg($this->inviter);
        $this->assertRefused($this->invitee, $old);

        $new = $this->invite($this->ownerA);

        $this->assertNotSame($old, $new);
        $this->accept($this->invitee, $old, 'get')->assertNotFound();
        $this->accept($this->invitee, $old)->assertNotFound();
        $this->assertUsable($this->invitee, $new, $this->ownerA);
    }

    #[DataProvider('modes')]
    public function test_the_guest_register_link_is_deliberately_unchanged(string $mode): void
    {
        // Pins "A1 covers the accept route only"; flip it if the guest-link parity follow-up (ARCHITECTURE 12 item 13) is ever approved.
        $this->setMode($mode);
        $token = $this->invite($this->inviter, 'brand-new@example.test');
        $this->leaveOrg($this->inviter);
        $this->app['auth']->forgetGuards();

        $this->get("register/invite/$token")->assertOk();
        $this->post("register/invite/$token", ['name' => 'Newbie', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('user.dashboard'));

        $user = User::where('email', 'brand-new@example.test')->first();
        $this->assertTrue($this->holds($user, 'estimator'));
        $this->assertNotNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
    }

    #[DataProvider('modes')]
    public function test_an_inviter_holding_only_user_management_a_through_a_custom_role_invalidates_the_invite(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        $this->leaveOrg($this->inviter);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Custom User Admin', 'slug' => 'custom_user_admin', 'category' => 'Organization', 'phase' => 'P1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $roleId, 'permission_group_id' => DB::table('permission_groups')->where('slug', 'user_management')->value('id'),
            'access_level' => 'A', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PermissionMatrix::class)->flush();
        DB::table('user_org_roles')->insert([
            'user_id' => $this->inviter->id, 'org_id' => $this->orgA->id, 'role_id' => $roleId,
            'assigned_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $permissions = app(PermissionService::class);
        $this->assertTrue($permissions->checkPermission($this->inviter->id, $this->orgA->id, 'user_management', 'A'));
        $this->assertFalse($permissions->checkPermission($this->inviter->id, $this->orgA->id, 'user_management', 'F'));
        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_inviter_who_is_also_a_low_level_member_writes_no_bypass_row_at_accept(string $mode): void
    {
        $this->setMode($mode);
        $ua = User::factory()->create(['role' => 'user']);
        $this->assignRole($ua, $this->orgA, 'viewer_read_only');
        config(['rbac.universal_admin_user_ids' => [$ua->id]]);
        $token = $this->invite($ua);
        $bypassRows = fn () => DB::table('rbac_audit_logs')->where('outcome', 'allowed_universal_admin')->count();
        $afterCreation = $bypassRows();
        $this->assertGreaterThan(0, $afterCreation);

        $this->assertUsable($this->invitee, $token, $ua);

        $this->assertSame($afterCreation, $bypassRows());
    }

    #[DataProvider('modes')]
    public function test_an_inviter_with_full_authority_only_in_the_invitees_session_org_invalidates_the_invite(string $mode): void
    {
        $this->setMode($mode);
        $inviter = $this->mkUser($this->orgA, 'project_manager');
        $this->assignRole($inviter, $this->orgB, 'organization_owner');
        $this->assignRole($this->invitee, $this->orgB, 'viewer_read_only');
        $token = $this->storedInvite($this->invitee->email, $inviter->id);

        $this->assertRefused($this->invitee, $token, true, $this->orgB->id);
        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('modes')]
    public function test_a_delegate_whose_own_membership_is_in_another_org_invalidates_the_invite(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgB, 'viewer_read_only');
        DB::table('delegations')->insert([
            'from_user_id' => $this->inviter->id, 'to_user_id' => $delegate->id, 'org_id' => $this->orgA->id,
            'granted_by' => $this->inviter->id, 'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertTrue(app(PermissionService::class)->checkPermission($delegate->id, $this->orgA->id, 'user_management', 'F'));
        $this->assignRole($this->invitee, $this->orgB, 'viewer_read_only');
        $token = $this->storedInvite($this->invitee->email, $delegate->id);

        $this->assertRefused($this->invitee, $token, true, $this->orgB->id);
        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_invitee_does_not_vouch_for_a_removed_inviter(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        $this->leaveOrg($this->inviter);
        config(['rbac.universal_admin_user_ids' => [$this->invitee->id]]);

        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('modes')]
    public function test_an_invite_row_that_outlived_its_inviter_row_fails_closed_without_a_server_error(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        Schema::disableForeignKeyConstraints();
        DB::table('user_org_roles')->where('user_id', $this->inviter->id)->delete();
        DB::table('users')->where('id', $this->inviter->id)->delete();
        Schema::enableForeignKeyConstraints();
        $this->assertSame(1, DB::table('org_invites')->where('token', $token)->count());

        $this->assertRefused($this->invitee, $token);
    }

    #[DataProvider('modes')]
    public function test_the_guest_register_page_still_shows_the_form_after_the_inviter_left(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter, 'brand-new@example.test');
        $this->leaveOrg($this->inviter);
        $this->app['auth']->forgetGuards();

        $this->get("register/invite/$token")->assertOk()->assertSee('been invited')->assertDontSee(self::INVALID_PAGE);
    }

    #[DataProvider('modes')]
    public function test_only_roles_holding_user_management_f_keep_an_invite_valid_across_every_seeded_role(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);

        $levels = DB::table('role_permissions as rp')
            ->join('permission_groups as pg', 'pg.id', '=', 'rp.permission_group_id')
            ->where('pg.slug', 'user_management')
            ->pluck('rp.access_level', 'rp.role_id');
        $withF = $withoutF = [];
        foreach (DB::table('roles')->pluck('slug', 'id') as $id => $slug) {
            ($levels[$id] ?? null) === 'F' ? $withF[$id] = $slug : $withoutF[$id] = $slug;
        }
        $this->assertNotEmpty($withF);
        $this->assertGreaterThan(count($withF), count($withoutF));

        foreach ($withF as $roleId => $slug) {
            $this->leaveOrg($this->inviter);
            DB::table('user_org_roles')->updateOrInsert(
                ['user_id' => $this->inviter->id, 'org_id' => $this->orgA->id, 'role_id' => $roleId],
                ['assigned_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            $this->accept($this->invitee, $token, 'get')->assertOk();
        }

        foreach ($withoutF as $roleId => $slug) {
            $this->leaveOrg($this->inviter);
            DB::table('user_org_roles')->updateOrInsert(
                ['user_id' => $this->inviter->id, 'org_id' => $this->orgA->id, 'role_id' => $roleId],
                ['assigned_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            $this->accept($this->invitee, $token, 'get')->assertNotFound();
            $this->accept($this->invitee, $token)->assertNotFound();
        }

        $this->assertNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $this->assertFalse($this->holds($this->invitee, 'estimator'));
    }

    #[DataProvider('modes')]
    public function test_the_refusal_flashes_nothing_to_the_session(string $mode): void
    {
        $this->setMode($mode);
        $token = $this->invite($this->inviter);
        $this->leaveOrg($this->inviter);

        foreach (['get', 'post'] as $method) {
            $this->accept($this->invitee, $token, $method)->assertNotFound()
                ->assertSessionMissing('error')->assertSessionMissing('status')->assertSessionMissing('success')
                ->assertSessionMissing('warning')->assertSessionMissing('info')->assertSessionHasNoErrors();
            $this->assertSame([], session()->get('_flash.old', []));
            $this->assertSame([], session()->get('_flash.new', []));
        }
    }
}

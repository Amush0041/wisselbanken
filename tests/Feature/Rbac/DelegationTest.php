<?php

namespace Tests\Feature\Rbac;

use App\Services\Rbac\DelegationService;
use App\Services\Rbac\PermissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DelegationTest extends RbacTestCase
{
    private DelegationService $service;
    private PermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service     = app(DelegationService::class);
        $this->permissions = app(PermissionService::class);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function makeUser(string $email): int
    {
        return DB::table('users')->insertGetId([
            'name'       => $email,
            'email'      => $email,
            'role'       => 'user',
            'password'   => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOrg(): int
    {
        return DB::table('organizations')->insertGetId([
            'name'       => 'Test Org',
            'org_type'   => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignRole(int $userId, int $orgId, string $roleSlug): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
        DB::table('user_org_roles')->insert([
            'user_id'     => $userId,
            'org_id'      => $orgId,
            'role_id'     => $roleId,
            'assigned_by' => $userId,
            'assigned_at' => now(),
            'is_active'   => true,
        ]);
    }

    // ─── resolveEffectiveUserId ────────────────────────────────────────────────

    /** @test */
    public function active_delegation_resolves_to_principal(): void
    {
        $orgId    = $this->makeOrg();
        $alice    = $this->makeUser('alice@test.com');
        $bob      = $this->makeUser('bob@test.com');

        $this->service->grant(
            grantedBy: $alice,
            fromUserId: $alice,
            toUserId: $bob,
            orgId: $orgId,
            startsAt: Carbon::now()->subMinute(),
            expiresAt: Carbon::now()->addHour(),
        );

        // Bob is acting as Alice — checkPermission should use Alice's user ID.
        $effective = $this->service->resolveEffectiveUserId($bob, $orgId);

        $this->assertSame($alice, $effective);
    }

    /** @test */
    public function no_delegation_resolves_to_self(): void
    {
        $orgId = $this->makeOrg();
        $alice = $this->makeUser('alice2@test.com');

        $this->assertSame($alice, $this->service->resolveEffectiveUserId($alice, $orgId));
    }

    /** @test */
    public function expired_delegation_is_ignored(): void
    {
        $orgId = $this->makeOrg();
        $alice = $this->makeUser('alice3@test.com');
        $bob   = $this->makeUser('bob3@test.com');

        $this->service->grant(
            grantedBy: $alice,
            fromUserId: $alice,
            toUserId: $bob,
            orgId: $orgId,
            startsAt: Carbon::now()->subHour(),
            expiresAt: Carbon::now()->subMinute(), // already expired
        );

        $this->assertSame($bob, $this->service->resolveEffectiveUserId($bob, $orgId));
    }

    /** @test */
    public function not_yet_started_delegation_is_ignored(): void
    {
        $orgId = $this->makeOrg();
        $alice = $this->makeUser('alice4@test.com');
        $bob   = $this->makeUser('bob4@test.com');

        $this->service->grant(
            grantedBy: $alice,
            fromUserId: $alice,
            toUserId: $bob,
            orgId: $orgId,
            startsAt: Carbon::now()->addHour(), // not started yet
            expiresAt: Carbon::now()->addDay(),
        );

        $this->assertSame($bob, $this->service->resolveEffectiveUserId($bob, $orgId));
    }

    /** @test */
    public function revoked_delegation_is_ignored(): void
    {
        $orgId = $this->makeOrg();
        $alice = $this->makeUser('alice5@test.com');
        $bob   = $this->makeUser('bob5@test.com');

        $delegation = $this->service->grant(
            grantedBy: $alice,
            fromUserId: $alice,
            toUserId: $bob,
            orgId: $orgId,
            startsAt: Carbon::now()->subMinute(),
            expiresAt: Carbon::now()->addHour(),
        );

        $this->service->revoke($delegation->id);

        $this->assertSame($bob, $this->service->resolveEffectiveUserId($bob, $orgId));
    }

    // ─── checkPermission integration ──────────────────────────────────────────

    /** @test */
    public function delegate_inherits_principals_roles_for_permission_check(): void
    {
        $orgId = $this->makeOrg();
        $alice = $this->makeUser('alice6@test.com'); // has Estimator role
        $bob   = $this->makeUser('bob6@test.com');   // has no roles

        $this->assignRole($alice, $orgId, 'estimator');

        // Without delegation Bob has no access.
        $this->assertFalse(
            $this->permissions->checkPermission($bob, $orgId, 'estimate_management', 'R')
        );

        // Grant Bob an active delegation from Alice.
        $this->service->grant(
            grantedBy: $alice,
            fromUserId: $alice,
            toUserId: $bob,
            orgId: $orgId,
            startsAt: Carbon::now()->subMinute(),
            expiresAt: Carbon::now()->addHour(),
        );

        // Now Bob's check uses Alice's roles → Estimator has Read on estimate_management.
        $this->assertTrue(
            $this->permissions->checkPermission($bob, $orgId, 'estimate_management', 'R')
        );
    }

    /** @test */
    public function delegate_does_not_exceed_principals_permission_level(): void
    {
        $orgId = $this->makeOrg();
        $alice = $this->makeUser('alice7@test.com'); // Requisitioner — only Submit on procurement
        $bob   = $this->makeUser('bob7@test.com');

        $this->assignRole($alice, $orgId, 'requisitioner');

        $this->service->grant(
            grantedBy: $alice,
            fromUserId: $alice,
            toUserId: $bob,
            orgId: $orgId,
            startsAt: Carbon::now()->subMinute(),
            expiresAt: Carbon::now()->addHour(),
        );

        // Requisitioner has Submit (S) on procurement, not Full (F).
        $this->assertTrue(
            $this->permissions->checkPermission($bob, $orgId, 'procurement', 'S')
        );
        $this->assertFalse(
            $this->permissions->checkPermission($bob, $orgId, 'procurement', 'F')
        );
    }

    /** @test */
    public function active_for_returns_delegation_record(): void
    {
        $orgId = $this->makeOrg();
        $alice = $this->makeUser('alice8@test.com');
        $bob   = $this->makeUser('bob8@test.com');

        $this->assertNull($this->service->activeFor($bob, $orgId));

        $delegation = $this->service->grant(
            grantedBy: $alice,
            fromUserId: $alice,
            toUserId: $bob,
            orgId: $orgId,
            startsAt: Carbon::now()->subMinute(),
            expiresAt: Carbon::now()->addHour(),
        );

        $found = $this->service->activeFor($bob, $orgId);

        $this->assertNotNull($found);
        $this->assertSame($delegation->id, $found->id);
    }
}

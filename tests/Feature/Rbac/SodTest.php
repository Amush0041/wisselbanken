<?php

namespace Tests\Feature\Rbac;

use App\Exceptions\Rbac\SodConflictException;
use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\Rbac\SodConflictRule;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\RoleAssignmentService;

/**
 * Separation-of-Duties enforcement via RoleAssignmentService.
 */
class SodTest extends RbacTestCase
{
    private RoleAssignmentService $service;
    private Organization $org;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RoleAssignmentService::class);
        $this->org     = Organization::create(['name' => 'Test Org', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $this->admin   = User::factory()->create();
    }

    public function test_assignment_blocked_when_sod_conflict_exists(): void
    {
        $roleA = Role::where('slug', 'procurement_manager')->firstOrFail();
        $roleB = Role::where('slug', 'executive_approver')->firstOrFail();

        $user = User::factory()->create();

        // Give the user Role A first.
        UserOrgRole::create([
            'user_id' => $user->id, 'org_id' => $this->org->id, 'role_id' => $roleA->id,
            'assigned_at' => now(), 'is_active' => true,
        ]);

        // Attempting to assign Role B (conflicts with A) must throw.
        $this->expectException(SodConflictException::class);

        $this->service->assign($this->admin->id, $user->id, $this->org->id, $roleB->id);
    }

    public function test_conflict_is_bidirectional(): void
    {
        $roleA = Role::where('slug', 'procurement_manager')->firstOrFail();
        $roleB = Role::where('slug', 'executive_approver')->firstOrFail();

        $user = User::factory()->create();

        // Give the user Role B first — assigning A should also be blocked.
        UserOrgRole::create([
            'user_id' => $user->id, 'org_id' => $this->org->id, 'role_id' => $roleB->id,
            'assigned_at' => now(), 'is_active' => true,
        ]);

        $this->expectException(SodConflictException::class);

        $this->service->assign($this->admin->id, $user->id, $this->org->id, $roleA->id);
    }

    public function test_assignment_allowed_when_no_conflict(): void
    {
        $role = Role::where('slug', 'estimator')->firstOrFail();
        $user = User::factory()->create();

        // No conflicting role held — must succeed.
        $result = $this->service->assign($this->admin->id, $user->id, $this->org->id, $role->id);

        $this->assertTrue($result['assignment']->is_active);
    }

    public function test_inactive_sod_rule_does_not_block(): void
    {
        $roleA = Role::where('slug', 'procurement_manager')->firstOrFail();
        $roleB = Role::where('slug', 'executive_approver')->firstOrFail();

        // Deactivate the seeded conflict rule for this specific pair.
        SodConflictRule::where('role_id_a', min($roleA->id, $roleB->id))
            ->where('role_id_b', max($roleA->id, $roleB->id))
            ->update(['is_active' => false]);

        $user = User::factory()->create();
        UserOrgRole::create([
            'user_id' => $user->id, 'org_id' => $this->org->id, 'role_id' => $roleA->id,
            'assigned_at' => now(), 'is_active' => true,
        ]);

        // With rule inactive, assignment should go through.
        $result = $this->service->assign($this->admin->id, $user->id, $this->org->id, $roleB->id);

        $this->assertTrue($result['assignment']->is_active);
    }

    public function test_sod_exception_carries_conflicting_role_names(): void
    {
        $roleA = Role::where('slug', 'procurement_manager')->firstOrFail();
        $roleB = Role::where('slug', 'executive_approver')->firstOrFail();

        $user = User::factory()->create();
        UserOrgRole::create([
            'user_id' => $user->id, 'org_id' => $this->org->id, 'role_id' => $roleA->id,
            'assigned_at' => now(), 'is_active' => true,
        ]);

        try {
            $this->service->assign($this->admin->id, $user->id, $this->org->id, $roleB->id);
            $this->fail('Expected SodConflictException was not thrown.');
        } catch (SodConflictException $e) {
            $this->assertNotEmpty($e->conflictingRoles);
            $this->assertContains($roleA->name, $e->conflictingRoles);
        }
    }
}

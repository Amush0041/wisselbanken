<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\RoleAssignmentService;

/**
 * Role assignment + peel-off mechanism (plan §6.3).
 */
class RoleAssignmentTest extends RbacTestCase
{
    private RoleAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RoleAssignmentService::class);
    }

    public function test_assigning_a_role_the_owner_holds_flags_peel_off(): void
    {
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $owner = User::factory()->create();
        $hire = User::factory()->create();
        $estimator = Role::where('slug', 'estimator')->firstOrFail();

        // Owner currently holds Estimator.
        $this->service->assign($owner->id, $owner->id, $org->id, $estimator->id);

        // Owner assigns Estimator to a new hire → peel-off candidate.
        $result = $this->service->assign($owner->id, $hire->id, $org->id, $estimator->id);

        $this->assertTrue($result['peel_off_candidate']);
        $this->assertTrue($this->service->holdsRole($hire->id, $org->id, $estimator->id));
    }

    public function test_no_peel_off_when_owner_does_not_hold_the_role(): void
    {
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $owner = User::factory()->create();
        $hire = User::factory()->create();
        $role = Role::where('slug', 'estimator')->firstOrFail();

        $result = $this->service->assign($owner->id, $hire->id, $org->id, $role->id);

        $this->assertFalse($result['peel_off_candidate']);
    }

    public function test_peel_off_deactivates_owner_assignment(): void
    {
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $owner = User::factory()->create();
        $role = Role::where('slug', 'estimator')->firstOrFail();
        $this->service->assign($owner->id, $owner->id, $org->id, $role->id);

        $this->assertTrue($this->service->peelOff($owner->id, $org->id, $role->id));
        $this->assertFalse($this->service->holdsRole($owner->id, $org->id, $role->id));

        // History is preserved (soft-removal, not delete).
        $this->assertDatabaseHas('user_org_roles', [
            'user_id' => $owner->id, 'org_id' => $org->id, 'role_id' => $role->id, 'is_active' => false,
        ]);
    }

    public function test_reassigning_a_peeled_role_reactivates_not_duplicates(): void
    {
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $owner = User::factory()->create();
        $role = Role::where('slug', 'estimator')->firstOrFail();

        $this->service->assign($owner->id, $owner->id, $org->id, $role->id);
        $this->service->peelOff($owner->id, $org->id, $role->id);
        $this->service->assign($owner->id, $owner->id, $org->id, $role->id);

        $this->assertSame(1, UserOrgRole::where('user_id', $owner->id)
            ->where('org_id', $org->id)->where('role_id', $role->id)->count());
        $this->assertTrue($this->service->holdsRole($owner->id, $org->id, $role->id));
    }
}

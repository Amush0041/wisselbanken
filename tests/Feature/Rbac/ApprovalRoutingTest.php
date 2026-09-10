<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\ApprovalRoutingService;

/**
 * The solo-operator auto-approve rule (plan §6.2).
 */
class ApprovalRoutingTest extends RbacTestCase
{
    private ApprovalRoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApprovalRoutingService::class);
    }

    public function test_sole_approver_auto_approves(): void
    {
        $org = Organization::create(['name' => 'Solo Co', 'org_type' => 'subcontractor']);
        $solo = User::factory()->create();
        // Holds Executive Approver (A on Approval Authority) and is the only approver.
        $this->assignRole($solo, $org, 'executive_approver');

        $result = $this->service->route($org->id, $solo->id);

        $this->assertTrue($result['auto_approve']);
        $this->assertSame([], $result['approver_ids']);
        $this->assertTrue($this->service->shouldAutoApprove($org->id, $solo->id));
    }

    public function test_two_approver_org_does_not_auto_approve_and_routes_to_the_other(): void
    {
        $org = Organization::create(['name' => 'Two Co', 'org_type' => 'general_contractor']);
        $requester = User::factory()->create();
        $other = User::factory()->create();
        $this->assignRole($requester, $org, 'executive_approver');
        $this->assignRole($other, $org, 'executive_approver');

        $result = $this->service->route($org->id, $requester->id);

        $this->assertFalse($result['auto_approve']);
        // Requester is removed from the routing pool; routed to the remaining approver.
        $this->assertSame([$other->id], $result['approver_ids']);
        $this->assertNotContains($requester->id, $result['approver_ids']);
    }

    public function test_non_approver_requester_routes_to_full_pool(): void
    {
        $org = Organization::create(['name' => 'Three Co', 'org_type' => 'owner']);
        $requester = User::factory()->create();      // Requisitioner — not an approver
        $approver1 = User::factory()->create();
        $approver2 = User::factory()->create();
        $this->assignRole($requester, $org, 'requisitioner');
        $this->assignRole($approver1, $org, 'executive_approver');
        $this->assignRole($approver2, $org, 'executive_approver');

        $result = $this->service->route($org->id, $requester->id);

        $this->assertFalse($result['auto_approve']);
        $this->assertEqualsCanonicalizing([$approver1->id, $approver2->id], $result['approver_ids']);
    }

    private function assignRole(User $user, Organization $org, string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        UserOrgRole::create([
            'user_id' => $user->id,
            'org_id' => $org->id,
            'role_id' => $role->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);
    }
}

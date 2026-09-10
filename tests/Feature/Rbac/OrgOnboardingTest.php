<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\OrgOnboardingService;

/**
 * Registration onboarding bundles (plan §6.1).
 */
class OrgOnboardingTest extends RbacTestCase
{
    private OrgOnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgOnboardingService::class);
    }

    public function test_small_buyer_gets_the_four_role_bundle(): void
    {
        $this->assertSame(
            ['organization_owner', 'procurement_manager', 'estimator', 'executive_approver'],
            $this->service->bundleFor('subcontractor', 'solo'),
        );
    }

    public function test_small_manufacturer_gets_the_three_role_bundle(): void
    {
        $this->assertSame(
            ['manufacturer_admin', 'product_manager', 'catalog_data_steward'],
            $this->service->bundleFor('manufacturer', '2-5'),
        );
    }

    public function test_larger_team_gets_owner_only_regardless_of_type(): void
    {
        $this->assertSame(['organization_owner'], $this->service->bundleFor('subcontractor', '20+'));
        $this->assertSame(['organization_owner'], $this->service->bundleFor('manufacturer', '6-20'));
    }

    public function test_small_other_type_gets_owner_only(): void
    {
        $this->assertSame(['organization_owner'], $this->service->bundleFor('architect', 'solo'));
    }

    public function test_onboard_creates_org_and_assigns_roles(): void
    {
        $user = User::factory()->create();

        $org = $this->service->onboard($user->id, 'Acme Subs', 'subcontractor', 'solo');

        $this->assertInstanceOf(Organization::class, $org);
        $this->assertSame('subcontractor', $org->org_type);
        $this->assertSame('solo', $org->team_size);

        $assigned = UserOrgRole::where('user_id', $user->id)->where('org_id', $org->id)
            ->where('is_active', true)->count();
        $this->assertSame(4, $assigned, 'solo subcontractor gets a 4-role bundle');
    }

    public function test_seed_org_roles_is_idempotent(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor', 'team_size' => 'solo']);

        $this->service->seedOrgRoles($user->id, $org->id, 'subcontractor', 'solo');
        $this->service->seedOrgRoles($user->id, $org->id, 'subcontractor', 'solo');

        $this->assertSame(4, UserOrgRole::where('user_id', $user->id)->where('org_id', $org->id)->count());
    }
}

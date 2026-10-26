<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, S03: the role dropdowns on the org screens offer exactly the roles the server will accept.
 * One rule (RoleAssignmentService::isAssignableByOrg) feeds the dropdown queries and the assign/invite checks.
 */
class OrgDropdownRolesTest extends ProjectTestCase
{
    private const PLATFORM_NAMES = ['Platform Super Admin', 'Platform Operations', 'Platform Data Reviewer', 'Platform Catalog Admin', 'Platform Finance / Billing'];
    private const SELLER_NAMES = ['Manufacturer Admin', 'Sales Rep', 'Pricing Manager', 'Catalog Data Steward', 'Order Fulfillment / CSR'];

    private User $ownerA;
    private User $mfrOwner;
    private Organization $mfr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->mfr = Organization::create(['name' => 'Mfr Org', 'org_type' => 'manufacturer', 'team_size' => 'solo']);
        $this->mfrOwner = $this->mkUser($this->mfr, 'organization_owner');
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function screens(): array
    {
        $out = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach (['org-admin' => ['roles'], 'org-admin/overview' => ['roles', 'allRoles'], 'org-admin/roles-list' => ['roles']] as $uri => $vars) {
                $out["$uri $mode"] = [$uri, $vars, $mode];
            }
        }

        return $out;
    }

    private function open(User $u, string $uri, int $orgId)
    {
        return $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $orgId])->get($uri)->assertOk();
    }

    #[DataProvider('screens')]
    public function test_a_buyer_org_is_offered_neither_platform_nor_seller_roles(string $uri, array $vars, string $mode): void
    {
        $this->setMode($mode);

        $response = $this->open($this->ownerA, $uri, $this->orgA->id);

        foreach ($vars as $var) {
            $categories = $response->viewData($var)->pluck('category')->unique()->values()->all();
            $this->assertNotContains('Platform', $categories, "$uri $var");
            $this->assertNotContains('Manufacturer/Seller', $categories, "$uri $var");
            $this->assertNotEmpty($categories, "$uri $var");
        }
        $html = $response->getContent();
        foreach ([...self::PLATFORM_NAMES, ...self::SELLER_NAMES] as $name) {
            $this->assertStringNotContainsString($name, $html, "$uri shows $name");
        }
        $this->assertStringContainsString('Executive Approver', $html);
    }

    #[DataProvider('screens')]
    public function test_a_manufacturer_org_is_offered_seller_roles_but_never_platform_roles(string $uri, array $vars, string $mode): void
    {
        $this->setMode($mode);

        $response = $this->open($this->mfrOwner, $uri, $this->mfr->id);

        foreach ($vars as $var) {
            $categories = $response->viewData($var)->pluck('category')->unique()->values()->all();
            $this->assertNotContains('Platform', $categories, "$uri $var");
        }
        $this->assertContains('Manufacturer/Seller', $response->viewData('roles')->pluck('category')->all());
        $html = $response->getContent();
        foreach (self::PLATFORM_NAMES as $name) {
            $this->assertStringNotContainsString($name, $html, "$uri shows $name");
        }
        $this->assertStringContainsString('Manufacturer Admin', $html);
    }

    public function test_the_dropdown_roles_equal_the_roles_the_server_accepts(): void
    {
        $svc = app(\App\Services\Rbac\RoleAssignmentService::class);
        $response = $this->open($this->ownerA, 'org-admin', $this->orgA->id);

        $offered = $response->viewData('roles')->pluck('slug')->sort()->values()->all();
        $expected = \App\Models\Rbac\Role::whereIn('phase', ['P1', 'P2'])->get()
            ->filter(fn ($r) => $svc->isAssignableByOrg($r, $this->orgA->org_type))->pluck('slug')->sort()->values()->all();

        $this->assertSame($expected, $offered);
    }

    public function test_the_roles_list_matrix_keeps_its_previous_content(): void
    {
        $roles = $this->open($this->ownerA, 'org-admin/roles-list', $this->orgA->id)->viewData('roles');

        $this->assertSame(
            \App\Models\Rbac\Role::whereIn('phase', ['P1', 'P2'])->whereNotIn('category', ['Platform', 'Manufacturer/Seller'])->count(),
            $roles->count()
        );
        $this->assertTrue($roles->contains('slug', 'organization_owner'));
        $this->assertTrue($roles->contains('slug', 'auditor_read_all'));
    }
}

<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\PermissionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PermissionServiceTest extends RbacTestCase
{
    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PermissionService::class);
    }

    public function test_hierarchy_full_satisfies_all_lower_levels(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor']);
        // Project Manager holds F on Project Management.
        $this->assignRole($user, $org, 'project_manager');

        foreach (['F', 'A', 'O', 'S', 'R'] as $level) {
            $this->assertTrue(
                $this->service->checkPermission($user->id, $org->id, 'project_management', $level),
                "F should satisfy required {$level}",
            );
        }
    }

    public function test_read_does_not_satisfy_higher_levels(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'owner']);
        // Viewer / Read Only holds R on Project Management.
        $this->assignRole($user, $org, 'viewer_read_only');

        $this->assertTrue($this->service->checkPermission($user->id, $org->id, 'project_management', 'R'));
        $this->assertFalse($this->service->checkPermission($user->id, $org->id, 'project_management', 'S'));
        $this->assertFalse($this->service->checkPermission($user->id, $org->id, 'project_management', 'F'));
    }

    public function test_org_scoping_isolation(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::create(['name' => 'Company A', 'org_type' => 'subcontractor']);
        $orgB = Organization::create(['name' => 'Company B', 'org_type' => 'subcontractor']);

        // Organization Owner at A only.
        $this->assignRole($user, $orgA, 'organization_owner');

        $this->assertTrue($this->service->checkPermission($user->id, $orgA->id, 'organization_management', 'F'));
        // Must NOT receive Owner permissions in the context of Company B.
        $this->assertFalse($this->service->checkPermission($user->id, $orgB->id, 'organization_management', 'F'));
    }

    public function test_multi_role_union(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'general_contractor']);
        // Requisitioner: Submit on Procurement. Executive Approver: Approve on Approval Authority.
        $this->assignRole($user, $org, 'requisitioner');
        $this->assignRole($user, $org, 'executive_approver');

        $this->assertTrue($this->service->checkPermission($user->id, $org->id, 'procurement', 'S'));
        $this->assertTrue($this->service->checkPermission($user->id, $org->id, 'approval_authority', 'A'));
    }

    public function test_inactive_role_is_ignored(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'owner']);
        $this->assignRole($user, $org, 'organization_owner', isActive: false);

        $this->assertFalse($this->service->checkPermission($user->id, $org->id, 'organization_management', 'F'));
    }

    public function test_project_scoping_requires_membership(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor']);
        $this->assignRole($user, $org, 'estimator'); // F on estimate_management

        $projectA = $this->makeProject($user->id);
        $projectB = $this->makeProject($user->id);

        // Member of A only.
        DB::table('project_members')->insert([
            'quote_id' => $projectA,
            'user_id' => $user->id,
            'org_id' => $org->id,
            'is_active' => true,
            'granted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Org-level grant alone still works without a projectId.
        $this->assertTrue($this->service->checkPermission($user->id, $org->id, 'estimate_management', 'F'));
        // Scoped to a project they belong to → allowed.
        $this->assertTrue($this->service->checkPermission($user->id, $org->id, 'estimate_management', 'F', $projectA));
        // Scoped to a project they do NOT belong to → denied even with the org role.
        $this->assertFalse($this->service->checkPermission($user->id, $org->id, 'estimate_management', 'F', $projectB));
    }

    public function test_user_with_no_role_is_denied(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'owner']);

        $this->assertFalse($this->service->checkPermission($user->id, $org->id, 'procurement', 'R'));
    }

    public function test_invalid_required_level_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'owner']);
        $this->service->checkPermission($user->id, $org->id, 'procurement', 'X');
    }

    private function assignRole(User $user, Organization $org, string $roleSlug, bool $isActive = true): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        UserOrgRole::create([
            'user_id' => $user->id,
            'org_id' => $org->id,
            'role_id' => $role->id,
            'assigned_at' => now(),
            'is_active' => $isActive,
        ]);
    }

    private function makeProject(int $userId): int
    {
        return DB::table('quotes')->insertGetId([
            'user_id' => $userId,
            'quote_number' => 'Q-' . uniqid(),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

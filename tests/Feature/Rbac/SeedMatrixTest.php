<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\PermissionGroup;
use App\Models\Rbac\Role;
use App\Models\Rbac\RolePermission;
use App\Support\Rbac\OrganizationType;
use Database\Seeders\Rbac\RbacSeeder;

/**
 * Validates the seeded foundation before any feature consumes it (plan §2.4).
 */
class SeedMatrixTest extends RbacTestCase
{
    public function test_seeds_expected_counts(): void
    {
        $this->assertSame(25, PermissionGroup::count(), '25 permission groups');
        $this->assertSame(49, Role::count(), '49 roles');
        $this->assertCount(20, OrganizationType::all(), '20 organization types');
    }

    public function test_phase_distribution(): void
    {
        // The matrix superset carries 49 roles: 17 P1 + 15 P2 + 17 P3.
        // Release 1 (P1+P2) = 32 here vs the plan's "31" because the matrix includes BOTH
        // machine accounts — api_system (P2) and integration_service_account (P1) — whereas
        // the Release 1 role sheet enumerates only the latter as a structural pull-forward.
        $this->assertSame(17, Role::where('phase', 'P1')->count(), 'P1 roles');
        $this->assertSame(15, Role::where('phase', 'P2')->count(), 'P2 roles');
        $this->assertSame(17, Role::where('phase', 'P3')->count(), 'Release 2 = P3 roles');
        $this->assertSame(32, Role::whereIn('phase', ['P1', 'P2'])->count(), 'Release 1 = P1+P2');
    }

    public function test_seed_is_idempotent(): void
    {
        $before = RolePermission::count();
        $this->seed(RbacSeeder::class);
        $this->assertSame($before, RolePermission::count(), 're-seeding must not duplicate rows');
    }

    public function test_spot_checks_from_the_plan(): void
    {
        // "Does Requisitioner have Submit access to Procurement?"
        $this->assertSame('S', $this->level('requisitioner', 'procurement'));

        // "Does Executive Approver have Approve access to Approval Authority?"
        $this->assertSame('A', $this->level('executive_approver', 'approval_authority'));

        // Platform Super Admin holds Full everywhere.
        $this->assertSame('F', $this->level('platform_super_admin', 'system_administration'));
    }

    private function level(string $roleSlug, string $groupSlug): ?string
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $group = PermissionGroup::where('slug', $groupSlug)->firstOrFail();

        return RolePermission::where('role_id', $role->id)
            ->where('permission_group_id', $group->id)
            ->value('access_level');
    }
}

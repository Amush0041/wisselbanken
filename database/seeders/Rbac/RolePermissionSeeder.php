<?php

namespace Database\Seeders\Rbac;

use App\Models\Rbac\PermissionGroup;
use App\Models\Rbac\Role;
use App\Models\Rbac\RolePermission;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the full F/A/O/S/R matrix — one row per (role, permission group) pair.
 * Must run after PermissionGroupSeeder and RoleSeeder. Idempotent.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = require __DIR__ . '/data/role_permission_matrix.php';

        $roleIds = Role::pluck('id', 'slug');
        $groupIds = PermissionGroup::pluck('id', 'slug');

        foreach ($matrix as $roleSlug => $permissions) {
            $roleId = $roleIds[$roleSlug]
                ?? throw new RuntimeException("Unknown role slug in matrix: {$roleSlug}");

            foreach ($permissions as $groupSlug => $level) {
                $groupId = $groupIds[$groupSlug]
                    ?? throw new RuntimeException("Unknown permission group slug in matrix: {$groupSlug}");

                RolePermission::updateOrCreate(
                    ['role_id' => $roleId, 'permission_group_id' => $groupId],
                    ['access_level' => $level],
                );
            }
        }
    }
}

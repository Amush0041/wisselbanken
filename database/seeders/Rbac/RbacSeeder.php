<?php

namespace Database\Seeders\Rbac;

use App\Services\Rbac\PermissionMatrix;
use Illuminate\Database\Seeder;

/**
 * RBAC foundation seeder. Loads the client's authoritative data in dependency order:
 *   1. permission_groups (25)
 *   2. roles (49 — Release 1 P1+P2, Release 2 P3)
 *   3. role_permissions (full F/A/O/S/R matrix)
 *
 * Organization types (20) are a constant list (App\Support\Rbac\OrganizationType),
 * not a seeded table, so they are not part of this run.
 *
 * Idempotent — safe to run repeatedly.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionGroupSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            SodConflictRuleSeeder::class,
        ]);

        // The cached matrix would otherwise serve stale data after a re-seed.
        app(PermissionMatrix::class)->flush();
    }
}

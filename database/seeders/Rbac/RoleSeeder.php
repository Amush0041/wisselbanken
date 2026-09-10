<?php

namespace Database\Seeders\Rbac;

use App\Models\Rbac\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds all 49 roles (Release 1 = phase P1+P2, Release 2 = phase P3).
 * Idempotent — keyed on slug.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = require __DIR__ . '/data/roles.php';

        foreach ($roles as [$slug, $name, $category, $phase, $description]) {
            Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => $category,
                    'phase' => $phase,
                    'description' => $description,
                ],
            );
        }
    }
}

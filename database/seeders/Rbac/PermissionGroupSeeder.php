<?php

namespace Database\Seeders\Rbac;

use App\Models\Rbac\PermissionGroup;
use Illuminate\Database\Seeder;

/**
 * Seeds the 25 permission groups. Idempotent — keyed on slug.
 */
class PermissionGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = require __DIR__ . '/data/permission_groups.php';

        foreach ($groups as [$slug, $name, $description]) {
            PermissionGroup::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}

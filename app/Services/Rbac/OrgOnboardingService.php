<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\Rbac\RoleAssignmentLog;
use App\Models\Rbac\UserOrgRole;
use Illuminate\Support\Facades\DB;

/**
 * Registration onboarding bundles (plan §6.1). Creates an organization for a new signup
 * and auto-assigns a starting set of roles based on org type + team size, so a solo
 * operator does not have to understand and self-assign four or five roles on day one.
 */
class OrgOnboardingService
{
    /**
     * Create the organization and seed its starting roles for the registering user.
     */
    public function onboard(int $userId, string $name, string $orgType, string $teamSize): Organization
    {
        return DB::transaction(function () use ($userId, $name, $orgType, $teamSize) {
            $org = Organization::create([
                'name' => $name,
                'org_type' => $orgType,
                'team_size' => $teamSize,
            ]);

            $this->seedOrgRoles($userId, $org->id, $orgType, $teamSize);

            return $org;
        });
    }

    /**
     * Assign the appropriate bundle of roles for (orgType, teamSize) to the user.
     *
     * @return array<int, string> the role slugs that were assigned
     */
    public function seedOrgRoles(int $userId, int $orgId, string $orgType, string $teamSize): array
    {
        $slugs = $this->bundleFor($orgType, $teamSize);

        $roleIds = Role::whereIn('slug', $slugs)->pluck('id', 'slug');

        foreach ($slugs as $slug) {
            if (! isset($roleIds[$slug])) {
                continue; // role not seeded (should not happen for Release 1 roles)
            }

            // SoD is intentionally bypassed here. A solo operator who just registered
            // legitimately holds all bundle roles (e.g. procurement_manager + executive_approver)
            // until they hire staff and use the peel-off mechanism to hand roles off.
            $assignment = UserOrgRole::firstOrCreate(
                ['user_id' => $userId, 'org_id' => $orgId, 'role_id' => $roleIds[$slug]],
                ['assigned_at' => now(), 'is_active' => true],
            );
            if ($assignment->wasRecentlyCreated) {
                RoleAssignmentLog::record($orgId, $userId, $roleIds[$slug], 'assigned', null);
            }
        }

        return $slugs;
    }

    /**
     * Decide which bundle applies. Larger teams (any type) get owner-only; small teams get
     * the buyer or manufacturer bundle when their org type matches, else owner-only.
     *
     * @return array<int, string>
     */
    public function bundleFor(string $orgType, string $teamSize): array
    {
        $bundles = config('rbac.onboarding.bundles');
        $isSmall = in_array($teamSize, config('rbac.small_team_sizes', []), true);

        if (! $isSmall) {
            return $bundles['owner_only'];
        }

        if (in_array($orgType, config('rbac.onboarding.manufacturer_org_types', []), true)) {
            return $bundles['manufacturer_small'];
        }

        if (in_array($orgType, config('rbac.onboarding.buyer_org_types', []), true)) {
            return $bundles['buyer_small'];
        }

        return $bundles['owner_only'];
    }
}

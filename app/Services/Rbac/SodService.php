<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Role;
use App\Models\Rbac\SodConflictRule;
use App\Models\Rbac\UserOrgRole;

/**
 * Separation-of-Duties enforcement (plan §7). Before a role is assigned, this service
 * checks whether the target user already holds a conflicting role in the same org.
 * If they do, assignment must be blocked — the caller receives the list of blocking
 * role names so a clear human-readable error can be returned.
 */
class SodService
{
    /**
     * Returns the names of any roles the user already holds in $orgId that conflict
     * with $roleIdToAssign. An empty array means no conflict — safe to proceed.
     *
     * @return array<string>
     */
    public function conflictsFor(int $userId, int $orgId, int $roleIdToAssign): array
    {
        if ($this->isSoleActiveMember($userId, $orgId)) {
            return [];
        }

        $conflictingIds = SodConflictRule::conflictingRoleIds($roleIdToAssign);

        if ($conflictingIds->isEmpty()) {
            return [];
        }

        $held = UserOrgRole::where('user_id', $userId)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->whereIn('role_id', $conflictingIds)
            ->pluck('role_id');

        if ($held->isNotEmpty() && $this->holdsOwnerRole($userId, $orgId)) {
            $bundleIds = Role::whereIn('slug', (array) config('rbac.onboarding.bundles.buyer_small'))->pluck('id');

            if ($bundleIds->contains($roleIdToAssign)) {
                $held = $held->reject(fn ($id) => $bundleIds->contains($id))->values();
            }
        }

        if ($held->isEmpty()) {
            return [];
        }

        return Role::whereIn('id', $held)->pluck('name')->all();
    }

    private function holdsOwnerRole(int $userId, int $orgId): bool
    {
        return UserOrgRole::where('user_id', $userId)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', 'organization_owner'))
            ->exists();
    }

    private function isSoleActiveMember(int $userId, int $orgId): bool
    {
        $memberIds = UserOrgRole::where('org_id', $orgId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');

        return $memberIds->count() === 1 && (int) $memberIds->first() === $userId;
    }
}

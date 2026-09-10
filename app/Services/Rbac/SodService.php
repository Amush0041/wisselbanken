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
        $conflictingIds = SodConflictRule::conflictingRoleIds($roleIdToAssign);

        if ($conflictingIds->isEmpty()) {
            return [];
        }

        $held = UserOrgRole::where('user_id', $userId)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->whereIn('role_id', $conflictingIds)
            ->pluck('role_id');

        if ($held->isEmpty()) {
            return [];
        }

        return Role::whereIn('id', $held)->pluck('name')->all();
    }
}

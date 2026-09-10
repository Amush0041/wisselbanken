<?php

namespace App\Services\Rbac;

use App\Exceptions\Rbac\SodConflictException;
use App\Models\Rbac\RoleAssignmentLog;
use App\Models\Rbac\UserOrgRole;

/**
 * Role assignment within an organization, including the peel-off mechanism (plan §6.3):
 * as a solo operator hires staff, roles move from the owner to new hires without lingering
 * on the owner's account. Assigning a role the assigner also holds flags a peel-off
 * candidate so the UI can prompt the owner to remove it from themselves.
 */
class RoleAssignmentService
{
    public function __construct(private readonly SodService $sod)
    {
    }

    /**
     * Assign a role to a user within an org. Reactivates a soft-removed assignment rather
     * than duplicating it. Throws SodConflictException if the assignment would violate a
     * Separation-of-Duties rule.
     *
     * @return array{assignment: UserOrgRole, peel_off_candidate: bool}
     *         peel_off_candidate is true when the assigner holds the same role themselves
     *         in this org (and is not the assignee).
     * @throws SodConflictException
     */
    public function assign(int $assignerId, int $targetUserId, int $orgId, int $roleId): array
    {
        $conflicts = $this->sod->conflictsFor($targetUserId, $orgId, $roleId);
        if ($conflicts !== []) {
            throw new SodConflictException($conflicts);
        }
        $assignment = UserOrgRole::where('user_id', $targetUserId)
            ->where('org_id', $orgId)
            ->where('role_id', $roleId)
            ->first();

        $wasInactive = false;
        if ($assignment) {
            if (! $assignment->is_active) {
                $assignment->update(['is_active' => true, 'assigned_by' => $assignerId, 'assigned_at' => now()]);
                $wasInactive = true;
            }
        } else {
            $assignment = UserOrgRole::create([
                'user_id'     => $targetUserId,
                'org_id'      => $orgId,
                'role_id'     => $roleId,
                'assigned_by' => $assignerId,
                'assigned_at' => now(),
                'is_active'   => true,
            ]);
            $wasInactive = true;
        }

        // Only log when the assignment actually changed state.
        if ($wasInactive) {
            RoleAssignmentLog::record($orgId, $targetUserId, $roleId, 'assigned', $assignerId);
        }

        $peelOffCandidate = $assignerId !== $targetUserId
            && $this->holdsRole($assignerId, $orgId, $roleId);

        return ['assignment' => $assignment, 'peel_off_candidate' => $peelOffCandidate];
    }

    /**
     * Peel a role off a user (soft-removal) — used when an owner confirms they want the
     * role removed from their own account after handing it to a new hire.
     */
    public function peelOff(int $userId, int $orgId, int $roleId): bool
    {
        $row = UserOrgRole::where('user_id', $userId)
            ->where('org_id', $orgId)
            ->where('role_id', $roleId)
            ->where('is_active', true)
            ->first();

        if (! $row) {
            return false;
        }

        $row->update(['is_active' => false]);
        RoleAssignmentLog::record($orgId, $userId, $roleId, 'removed', $userId);

        return true;
    }

    /**
     * Soft-remove a single role assignment by its id.
     */
    public function deactivate(int $userOrgRoleId, ?int $performedBy = null): bool
    {
        $row = UserOrgRole::where('id', $userOrgRoleId)->where('is_active', true)->first();

        if (! $row) {
            return false;
        }

        $row->update(['is_active' => false]);
        RoleAssignmentLog::record($row->org_id, $row->user_id, $row->role_id, 'removed', $performedBy);

        return true;
    }

    public function holdsRole(int $userId, int $orgId, int $roleId): bool
    {
        return UserOrgRole::where('user_id', $userId)
            ->where('org_id', $orgId)
            ->where('role_id', $roleId)
            ->where('is_active', true)
            ->exists();
    }
}

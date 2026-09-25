<?php

namespace App\Services\Rbac;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use App\Services\Rbac\OrgRelationshipService;

/**
 * The single authoritative permission engine. Every part of the system — old and new —
 * calls checkPermission() to determine whether a user may perform an action. Built once,
 * never duplicated, never bypassed with inline role checks.
 *
 * Access-level hierarchy: F > A > O > S > R. A higher level satisfies any lower required
 * level for the same permission group.
 *
 * Performance: the static matrix (roles × groups → level) is served from PermissionMatrix
 * (cached), so a check is a single indexed lookup on user_org_roles plus an in-memory
 * array read — no joins to the static tables, regardless of their size.
 */
class PermissionService
{
    /**
     * Access levels in descending order of authority.
     */
    private const HIERARCHY = ['F', 'A', 'O', 'S', 'R'];

    public function __construct(
        private readonly PermissionMatrix $matrix,
        private readonly DelegationService $delegation,
        private readonly OrgRelationshipService $orgRelationships,
    ) {
    }

    /**
     * Determine whether a user is allowed to perform an action.
     *
     * @param  int       $userId           the acting user (may be a delegate)
     * @param  int       $orgId            MANDATORY — the organization context of the action
     * @param  string    $permissionGroup  permission group slug, e.g. 'procurement'
     * @param  string    $requiredLevel    one of F/A/O/S/R
     * @param  int|null  $projectId        optional — a projects.id; membership is project_members.project_id
     */
    public function checkPermission(
        int $userId,
        int $orgId,
        string $permissionGroup,
        string $requiredLevel,
        ?int $projectId = null,
    ): bool {
        $requiredLevel = strtoupper($requiredLevel);

        if (! in_array($requiredLevel, self::HIERARCHY, true)) {
            throw new InvalidArgumentException("Invalid required access level: {$requiredLevel}");
        }

        // 0. Delegation: if this user is acting on behalf of another user, evaluate the check
        //    against the principal's roles rather than the delegate's own roles.
        $effectiveUserId = $this->delegation->resolveEffectiveUserId($userId, $orgId);

        // 1. Active role ids the effective user holds in THIS organization. org_id is always
        //    required — there is no code path that checks permissions without an org context.
        $roleIds = $this->activeRoleIds($effectiveUserId, $orgId);

        if ($roleIds === []) {
            return false;
        }

        // 2. Strongest level granted across those roles for the group (from the cached matrix).
        $grantedLevel = $this->matrix->levelFor($roleIds, $permissionGroup);

        if ($grantedLevel === null
            || PermissionMatrix::rank($grantedLevel) > PermissionMatrix::rank($requiredLevel)) {
            return false;
        }

        // 3. Project-level scoping: when a projectId (projects.id) is supplied, the effective user
        //    must additionally be an active member of that project.
        if ($projectId !== null && ! $this->isActiveProjectMember($effectiveUserId, $orgId, $projectId)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, int>
     */
    private function activeRoleIds(int $userId, int $orgId): array
    {
        return DB::table('user_org_roles')
            ->where('user_id', $userId)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Cross-org enforcement (document §4.5).
     *
     * Returns true when from_org has an active relationship of the specified type to to_org.
     * Use abort_if(! $permissions->canInteractWithOrg(...), 403) at cross-org boundaries.
     *
     * Relationship types: buyer_seller, gc_subcontractor, distributor_manufacturer,
     *                     manufacturer_rep_agency, gpo_member, delegation
     */
    public function canInteractWithOrg(int $fromOrgId, int $toOrgId, string $relationshipType): bool
    {
        return $this->orgRelationships->canInteract($fromOrgId, $toOrgId, $relationshipType);
    }

    /**
     * Return partner org IDs the given org has outgoing relationships with of a specific type.
     *
     * @return array<int, int>
     */
    public function partnerOrgIds(int $fromOrgId, string $relationshipType): array
    {
        return $this->orgRelationships->partnerIds($fromOrgId, $relationshipType);
    }

    private function isActiveProjectMember(int $userId, int $orgId, int $projectId): bool
    {
        return DB::table('project_members')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('project_members.project_id', $projectId)
            ->where('project_members.user_id', $userId)
            ->where('project_members.org_id', $orgId)
            ->where('project_members.is_active', true)
            ->where('projects.org_id', $orgId)
            ->whereNull('projects.deleted_at')
            ->exists();
    }
}

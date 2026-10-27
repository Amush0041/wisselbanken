<?php

namespace App\Services\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Manages time-bound delegations: User B can act on behalf of User A for a limited period.
 *
 * When B has an active delegation from A in org X, any permission check for B in org X is
 * evaluated against A's roles instead of B's. This is the structural pull-forward from plan
 * §4.3 — the mechanism is built now so it never needs to be retrofitted into live auth paths.
 */
class DelegationService
{
    /**
     * Grant User $toUserId the right to act as User $fromUserId within $orgId for the given
     * time window.
     */
    public function grant(
        int $grantedBy,
        int $fromUserId,
        int $toUserId,
        int $orgId,
        Carbon $startsAt,
        Carbon $expiresAt,
    ): Delegation {
        return Delegation::create([
            'from_user_id' => $fromUserId,
            'to_user_id'   => $toUserId,
            'org_id'       => $orgId,
            'granted_by'   => $grantedBy,
            'starts_at'    => $startsAt,
            'expires_at'   => $expiresAt,
            'is_active'    => true,
        ]);
    }

    /**
     * Soft-revoke a delegation by ID. History is preserved; the delegation simply stops
     * being evaluated.
     */
    public function revoke(int $delegationId): void
    {
        Delegation::where('id', $delegationId)->update(['is_active' => false]);
    }

    /**
     * Audit row for a grant or revoke. Fail-soft: a failing audit write must never block the delegation change.
     */
    public function audit(string $outcome, Delegation $delegation, int $actorId, string $level): void
    {
        try {
            $names = DB::table('users')->whereIn('id', [$delegation->to_user_id, $delegation->from_user_id])->pluck('name', 'id');

            AuditLog::event($outcome, $actorId, (int) $delegation->org_id, 'delegation_and_impersonation', $level, sprintf(
                'delegate %s (#%d) for %s (#%d), %s to %s',
                $names[$delegation->to_user_id] ?? 'unknown',
                $delegation->to_user_id,
                $names[$delegation->from_user_id] ?? 'unknown',
                $delegation->from_user_id,
                $delegation->starts_at->toDateTimeString(),
                $delegation->expires_at->toDateTimeString(),
            ));
        } catch (\Throwable $e) {
            Log::error('delegation audit row write failed', ['outcome' => $outcome, 'delegation_id' => $delegation->id, 'error' => get_class($e)]);
        }
    }

    /**
     * Resolve the effective user ID for a permission check.
     *
     * If $userId currently holds an active, non-expired delegation in $orgId, returns the
     * from_user_id (the principal they represent). Otherwise returns $userId unchanged.
     *
     * This is called at the top of PermissionService::checkPermission() so that the
     * delegate's check is always evaluated against the principal's roles.
     */
    public function resolveEffectiveUserId(int $userId, int $orgId): int
    {
        $now = Carbon::now();

        $fromUserId = DB::table('delegations')
            ->where('to_user_id', $userId)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('expires_at', '>', $now)
            ->value('from_user_id');

        return $fromUserId !== null ? (int) $fromUserId : $userId;
    }

    /**
     * Return the active delegation record for $toUserId in $orgId, or null if none.
     */
    public function activeFor(int $toUserId, int $orgId): ?Delegation
    {
        $now = Carbon::now();

        return Delegation::where('to_user_id', $toUserId)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('expires_at', '>', $now)
            ->first();
    }
}

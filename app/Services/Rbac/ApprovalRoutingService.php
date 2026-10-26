<?php

namespace App\Services\Rbac;

use App\Models\Order;
use App\Models\Rbac\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Solo-operator auto-approve rule (plan §6.2) — built and tested as an explicit rule,
 * because it does not exist in any off-the-shelf RBAC library.
 *
 * Separation of duties means the requester and the approver are normally different
 * people. For a solo operator that separation collapses. The correct condition is NOT
 * "does the requester hold an approver role" — it is "is the requester the SOLE user in
 * the org with Approval Authority 'A'". If a second approver exists, the request must be
 * routed to them; the requester is never allowed to approve their own request unless they
 * are genuinely the only person who can.
 */
class ApprovalRoutingService
{
    /**
     * The permission group and level that define an approver.
     */
    private const APPROVAL_GROUP = 'approval_authority';
    private const APPROVAL_LEVEL = 'A';

    public const AUTO_APPROVAL_NOTE = 'Auto-approved: you are the only approver in your organization.';

    /**
     * Decide how a submitted purchase request should be routed.
     *
     * @return array{auto_approve: bool, approver_ids: array<int, int>}
     *         approver_ids = users the request should be routed to (empty when auto-approved).
     */
    public function route(int $orgId, int $requesterId): array
    {
        $approverPool = $this->approverPool($orgId);

        // Sole approver in the org, and it is the requester → no one else to route to.
        if (count($approverPool) === 1 && in_array($requesterId, $approverPool, true)) {
            return ['auto_approve' => true, 'approver_ids' => []];
        }

        // Requester is one of several approvers → remove them, route to the rest.
        if (in_array($requesterId, $approverPool, true)) {
            $remaining = array_values(array_filter(
                $approverPool,
                fn (int $id) => $id !== $requesterId,
            ));

            return ['auto_approve' => false, 'approver_ids' => $remaining];
        }

        // Requester is not an approver → route to the whole pool normally.
        return ['auto_approve' => false, 'approver_ids' => array_values($approverPool)];
    }

    /**
     * Initial order columns for a routed submission. An auto-approved order is approved by its requester,
     * the same end state OrderApprovalController::approve produces.
     *
     * @param  array{auto_approve: bool, approver_ids: array<int, int>}  $routing
     * @return array{status: string, approved_by?: int, approval_note?: string}
     */
    public function initialOrderState(array $routing, int $requesterId): array
    {
        if ($routing['auto_approve']) {
            return ['status' => 'processing', 'approved_by' => $requesterId, 'approval_note' => self::AUTO_APPROVAL_NOTE];
        }

        return ['status' => 'pending_approval'];
    }

    public function recordAutoApproval(Order $order): void
    {
        AuditLog::event(
            'auto_approved',
            (int) $order->user_id,
            (int) $order->org_id,
            self::APPROVAL_GROUP,
            self::APPROVAL_LEVEL,
            'sole_approver order ' . $order->order_number,
        );
    }

    /**
     * Approvers who could decide the submitter's order in their place: the pool minus the submitter.
     *
     * @return array<int, int>
     */
    public function otherApprovers(int $orgId, int $submitterId): array
    {
        return array_values(array_filter(
            $this->approverPool($orgId),
            fn (int $id) => $id !== $submitterId,
        ));
    }

    /**
     * Convenience predicate for the sole-approver case.
     */
    public function shouldAutoApprove(int $orgId, int $requesterId): bool
    {
        return $this->route($orgId, $requesterId)['auto_approve'];
    }

    /**
     * Users in the org with an active role granting Approval Authority at exactly 'A'
     * level (or stronger — 'F' implies approval). Returns distinct user ids.
     *
     * @return array<int, int>
     */
    public function approverPool(int $orgId): array
    {
        // 'F' is stronger than 'A' on the F > A > O > S > R hierarchy, so a Full grant on
        // Approval Authority also makes a user an approver.
        return DB::table('user_org_roles as uor')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'uor.role_id')
            ->join('permission_groups as pg', 'pg.id', '=', 'rp.permission_group_id')
            ->where('uor.org_id', $orgId)
            ->where('uor.is_active', true)
            ->where('pg.slug', self::APPROVAL_GROUP)
            ->whereIn('rp.access_level', ['F', self::APPROVAL_LEVEL])
            ->distinct()
            ->pluck('uor.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

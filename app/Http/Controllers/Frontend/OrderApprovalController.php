<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rbac\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\Rbac\ApprovalRoutingService;
use App\Services\Rbac\DelegationService;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use App\Support\Rbac\DenialRecorder;

/**
 * Lets users with approval_authority:A view and act on orders in
 * pending_approval status for their organisation (doc §6.2, order approval UI).
 */
class OrderApprovalController extends Controller
{
    public function index()
    {
        $this->requireOrgLevel('approval_authority', 'A');
        $orgId = CurrentOrg::sessionOrg((int) Auth::id());

        $pendingOrders = Order::with(['user', 'items'])
            ->where('org_id', $orgId)
            ->where('status', 'pending_approval')
            ->latest()
            ->get();

        $selfIds = $this->selfIds((int) $orgId);
        $awaitingOthers = $pendingOrders
            ->filter(fn (Order $order) => in_array((int) $order->user_id, $selfIds, true)
                && app(ApprovalRoutingService::class)->otherApprovers((int) $orgId, (int) $order->user_id) !== [])
            ->pluck('id')
            ->values()
            ->all();

        return view('user.orders.approvals', compact('pendingOrders', 'awaitingOthers'));
    }

    public function approve(Request $request, Order $order)
    {
        $this->requireOrgLevel('approval_authority', 'A');
        $this->authorizeApprovalAction($order);
        $soleApproverOfOwnOrder = $this->guardOwnOrder($order, 'approve');

        $request->validate(['note' => 'nullable|string|max:1000']);

        DB::transaction(function () use ($order, $request, $soleApproverOfOwnOrder) {
            $order->update([
                'status'      => 'processing',
                'approved_by' => Auth::id(),
                'approval_note' => $request->note,
            ]);

            if ($soleApproverOfOwnOrder) {
                AuditLog::event('self_approved', (int) Auth::id(), (int) $order->org_id, 'approval_authority', 'A', 'sole_approver_self_approval order ' . $order->order_number);
            }
        });

        return redirect()->route('order.approvals')
            ->with('success', "Order #{$order->order_number} has been approved.");
    }

    public function reject(Request $request, Order $order)
    {
        $this->requireOrgLevel('approval_authority', 'A');
        $this->authorizeApprovalAction($order);
        $this->guardOwnOrder($order, 'reject');

        $request->validate(['note' => 'nullable|string|max:1000']);

        $order->update([
            'status'      => 'cancelled',
            'rejected_by' => Auth::id(),
            'approval_note' => $request->note,
        ]);

        return redirect()->route('order.approvals')
            ->with('success', "Order #{$order->order_number} has been rejected.");
    }

    private function authorizeApprovalAction(Order $order): void
    {
        $orgId = CurrentOrg::sessionOrg((int) Auth::id());

        if ((int) $order->org_id !== (int) $orgId) {
            DenialRecorder::record(request());
            abort(404);
        }
        abort_if(
            $order->status !== 'pending_approval',
            403,
            'This order is no longer pending approval.'
        );
    }

    /**
     * Returns true when this is the caller's own order (or the principal's) and nobody else can decide it,
     * false when it is somebody else's order, and aborts with 403 when another approver is available.
     */
    private function guardOwnOrder(Order $order, string $verb): bool
    {
        if (! in_array((int) $order->user_id, $this->selfIds((int) $order->org_id), true)) {
            return false;
        }

        abort_if(
            app(ApprovalRoutingService::class)->otherApprovers((int) $order->org_id, (int) $order->user_id) !== [],
            403,
            "You cannot {$verb} your own order while another approver is available."
        );

        return true;
    }

    /** @return array<int, int> the caller and, for a delegate, the principal they act for */
    private function selfIds(int $orgId): array
    {
        $userId = (int) Auth::id();

        return [$userId, app(DelegationService::class)->resolveEffectiveUserId($userId, $orgId)];
    }

    private function requireOrgLevel(string $group, string $level): void
    {
        $userId = (int) Auth::id();
        $orgId = (int) CurrentOrg::id($userId);

        abort_unless(
            $orgId > 0 && app(PermissionService::class)->checkPermission($userId, $orgId, $group, $level),
            403,
            'You do not have permission to perform this action.'
        );
    }
}

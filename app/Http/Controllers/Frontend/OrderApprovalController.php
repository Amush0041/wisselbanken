<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets users with approval_authority:A view and act on orders in
 * pending_approval status for their organisation (doc §6.2, order approval UI).
 */
class OrderApprovalController extends Controller
{
    public function index()
    {
        $orgId = session(config('rbac.current_org_session_key'));

        $pendingOrders = Order::with(['user', 'items'])
            ->where('org_id', $orgId)
            ->where('status', 'pending_approval')
            ->latest()
            ->get();

        return view('user.orders.approvals', compact('pendingOrders'));
    }

    public function approve(Request $request, Order $order)
    {
        $this->authorizeApprovalAction($order);

        $request->validate(['note' => 'nullable|string|max:1000']);

        $order->update([
            'status'      => 'processing',
            'approved_by' => Auth::id(),
            'approval_note' => $request->note,
        ]);

        return redirect()->route('order.approvals')
            ->with('success', "Order #{$order->order_number} has been approved.");
    }

    public function reject(Request $request, Order $order)
    {
        $this->authorizeApprovalAction($order);

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
        $orgId = session(config('rbac.current_org_session_key'));

        abort_if(
            (int) $order->org_id !== (int) $orgId || $order->status !== 'pending_approval',
            403,
            'This order is not in your organisation or is no longer pending approval.'
        );
    }
}

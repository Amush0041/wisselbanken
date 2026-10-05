<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariationColor;
use App\Services\Rbac\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\Rbac\CurrentOrg;

class OrderController extends Controller
{
    private function isOrgAdmin(): bool
    {
        $user  = Auth::user();
        $orgId = CurrentOrg::sessionOrg((int) Auth::id());
        return $user->role === 'admin' ||
            ($orgId && app(PermissionService::class)->checkPermission($user->id, (int) $orgId, 'user_management', 'F'));
    }

    public function viewOrders()
    {
        $this->requireOrgLevel('procurement', 'R');
        $user       = Auth::user();
        $orgId      = CurrentOrg::sessionOrg((int) Auth::id());
        $isOrgAdmin = $this->isOrgAdmin();

        $query = Order::with(['items', 'user', 'approvedBy', 'rejectedBy']);

        if ($isOrgAdmin && $orgId) {
            $query->where('org_id', $orgId);
        } else {
            $query->where('user_id', $user->id);
        }

        $orders = $query->latest()->get();

        return view('user.orders.index', compact('orders', 'isOrgAdmin'));
    }

    public function orderDetail($id)
    {
        $this->requireOrgLevel('procurement', 'R');
        $user       = Auth::user();
        $orgId      = CurrentOrg::sessionOrg((int) Auth::id());
        $isOrgAdmin = $this->isOrgAdmin();

        $query = Order::with(['items', 'user', 'approvedBy', 'rejectedBy']);

        if ($isOrgAdmin && $orgId) {
            $query->where(function ($q) use ($user, $orgId) {
                $q->where('user_id', $user->id)->orWhere('org_id', $orgId);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        $order = $query->where('id', $id)->first();
        if ($order) {
            $orderItems = ProductVariationColor::with([
                'productVariation.product.division',
                'productVariation.product.specification',
                'productVariation.product.manufacturer',
                'productVariation.size',
                'productVariation.thickness',
                'productVariation.finish',
                'productVariation.paint_type',
                'productVariation.color_effect',
                'color'
            ])->whereIn('id', $order->items->pluck('product_variation_color_id'))->get(); 
            return view('frontend.orders.orderDetail', compact('orderItems', 'order'));
        } else {
            return back();
        }
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

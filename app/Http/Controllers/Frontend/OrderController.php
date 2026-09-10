<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariationColor;
use App\Services\Rbac\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    private function isOrgAdmin(): bool
    {
        $user  = Auth::user();
        $orgId = session(config('rbac.current_org_session_key'));
        return $user->role === 'admin' ||
            ($orgId && app(PermissionService::class)->checkPermission($user->id, (int) $orgId, 'user_management', 'F'));
    }

    public function viewOrders()
    {
        $user       = Auth::user();
        $orgId      = session(config('rbac.current_org_session_key'));
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
        $user       = Auth::user();
        $orgId      = session(config('rbac.current_org_session_key'));
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
}

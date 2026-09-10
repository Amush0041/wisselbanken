<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use App\DataTables\OrderDataTable;
use App\Models\ProductVariationColor;

class OrderController extends Controller
{
    // Show all orders
    public function index(OrderDataTable $dataTable)
    {
        return $dataTable->render('admin.orders.index');
    }

    // Show order details
    public function show(Order $order)
    {      
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
        return view('admin.orders.show', compact('order','orderItems'));
    }
} 
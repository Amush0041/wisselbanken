<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ProductPricingDataTable;
use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductPricing;
use Illuminate\Http\Request;

class ProductPricingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ProductPricingDataTable $dataTable)
    {
        $manufacturers  = Manufacturer::where('status',1)->get();
        $colors          = Color::where('status',1)->get();
        return $dataTable->render('admin.products.pricing.index',compact('colors','manufacturers'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreProductPricingRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'manufacturer' => ['required'],
            'product' => ['required'],
            'color' => ['required'],
            'part_name' => ['required'],
            'part_number' => ['required'],
            'price' => ['required','numeric'],
            'unit_price' => ['required','numeric'],
            'quantity' => ['required','numeric']
        ]);

        $pricing = new ProductPricing();
        $pricing->create([
            'manufacturer_id'    => $request->manufacturer,
            'product_id'        => $request->product,
            'color_id'          => $request->color,
            'part_name'         => $request->part_name,
            'part_number'       => $request->part_number,
            'price'             => $request->price,
            'unit_price'        => $request->unit_price,
            'quantity'          => $request->quantity,
        ]);
        return response()->json(['message' => 'Product pricing added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ProductPricing  $product_pricing
     * @return \Illuminate\Http\Response
     */
    public function show(ProductPricing $product_pricing)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ProductPricing  $product_pricing
     * @return \Illuminate\Http\Response
     */
    public function edit(ProductPricing $product_pricing)
    {
        return $product_pricing;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateProductPricingRequest  $request
     * @param  \App\Models\ProductPricing  $product_pricing
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ProductPricing $product_pricing)
    {
        
        $request->validate([
            'manufacturer' => ['required'],
            'product' => ['required'],
            'color' => ['required'],
            'part_name' => ['required'],
            'part_number' => ['required'],
            'price' => ['required','numeric'],
            'unit_price' => ['required','numeric'],
            'quantity' => ['required','numeric']
        ]);
 
        $product_pricing->update([
            'manufacturer_id'    => $request->manufacturer,
            'product_id'        => $request->product,
            'color_id'          => $request->color,
            'part_name'         => $request->part_name,
            'part_number'       => $request->part_number,
            'price'             => $request->price,
            'unit_price'        => $request->unit_price,
            'quantity'          => $request->quantity,
        ]);
        return response()->json(['message' => 'Product pricing update successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ProductPricing  $product_pricing
     * @return \Illuminate\Http\Response
     */
    public function destroy(ProductPricing $product_pricing)
    {
        if (!$product_pricing->delete()) {
            return response()->json(['status' => 'failure', 'message' => 'Something going wrong!.']);
        }
        return response()->json(['status' => 'success', 'message' => 'Product Pricing delete successfully.']);
    }


    public function getManfacturerProducts(Request $request)
    {
        $manfacture_id = $request->input('id');
        $products = Product::where('manufacturer_id', $manfacture_id)->get();
        if ($products->isNotEmpty()) {
            return response()->json(['success' => true, 'products' => $products]);
        } else {
            return response()->json(['success' => false, 'message' => 'No Products Found !']);
        }
    }
}

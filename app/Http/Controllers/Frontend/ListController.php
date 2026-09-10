<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\ColorEffect;
use App\Models\Division;
use App\Models\Finish;
use App\Models\Manufacturer;
use App\Models\PaintType;
use App\Models\Product;
use App\Models\ProductVariationColor;
use App\Models\SavedList;
use App\Models\SavedListItem;
use App\Models\Size;
use App\Models\Specification;
use App\Models\Thickness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ListController extends Controller
{
    public function viewLists()
    {
        $lists = SavedList::with('items')->where('user_id', Auth::user()->id)->orderBy('created_at', 'desc')->get();
        return view('user.lists.viewlist', compact('lists'));
    }

    public function viewListDetail($id, $slug, Request $request)
    {
        $list = SavedList::with('items')->where('id', $id)->where('user_id', Auth::user()->id)->first();
        $list_items = SavedListItem::where('saved_list_id', $id)->get();
        $product_color_variation_ids = $list_items->pluck('product_color_variation_id')->toArray(); 
        $query = ProductVariationColor::with([
            'productVariation.product.division',
            'productVariation.product.specification',
            'productVariation.product.manufacturer',
            'productVariation',
            'productVariation.size',
            'productVariation.thickness',
            'productVariation.finish',
            'productVariation.paint_type',
            'productVariation.color_effect',
            'color'
        ])->whereIn('id', $product_color_variation_ids)->get(); 
        // Collect all the IDs
        $division_ids = $query->pluck('productVariation.product.division.id')->unique()->filter()->values();
        $specification_ids = $query->pluck('productVariation.product.specification.id')->unique()->filter()->values();
        $manufacturer_ids = $query->pluck('productVariation.product.manufacturer.id')->unique()->filter()->values();
        $product_ids = $query->pluck('productVariation.product.id')->unique()->filter()->values();
        $size_ids = $query->pluck('productVariation.size.id')->unique()->filter()->values();
        $thickness_ids = $query->pluck('productVariation.thickness.id')->unique()->filter()->values();
        $finish_ids = $query->pluck('productVariation.finish.id')->unique()->filter()->values();
        $paint_type_ids = $query->pluck('productVariation.paint_type.id')->merge(
            $query->pluck('color.paint_type_id') // also from color directly
        )->unique()->filter()->values();
        $color_ids = $query->pluck('color.id')->unique()->filter()->values();
        $color_effect_ids = $query->pluck('productVariation.color_effect.id')->unique()->filter()->values();

        $divisions           = Division::whereIn('id', $division_ids->toArray())->get(['id', 'name', 'code']);
        $specifications     = Specification::whereIn('id', $specification_ids->toArray())->get(['id', 'specification_number', 'material_type']);
        $manufacturers      = Manufacturer::whereIn('id', $manufacturer_ids->toArray())->get(['id', 'name']);
        $products           = Product::whereIn('id', $product_ids->toArray())->get(['id', 'name']);
        $sizes              = Size::whereIn('id', $size_ids->toArray())->get(['id', 'name']);
        $thicknesses        = Thickness::whereIn('id', $thickness_ids->toArray())->get(['id', 'name']);
        $finishes           = Finish::whereIn('id', $finish_ids->toArray())->get(['id', 'name']);
        $paint_types        = PaintType::whereIn('id', $paint_type_ids->toArray())->get(['id', 'name']);
        $colors             = Color::with('paint_type')->whereIn('id', $color_ids->toArray())->get();
        $color_effects      = ColorEffect::whereIn('id', $color_effect_ids->toArray())->get(['id', 'name']);

        return view('user.lists.listDetail', compact('list', 'divisions', 'specifications', 'manufacturers', 'products', 'sizes', 'thicknesses', 'finishes', 'paint_types', 'colors', 'color_effects'));
    }

    public function listDetailData($id, Request $request)
    {
        $list = SavedList::where('id', $id)
            ->where('user_id', Auth::user()->id)
            ->firstOrFail();

        $list_items = SavedListItem::where('saved_list_id', $id)->get();
        $product_color_variation_ids = $list_items->pluck('product_color_variation_id')->toArray();
        $quantities = $list_items->pluck('quantity', 'product_color_variation_id');
        
        // Debug: Log the count for troubleshooting
        \Log::info('List items count for list ' . $id . ': ' . $list_items->count());
        \Log::info('Product color variation IDs: ' . implode(', ', $product_color_variation_ids));

        $query = ProductVariationColor::with([
            'productVariation.product.division',
            'productVariation.product.specification',
            'productVariation.product.manufacturer',
            'productVariation.size',
            'productVariation.thickness',
            'productVariation.finish',
            'productVariation.paint_type',
            'productVariation.color_effect',
            'color'
        ])->whereIn('id', $product_color_variation_ids)
            ->orderBy('created_at', 'desc');

        if ($request->ajax()) {
            // Apply filters
            $query->when($request->filled('productIds'), fn($q) =>
            $q->whereHas('productVariation.product', fn($q) =>
            $q->whereIn('id', $request->productIds)));


            $query->when($request->filled('divisionIds'), fn($q) =>
            $q->whereHas('productVariation.product.division', fn($q) =>
            $q->whereIn('id', $request->divisionIds)));

            $query->when($request->filled('specificationIds'), fn($q) =>
            $q->whereHas('productVariation.product.specification', fn($q) =>
            $q->whereIn('id', $request->specificationIds)));

            $query->when($request->filled('manufacturerIds'), fn($q) =>
            $q->whereHas('productVariation.product.manufacturer', fn($q) =>
            $q->whereIn('id', $request->manufacturerIds)));

            $query->when($request->filled('sizesIds'), fn($q) =>
            $q->whereHas('productVariation', fn($q) =>
            $q->whereIn('size_id', $request->sizesIds)));

            $query->when($request->filled('thicknessIds'), fn($q) =>
            $q->whereHas('productVariation', fn($q) =>
            $q->whereIn('thickness_id', $request->thicknessIds)));

            $query->when($request->filled('painttypesIds'), fn($q) =>
            $q->whereHas('productVariation', fn($q) =>
            $q->whereIn('paint_type_id', $request->painttypesIds)));

            $query->when($request->filled('finishIds'), fn($q) =>
            $q->whereHas('productVariation', fn($q) =>
            $q->whereIn('finish_id', $request->finishIds)));

            $query->when($request->filled('colorEffectIds'), fn($q) =>
            $q->whereHas('productVariation', fn($q) =>
            $q->whereIn('color_effect_id', $request->colorEffectIds)));

            $query->when($request->filled('colorsIds'), fn($q) =>
            $q->whereIn('color_id', $request->colorsIds));

            // Product name search
            $query->when($request->filled('productNames'), function ($q) use ($request) {
                $productNames = is_array($request->productNames) ? $request->productNames : explode(',', $request->productNames);
                $q->whereHas('productVariation.product', fn($query) =>
                $query->whereIn('name', $productNames));
            });
            // Get all list items with their quantities
            $list_items = SavedListItem::where('saved_list_id', $id)->get();
            $product_color_variation_ids = $list_items->pluck('product_color_variation_id')->toArray();
            $quantities = $list_items->pluck('quantity', 'product_color_variation_id');
            $item_ids = $list_items->pluck('id', 'product_color_variation_id'); 
            
            // Debug: Log the query count
            \Log::info('Query count for list ' . $id . ': ' . $query->count());
            
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($variationColor) use ($quantities) {
                    return '<input type="checkbox" class="variation-checkbox custom-check" 
                            data-color-id="' . encrypt($variationColor->color_id) . '" 
                            data-variation-id="' . encrypt($variationColor->product_variation_id) . '" 
                            data-product-color-variation-id="' . encrypt($variationColor->id) . '" 
                            data-quantity="1" checked>';
                })
                ->addColumn('quantity', function ($variationColor) use ($quantities,$item_ids) {
                    // Use saved quantity if exists, otherwise default to 0
                    $savedQuantity = $quantities[$variationColor->id] ?? 0;
                    $item_id       = $item_ids[$variationColor->id];
                    return '<input type="number" class="quantity-input" data-price="' . e($variationColor->productVariation->pricing) . '" 
                            min="1" value="' . e($savedQuantity) . '" data-id="'.$item_id.'" data-price="'.$variationColor->productVariation->pricing.'">';
                })
                ->addColumn('subtotal', function ($variationColor) use ($quantities) {
                    $savedQuantity = $quantities[$variationColor->id] ?? 0;
                    $subtotal = $savedQuantity * $variationColor->productVariation->pricing;
                    return '<span class="subtotal">' . e(number_format($subtotal, 2)) . '</span>';
                })
                ->addColumn('product_name', function ($variationColor) {
                    $product = $variationColor->productVariation->product;
                    $imageUrl = $product->feature_image ? asset($product->feature_image) : asset('demo.jpg');
                    $url = url('product-detail/' . $product->slug);

                    return '<div style="display: flex; align-items: center; gap: 10px;">
                                <img src="' . $imageUrl . '" alt="' . e($product->name) . '" width="50" height="50" style="border-radius: 5px;">
                                <a class="text-primary" href="' . $url . '">' . e($product->name) . '</a>
                            </div>';
                })
                ->addColumn('division', fn($variationColor) => $variationColor->productVariation->product->division->code ?? '')
                ->addColumn('specification', fn($variationColor) => $variationColor->productVariation->product->specification->specification_number ?? '')
                ->addColumn('manufacturer', fn($variationColor) => $variationColor->productVariation->product->manufacturer->name ?? '')
                ->addColumn('size', fn($variationColor) => $variationColor->productVariation->size->name ?? 'N/A')
                ->addColumn('thickness', fn($variationColor) => $variationColor->productVariation->thickness->name ?? 'N/A')
                ->addColumn('finish', fn($variationColor) => $variationColor->productVariation->finish->name ?? 'N/A')
                ->addColumn('color', fn($variationColor) => $variationColor->color->name ?? 'N/A')
                ->addColumn('paint_type', fn($variationColor) => $variationColor->productVariation->paint_type->name ?? 'N/A')
                ->addColumn('color_effect', fn($variationColor) => $variationColor->productVariation->color_effect->name ?? 'N/A')
                ->addColumn('pricing', function ($variationColor) {
                    return number_format($variationColor->productVariation->pricing, 2);
                })->with([
                    'recordsTotal' => $list_items->count(),
                    'recordsFiltered' => $query->count(),
                    'totalRecords' => $list_items->count()
                ])

                ->rawColumns(['checkbox', 'quantity', 'subtotal', 'product_name'])
                ->make(true);
        }
    }

    public function removList($id)
    {
        try {
            // Find the list
            $list = SavedList::find($id);
            // Check if the authenticated user owns this list
            if ($list->user_id !== Auth::user()->id) {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
                ], 403);
            }
            if ($list) {
                $listitems = SavedListItem::where('saved_list_id', $id)->delete();
            }
            $list->delete();

            return response()->json([
                'success' => true,
                'message' => 'List deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting list: ' . $e->getMessage()
            ], 500);
        }
    }
    public function removListItem($id, Request $request)
    {
        try {
            $listItem = SavedListItem::where('id', $id)
                ->where('saved_list_id', $request->list_id)
                ->firstOrFail();

            $listItem->delete();

            $totalItems = SavedListItem::where('saved_list_id', $request->list_id)->count();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from list successfully',
                'total_items' => $totalItems
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateListItemQuantity(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'item_id' => 'required',
            'quantity' => 'required|integer|min:1'
        ]);

        try {
            $item = SavedListItem::with('savedList','variation')->where('id', $request->item_id)
                ->whereHas('savedList', function ($query) {
                    $query->where('user_id', Auth::user()->id);
                })
                ->firstOrFail(); 
            $item->update(['quantity' => $request->quantity]);

            return response()->json([
                'success' => true,
                'message' => 'Quantity updated successfully',
                'subtotal' => $item->variation->pricing * $request->quantity
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update quantity'
            ], 500);
        }
    }

    public function updateListInfo(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'project_name' => 'required|string|max:255',
            'address1' => 'required|string|max:255',
            'address2' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|integer|exists:state_taxes,id',
            'postcode' => 'required|string|max:20',
        ]);

        try {
            $list = SavedList::where('id', $id)
                ->where('user_id', Auth::user()->id)
                ->firstOrFail();

            $list->update([
                'name' => $request->project_name, // Update list name with project name
                'address1' => $request->address1,
                'address2' => $request->address2,
                'city' => $request->city,
                'state_id' => $request->state,
                'postcode' => $request->postcode,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Project information updated successfully',
                'data' => [
                    'project_name' => $request->project_name,
                    'address1' => $request->address1,
                    'address2' => $request->address2,
                    'city' => $request->city,
                    'state_id' => $request->state,
                    'postcode' => $request->postcode,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project information: ' . $e->getMessage()
            ], 500);
        }
    }

 
}

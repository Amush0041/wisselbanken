<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\ColorEffect;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Specification;
use Illuminate\Http\Request;

class ProductFilterDataController extends Controller
{
    public function getFilterData(Request $request)
    {
        // Division filter
        if ($request->type == 'Division') {
            if ($request->ids) {
                $ids = $request->ids;
                if (count($ids) > 0) {
                    $specifications = Specification::whereIn('division_id', $ids)->orderBy('specification_number', 'asc')->get();

                    $output = '';
                    foreach ($specifications as $spec) {
                        $output .= '<li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="specificationsIds[]" value="' . $spec->id . '">
                                            ' . $spec->specification_number . ' - ' . $spec->material_type . '
                                        </label>
                                    </li>';
                    }
                    return response()->json($output);
                }
            }
        }
        // specification filter
        if ($request->type == 'Specification') {
            if ($request->ids) {
                $ids = $request->ids;
                if (count($ids) > 0) {
                    // Get unique manufacturer IDs that have products with the selected specification(s)
                    $manufacturerIds = Product::whereIn('specification_id', $ids)
                        ->distinct()
                        ->pluck('manufacturer_id')
                        ->filter()
                        ->toArray();
                    
                    // Get manufacturers with those IDs
                    $manufacturers = Manufacturer::whereIn('id', $manufacturerIds)
                        ->orderBy('name', 'asc')
                        ->get(['id', 'name']);

                    $output = '';
                    foreach ($manufacturers as $manufacturer) {
                        $output .= '<li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="manufacturersIds[]" value="' . $manufacturer->id . '">
                                            ' . $manufacturer->name . '
                                        </label>
                                    </li>';
                    }
                    return response()->json($output);
                }
            }
        }
        // Manufacturer filter
        if ($request->type == 'Manufacturers') {
            if ($request->ids) {
                $ids = $request->ids;
                if (count($ids) > 0) {
                    $products = Product::whereIn('manufacturer_id', $ids)
                        ->orderBy('name', 'asc')
                        ->get(['id', 'name'])
                        ->unique('name')
                        ->values();

                    $productoutput = '';
                    foreach ($products as $product) {
                        $productoutput .= '<li>
                                                <label>
                                                    <input type="checkbox" class="custom-check" name="productIds[]" value="' . $product->id . '">
                                                    ' . $product->name . '
                                                </label>
                                            </li>';
                    }
                    return response()->json($productoutput);
                }
            }
        }

        // Product related siz, thickness and paint_type filter
        if ($request->type == 'Products') {
            if ($request->ids) {
                $ids = $request->ids;
                if (count($ids) > 0) {
                    $products = Product::with([
                        'productVariation.size',
                        'productVariation.thickness',
                        'productVariation.paint_type',
                        'productVariation.finish',
                    ])->whereIn('id', $ids)->get(['id', 'name']);

                    $sizes = collect();
                    $thicknesses = collect();
                    $paintTypes = collect();
                    $finishes = collect();

                    foreach ($products as $product) {
                        $sizes = $sizes->merge($product->productVariation->pluck('size'));
                        $thicknesses = $thicknesses->merge($product->productVariation->pluck('thickness'));
                        $paintTypes = $paintTypes->merge($product->productVariation->pluck('paint_type'));
                        $finishes = $finishes->merge($product->productVariation->pluck('finish'));
                    }
                    // Apply uniqueness based on name globally
                    $sizes = $sizes->unique('name')->sortBy(fn($size) => intval($size->name));
                    $thicknesses = $thicknesses->unique('name')->sortBy('name');
                    $paintTypes = $paintTypes->unique('name')->sortBy('name');
                    $finishes = $finishes->unique('name')->sortBy('name');

                    $sizeOutput = '';
                    foreach ($sizes as $size) {
                        $sizeOutput .= '<li>
                                            <label>
                                                <input type="checkbox" class="custom-check" name="sizesIds[]" value="' . $size->id . '">
                                                ' . $size->name . '
                                            </label>
                                        </li>';
                    }

                    $thicknessOutput = '';
                    foreach ($thicknesses as $thickness) {
                        $thicknessOutput .= '<li>
                                                <label>
                                                    <input type="checkbox" class="custom-check" name="thicknessIds[]" value="' . $thickness->id . '">
                                                    ' . $thickness->name . '
                                                </label>
                                            </li>';
                    }

                    $paintTypeOutput = '';
                    foreach ($paintTypes as $paintType) {
                        $paintTypeOutput .= '<li>
                                                <label>
                                                    <input type="checkbox" class="custom-check" name="painttypesIds[]" value="' . $paintType->id . '">
                                                    ' . $paintType->name . '
                                                </label>
                                            </li>';
                    }
                    $finishOutput = '';
                    foreach ($finishes as $finish) {
                        $finishOutput .= '<li>
                                                <label>
                                                    <input type="checkbox" class="custom-check" name="finishIds[]" value="' . $finish->id . '">
                                                    ' . $finish->name . '
                                                </label>
                                            </li>';
                    }
                    return response()->json([
                        'sizes' => $sizeOutput,
                        'thicknesses' => $thicknessOutput,
                        'paintTypes' => $paintTypeOutput,
                        'finishes'     => $finishOutput
                    ]);
                }
            }
        }

        if ($request->type == 'Colors') {
            if ($request->ids) {
                $ids = $request->ids;
                if (count($ids) > 0) {
                    // Require at least product selection to show colors
                    if (!$request->filled('productIds') || count($request->productIds) == 0) {
                        return response()->json(['colors' => '', 'coloreffect' => '']);
                    }
                    
                    // Query ProductVariationColor to get only colors available for selected product combination
                    $query = \App\Models\ProductVariationColor::with(['color.paint_type', 'productVariation']);
                    
                    // Filter by product IDs (required)
                    $query->whereHas('productVariation', function($q) use ($request) {
                        $q->whereIn('product_id', $request->productIds);
                    });
                    
                    // Filter by size IDs if provided
                    if ($request->filled('sizesIds') && count($request->sizesIds) > 0) {
                        $query->whereHas('productVariation', function($q) use ($request) {
                            $q->whereIn('size_id', $request->sizesIds);
                        });
                    }
                    
                    // Filter by thickness IDs if provided
                    if ($request->filled('thicknessIds') && count($request->thicknessIds) > 0) {
                        $query->whereHas('productVariation', function($q) use ($request) {
                            $q->whereIn('thickness_id', $request->thicknessIds);
                        });
                    }
                    
                    // Filter by finish IDs if provided
                    if ($request->filled('finishIds') && count($request->finishIds) > 0) {
                        $query->whereHas('productVariation', function($q) use ($request) {
                            $q->whereIn('finish_id', $request->finishIds);
                        });
                    }
                    
                    // Filter by paint type IDs
                    $query->whereHas('productVariation', function($q) use ($ids) {
                        $q->whereIn('paint_type_id', $ids);
                    });
                    
                    // Get unique colors from the filtered results
                    $variationColors = $query->get();
                    $colors = $variationColors->pluck('color')->unique('id')->filter()->sortBy('name');
                    
                    $colorsoutput = '';
                    foreach ($colors as $color) {
                        if ($color) {
                            $colorsoutput .= '<li>
                                                    <label>
                                                        <input type="checkbox" class="custom-check" name="colorsIds[]" value="' . $color->id . '">
                                                        ' . $color->name . '
                                                    </label>
                                                </li>';
                        }
                    }
                    
                    // Get color effects from the filtered product variations
                    $colorEffectIds = $variationColors->pluck('productVariation.color_effect_id')->unique()->filter();
                    $coloreffects = \App\Models\ColorEffect::whereIn('id', $colorEffectIds)->orderBy('name', 'asc')->get(['id', 'name']);
                    
                    $coloreffectoutput = '';
                    foreach ($coloreffects as $coloreffect) { 
                        if($coloreffect->name == 'Standard/No Effect'){
                            $coloreffectoutput .= '<li>
                                    <label>
                                        <input type="checkbox" class="custom-check" name="colorEffectIds[]" value="' . $coloreffect->id . '">
                                        ' . $coloreffect->name . '
                                    </label>
                                </li>';
                        }
                    }
                    
                    return response()->json(['colors' => $colorsoutput, 'coloreffect' => $coloreffectoutput]);
                }
            }
        }
    }
}

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
use App\Models\ProductFile;
use App\Models\ProductVariation;
use App\Models\ProductVariationColor;
use App\Models\Size;
use App\Models\Specification;
use App\Models\Thickness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller
{

    public function productManufacturer(Request $request)
    {
        $filter = $request->query('filter');
        if (!$filter) return back();

        [$divisionCode, $specificationNumber] = explode('-', $filter) + [null, null];
        if (!$divisionCode || !$specificationNumber) return back()->with('error', 'Invalid filter format.');

        $division = Division::where('code', $divisionCode)->first();
        if (!$division) return back()->with('error', 'Division not found.');

        $specification = Specification::where([
            'division_id' => $division->id,
            'specification_number' => $specificationNumber
        ])->first();

        if (!$specification) return back()->with('error', 'Specification not found.');

        // Optimized query to fetch manufacturer names and product counts in one go
        $manufacturerProducts = Manufacturer::join('products', 'manufacturers.id', '=', 'products.manufacturer_id')
            ->where('products.division_id', $division->id)
            ->where('products.specification_id', $specification->id)
            ->select('manufacturers.id', 'manufacturers.name',  'manufacturers.image', DB::raw('COUNT(products.id) as product_count'))
            ->groupBy('manufacturers.id', 'manufacturers.name', 'manufacturers.image')
            ->orderBy('manufacturers.name')
            ->get(); 
        if (!$divisionCode || !$specificationNumber) return back()->with('error', 'Invalid filter format.');
        return view('frontend.products.manufacturer.productManufacturer', compact('manufacturerProducts', 'divisionCode', 'specificationNumber'));
    }


    public function productFilter(Request $request)
    {
        // Fetch specifications and divisions
        $specifications = collect();
        $divisions = Division::whereBetween('code', [01, 16])->get(['code', 'id', 'name']);
        $manufacturers = collect();
        // Initialize query for products
        $query = Product::with(['division', 'specification', 'manufacturer'])->orderBy('name', 'asc');
        $division_filter        = null;
        $specification_filter   = null;
        $manufacturer_filter    = null;
        // Apply filtering if `filter` exists in the query
        if ($request->query('filter')) {
            $data = explode('-', $request->query('filter'));

            // Ensure both values exist before filtering
            if (count($data) == 3) {
                $division_filter        = $data[0];
                $specification_filter   = $data[1];
                $manufacturer_filter    = $data[2];
                $divis    = Division::Where('code', $division_filter)->first();
                if ($divis) {
                    $specifications = Specification::where('division_id', $divis->id)->where('specification_number', $specification_filter)->get(['material_type', 'specification_number', 'id']);
                }

                $manufacturers =  Manufacturer::where('id', $manufacturer_filter)->orderBy('name', 'asc')->get(['name', 'id']);
                // Apply filtering conditions
                $query->whereHas('division', function ($q) use ($division_filter) {
                    $q->where('code', $division_filter);
                })->whereHas('specification', function ($q) use ($specification_filter) {
                    $q->where('specification_number', trim($specification_filter));
                })->whereHas('manufacturer', function ($q) use ($manufacturer_filter) {
                    $q->where('manufacturer_id', $manufacturer_filter);
                });
            }
        }
        $product_details        = $query->get();
        $total_products_count    = Product::count(); // Total products in the database
        $filtered_products_count = $product_details->count(); // Number of products after filtering
        //get sizes thickness paint type color

        return view('frontend.products.filter')->with([
            'product_details' => $product_details,
            'divisions' => $divisions,
            'manufacturers' => $manufacturers,
            'specifications' => $specifications,
            'total_products_count' => $total_products_count,
            'filtered_products_count' => $filtered_products_count,
            'division_filter' => $division_filter,
            'specification_filter' => $specification_filter,
            'manufacturer_filter' => $manufacturer_filter,
        ]);
    }

    public function getProducts(Request $request)
    {
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
        ])->orderBy('created_at', 'desc');

        if ($request->ajax()) {
            // Apply filters
            $query->when($request->filled('productIds'), fn($q) => $q->whereHas('productVariation.product', fn($q) => $q->whereIn('id', $request->productIds)));
            $query->when($request->filled('divisionIds'), fn($q) => $q->whereHas('productVariation.product.division', fn($q) => $q->whereIn('id', $request->divisionIds)));
            $query->when($request->filled('specificationIds'), fn($q) => $q->whereHas('productVariation.product.specification', fn($q) => $q->whereIn('id', $request->specificationIds)));
            $query->when($request->filled('manufacturerIds'), fn($q) => $q->whereHas('productVariation.product.manufacturer', fn($q) => $q->whereIn('id', $request->manufacturerIds)));
            $query->when($request->filled('sizesIds'), fn($q) => $q->whereHas('productVariation', fn($q) => $q->whereIn('size_id', $request->sizesIds)));
            $query->when($request->filled('thicknessIds'), fn($q) => $q->whereHas('productVariation', fn($q) => $q->whereIn('thickness_id', $request->thicknessIds)));
            $query->when($request->filled('painttypesIds'), fn($q) => $q->whereHas('productVariation', fn($q) => $q->whereIn('paint_type_id', $request->painttypesIds)));
            $query->when($request->filled('finishIds'), fn($q) => $q->whereHas('productVariation', fn($q) => $q->whereIn('finish_id', $request->finishIds)));
            $query->when($request->filled('colorEffectIds'), fn($q) => $q->whereHas('productVariation', fn($q) => $q->whereIn('color_effect_id', $request->colorEffectIds)));
            $query->when($request->filled('colorsIds'), fn($q) => $q->whereIn('color_id', $request->colorsIds));

            // Product name search
            $query->when($request->filled('productNames'), function ($q) use ($request) {
                $productNames = is_array($request->productNames) ? $request->productNames : explode(',', $request->productNames);
                $q->whereHas('productVariation.product', fn($query) => $query->whereIn('name', $productNames));
            });

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($variationColor) {
                    $cart = session()->get('cart', []);
                    $isChecked = isset($cart[$variationColor->id]) ? 'checked' : '';

                    return '<input type="checkbox" class="variation-checkbox custom-check" 
                                data-color-id="' . encrypt($variationColor->color_id) . '" 
                                data-variation-id="' . encrypt($variationColor->product_variation_id) . '" 
                                data-product-color-variation-id="' . encrypt($variationColor->id) . '" 
                                data-quantity="1" 
                                ' . $isChecked . '>';
                })
                ->addColumn('quantity', function ($variationColor) {
                    $cart = session()->get('cart', []);
                    if (isset($cart[$variationColor->id])) {
                        return '<input type="number" class="quantity-input" data-price="' . e($variationColor->productVariation->pricing) . '" min="0" value="' . e($cart[$variationColor->id]['quantity']) . '">';
                    } else {
                        return '<input type="number" class="quantity-input" data-price="' . e($variationColor->productVariation->pricing) . '" min="0" value="0">';
                    }
                })
                ->addColumn('subtotal', function ($variationColor) {
                    $cart = session()->get('cart', []);
                    if (isset($cart[$variationColor->id])) {
                        return '<span class="subtotal">' . e($cart[$variationColor->id]['quantity'] * $variationColor->productVariation->pricing) . '</span>';
                    } else {
                        return '<span class="subtotal">$0</span>';
                    }
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
                })
                ->rawColumns(['checkbox', 'quantity', 'subtotal', 'product_name'])
                ->make(true);
        }
    }


    public function productDetail($slug)
    {
        $product = Product::with([
            'division',
            'specification',
            'manufacturer',
            'productFiles',
            'variations.size',
            'variations.thickness',
            'variations.paint_type',
            'variations.finish',
            'variations.color_effect',
            'variations.colors'
        ])->where('slug', $slug)->firstOrFail();

        $product_files = $product->productFiles->groupBy('type');

        return view('frontend.products.productdetail', compact('product', 'product_files'));
    }
    public function getProductVariations(Request $request)
    {
        // Load ProductVariation with all necessary fields
        $query = ProductVariationColor::with([
            'productVariation' => function ($q) {
                $q->select([
                    'id',
                    'product_id',
                    'size_id',
                    'thickness_id',
                    'finish_id',
                    'paint_type_id',
                    'color_id',
                    'color_effect_id'
                ]);
            },
            'productVariation.product.division',
            'productVariation.product.specification',
            'productVariation.product.manufacturer',
            'productVariation.size',
            'productVariation.thickness',
            'productVariation.finish',
            'productVariation.paint_type',
            'productVariation.color_effect',
            'color'
        ])->whereHas('productVariation', function ($q) use ($request) {
            $q->where('product_id', $request->product_id);
        });

        $thicknesses        = collect();
        $finishes           = collect();
        $paintTypes         = collect();
        $colors             = collect();
        $colorEffects       = collect();
        $selectedVariation  = null;

        // DEBUG: Check if the query is correct
        // dd($query->toSql(), $query->getBindings()); // Uncomment this for debugging

        if ($request->size_id) {
            $query->whereHas('productVariation', function ($q) use ($request) {
                $q->where('size_id', $request->size_id);
            });

            // FIXED: Ensure thickness_id is retrieved correctly
            $thicknesses = Thickness::whereIn('id', $query->get()->pluck('productVariation.thickness_id'))->get();
        }

        if ($request->thickness_id) {
            $query->whereHas('productVariation', function ($q) use ($request) {
                $q->where('thickness_id', $request->thickness_id);
            });

            // FIXED: Ensure finish_id is retrieved correctly
            $finishes = Finish::whereIn('id', $query->get()->pluck('productVariation.finish_id'))->get();
        }

        if ($request->finish_id) {
            $query->whereHas('productVariation', function ($q) use ($request) {
                $q->where('finish_id', $request->finish_id);
            });

            // FIXED: Ensure paint_type_id is retrieved correctly
            $paintTypes = PaintType::whereIn('id', $query->get()->pluck('productVariation.paint_type_id'))->get();
        }

        if ($request->paint_type_id) {
            $query->whereHas('productVariation', function ($q) use ($request) {
                $q->where('paint_type_id', $request->paint_type_id);
            });

            // FIXED: Ensure color_id is retrieved correctly
            $colors = Color::whereIn('id', $query->get()->pluck('color_id'))->get();
        }

        if ($request->color_id) {
            $query->where('color_id', $request->color_id);

            // FIXED: Ensure color_effect_id is retrieved correctly
            $colorEffects = ColorEffect::whereIn('id', $query->get()->pluck('productVariation.color_effect_id'))->get();
        }

        if ($request->color_effect_id) {
            $query->whereHas('productVariation', function ($q) use ($request) {
                $q->where('color_effect_id', $request->color_effect_id);
            });
        }

        if ($request->size_id && $request->thickness_id && $request->finish_id && $request->paint_type_id && $request->color_id && $request->color_effect_id) {
            $selectedVariation = $query->with('productVariation')->first();

        }

        return response()->json([
            'thickness'      => $thicknesses,
            'finish'         => $finishes,
            'paintTypes'     => $paintTypes,
            'colors'         => $colors,
            'colorEffects'   => $colorEffects,
            'selectedVariation' => $selectedVariation,
            'not_available'  => !$selectedVariation,
        ]);
    }
}

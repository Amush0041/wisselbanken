<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ProductDataTable;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\Color;
use App\Models\ColorEffect;
use App\Models\Division;
use App\Models\Finish;
use App\Models\Manufacturer;
use App\Models\PaintType;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductColorEffect;
use App\Models\ProductFinish;
use App\Models\ProductPaintType;
use App\Models\ProductSize;
use App\Models\ProductThickness;
use App\Models\ProductVariation;
use App\Models\Size;
use App\Models\Specification;
use App\Models\Thickness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ProductDataTable $dataTable)
    {
        return $dataTable->render('admin.products.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $divisions      = Division::where('status', 1)->orderBy('name', 'asc')->get();
        $specifications = Specification::where('status', 1)->orderBy('specification_number', 'asc')->get();
        $manufacturers   =  Manufacturer::where('status', 1)->orderBy('name', 'asc')->pluck('name', 'id');
        $sizes           = Size::where('status', 1)->orderBy('name', 'asc')->get();
        $thicknesses      = Thickness::where('status', 1)->orderBy('name', 'asc')->get();
        $finishes         = Finish::where('status', 1)->orderBy('name', 'asc')->get();
        $paintTypes      = PaintType::where('status', 1)->orderBy('name', 'asc')->get();
        // Fetch only Color Effects linked to this product 
        $colorEffects    = ColorEffect::where('status', 1)->orderBy('name', 'asc')->get();
        return view('admin.products.create', compact('divisions', 'specifications', 'manufacturers', 'sizes', 'thicknesses', 'finishes', 'paintTypes', 'colorEffects'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreProductRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'division_id' => 'required|integer',
            'specification_id' => 'required|integer',
            'manufacturer_id' => 'required|integer',
            'feature_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'size_id.*' => 'required|integer',
            'thickness_id.*' => 'required|integer',
            'finish_id.*' => 'required|integer',
            'paint_type_id.*' => 'required|integer',
            'color_id.*' => 'required|array',
            'color_effect_id.*' => 'required|integer',
            'pricing.*' => 'required|numeric|min:0',
        ]);

        // Handle Manufacturer (Create if not exists)

        $manufacturer = Manufacturer::find($request->manufacturer_id);
        if ($manufacturer) {
            $manufacturer_id = $manufacturer->id;
        } else {
            $manufacturer = Manufacturer::updateOrCreate(
                ['name' => $request->manufacturer_id],
                ['name' => $request->manufacturer_id, 'slug' => Str::slug($request->manufacturer_id), 'status' => 1] // Create if not exists
            );
            $manufacturer_id = $manufacturer->id;
        }



        // Handle Feature Image Upload
        $feature_image = null;
        if ($request->hasFile('feature_image')) {
            $image = $request->feature_image->store('product-detail/feature_image', 'public');
            $feature_image = 'storage/' . $image;
        }

        // Create Product 
        $product = Product::create([
            'name'                  => $request->name,
            'user_id'               => Auth::user()->id,
            'division_id'           => $request->division_id,
            'specification_id'      => $request->specification_id,
            'material_type_id'      => $request->specification_id,
            'manufacturer_id'       => $manufacturer_id,
            'product_description'   => $request->description,
            'feature_image'         => $feature_image,
            'status'                => 1,
        ]);

        // Create Product Variations
        foreach ($request->size_id as $key => $size_id) {
            $productVariation = ProductVariation::create([
                'product_id'       => $product->id,
                'user_id'          => Auth::user()->id,
                'size_id'          => $size_id,
                'thickness_id'     => $request->thickness_id[$key] ?? null,
                'finish_id'        => $request->finish_id[$key] ?? null,
                'paint_type_id'    => $request->paint_type_id[$key] ?? null,
                'color_effect_id'  => $request->color_effect_id[$key] ?? null,
                'pricing'       => $request->pricing[$key] ?? 0,
            ]);

            // Attach colors to the product variation
            if (!empty($request->color_id[$key])) {
                $productVariation->colors()->sync($request->color_id[$key]);
            }
        }

        return redirect('admin/products')->with(['status' => 'success', 'message' => 'Product added successfully!']);
    }


    public function show($id)
    {
        //
    }


    public function edit($id)
    {
        $product        = Product::with(['productVariation'])->find($id);
        $divisions      = Division::where('status', 1)->orderBy('name', 'asc')->get();
        $specifications = Specification::where('status', 1)->where('division_id', $product->division_id)->orderBy('specification_number', 'asc')->get();
        $material_type  = Specification::where('status', 1)->where('id', $product->specification_id)->first();
        $manufacturers  = Manufacturer::where('status', 1)->orderBy('name', 'asc')->pluck('name', 'id');
        $sizes           = Size::where('status', 1)->orderBy('name', 'asc')->get();
        $thicknesses      = Thickness::where('status', 1)->orderBy('name', 'asc')->get();
        $finishes         = Finish::where('status', 1)->orderBy('name', 'asc')->get();
        $paintTypes      = PaintType::where('status', 1)->orderBy('name', 'asc')->get();
        // Fetch only Color Effects linked to this product 
        $colorEffects    = ColorEffect::where('status', 1)->orderBy('name', 'asc')->get();
        // Fetch only Colors that belong to these Color Effects 
        $paintTypeIds = ProductVariation::where('product_id', $product->id)->pluck('paint_type_id');
        $colors        = Color::whereIn('paint_type_id', $paintTypeIds)->get();

        return view('admin.products.edit', compact('product', 'divisions', 'material_type', 'specifications', 'manufacturers', 'sizes', 'thicknesses', 'finishes', 'paintTypes', 'colors', 'colorEffects'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateColorRequest  $request
     * @param  \App\Models\Color  $color
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'division_id' => 'required|integer',
            'specification_id' => 'required|integer',
            'manufacturer_id' => 'required',
            'feature_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'size_id.*' => 'required',
            'thickness_id.*' => 'required',
            'finish_id.*' => 'required',
            'paint_type_id.*' => 'required',
            'color_id.*' => 'required|array',
            'color_effect_id.*' => 'required',
            'pricing.*' => 'required|numeric|min:0',
        ]);
    
        // Handle Manufacturer (Create if not exists)
        $manufacturer = Manufacturer::find($request->manufacturer_id);
        if (!$manufacturer) {
            $manufacturer = Manufacturer::updateOrCreate(
                ['name' => $request->manufacturer_id],
                ['name' => $request->manufacturer_id, 'slug' => Str::slug($request->manufacturer_id), 'status' => 1]
            );
        }
        $manufacturer_id = $manufacturer->id;
    
        // Find the product
        $product = Product::findOrFail($id);
    
        // Handle Feature Image Upload
        if ($request->hasFile('feature_image')) {
            if (File::exists($product->feature_image)) {
                File::delete($product->feature_image);
            }
            $image = $request->feature_image->store('product-detail/feature_image', 'public');
            $feature_image = 'storage/' . $image;
        } else {
            $feature_image = $product->feature_image;
        }
    
        // Update Product
        $product->update([
            'name' => $request->name,
            'user_id' => Auth::user()->id,
            'division_id' => $request->division_id,
            'specification_id' => $request->specification_id,
            'material_type_id' => $request->specification_id,
            'manufacturer_id' => $manufacturer_id,
            'product_description' => $request->description,
            'feature_image' => $feature_image,
            'status' => 1,
        ]);
    
        // Process variations
        $existingVariationIds = $request->variation_id ?? [];
    
        // Get current variations in the database
        $existingVariations = $product->productVariation()->pluck('id')->toArray();
    
        // Identify variations to delete
        $variationsToDelete = array_diff($existingVariations, $existingVariationIds);
    
        // Helper function to check if ProductVariationColor is used in lists or pallets
        $isVariationColorUsed = function($variationColorId) {
            $usedInPallet = \App\Models\Pallet::where('product_variation_color_id', $variationColorId)->exists();
            $usedInList = \App\Models\SavedListItem::where('product_color_variation_id', $variationColorId)->exists();
            return $usedInPallet || $usedInList;
        };
    
        // **Safely remove variations - check if colors are used before deleting**
        foreach ($variationsToDelete as $variationId) {
            $variation = ProductVariation::find($variationId);
            if ($variation) {
                // Get all ProductVariationColor records for this variation
                $variationColors = \App\Models\ProductVariationColor::where('product_variation_id', $variationId)->get();
                
                foreach ($variationColors as $variationColor) {
                    // Check if this variation color is used in lists or pallets
                    if ($isVariationColorUsed($variationColor->id)) {
                        // If used, just detach from variation (set product_variation_id to null)
                        // But ProductVariationColor requires product_variation_id, so we can't null it
                        // Instead, we'll keep the record but it will be orphaned
                        // The variation will be deleted but the color record stays for lists/pallets
                    } else {
                        // If not used, safe to delete
                        $variationColor->delete();
                    }
                }
                
                // Now delete the variation
                $variation->delete();
            }
        }
    
        // Helper function to get or create ID for a field
        $getOrCreateId = function($value, $model, $fieldName) {
            if (is_numeric($value)) {
                return (int) $value;
            }
            
            // Handle new values (format: "new_value" or just the value)
            $name = str_replace('new_', '', $value);
            
            // Find or create the record with slug
            $record = $model::firstOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'status' => 1
                ]
            );
            
            return $record->id;
        };
        
        // Add or update variations
        foreach ($request->size_id as $index => $sizeId) {
            // Skip if required fields are missing
            if (!isset($request->thickness_id[$index]) || 
                !isset($request->finish_id[$index]) || 
                !isset($request->paint_type_id[$index]) || 
                !isset($request->pricing[$index])) {
                continue;
            }
            
            $variationData = [
                'size_id' => $getOrCreateId($sizeId, Size::class, 'size'),
                'thickness_id' => $getOrCreateId($request->thickness_id[$index], Thickness::class, 'thickness'),
                'user_id' => Auth::user()->id,
                'finish_id' => $getOrCreateId($request->finish_id[$index], Finish::class, 'finish'),
                'paint_type_id' => $getOrCreateId($request->paint_type_id[$index], PaintType::class, 'paint_type'),
                'pricing' => $request->pricing[$index] ?? 0,
                'color_effect_id'  => !empty($request->color_effect_id[$index]) ? $getOrCreateId($request->color_effect_id[$index], ColorEffect::class, 'color_effect') : null,
            ];
    
            // Update or create product variation
            $variation = $product->productVariation()->updateOrCreate(
                ['id' => $request->variation_id[$index] ?? null],
                $variationData
            );
    
            // Handle colors - get or create IDs for new colors
            $colorIds = [];
            if (isset($request->color_id[$index]) && is_array($request->color_id[$index])) {
                foreach ($request->color_id[$index] as $colorValue) {
                    $paintTypeId = $variationData['paint_type_id'];
                    if (is_numeric($colorValue)) {
                        $colorIds[] = (int) $colorValue;
                    } else {
                        $colorName = str_replace('new_', '', $colorValue);
                        $color = Color::firstOrCreate(
                            [
                                'name' => $colorName,
                                'paint_type_id' => $paintTypeId
                            ],
                            [
                                'name' => $colorName,
                                'slug' => Str::slug($colorName),
                                'paint_type_id' => $paintTypeId,
                                'status' => 1
                            ]
                        );
                        $colorIds[] = $color->id;
                    }
                }
            }
            
            // Get existing ProductVariationColor records for this variation
            $existingVariationColors = \App\Models\ProductVariationColor::where('product_variation_id', $variation->id)
                ->pluck('color_id', 'id')
                ->toArray();
            
            // Process each color - check if it already exists, if not create it
            $variationColorIdsToKeep = [];
            foreach ($colorIds as $colorId) {
                // Check if this color already exists for this variation
                $existingVariationColorId = array_search($colorId, $existingVariationColors);
                
                if ($existingVariationColorId !== false) {
                    // Already exists - keep it
                    $variationColorIdsToKeep[] = $existingVariationColorId;
                } else {
                    // New color - create ProductVariationColor record
                    $newVariationColor = \App\Models\ProductVariationColor::create([
                        'product_variation_id' => $variation->id,
                        'color_id' => $colorId
                    ]);
                    $variationColorIdsToKeep[] = $newVariationColor->id;
                }
            }
            
            // Find ProductVariationColor records to remove (colors that are no longer selected)
            $variationColorsToRemove = \App\Models\ProductVariationColor::where('product_variation_id', $variation->id)
                ->whereNotIn('id', $variationColorIdsToKeep)
                ->get();
            
            // Remove only those that are not used in lists or pallets
            foreach ($variationColorsToRemove as $variationColorToRemove) {
                if (!$isVariationColorUsed($variationColorToRemove->id)) {
                    // Safe to delete - not used anywhere
                    $variationColorToRemove->delete();
                }
                // If used, keep it (it will be orphaned but still accessible for lists/pallets)
            }
        }
    
        return redirect('admin/products')->with(['status' => 'success', 'message' => 'Product updated successfully!']);
    }
    
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        if (File::exists($product->feature_image)) {
            File::delete($product->feature_image);
        }
        // First, delete the related colors manually
        Color::whereIn('product_variation_id', ProductVariation::where('product_id', $id)->pluck('id'))->delete();

        // Then, delete the variations
        ProductVariation::where('product_id', $id)->delete();
        $product->delete();
        return response()->json(['status' => 'success', 'message' => 'Product delete successfully.']);
    }



    public function fetchSpecification(Request $request)
    {

        $data['specification'] = Specification::where("division_id", $request->division_id)->get(["specification_number", 'material_type', "id"]);

        return response()->json($data);
    }

    public function fetchMaterialType(Request $request)
    {

        $data['materials'] = Specification::where("id", $request->specification_id)->get(["material_type", "id"])->first();

        return response()->json($data);
    }

    public function productImport(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|mimes:xlsx,csv',
        ]);

        try {
            $import = new ProductImport();
            Excel::import($import, $request->file('file'));
            
            return redirect()->back()->with('success', 'Data imported successfully!');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
            
            foreach ($failures as $failure) {
                $errorMessages[] = "Row " . ($failure->row() + 1) . ": " . implode(', ', $failure->errors());
            }
            
            return redirect()->back()
                ->with('error', 'Import failed with validation errors:')
                ->with('error_details', $errorMessages);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Check if error message contains our custom error format
            if (strpos($errorMessage, 'Import completed with errors:') !== false) {
                $errorDetails = explode("\n", $errorMessage);
                array_shift($errorDetails); // Remove the first line "Import completed with errors:"
                
                return redirect()->back()
                    ->with('error', 'Import completed with some errors. Please review and fix the issues below:')
                    ->with('error_details', $errorDetails);
            }
            
            return redirect()->back()
                ->with('error', 'Error importing data: ' . $errorMessage);
        }
    }

    public function fetchColors(Request $request)
    {
        $paintTypeIds = $request->paint_type_id;
        $colors = Color::where('paint_type_id', $paintTypeIds)->get();
        return response()->json($colors);
    }
}

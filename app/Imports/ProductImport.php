<?php

namespace App\Imports;

use App\Models\Color;
use App\Models\ColorEffect;
use Illuminate\Support\Str;
use App\Models\Division;
use App\Models\Finish;
use App\Models\Manufacturer;
use App\Models\PaintType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Size;
use App\Models\Specification;
use App\Models\Thickness;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Validation\ValidationException;
use PgSql\Lob;

class ProductImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure
{
    protected $errors = [];
    protected $rowNumber = 0;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'division' => 'required',
            'specification' => 'required',
            'material' => 'required',
            'manufacturer' => 'required',
            'product' => 'required',
            'size' => 'required',
            'thickness' => 'required',
            'finish' => 'required',
            'paint_type' => 'required',
            'color' => 'required',
            'color_effect' => 'required',
            'pricing' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * @param Failure[] $failures
     */
    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = "Row " . ($failure->row() + 2) . ": " . implode(', ', $failure->errors());
        }
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        $product_id = '';
        $rowNumber = 2; // Start from row 2 (row 1 is header)
        
        foreach ($collection as $key => $row) {
            $currentRow = $rowNumber++;
            
            try {
                // Validate required fields exist
                $requiredFields = ['division', 'specification', 'material', 'manufacturer', 'product', 'size', 'thickness', 'finish', 'paint_type', 'color', 'color_effect'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (empty($row[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    $this->errors[] = "Row {$currentRow}: Missing required fields: " . implode(', ', $missingFields);
                    continue; // Skip this row
                }
                
                // Validate division exists
                $division = Division::where('code', $row['division'])->first();
                if (!$division) {
                    $this->errors[] = "Row {$currentRow}: Division code '{$row['division']}' not found in database";
                    continue;
                }
                
                // Validate pricing if provided
                if (isset($row['pricing']) && !empty($row['pricing']) && !is_numeric($row['pricing'])) {
                    $this->errors[] = "Row {$currentRow}: Pricing must be a number (value: '{$row['pricing']}')";
                    continue;
                }

                // Process multiple sizes with unique slug
                $size_slug = $this->generateUniqueSlug(Size::class, trim($row['size']));
                $sizeid = Size::updateOrCreate(
                    ['name' => $row['size']],
                    ['slug' => $size_slug, 'status' => 1]
                );

                // Process multiple thicknesses with unique slug
                $thickness_slug = $this->generateUniqueSlug(Thickness::class, trim($row['thickness']));
                $thicknesses = Thickness::updateOrCreate(
                    ['name' => $row['thickness']],
                    ['slug' => $thickness_slug, 'status' => 1]
                );

                // Process multiple finishes with unique slugs
                $finish_slug = $this->generateUniqueSlug(Finish::class, trim($row['finish']));
                $finishes = Finish::updateOrCreate(
                    ['name' => $row['finish']],
                    ['slug' => $finish_slug, 'status' => 1]
                );

                // Save Paint Types
                $paint_type_slug = $this->generateUniqueSlug(PaintType::class, trim($row['paint_type']));
                $paint_type = PaintType::updateOrCreate(
                    ['name' => $row['paint_type']],
                    ['slug' => $paint_type_slug, 'status' => 1]
                );

                // Save Colors (individually for each paint type)
                $color_ids = [];
                $colorNames = collect(explode(',', $row['color']))->map('trim')->unique()->toArray();
                foreach ($colorNames as $colorName) {
                    if (!empty($colorName)) {
                        $color = Color::firstOrCreate(
                            ['name' => $colorName, 'paint_type_id' => $paint_type->id],
                            ['slug' => $this->generateUniqueSlug(Color::class, $colorName), 'status' => 1]
                        );
                        $color_ids[] = $color->id;
                    }
                }
                
                // Save Color Effects (Independent of Paint Types & Colors)
                $color_effect_id_slug = $this->generateUniqueSlug(ColorEffect::class, trim($row['color_effect']));
                $color_effect_id = ColorEffect::updateOrCreate(
                    ['name' => $row['color_effect']],
                    ['slug' => $color_effect_id_slug, 'status' => 1]
                );

                // Ensure unique slug for Product
                $product_name = trim($row['product']);
                $product_slug = $this->generateUniqueSlug(Product::class, $product_name);
                
                // Check or create Specification 
                $specification_slug = $this->generateUniqueSlug(Specification::class, trim($row['specification']));
                $specification = Specification::updateOrCreate(
                    ['specification_number' => $row['specification']],
                    ['division_id' => $division->id, 'material_type' => $row['material'], 'slug' => $specification_slug, 'status' => 1]
                );

                $manufacturer_slug = $this->generateUniqueSlug(Manufacturer::class, trim($row['manufacturer']));
                $manufacturer = Manufacturer::updateOrCreate(
                    ['name' => $row['manufacturer']],
                    ['slug' => $manufacturer_slug, 'status' => 1]
                );

                $product = Product::updateOrCreate(
                    [
                        'name'             => $row['product'] ?? '',
                        'division_id'      => $division->id,
                        'specification_id' => $specification->id,
                    ],
                    [
                        'user_id'             => Auth::user()->id,
                        'material_type_id'    => $specification->id,
                        'manufacturer_id'     => $manufacturer->id,
                        'product_description' => $row['description'] ?? '',
                        'slug'                => $product_slug,
                        'status'              => 1,
                    ]
                );

                $product_id = $product->id;

                $productVariation = ProductVariation::firstOrCreate(
                    [
                        'product_id'    => $product_id,
                        'user_id'       => Auth::user()->id,
                        'size_id'          => $sizeid->id,
                        'thickness_id'     => $thicknesses->id,
                        'finish_id'        => $finishes->id,
                        'paint_type_id'    => $paint_type->id,
                        'color_effect_id'  => $color_effect_id->id,
                        'pricing'       => $row['pricing'] ?? 0,
                    ]
                );

                // Attach multiple colors to the product variation
                $productVariation->colors()->sync($color_ids);
                
            } catch (\Exception $e) {
                $this->errors[] = "Row {$currentRow}: " . $e->getMessage();
                Log::error("Product import error on row {$currentRow}: " . $e->getMessage(), [
                    'row' => $row,
                    'trace' => $e->getTraceAsString()
                ]);
                continue; // Skip this row and continue with next
            }
        }
        
        // If there are errors, throw an exception with all errors
        if (!empty($this->errors)) {
            throw new \Exception("Import completed with errors:\n" . implode("\n", $this->errors));
        }
    }

    private function generateUniqueSlug($model, $name)
    {
        $slug = Str::slug($name);
        $original_slug = $slug;
        $count = 1;

        // Check if slug already exists
        while ($model::where('slug', $slug)->exists()) {
            $slug = $original_slug . '-' . $count;
            $count++;
        }

        return $slug;
    }
}

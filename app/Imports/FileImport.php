<?php

namespace App\Imports;

use App\Models\Color;
use App\Models\ColorEffect;
use App\Models\Finish;
use App\Models\PaintType;
use App\Models\Size;
use App\Models\Thickness;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FileImport implements ToCollection, WithHeadingRow
{
    protected $errors = [];

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
        DB::beginTransaction(); // Start Transaction
        try {
            $rowNumber = 2; // Start from row 2 (row 1 is header)
            
            foreach ($collection as $row) {
                $currentRow = $rowNumber++;
                
                try {
                    // Check if row is completely empty
                    $rowHasData = false;
                    foreach ($row as $value) {
                        if (!empty($value)) {
                            $rowHasData = true;
                            break;
                        }
                    }
                    
                    if (!$rowHasData) {
                        $this->errors[] = "Row {$currentRow}: Row is empty, skipping";
                        continue;
                    }
                    
                    // ✅ Import Size
                    if (!empty($row['size'])) {  
                    $size_slug = Str::slug($row['size']);
                    $size_originalSlug = $size_slug;
                    $size_counter = 1;

                    while (Size::where('slug', $size_slug)->exists()) {
                        $size_slug = $size_originalSlug . '-' . $size_counter;
                        $size_counter++;
                    }

                    Size::updateOrCreate(
                        ['name' => $row['size']], 
                        [
                            'name' => $row['size'],
                            'slug' => $size_slug, 
                            'status' => 1,
                        ]
                    );
                }

                // ✅ Import Thickness
                if (!empty($row['thickness'])) {  
                    $thickness_slug = Str::slug($row['thickness']);
                    $thickness_originalSlug = $thickness_slug;
                    $thickness_counter = 1;

                    while (Thickness::where('slug', $thickness_slug)->exists()) {
                        $thickness_slug = $thickness_originalSlug . '-' . $thickness_counter;
                        $thickness_counter++;
                    }

                    Thickness::updateOrCreate(
                        ['name' => $row['thickness']], 
                        [
                            'name' => $row['thickness'],
                            'slug' => $thickness_slug, 
                            'status' => 1,
                        ]
                    );
                }

                // ✅ Import Finish
                if (!empty($row['finish'])) {  
                    $finish_slug = Str::slug($row['finish']);
                    $finish_originalSlug = $finish_slug;
                    $finish_counter = 1;

                    while (Finish::where('slug', $finish_slug)->exists()) {
                        $finish_slug = $finish_originalSlug . '-' . $finish_counter;
                        $finish_counter++;
                    }

                    Finish::updateOrCreate(
                        ['name' => $row['finish']], 
                        [
                            'name' => $row['finish'],
                            'slug' => $finish_slug, 
                            'status' => 1,
                        ]
                    );
                }

                // ✅ Import Paint Type
                if (!empty($row['paint_type'])) {  
                    $paint_type_slug = Str::slug($row['paint_type']);
                    $paint_type_originalSlug = $paint_type_slug;
                    $paint_type_counter = 1;

                    while (PaintType::where('slug', $paint_type_slug)->exists()) {
                        $paint_type_slug = $paint_type_originalSlug . '-' . $paint_type_counter;
                        $paint_type_counter++;
                    }

                    PaintType::updateOrCreate(
                        ['name' => $row['paint_type']], 
                        [
                            'name' => $row['paint_type'],
                            'slug' => $paint_type_slug, 
                            'status' => 1,
                        ]
                    );
                }

                // ✅ Import Color
                if (!empty($row['color'])) {  
                    $color_slug = Str::slug($row['color']);
                    $color_originalSlug = $color_slug;
                    $color_counter = 1;

                    while (Color::where('slug', $color_slug)->exists()) {
                        $color_slug = $color_originalSlug . '-' . $color_counter;
                        $color_counter++;
                    }

                    Color::updateOrCreate(
                        ['name' => $row['color']], 
                        [
                            'name' => $row['color'],
                            'slug' => $color_slug, 
                            'status' => 1,
                        ]
                    );
                }

                // ✅ Import Color Effect
                if (!empty($row['color_effect'])) {  
                    $color_effect_slug = Str::slug($row['color_effect']);
                    $color_effect_originalSlug = $color_effect_slug;
                    $color_effect_counter = 1;

                    while (ColorEffect::where('slug', $color_effect_slug)->exists()) {
                        $color_effect_slug = $color_effect_originalSlug . '-' . $color_effect_counter;
                        $color_effect_counter++;
                    }

                    ColorEffect::updateOrCreate(
                        ['name' => $row['color_effect']], 
                        [
                            'name' => $row['color_effect'],
                            'slug' => $color_effect_slug, 
                            'status' => 1,
                        ]
                    );
                }
                } catch (\Exception $e) {
                    $this->errors[] = "Row {$currentRow}: " . $e->getMessage();
                    Log::error("File import error on row {$currentRow}: " . $e->getMessage(), [
                        'row' => $row,
                        'trace' => $e->getTraceAsString()
                    ]);
                    continue; // Skip this row and continue with next
                }
            }
            
            // If there are errors, throw an exception with all errors BEFORE committing
            if (!empty($this->errors)) {
                DB::rollback(); // Rollback if there are errors
                throw new \Exception("Import completed with errors:\n" . implode("\n", $this->errors));
            }
            
            DB::commit(); // ✅ Commit changes only if no errors occurred
        } catch (\Exception $e) {
            // Only rollback if transaction is still active (not already committed or rolled back)
            if (DB::transactionLevel() > 0) {
                DB::rollback(); // ❌ Rollback on failure
            }
            
            // If it's our custom error message, rethrow it
            if (strpos($e->getMessage(), 'Import completed with errors:') !== false) {
                throw $e;
            }
            
            // Otherwise, wrap it with more context
            throw new \Exception("Error importing file: " . $e->getMessage());
        }
    }
}

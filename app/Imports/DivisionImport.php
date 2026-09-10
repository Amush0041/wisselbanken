<?php

namespace App\Imports;

use App\Models\Division;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class DivisionImport implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (!empty($row['division']) && !empty($row['division_name'])) {
                // Generate initial slug
                $slug = Str::slug($row['division_name']);
                
                // Ensure slug is unique
                $originalSlug = $slug;
                $counter = 1;
                while (Division::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                // Update or create the division
                Division::updateOrCreate(
                    ['code' => $row['division']], // Unique identifier
                    [
                        'name' => $row['division_name'],
                        'slug' => $slug,
                        'code' => $row['division'],
                        'status' => 1,
                    ]
                );
            }
        }
    }
}

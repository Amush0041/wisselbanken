<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateTaxesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('state_taxes')->insert([
            ['state' => 'Alabama', 'state_tax_rate' => 4.00, 'avg_local_tax_rate' => 5.43, 'max_local' => 8.00, 'combined_tax_rate' => 9.43],
            ['state' => 'Alaska', 'state_tax_rate' => 0.00, 'avg_local_tax_rate' => 1.82, 'max_local' => 7.85, 'combined_tax_rate' => 1.82],
            ['state' => 'Arizona', 'state_tax_rate' => 5.60, 'avg_local_tax_rate' => 2.81, 'max_local' => 5.30, 'combined_tax_rate' => 8.41],
            ['state' => 'Arkansas', 'state_tax_rate' => 6.50, 'avg_local_tax_rate' => 2.96, 'max_local' => 6.125, 'combined_tax_rate' => 9.46],
            ['state' => 'California', 'state_tax_rate' => 7.25, 'avg_local_tax_rate' => 1.55, 'max_local' => 4.75, 'combined_tax_rate' => 8.80],
            ['state' => 'Colorado', 'state_tax_rate' => 2.90, 'avg_local_tax_rate' => 4.96, 'max_local' => 8.30, 'combined_tax_rate' => 7.86],
            ['state' => 'Connecticut', 'state_tax_rate' => 6.35, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 6.35],
            ['state' => 'Delaware', 'state_tax_rate' => 0.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 0.00],
            ['state' => 'Florida', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 1.05, 'max_local' => 2.00, 'combined_tax_rate' => 7.05],
            ['state' => 'Georgia', 'state_tax_rate' => 4.00, 'avg_local_tax_rate' => 3.42, 'max_local' => 5.00, 'combined_tax_rate' => 7.42],
            ['state' => 'Hawaii', 'state_tax_rate' => 4.00, 'avg_local_tax_rate' => 0.50, 'max_local' => 0.50, 'combined_tax_rate' => 4.50],
            ['state' => 'Idaho', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.03, 'max_local' => 3.00, 'combined_tax_rate' => 6.03],
            ['state' => 'Illinois', 'state_tax_rate' => 6.25, 'avg_local_tax_rate' => 2.64, 'max_local' => 4.75, 'combined_tax_rate' => 8.89],
            ['state' => 'Indiana', 'state_tax_rate' => 7.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 7.00],
            ['state' => 'Iowa', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.94, 'max_local' => 2.00, 'combined_tax_rate' => 6.94],
            ['state' => 'Kansas', 'state_tax_rate' => 6.50, 'avg_local_tax_rate' => 2.27, 'max_local' => 4.25, 'combined_tax_rate' => 8.77],
            ['state' => 'Kentucky', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 6.00],
            ['state' => 'Louisiana', 'state_tax_rate' => 4.45, 'avg_local_tax_rate' => 5.12, 'max_local' => 7.00, 'combined_tax_rate' => 9.57],
            ['state' => 'Maine', 'state_tax_rate' => 5.50, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 5.50],
            ['state' => 'Maryland', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 6.00],
            ['state' => 'Massachusetts', 'state_tax_rate' => 6.25, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 6.25],
            ['state' => 'Michigan', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 6.00],
            ['state' => 'Minnesota', 'state_tax_rate' => 6.875, 'avg_local_tax_rate' => 0.55, 'max_local' => 2.00, 'combined_tax_rate' => 7.43],
            ['state' => 'Mississippi', 'state_tax_rate' => 7.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 7.00],
            ['state' => 'Missouri', 'state_tax_rate' => 4.225, 'avg_local_tax_rate' => 4.19, 'max_local' => 5.875, 'combined_tax_rate' => 8.41],
            ['state' => 'Montana', 'state_tax_rate' => 0.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 0.00],
            ['state' => 'Nebraska', 'state_tax_rate' => 5.50, 'avg_local_tax_rate' => 1.47, 'max_local' => 2.00, 'combined_tax_rate' => 6.97],
            ['state' => 'Nevada', 'state_tax_rate' => 6.85, 'avg_local_tax_rate' => 1.39, 'max_local' => 1.525, 'combined_tax_rate' => 8.24],
            ['state' => 'New Hampshire', 'state_tax_rate' => 0.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 0.00],
            ['state' => 'New Jersey', 'state_tax_rate' => 6.625, 'avg_local_tax_rate' => -0.03, 'max_local' => 3.3125, 'combined_tax_rate' => 6.60],
            ['state' => 'New Mexico', 'state_tax_rate' => 4.875, 'avg_local_tax_rate' => 2.75, 'max_local' => 4.5625, 'combined_tax_rate' => 7.63],
            ['state' => 'New York', 'state_tax_rate' => 4.00, 'avg_local_tax_rate' => 4.53, 'max_local' => 4.875, 'combined_tax_rate' => 8.53],
            ['state' => 'North Carolina', 'state_tax_rate' => 4.75, 'avg_local_tax_rate' => 2.25, 'max_local' => 2.75, 'combined_tax_rate' => 7.00],
            ['state' => 'North Dakota', 'state_tax_rate' => 5.00, 'avg_local_tax_rate' => 1.48, 'max_local' => 3.50, 'combined_tax_rate' => 6.48],
            ['state' => 'Ohio', 'state_tax_rate' => 5.75, 'avg_local_tax_rate' => 1.56, 'max_local' => 2.25, 'combined_tax_rate' => 7.31],
            ['state' => 'Oklahoma', 'state_tax_rate' => 4.50, 'avg_local_tax_rate' => 4.50, 'max_local' => 7.00, 'combined_tax_rate' => 9.00],
            ['state' => 'Oregon', 'state_tax_rate' => 0.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 0.00],
            ['state' => 'Pennsylvania', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.34, 'max_local' => 2.00, 'combined_tax_rate' => 6.34],
            ['state' => 'Rhode Island', 'state_tax_rate' => 7.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 7.00],
            ['state' => 'South Carolina', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 1.50, 'max_local' => 3.00, 'combined_tax_rate' => 7.50],
            ['state' => 'South Dakota', 'state_tax_rate' => 4.50, 'avg_local_tax_rate' => 1.91, 'max_local' => 4.50, 'combined_tax_rate' => 6.41],
            ['state' => 'Tennessee', 'state_tax_rate' => 7.00, 'avg_local_tax_rate' => 2.56, 'max_local' => 2.75, 'combined_tax_rate' => 9.56],
            ['state' => 'Texas', 'state_tax_rate' => 6.25, 'avg_local_tax_rate' => 1.94, 'max_local' => 2.00, 'combined_tax_rate' => 8.19],
            ['state' => 'Utah', 'state_tax_rate' => 6.10, 'avg_local_tax_rate' => 1.22, 'max_local' => 4.20, 'combined_tax_rate' => 7.32],
            ['state' => 'Vermont', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.37, 'max_local' => 1.00, 'combined_tax_rate' => 6.37],
            ['state' => 'Virginia', 'state_tax_rate' => 5.30, 'avg_local_tax_rate' => 0.47, 'max_local' => 2.70, 'combined_tax_rate' => 5.77],
            ['state' => 'Washington', 'state_tax_rate' => 6.50, 'avg_local_tax_rate' => 2.93, 'max_local' => 4.10, 'combined_tax_rate' => 9.43],
            ['state' => 'West Virginia', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.57, 'max_local' => 1.00, 'combined_tax_rate' => 6.57],
            ['state' => 'Wisconsin', 'state_tax_rate' => 5.00, 'avg_local_tax_rate' => 0.70, 'max_local' => 2.90, 'combined_tax_rate' => 5.70],
            ['state' => 'Wyoming', 'state_tax_rate' => 4.00, 'avg_local_tax_rate' => 1.44, 'max_local' => 2.00, 'combined_tax_rate' => 5.44],
            ['state' => 'District of Columbia', 'state_tax_rate' => 6.00, 'avg_local_tax_rate' => 0.00, 'max_local' => 0.00, 'combined_tax_rate' => 6.00],
        ]);
    }
} 
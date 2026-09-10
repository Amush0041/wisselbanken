<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@admin.com'], // Check if this email exists
            [
                'name' => 'Admin',
                'password' => Hash::make('123456789'), // Change the password as needed
                'role' => 'admin', // Add this field if your users table has a role column
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add name column
            $table->string('name')->nullable()->after('order_number');
            
            // Drop first_name and last_name columns
            $table->dropColumn(['first_name', 'last_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add back first_name and last_name columns
            $table->string('first_name')->nullable()->after('order_number');
            $table->string('last_name')->nullable()->after('first_name');
            
            // Drop name column
            $table->dropColumn('name');
        });
    }
};

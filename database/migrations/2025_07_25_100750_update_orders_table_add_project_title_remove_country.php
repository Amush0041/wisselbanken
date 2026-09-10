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
            // Add project_title column
            $table->string('project_title')->nullable()->after('name');
            
            // Drop country column
            $table->dropColumn('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add back country column
            $table->string('country')->nullable()->after('postcode');
            
            // Drop project_title column
            $table->dropColumn('project_title');
        });
    }
};

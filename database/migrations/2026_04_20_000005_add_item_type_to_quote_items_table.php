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
        if (!Schema::hasTable('quote_items') || Schema::hasColumn('quote_items', 'item_type')) {
            return;
        }

        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('product')->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('quote_items') || !Schema::hasColumn('quote_items', 'item_type')) {
            return;
        }

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};

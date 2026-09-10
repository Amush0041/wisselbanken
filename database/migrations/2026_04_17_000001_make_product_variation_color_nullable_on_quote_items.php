<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropForeign(['product_variation_color_id']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('product_variation_color_id')->nullable()->change();
            $table->foreign('product_variation_color_id')
                ->references('id')
                ->on('product_variation_color')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropForeign(['product_variation_color_id']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('product_variation_color_id')->nullable(false)->change();
            $table->foreign('product_variation_color_id')
                ->references('id')
                ->on('product_variation_color')
                ->onDelete('cascade');
        });
    }
};


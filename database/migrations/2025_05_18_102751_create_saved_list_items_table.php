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
        Schema::create('saved_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_list_id')->constrained('saved_lists')->onDelete('cascade');
            $table->foreignId('product_variation_id')->constrained('product_variations')->onDelete('cascade');
            $table->foreignId('product_color_variation_id')->constrained('product_variation_color')->onDelete('cascade');
            $table->foreignId('color_id')->constrained('colors')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamps();
            
            // Create separate indexes instead of one composite
            $table->index(['saved_list_id']);
            $table->index(['product_variation_id']);
            $table->index(['color_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_list_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_variation_color_id');
            $table->string('name', 512)->nullable();
            $table->text('description')->nullable();
            $table->decimal('default_unit_price', 10, 2)->default(0);
            $table->text('item_notes')->nullable();
            $table->timestamps();

            $table->foreign('product_variation_color_id')
                ->references('id')
                ->on('product_variation_color')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'product_variation_color_id'], 'user_products_user_pvc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_products');
    }
};

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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('size_id')->nullable(); 
            $table->integer('thickness_id')->nullable(); 
            $table->integer('finish_id')->nullable(); 
            $table->integer('paint_type_id')->nullable(); 
            $table->longText('color_id')->nullable();
            $table->integer('color_effect_id')->nullable();
            $table->string('pricing')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};

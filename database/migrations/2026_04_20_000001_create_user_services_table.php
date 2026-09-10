<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 512);
            $table->text('description')->nullable();
            $table->string('content_hash', 64);
            $table->decimal('default_unit_price', 10, 2)->default(0);
            $table->text('item_notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'content_hash'], 'user_services_user_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_services');
    }
};

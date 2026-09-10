<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('state');
            $table->decimal('state_tax_rate', 5, 3);
            $table->decimal('avg_local_tax_rate', 5, 3);
            $table->decimal('max_local', 5, 3);
            $table->decimal('combined_tax_rate', 5, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_taxes');
    }
}; 
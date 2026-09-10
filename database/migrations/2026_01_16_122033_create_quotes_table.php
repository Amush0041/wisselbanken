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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('saved_list_id')->nullable()->constrained('saved_lists')->onDelete('set null');
            $table->string('name')->nullable();
            $table->string('project_name')->nullable();
            $table->string('quote_number')->unique();
            $table->enum('status', ['draft', 'completed', 'sent'])->default('draft');
            $table->text('notes')->nullable();
            $table->boolean('internal_notes')->default(false);
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};

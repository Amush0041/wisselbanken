<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_products')) {
            return;
        }

        Schema::table('user_products', function (Blueprint $table) {
            if (! Schema::hasColumn('user_products', 'parent_product_id')) {
                $table->unsignedBigInteger('parent_product_id')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('user_products', 'variant_size')) {
                $table->string('variant_size', 255)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('user_products', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('variant_size');
            }
            if (! Schema::hasColumn('user_products', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_archived');
            }
        });

        try {
            Schema::table('user_products', function (Blueprint $table) {
                $table->foreign('parent_product_id')
                    ->references('id')
                    ->on('user_products')
                    ->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // Ignore if foreign key already exists in this environment.
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive for production safety.
    }
};


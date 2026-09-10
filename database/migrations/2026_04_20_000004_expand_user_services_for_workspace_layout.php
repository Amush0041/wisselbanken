<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_services')) {
            return;
        }

        Schema::table('user_services', function (Blueprint $table) {
            if (! Schema::hasColumn('user_services', 'service_name')) {
                $table->string('service_name', 512)->nullable()->after('title');
            }
            if (! Schema::hasColumn('user_services', 'sku')) {
                $table->string('sku', 255)->nullable()->after('service_name');
            }
            if (! Schema::hasColumn('user_services', 'variant_size')) {
                $table->string('variant_size', 255)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('user_services', 'quantity')) {
                $table->decimal('quantity', 12, 2)->default(1)->after('variant_size');
            }
            if (! Schema::hasColumn('user_services', 'unit_type')) {
                $table->string('unit_type', 100)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('user_services', 'tax_label')) {
                $table->string('tax_label', 255)->nullable()->after('unit_type');
            }
            if (! Schema::hasColumn('user_services', 'inventory_enabled')) {
                $table->boolean('inventory_enabled')->default(true)->after('tax_label');
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};


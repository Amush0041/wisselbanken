<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_products')) {
            return;
        }

        if (Schema::hasColumn('user_products', 'product_variation_color_id')) {
            try {
                Schema::table('user_products', function (Blueprint $table) {
                    $table->dropForeign(['product_variation_color_id']);
                });
            } catch (\Throwable $e) {
                // Constraint may already be removed on some environments.
            }

            DB::statement('ALTER TABLE `user_products` MODIFY `product_variation_color_id` BIGINT UNSIGNED NULL');
        }

        Schema::table('user_products', function (Blueprint $table) {
            if (! Schema::hasColumn('user_products', 'sku')) {
                $table->string('sku', 255)->nullable()->after('name');
            }
            if (! Schema::hasColumn('user_products', 'category')) {
                $table->string('category', 255)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('user_products', 'quantity')) {
                $table->decimal('quantity', 12, 2)->default(1)->after('category');
            }
            if (! Schema::hasColumn('user_products', 'unit_type')) {
                $table->string('unit_type', 100)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('user_products', 'buy_price')) {
                $table->decimal('buy_price', 12, 2)->default(0)->after('default_unit_price');
            }
            if (! Schema::hasColumn('user_products', 'buy_price_tax')) {
                $table->decimal('buy_price_tax', 12, 2)->default(0)->after('buy_price');
            }
            if (! Schema::hasColumn('user_products', 'sell_price')) {
                $table->decimal('sell_price', 12, 2)->default(0)->after('buy_price_tax');
            }
            if (! Schema::hasColumn('user_products', 'sell_price_tax')) {
                $table->decimal('sell_price_tax', 12, 2)->default(0)->after('sell_price');
            }
            if (! Schema::hasColumn('user_products', 'currency')) {
                $table->string('currency', 10)->default('PKR')->after('sell_price_tax');
            }
            if (! Schema::hasColumn('user_products', 'stock')) {
                $table->decimal('stock', 12, 2)->default(0)->after('currency');
            }
            if (! Schema::hasColumn('user_products', 'inventory_enabled')) {
                $table->boolean('inventory_enabled')->default(true)->after('stock');
            }
            if (! Schema::hasColumn('user_products', 'on_hand_stock')) {
                $table->decimal('on_hand_stock', 12, 2)->default(0)->after('inventory_enabled');
            }
            if (! Schema::hasColumn('user_products', 'committed_stock')) {
                $table->decimal('committed_stock', 12, 2)->default(0)->after('on_hand_stock');
            }
            if (! Schema::hasColumn('user_products', 'available_for_sale')) {
                $table->decimal('available_for_sale', 12, 2)->default(0)->after('committed_stock');
            }
            if (! Schema::hasColumn('user_products', 'to_be_invoiced')) {
                $table->decimal('to_be_invoiced', 12, 2)->default(0)->after('available_for_sale');
            }
            if (! Schema::hasColumn('user_products', 'to_be_billed')) {
                $table->decimal('to_be_billed', 12, 2)->default(0)->after('to_be_invoiced');
            }
            if (! Schema::hasColumn('user_products', 'image_url')) {
                $table->string('image_url', 1024)->nullable()->after('to_be_billed');
            }
        });
    }

    public function down(): void
    {
        // Keep backwards-safe; we intentionally do not drop columns automatically.
    }
};


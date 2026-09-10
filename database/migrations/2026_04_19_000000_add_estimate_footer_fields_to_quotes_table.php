<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->text('terms_and_conditions')->nullable()->after('notes');
            $table->text('staff_notes')->nullable()->after('terms_and_conditions');
            $table->decimal('shipping_cost', 12, 2)->default(0)->after('shipping_method');
            $table->string('order_discount_raw', 64)->nullable()->after('shipping_cost');
            $table->json('attachments')->nullable()->after('order_discount_raw');
        });

        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'internal_notes')) {
                $table->dropColumn('internal_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'terms_and_conditions',
                'staff_notes',
                'shipping_cost',
                'order_discount_raw',
                'attachments',
            ]);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->boolean('internal_notes')->default(false)->after('notes');
        });
    }
};

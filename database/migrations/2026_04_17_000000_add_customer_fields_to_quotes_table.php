<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('saved_list_id')->constrained('customers')->nullOnDelete();
            $table->text('customer_address')->nullable()->after('project_name');
            $table->string('currency', 10)->default('USD')->after('quote_number');
            $table->date('estimate_date')->nullable()->after('currency');
            $table->string('shipping_method')->nullable()->after('estimate_date');

            $table->index(['user_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'customer_id']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['customer_address', 'currency', 'estimate_date', 'shipping_method']);
        });
    }
};


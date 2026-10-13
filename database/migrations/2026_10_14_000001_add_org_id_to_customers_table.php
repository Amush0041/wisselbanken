<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'org_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('org_id')->nullable()->after('user_id');
            $table->foreign('org_id', 'customers_org_id_foreign')
                ->references('id')->on('organizations')->nullOnDelete();
            $table->index(['org_id', 'is_active'], 'customers_org_id_is_active_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'org_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign('customers_org_id_foreign');
            $table->dropIndex('customers_org_id_is_active_index');
            $table->dropColumn('org_id');
        });
    }
};

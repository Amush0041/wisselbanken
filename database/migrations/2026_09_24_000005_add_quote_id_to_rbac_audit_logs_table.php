<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rbac_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        if (DB::table('rbac_audit_logs')->whereNotNull('quote_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: rbac_audit_logs has rows with quote_id set. Rolling back would destroy that audit data.'
            );
        }

        Schema::table('rbac_audit_logs', function (Blueprint $table) {
            $table->dropColumn('quote_id');
        });
    }
};

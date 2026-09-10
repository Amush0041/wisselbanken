<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC Phase 2 — team size captured at registration, used to pick the onboarding role
 * bundle (plan §6.1). Solo / 2-5 / 6-20 / 20+.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('team_size')->nullable()->after('org_type');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('team_size');
        });
    }
};

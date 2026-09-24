<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_crosswalk', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('org_id')
                ->constrained('projects')->cascadeOnDelete();
            $table->index(['org_id', 'project_id']);
        });

        Schema::table('plan_crosswalk', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('plan_crosswalk')->whereNull('quote_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: plan_crosswalk has rows with quote_id IS NULL (new-format rows). Rolling back would orphan them.'
            );
        }

        Schema::table('plan_crosswalk', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable(false)->change();
        });

        Schema::table('plan_crosswalk', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['org_id', 'project_id']);
            $table->dropColumn('project_id');
        });
    }
};

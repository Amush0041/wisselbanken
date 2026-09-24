<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')
                ->constrained('projects')->cascadeOnDelete();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('project_members')->whereNull('quote_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: project_members has rows with quote_id IS NULL (new-format rows). Rolling back would orphan them.'
            );
        }

        Schema::table('project_members', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable(false)->change();
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropUnique(['project_id', 'user_id']);
            $table->dropColumn('project_id');
        });
    }
};

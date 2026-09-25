<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rfq_requests', 'project_id')) {
            return;
        }

        Schema::table('rfq_requests', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('org_id')
                ->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rfq_requests', 'project_id')) {
            return;
        }

        Schema::table('rfq_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};

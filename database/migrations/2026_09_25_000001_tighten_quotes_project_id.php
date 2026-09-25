<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->guard();

        if ($this->projectIdIsNullable()) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (! $this->projectIdIsNullable()) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->change();
            });
        }
    }

    private function guard(): void
    {
        if (! app()->environment('testing')) {
            if (! app()->isDownForMaintenance()) {
                throw new RuntimeException('Phase 5 migrations require maintenance mode (php artisan down). Nothing was changed.');
            }

            if (! config('rbac.phase5_backup_confirmed')) {
                throw new RuntimeException('Set PHASE5_BACKUP_CONFIRMED=1 after the backup has been test-restored. Nothing was changed.');
            }
        }

        $orphans = DB::table('quotes')->whereNull('project_id')->count();

        if ($orphans > 0) {
            throw new RuntimeException(
                "Refusing: {$orphans} quote(s) (trashed included) have project_id IS NULL. "
                .'Run the Phase 2 backfill first. Check: SELECT COUNT(*) FROM quotes WHERE project_id IS NULL;'
            );
        }
    }

    private function projectIdIsNullable(): bool
    {
        foreach (Schema::getColumns('quotes') as $column) {
            if ($column['name'] === 'project_id') {
                return (bool) $column['nullable'];
            }
        }

        throw new RuntimeException('quotes.project_id does not exist.');
    }
};

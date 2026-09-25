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

        if (Schema::hasColumn('project_members', 'quote_id')) {
            DB::table('project_members')->whereNull('project_id')->delete();

            $foreign = collect(Schema::getForeignKeys('project_members'))
                ->first(fn ($fk) => $fk['columns'] === ['quote_id']);

            if ($foreign) {
                Schema::table('project_members', function (Blueprint $table) {
                    $table->dropForeign(['quote_id']);
                });
            }

            $unique = collect(Schema::getIndexes('project_members'))
                ->first(fn ($index) => $index['unique'] && $index['columns'] === ['quote_id', 'user_id']);

            if ($unique) {
                Schema::table('project_members', function (Blueprint $table) {
                    $table->dropUnique(['quote_id', 'user_id']);
                });
            }

            Schema::table('project_members', function (Blueprint $table) {
                $table->dropColumn('quote_id');
            });
        }

        if ($this->projectIdIsNullable()) {
            Schema::table('project_members', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible: legacy project_members rows and quote_id were dropped. Restore the backup.');
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

        if (! Schema::hasColumn('project_members', 'quote_id')) {
            return;
        }

        $uncovered = DB::table('project_members as pm')
            ->leftJoin('quotes as q', 'q.id', '=', 'pm.quote_id')
            ->leftJoin('project_members as p2', function ($join) {
                $join->on('p2.project_id', '=', 'q.project_id')->on('p2.user_id', '=', 'pm.user_id')
                    ->on('p2.org_id', '=', 'pm.org_id')
                    ->where('p2.is_active', '=', 1);
            })
            ->whereNull('pm.project_id')
            ->where('pm.is_active', true)
            ->whereNull('p2.id')
            ->count();

        if ($uncovered > 0) {
            throw new RuntimeException(
                "Refusing: {$uncovered} active legacy project_members row(s) are not covered by an active same-organization project membership for the same user. "
                .'Review membership_org_mismatch.csv and re-enrol or deactivate them first. '
                .'Check: SELECT COUNT(*) FROM project_members pm JOIN quotes q ON q.id=pm.quote_id '
                .'LEFT JOIN project_members p2 ON p2.project_id=q.project_id AND p2.user_id=pm.user_id AND p2.org_id=pm.org_id AND p2.is_active=1 '
                .'WHERE pm.project_id IS NULL AND pm.is_active=1 AND p2.id IS NULL;'
            );
        }

        $unkeyed = DB::table('project_members')->whereNull('project_id')->whereNull('quote_id')->count();

        if ($unkeyed > 0) {
            throw new RuntimeException(
                "Refusing: {$unkeyed} project_members row(s) have neither project_id nor quote_id. "
                .'Check: SELECT COUNT(*) FROM project_members WHERE project_id IS NULL AND quote_id IS NULL;'
            );
        }
    }

    private function projectIdIsNullable(): bool
    {
        foreach (Schema::getColumns('project_members') as $column) {
            if ($column['name'] === 'project_id') {
                return (bool) $column['nullable'];
            }
        }

        throw new RuntimeException('project_members.project_id does not exist.');
    }
};

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

        if (Schema::hasColumn('plan_crosswalk', 'quote_id')) {
            $foreign = collect(Schema::getForeignKeys('plan_crosswalk'))
                ->first(fn ($fk) => $fk['columns'] === ['quote_id']);

            if ($foreign) {
                Schema::table('plan_crosswalk', function (Blueprint $table) {
                    $table->dropForeign(['quote_id']);
                });
            }

            $index = collect(Schema::getIndexes('plan_crosswalk'))
                ->first(fn ($index) => $index['columns'] === ['org_id', 'quote_id']);

            if ($index) {
                $hasOtherOrgIndex = collect(Schema::getIndexes('plan_crosswalk'))
                    ->contains(fn ($other) => $other['columns'] !== ['org_id', 'quote_id'] && ($other['columns'][0] ?? null) === 'org_id');

                if (! $hasOtherOrgIndex) {
                    Schema::table('plan_crosswalk', function (Blueprint $table) {
                        $table->index(['org_id', 'project_id']);
                    });
                }

                Schema::table('plan_crosswalk', function (Blueprint $table) {
                    $table->dropIndex(['org_id', 'quote_id']);
                });
            }

            Schema::table('plan_crosswalk', function (Blueprint $table) {
                $table->dropColumn('quote_id');
            });
        }

        if ($this->projectIdIsNullable()) {
            Schema::table('plan_crosswalk', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable(false)->change();
            });
        }

        $unique = collect(Schema::getIndexes('plan_crosswalk'))
            ->first(fn ($index) => $index['unique'] && $index['columns'] === ['project_id', 'plan_line_code']);

        if (! $unique) {
            Schema::table('plan_crosswalk', function (Blueprint $table) {
                $table->unique(['project_id', 'plan_line_code']);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible: plan_crosswalk.quote_id was dropped. Restore the backup.');
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

        $unlinked = DB::table('plan_crosswalk')->whereNull('project_id')->count();

        if ($unlinked > 0) {
            throw new RuntimeException(
                "Refusing: {$unlinked} plan_crosswalk row(s) have project_id IS NULL. Relink or delete them manually. "
                .'Check: SELECT COUNT(*) FROM plan_crosswalk WHERE project_id IS NULL;'
            );
        }

        $mismatched = DB::table('plan_crosswalk as c')
            ->join('projects as p', 'p.id', '=', 'c.project_id')
            ->whereColumn('c.org_id', '<>', 'p.org_id')
            ->count();

        if ($mismatched > 0) {
            throw new RuntimeException(
                "Refusing: {$mismatched} plan_crosswalk row(s) belong to a different organization than their project. "
                .'Check: SELECT COUNT(*) FROM plan_crosswalk c JOIN projects p ON p.id=c.project_id WHERE c.org_id<>p.org_id;'
            );
        }

        $duplicates = DB::table('plan_crosswalk')
            ->select('project_id', 'plan_line_code')
            ->groupBy('project_id', 'plan_line_code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException(
                "Refusing: {$duplicates} duplicate (project_id, plan_line_code) pair(s) in plan_crosswalk. Resolve them manually. "
                .'Check: SELECT project_id, plan_line_code FROM plan_crosswalk GROUP BY 1,2 HAVING COUNT(*)>1;'
            );
        }
    }

    private function projectIdIsNullable(): bool
    {
        foreach (Schema::getColumns('plan_crosswalk') as $column) {
            if ($column['name'] === 'project_id') {
                return (bool) $column['nullable'];
            }
        }

        throw new RuntimeException('plan_crosswalk.project_id does not exist.');
    }
};

<?php

namespace Tests\Feature\Rbac;

use App\Models\PlanCrosswalk;
use App\Models\Project;
use App\Models\Rbac\Organization;
use App\Models\Rbac\ProjectMember;
use App\Models\User;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 5b: the three tightening migrations, run against the pre-5b schema that RbacTestCase builds from the
 * real Phase 1 migrations (in-memory sqlite, no real database). Proves the guards, the resulting schema and
 * re-runnability; it does not prove MariaDB DDL behaviour (see the B1 gap list in REVIEW.md "QA results - Phase 5").
 */
class Phase5MigrationsTest extends RbacTestCase
{
    private const QUOTES = '2026_09_25_000001_tighten_quotes_project_id.php';
    private const MEMBERS = '2026_09_25_000002_tighten_project_members.php';
    private const CROSSWALK = '2026_09_25_000003_tighten_plan_crosswalk.php';

    private Organization $orgA;
    private Organization $orgB;
    private User $user;
    private User $other;
    private int $pA;
    private int $pB;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'A', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $this->orgB = Organization::create(['name' => 'B', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
        $this->pA = $this->project($this->orgA);
        $this->pB = $this->project($this->orgB);
    }

    // ---- helpers ---------------------------------------------------------------

    private function migration(string $file): object
    {
        return require database_path('migrations/'.$file);
    }

    private function up(string $file): void
    {
        $this->migration($file)->up();
    }

    private function project(Organization $org): int
    {
        return (int) DB::table('projects')->insertGetId([
            'org_id' => $org->id, 'name' => 'P'.++$this->seq, 'status' => 'active', 'created_by' => $this->user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function quote(?int $projectId, ?string $deletedAt = null): int
    {
        return (int) DB::table('quotes')->insertGetId([
            'user_id' => $this->user->id, 'project_id' => $projectId, 'quote_number' => 'Q-'.++$this->seq,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deletedAt,
        ]);
    }

    private function projectRow(int $projectId, User $u, Organization $org): int
    {
        return (int) DB::table('project_members')->insertGetId([
            'project_id' => $projectId, 'user_id' => $u->id, 'org_id' => $org->id, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function legacyRow(?int $quoteId, User $u, Organization $org, bool $active = true, ?int $projectId = null): int
    {
        return (int) DB::table('project_members')->insertGetId([
            'quote_id' => $quoteId, 'project_id' => $projectId, 'user_id' => $u->id, 'org_id' => $org->id, 'is_active' => $active,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function xw(?int $projectId, string $code, ?Organization $org = null, ?int $quoteId = null): int
    {
        return (int) DB::table('plan_crosswalk')->insertGetId([
            'org_id' => ($org ?? $this->orgA)->id, 'quote_id' => $quoteId, 'project_id' => $projectId, 'plan_line_code' => $code,
            'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function nullable(string $table, string $column): bool
    {
        foreach (Schema::getColumns($table) as $c) {
            if ($c['name'] === $column) {
                return (bool) $c['nullable'];
            }
        }
        $this->fail("$table.$column missing");
    }

    private function index(string $table, array $columns, ?bool $unique = null): bool
    {
        foreach (Schema::getIndexes($table) as $i) {
            if ($i['columns'] === $columns && ($unique === null || $i['unique'] === $unique)) {
                return true;
            }
        }

        return false;
    }

    private function fk(string $table, array $columns): ?array
    {
        return collect(Schema::getForeignKeys($table))->firstWhere('columns', $columns);
    }

    /** the config/env/maintenance state the guard reads outside `testing` */
    private function production(bool $maintenance, bool $confirmed): void
    {
        $this->app['env'] = 'production';
        config(['rbac.phase5_backup_confirmed' => $confirmed ? '1' : false]);
        $this->app->instance(MaintenanceMode::class, new class($maintenance) implements MaintenanceMode {
            public function __construct(private bool $down)
            {
            }

            public function activate(array $payload): void
            {
            }

            public function deactivate(): void
            {
            }

            public function active(): bool
            {
                return $this->down;
            }

            public function data(): array
            {
                return [];
            }
        });
    }

    private function assertRefuses(string $file, string $pattern): void
    {
        try {
            $this->up($file);
        } catch (\RuntimeException $e) {
            $this->assertMatchesRegularExpression($pattern, $e->getMessage());

            return;
        }
        $this->fail("expected a refusal matching $pattern");
    }

    /** @return array<string, array{0:string}> */
    public static function migrations(): array
    {
        return ['quotes' => [self::QUOTES], 'project_members' => [self::MEMBERS], 'plan_crosswalk' => [self::CROSSWALK]];
    }

    /** @return array<string,mixed> what a refused run must not have touched */
    private function schemaAndRows(): array
    {
        return [
            'quotes.null' => $this->nullable('quotes', 'project_id'),
            'pm.null' => $this->nullable('project_members', 'project_id'),
            'pm.quote_id' => Schema::hasColumn('project_members', 'quote_id'),
            'pm.rows' => DB::table('project_members')->orderBy('id')->get()->all(),
            'xw.null' => $this->nullable('plan_crosswalk', 'project_id'),
            'xw.quote_id' => Schema::hasColumn('plan_crosswalk', 'quote_id'),
            'xw.unique' => $this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true),
            'xw.rows' => DB::table('plan_crosswalk')->orderBy('id')->get()->all(),
            'quotes.rows' => DB::table('quotes')->orderBy('id')->get()->all(),
        ];
    }

    // ---- guard: maintenance mode and backup confirmation ------------------------

    #[DataProvider('migrations')]
    public function test_outside_testing_it_refuses_without_maintenance_mode_and_changes_nothing(string $file): void
    {
        $this->legacyRow($this->quote($this->pA), $this->user, $this->orgA, false);
        $before = $this->schemaAndRows();
        $this->production(false, true);

        $this->assertRefuses($file, '/require maintenance mode \(php artisan down\)\. Nothing was changed\./');

        $this->assertEquals($before, $this->schemaAndRows());
    }

    #[DataProvider('migrations')]
    public function test_outside_testing_it_refuses_without_the_backup_confirmation_and_changes_nothing(string $file): void
    {
        $before = $this->schemaAndRows();
        $this->production(true, false);

        $this->assertRefuses($file, '/Set PHASE5_BACKUP_CONFIRMED=1 after the backup has been test-restored\. Nothing was changed\./');

        $this->assertEquals($before, $this->schemaAndRows());
    }

    #[DataProvider('migrations')]
    public function test_outside_testing_maintenance_is_checked_before_the_confirmation_and_before_the_data(string $file): void
    {
        $this->quote(null);
        $this->xw(null, 'X');
        $this->production(false, false);

        $this->assertRefuses($file, '/maintenance mode/');
    }

    #[DataProvider('migrations')]
    public function test_outside_testing_a_data_violation_is_still_refused_when_both_acknowledgements_are_given(string $file): void
    {
        $this->quote(null);
        $this->xw(null, 'X');
        $this->legacyRow(null, $this->user, $this->orgA);
        $this->production(true, true);

        $this->assertRefuses($file, '/^Refusing: /');
    }

    public function test_outside_testing_with_maintenance_and_confirmation_all_three_apply_on_clean_data(): void
    {
        $this->production(true, true);

        foreach ([self::QUOTES, self::MEMBERS, self::CROSSWALK] as $file) {
            $this->up($file);
        }

        $this->assertFalse($this->nullable('quotes', 'project_id'));
        $this->assertFalse($this->nullable('project_members', 'project_id'));
        $this->assertFalse($this->nullable('plan_crosswalk', 'project_id'));
    }

    #[DataProvider('migrations')]
    public function test_in_testing_the_acknowledgements_are_not_required(string $file): void
    {
        $this->assertTrue(app()->environment('testing'));
        config(['rbac.phase5_backup_confirmed' => false]);

        $this->up($file);

        $this->assertTrue(true);
    }

    public function test_the_confirmation_is_read_from_config_not_from_the_environment(): void
    {
        $this->production(true, false);
        putenv('PHASE5_BACKUP_CONFIRMED=1');
        $_ENV['PHASE5_BACKUP_CONFIRMED'] = '1';
        try {
            $this->assertRefuses(self::QUOTES, '/PHASE5_BACKUP_CONFIRMED/');
        } finally {
            putenv('PHASE5_BACKUP_CONFIRMED');
            unset($_ENV['PHASE5_BACKUP_CONFIRMED']);
        }
    }

    public function test_the_config_key_exists(): void
    {
        $this->assertArrayHasKey('phase5_backup_confirmed', config('rbac'));
    }

    // ---- 000001 quotes ------------------------------------------------------------

    public function test_quotes_refuses_a_live_null_project_quote_with_the_count_and_the_check_sql(): void
    {
        $this->quote($this->pA);
        $this->quote(null);

        $this->assertRefuses(self::QUOTES, '/Refusing: 1 quote\(s\) \(trashed included\) have project_id IS NULL\..*SELECT COUNT\(\*\) FROM quotes WHERE project_id IS NULL;/');

        $this->assertTrue($this->nullable('quotes', 'project_id'));
    }

    public function test_quotes_refuses_a_trashed_null_project_quote(): void
    {
        $this->quote($this->pA);
        $this->quote(null, now()->toDateTimeString());

        $this->assertRefuses(self::QUOTES, '/Refusing: 1 quote\(s\)/');
        $this->assertTrue($this->nullable('quotes', 'project_id'));
    }

    public function test_quotes_counts_every_null_quote(): void
    {
        $this->quote(null);
        $this->quote(null, now()->toDateTimeString());
        $this->quote(null);

        $this->assertRefuses(self::QUOTES, '/Refusing: 3 quote\(s\)/');
    }

    public function test_quotes_succeeds_on_clean_data_keeps_the_fk_and_rejects_null_afterwards(): void
    {
        $q = $this->quote($this->pA);
        $this->quote($this->pB, now()->toDateTimeString());

        $this->up(self::QUOTES);

        $this->assertFalse($this->nullable('quotes', 'project_id'));
        $fk = $this->fk('quotes', ['project_id']);
        $this->assertNotNull($fk, 'FK on quotes.project_id survives the change');
        $this->assertSame('projects', $fk['foreign_table']);
        $this->assertSame('restrict', strtolower((string) $fk['on_delete']), 'still RESTRICT (never cascade or set null)');
        $this->assertSame($this->pA, (int) DB::table('quotes')->where('id', $q)->value('project_id'), 'data untouched');
        $this->assertSame(2, DB::table('quotes')->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->quote(null);
    }

    public function test_quotes_fk_still_restricts_deleting_a_project_with_quotes(): void
    {
        $this->quote($this->pA);
        $this->up(self::QUOTES);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('projects')->where('id', $this->pA)->delete();
    }

    public function test_quotes_is_re_runnable_after_a_refusal_once_the_data_is_fixed_and_after_success(): void
    {
        $q = $this->quote(null);
        $this->assertRefuses(self::QUOTES, '/Refusing/');

        DB::table('quotes')->where('id', $q)->update(['project_id' => $this->pA]);
        $this->up(self::QUOTES);
        $this->up(self::QUOTES);

        $this->assertFalse($this->nullable('quotes', 'project_id'));
        $this->assertSame(1, DB::table('quotes')->count());
    }

    public function test_quotes_down_makes_the_column_nullable_again_and_is_idempotent(): void
    {
        $this->up(self::QUOTES);
        $this->assertFalse($this->nullable('quotes', 'project_id'));

        $this->migration(self::QUOTES)->down();
        $this->assertTrue($this->nullable('quotes', 'project_id'));
        $this->assertNotNull($this->quote(null));
        $this->assertNotNull($this->fk('quotes', ['project_id']), 'FK survives down() too');

        $this->migration(self::QUOTES)->down();
        $this->assertTrue($this->nullable('quotes', 'project_id'));
    }

    // ---- amendment 3: T1 backup confirmation mapping ---------------------------------

    private static function forgetKey(array &$bag): void
    {
        unset($bag['PHASE5_BACKUP_CONFIRMED']);
    }

    private function configValueFor(?string $env): mixed
    {
        $previous = [getenv('PHASE5_BACKUP_CONFIRMED'), $_ENV['PHASE5_BACKUP_CONFIRMED'] ?? null, $_SERVER['PHASE5_BACKUP_CONFIRMED'] ?? null];
        try {
            if ($env === null) {
                putenv('PHASE5_BACKUP_CONFIRMED');
                unset($_ENV['PHASE5_BACKUP_CONFIRMED'], $_SERVER['PHASE5_BACKUP_CONFIRMED']);
            } else {
                putenv("PHASE5_BACKUP_CONFIRMED=$env");
                $_ENV['PHASE5_BACKUP_CONFIRMED'] = $env;
                $_SERVER['PHASE5_BACKUP_CONFIRMED'] = $env;
            }

            return (require config_path('rbac.php'))['phase5_backup_confirmed'];
        } finally {
            $previous[0] === false ? putenv('PHASE5_BACKUP_CONFIRMED') : putenv('PHASE5_BACKUP_CONFIRMED='.$previous[0]);
            $previous[1] === null ? self::forgetKey($_ENV) : $_ENV['PHASE5_BACKUP_CONFIRMED'] = $previous[1];
            $previous[2] === null ? self::forgetKey($_SERVER) : $_SERVER['PHASE5_BACKUP_CONFIRMED'] = $previous[2];
        }
    }

    /** @return array<string, array{0:string}> */
    public static function offValues(): array
    {
        return array_combine(['off', 'no', 'n', '0', 'false', 'disabled', 'OFF', 'empty', 'garbage'], array_map(fn ($v) => [$v], ['off', 'no', 'n', '0', 'false', 'disabled', 'OFF', '', 'yes please']));
    }

    /** @return array<string, array{0:string}> */
    public static function onValues(): array
    {
        return array_combine(['1', 'true', 'on', 'TRUE', 'yes'], array_map(fn ($v) => [$v], ['1', 'true', 'on', 'TRUE', 'yes']));
    }

    #[DataProvider('offValues')]
    public function test_the_config_maps_env_off_values_to_false(string $env): void
    {
        $this->assertFalse($this->configValueFor($env), "PHASE5_BACKUP_CONFIRMED=$env");
    }

    #[DataProvider('onValues')]
    public function test_the_config_maps_env_on_values_to_true(string $env): void
    {
        $this->assertTrue($this->configValueFor($env), "PHASE5_BACKUP_CONFIRMED=$env");
    }

    public function test_the_config_is_false_when_the_variable_is_unset(): void
    {
        $this->assertFalse($this->configValueFor(null));
    }

    /** @return array<string, array{0:string, 1:mixed}> */
    public static function falsyConfigs(): array
    {
        $out = [];
        foreach (self::migrations() as $name => [$file]) {
            foreach (['false' => false, 'null' => null, 'zero' => 0] as $label => $value) {
                $out["$name/$label"] = [$file, $value];
            }
        }

        return $out;
    }

    #[DataProvider('falsyConfigs')]
    public function test_outside_testing_each_migration_refuses_when_the_confirmation_config_is_falsy(string $file, mixed $value): void
    {
        $this->production(true, true);
        config(['rbac.phase5_backup_confirmed' => $value]);
        $before = $this->schemaAndRows();

        $this->assertRefuses($file, '/Set PHASE5_BACKUP_CONFIRMED=1 after the backup has been test-restored\. Nothing was changed\./');

        $this->assertEquals($before, $this->schemaAndRows());
    }

    // ---- amendment 3: T3 plan_crosswalk backing index ---------------------------------

    private function orgLeadingIndexes(): array
    {
        return collect(Schema::getIndexes('plan_crosswalk'))->filter(fn ($i) => ($i['columns'][0] ?? null) === 'org_id')->pluck('columns')->values()->all();
    }

    public function test_crosswalk_recreates_the_org_project_index_when_it_was_removed_beforehand(): void
    {
        Schema::table('plan_crosswalk', fn (Blueprint $t) => $t->dropIndex(['org_id', 'project_id']));
        $this->assertSame([['org_id', 'quote_id']], $this->orgLeadingIndexes(), 'precondition: only the legacy org index is left');

        $this->up(self::CROSSWALK);

        $this->assertSame([['org_id', 'project_id']], $this->orgLeadingIndexes());
        $this->assertFalse(Schema::hasColumn('plan_crosswalk', 'quote_id'));
        $this->assertTrue($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));
        $this->assertFalse($this->index('plan_crosswalk', ['org_id', 'quote_id']));
    }

    public function test_crosswalk_with_the_org_project_index_present_drops_the_quote_index_and_column_and_adds_the_unique(): void
    {
        $this->assertTrue($this->index('plan_crosswalk', ['org_id', 'project_id']), 'precondition');

        $this->up(self::CROSSWALK);

        $this->assertFalse(Schema::hasColumn('plan_crosswalk', 'quote_id'));
        $this->assertFalse($this->index('plan_crosswalk', ['org_id', 'quote_id']));
        $this->assertTrue($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));
        $this->assertSame([['org_id', 'project_id']], $this->orgLeadingIndexes(), 'still exactly one org-leading index, none duplicated');
        $this->assertNotNull($this->fk('plan_crosswalk', ['org_id']), 'org_id FK still declared');
        $this->assertNotNull($this->fk('plan_crosswalk', ['project_id']), 'project_id FK still declared');
    }

    // ---- 000002 project_members ---------------------------------------------------

    public function test_members_refuses_an_active_legacy_row_not_covered_by_a_project_row(): void
    {
        $q = $this->quote($this->pA);
        $this->projectRow($this->pA, $this->other, $this->orgA);      // someone else is covered, not this user
        $this->legacyRow($q, $this->user, $this->orgA);
        $before = $this->schemaAndRows();

        $this->assertRefuses(self::MEMBERS, '/Refusing: 1 active legacy project_members row\(s\) are not covered by an active same-organization project membership for the same user\..*membership_org_mismatch\.csv.*SELECT COUNT\(\*\) FROM project_members pm JOIN quotes q.*LEFT JOIN project_members p2 ON p2\.project_id=q\.project_id AND p2\.user_id=pm\.user_id AND p2\.org_id=pm\.org_id AND p2\.is_active=1 .*WHERE pm\.project_id IS NULL AND pm\.is_active=1 AND p2\.id IS NULL;/s');

        $this->assertEquals($before, $this->schemaAndRows(), 'nothing deleted, nothing dropped');
    }

    public function test_members_refuses_when_the_only_project_row_is_inactive(): void
    {
        $q = $this->quote($this->pA);
        $this->legacyRow($q, $this->user, $this->orgA);
        $covering = $this->projectRow($this->pA, $this->user, $this->orgA);
        DB::table('project_members')->where('id', $covering)->update(['is_active' => false]);
        $before = $this->schemaAndRows();

        $this->assertRefuses(self::MEMBERS, '/Refusing: 1 active legacy project_members row\(s\) are not covered by an active same-organization/');

        $this->assertEquals($before, $this->schemaAndRows());
    }

    public function test_members_refuses_when_the_only_project_row_belongs_to_another_organization(): void
    {
        $q = $this->quote($this->pA);
        $this->legacyRow($q, $this->user, $this->orgA);
        $this->projectRow($this->pA, $this->user, $this->orgB);
        $before = $this->schemaAndRows();

        $this->assertRefuses(self::MEMBERS, '/Refusing: 1 active legacy project_members row\(s\) are not covered by an active same-organization/');

        $this->assertEquals($before, $this->schemaAndRows());
    }

    public function test_members_passes_with_an_active_same_organization_project_row_and_drops_the_legacy_row(): void
    {
        $q = $this->quote($this->pA);
        $this->legacyRow($q, $this->user, $this->orgA);
        $covering = $this->projectRow($this->pA, $this->user, $this->orgA);

        $this->up(self::MEMBERS);

        $this->assertSame([$covering], DB::table('project_members')->pluck('id')->map(fn ($v) => (int) $v)->all());
        $this->assertFalse(Schema::hasColumn('project_members', 'quote_id'));
    }

    public function test_members_an_inactive_legacy_row_stays_deletable_without_any_covering_row(): void
    {
        $this->legacyRow($this->quote($this->pA), $this->user, $this->orgA, false);

        $this->up(self::MEMBERS);

        $this->assertSame(0, DB::table('project_members')->count());
    }

    public function test_members_counts_each_uncovered_active_row_and_ignores_the_covered_and_inactive_ones(): void
    {
        $q1 = $this->quote($this->pA);
        $q2 = $this->quote($this->pA);
        $u3 = User::factory()->create();
        $this->legacyRow($q1, $this->user, $this->orgA);                       // uncovered
        $this->legacyRow($q1, $this->other, $this->orgA);                      // uncovered
        $this->legacyRow($q2, $u3, $this->orgA, false);                        // inactive: allowed
        $this->projectRow($this->pA, $u3, $this->orgA);

        $this->assertRefuses(self::MEMBERS, '/Refusing: 2 active legacy/');
    }

    public function test_members_refuses_an_active_legacy_row_on_a_quote_that_has_no_project(): void
    {
        $this->legacyRow($this->quote(null), $this->user, $this->orgA);

        $this->assertRefuses(self::MEMBERS, '/Refusing: 1 active legacy/');
    }

    public function test_members_refuses_an_active_row_with_neither_key(): void
    {
        $this->legacyRow(null, $this->user, $this->orgA);

        $this->assertRefuses(self::MEMBERS, '/^Refusing: /');
        $this->assertTrue(Schema::hasColumn('project_members', 'quote_id'));
        $this->assertSame(1, DB::table('project_members')->count());
    }

    public function test_members_refuses_an_inactive_row_with_neither_key_with_its_own_message(): void
    {
        $this->legacyRow(null, $this->user, $this->orgA, false);

        $this->assertRefuses(self::MEMBERS, '/Refusing: 1 project_members row\(s\) have neither project_id nor quote_id\..*SELECT COUNT\(\*\) FROM project_members WHERE project_id IS NULL AND quote_id IS NULL;/');

        $this->assertTrue(Schema::hasColumn('project_members', 'quote_id'));
        $this->assertSame(1, DB::table('project_members')->count());
    }

    public function test_members_succeeds_deletes_only_the_covered_or_inactive_legacy_rows_and_drops_quote_id(): void
    {
        $q = $this->quote($this->pA);
        $u3 = User::factory()->create();
        $keep = $this->projectRow($this->pA, $this->user, $this->orgA);
        $keepB = $this->projectRow($this->pB, $this->other, $this->orgB);
        $this->legacyRow($q, $this->user, $this->orgA);           // active but covered by $keep
        $this->legacyRow($q, $u3, $this->orgA, false);            // inactive and uncovered
        $this->legacyRow($q, $this->other, $this->orgA, false, null);

        $this->up(self::MEMBERS);

        $this->assertFalse(Schema::hasColumn('project_members', 'quote_id'));
        $this->assertFalse($this->nullable('project_members', 'project_id'));
        $this->assertSame([$keep, $keepB], DB::table('project_members')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all(), 'project rows are kept, legacy rows are gone');
        $this->assertTrue($this->index('project_members', ['project_id', 'user_id'], true), 'unique(project_id, user_id) kept');
        $this->assertFalse($this->index('project_members', ['quote_id', 'user_id']), 'legacy unique gone');
        $this->assertNull($this->fk('project_members', ['quote_id']));
        $this->assertNotNull($this->fk('project_members', ['project_id']), 'FK on project_id kept');
        $this->assertNotNull($this->fk('project_members', ['user_id']));
        $this->assertNotNull($this->fk('project_members', ['org_id']));
    }

    public function test_members_project_id_rejects_null_and_the_unique_pair_still_holds_after_the_migration(): void
    {
        $this->up(self::MEMBERS);

        try {
            DB::table('project_members')->insert(['project_id' => null, 'user_id' => $this->user->id, 'org_id' => $this->orgA->id]);
            $this->fail('NULL project_id must be rejected');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(0, DB::table('project_members')->count());
        }

        $this->projectRow($this->pA, $this->user, $this->orgA);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->projectRow($this->pA, $this->user, $this->orgA);
    }

    public function test_members_models_work_after_the_migration(): void
    {
        $this->up(self::MEMBERS);

        $m = ProjectMember::enrol(Project::findOrFail($this->pA), $this->user->id, $this->orgA->id, null);

        $this->assertSame($this->pA, (int) $m->fresh()->project_id);
        $this->assertFalse(in_array('quote_id', (new ProjectMember)->getFillable(), true));
        $this->assertFalse(method_exists(ProjectMember::class, 'quote'));
    }

    public function test_members_is_re_runnable_after_a_refusal_once_the_data_is_fixed_and_after_success(): void
    {
        $q = $this->quote($this->pA);
        $legacy = $this->legacyRow($q, $this->user, $this->orgA);
        $this->assertRefuses(self::MEMBERS, '/Refusing/');

        $this->projectRow($this->pA, $this->user, $this->orgA);
        $this->up(self::MEMBERS);
        $this->up(self::MEMBERS);

        $this->assertFalse(Schema::hasColumn('project_members', 'quote_id'));
        $this->assertSame(0, DB::table('project_members')->where('id', $legacy)->count());
    }

    public function test_members_resumes_after_a_run_that_died_between_the_fk_drop_and_the_unique_drop(): void
    {
        $q = $this->quote($this->pA);
        $this->projectRow($this->pA, $this->user, $this->orgA);
        $this->legacyRow($q, $this->user, $this->orgA);
        Schema::table('project_members', fn (Blueprint $t) => $t->dropForeign(['quote_id']));
        $this->assertNull($this->fk('project_members', ['quote_id']));
        $this->assertTrue($this->index('project_members', ['quote_id', 'user_id'], true));

        $this->up(self::MEMBERS);

        $this->assertFalse(Schema::hasColumn('project_members', 'quote_id'));
        $this->assertFalse($this->nullable('project_members', 'project_id'));
    }

    public function test_members_resumes_after_a_run_that_died_before_the_column_drop(): void
    {
        $this->projectRow($this->pA, $this->user, $this->orgA);
        Schema::table('project_members', function (Blueprint $t) {
            $t->dropForeign(['quote_id']);
            $t->dropUnique(['quote_id', 'user_id']);
        });

        $this->up(self::MEMBERS);

        $this->assertFalse(Schema::hasColumn('project_members', 'quote_id'));
        $this->assertFalse($this->nullable('project_members', 'project_id'));
    }

    public function test_members_resumes_after_a_run_that_died_before_the_not_null_change(): void
    {
        $this->projectRow($this->pA, $this->user, $this->orgA);
        Schema::table('project_members', function (Blueprint $t) {
            $t->dropForeign(['quote_id']);
            $t->dropUnique(['quote_id', 'user_id']);
            $t->dropColumn('quote_id');
        });
        $this->assertTrue($this->nullable('project_members', 'project_id'));

        $this->up(self::MEMBERS);

        $this->assertFalse($this->nullable('project_members', 'project_id'));
    }

    public function test_members_resumed_run_with_a_stray_null_project_row_fails_instead_of_succeeding(): void
    {
        $this->legacyRow(null, $this->user, $this->orgA, false);
        Schema::table('project_members', function (Blueprint $t) {
            $t->dropForeign(['quote_id']);
            $t->dropUnique(['quote_id', 'user_id']);
            $t->dropColumn('quote_id');
        });

        try {
            $this->up(self::MEMBERS);
            $this->fail('a NULL project_id row must stop the NOT NULL change');
        } catch (\Throwable $e) {
            $this->assertTrue($this->nullable('project_members', 'project_id'), 'still nullable, the database (not the guard) refused');
        }
    }

    public function test_members_down_is_irreversible_and_changes_nothing(): void
    {
        $this->up(self::MEMBERS);
        $before = $this->schemaAndRows();

        try {
            $this->migration(self::MEMBERS)->down();
            $this->fail('down() must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Irreversible', $e->getMessage());
            $this->assertStringContainsString('Restore the backup', $e->getMessage());
        }

        $this->assertEquals($before, $this->schemaAndRows());
    }

    // ---- 000003 plan_crosswalk ----------------------------------------------------

    public function test_crosswalk_refuses_a_null_project_row(): void
    {
        $this->xw($this->pA, 'OK');
        $this->xw(null, 'NULL-ROW', null, $this->quote($this->pA));
        $before = $this->schemaAndRows();

        $this->assertRefuses(self::CROSSWALK, '/Refusing: 1 plan_crosswalk row\(s\) have project_id IS NULL\. Relink or delete them manually\..*SELECT COUNT\(\*\) FROM plan_crosswalk WHERE project_id IS NULL;/');

        $this->assertEquals($before, $this->schemaAndRows(), 'nothing dropped, nothing deleted');
    }

    public function test_crosswalk_refuses_a_row_whose_org_differs_from_its_projects_org(): void
    {
        $this->xw($this->pA, 'OK');
        $this->xw($this->pB, 'MISMATCH', $this->orgA);
        $before = $this->schemaAndRows();

        $this->assertRefuses(self::CROSSWALK, '/Refusing: 1 plan_crosswalk row\(s\) belong to a different organization than their project\..*c\.org_id<>p\.org_id;/');

        $this->assertEquals($before, $this->schemaAndRows());
    }

    public function test_crosswalk_refuses_duplicate_project_and_code_pairs(): void
    {
        $this->xw($this->pA, 'DUP');
        $this->xw($this->pA, 'DUP');
        $this->xw($this->pB, 'DUP', $this->orgB);
        $this->xw($this->pB, 'DUP', $this->orgB);
        $this->xw($this->pB, 'UNIQUE', $this->orgB);
        $before = $this->schemaAndRows();

        $this->assertRefuses(self::CROSSWALK, '/Refusing: 2 duplicate \(project_id, plan_line_code\) pair\(s\) in plan_crosswalk\. Resolve them manually\..*GROUP BY 1,2 HAVING COUNT\(\*\)>1;/');

        $this->assertEquals($before, $this->schemaAndRows());
    }

    public function test_crosswalk_the_same_code_in_different_projects_is_not_a_duplicate(): void
    {
        $this->xw($this->pA, 'SAME');
        $p2 = $this->project($this->orgA);
        $this->xw($p2, 'SAME');
        $this->xw($this->pB, 'SAME', $this->orgB);

        $this->up(self::CROSSWALK);

        $this->assertSame(3, DB::table('plan_crosswalk')->count());
    }

    public function test_crosswalk_succeeds_on_clean_data_and_leaves_the_expected_schema(): void
    {
        $a = $this->xw($this->pA, 'A1', null, $this->quote($this->pA));
        $b = $this->xw($this->pB, 'B1', $this->orgB);

        $this->up(self::CROSSWALK);

        $this->assertFalse(Schema::hasColumn('plan_crosswalk', 'quote_id'));
        $this->assertFalse($this->nullable('plan_crosswalk', 'project_id'));
        $this->assertFalse($this->index('plan_crosswalk', ['org_id', 'quote_id']));
        $this->assertTrue($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));
        $this->assertTrue($this->index('plan_crosswalk', ['org_id', 'project_id']), 'the (org_id, project_id) index stays');
        $this->assertNull($this->fk('plan_crosswalk', ['quote_id']));
        $this->assertNotNull($this->fk('plan_crosswalk', ['project_id']));
        $this->assertNotNull($this->fk('plan_crosswalk', ['org_id']));
        $this->assertSame([$a, $b], DB::table('plan_crosswalk')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all(), 'rows kept');
        $this->assertSame([$this->pA, $this->pB], DB::table('plan_crosswalk')->orderBy('id')->pluck('project_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_crosswalk_rejects_null_project_and_duplicate_pairs_afterwards(): void
    {
        $this->up(self::CROSSWALK);

        try {
            $this->xw(null, 'N');
            $this->fail('NULL project_id must be rejected');
        } catch (\Illuminate\Database\QueryException) {
        }

        DB::table('plan_crosswalk')->insert(['org_id' => $this->orgA->id, 'project_id' => $this->pA, 'plan_line_code' => 'D', 'created_by' => $this->user->id]);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('plan_crosswalk')->insert(['org_id' => $this->orgA->id, 'project_id' => $this->pA, 'plan_line_code' => 'D', 'created_by' => $this->user->id]);
    }

    public function test_crosswalk_model_works_after_the_migration(): void
    {
        $this->up(self::CROSSWALK);

        $row = PlanCrosswalk::create(['org_id' => $this->orgA->id, 'project_id' => $this->pA, 'plan_line_code' => 'M', 'created_by' => $this->user->id]);

        $this->assertSame($this->pA, (int) $row->fresh()->project_id);
        $this->assertFalse(in_array('quote_id', (new PlanCrosswalk)->getFillable(), true));
        $this->assertFalse(method_exists(PlanCrosswalk::class, 'quote'));
    }

    public function test_crosswalk_is_re_runnable_after_a_refusal_once_the_data_is_fixed_and_after_success(): void
    {
        $this->xw($this->pA, 'DUP');
        $dup = $this->xw($this->pA, 'DUP');
        $this->assertRefuses(self::CROSSWALK, '/Refusing/');

        DB::table('plan_crosswalk')->where('id', $dup)->update(['plan_line_code' => 'DUP2']);
        $this->up(self::CROSSWALK);
        $this->up(self::CROSSWALK);

        $this->assertFalse(Schema::hasColumn('plan_crosswalk', 'quote_id'));
        $this->assertTrue($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));
    }

    public function test_crosswalk_resumes_after_a_run_that_died_after_the_fk_drop(): void
    {
        $this->xw($this->pA, 'R1');
        Schema::table('plan_crosswalk', fn (Blueprint $t) => $t->dropForeign(['quote_id']));

        $this->up(self::CROSSWALK);

        $this->assertFalse(Schema::hasColumn('plan_crosswalk', 'quote_id'));
        $this->assertTrue($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));
    }

    public function test_crosswalk_resumes_after_a_run_that_died_after_the_column_drop(): void
    {
        $this->xw($this->pA, 'R1');
        Schema::table('plan_crosswalk', function (Blueprint $t) {
            $t->dropForeign(['quote_id']);
            $t->dropIndex(['org_id', 'quote_id']);
            $t->dropColumn('quote_id');
        });
        $this->assertTrue($this->nullable('plan_crosswalk', 'project_id'));

        $this->up(self::CROSSWALK);

        $this->assertFalse($this->nullable('plan_crosswalk', 'project_id'));
        $this->assertTrue($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));
    }

    public function test_crosswalk_resumes_after_a_run_that_died_before_the_unique(): void
    {
        $this->xw($this->pA, 'R1');
        Schema::table('plan_crosswalk', function (Blueprint $t) {
            $t->dropForeign(['quote_id']);
            $t->dropIndex(['org_id', 'quote_id']);
            $t->dropColumn('quote_id');
        });
        Schema::table('plan_crosswalk', fn (Blueprint $t) => $t->unsignedBigInteger('project_id')->nullable(false)->change());
        $this->assertFalse($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));

        $this->up(self::CROSSWALK);

        $this->assertTrue($this->index('plan_crosswalk', ['project_id', 'plan_line_code'], true));
        $this->assertFalse($this->nullable('plan_crosswalk', 'project_id'));
    }

    public function test_crosswalk_a_resumed_run_still_refuses_duplicates_created_in_the_meantime(): void
    {
        $this->xw($this->pA, 'R1');
        Schema::table('plan_crosswalk', function (Blueprint $t) {
            $t->dropForeign(['quote_id']);
            $t->dropIndex(['org_id', 'quote_id']);
            $t->dropColumn('quote_id');
        });
        DB::table('plan_crosswalk')->insert(['org_id' => $this->orgA->id, 'project_id' => $this->pA, 'plan_line_code' => 'R1', 'created_by' => $this->user->id]);

        $this->assertRefuses(self::CROSSWALK, '/Refusing: 1 duplicate/');
    }

    public function test_crosswalk_down_is_irreversible_and_changes_nothing(): void
    {
        $this->up(self::CROSSWALK);
        $before = $this->schemaAndRows();

        try {
            $this->migration(self::CROSSWALK)->down();
            $this->fail('down() must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Irreversible', $e->getMessage());
            $this->assertStringContainsString('Restore the backup', $e->getMessage());
        }

        $this->assertEquals($before, $this->schemaAndRows());
    }

    // ---- the three in order --------------------------------------------------------

    public function test_all_three_in_order_on_realistic_clean_data(): void
    {
        $q = $this->quote($this->pA);
        $this->projectRow($this->pA, $this->user, $this->orgA);
        $this->legacyRow($q, $this->user, $this->orgA);
        $this->xw($this->pA, 'C1', null, $q);

        foreach ([self::QUOTES, self::MEMBERS, self::CROSSWALK] as $file) {
            $this->up($file);
        }

        $this->assertSame(1, DB::table('quotes')->count());
        $this->assertSame(1, DB::table('project_members')->count());
        $this->assertSame(1, DB::table('plan_crosswalk')->count());
        $this->assertSame(0, DB::table('rbac_audit_logs')->count());
    }

    public function test_a_failed_guard_in_a_later_migration_leaves_the_earlier_ones_applied_and_consistent(): void
    {
        $this->xw(null, 'BAD');

        $this->up(self::QUOTES);
        $this->up(self::MEMBERS);
        $this->assertRefuses(self::CROSSWALK, '/Refusing: 1 plan_crosswalk/');

        $this->assertFalse($this->nullable('quotes', 'project_id'));
        $this->assertFalse(Schema::hasColumn('project_members', 'quote_id'));
        $this->assertTrue(Schema::hasColumn('plan_crosswalk', 'quote_id'));
        $this->assertSame(1, DB::table('plan_crosswalk')->count());
    }
}

<?php

namespace Tests\Feature\Rbac;

use App\Models\PlanCrosswalk;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 schema, rollback guards and model relations for the Projects entity.
 * Runs on in-memory sqlite: it proves the migration logic, not MariaDB DDL behaviour.
 */
class ProjectSchemaTest extends RbacTestCase
{
    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org  = Organization::create(['name' => 'Org', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $this->user = User::factory()->create();
    }

    private function column(string $table, string $name): array
    {
        foreach (Schema::getColumns($table) as $c) {
            if ($c['name'] === $name) {
                return $c;
            }
        }
        $this->fail("Column {$table}.{$name} does not exist");
    }

    private function hasIndex(string $table, array $columns, ?bool $unique = null): bool
    {
        foreach (Schema::getIndexes($table) as $i) {
            if ($i['columns'] === $columns && ($unique === null || $i['unique'] === $unique)) {
                return true;
            }
        }
        return false;
    }

    private function migration(string $file): object
    {
        return require database_path('migrations/' . $file);
    }

    private function makeProject(array $attrs = []): Project
    {
        return Project::create($attrs + ['org_id' => $this->org->id, 'name' => 'P', 'created_by' => $this->user->id]);
    }

    private function makeQuote(): Quote
    {
        $id = DB::table('quotes')->insertGetId([
            'user_id' => $this->user->id, 'quote_number' => 'Q-' . uniqid(), 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return Quote::findOrFail($id);
    }

    // ---- schema -----------------------------------------------------------------

    public function test_projects_table_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('projects'));
        foreach (['org_id', 'name'] as $c) {
            $this->assertFalse($this->column('projects', $c)['nullable'], "projects.$c NOT NULL");
        }
        foreach (['address', 'bid_due_at', 'created_by', 'deleted_at', 'created_at', 'updated_at'] as $c) {
            $this->assertTrue($this->column('projects', $c)['nullable'], "projects.$c nullable");
        }
        $this->assertFalse($this->column('projects', 'status')['nullable']);
        $this->assertStringContainsString('active', (string) $this->column('projects', 'status')['default']);
        $this->assertTrue($this->hasIndex('projects', ['org_id', 'status']));
        $this->assertTrue($this->hasIndex('projects', ['created_by']));
    }

    public function test_quotes_project_id_is_nullable_fk(): void
    {
        $this->assertTrue($this->column('quotes', 'project_id')['nullable']);
        $fk = collect(Schema::getForeignKeys('quotes'))->firstWhere('columns', ['project_id']);
        $this->assertNotNull($fk);
        $this->assertSame('projects', $fk['foreign_table']);
    }

    public function test_project_members_schema(): void
    {
        $this->assertTrue($this->column('project_members', 'project_id')['nullable']);
        $this->assertTrue($this->column('project_members', 'quote_id')['nullable']);
        $this->assertTrue($this->hasIndex('project_members', ['project_id', 'user_id'], true));
        $this->assertTrue($this->hasIndex('project_members', ['quote_id', 'user_id'], true), 'legacy unique kept');
    }

    public function test_project_members_unique_project_user_enforced(): void
    {
        $p = $this->makeProject();
        $row = ['project_id' => $p->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id, 'is_active' => true];
        DB::table('project_members')->insert($row);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('project_members')->insert($row);
    }

    public function test_project_members_allows_multiple_null_project_legacy_rows(): void
    {
        $q1 = $this->makeQuote();
        $q2 = $this->makeQuote();
        foreach ([$q1, $q2] as $q) {
            DB::table('project_members')->insert(['quote_id' => $q->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id]);
        }
        $this->assertSame(2, DB::table('project_members')->whereNull('project_id')->count());
    }

    public function test_plan_crosswalk_schema(): void
    {
        $this->assertTrue($this->column('plan_crosswalk', 'project_id')['nullable']);
        $this->assertTrue($this->column('plan_crosswalk', 'quote_id')['nullable']);
        $this->assertTrue($this->hasIndex('plan_crosswalk', ['org_id', 'project_id']));
        $this->assertTrue($this->hasIndex('plan_crosswalk', ['org_id', 'quote_id']), 'legacy index kept');
    }

    public function test_rbac_audit_logs_quote_id(): void
    {
        $this->assertTrue($this->column('rbac_audit_logs', 'quote_id')['nullable']);
        $this->assertTrue(Schema::hasColumn('rbac_audit_logs', 'project_id'), 'project_id kept');
        $log = AuditLog::create(['user_id' => $this->user->id, 'org_id' => $this->org->id, 'quote_id' => 7, 'method' => 'GET', 'route_uri' => 'x', 'batch' => 1, 'outcome' => 'allow']);
        $this->assertSame(7, (int) $log->fresh()->quote_id);
    }

    // ---- rollback ---------------------------------------------------------------

    public function test_rollback_000003_aborts_when_null_quote_id_row_exists(): void
    {
        $p = $this->makeProject();
        DB::table('project_members')->insert(['project_id' => $p->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id]);

        try {
            $this->migration('2026_09_24_000003_add_project_id_to_project_members_table.php')->down();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('quote_id IS NULL', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('project_members', 'project_id'), 'schema untouched after abort');
        $this->assertSame(1, DB::table('project_members')->count());
    }

    public function test_rollback_000004_aborts_when_null_quote_id_row_exists(): void
    {
        $p = $this->makeProject();
        DB::table('plan_crosswalk')->insert([
            'org_id' => $this->org->id, 'project_id' => $p->id, 'plan_line_code' => 'A1', 'created_by' => $this->user->id,
        ]);

        try {
            $this->migration('2026_09_24_000004_add_project_id_to_plan_crosswalk_table.php')->down();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('quote_id IS NULL', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('plan_crosswalk', 'project_id'));
        $this->assertSame(1, DB::table('plan_crosswalk')->count());
    }

    public function test_rollback_000003_clean_when_only_legacy_rows(): void
    {
        $q = $this->makeQuote();
        DB::table('project_members')->insert(['quote_id' => $q->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id]);

        $this->migration('2026_09_24_000003_add_project_id_to_project_members_table.php')->down();

        $this->assertFalse(Schema::hasColumn('project_members', 'project_id'));
        $this->assertFalse($this->column('project_members', 'quote_id')['nullable']);
        $this->assertSame(1, DB::table('project_members')->count());
    }

    public function test_rollback_000004_clean_when_only_legacy_rows(): void
    {
        $q = $this->makeQuote();
        DB::table('plan_crosswalk')->insert([
            'org_id' => $this->org->id, 'quote_id' => $q->id, 'plan_line_code' => 'A1', 'created_by' => $this->user->id,
        ]);

        $this->migration('2026_09_24_000004_add_project_id_to_plan_crosswalk_table.php')->down();

        $this->assertFalse(Schema::hasColumn('plan_crosswalk', 'project_id'));
        $this->assertFalse($this->column('plan_crosswalk', 'quote_id')['nullable']);
        $this->assertSame(1, DB::table('plan_crosswalk')->count());
    }

    public function test_full_down_in_reverse_order_on_empty_tables(): void
    {
        foreach (['05_add_quote_id_to_rbac_audit_logs', '04_add_project_id_to_plan_crosswalk', '03_add_project_id_to_project_members', '02_add_project_id_to_quotes', '01_create_projects'] as $m) {
            $n = substr($m, 0, 2);
            $this->migration("2026_09_24_0000{$n}_" . substr($m, 3) . '_table.php')->down();
        }
        $this->assertFalse(Schema::hasTable('projects'));
        $this->assertFalse(Schema::hasColumn('quotes', 'project_id'));
        $this->assertFalse(Schema::hasColumn('rbac_audit_logs', 'quote_id'));
    }

    // ---- enrol() ----------------------------------------------------------------

    public function test_enrol_inserts_new_member(): void
    {
        $p = $this->makeProject();
        $granter = User::factory()->create();

        $m = ProjectMember::enrol($p, $this->user->id, $this->org->id, $granter->id);

        $this->assertTrue($m->wasRecentlyCreated);
        $this->assertNull($m->fresh()->quote_id);
        $row = DB::table('project_members')->first();
        $this->assertSame($p->id, (int) $row->project_id);
        $this->assertSame($this->org->id, (int) $row->org_id);
        $this->assertSame($granter->id, (int) $row->granted_by);
        $this->assertNotNull($row->granted_at);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertNull($row->quote_id);
    }

    private function otherOrg(): Organization
    {
        return Organization::create(['name' => 'Other', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
    }

    private function memberRows(): array
    {
        return DB::table('project_members')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function test_enrol_active_member_is_unchanged(): void
    {
        $p  = $this->makeProject();
        $g1 = User::factory()->create();
        $g2 = User::factory()->create();

        $first = ProjectMember::enrol($p, $this->user->id, $this->org->id, $g1->id);
        DB::table('project_members')->where('id', $first->id)->update([
            'granted_at' => '2020-01-01 00:00:00', 'updated_at' => '2020-01-01 00:00:00',
        ]);
        $before = $this->memberRows();

        $again = ProjectMember::enrol($p, $this->user->id, $this->org->id, $g2->id);

        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertFalse($again->wasChanged());
        $this->assertSame($before, $this->memberRows());
        $this->assertSame($g1->id, (int) $this->memberRows()[0]['granted_by']);
        $this->assertSame($this->org->id, (int) $this->memberRows()[0]['org_id']);
        $this->assertCount(1, $this->memberRows());
    }

    public function test_enrol_mismatched_org_on_existing_row_throws_and_writes_nothing(): void
    {
        $p = $this->makeProject();
        ProjectMember::enrol($p, $this->user->id, $this->org->id, null);
        $before = $this->memberRows();

        try {
            ProjectMember::enrol($p, $this->user->id, $this->otherOrg()->id, null);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->memberRows());
        $this->assertCount(1, $this->memberRows());
    }

    public function test_enrol_mismatched_org_on_new_member_throws_and_writes_nothing(): void
    {
        $p = $this->makeProject();

        try {
            ProjectMember::enrol($p, $this->user->id, $this->otherOrg()->id, null);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, DB::table('project_members')->count());
    }

    public function test_enrol_inactive_member_is_reactivated_with_new_grant(): void
    {
        $p  = $this->makeProject();
        $g1 = User::factory()->create();
        $g2 = User::factory()->create();

        $first = ProjectMember::enrol($p, $this->user->id, $this->org->id, $g1->id);
        DB::table('project_members')->where('id', $first->id)->update(['is_active' => false, 'granted_at' => '2020-01-01 00:00:00']);

        $m = ProjectMember::enrol($p, $this->user->id, $this->org->id, $g2->id);

        $this->assertFalse($m->wasRecentlyCreated);
        $this->assertTrue($m->wasChanged('is_active'));
        $row = DB::table('project_members')->first();
        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame($this->org->id, (int) $row->org_id);
        $this->assertSame($g2->id, (int) $row->granted_by);
        $this->assertGreaterThan('2020-01-01 00:00:00', $row->granted_at);
        $this->assertSame($first->id, (int) $row->id);
        $this->assertSame(1, DB::table('project_members')->count());
    }

    public function test_enrol_mismatched_org_on_inactive_row_throws_and_stays_inactive(): void
    {
        $p = $this->makeProject();
        $first = ProjectMember::enrol($p, $this->user->id, $this->org->id, null);
        DB::table('project_members')->where('id', $first->id)->update(['is_active' => false, 'granted_at' => '2020-01-01 00:00:00']);
        $before = $this->memberRows();

        try {
            ProjectMember::enrol($p, $this->user->id, $this->otherOrg()->id, null);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, $this->memberRows());
        $this->assertSame(0, (int) $this->memberRows()[0]['is_active']);
    }

    public function test_enrol_race_lost_active_row_is_returned_unchanged(): void
    {
        $p = $this->makeProject();
        $racer = User::factory()->create();
        $fired = false;

        ProjectMember::creating(function () use (&$fired, $p, $racer) {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('project_members')->insert([
                'project_id' => $p->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id,
                'granted_by' => $racer->id, 'granted_at' => now(), 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $m = ProjectMember::enrol($p, $this->user->id, $this->org->id, null);

        $this->assertTrue($fired, 'race listener ran');
        $this->assertFalse($m->wasRecentlyCreated);
        $this->assertFalse($m->wasChanged());
        $this->assertTrue($m->is_active);
        $this->assertSame($racer->id, (int) $m->granted_by, 'returns the racing row, not ours');
        $this->assertSame($racer->id, (int) DB::table('project_members')->value('granted_by'), 'racing row not overwritten');
        $this->assertSame(1, DB::table('project_members')->where('project_id', $p->id)->where('user_id', $this->user->id)->count());
    }

    public function test_enrol_race_lost_inactive_row_is_reactivated(): void
    {
        $p = $this->makeProject();
        $racer = User::factory()->create();
        $g2 = User::factory()->create();
        $fired = false;

        ProjectMember::creating(function () use (&$fired, $p, $racer) {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('project_members')->insert([
                'project_id' => $p->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id,
                'granted_by' => $racer->id, 'granted_at' => '2020-01-01 00:00:00', 'is_active' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $m = ProjectMember::enrol($p, $this->user->id, $this->org->id, $g2->id);

        $this->assertTrue($fired);
        $this->assertFalse($m->wasRecentlyCreated);
        $this->assertTrue($m->wasChanged('is_active'));
        $row = DB::table('project_members')->first();
        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame($g2->id, (int) $row->granted_by);
        $this->assertGreaterThan('2020-01-01 00:00:00', $row->granted_at);
        $this->assertSame($this->org->id, (int) $row->org_id);
        $this->assertSame(1, DB::table('project_members')->count());
    }

    // ---- A6 ---------------------------------------------------------------------

    public function test_enrol_unsaved_project_throws_and_leaves_legacy_row_untouched(): void
    {
        $q = $this->makeQuote();
        DB::table('project_members')->insert([
            'quote_id' => $q->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id,
            'is_active' => false, 'granted_at' => '2020-01-01 00:00:00',
            'created_at' => '2020-01-01 00:00:00', 'updated_at' => '2020-01-01 00:00:00',
        ]);
        $before = DB::table('project_members')->get()->map(fn ($r) => (array) $r)->all();

        try {
            ProjectMember::enrol(new Project(), $this->user->id, $this->org->id, null);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($before, DB::table('project_members')->get()->map(fn ($r) => (array) $r)->all());
    }

    // ---- scopeVisibleTo ---------------------------------------------------------

    public function test_scope_visible_to(): void
    {
        $otherOrg  = $this->otherOrg();
        $otherUser = User::factory()->create();

        $visible   = $this->makeProject(['name' => 'visible']);
        $inactive  = $this->makeProject(['name' => 'inactive']);
        $others    = $this->makeProject(['name' => 'other-user']);
        $mismatch  = $this->makeProject(['name' => 'mismatched-member-org']);
        $deleted   = $this->makeProject(['name' => 'deleted']);
        $legacy    = $this->makeProject(['name' => 'legacy-only']);
        $control   = $this->makeProject(['name' => 'other-org-project', 'org_id' => $otherOrg->id]);

        ProjectMember::enrol($visible, $this->user->id, $this->org->id, null);
        ProjectMember::enrol($inactive, $this->user->id, $this->org->id, null)->update(['is_active' => false]);
        ProjectMember::enrol($others, $otherUser->id, $this->org->id, null);
        ProjectMember::enrol($deleted, $this->user->id, $this->org->id, null);
        $deleted->delete();
        ProjectMember::enrol($control, $this->user->id, $otherOrg->id, null);

        DB::table('project_members')->insert([
            'project_id' => $mismatch->id, 'user_id' => $this->user->id, 'org_id' => $otherOrg->id, 'is_active' => true,
        ]);

        $q = $this->makeQuote();
        $q->update(['project_id' => $legacy->id]);
        DB::table('project_members')->insert([
            'quote_id' => $q->id, 'user_id' => $this->user->id, 'org_id' => $this->org->id, 'is_active' => true,
        ]);

        $this->assertSame(['visible'], Project::visibleTo($this->user->id, $this->org->id)->pluck('name')->all());
        $this->assertSame(['other-org-project'], Project::visibleTo($this->user->id, $otherOrg->id)->pluck('name')->all());
        $this->assertSame(['other-user'], Project::visibleTo($otherUser->id, $this->org->id)->pluck('name')->all());
        $this->assertSame([], Project::visibleTo($otherUser->id, $otherOrg->id)->pluck('name')->all());
    }

    // ---- FK behaviour (sqlite with FK enforcement; real InnoDB is B1 step 6) ----

    private function assertFkEnforced(): void
    {
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
    }

    public function test_force_deleting_project_referenced_by_quote_fails(): void
    {
        $this->assertFkEnforced();
        $p = $this->makeProject();
        $this->makeQuote()->update(['project_id' => $p->id]);

        try {
            $p->forceDelete();
            $this->fail('Expected QueryException');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertTrue(Project::withTrashed()->whereKey($p->id)->exists());
    }

    public function test_force_deleting_project_cascades_members_and_crosswalk(): void
    {
        $this->assertFkEnforced();
        $p = $this->makeProject();
        ProjectMember::enrol($p, $this->user->id, $this->org->id, null);
        PlanCrosswalk::create(['org_id' => $this->org->id, 'project_id' => $p->id, 'plan_line_code' => 'X', 'created_by' => $this->user->id]);
        $this->assertSame(1, DB::table('project_members')->count());
        $this->assertSame(1, DB::table('plan_crosswalk')->count());

        $p->forceDelete();

        $this->assertSame(0, DB::table('project_members')->count());
        $this->assertSame(0, DB::table('plan_crosswalk')->count());
    }

    public function test_deleting_org_that_owns_a_project_fails(): void
    {
        $this->assertFkEnforced();
        $p = $this->makeProject();

        try {
            $this->org->delete();
            $this->fail('Expected QueryException');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertTrue(Organization::whereKey($this->org->id)->exists());
        $this->assertTrue(Project::whereKey($p->id)->exists());
    }

    public function test_deleting_creator_nulls_projects_created_by(): void
    {
        $this->assertFkEnforced();
        $p = $this->makeProject();
        $this->assertSame($this->user->id, (int) $p->created_by);

        $this->user->delete();

        $this->assertNull(DB::table('projects')->where('id', $p->id)->value('created_by'));
    }

    // ---- 000005 rollback guard --------------------------------------------------

    public function test_rollback_000005_aborts_when_quote_id_set(): void
    {
        AuditLog::create(['user_id' => $this->user->id, 'org_id' => $this->org->id, 'quote_id' => 7, 'method' => 'GET', 'route_uri' => 'x', 'batch' => 1, 'outcome' => 'allow']);

        try {
            $this->migration('2026_09_24_000005_add_quote_id_to_rbac_audit_logs_table.php')->down();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('quote_id set', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('rbac_audit_logs', 'quote_id'));
        $this->assertSame(1, DB::table('rbac_audit_logs')->count());
    }

    public function test_rollback_000005_clean_when_all_null(): void
    {
        AuditLog::create(['user_id' => $this->user->id, 'org_id' => $this->org->id, 'method' => 'GET', 'route_uri' => 'x', 'batch' => 1, 'outcome' => 'allow']);

        $this->migration('2026_09_24_000005_add_quote_id_to_rbac_audit_logs_table.php')->down();

        $this->assertFalse(Schema::hasColumn('rbac_audit_logs', 'quote_id'));
        $this->assertSame(1, DB::table('rbac_audit_logs')->count());
    }

    // ---- relations --------------------------------------------------------------

    public function test_model_relations_resolve(): void
    {
        $p = $this->makeProject();
        $q = $this->makeQuote();
        $q->update(['project_id' => $p->id]);
        $m = ProjectMember::enrol($p, $this->user->id, $this->org->id, null);
        $cw = PlanCrosswalk::create([
            'org_id' => $this->org->id, 'project_id' => $p->id, 'plan_line_code' => 'X', 'created_by' => $this->user->id,
        ]);

        $this->assertTrue($q->fresh()->project->is($p));
        $this->assertTrue($m->project->is($p));
        $this->assertTrue($cw->project->is($p));
        $this->assertTrue($p->quotes->first()->is($q));
        $this->assertTrue($p->members->first()->is($m));
        $this->assertTrue($p->crosswalk->first()->is($cw));
        $this->assertTrue($p->organization->is($this->org));
        $this->assertTrue($p->creator->is($this->user));
    }
}

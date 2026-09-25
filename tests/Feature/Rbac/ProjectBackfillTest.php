<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\ProjectMember;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 2: php artisan projects:backfill (PLAN.md C12-C15, tests 1-59).
 * Runs on the in-memory sqlite forced by RbacTestCase, with report files under a temp storage path.
 * sqlite cannot prove FOR UPDATE locking, real savepoint/undo behaviour, memory, timezone or collation
 * behaviour; those belong to the B1 dry-run on a restored production copy.
 * The class must not use DatabaseTransactions: dry-run owns its outer transaction.
 */
class ProjectBackfillTest extends RbacTestCase
{
    private string $tmp;
    private object $maint;
    private string $out = '';
    private int $roleSeq = 0;
    private int $qSeq = 0;
    private int $uSeq = 0;
    private int $fSeq = 0;
    private int $exit = -1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        Schema::table('quotes', fn ($t) => $t->text('project_address')->nullable());

        $this->tmp = sys_get_temp_dir().'/pbf_'.uniqid('', true);
        mkdir($this->tmp, 0700, true);
        $this->app->useStoragePath($this->tmp);
        config(['logging.default' => 'null']);

        $this->maint = new class implements MaintenanceMode
        {
            public bool $down = true;

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
        };
        $this->app->instance(MaintenanceMode::class, $this->maint);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir.'/'.$f;
            is_dir($p) && ! is_link($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }

    // ---- builders ---------------------------------------------------------------

    private function user(): int
    {
        $n = ++$this->uSeq;

        return (int) DB::table('users')->insertGetId([
            'name' => "U$n", 'email' => "u$n@example.test", 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function org(): int
    {
        return Organization::create(['name' => 'Org'.uniqid(), 'org_type' => 'subcontractor', 'team_size' => 'solo'])->id;
    }

    private function role(int $user, int $org, bool $active = true): void
    {
        $ids = DB::table('roles')->orderBy('id')->pluck('id')->all();
        DB::table('user_org_roles')->insert([
            'user_id' => $user, 'org_id' => $org, 'role_id' => $ids[$this->roleSeq++ % count($ids)],
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function owner(int ...$orgs): int
    {
        $u = $this->user();
        foreach ($orgs as $o) {
            $this->role($u, $o);
        }

        return $u;
    }

    private function q(int $owner, array $a = []): int
    {
        return (int) DB::table('quotes')->insertGetId($a + [
            'user_id' => $owner, 'quote_number' => 'Q-'.++$this->qSeq, 'status' => 'draft',
            'name' => null, 'project_name' => null, 'project_address' => null,
            'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00', 'deleted_at' => null,
        ]);
    }

    private function legacy(int $quote, int $user, int $org, bool $active = true, ?int $by = null, ?string $at = '2025-02-01 00:00:00'): int
    {
        return (int) DB::table('project_members')->insertGetId([
            'quote_id' => $quote, 'user_id' => $user, 'org_id' => $org, 'granted_by' => $by, 'granted_at' => $at,
            'is_active' => $active, 'created_at' => '2025-02-01 00:00:00', 'updated_at' => '2025-02-01 00:00:00',
        ]);
    }

    private function cw(int $quote, int $org, string $code, int $creator): int
    {
        return (int) DB::table('plan_crosswalk')->insertGetId([
            'org_id' => $org, 'quote_id' => $quote, 'plan_line_code' => $code, 'created_by' => $creator,
            'created_at' => '2025-03-03 00:00:00', 'updated_at' => '2025-03-03 00:00:00',
        ]);
    }

    private function project(int $org, int $createdBy, string $name, ?string $deletedAt = null): int
    {
        return (int) DB::table('projects')->insertGetId([
            'org_id' => $org, 'name' => $name, 'status' => 'active', 'created_by' => $createdBy,
            'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00', 'deleted_at' => $deletedAt,
        ]);
    }

    private function attach(int $quote, int $project): void
    {
        DB::table('quotes')->where('id', $quote)->update(['project_id' => $project]);
    }

    private function csvFile(string $content): string
    {
        $p = $this->tmp.'/ov'.++$this->fSeq.'.csv';
        file_put_contents($p, $content);

        return $p;
    }

    // ---- running and reading ----------------------------------------------------

    private function legacyRows(): array
    {
        return DB::table('project_members')->whereNotNull('quote_id')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    private function backfill(array $opts = [], ?string $overrides = null): int
    {
        $legacyBefore = $this->legacyRows();
        if ($overrides !== null) {
            $opts['--overrides'] = $overrides;
        }
        $this->exit = $this->invoke($opts);

        $this->assertSame($legacyBefore, $this->legacyRows(), 'legacy member rows must be byte-identical');
        $mismatch = DB::selectOne('SELECT COUNT(*) c FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.org_id <> p.org_id');
        $this->assertSame(0, (int) $mismatch->c, 'invariant: project_members.org_id = projects.org_id');

        return $this->exit;
    }

    private function invoke(array $opts): int
    {
        // Command state (overrides, abort flag) lives on the instance; a real invocation is a fresh process.
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
        $exit = Artisan::call('projects:backfill', $opts);
        $this->out = Artisan::output();

        return $exit;
    }

    private function table(string $name): array
    {
        return DB::table($name)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    private function stat(string $label): string
    {
        $this->assertSame(1, preg_match('/^'.preg_quote($label, '/').': (.*)$/m', $this->out, $m), "summary line '$label' present");

        return $m[1];
    }

    private function reportDir(): string
    {
        $this->assertSame(1, preg_match('/^report directory: (.+)$/m', $this->out, $m), 'report directory printed');

        return $m[1];
    }

    private function csv(string $name, ?string $dir = null): array
    {
        $h = fopen(($dir ?? $this->reportDir()).'/'.$name.'.csv', 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        $rows = [];
        while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $rows[] = array_combine($header, $r);
        }
        fclose($h);

        return $rows;
    }

    private function assertNoReports(): void
    {
        $this->assertDirectoryDoesNotExist($this->tmp.'/app/backfill');
    }

    private function counts(): array
    {
        return [
            DB::table('projects')->count(),
            DB::table('project_members')->count(),
            DB::table('quotes')->whereNotNull('project_id')->count(),
            DB::table('plan_crosswalk')->whereNotNull('project_id')->count(),
        ];
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame([0, 0, 0, 0], $this->counts());
    }

    private function projectOf(int $quote): ?int
    {
        $v = DB::table('quotes')->where('id', $quote)->value('project_id');

        return $v === null ? null : (int) $v;
    }

    private function groupFor(int $quote): array
    {
        foreach ($this->csv('groups') as $g) {
            if (in_array((string) $quote, explode(' ', $g['quote_ids']), true)) {
                return $g;
            }
        }
        $this->fail("quote $quote not in groups.csv");
    }

    private function unresolvedReasons(): array
    {
        $r = [];
        foreach ($this->csv('unresolved') as $u) {
            $r[(int) $u['quote_id']] = $u['reason'];
        }
        ksort($r);

        return $r;
    }

    // ---- 1-3 grouping -----------------------------------------------------------

    public function test_01_case_and_space_variants_form_one_project(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'Main St', 'created_at' => '2025-01-05 00:00:00', 'updated_at' => '2025-01-05 00:00:00']);
        $q2 = $this->q($a, ['project_name' => ' main   st ', 'created_at' => '2025-01-03 00:00:00', 'updated_at' => '2025-03-01 00:00:00']);
        $q3 = $this->q($a, ['project_name' => 'MAIN ST', 'created_at' => '2025-01-04 00:00:00', 'updated_at' => '2025-02-01 00:00:00']);

        $this->assertSame(0, $this->backfill());

        $this->assertSame(1, DB::table('projects')->count());
        $p = DB::table('projects')->first();
        $this->assertSame('main   st', $p->name);
        $this->assertSame('2025-01-03 00:00:00', $p->created_at);
        $this->assertSame($p->id, $this->projectOf($q1));
        $this->assertSame($p->id, $this->projectOf($q2));
        $this->assertSame($p->id, $this->projectOf($q3));
        $g = $this->csv('groups');
        $this->assertCount(1, $g);
        $this->assertSame('named:main st', $g[0]['group_key']);
        $this->assertSame('created', $g[0]['action']);
        $this->assertSame('3', $g[0]['quote_count']);
    }

    public function test_backfill_writes_no_project_member_logs(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $b = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'Main St']);
        $this->legacy($q1, $b, $org);

        $this->assertSame(0, $this->backfill());

        $this->assertGreaterThan(0, DB::table('project_members')->whereNotNull('project_id')->count(), 'the backfill did create project-keyed members');
        $this->assertSame(0, DB::table('project_member_logs')->count());
    }

    public function test_02_two_owners_same_name_same_org_get_two_projects(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $b = $this->owner($org);
        $qa = $this->q($a, ['project_name' => 'Main St']);
        $qb = $this->q($b, ['project_name' => 'Main St']);

        $this->assertSame(0, $this->backfill());

        $this->assertSame(2, DB::table('projects')->count());
        $this->assertNotSame($this->projectOf($qa), $this->projectOf($qb));
        $this->assertSame($a, (int) DB::table('projects')->where('id', $this->projectOf($qa))->value('created_by'));
        $this->assertSame($b, (int) DB::table('projects')->where('id', $this->projectOf($qb))->value('created_by'));
    }

    public function test_03_unicode_normalization(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $cafe1 = $this->q($a, ['project_name' => 'Café']);
        $cafe2 = $this->q($a, ['project_name' => 'Cafe']);
        $ete1 = $this->q($a, ['project_name' => 'ÉTÉ']);
        $ete2 = $this->q($a, ['project_name' => 'été']);
        $sp1 = $this->q($a, ['project_name' => "Main\u{00A0}St"]);
        $sp2 = $this->q($a, ['project_name' => "Main\tSt"]);
        $sp3 = $this->q($a, ['project_name' => 'Main St']);
        $bad = $this->q($a, ['project_name' => "\xC3\x28", 'name' => 'Broken']);

        $this->assertSame(0, $this->backfill());

        $this->assertNotSame($this->projectOf($cafe1), $this->projectOf($cafe2));
        $this->assertSame($this->projectOf($ete1), $this->projectOf($ete2));
        $this->assertSame($this->projectOf($sp1), $this->projectOf($sp2));
        $this->assertSame($this->projectOf($sp1), $this->projectOf($sp3));
        $this->assertSame('singleton:'.$bad, $this->groupFor($bad)['group_key']);
        $w = $this->csv('warnings');
        $this->assertCount(1, $w);
        $this->assertSame(['source' => 'normalize', 'line_or_quote_id' => (string) $bad, 'message' => 'project_name_invalid_utf8'], $w[0]);
        $this->assertSame('1', $this->stat('warnings'));
    }

    // ---- 4-5 unnamed ------------------------------------------------------------

    public function test_04_unnamed_quotes_are_singletons_with_fallback_names(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => null, 'name' => 'Kitchen']);
        $q2 = $this->q($a, ['project_name' => '', 'name' => '']);
        $q3 = $this->q($a, ['project_name' => 'Estimate', 'name' => 'Estimate']);
        $q4 = $this->q($a, ['project_name' => ' estimate ', 'name' => null, 'quote_number' => 'QN-4']);

        $this->assertSame(0, $this->backfill());

        $this->assertSame(4, DB::table('projects')->count());
        $names = DB::table('projects')->pluck('name', 'id');
        $this->assertSame('Kitchen', $names[$this->projectOf($q1)]);
        $this->assertSame('Untitled project ('.DB::table('quotes')->where('id', $q2)->value('quote_number').')', $names[$this->projectOf($q2)]);
        $this->assertSame('Untitled project ('.DB::table('quotes')->where('id', $q3)->value('quote_number').')', $names[$this->projectOf($q3)]);
        $this->assertSame('Untitled project (QN-4)', $names[$this->projectOf($q4)]);
        foreach ([$q1, $q2, $q3, $q4] as $q) {
            $g = $this->groupFor($q);
            $this->assertSame('singleton:'.$q, $g['group_key']);
            $this->assertSame('singleton', $g['key_source']);
        }
    }

    public function test_05_h1_equal_name_is_singleton_and_listed_as_candidate_merge(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'Base bid', 'name' => 'Base bid']);
        $q2 = $this->q($a, ['project_name' => 'BASE BID', 'name' => 'base  bid']);

        $this->assertSame(0, $this->backfill());

        $this->assertNotSame($this->projectOf($q1), $this->projectOf($q2));
        $this->assertSame(2, DB::table('projects')->count());
        $c = $this->csv('candidate_merges');
        $this->assertCount(1, $c);
        $this->assertSame(['org_id' => (string) $org, 'owner_user_id' => (string) $a, 'normalized_name' => 'base bid', 'quote_ids' => "$q1 $q2"], $c[0]);
        $this->assertSame('1', $this->stat('candidate merges'));
    }

    // ---- 6-8 org resolution -----------------------------------------------------

    public function test_06_two_role_rows_in_one_org_count_once(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $this->role($a, $org);
        $q = $this->q($a, ['project_name' => 'Solo']);

        $this->assertSame(0, $this->backfill());

        $this->assertSame($org, (int) DB::table('projects')->value('org_id'));
        $this->assertSame('single_org', $this->groupFor($q)['org_resolution']);
    }

    public function test_07_multi_org_consensus_or_unresolved(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $a = $this->owner($o7, $o9);
        $x = $this->user();
        $y = $this->user();
        $agree = $this->q($a, ['project_name' => 'Agree']);
        $this->legacy($agree, $x, $o9);
        $none = $this->q($a, ['project_name' => 'None']);
        $split = $this->q($a, ['project_name' => 'Split']);
        $this->legacy($split, $x, $o7);
        $this->legacy($split, $y, $o9);

        $this->assertSame(1, $this->backfill());

        $this->assertSame($o9, (int) DB::table('projects')->where('id', $this->projectOf($agree))->value('org_id'));
        $this->assertSame('membership', $this->groupFor($agree)['org_resolution']);
        $this->assertNull($this->projectOf($none));
        $this->assertNull($this->projectOf($split));
        $this->assertSame([$none => 'multi_org_no_consensus', $split => 'multi_org_no_consensus'], $this->unresolvedReasons());
        $u = collect($this->csv('unresolved'))->firstWhere('quote_id', (string) $split);
        $this->assertSame("$o7 $o9", $u['legacy_member_org_ids']);
        $this->assertSame("$o7 $o9", $u['owner_active_org_ids']);
    }

    public function test_08_zero_active_org_and_override_to_inactive_row(): void
    {
        $org = $this->org();
        $a = $this->user();
        $this->role($a, $org, false);
        $q = $this->q($a, ['project_name' => 'Lonely']);

        $this->assertSame(1, $this->backfill());
        $this->assertSame([$q => 'no_active_org'], $this->unresolvedReasons());
        $this->assertNull($this->projectOf($q));

        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$q,$org,,\n");
        $this->assertSame(0, $this->backfill([], $file));
        $this->assertSame($org, (int) DB::table('projects')->value('org_id'));
        $this->assertSame('override', $this->groupFor($q)['org_resolution']);
        $m = $this->csv('memberships');
        $this->assertSame('no', $m[0]['member_active_in_org']);
    }

    public function test_08b_override_to_org_without_any_role_exits_2_and_writes_nothing(): void
    {
        $org = $this->org();
        $other = $this->org();
        $a = $this->user();
        $this->role($a, $org, false);
        $q = $this->q($a, ['project_name' => 'Lonely']);

        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$q,$other,,\n");
        $this->assertSame(2, $this->backfill([], $file));

        $this->assertStringContainsString('line 2: org_without_role', $this->out);
        $this->assertNothingWritten();
        $this->assertNoReports();
    }

    // ---- 9 overrides ------------------------------------------------------------

    public function test_09_merge_split_name_and_bom(): void
    {
        $o7 = $this->org();
        $a = $this->owner($o7);
        $q101 = $this->q($a, ['project_name' => 'Main St', 'updated_at' => '2025-01-01 00:00:00']);
        $q102 = $this->q($a, ['project_name' => 'main  st ', 'updated_at' => '2025-02-01 00:00:00']);
        $q103 = $this->q($a, ['project_name' => 'Main St']);
        $q104 = $this->q($a, ['project_name' => 'Estimate']);
        $q105 = $this->q($a, ['project_name' => 'Base bid', 'name' => 'Base bid']);
        $file = $this->csvFile("\xEF\xBB\xBF".implode("\n", [
            'quote_id,org_id,project_key,project_name',
            "$q101,$o7,,",
            "$q102,$o7,,",
            "$q103,$o7,main st phase 2,Main St – Phase 2",
            "$q104,$o7,main st,",
        ])."\n");

        $this->assertSame(0, $this->backfill([], $file));

        $p1 = $this->projectOf($q101);
        $this->assertSame($p1, $this->projectOf($q102));
        $this->assertSame($p1, $this->projectOf($q104));
        $this->assertSame('main  st', DB::table('projects')->where('id', $p1)->value('name'));
        $p2 = $this->projectOf($q103);
        $this->assertNotSame($p1, $p2);
        $this->assertSame('Main St – Phase 2', DB::table('projects')->where('id', $p2)->value('name'));
        $this->assertSame('named:main st phase 2', $this->groupFor($q103)['group_key']);
        $this->assertSame('override', $this->groupFor($q103)['key_source']);
        $p3 = $this->projectOf($q105);
        $this->assertNotContains($p3, [$p1, $p2]);
        $this->assertSame(3, DB::table('projects')->count());
    }

    public static function fatalOverrides(): array
    {
        return [
            'conflicting names' => ["Q1,,k,SECRETA\nQ2,,k,SECRETB\n", ['line 2: conflicting_project_name', 'line 3: conflicting_project_name']],
            'duplicate quote' => ["Q1,,k,\nQ1,,k,\n", ['line 3: duplicate_quote_id']],
            'unknown quote' => ["999999,,SECRETK,\n", ['line 2: unknown_quote']],
            'all empty' => ["Q1,,,\n", ['line 2: empty_override_row']],
            'whitespace key' => ["Q1,,\"   \",\n", ['line 2: blank_cell']],
            'nbsp key' => ["Q1,,\"\u{00A0}\",\n", ['line 2: blank_cell']],
            'missing header' => ["1,2,3\n", ['line 1: missing_or_invalid_header']],
        ];
    }

    #[DataProvider('fatalOverrides')]
    public function test_09b_fatal_override_files_exit_2_write_nothing_and_print_no_values(string $body, array $expected): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'A']);
        $q2 = $this->q($a, ['project_name' => 'B']);
        $body = str_replace(['Q1', 'Q2'], [(string) $q1, (string) $q2], $body);
        $header = str_starts_with($body, '1,2,3') ? '' : "quote_id,org_id,project_key,project_name\n";

        $this->assertSame(2, $this->backfill([], $this->csvFile($header.$body)));

        foreach ($expected as $e) {
            $this->assertStringContainsString($e, $this->out);
        }
        $this->assertStringNotContainsString('SECRET', $this->out);
        $this->assertNothingWritten();
        $this->assertNoReports();
    }

    public function test_09c_override_on_attached_quote_never_attaches_or_moves_it(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $p0 = $this->project($org, $a, 'Zed');
        $att = $this->q($a, ['project_name' => 'Zed']);
        $this->attach($att, $p0);
        $free = $this->q($a, ['project_name' => 'Other']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$att,$org,newkey,Renamed\n");

        $this->assertSame(0, $this->backfill([], $file));

        $this->assertSame($p0, $this->projectOf($att));
        $this->assertSame('Zed', DB::table('projects')->where('id', $p0)->value('name'));
        $this->assertSame(2, DB::table('projects')->count());
        $this->assertNotSame($p0, $this->projectOf($free));
        $w = $this->csv('warnings');
        $this->assertCount(1, $w);
        $this->assertSame('attached_quote_org_or_name_ignored', $w[0]['message']);
    }

    // ---- 10-12 attributes -------------------------------------------------------

    public function test_10_trashed_and_mixed_groups(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $t1 = $this->q($a, ['project_name' => 'Alpha', 'deleted_at' => '2025-05-01 00:00:00']);
        $t2 = $this->q($a, ['project_name' => 'Alpha', 'deleted_at' => '2025-06-01 00:00:00']);
        $m1 = $this->q($a, ['project_name' => 'Beta', 'deleted_at' => '2025-05-01 00:00:00']);
        $m2 = $this->q($a, ['project_name' => 'Beta']);

        $this->assertSame(0, $this->backfill());

        $alpha = DB::table('projects')->where('id', $this->projectOf($t1))->first();
        $this->assertSame($this->projectOf($t1), $this->projectOf($t2));
        $this->assertSame('2025-06-01 00:00:00', $alpha->deleted_at);
        $beta = DB::table('projects')->where('id', $this->projectOf($m1))->first();
        $this->assertSame($this->projectOf($m1), $this->projectOf($m2));
        $this->assertNull($beta->deleted_at);
        $g = $this->groupFor($t1);
        $this->assertSame('2', $g['trashed_quote_count']);
        $this->assertSame('2025-06-01 00:00:00', $g['deleted_at']);
    }

    public function test_11_address_choice_and_conflicts(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'Gamma', 'project_address' => 'Old St 1', 'updated_at' => '2025-01-01 00:00:00']);
        $q2 = $this->q($a, ['project_name' => 'Gamma', 'project_address' => '   ', 'updated_at' => '2025-03-01 00:00:00']);
        $q3 = $this->q($a, ['project_name' => 'Gamma', 'project_address' => ' New St 2 ', 'updated_at' => '2025-02-01 00:00:00']);
        $none = $this->q($a, ['project_name' => 'Delta']);

        $this->assertSame(0, $this->backfill());

        $this->assertSame('New St 2', DB::table('projects')->where('id', $this->projectOf($q1))->value('address'));
        $this->assertNull(DB::table('projects')->where('id', $this->projectOf($none))->value('address'));
        $c = $this->csv('address_conflicts');
        $this->assertCount(1, $c);
        $this->assertSame((string) $q1, $c[0]['quote_id']);
        $this->assertSame('Old St 1', $c[0]['quote_address']);
        $this->assertSame('New St 2', $c[0]['chosen_address']);
        $this->assertSame('1', $this->stat('address conflicts'));
    }

    public function test_12_defaults_and_quote_updated_at_unchanged(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q = $this->q($a, ['project_name' => 'Keep', 'updated_at' => '2025-04-04 04:04:04']);

        $this->assertSame(0, $this->backfill());

        $p = DB::table('projects')->first();
        $this->assertSame('active', $p->status);
        $this->assertNull($p->bid_due_at);
        $this->assertSame($a, (int) $p->created_by);
        $this->assertSame($org, (int) $p->org_id);
        $this->assertSame('2025-04-04 04:04:04', DB::table('quotes')->where('id', $q)->value('updated_at'));
    }

    // ---- 13-17 membership -------------------------------------------------------

    public function test_13_membership_union_and_grant_source(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $b = $this->user();
        $c = $this->user();
        $g1 = $this->user();
        $g2 = $this->user();
        $q1 = $this->q($a, ['project_name' => 'U', 'created_at' => '2024-05-05 00:00:00']);
        $q2 = $this->q($a, ['project_name' => 'U', 'created_at' => '2024-06-06 00:00:00']);
        $this->legacy($q1, $b, $org, true, $g1, '2025-03-01 00:00:00');
        $this->legacy($q2, $b, $org, false, $g2, '2025-01-01 00:00:00');
        $this->legacy($q1, $c, $org, false, $g2, '2025-04-01 00:00:00');

        $this->assertSame(0, $this->backfill());

        $p = $this->projectOf($q1);
        $rows = DB::table('project_members')->where('project_id', $p)->get()->keyBy('user_id');
        $this->assertCount(3, $rows);
        $this->assertSame(1, (int) $rows[$b]->is_active);
        $this->assertSame($g1, (int) $rows[$b]->granted_by);
        $this->assertSame('2025-03-01 00:00:00', $rows[$b]->granted_at);
        $this->assertSame(0, (int) $rows[$c]->is_active);
        $this->assertSame($g2, (int) $rows[$c]->granted_by);
        $this->assertSame(1, (int) $rows[$a]->is_active);
        $this->assertNull($rows[$a]->granted_by);
        $this->assertSame('2024-05-05 00:00:00', $rows[$a]->granted_at);
        $this->assertNull($rows[$a]->quote_id);
        $this->assertSame($org, (int) $rows[$b]->org_id);
    }

    public function test_14_widening_report(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $m = $this->user();
        $qa = $this->q($a, ['project_name' => 'W']);
        $qb = $this->q($a, ['project_name' => 'W']);
        $this->legacy($qa, $m, $org);

        $this->assertSame(0, $this->backfill());

        $w = $this->csv('membership_widening');
        $this->assertCount(1, $w);
        $this->assertSame((string) $m, $w[0]['user_id']);
        $this->assertSame((string) $qb, $w[0]['gained_quote_ids']);
        $this->assertSame((string) $this->projectOf($qa), $w[0]['project_id']);
        $this->assertSame('1', $this->stat('widening users'));
    }

    public function test_15_explicit_owner_removal(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q = $this->q($a, ['project_name' => 'R']);
        $this->legacy($q, $a, $org, false);

        $this->assertSame(0, $this->backfill());

        $row = DB::table('project_members')->where('project_id', $this->projectOf($q))->where('user_id', $a)->first();
        $this->assertSame(0, (int) $row->is_active);
        $m = $this->csv('memberships');
        $this->assertSame('owner_explicitly_removed', $m[0]['action']);
        $this->assertSame('yes', $m[0]['is_owner']);
    }

    public function test_16_b4_rehome_versus_mismatch(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $a = $this->owner($o7);
        $rehome = $this->user();
        $this->role($rehome, $o7);
        $this->role($rehome, $o9);
        $stuck = $this->user();
        $this->role($stuck, $o9);
        $q = $this->q($a, ['project_name' => 'B4']);
        $rowRehome = $this->legacy($q, $rehome, $o9);
        $rowStuck = $this->legacy($q, $stuck, $o9);

        $this->assertSame(0, $this->backfill());

        $p = $this->projectOf($q);
        $m = DB::table('project_members')->where('project_id', $p)->where('user_id', $rehome)->first();
        $this->assertNotNull($m);
        $this->assertSame($o7, (int) $m->org_id);
        $this->assertNull(DB::table('project_members')->where('project_id', $p)->where('user_id', $stuck)->first());
        $r = $this->csv('membership_rehomed');
        $this->assertCount(1, $r);
        $this->assertSame((string) $rowRehome, $r[0]['legacy_row_id']);
        $this->assertSame((string) $o9, $r[0]['legacy_org_id']);
        $this->assertSame((string) $o7, $r[0]['project_org_id']);
        $mm = $this->csv('membership_org_mismatch');
        $this->assertCount(1, $mm);
        $this->assertSame((string) $rowStuck, $mm[0]['legacy_row_id']);
        $this->assertSame((string) $o9, $mm[0]['user_active_org_ids']);
    }

    public function test_17_enrol_is_never_called_and_invariant_holds(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $q = $this->q($a, ['project_name' => 'NoEnrol']);
        $this->legacy($q, $u, $org);
        $fired = false;
        ProjectMember::creating(function () use (&$fired) {
            $fired = true;
        });

        $this->assertSame(0, $this->backfill());

        $this->assertFalse($fired, 'ProjectMember::enrol()/create must not be used');
        $this->assertSame(2, DB::table('project_members')->whereNotNull('project_id')->count());
    }

    // ---- 18 crosswalk -----------------------------------------------------------

    public function test_18_crosswalk_link_duplicates_mismatch_and_unresolved(): void
    {
        $org = $this->org();
        $other = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'CW']);
        $q2 = $this->q($a, ['project_name' => 'CW']);
        $r1 = $this->cw($q1, $org, 'A1', $a);
        $r2 = $this->cw($q2, $org, 'A1', $a);
        $r3 = $this->cw($q1, $other, 'B2', $a);
        $lonely = $this->user();
        $this->role($lonely, $org, false);
        $qu = $this->q($lonely, ['project_name' => 'Unres']);
        $ru = $this->cw($qu, $org, 'Z9', $lonely);

        $this->assertSame(1, $this->backfill());

        $p = $this->projectOf($q1);
        $this->assertSame($p, (int) DB::table('plan_crosswalk')->where('id', $r1)->value('project_id'));
        $this->assertSame($p, (int) DB::table('plan_crosswalk')->where('id', $r2)->value('project_id'));
        $this->assertNull(DB::table('plan_crosswalk')->where('id', $r3)->value('project_id'));
        $this->assertNull(DB::table('plan_crosswalk')->where('id', $ru)->value('project_id'));
        $this->assertSame(['2025-03-03 00:00:00'], DB::table('plan_crosswalk')->pluck('updated_at')->unique()->values()->all());
        $c = collect($this->csv('crosswalk_conflicts'))->keyBy('crosswalk_id');
        $this->assertCount(3, $c);
        $this->assertSame(['duplicate_code', 'yes'], [$c[$r1]['type'], $c[$r1]['linked']]);
        $this->assertSame(['duplicate_code', 'yes'], [$c[$r2]['type'], $c[$r2]['linked']]);
        $this->assertSame(['org_mismatch', 'no'], [$c[$r3]['type'], $c[$r3]['linked']]);
        $this->assertSame('2', $this->stat('crosswalk linked'));
        $this->assertSame('2', $this->stat('crosswalk duplicate codes'));
        $this->assertSame('1', $this->stat('crosswalk org mismatches'));
    }

    // ---- 19-22 idempotency ------------------------------------------------------

    public function test_19_second_run_reports_zeros_and_changes_nothing(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $q1 = $this->q($a, ['project_name' => 'Idem']);
        $q2 = $this->q($a, ['project_name' => 'Idem2']);
        $this->legacy($q1, $u, $org);
        $this->cw($q1, $org, 'X', $a);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$q2,$org,idem2,\n");

        Carbon::setTestNow('2026-02-01 10:00:00');
        $this->assertSame(0, $this->backfill([], $file));
        Carbon::setTestNow('2026-02-02 10:00:00');
        $snap = [$this->table('projects'), $this->table('project_members'), $this->table('quotes'), $this->table('plan_crosswalk')];

        $this->assertSame(0, $this->backfill([], $file));

        foreach (['projects created', 'projects reused', 'quotes attached', 'crosswalk linked'] as $l) {
            $this->assertSame('0', $this->stat($l), $l);
        }
        $this->assertSame('0 (active 0, inactive 0)', $this->stat('members inserted'));
        $this->assertSame($snap, [$this->table('projects'), $this->table('project_members'), $this->table('quotes'), $this->table('plan_crosswalk')]);
    }

    public function test_20_straggler_joins_existing_project_and_existing_rows_are_untouched(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $s = $this->user();
        $q1 = $this->q($a, ['project_name' => 'Join']);
        $this->legacy($q1, $u, $org);
        Carbon::setTestNow('2026-02-01 10:00:00');
        $this->assertSame(0, $this->backfill());
        Carbon::setTestNow('2026-02-02 10:00:00');
        $p = $this->projectOf($q1);
        $before = DB::table('project_members')->where('project_id', $p)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $q2 = $this->q($a, ['project_name' => ' JOIN ']);
        $this->legacy($q2, $s, $org);
        $this->assertSame(0, $this->backfill());

        $this->assertSame($p, $this->projectOf($q2));
        $this->assertSame(1, DB::table('projects')->count());
        $this->assertSame('0', $this->stat('projects created'));
        $this->assertSame('1', $this->stat('projects reused'));
        $this->assertSame('1 (active 1, inactive 0)', $this->stat('members inserted'));
        $after = DB::table('project_members')->where('project_id', $p)->orderBy('id')->get()->map(fn ($r) => (array) $r);
        $this->assertSame($before, $after->take(count($before))->all());
        $this->assertCount(count($before) + 1, $after);
        $actions = collect($this->csv('memberships'))->pluck('action', 'user_id')->all();
        $this->assertSame('existing_untouched', $actions[$a]);
        $this->assertSame('inserted_active', $actions[$s]);
    }

    public function test_21_key_mapping_to_two_projects_is_ambiguous(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $pa = $this->project($org, $a, 'Twin');
        $pb = $this->project($org, $a, 'Twin');
        $this->attach($this->q($a, ['project_name' => 'Twin']), $pa);
        $this->attach($this->q($a, ['project_name' => 'Twin']), $pb);
        $s = $this->q($a, ['project_name' => 'twin']);

        $this->assertSame(1, $this->backfill());

        $this->assertNull($this->projectOf($s));
        $this->assertSame([$s => 'ambiguous_existing_project'], $this->unresolvedReasons());
        $this->assertSame(2, DB::table('projects')->count());
    }

    public function test_22_live_straggler_with_only_trashed_sibling_is_unresolved(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $p = $this->project($org, $a, 'Gone', '2025-06-06 00:00:00');
        $this->attach($this->q($a, ['project_name' => 'Gone', 'deleted_at' => '2025-06-06 00:00:00']), $p);
        $s = $this->q($a, ['project_name' => 'Gone']);

        $this->assertSame(1, $this->backfill());

        $this->assertNull($this->projectOf($s));
        $this->assertSame([$s => 'sibling_project_trashed'], $this->unresolvedReasons());
        $this->assertSame(1, DB::table('projects')->count());
    }

    // ---- 23-27 modes ------------------------------------------------------------

    public function test_23_dry_run_leaves_counts_unchanged_and_uses_dry_run_dir(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $q = $this->q($a, ['project_name' => 'Dry']);
        $this->legacy($q, $u, $org);
        $this->cw($q, $org, 'D1', $a);
        $lonely = $this->user();
        $this->role($lonely, $org, false);
        $qu = $this->q($lonely, ['project_name' => 'Unres']);
        $before = $this->counts();

        $this->assertSame(1, $this->backfill(['--dry-run' => true]));

        $this->assertSame($before, $this->counts());
        $this->assertSame(0, DB::table('projects')->count());
        $this->assertNull($this->projectOf($q));
        $this->assertSame('dry-run', $this->stat('mode'));
        $this->assertStringEndsWith('-dry-run', $this->reportDir());
        $this->assertCount(1, $this->csv('groups'));
        $this->assertSame([$qu => 'no_active_org'], $this->unresolvedReasons());
        $this->assertStringContainsString('exit code: 1', $this->out);
    }

    public function test_23b_dry_run_clean_data_exits_0(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $this->q($a, ['project_name' => 'Dry']);

        $this->assertSame(0, $this->backfill(['--dry-run' => true]));

        $this->assertSame([0, 0, 0, 0], $this->counts());
    }

    private function poisonMemberTrigger(int $userId): void
    {
        DB::unprepared("CREATE TRIGGER boom_member BEFORE INSERT ON project_members WHEN NEW.user_id = $userId BEGIN SELECT RAISE(ABORT, 'boom-member'); END");
    }

    public function test_24_group_failure_is_isolated_and_reported(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $poison = $this->user();
        $ok1 = $this->q($a, ['project_name' => 'ok1']);
        $boom = $this->q($a, ['project_name' => 'boom']);
        $late = $this->q($a, ['project_name' => 'late']);
        $ok2 = $this->q($a, ['project_name' => 'ok2']);
        $this->legacy($late, $poison, $org);
        DB::unprepared("CREATE TRIGGER boom_project BEFORE INSERT ON projects WHEN NEW.name = 'boom' BEGIN SELECT RAISE(ABORT, 'boom-project'); END");
        $this->poisonMemberTrigger($poison);

        $this->assertSame(1, $this->backfill());

        $this->assertNotNull($this->projectOf($ok1));
        $this->assertNotNull($this->projectOf($ok2));
        $this->assertNull($this->projectOf($boom));
        $this->assertNull($this->projectOf($late));
        $this->assertSame(2, DB::table('projects')->count(), 'the late failure must roll back its project insert and quote attach');
        $e = $this->csv('errors');
        $this->assertCount(2, $e);
        $this->assertSame('2', $this->stat('errors'));
        $this->assertContains((string) $boom, array_column($e, 'quote_ids'));
        $this->assertContains((string) $late, array_column($e, 'quote_ids'));
    }

    public function test_25_production_without_force_declined_writes_nothing(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $this->q($a, ['project_name' => 'Prod']);
        $this->app['env'] = 'production';

        $this->mockConsoleOutput = true;
        $this->artisan('projects:backfill')
            ->expectsConfirmation('This runs against PRODUCTION. Note that --dry-run holds row locks for the whole run. Continue?', 'no')
            ->assertExitCode(2);
        $this->withoutMockingConsoleOutput();

        $this->assertNothingWritten();
        $this->assertNoReports();

        $this->assertSame(0, $this->invoke(['--force' => true]));
        $this->assertSame(1, DB::table('projects')->count());
    }

    public function test_26_report_headers_and_file_set_match_c7(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q = $this->q($a, ['project_name' => 'Hdr']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$q,,hdr,\n");

        $this->assertSame(0, $this->backfill([], $file));

        $expected = [
            'groups' => 'project_id,action,org_id,org_resolution,owner_user_id,key_source,group_key,project_name,address,created_at,deleted_at,quote_count,trashed_quote_count,quote_ids,quote_numbers',
            'unresolved' => 'quote_id,quote_number,owner_user_id,reason,owner_active_org_ids,legacy_member_org_ids',
            'memberships' => 'project_id,user_id,org_id,action,is_owner,member_active_in_org,source_row_ids,granted_by,granted_at',
            'membership_widening' => 'project_id,user_id,gained_quote_ids',
            'membership_rehomed' => 'legacy_row_id,quote_id,user_id,legacy_org_id,legacy_is_active,project_id,project_org_id',
            'membership_org_mismatch' => 'legacy_row_id,quote_id,user_id,legacy_org_id,legacy_is_active,project_id,project_org_id,user_active_org_ids',
            'crosswalk_conflicts' => 'crosswalk_id,quote_id,project_id,plan_line_code,org_id,project_org_id,type,linked',
            'address_conflicts' => 'project_id,chosen_address,quote_id,quote_address',
            'candidate_merges' => 'org_id,owner_user_id,normalized_name,quote_ids',
            'warnings' => 'source,line_or_quote_id,message',
            'errors' => 'owner_user_id,group_key,quote_ids,exception_class,message',
        ];
        $dir = $this->reportDir();
        foreach ($expected as $file => $header) {
            $this->assertSame($header."\n", explode("\n", file_get_contents("$dir/$file.csv"), 2)[0]."\n", $file);
        }
        $files = array_values(array_diff(scandir($dir), ['.', '..']));
        $want = array_merge(array_map(fn ($f) => "$f.csv", array_keys($expected)), ['overrides.csv', 'summary.txt']);
        sort($files);
        sort($want);
        $this->assertSame($want, $files);
    }

    private function breakAndRun(callable $break, string $needle): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q = $this->q($a, ['project_name' => 'Sch']);
        $this->legacy($q, $this->user(), $org);
        $break();

        $this->assertSame(2, $this->backfill());

        $this->assertStringContainsString('schema incomplete', $this->out);
        $this->assertStringContainsString($needle, $this->out);
        $this->assertSame(0, DB::table('projects')->count());
        $this->assertNull($this->projectOf($q));
        $this->assertSame(0, DB::table('project_members')->whereNotNull('project_id')->count());
        $this->assertNoReports();
    }

    public function test_27a_schema_project_members_quote_id_not_null(): void
    {
        $this->breakAndRun(fn () => Schema::table('project_members', fn ($t) => $t->unsignedBigInteger('quote_id')->nullable(false)->change()), 'project_members.quote_id must be nullable');
    }

    public function test_27b_schema_plan_crosswalk_quote_id_not_null(): void
    {
        $this->breakAndRun(fn () => Schema::table('plan_crosswalk', fn ($t) => $t->unsignedBigInteger('quote_id')->nullable(false)->change()), 'plan_crosswalk.quote_id must be nullable');
    }

    public function test_27c_schema_quotes_project_address_missing(): void
    {
        $this->breakAndRun(fn () => Schema::table('quotes', fn ($t) => $t->dropColumn('project_address')), 'quotes.project_address missing');
    }

    public function test_27d_schema_plan_crosswalk_project_id_missing(): void
    {
        $this->breakAndRun(fn () => (require database_path('migrations/2026_09_24_000004_add_project_id_to_plan_crosswalk_table.php'))->down(), 'plan_crosswalk.project_id missing');
    }

    // ---- 28-33 -------------------------------------------------------------------

    public function test_28_straggler_does_not_join_a_project_created_by_another_user(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $b = $this->owner($org);
        $foreign = $this->project($org, $b, 'Shared');
        $this->attach($this->q($a, ['project_name' => 'Shared']), $foreign);
        $s = $this->q($a, ['project_name' => 'Shared']);

        $this->assertSame(0, $this->backfill());

        $p = $this->projectOf($s);
        $this->assertNotSame($foreign, $p);
        $this->assertSame($a, (int) DB::table('projects')->where('id', $p)->value('created_by'));
        $this->assertSame('1', $this->stat('projects created'));
        $this->assertSame(0, DB::table('project_members')->where('project_id', $foreign)->count());
    }

    public function test_29_existing_inactive_member_is_not_reactivated_on_reuse(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $q1 = $this->q($a, ['project_name' => 'Stay']);
        $this->legacy($q1, $u, $org, false);
        $this->assertSame(0, $this->backfill());
        $p = $this->projectOf($q1);
        $before = DB::table('project_members')->where('project_id', $p)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertSame(0, (int) DB::table('project_members')->where('project_id', $p)->where('user_id', $u)->value('is_active'));

        $q2 = $this->q($a, ['project_name' => 'Stay']);
        $this->legacy($q2, $u, $org, true);
        $this->assertSame(0, $this->backfill());

        $this->assertSame($before, DB::table('project_members')->where('project_id', $p)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
        $actions = collect($this->csv('memberships'))->pluck('action', 'user_id')->all();
        $this->assertSame('existing_untouched', $actions[$u]);
    }

    public function test_30_timestamps_are_copied_as_stored_strings(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'Ts', 'created_at' => '2024-12-31 23:59:59', 'deleted_at' => '2025-03-03 03:03:03']);
        $q2 = $this->q($a, ['project_name' => 'Ts', 'created_at' => '2025-02-02 02:02:02', 'deleted_at' => '2025-07-07 07:07:07']);

        $this->assertSame(0, $this->backfill());

        $p = DB::table('projects')->first();
        $this->assertSame('2024-12-31 23:59:59', $p->created_at);
        $this->assertSame('2025-07-07 07:07:07', $p->deleted_at);
        $this->assertSame($p->id, $this->projectOf($q1));
        $this->assertSame($p->id, $this->projectOf($q2));
    }

    private function bigScenario(): array
    {
        $org = $this->org();
        $a = $this->owner($org);
        $now = '2025-01-01 00:00:00';
        $rows = [];
        for ($i = 0; $i < 1200; $i++) {
            $rows[] = ['user_id' => $a, 'quote_number' => 'BIG-'.$i, 'status' => 'draft', 'name' => null, 'project_name' => 'Big', 'project_address' => null,
                'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null];
        }
        foreach (array_chunk($rows, 100) as $c) {
            DB::table('quotes')->insert($c);
        }
        $quoteIds = DB::table('quotes')->orderBy('id')->pluck('id')->all();

        $users = [];
        for ($i = 0; $i < 120; $i++) {
            $users[] = ['name' => "M$i", 'email' => "m$i@example.test", 'password' => 'x', 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('users')->insert($users);
        $memberIds = DB::table('users')->where('email', 'like', 'm%@example.test')->orderBy('id')->pluck('id')->all();
        $members = [];
        foreach ($memberIds as $k => $uid) {
            $members[] = ['quote_id' => $quoteIds[$k], 'user_id' => $uid, 'org_id' => $org, 'granted_by' => null, 'granted_at' => $now, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($members, 100) as $c) {
            DB::table('project_members')->insert($c);
        }
        $cw = [];
        for ($i = 0; $i < 450; $i++) {
            $cw[] = ['org_id' => $org, 'quote_id' => $quoteIds[$i], 'plan_line_code' => "C$i", 'created_by' => $a, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($cw, 100) as $c) {
            DB::table('plan_crosswalk')->insert($c);
        }

        return [$org, $a, $quoteIds];
    }

    public function test_31_large_group_is_attached_in_chunks(): void
    {
        [, , $quoteIds] = $this->bigScenario();
        DB::enableQueryLog();

        $this->assertSame(0, $this->backfill());

        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(1200, DB::table('quotes')->whereNotNull('project_id')->count());
        $this->assertSame(1, DB::table('projects')->count());
        $this->assertSame(450, DB::table('plan_crosswalk')->whereNotNull('project_id')->count());
        $max = max(array_map(fn ($q) => count($q['bindings']), $log));
        $this->assertLessThanOrEqual(500, $max, 'no statement may exceed 500 bindings');
        $this->assertCount(1200, $quoteIds);
    }

    public function test_32_long_multibyte_name_is_truncated_by_characters(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $name = str_repeat('é', 300);
        $this->q($a, ['project_name' => $name]);

        $this->assertSame(0, $this->backfill());

        $stored = DB::table('projects')->value('name');
        $this->assertSame(255, mb_strlen($stored, 'UTF-8'));
        $this->assertSame(str_repeat('é', 255), $stored);
    }

    private function escapeDryRunOnce(): void
    {
        $done = false;
        Event::listen(TransactionBeginning::class, function () use (&$done) {
            if (! $done && DB::transactionLevel() >= 2) {
                $done = true;
                $pdo = DB::connection()->getPdo();
                $pdo->exec('COMMIT');
                DB::table('projects')->insert([
                    'org_id' => DB::table('organizations')->value('id'), 'name' => 'escaped',
                    'status' => 'active', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00',
                ]);
                $pdo->exec('BEGIN');
            }
        });
    }

    public function test_33_dry_run_verification_failure_exits_3(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q = $this->q($a, ['project_name' => 'Escape']);
        $this->escapeDryRunOnce();

        $exit = $this->invoke(['--dry-run' => true]);

        $this->assertSame(3, $exit);
        $this->assertStringContainsString('CRITICAL: dry-run verification failed', $this->out);
        $this->assertStringContainsString('exit code: 3', $this->out);
        $this->assertSame(['escaped'], DB::table('projects')->pluck('name')->all(), 'the escaped write persisted, the group write was rolled back');
        $this->assertNull($this->projectOf($q));
    }

    // ---- 34-37 -------------------------------------------------------------------

    private function workedExample(): array
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $a = $this->owner($o7, $o9);
        $x = $this->user();
        $q101 = $this->q($a, ['project_name' => 'Main St', 'updated_at' => '2025-01-01 00:00:00']);
        $q102 = $this->q($a, ['project_name' => 'main  st ', 'updated_at' => '2025-02-01 00:00:00']);
        $q103 = $this->q($a, ['project_name' => 'Main St']);
        $q104 = $this->q($a, ['project_name' => 'Estimate']);
        $q106 = $this->q($a, ['project_name' => 'Main St']);
        $q107 = $this->q($a, ['project_name' => 'Whatever']);
        $p0 = $this->project($o7, $a, 'Zed');
        $q110 = $this->q($a, ['project_name' => 'Zed']);
        $this->attach($q110, $p0);
        $q111 = $this->q($a, ['project_name' => 'Zed']);
        $file = $this->csvFile(implode("\n", [
            'quote_id,org_id,project_key,project_name',
            "$q101,$o7,,",
            "$q102,$o7,,",
            "$q103,$o7,main st phase 2,Main St – Phase 2",
            "$q104,$o7,main st,",
            "$q107,,main st phase 2,",
            "$q110,$o9,zedkey,",
            "$q111,$o7,zedkey,",
        ])."\n");

        return compact('o7', 'o9', 'a', 'x', 'q101', 'q102', 'q103', 'q104', 'q106', 'q107', 'p0', 'q110', 'q111', 'file');
    }

    public function test_34_override_and_sibling_worked_example_with_stragglers(): void
    {
        $w = $this->workedExample();
        extract($w);

        $this->assertSame(1, $this->backfill([], $file));

        $p1 = $this->projectOf($q101);
        $p2 = $this->projectOf($q103);
        $this->assertNotNull($p1);
        $this->assertNotSame($p1, $p2);
        $this->assertSame($p1, $this->projectOf($q104));
        $this->assertSame($p0, $this->projectOf($q111), 'sibling org is projects.org_id, not the override org');
        $this->assertSame($p0, $this->projectOf($q110));
        $this->assertSame([$q106 => 'multi_org_no_consensus', $q107 => 'multi_org_no_consensus'], $this->unresolvedReasons());
        $warn = $this->csv('warnings');
        $this->assertCount(1, $warn);
        $this->assertSame(['source' => 'overrides', 'line_or_quote_id' => '7', 'message' => 'attached_quote_org_or_name_ignored'], $warn[0]);

        $this->legacy($q106, $x, $o7);
        $this->legacy($q107, $x, $o7);
        $this->assertSame(0, $this->backfill([], $file));

        $this->assertSame($p1, $this->projectOf($q106), 'straggler by name joins P1');
        $this->assertSame($p2, $this->projectOf($q107), 'straggler by override key joins P2');
        $this->assertSame($p0, $this->projectOf($q110), 'attached quote not moved');
        $this->assertSame([], $this->unresolvedReasons());
        $warn = $this->csv('warnings');
        $this->assertCount(1, $warn, 'only the disagreeing attached row warns; agreeing rows stay silent');
        $this->assertSame('7', $warn[0]['line_or_quote_id']);
        $this->assertSame(3, DB::table('projects')->count());
        $this->assertSame('2', $this->stat('projects reused'));
    }

    public function test_35_second_run_without_the_file_is_ambiguous_and_joins_nothing(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $a = $this->owner($o7, $o9);
        $x = $this->user();
        $q101 = $this->q($a, ['project_name' => 'Main St']);
        $q103 = $this->q($a, ['project_name' => 'Main St']);
        $q106 = $this->q($a, ['project_name' => 'Main St']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$q101,$o7,,\n$q103,$o7,main st phase 2,Main St – Phase 2\n");

        $this->assertSame(1, $this->backfill([], $file));
        $p1 = $this->projectOf($q101);
        $p2 = $this->projectOf($q103);
        $this->assertNotSame($p1, $p2);
        $this->assertNull($this->projectOf($q106));

        $this->legacy($q106, $x, $o7);
        $this->assertSame(1, $this->backfill());

        $this->assertNull($this->projectOf($q106));
        $this->assertSame([$q106 => 'ambiguous_existing_project'], $this->unresolvedReasons());
        $this->assertSame(2, DB::table('projects')->count());
    }

    public function test_36_typed_keys_do_not_collide(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $single = $this->q($a, ['project_name' => null, 'name' => 'Lone']);
        $named = $this->q($a, ['project_name' => (string) $single]);
        $hash = $this->q($a, ['project_name' => '#q'.$single]);
        $keyed = $this->q($a, ['project_name' => 'Other']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$keyed,,$single,\n");

        $this->assertSame(0, $this->backfill([], $file));

        $ps = [$this->projectOf($single), $this->projectOf($named), $this->projectOf($hash)];
        $this->assertCount(3, array_unique($ps));
        $this->assertSame($this->projectOf($named), $this->projectOf($keyed), 'override key is a named key, never a singleton');
        $this->assertSame('named:'.$single, $this->groupFor($keyed)['group_key']);
        $this->assertSame('singleton:'.$single, $this->groupFor($single)['group_key']);
    }

    public function test_37_null_ordering_and_fallbacks(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $n1 = $this->q($a, ['project_name' => 'Zed', 'updated_at' => null, 'created_at' => '2025-01-01 00:00:00', 'project_address' => 'Null St']);
        $n2 = $this->q($a, ['project_name' => 'zed ', 'updated_at' => '2025-01-02 00:00:00', 'created_at' => '2025-01-02 00:00:00', 'project_address' => 'Dated St']);
        $t1 = $this->q($a, ['project_name' => 'Tie', 'updated_at' => '2025-05-05 00:00:00', 'created_at' => '2025-05-05 00:00:00']);
        $t2 = $this->q($a, ['project_name' => 'tie', 'updated_at' => '2025-05-05 00:00:00', 'created_at' => '2025-05-05 00:00:00']);
        $c1 = $this->q($a, ['project_name' => 'Nullo', 'created_at' => null, 'updated_at' => null]);
        $c2 = $this->q($a, ['project_name' => 'Nullo', 'created_at' => null, 'updated_at' => null]);
        $this->legacy($c1, $u, $org, true, null, null);

        $this->assertSame(0, $this->backfill());

        $zed = DB::table('projects')->where('id', $this->projectOf($n1))->first();
        $this->assertSame('zed', $zed->name, 'a NULL updated_at is never chosen while a non-NULL exists');
        $this->assertSame('Dated St', $zed->address);
        $this->assertSame('tie', DB::table('projects')->where('id', $this->projectOf($t1))->value('name'), 'ties go by id desc');
        $nullo = DB::table('projects')->where('id', $this->projectOf($c1))->first();
        $this->assertSame('2026-09-24 10:00:00', $nullo->created_at, 'all-NULL created_at falls back to $runAt');
        $mem = DB::table('project_members')->where('project_id', $nullo->id)->get()->keyBy('user_id');
        $this->assertNull($mem[$u]->granted_at, 'a NULL legacy granted_at is copied as NULL');
        $this->assertSame('2026-09-24 10:00:00', $mem[$a]->granted_at, 'owner with no row and no quote created_at uses $runAt');
        $this->assertSame($this->projectOf($c1), $this->projectOf($c2));
        $this->assertNotNull($this->projectOf($n2));
    }

    // ---- 38 / 59 concurrent_change ----------------------------------------------

    private function concurrentScenario(): array
    {
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $g1a = $this->q($a, ['project_name' => 'First']);
        $g1b = $this->q($a, ['project_name' => 'First']);
        $g2 = $this->q($a, ['project_name' => 'Second']);
        $this->legacy($g1a, $u, $org);
        $foreign = $this->project($org, $a, 'Sneaky');
        $fired = false;
        Event::listen(TransactionBeginning::class, function () use (&$fired, $g1a, $foreign) {
            if (! $fired) {
                $fired = true;
                DB::table('quotes')->where('id', $g1a)->update(['project_id' => $foreign]);
            }
        });

        return compact('org', 'a', 'g1a', 'g1b', 'g2', 'foreign');
    }

    public function test_38_concurrent_change_is_detected_and_written_nowhere(): void
    {
        extract($this->concurrentScenario());

        $this->assertSame(1, $this->backfill());

        $this->assertSame($foreign, $this->projectOf($g1a));
        $this->assertNull($this->projectOf($g1b));
        $this->assertSame([$g1a => 'concurrent_change', $g1b => 'concurrent_change'], $this->unresolvedReasons());
        $this->assertSame(2, DB::table('projects')->count(), 'foreign project plus the second group only');
        $this->assertSame(0, DB::table('project_members')->where('project_id', $foreign)->count());
    }

    public function test_59_concurrent_change_accounting(): void
    {
        extract($this->concurrentScenario());

        $this->assertSame(1, $this->backfill());

        $groups = $this->csv('groups');
        $this->assertCount(1, $groups);
        $this->assertSame((string) $g2, $groups[0]['quote_ids']);
        $memberProjects = array_column($this->csv('memberships'), 'project_id');
        $this->assertSame([(string) $groups[0]['project_id']], array_values(array_unique($memberProjects)));
        $this->assertSame('1', $this->stat('projects created'));
        $this->assertSame('1', $this->stat('quotes attached'));
        $this->assertSame('2', $this->stat('unresolved quotes'));
    }

    // ---- 39-40 ------------------------------------------------------------------

    public function test_39_all_trashed_group_reuses_trashed_sibling(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $p = $this->project($org, $a, 'Old', '2025-06-06 00:00:00');
        $this->attach($this->q($a, ['project_name' => 'Old', 'deleted_at' => '2025-06-06 00:00:00']), $p);
        DB::table('project_members')->insert(['project_id' => $p, 'user_id' => $a, 'org_id' => $org, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00']);
        $s = $this->q($a, ['project_name' => 'Old', 'deleted_at' => '2025-07-07 00:00:00']);
        $before = $this->table('project_members');

        $this->assertSame(0, $this->backfill());

        $this->assertSame($p, $this->projectOf($s));
        $this->assertSame(1, DB::table('projects')->count());
        $this->assertSame($before, $this->table('project_members'));
        $this->assertSame('1', $this->stat('projects reused'));
    }

    public function test_40_rule_iii_requires_the_legacy_org_to_be_an_active_org(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $o11 = $this->org();
        $a = $this->owner($o7, $o9);
        $x = $this->user();
        $q = $this->q($a, ['project_name' => 'Off']);
        $this->legacy($q, $x, $o11);

        $this->assertSame(1, $this->backfill());

        $this->assertNull($this->projectOf($q));
        $this->assertSame([$q => 'multi_org_no_consensus'], $this->unresolvedReasons());
    }

    // ---- 41-42 ------------------------------------------------------------------

    public function test_41_report_escaping_modes_and_umask(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $names = ['=cmd', '+plus', '-minus', '@at', 'back\\"slash'];
        $ids = [];
        foreach ($names as $n) {
            $ids[$n] = $this->q($a, ['project_name' => $n]);
        }
        $tab = $this->q($a, ['project_name' => 'TabQuote', 'quote_number' => "\tQ-tab"]);
        $cr = $this->q($a, ['project_name' => 'CrQuote', 'quote_number' => "\rQ-cr"]);
        $umask = umask(0022);

        try {
            $this->assertSame(0, $this->backfill());
            $this->assertSame(0022, umask(), 'umask restored');
        } finally {
            umask($umask);
        }

        $rows = collect($this->csv('groups'));
        foreach (['=cmd', '+plus', '-minus', '@at'] as $n) {
            $this->assertSame("'".$n, $rows->firstWhere('quote_ids', (string) $ids[$n])['project_name']);
            $this->assertSame($n, DB::table('projects')->where('id', $this->projectOf($ids[$n]))->value('name'), 'DB value untouched');
        }
        $this->assertSame('back\\"slash', $rows->firstWhere('quote_ids', (string) $ids['back\\"slash'])['project_name']);
        $this->assertSame("'\tQ-tab", $rows->firstWhere('quote_ids', (string) $tab)['quote_numbers']);
        $this->assertSame("'\rQ-cr", $rows->firstWhere('quote_ids', (string) $cr)['quote_numbers']);
        $this->assertSame((string) $this->projectOf($ids['=cmd']), $rows->firstWhere('quote_ids', (string) $ids['=cmd'])['project_id']);
        $dir = $this->reportDir();
        if (DIRECTORY_SEPARATOR === '/') {
            $this->assertSame(0700, fileperms($dir) & 0777);
            foreach (glob("$dir/*") as $f) {
                $this->assertSame(0600, fileperms($f) & 0777, basename($f));
            }
        }
    }

    public function test_42_no_client_text_reaches_the_log_or_console(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $poison = $this->user();
        $this->q($a, ['project_name' => 'SECRETNAME one', 'project_address' => 'SECRETADDR St 1']);
        $bad = $this->q($a, ['project_name' => 'SECRETNAME two', 'project_address' => 'SECRETADDR St 2']);
        $this->legacy($bad, $poison, $org);
        $this->poisonMemberTrigger($poison);
        $logged = [];
        Log::listen(function (MessageLogged $e) use (&$logged) {
            $logged[] = $e->message.json_encode($e->context);
        });

        $this->assertSame(1, $this->backfill());

        foreach ($logged as $line) {
            $this->assertStringNotContainsString('SECRET', $line);
            $this->assertStringNotContainsString('boom-member', $line);
        }
        $this->assertStringNotContainsString('SECRET', $this->out);
        $this->assertStringNotContainsString('boom-member', $this->out);
        $this->assertCount(1, $this->csv('errors'));
    }

    // ---- 43-44 ------------------------------------------------------------------

    public function test_43_h3_live_run_guard(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'Live1']);
        $this->maint->down = false;

        $this->assertSame(0, $this->backfill(['--dry-run' => true]));
        $this->assertStringContainsString('the application is live', $this->out);
        $this->assertSame('no', $this->stat('maintenance mode'));
        $this->assertSame([0, 0, 0, 0], $this->counts());

        $this->assertSame(2, $this->backfill());
        $this->assertStringContainsString('requires maintenance mode', $this->out);
        $this->assertNothingWritten();
        $this->assertNull($this->projectOf($q1));

        $this->assertSame(0, $this->backfill(['--allow-live' => true]));
        $this->assertNotNull($this->projectOf($q1));

        $q2 = $this->q($a, ['project_name' => 'Live2']);
        $this->maint->down = true;
        $this->assertSame(0, $this->backfill());
        $this->assertNotNull($this->projectOf($q2));
        $this->assertSame('yes', $this->stat('maintenance mode'));
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    public function test_44_h4_unlinked_crosswalk_line_is_always_printed(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $this->q($a, ['project_name' => 'Clean']);
        $this->assertSame(0, $this->backfill());
        $this->assertSame('0', $this->stat('unlinked crosswalk rows (org_mismatch)'));
        $this->assertStringContainsString('unlinked crosswalk rows (org_mismatch): 0', file_get_contents($this->reportDir().'/summary.txt'));

        $other = $this->org();
        $q = $this->q($a, ['project_name' => 'Mismatch']);
        $this->cw($q, $other, 'M1', $a);
        $this->assertSame(0, $this->backfill(), 'org_mismatch alone keeps exit 0 (H4 provisional)');
        $this->assertSame('1', $this->stat('unlinked crosswalk rows (org_mismatch)'));
    }

    // ---- 46 ---------------------------------------------------------------------

    public function test_46_overrides_copy_is_byte_identical_and_hashed_over_raw_bytes(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q = $this->q($a, ['project_name' => 'Copy']);
        $raw = "\xEF\xBB\xBF"."quote_id,org_id,project_key,project_name\n$q,,,=SUM(1)\n";
        $file = $this->csvFile($raw);

        $this->assertSame(0, $this->backfill([], $file));

        $dir = $this->reportDir();
        $this->assertSame($raw, file_get_contents("$dir/overrides.csv"));
        $hash = hash('sha256', $raw);
        $this->assertSame($hash, $this->stat('overrides sha256'));
        $this->assertStringContainsString("overrides sha256: $hash", file_get_contents("$dir/summary.txt"));
        $this->assertSame("'=SUM(1)", $this->csv('groups')[0]['project_name'] ?? null);
    }

    // ---- 47-48 ------------------------------------------------------------------

    public function test_47_one_run_at_is_used_for_every_write(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $org = $this->org();
        $a = $this->owner($org);
        $u = $this->user();
        $g1 = $this->q($a, ['project_name' => 'One', 'created_at' => null]);
        $g2 = $this->q($a, ['project_name' => 'Two', 'created_at' => null]);
        $g3 = $this->q($a, ['project_name' => 'Three', 'created_at' => null]);
        $this->legacy($g1, $u, $org, true, null, null);
        Event::listen(TransactionBeginning::class, fn () => Carbon::setTestNow(Carbon::now()->addSecond()));

        $this->assertSame(0, $this->backfill());

        $runAt = '2026-01-01 00:00:00';
        $this->assertSame([$runAt], DB::table('projects')->pluck('updated_at')->unique()->values()->all());
        $this->assertSame([$runAt], DB::table('projects')->pluck('created_at')->unique()->values()->all());
        $this->assertSame([$runAt], DB::table('project_members')->whereNotNull('project_id')->pluck('created_at')->unique()->values()->all());
        $this->assertSame([$runAt], DB::table('project_members')->whereNotNull('project_id')->pluck('updated_at')->unique()->values()->all());
        $this->assertSame([$runAt], DB::table('project_members')->whereNotNull('project_id')->whereNotNull('granted_at')->pluck('granted_at')->unique()->values()->all());
        $this->assertNotNull($this->projectOf($g2));
        $this->assertNotNull($this->projectOf($g3));
    }

    public function test_48_owners_groups_and_members_are_processed_in_ascending_order(): void
    {
        $org = $this->org();
        $u1 = $this->owner($org);
        $u2 = $this->owner($org);
        $mx = $this->user();
        $my = $this->user();
        $this->assertLessThan($my, $mx);
        $b1 = $this->q($u2, ['project_name' => 'B-first']);
        $b2 = $this->q($u2, ['project_name' => 'A-second']);
        $a1 = $this->q($u1, ['project_name' => 'Z-owner1']);
        $a2 = $this->q($u1, ['project_name' => 'Y-owner1']);
        $this->legacy($a1, $my, $org);
        $this->legacy($a1, $mx, $org);

        $this->assertSame(0, $this->backfill());

        $order = DB::table('projects')->orderBy('id')->get()->map(fn ($p) => [(int) $p->created_by, $p->name])->all();
        $this->assertSame([[$u1, 'Z-owner1'], [$u1, 'Y-owner1'], [$u2, 'B-first'], [$u2, 'A-second']], $order);
        $this->assertSame(array_column($this->csv('groups'), 'owner_user_id'), [(string) $u1, (string) $u1, (string) $u2, (string) $u2]);
        $p = $this->projectOf($a1);
        $members = DB::table('project_members')->where('project_id', $p)->orderBy('id')->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([$my, $mx, $u1], $members);
        $this->assertNotNull($this->projectOf($a2));
        $this->assertNotNull($this->projectOf($b1));
        $this->assertNotNull($this->projectOf($b2));
    }

    // ---- 49-50 ------------------------------------------------------------------

    public function test_49_attached_override_warnings_only_for_owners_with_unattached_quotes(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $done = $this->owner($o7);
        $busy = $this->owner($o7);
        $p1 = $this->project($o7, $done, 'Done');
        $a1 = $this->q($done, ['project_name' => 'Done']);
        $this->attach($a1, $p1);
        $p2 = $this->project($o7, $busy, 'Busy');
        $a2 = $this->q($busy, ['project_name' => 'Busy']);
        $this->attach($a2, $p2);
        $this->q($busy, ['project_name' => 'Still unattached']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$a1,$o9,,\n$a2,$o9,,\n");

        $this->assertSame(0, $this->backfill([], $file));

        $w = $this->csv('warnings');
        $this->assertCount(1, $w);
        $this->assertSame(['source' => 'overrides', 'line_or_quote_id' => '3', 'message' => 'attached_quote_org_or_name_ignored'], $w[0]);
    }

    public function test_50_org_without_role_applies_to_unattached_quotes_only(): void
    {
        $o7 = $this->org();
        $stranger = $this->org();
        $a = $this->owner($o7);
        $free = $this->q($a, ['project_name' => 'Free']);
        $p = $this->project($o7, $a, 'Att');
        $att = $this->q($a, ['project_name' => 'Att']);
        $this->attach($att, $p);

        $bad = $this->csvFile("quote_id,org_id,project_key,project_name\n$free,$stranger,,\n");
        $this->assertSame(2, $this->backfill([], $bad));
        $this->assertStringContainsString('line 2: org_without_role', $this->out);
        $this->assertNull($this->projectOf($free));

        $ok = $this->csvFile("quote_id,org_id,project_key,project_name\n$att,$stranger,,\n");
        $this->assertSame(0, $this->backfill([], $ok));
        $this->assertStringNotContainsString('org_without_role', $this->out);
        $this->assertSame($p, $this->projectOf($att));
    }

    // ---- 51 H6 ------------------------------------------------------------------

    public function test_51_invalid_utf8_address_is_warned_and_never_written(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $mixedBad = $this->q($a, ['project_name' => 'Mixed', 'project_address' => "CORRUPTMARK\xFF\xFE", 'updated_at' => '2025-05-01 00:00:00']);
        $mixedGood = $this->q($a, ['project_name' => 'Mixed', 'project_address' => 'Good St 1', 'updated_at' => '2025-01-01 00:00:00']);
        $onlyBad = $this->q($a, ['project_name' => 'Only', 'project_address' => "CORRUPTMARK\xC3\x28"]);

        $this->assertSame(0, $this->backfill());

        $this->assertSame('Good St 1', DB::table('projects')->where('id', $this->projectOf($mixedGood))->value('address'));
        $this->assertNull(DB::table('projects')->where('id', $this->projectOf($onlyBad))->value('address'));
        $w = collect($this->csv('warnings'))->sortBy('line_or_quote_id')->values()->all();
        $this->assertSame([
            ['source' => 'normalize', 'line_or_quote_id' => (string) $mixedBad, 'message' => 'project_address_invalid_utf8'],
            ['source' => 'normalize', 'line_or_quote_id' => (string) $onlyBad, 'message' => 'project_address_invalid_utf8'],
        ], $w);
        $this->assertSame('2', $this->stat('warnings'));
        $this->assertSame([], $this->csv('address_conflicts'));
        foreach (glob($this->reportDir().'/*') as $f) {
            $bytes = file_get_contents($f);
            $this->assertStringNotContainsString('CORRUPTMARK', $bytes, basename($f));
            $this->assertStringNotContainsString("\xFF\xFE", $bytes, basename($f));
        }
        $this->assertStringNotContainsString('CORRUPTMARK', $this->out);
    }

    // ---- 52-53 ------------------------------------------------------------------

    public function test_52_candidate_merges_rule(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $solo = $this->q($a, ['project_name' => 'Base bid', 'name' => 'Base bid']);
        $h1 = $this->q($a, ['project_name' => 'Main St', 'name' => 'Main St']);
        $plain = $this->q($a, ['project_name' => 'main st', 'name' => 'Something else']);
        $this->q($a, ['project_name' => 'Ordinary', 'name' => 'x']);
        $this->q($a, ['project_name' => 'ordinary', 'name' => 'y']);
        $keyed = $this->q($a, ['project_name' => 'Dee', 'name' => 'Dee']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$keyed,,deekey,\n");

        $this->assertSame(0, $this->backfill([], $file));

        $rows = collect($this->csv('candidate_merges'))->sortBy('normalized_name')->values()->all();
        $this->assertSame([
            ['org_id' => (string) $org, 'owner_user_id' => (string) $a, 'normalized_name' => 'base bid', 'quote_ids' => (string) $solo],
            ['org_id' => (string) $org, 'owner_user_id' => (string) $a, 'normalized_name' => 'main st', 'quote_ids' => "$h1 $plain"],
        ], $rows);
    }

    public function test_53_member_order_follows_lowest_source_row_then_owner_last(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $b = $this->user();
        $c = $this->user();
        $q = $this->q($a, ['project_name' => 'Ord']);
        $q2 = $this->q($a, ['project_name' => 'Ord']);
        $rowC = $this->legacy($q, $c, $org);
        $rowB = $this->legacy($q2, $b, $org);
        $this->assertLessThan($rowB, $rowC);
        $this->assertLessThan($c, $b);

        $this->assertSame(0, $this->backfill());

        $p = $this->projectOf($q);
        $inserted = DB::table('project_members')->where('project_id', $p)->orderBy('id')->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([$c, $b, $a], $inserted);
        $this->assertSame([(string) $c, (string) $b, (string) $a], array_column($this->csv('memberships'), 'user_id'));
    }

    // ---- 54-55 ------------------------------------------------------------------

    public function test_54_override_line_numbers_are_record_numbers_with_blank_lines_counted(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'A']);
        $q2 = $this->q($a, ['project_name' => 'B']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n\n$q1,,SECRETKEY,\n$q2,,,\n");

        $this->assertSame(2, $this->backfill([], $file));

        $this->assertStringContainsString('line 4: empty_override_row', $this->out);
        $this->assertStringNotContainsString('line 3', $this->out);
        $this->assertStringNotContainsString('SECRETKEY', $this->out);
        $this->assertNothingWritten();
    }

    public function test_55_override_text_rules(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $q1 = $this->q($a, ['project_name' => 'A']);
        $q2 = $this->q($a, ['project_name' => 'B']);
        $head = "quote_id,org_id,project_key,project_name\n";

        $this->assertSame(2, $this->backfill([], $this->csvFile($head."$q1,,k,bad\xFF\xFEname\n")));
        $this->assertStringContainsString('line 2: invalid_utf8', $this->out);

        $this->assertSame(2, $this->backfill([], $this->csvFile($head."$q1,,k,Main St\n$q2,,k,main st\n")));
        $this->assertStringContainsString('line 2: conflicting_project_name', $this->out);
        $this->assertStringContainsString('line 3: conflicting_project_name', $this->out);
        $this->assertNothingWritten();

        $this->assertSame(0, $this->backfill([], $this->csvFile($head."$q1,,k,Main St\n$q2,,k, Main St \n")));
        $this->assertSame($this->projectOf($q1), $this->projectOf($q2));
        $this->assertSame('Main St', DB::table('projects')->value('name'));
    }

    // ---- 56-58 ------------------------------------------------------------------

    public function test_56_no_statement_exceeds_500_bindings_and_inserts_stay_within_50_rows(): void
    {
        $this->bigScenario();
        DB::enableQueryLog();

        $this->assertSame(0, $this->backfill());

        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $memberInserts = 0;
        foreach ($log as $q) {
            $this->assertLessThanOrEqual(500, count($q['bindings']), substr($q['query'], 0, 80));
            if (str_starts_with($q['query'], 'insert into "project_members"')) {
                $memberInserts++;
                $this->assertLessThanOrEqual(50, count($q['bindings']) / 8);
            }
        }
        $this->assertGreaterThanOrEqual(3, $memberInserts, '121 members need at least 3 insert statements of 50 rows');
        $this->assertSame(121, DB::table('project_members')->whereNotNull('project_id')->count());
    }

    public function test_57_pre_pass_finds_a_later_owners_conflict_before_any_write(): void
    {
        $org = $this->org();
        $first = $this->owner($org);
        $later = $this->owner($org);
        $ok = $this->q($first, ['project_name' => 'Fine']);
        $l1 = $this->q($later, ['project_name' => 'X']);
        $l2 = $this->q($later, ['project_name' => 'Y']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$l1,,k,One\n$l2,,k,Two\n");

        $this->assertSame(2, $this->backfill([], $file));

        $this->assertStringContainsString('conflicting_project_name', $this->out);
        $this->assertNothingWritten();
        $this->assertNull($this->projectOf($ok));
        $this->assertNoReports();
    }

    public function test_57b_pre_pass_warnings_are_not_duplicated(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $bad = $this->q($a, ['project_name' => "\xC3\x28", 'name' => 'Broken']);
        $ok = $this->q($a, ['project_name' => 'Fine']);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$ok,,,Fine name\n");

        $this->assertSame(0, $this->backfill([], $file));

        $w = $this->csv('warnings');
        $this->assertCount(1, $w);
        $this->assertSame((string) $bad, $w[0]['line_or_quote_id']);
    }

    public function test_58_every_report_is_complete_after_each_exit_path(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $poison = $this->user();
        $this->q($a, ['project_name' => 'Fine']);
        $bad = $this->q($a, ['project_name' => 'Bad']);
        $this->legacy($bad, $poison, $org);
        $this->poisonMemberTrigger($poison);

        $this->assertSame(1, $this->backfill());
        $this->assertReportsComplete(1);

        $this->assertSame(1, $this->backfill(['--dry-run' => true]));
        $this->assertReportsComplete(1);
    }

    public function test_58b_reports_complete_on_clean_run_and_exit_3(): void
    {
        $org = $this->org();
        $a = $this->owner($org);
        $this->q($a, ['project_name' => 'Fine']);
        $this->assertSame(0, $this->backfill());
        $this->assertReportsComplete(0);

        $this->q($a, ['project_name' => 'Escape']);
        $this->escapeDryRunOnce();
        $this->exit = $this->invoke(['--dry-run' => true]);
        $this->assertSame(3, $this->exit);
        $this->assertReportsComplete(3);
    }

    // ---- 60-62 (C16) ------------------------------------------------------------

    public function test_60_b4_rehome_requires_an_active_role_in_the_project_org(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $a = $this->owner($o7);
        $u = $this->user();
        $this->role($u, $o7, false);
        $this->role($u, $o9);
        $v = $this->user();
        $this->role($v, $o7);
        $w = $this->user();
        $this->role($w, $o7, false);
        $this->role($w, $o7);
        $q = $this->q($a, ['project_name' => 'B4 active']);
        $rowU = $this->legacy($q, $u, $o9);
        $rowV = $this->legacy($q, $v, $o9);
        $rowW = $this->legacy($q, $w, $o9);

        $this->assertSame(0, $this->backfill());

        $p = $this->projectOf($q);
        $this->assertNull(DB::table('project_members')->where('project_id', $p)->where('user_id', $u)->first());
        foreach ([$v, $w] as $ok) {
            $this->assertSame($o7, (int) DB::table('project_members')->where('project_id', $p)->where('user_id', $ok)->value('org_id'));
        }
        $mm = $this->csv('membership_org_mismatch');
        $this->assertCount(1, $mm);
        $this->assertSame((string) $rowU, $mm[0]['legacy_row_id']);
        $this->assertSame((string) $o9, $mm[0]['user_active_org_ids']);
        $this->assertSame((string) $o7, $mm[0]['project_org_id']);
        $this->assertEqualsCanonicalizing([(string) $rowV, (string) $rowW], array_column($this->csv('membership_rehomed'), 'legacy_row_id'));
        $this->assertSame('1', $this->stat('membership org mismatch'));
        $this->assertSame('2', $this->stat('membership rehomed'));
    }

    public function test_61_owner_active_orgs_feed_membership_decisions(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $o11 = $this->org();

        $a = $this->owner($o7);
        $qa = $this->q($a, ['project_name' => 'Fixture A']);
        $this->legacy($qa, $a, $o9);
        $this->assertSame(0, $this->backfill());
        $this->assertOwnerRehomed($qa, $a, $o7, 'yes');

        $b = $this->owner($o7, $o9);
        $qb = $this->q($b, ['project_name' => 'Fixture B']);
        $this->legacy($qb, $b, $o7);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$qb,$o9,,\n");
        $this->assertSame(0, $this->backfill([], $file));
        $this->assertOwnerRehomed($qb, $b, $o9, 'yes');

        $c = $this->owner($o7);
        $this->role($c, $o11, false);
        $qc = $this->q($c, ['project_name' => 'Fixture C']);
        $rowC = $this->legacy($qc, $c, $o9);
        $file = $this->csvFile("quote_id,org_id,project_key,project_name\n$qc,$o11,,\n");
        $this->assertSame(0, $this->backfill([], $file));
        $mm = collect($this->csv('membership_org_mismatch'))->firstWhere('legacy_row_id', (string) $rowC);
        $this->assertNotNull($mm);
        $this->assertNull(collect($this->csv('membership_rehomed'))->firstWhere('legacy_row_id', (string) $rowC));
        $owner = collect($this->csv('memberships'))->firstWhere('user_id', (string) $c);
        $this->assertSame('no', $owner['member_active_in_org']);
        $this->assertSame((string) $o11, $owner['org_id']);
    }

    private function assertOwnerRehomed(int $quote, int $owner, int $projectOrg, string $activeInOrg): void
    {
        $p = $this->projectOf($quote);
        $this->assertSame($projectOrg, (int) DB::table('projects')->where('id', $p)->value('org_id'));
        $this->assertSame($projectOrg, (int) DB::table('project_members')->where('project_id', $p)->where('user_id', $owner)->value('org_id'));
        $rehomed = collect($this->csv('membership_rehomed'))->firstWhere('quote_id', (string) $quote);
        $this->assertNotNull($rehomed, 'owner legacy row is rehomed');
        $this->assertNull(collect($this->csv('membership_org_mismatch'))->firstWhere('quote_id', (string) $quote));
        $m = collect($this->csv('memberships'))->firstWhere('user_id', (string) $owner);
        $this->assertSame($activeInOrg, $m['member_active_in_org']);
    }

    public function test_62_owner_with_inactive_and_active_roles_resolves_to_the_active_org(): void
    {
        $o7 = $this->org();
        $o9 = $this->org();
        $a = $this->user();
        $this->role($a, $o7, false);
        $this->role($a, $o9);
        $q = $this->q($a, ['project_name' => 'Mixed roles']);

        $this->assertSame(0, $this->backfill());

        $this->assertSame($o9, (int) DB::table('projects')->value('org_id'));
        $this->assertSame('single_org', $this->groupFor($q)['org_resolution']);
        $this->assertSame([], $this->csv('unresolved'));
    }

    private function assertReportsComplete(int $exit): void
    {
        $dir = $this->reportDir();
        foreach (glob("$dir/*.csv") as $f) {
            if (basename($f) === 'overrides.csv') {
                continue;
            }
            $this->assertNotSame('', (string) file_get_contents($f), basename($f).' has a header');
            $this->assertStringEndsWith("\n", file_get_contents($f), basename($f));
        }
        $summary = rtrim(file_get_contents("$dir/summary.txt"), "\n");
        $lines = explode("\n", $summary);
        $this->assertSame("exit code: $exit", end($lines));
        $this->assertSame($exit, $this->exit);
    }
}

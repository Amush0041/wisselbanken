<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * customers:backfill-org. Fixture (ProjectTestCase): orgA = 1, orgB = 2; p1/p2 in A, p3 in B;
 * est/viewer/super/stranger/owner in A only, outsider in B only, multi in A and B.
 */
class CustomerOrgBackfillTest extends ProjectTestCase
{
    private string $tmp;
    private string $origStorage;
    private string $origEnv;
    private string $out = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->origStorage = $this->app->storagePath();
        $this->origEnv = $this->app['env'];
        $this->tmp = sys_get_temp_dir().'/cust-backfill-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
        $this->app->useStoragePath($this->tmp);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->origStorage);
        $this->app['env'] = $this->origEnv;
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    private function cust(int $creator, ?int $org = null, ?string $deletedAt = null): int
    {
        return (int) DB::table('customers')->insertGetId([
            'user_id' => $creator, 'org_id' => $org, 'company_name' => 'C'.uniqid(), 'deleted_at' => $deletedAt,
            'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
        ]);
    }

    private function useBy(int $quoteId, int $customerId): void
    {
        DB::table('quotes')->where('id', $quoteId)->update(['customer_id' => $customerId]);
    }

    private function org(int $id): ?int
    {
        $v = DB::table('customers')->where('id', $id)->value('org_id');

        return $v === null ? null : (int) $v;
    }

    private function bf(array $opts = []): int
    {
        $buf = new \Symfony\Component\Console\Output\BufferedOutput();
        $code = Artisan::call('customers:backfill-org', $opts, $buf);
        $this->out = $buf->fetch();

        return $code;
    }

    private function reportDir(): string
    {
        $dirs = glob($this->tmp.'/app/backfill/customers-org/*', GLOB_ONLYDIR);
        $this->assertNotEmpty($dirs, 'report directory written');
        sort($dirs);

        return end($dirs);
    }

    /** @return array<int, array<string,string>> */
    private function csv(string $file): array
    {
        $h = fopen($this->reportDir().'/'.$file, 'r');
        $header = fgetcsv($h);
        $rows = [];
        while (($r = fgetcsv($h)) !== false) {
            $rows[] = array_combine($header, $r);
        }
        fclose($h);

        return $rows;
    }

    private function overrides(string $content): string
    {
        $f = $this->tmp.'/ov-'.uniqid().'.csv';
        file_put_contents($f, $content);

        return $f;
    }

    // ---- rules -------------------------------------------------------------------------

    public function test_rule1_a_single_org_of_quote_evidence_wins_even_over_a_creator_in_another_org(): void
    {
        $c = $this->cust($this->outsider->id);
        $this->useBy($this->q1, $c);

        $this->assertSame(0, $this->bf());
        $this->assertSame($this->orgA->id, $this->org($c));
        $this->assertSame('quote_org', $this->csv('assigned.csv')[0]['rule']);
        $this->assertCount(1, $this->csv('warnings.csv'), 'creator differs from quote org is warned');
    }

    public function test_rule1_counts_soft_deleted_quotes_and_two_quotes_in_the_same_org_are_one_org(): void
    {
        $c = $this->cust($this->multi->id);
        $this->useBy($this->q1, $c);
        $this->useBy($this->q3, $c);
        DB::table('quotes')->where('id', $this->q3)->update(['deleted_at' => now()]);
        $d = $this->cust($this->multi->id);
        $this->useBy($this->q4, $d);
        DB::table('quotes')->where('id', $this->q4)->update(['deleted_at' => now()]);

        $this->assertSame(0, $this->bf());
        $this->assertSame($this->orgA->id, $this->org($c));
        $this->assertSame($this->orgB->id, $this->org($d), 'a soft-deleted quote is still evidence (creator is in A and B)');
    }

    public function test_rule1_soft_deleted_project_still_counts_as_evidence(): void
    {
        $p = $this->mkProject($this->orgB, $this->outsider, 'Gone', now()->toDateTimeString());
        $q = $this->mkQuote($this->outsider, $p);
        $c = $this->cust($this->multi->id);
        $this->useBy($q, $c);

        $this->assertSame(0, $this->bf());
        $this->assertSame($this->orgB->id, $this->org($c));
    }

    public function test_rule2_quotes_spanning_orgs_are_unresolved_and_left_null(): void
    {
        $c = $this->cust($this->multi->id);
        $this->useBy($this->q1, $c);
        $this->useBy($this->q4, $c);

        $this->assertSame(1, $this->bf());
        $this->assertNull($this->org($c));
        $u = $this->csv('unresolved.csv');
        $this->assertSame('quotes span several orgs', $u[0]['reason']);
        $this->assertSame((string) $c, $u[0]['customer_id']);
    }

    public function test_quotes_without_a_project_are_not_evidence(): void
    {
        $c = $this->cust($this->est->id);
        $this->useBy($this->q0, $c);

        $this->assertSame(0, $this->bf());
        $this->assertSame($this->orgA->id, $this->org($c));
        $this->assertSame('creator_single_org', $this->csv('assigned.csv')[0]['rule']);
    }

    public function test_rule3_no_quotes_and_a_creator_with_one_active_org_gets_that_org(): void
    {
        $c = $this->cust($this->outsider->id);

        $this->assertSame(0, $this->bf());
        $this->assertSame($this->orgB->id, $this->org($c));
    }

    public function test_rule3_ignores_inactive_role_rows(): void
    {
        $u = $this->mkUser($this->orgA, 'estimator');
        $this->assignRole($u, $this->orgB, 'estimator', false);
        $c = $this->cust($u->id);

        $this->assertSame(0, $this->bf());
        $this->assertSame($this->orgA->id, $this->org($c));
    }

    public function test_rule4_a_creator_with_several_or_no_active_orgs_is_unresolved(): void
    {
        $several = $this->cust($this->multi->id);
        $none = $this->cust(User::factory()->create()->id);
        $inactiveOnly = $this->mkUser();
        $this->assignRole($inactiveOnly, $this->orgA, 'estimator', false);
        $inactive = $this->cust($inactiveOnly->id);

        $this->assertSame(1, $this->bf());
        $this->assertNull($this->org($several));
        $this->assertNull($this->org($none));
        $this->assertNull($this->org($inactive));
        $reasons = collect($this->csv('unresolved.csv'))->pluck('reason', 'customer_id');
        $this->assertSame('no quote evidence and creator has several active orgs', $reasons[(string) $several]);
        $this->assertSame('no quote evidence and creator has no active org', $reasons[(string) $none]);
        $this->assertSame('no quote evidence and creator has no active org', $reasons[(string) $inactive]);
    }

    public function test_soft_deleted_customers_are_backfilled_too(): void
    {
        $c = $this->cust($this->est->id, null, now()->toDateTimeString());

        $this->assertSame(0, $this->bf());
        $this->assertSame($this->orgA->id, $this->org($c));
        $this->assertSame('1', $this->csv('assigned.csv')[0]['deleted']);
    }

    // ---- overrides -----------------------------------------------------------------------

    public function test_overrides_win_over_every_rule_for_listed_ids_only(): void
    {
        $quoted = $this->cust($this->est->id);
        $this->useBy($this->q1, $quoted);
        $spanning = $this->cust($this->multi->id);
        $this->useBy($this->q1, $spanning);
        $this->useBy($this->q4, $spanning);
        $creatorRule = $this->cust($this->est->id);
        $f = $this->overrides("customer_id,org_id\n$quoted,{$this->orgB->id}\n$spanning,{$this->orgA->id}\n");

        $this->assertSame(0, $this->bf(['--overrides' => $f]));
        $this->assertSame($this->orgB->id, $this->org($quoted));
        $this->assertSame($this->orgA->id, $this->org($spanning));
        $this->assertSame($this->orgA->id, $this->org($creatorRule));
        $rules = collect($this->csv('assigned.csv'))->pluck('rule', 'customer_id');
        $this->assertSame('override', $rules[(string) $quoted]);
        $this->assertSame('creator_single_org', $rules[(string) $creatorRule]);
    }

    public function test_overrides_reject_unknown_customer_unknown_org_bad_header_and_missing_file_and_write_nothing(): void
    {
        $c = $this->cust($this->est->id);
        $bad = [
            "customer_id,org_id\n9999,{$this->orgA->id}\n",
            "customer_id,org_id\n$c,9999\n",
            "customer_id,org_id\n$c,abc\n",
            "id,org\n$c,{$this->orgA->id}\n",
            "customer_id,org_id\n$c,{$this->orgA->id}\n$c,9999\n",
        ];
        foreach ($bad as $content) {
            $this->assertSame(2, $this->bf(['--overrides' => $this->overrides($content)]), $content);
            $this->assertNull($this->org($c), 'nothing written on refusal');
        }
        $this->assertSame(2, $this->bf(['--overrides' => $this->tmp.'/missing.csv']));
        $this->assertNull($this->org($c));
    }

    // ---- dry run / idempotence / safety ---------------------------------------------------

    public function test_dry_run_writes_nothing_but_reports(): void
    {
        $c = $this->cust($this->est->id);
        $u = $this->cust($this->multi->id);

        $this->assertSame(1, $this->bf(['--dry-run' => true]));
        $this->assertNull($this->org($c));
        $this->assertNull($this->org($u));
        $this->assertSame((string) $c, $this->csv('assigned.csv')[0]['customer_id']);
        $this->assertStringContainsString('DRY RUN: assigned 1, unresolved 1', file_get_contents($this->reportDir().'/summary.txt'));
    }

    public function test_second_run_changes_nothing_and_says_nothing_to_do(): void
    {
        $c = $this->cust($this->est->id);
        $this->assertSame(0, $this->bf());
        $snapshot = DB::table('customers')->get()->map(fn ($r) => (array) $r)->all();

        $this->assertSame(0, $this->bf());
        $this->assertSame($snapshot, DB::table('customers')->get()->map(fn ($r) => (array) $r)->all());
        $this->assertSame('assigned 0, unresolved 0, already set 1', trim(file_get_contents($this->reportDir().'/summary.txt')));
        $this->artisan('customers:backfill-org')->expectsOutputToContain('nothing to do')->assertExitCode(0);
        $this->assertSame([], $this->csv('assigned.csv'));
        $this->assertSame($this->orgA->id, $this->org($c));
    }

    public function test_only_null_org_rows_are_touched_and_timestamps_are_not_changed(): void
    {
        $set = $this->cust($this->est->id, $this->orgB->id);
        $this->useBy($this->q1, $set);
        $null = $this->cust($this->est->id);
        $f = $this->overrides("customer_id,org_id\n$set,{$this->orgA->id}\n");

        $this->assertSame(0, $this->bf(['--overrides' => $f]));
        $this->assertSame($this->orgB->id, $this->org($set), 'an already set org is never changed, even by override');
        $this->assertSame($this->orgA->id, $this->org($null));
        $this->assertSame('2024-01-01 00:00:00', DB::table('customers')->where('id', $null)->value('updated_at'));
        $this->assertSame([(string) $null], array_column($this->csv('assigned.csv'), 'customer_id'));
    }

    public function test_a_row_that_gets_an_org_between_planning_and_writing_is_not_overwritten(): void
    {
        $c = $this->cust($this->est->id);
        $fired = false;
        DB::listen(function ($q) use ($c, &$fired) {
            if (! $fired && str_contains($q->sql, 'from "quotes"')) {
                $fired = true;
                DB::table('customers')->where('id', $c)->update(['org_id' => $this->orgB->id]);
            }
        });

        $this->bf();

        $this->assertTrue($fired);
        $this->assertSame($this->orgB->id, $this->org($c), 'the concurrent value wins; the UPDATE is guarded by org_id IS NULL');
    }

    public function test_it_fills_more_than_one_chunk(): void
    {
        $ids = [];
        for ($i = 0; $i < 850; $i++) {
            $ids[] = ['user_id' => $this->est->id, 'company_name' => "N$i", 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($ids, 100) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        $this->assertSame(0, $this->bf());
        $this->assertSame(0, DB::table('customers')->whereNull('org_id')->count());
        $this->assertCount(850, $this->csv('assigned.csv'));
    }

    // ---- guards and exit codes --------------------------------------------------------------

    public function test_production_needs_force_and_writes_nothing_without_it(): void
    {
        $c = $this->cust($this->est->id);
        $this->app['env'] = 'production';

        $this->assertSame(2, $this->bf());
        $this->assertNull($this->org($c));
        $this->assertSame(0, $this->bf(['--dry-run' => true]), 'dry run is allowed in production');
        $this->assertNull($this->org($c));
        $this->assertSame(0, $this->bf(['--force' => true]));
        $this->assertSame($this->orgA->id, $this->org($c));
    }

    public function test_exit_2_when_the_column_is_missing_and_nothing_is_written(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('org_id'));

        $this->artisan('customers:backfill-org')->expectsOutputToContain('customers.org_id is missing')->assertExitCode(2);
        $this->assertDirectoryDoesNotExist($this->tmp.'/app/backfill');
    }

    public function test_exit_codes_0_clean_and_1_unresolved_remain(): void
    {
        $this->assertSame(0, $this->bf(), 'no customers');
        $this->cust($this->est->id);
        $this->assertSame(0, $this->bf());
        $this->cust($this->multi->id);
        $this->assertSame(1, $this->bf());
        $this->assertSame(1, $this->bf(), 'still unresolved on a rerun');
    }

    public function test_reports_are_written_with_headers_and_a_summary(): void
    {
        $this->cust($this->est->id);
        $this->bf();
        $dir = $this->reportDir();

        foreach (['assigned.csv', 'unresolved.csv', 'warnings.csv', 'summary.txt'] as $f) {
            $this->assertFileExists("$dir/$f");
        }
        $this->assertStringStartsWith('customer_id,user_id,org_id,rule,deleted', file_get_contents("$dir/assigned.csv"));
        $this->assertStringStartsWith('customer_id,user_id,reason,quote_org_ids,creator_active_org_ids,deleted', file_get_contents("$dir/unresolved.csv"));
        $this->assertSame('assigned 1, unresolved 0, already set 0', trim(file_get_contents("$dir/summary.txt")));
    }
}

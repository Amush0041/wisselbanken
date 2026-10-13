<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillCustomerOrg extends Command
{
    protected $signature = 'customers:backfill-org
        {--dry-run}
        {--overrides= : path to a CSV with header customer_id,org_id}
        {--force : required when APP_ENV=production}';

    protected $description = 'Fill customers.org_id from quote evidence, else the creator\'s single active org';

    private const CHUNK = 400;

    public function handle(): int
    {
        if (! Schema::hasTable('customers') || ! Schema::hasColumn('customers', 'org_id')) {
            $this->error('customers.org_id is missing; run migration 2026_10_14_000001 first');

            return 2;
        }

        $dry = (bool) $this->option('dry-run');

        if (! $dry && app()->environment('production') && ! $this->option('force')) {
            $this->error('production run requires --force');

            return 2;
        }

        $overrides = [];
        $path = $this->option('overrides');
        if ($path !== null && $path !== '') {
            $overrides = $this->loadOverrides((string) $path);
            if ($overrides === null) {
                return 2;
            }
        }

        $dir = storage_path('app/backfill/customers-org/'.now()->format('Ymd_His'));
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('cannot create report directory');

            return 2;
        }

        $assigned = fopen($dir.'/assigned.csv', 'w');
        $unresolved = fopen($dir.'/unresolved.csv', 'w');
        $warnings = fopen($dir.'/warnings.csv', 'w');
        fputcsv($assigned, ['customer_id', 'user_id', 'org_id', 'rule', 'deleted']);
        fputcsv($unresolved, ['customer_id', 'user_id', 'reason', 'quote_org_ids', 'creator_active_org_ids', 'deleted']);
        fputcsv($warnings, ['customer_id', 'message']);

        $counts = ['assigned' => 0, 'unresolved' => 0, 'already_set' => (int) DB::table('customers')->whereNotNull('org_id')->count()];

        DB::table('customers')->whereNull('org_id')->orderBy('id')->chunkById(self::CHUNK, function ($rows) use (&$counts, $overrides, $dry, $assigned, $unresolved, $warnings) {
            $ids = $rows->pluck('id')->all();

            $quoteOrgs = [];
            foreach (DB::table('quotes')
                ->join('projects', 'projects.id', '=', 'quotes.project_id')
                ->whereIn('quotes.customer_id', $ids)
                ->select('quotes.customer_id', 'projects.org_id')->distinct()->get() as $r) {
                $quoteOrgs[$r->customer_id][] = (int) $r->org_id;
            }

            $creatorIds = $rows->pluck('user_id')->unique()->all();
            $creatorOrgs = [];
            foreach (DB::table('user_org_roles')->whereIn('user_id', $creatorIds)->where('is_active', 1)
                ->select('user_id', 'org_id')->distinct()->get() as $r) {
                $creatorOrgs[$r->user_id][] = (int) $r->org_id;
            }

            $plan = [];
            foreach ($rows as $c) {
                $q = array_values(array_unique($quoteOrgs[$c->id] ?? []));
                $u = array_values(array_unique($creatorOrgs[$c->user_id] ?? []));
                $deleted = $c->deleted_at !== null ? 1 : 0;
                $org = null;
                $rule = null;
                $reason = null;

                if (isset($overrides[$c->id])) {
                    $org = $overrides[$c->id];
                    $rule = 'override';
                } elseif (count($q) === 1) {
                    $org = $q[0];
                    $rule = 'quote_org';
                    if ($u !== [] && ! in_array($org, $u, true)) {
                        fputcsv($warnings, [$c->id, 'quote org '.$org.' differs from creator active orgs '.implode('|', $u)]);
                    }
                } elseif (count($q) >= 2) {
                    $reason = 'quotes span several orgs';
                } elseif (count($u) === 1) {
                    $org = $u[0];
                    $rule = 'creator_single_org';
                } else {
                    $reason = $u === [] ? 'no quote evidence and creator has no active org' : 'no quote evidence and creator has several active orgs';
                }

                if ($org === null) {
                    $counts['unresolved']++;
                    fputcsv($unresolved, [$c->id, $c->user_id, $reason, implode('|', $q), implode('|', $u), $deleted]);

                    continue;
                }

                $plan[$c->id] = $org;
                fputcsv($assigned, [$c->id, $c->user_id, $org, $rule, $deleted]);
            }

            if (! $dry && $plan !== []) {
                DB::transaction(function () use ($plan) {
                    foreach ($plan as $id => $org) {
                        DB::table('customers')->where('id', $id)->whereNull('org_id')->update(['org_id' => $org]);
                    }
                });
            }
            $counts['assigned'] += count($plan);
        });

        fclose($assigned);
        fclose($unresolved);
        fclose($warnings);

        $summary = ($dry ? 'DRY RUN: ' : '').sprintf(
            'assigned %d, unresolved %d, already set %d',
            $counts['assigned'], $counts['unresolved'], $counts['already_set']
        );
        file_put_contents($dir.'/summary.txt', $summary.PHP_EOL);
        $this->info($summary);
        $this->line('reports: '.$dir);

        if ($counts['assigned'] === 0 && $counts['unresolved'] === 0) {
            $this->info('nothing to do');
        }

        return $counts['unresolved'] > 0 ? 1 : 0;
    }

    private function loadOverrides(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('overrides file is not readable');

            return null;
        }

        $out = [];
        $h = fopen($path, 'r');
        $header = fgetcsv($h);
        if ($header === false || array_map('trim', $header) !== ['customer_id', 'org_id']) {
            fclose($h);
            $this->error('overrides header must be customer_id,org_id');

            return null;
        }

        $line = 1;
        while (($row = fgetcsv($h)) !== false) {
            $line++;
            if ($row === [null] || count($row) < 2) {
                continue;
            }
            $cid = trim($row[0]);
            $oid = trim($row[1]);
            if (! ctype_digit($cid) || ! ctype_digit($oid)
                || ! DB::table('customers')->where('id', (int) $cid)->exists()
                || ! DB::table('organizations')->where('id', (int) $oid)->exists()) {
                fclose($h);
                $this->error("overrides line {$line}: unknown customer or organization");

                return null;
            }
            $out[(int) $cid] = (int) $oid;
        }
        fclose($h);

        return $out;
    }
}

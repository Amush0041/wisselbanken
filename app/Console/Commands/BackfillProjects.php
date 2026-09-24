<?php

namespace App\Console\Commands;

use App\Support\ProjectBackfillPlanner;
use Illuminate\Console\Command;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BackfillProjects extends Command
{
    protected $signature = 'projects:backfill
        {--dry-run}
        {--overrides= : path to the overrides CSV}
        {--force : skip the production confirmation}
        {--allow-live : real run without maintenance mode}';

    protected $description = 'Group unattached quotes into projects and move their memberships and crosswalk rows up';

    private const CHUNK = 400;

    private const OVERRIDES_HEADER = ['quote_id', 'org_id', 'project_key', 'project_name'];

    private const REQUIRED_COLUMNS = [
        'quotes' => ['id', 'user_id', 'name', 'project_name', 'project_address', 'quote_number', 'created_at', 'updated_at', 'deleted_at', 'project_id'],
        'project_members' => ['id', 'quote_id', 'project_id', 'user_id', 'org_id', 'granted_by', 'granted_at', 'is_active', 'created_at', 'updated_at'],
        'plan_crosswalk' => ['id', 'org_id', 'quote_id', 'project_id', 'plan_line_code'],
        'user_org_roles' => ['user_id', 'org_id', 'is_active'],
        'projects' => ['id', 'org_id', 'name', 'status', 'address', 'bid_due_at', 'created_by', 'created_at', 'updated_at', 'deleted_at'],
    ];

    private const REPORT_HEADERS = [
        'groups' => ['project_id', 'action', 'org_id', 'org_resolution', 'owner_user_id', 'key_source', 'group_key', 'project_name', 'address', 'created_at', 'deleted_at', 'quote_count', 'trashed_quote_count', 'quote_ids', 'quote_numbers'],
        'unresolved' => ['quote_id', 'quote_number', 'owner_user_id', 'reason', 'owner_active_org_ids', 'legacy_member_org_ids'],
        'memberships' => ['project_id', 'user_id', 'org_id', 'action', 'is_owner', 'member_active_in_org', 'source_row_ids', 'granted_by', 'granted_at'],
        'membership_widening' => ['project_id', 'user_id', 'gained_quote_ids'],
        'membership_rehomed' => ['legacy_row_id', 'quote_id', 'user_id', 'legacy_org_id', 'legacy_is_active', 'project_id', 'project_org_id'],
        'membership_org_mismatch' => ['legacy_row_id', 'quote_id', 'user_id', 'legacy_org_id', 'legacy_is_active', 'project_id', 'project_org_id', 'user_active_org_ids'],
        'crosswalk_conflicts' => ['crosswalk_id', 'quote_id', 'project_id', 'plan_line_code', 'org_id', 'project_org_id', 'type', 'linked'],
        'address_conflicts' => ['project_id', 'chosen_address', 'quote_id', 'quote_address'],
        'candidate_merges' => ['org_id', 'owner_user_id', 'normalized_name', 'quote_ids'],
        'warnings' => ['source', 'line_or_quote_id', 'message'],
        'errors' => ['owner_user_id', 'group_key', 'quote_ids', 'exception_class', 'message'],
    ];

    /** @var array<string, resource> */
    private array $handles = [];

    private array $stats = [];

    private array $overrides = [];

    private string $reportDir = '';

    private bool $aborted = false;

    public function handle(): int
    {
        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');

        $problems = $this->schemaProblems();
        if ($problems !== []) {
            $this->error('schema incomplete; follow B5/B11');
            foreach ($problems as $problem) {
                $this->line('  '.$problem);
            }

            return 2;
        }

        $rawOverrides = null;
        $overridesHash = null;
        $path = $this->option('overrides');
        if ($path !== null && $path !== '') {
            $rawOverrides = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
            if ($rawOverrides === false) {
                $this->error('overrides file is not readable');

                return 2;
            }
            $overridesHash = hash('sha256', $rawOverrides);

            [$this->overrides, $errors] = $this->parseOverrides($rawOverrides);
            if ($errors === []) {
                $errors = $this->prePassErrors();
            }
            if ($errors !== []) {
                usort($errors, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
                $this->error('overrides file rejected; nothing written');
                foreach ($errors as [$line, $code]) {
                    $this->line("  line {$line}: {$code}");
                }

                return 2;
            }
        }

        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('This runs against PRODUCTION. Note that --dry-run holds row locks for the whole run. Continue?', false)) {
                $this->error('aborted; nothing written');

                return 2;
            }
        }

        $maintenance = app()->isDownForMaintenance();
        if (! $dry && ! $maintenance && ! $this->option('allow-live')) {
            $this->error('a real run requires maintenance mode (php artisan down) or --allow-live; nothing written');

            return 2;
        }
        if ($dry && ! $maintenance) {
            $this->warn('the application is live; a dry run holds row locks until it finishes');
        }

        $this->stats = array_fill_keys([
            'owners', 'scanned', 'created', 'reused', 'attached', 'ins_active', 'ins_inactive',
            'rehomed', 'mismatch', 'widening', 'cw_linked', 'cw_dup', 'cw_mismatch',
            'addr_conflicts', 'candidates', 'unresolved', 'errors', 'warnings',
        ], 0);

        $runAt = now()->format('Y-m-d H:i:s');
        $before = $dry ? $this->counts() : null;

        $this->openReports($dry, $rawOverrides);

        $verifyFailed = false;
        if ($dry) {
            DB::beginTransaction();
        }
        try {
            $this->processOwners($runAt);
        } catch (Throwable $e) {
            $this->aborted = true;
            $this->stats['errors']++;
            $this->put('errors', ['', '', '', get_class($e), $this->text($e->getMessage())]);
        } finally {
            if ($dry) {
                try {
                    DB::rollBack();
                } catch (Throwable) {
                    $verifyFailed = true;
                }
            }
        }

        if ($dry) {
            try {
                $verifyFailed = $verifyFailed || $this->counts() !== $before;
            } catch (Throwable) {
                $verifyFailed = true;
            }
        }

        $exit = $verifyFailed ? 3 : (($this->stats['unresolved'] > 0 || $this->stats['errors'] > 0 || $this->aborted) ? 1 : 0);

        $lines = $this->summaryLines($dry, $maintenance, $overridesHash, $started, $exit);
        foreach ($lines as $line) {
            $this->line($line);
            fwrite($this->handles['summary'], $line."\n");
        }
        foreach ($this->handles as $h) {
            fclose($h);
        }
        $this->handles = [];

        return $exit;
    }

    private function schemaProblems(): array
    {
        $problems = [];
        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $problems[] = "table {$table} missing";

                continue;
            }
            $found = [];
            foreach (Schema::getColumns($table) as $col) {
                $found[$col['name']] = (bool) ($col['nullable'] ?? false);
            }
            foreach ($columns as $column) {
                if (! array_key_exists($column, $found)) {
                    $problems[] = "{$table}.{$column} missing";
                }
            }
            if (in_array($table, ['project_members', 'plan_crosswalk'], true) && array_key_exists('quote_id', $found) && ! $found['quote_id']) {
                $problems[] = "{$table}.quote_id must be nullable";
            }
        }

        return $problems;
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{0: int, 1: string}>}
     */
    private function parseOverrides(string $raw): array
    {
        $errors = [];
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $h = fopen('php://memory', 'r+');
        fwrite($h, $raw);
        rewind($h);

        $header = fgetcsv($h, 0, ',', '"', '');
        if ($header === false || $header !== self::OVERRIDES_HEADER) {
            fclose($h);

            return [[], [[1, 'missing_or_invalid_header']]];
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null]) {
                continue;
            }
            if (count($cells) !== 4) {
                $errors[] = [$line, 'bad_column_count'];

                continue;
            }

            $trimmed = [];
            $bad = false;
            foreach ($cells as $cell) {
                $t = ProjectBackfillPlanner::trimText($cell);
                if ($t === null) {
                    $errors[] = [$line, 'invalid_utf8'];
                    $bad = true;
                    break;
                }
                if ($cell !== '' && $t === '') {
                    $errors[] = [$line, 'blank_cell'];
                    $bad = true;
                    break;
                }
                $trimmed[] = $t;
            }
            if ($bad) {
                continue;
            }
            [$quoteId, $orgId, $key, $name] = $trimmed;

            if ($quoteId === '' || ! ctype_digit($quoteId) || (int) $quoteId < 1) {
                $errors[] = [$line, 'invalid_quote_id'];

                continue;
            }
            if ($orgId !== '' && (! ctype_digit($orgId) || (int) $orgId < 1)) {
                $errors[] = [$line, 'invalid_org_id'];

                continue;
            }
            if ($orgId === '' && $key === '' && $name === '') {
                $errors[] = [$line, 'empty_override_row'];

                continue;
            }
            if (isset($rows[(int) $quoteId])) {
                $errors[] = [$line, 'duplicate_quote_id'];

                continue;
            }

            $rows[(int) $quoteId] = [
                'line' => $line,
                'org_id' => $orgId === '' ? null : (int) $orgId,
                'project_key' => $key,
                'key_norm' => ProjectBackfillPlanner::normalize($key),
                'project_name' => $name,
                'attached' => false,
            ];
        }
        fclose($h);

        foreach (array_chunk(array_keys($rows), self::CHUNK) as $chunk) {
            $found = [];
            foreach (DB::table('quotes')->whereIn('id', $chunk)->get(['id', 'user_id', 'project_id']) as $q) {
                $found[(int) $q->id] = $q;
            }
            foreach ($chunk as $id) {
                if (! isset($found[$id])) {
                    $errors[] = [$rows[$id]['line'], 'unknown_quote'];

                    continue;
                }
                $rows[$id]['attached'] = $found[$id]->project_id !== null;
                if (! $rows[$id]['attached'] && $rows[$id]['org_id'] !== null) {
                    $hasRole = DB::table('user_org_roles')
                        ->where('user_id', $found[$id]->user_id)
                        ->where('org_id', $rows[$id]['org_id'])
                        ->exists();
                    if (! $hasRole) {
                        $errors[] = [$rows[$id]['line'], 'org_without_role'];
                    }
                }
            }
        }

        return [$rows, $errors];
    }

    private function prePassErrors(): array
    {
        $planner = new ProjectBackfillPlanner();
        $runAt = now()->format('Y-m-d H:i:s');
        $errors = [];
        foreach ($this->ownerIds() as $ownerId) {
            $plan = $planner->plan($this->loadContext($ownerId, $runAt));
            foreach ($plan['fatal'] as $fatal) {
                $errors[] = $fatal;
            }
        }

        return $errors;
    }

    private function ownerIds(): array
    {
        return array_map('intval', DB::table('quotes')->whereNull('project_id')->distinct()->orderBy('user_id')->pluck('user_id')->all());
    }

    private function counts(): array
    {
        return [
            'projects' => DB::table('projects')->count(),
            'project_members' => DB::table('project_members')->count(),
            'quotes' => DB::table('quotes')->whereNotNull('project_id')->count(),
            'plan_crosswalk' => DB::table('plan_crosswalk')->whereNotNull('project_id')->count(),
        ];
    }

    private function loadContext(int $ownerId, string $runAt): array
    {
        $quotes = [];
        $rows = DB::table('quotes')
            ->where('user_id', $ownerId)
            ->whereNull('project_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'name', 'project_name', 'project_address', 'quote_number', 'created_at', 'updated_at', 'deleted_at']);
        foreach ($rows as $r) {
            $r = (array) $r;
            $r['id'] = (int) $r['id'];
            $r['user_id'] = (int) $r['user_id'];
            $quotes[] = $r;
        }
        unset($rows);
        $quoteIds = array_map(fn ($q) => $q['id'], $quotes);

        $activeOrgIds = array_map('intval', DB::table('user_org_roles')
            ->where('user_id', $ownerId)
            ->where('is_active', true)
            ->distinct()
            ->orderBy('org_id')
            ->pluck('org_id')
            ->all());

        $legacy = [];
        $crosswalk = [];
        foreach (array_chunk($quoteIds, self::CHUNK) as $chunk) {
            $members = DB::table('project_members')
                ->whereIn('quote_id', $chunk)
                ->whereNull('project_id')
                ->orderBy('id')
                ->get(['id', 'quote_id', 'user_id', 'org_id', 'granted_by', 'granted_at', 'is_active']);
            foreach ($members as $m) {
                $m = (array) $m;
                $legacy[] = [
                    'id' => (int) $m['id'],
                    'quote_id' => (int) $m['quote_id'],
                    'user_id' => (int) $m['user_id'],
                    'org_id' => (int) $m['org_id'],
                    'granted_by' => $m['granted_by'] === null ? null : (int) $m['granted_by'],
                    'granted_at' => $m['granted_at'],
                    'is_active' => (bool) $m['is_active'],
                ];
            }

            $cw = DB::table('plan_crosswalk')
                ->whereIn('quote_id', $chunk)
                ->whereNull('project_id')
                ->orderBy('id')
                ->get(['id', 'org_id', 'quote_id', 'plan_line_code']);
            foreach ($cw as $c) {
                $c = (array) $c;
                $crosswalk[] = [
                    'id' => (int) $c['id'],
                    'org_id' => (int) $c['org_id'],
                    'quote_id' => (int) $c['quote_id'],
                    'plan_line_code' => $c['plan_line_code'],
                ];
            }
        }

        $attached = [];
        $attRows = DB::table('quotes')
            ->join('projects', 'projects.id', '=', 'quotes.project_id')
            ->where('quotes.user_id', $ownerId)
            ->orderBy('quotes.id')
            ->get([
                'quotes.id as quote_id', 'quotes.name', 'quotes.project_name',
                'projects.id as project_id', 'projects.org_id as project_org_id',
                'projects.created_by as project_created_by', 'projects.deleted_at as project_deleted_at',
                'projects.name as project_title', 'projects.address as project_address',
                'projects.created_at as project_created_at',
            ]);
        foreach ($attRows as $a) {
            $a = (array) $a;
            $a['quote_id'] = (int) $a['quote_id'];
            $a['project_id'] = (int) $a['project_id'];
            $a['project_org_id'] = (int) $a['project_org_id'];
            $attached[] = $a;
        }
        unset($attRows);

        $userIds = array_values(array_unique(array_filter(
            array_map(fn ($m) => $m['user_id'], $legacy),
            fn ($id) => $id !== $ownerId
        )));
        $userActiveOrgs = [];
        foreach (array_chunk($userIds, self::CHUNK) as $chunk) {
            $roles = DB::table('user_org_roles')
                ->whereIn('user_id', $chunk)
                ->where('is_active', true)
                ->distinct()
                ->get(['user_id', 'org_id']);
            foreach ($roles as $r) {
                $userActiveOrgs[(int) $r->user_id][] = (int) $r->org_id;
            }
        }

        return [
            'owner_id' => $ownerId,
            'quotes' => $quotes,
            'active_org_ids' => $activeOrgIds,
            'legacy_members' => $legacy,
            'crosswalk' => $crosswalk,
            'attached' => $attached,
            'user_active_orgs' => $userActiveOrgs,
            'overrides' => $this->overrides,
            'run_at' => $runAt,
            'existing_members' => fn (int $projectId) => DB::table('project_members')->where('project_id', $projectId)->pluck('user_id')->all(),
            'existing_codes' => fn (int $projectId) => DB::table('plan_crosswalk')->where('project_id', $projectId)->pluck('plan_line_code')->all(),
        ];
    }

    private function processOwners(string $runAt): void
    {
        $planner = new ProjectBackfillPlanner();

        foreach ($this->ownerIds() as $ownerId) {
            if ($this->aborted) {
                return;
            }

            $ctx = $this->loadContext($ownerId, $runAt);
            $plan = $planner->plan($ctx);

            $this->stats['owners']++;
            $this->stats['scanned'] += count($ctx['quotes']);

            foreach ($plan['warnings'] as [$source, $ref, $message]) {
                $this->stats['warnings']++;
                $this->put('warnings', [$source, $ref, $this->text($message)]);
            }
            foreach ($plan['candidate_merges'] as $c) {
                $this->stats['candidates']++;
                $this->put('candidate_merges', [$c['org_id'], $c['owner_user_id'], $this->text($c['normalized_name']), implode(' ', $c['quote_ids'])]);
            }
            foreach ($plan['unresolved'] as $u) {
                $this->writeUnresolved($u);
            }

            foreach ($plan['groups'] as $g) {
                $this->writeGroup($g, $runAt);
                if ($this->aborted) {
                    break;
                }
            }

            unset($ctx, $plan);
        }
    }

    private function writeUnresolved(array $u): void
    {
        $this->stats['unresolved']++;
        $this->put('unresolved', [
            $u['quote_id'],
            $this->text($u['quote_number']),
            $u['owner_user_id'],
            $u['reason'],
            implode(' ', $u['owner_active_org_ids']),
            implode(' ', $u['legacy_member_org_ids']),
        ]);
    }

    private function writeGroup(array $g, string $runAt): void
    {
        $qids = array_map(fn ($q) => $q['id'], $g['quotes']);
        sort($qids);

        try {
            $projectId = DB::transaction(fn () => $this->commitGroup($g, $qids, $runAt));
        } catch (Throwable $e) {
            $this->stats['errors']++;
            $this->put('errors', [$g['owner_id'], $this->text($g['group_key']), implode(' ', $qids), get_class($e), $this->text($e->getMessage())]);
            if ($this->lostConnection($e)) {
                $this->aborted = true;
            }

            return;
        }

        if ($projectId === null) {
            foreach ($g['quotes'] as $q) {
                $this->writeUnresolved([
                    'quote_id' => $q['id'],
                    'quote_number' => $q['quote_number'],
                    'owner_user_id' => $g['owner_id'],
                    'reason' => 'concurrent_change',
                    'owner_active_org_ids' => $g['owner_active_org_ids'],
                    'legacy_member_org_ids' => $g['legacy_org_ids'][$q['id']] ?? [],
                ]);
            }

            return;
        }

        $created = $g['action'] === 'create';
        $this->stats[$created ? 'created' : 'reused']++;
        $this->stats['attached'] += count($qids);

        $trashed = count(array_filter($g['quotes'], fn ($q) => $q['deleted_at'] !== null));
        $this->put('groups', [
            $projectId,
            $g['action'] === 'create' ? 'created' : 'reused',
            $g['org_id'],
            $g['org_resolution'],
            $g['owner_id'],
            $g['key_source'],
            $this->text($g['group_key']),
            $this->text($g['project']['name']),
            $this->text($g['project']['address']),
            $g['project']['created_at'],
            $g['project']['deleted_at'],
            count($qids),
            $trashed,
            implode(' ', $qids),
            $this->text(implode(' ', array_map(fn ($q) => $q['quote_number'], $g['quotes']))),
        ]);

        foreach ($g['members'] as $m) {
            if ($m['action'] === 'inserted_active') {
                $this->stats['ins_active']++;
            } elseif ($m['action'] !== 'existing_untouched') {
                $this->stats['ins_inactive']++;
            }
            $this->put('memberships', [
                $projectId,
                $m['user_id'],
                $m['org_id'],
                $m['action'],
                $m['is_owner'] ? 'yes' : 'no',
                $m['member_active_in_org'] ? 'yes' : 'no',
                implode(' ', $m['source_row_ids']),
                $m['granted_by'],
                $m['granted_at'],
            ]);
        }

        foreach ($g['widening'] as $w) {
            $this->stats['widening']++;
            $this->put('membership_widening', [$projectId, $w['user_id'], implode(' ', $w['gained_quote_ids'])]);
        }

        foreach ($g['rehomed'] as $r) {
            $this->stats['rehomed']++;
            $row = $r['row'];
            $this->put('membership_rehomed', [$row['id'], $row['quote_id'], $row['user_id'], $row['org_id'], (int) $row['is_active'], $projectId, $r['project_org_id']]);
        }

        foreach ($g['mismatch'] as $r) {
            $this->stats['mismatch']++;
            $row = $r['row'];
            $this->put('membership_org_mismatch', [$row['id'], $row['quote_id'], $row['user_id'], $row['org_id'], (int) $row['is_active'], $projectId, $r['project_org_id'], implode(' ', $r['user_active_org_ids'])]);
        }

        $this->stats['cw_linked'] += count($g['crosswalk']['link_ids']);
        foreach ($g['crosswalk']['conflicts'] as $c) {
            $this->stats[$c['type'] === 'duplicate_code' ? 'cw_dup' : 'cw_mismatch']++;
            $row = $c['row'];
            $this->put('crosswalk_conflicts', [
                $row['id'],
                $row['quote_id'],
                $projectId,
                $this->text($row['plan_line_code']),
                $row['org_id'],
                $g['org_id'],
                $c['type'],
                $c['linked'] ? 'yes' : 'no',
            ]);
        }

        foreach ($g['address_conflicts'] as $a) {
            $this->stats['addr_conflicts']++;
            $this->put('address_conflicts', [$projectId, $this->text($a['chosen_address']), $a['quote_id'], $this->text($a['quote_address'])]);
        }
    }

    private function commitGroup(array $g, array $qids, string $runAt): ?int
    {
        $locked = [];
        foreach (array_chunk($qids, self::CHUNK) as $chunk) {
            foreach (DB::table('quotes')->whereIn('id', $chunk)->whereNull('project_id')->lockForUpdate()->pluck('id') as $id) {
                $locked[] = (int) $id;
            }
        }
        sort($locked);
        if ($locked !== $qids) {
            return null;
        }

        if ($g['action'] === 'create') {
            $p = $g['project'];
            $projectId = (int) DB::table('projects')->insertGetId([
                'org_id' => $p['org_id'],
                'name' => $p['name'],
                'status' => $p['status'],
                'address' => $p['address'],
                'bid_due_at' => $p['bid_due_at'],
                'created_by' => $p['created_by'],
                'created_at' => $p['created_at'],
                'updated_at' => $p['updated_at'],
                'deleted_at' => $p['deleted_at'],
            ]);
        } else {
            $projectId = (int) $g['project_id'];
        }

        foreach (array_chunk($qids, self::CHUNK) as $chunk) {
            DB::table('quotes')->whereIn('id', $chunk)->whereNull('project_id')->update(['project_id' => $projectId]);
        }

        $inserts = [];
        foreach ($g['members'] as $m) {
            if ($m['action'] === 'existing_untouched') {
                continue;
            }
            $inserts[] = [
                'project_id' => $projectId,
                'user_id' => $m['user_id'],
                'org_id' => $m['org_id'],
                'granted_by' => $m['granted_by'],
                'granted_at' => $m['granted_at'],
                'is_active' => (int) $m['is_active'],
                'created_at' => $runAt,
                'updated_at' => $runAt,
            ];
        }
        foreach (array_chunk($inserts, 50) as $chunk) {
            DB::table('project_members')->insert($chunk);
        }

        foreach (array_chunk($g['crosswalk']['link_ids'], self::CHUNK) as $chunk) {
            DB::table('plan_crosswalk')->whereIn('id', $chunk)->whereNull('project_id')->update(['project_id' => $projectId]);
        }

        return $projectId;
    }

    private function lostConnection(Throwable $e): bool
    {
        return (new class
        {
            use DetectsLostConnections;

            public function check(Throwable $e): bool
            {
                return $this->causedByLostConnection($e);
            }
        })->check($e);
    }

    private function openReports(bool $dry, ?string $rawOverrides): void
    {
        $previous = umask(0077);
        try {
            $base = storage_path('app/backfill/projects-entity');
            $name = now('UTC')->format('Ymd_His').($dry ? '-dry-run' : '');
            $dir = $base.'/'.$name;
            for ($n = 2; file_exists($dir); $n++) {
                $dir = $base.'/'.$name.'-'.$n;
            }
            mkdir($dir, 0700, true);
            chmod($dir, 0700);
            $this->reportDir = $dir;

            foreach (self::REPORT_HEADERS as $file => $header) {
                $this->handles[$file] = $this->openFile($dir.'/'.$file.'.csv');
                $this->put($file, $header);
            }
            $this->handles['summary'] = $this->openFile($dir.'/summary.txt');

            if ($rawOverrides !== null) {
                $copy = $this->openFile($dir.'/overrides.csv');
                fwrite($copy, $rawOverrides);
                fclose($copy);
            }
        } finally {
            umask($previous);
        }
    }

    /** @return resource */
    private function openFile(string $path)
    {
        $h = fopen($path, 'w');
        chmod($path, 0600);

        return $h;
    }

    private function put(string $file, array $row): void
    {
        fputcsv($this->handles[$file], $row, ',', '"', '');
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = (string) $value;
        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    private function summaryLines(bool $dry, bool $maintenance, ?string $overridesHash, float $started, int $exit): array
    {
        $s = $this->stats;
        $lines = [
            'mode: '.($dry ? 'dry-run' : 'real'),
            "quotes scanned: {$s['scanned']}",
            "owners: {$s['owners']}",
            "projects created: {$s['created']}",
            "projects reused: {$s['reused']}",
            "quotes attached: {$s['attached']}",
            'members inserted: '.($s['ins_active'] + $s['ins_inactive'])." (active {$s['ins_active']}, inactive {$s['ins_inactive']})",
            "membership rehomed: {$s['rehomed']}",
            "membership org mismatch: {$s['mismatch']}",
            "widening users: {$s['widening']}",
            "crosswalk linked: {$s['cw_linked']}",
            "crosswalk duplicate codes: {$s['cw_dup']}",
            "crosswalk org mismatches: {$s['cw_mismatch']}",
            "unlinked crosswalk rows (org_mismatch): {$s['cw_mismatch']}",
            "address conflicts: {$s['addr_conflicts']}",
            "candidate merges: {$s['candidates']}",
            "unresolved quotes: {$s['unresolved']}",
            "errors: {$s['errors']}",
            "warnings: {$s['warnings']}",
            'maintenance mode: '.($maintenance ? 'yes' : 'no'),
            'overrides sha256: '.($overridesHash ?? 'none'),
            'report directory: '.$this->reportDir,
            'duration: '.round(microtime(true) - $started, 2).'s',
            'peak memory: '.round(memory_get_peak_usage(true) / 1048576, 1).' MB',
        ];
        if ($dry) {
            $lines[] = 'dry-run: project_id values in the reports were assigned inside the rolled-back transaction and are discarded';
        }
        if ($exit === 3) {
            $lines[] = 'CRITICAL: dry-run verification failed; database counts changed';
        }
        $lines[] = "exit code: {$exit}";

        return $lines;
    }
}

<?php

namespace Tests\Feature\Rbac;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Phase 5 guard: quotes must never again be read by `user_id` as a visibility scope. The only places that may name
 * `quotes.user_id` are Quote::scopeVisibleTo (author narrowing inside a project scope), destroyUser (refusal), the
 * inert projects:backfill command (kept 30 days after 5b, then deleted) and writes (Quote::create).
 *
 * Text scan of app/ and resources/: it sees statements that start with `Quote::` or `DB::table('quotes')` and the
 * literal `quotes.user_id`. It cannot see a Quote builder stored in a variable and filtered in a later statement;
 * that gap is stated in REVIEW.md, and the behavioural tests (ProjectReadPathsTest, ProjectDashboardCountersTest) cover it.
 */
class QuoteAuthorScopeGuardTest extends TestCase
{
    private const STATEMENT = '/(?:\\\\?App\\\\Models\\\\)?\bQuote::[^;]*?user_id[^;]*;|DB::table\([\'"]quotes[\'"]\)[^;]*?user_id[^;]*;|quotes\.user_id[^;]*;/s';

    /** @return list<string> normalised offending statements */
    private static function scan(string $code): array
    {
        preg_match_all(self::STATEMENT, $code, $m);

        return array_values(array_filter(
            array_map(fn ($s) => preg_replace('/\s+/', ' ', $s), $m[0]),
            fn ($s) => ! preg_match('/^(?:\\\\?App\\\\Models\\\\)?Quote::(?:create|forceCreate|insert)\(/', $s),
        ));
    }

    /** @return array<string, string> relative path => contents */
    private static function sources(): array
    {
        $out = [];
        foreach (['app', 'resources'] as $root) {
            $base = dirname(__DIR__, 3);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$base/$root", FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (preg_match('/\.php$/', (string) $file)) {
                    $out[substr((string) $file, strlen($base) + 1)] = file_get_contents((string) $file);
                }
            }
        }
        ksort($out);

        return $out;
    }

    public function test_the_scanner_flags_a_user_id_read_and_ignores_writes_and_the_scope(): void
    {
        $this->assertNotEmpty(self::scan("\$n = Quote::where('user_id', \$id)->count();"));
        $this->assertNotEmpty(self::scan("\$n = \\App\\Models\\Quote::query()\n    ->where('status', 'x')\n    ->where(\"user_id\", \$id)->get();"));
        $this->assertNotEmpty(self::scan("Quote::withTrashed()->where('user_id', 1)->exists();"));
        $this->assertNotEmpty(self::scan("DB::table('quotes')->where('user_id', 1)->count();"));
        $this->assertNotEmpty(self::scan("\$q->where('quotes.user_id', 1);"));
        $this->assertNotEmpty(self::scan("Quote::whereIn('project_id', \$ids)->orWhere('user_id', 1)->get();"));
        $this->assertSame([], self::scan("Quote::create(['user_id' => Auth::id(), 'name' => 'x']);"));
        $this->assertSame([], self::scan("Quote::visibleTo(\$userId, \$orgId, true)->count();"));
        $this->assertSame([], self::scan("Quote::where('project_id', 1)->count();"));
    }

    public function test_no_code_under_app_or_resources_reads_quotes_by_user_id_outside_the_allowlist(): void
    {
        $allowedFiles = [
            'app/Models/Quote.php' => 1,
            'app/Console/Commands/BackfillProjects.php' => null,
        ];
        $allowedStatements = [
            'app/Http/Controllers/Admin/RbacController.php' => ["Quote::withTrashed()->where('user_id', \$user->id)->exists()"],
        ];

        $offences = [];
        foreach (self::sources() as $path => $code) {
            $found = self::scan($code);
            if (array_key_exists($path, $allowedFiles)) {
                if ($allowedFiles[$path] !== null) {
                    $this->assertCount($allowedFiles[$path], $found, "$path: the allowed number of user_id mentions changed");
                }

                continue;
            }
            foreach ($found as $statement) {
                $ok = false;
                foreach ($allowedStatements[$path] ?? [] as $prefix) {
                    $ok = $ok || str_starts_with($statement, $prefix);
                }
                if (! $ok) {
                    $offences[] = "$path: ".substr($statement, 0, 140);
                }
            }
        }

        $this->assertSame([], $offences, "quotes must be scoped by Quote::visibleTo, not by user_id:\n".implode("\n", $offences));
    }

    public function test_the_author_is_only_compared_where_the_plan_names_it(): void
    {
        $hits = [];
        foreach (self::sources() as $path => $code) {
            if (preg_match_all('/\$(?:quote|originalQuote|q)->user_id/', $code, $m)) {
                $hits[$path] = count($m[0]);
            }
        }

        $this->assertSame(['app/Http/Controllers/Frontend/QuoteController.php' => 2], $hits, 'staff_notes (owner only) and the PDF write (owner only) are the two author comparisons');
    }

    public function test_the_user_model_has_no_quotes_relation_that_could_bypass_the_scope(): void
    {
        $user = file_get_contents(dirname(__DIR__, 3).'/app/Models/User.php');

        $this->assertDoesNotMatchRegularExpression('/function\s+quotes\s*\(/', $user);
    }
}

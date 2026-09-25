<?php

namespace Tests\Feature\Rbac;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 5 amendment 2 A5: the dashboard quote counter, status chart and recent list are the quotes-index scope
 * (Quote::visibleTo for the current org) instead of the Phase 3 H8 owner-only rule.
 *
 * Fixture (see ProjectTestCase): P1 (org A) members est, viewer (estimate_management R only), super (no estimate_management),
 * multi (org A row), owner; P2 (org A) owner only; P3 (org B) outsider. q1 and q2 are owner's quotes in P1.
 */
class ProjectDashboardCountersTest extends ProjectTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->decimal('total', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('saved_list_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->timestamps();
        });
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($d, $f) => date(str_replace(['%Y', '%m'], ['Y', 'm'], $f), strtotime($d)), 2);

        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function orgSession(int $orgId): array
    {
        return [config('rbac.current_org_session_key') => $orgId];
    }

    /**
     * @return array{count:int, recent:int[], chart:int[], html:string, view:int}
     */
    private function dash(User $u, array $session = []): array
    {
        $r = $this->actingAs($u)->withSession($session)->get('user-dashboard')->assertOk();
        $html = $r->getContent();
        $this->assertSame(1, preg_match('#<span class="fw-semibold">(\d+)</span> Quotes#', $html, $m));
        $this->assertSame(1, preg_match('#var chartQuoteOpts = \{\s*series: \[(\d+),(\d+),(\d+)\]#', $html, $c));
        $recent = $r->viewData('recentQuotes')->pluck('id')->map(fn ($v) => (int) $v)->all();
        sort($recent);

        return ['count' => (int) $m[1], 'recent' => $recent, 'chart' => [(int) $c[1], (int) $c[2], (int) $c[3]], 'html' => $html, 'view' => (int) $r->viewData('quotesCount')];
    }

    /** the quotes-index total for the same user and org context, read in audit mode so a role-less member is not blocked */
    private function indexTotal(User $u, array $session = []): int
    {
        $before = \App\Models\Rbac\RbacSetting::get('rbac_mode');
        $this->setMode('audit');
        $total = (int) $this->actingAs($u)->withSession($session)->json('GET', 'quotes/list', [])->assertOk()->json('pagination.total');
        $this->setMode($before ?: 'audit');

        return $total;
    }

    private function assertDashboard(User $u, array $ids, ?array $chart = null, array $session = [], string $label = ''): array
    {
        sort($ids);
        $d = $this->dash($u, $session);

        $this->assertSame($ids, $d['recent'], "$label recent quotes");
        $this->assertSame(count($ids), $d['count'], "$label counter");
        $this->assertSame($d['count'], $d['view'], "$label view var");
        $this->assertSame($d['count'], $this->indexTotal($u, $session), "$label counter equals the quotes index");
        if ($chart !== null) {
            $this->assertSame($chart, $d['chart'], "$label status chart");
        }
        $this->assertSame($d['count'], array_sum($d['chart']), "$label chart adds up to the counter (all fixture statuses are draft/sent/completed)");

        return $d;
    }

    private function q(User $u, ?int $project, string $status = 'draft', ?string $number = null): int
    {
        return $this->mkQuote($u, $project, ['status' => $status] + ($number ? ['quote_number' => $number] : []));
    }

    // ---- 1. member with R sees every quote of the member projects, any author -----

    #[DataProvider('modes')]
    public function test_a_member_with_read_sees_every_quote_in_member_projects_from_any_author(string $mode): void
    {
        $this->setMode($mode);
        $own = $this->q($this->viewer, $this->p1, 'sent', 'V-OWN');
        $this->q($this->outsider, $this->p3, 'draft', 'HIDDEN-P3');   // other org

        $d = $this->assertDashboard($this->viewer, [$this->q1, $this->q2, $own], [2, 1, 0], [], "R/$mode/not in P2");
        $this->assertNotContains($this->q3, $d["recent"], "viewer is not a member of P2");

        $this->member($this->p2, $this->viewer, $this->orgA);
        $inP2 = $this->q($this->owner, $this->p2, 'completed', 'P2-VISIBLE');

        $d = $this->assertDashboard($this->viewer, [$this->q1, $this->q2, $this->q3, $own, $inP2], [3, 1, 1], [], "R/$mode/in P2");
        $this->assertStringContainsString('V-OWN', $d['html']);
        $this->assertStringContainsString('P2-VISIBLE', $d['html']);
        $this->assertStringNotContainsString('HIDDEN-P3', $d['html']);
    }

    public function test_recent_quotes_is_limited_to_five_of_the_visible_set_and_the_counter_is_not(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->q($this->owner, $this->p1, 'draft', "MANY-$i");
        }

        $d = $this->dash($this->viewer);

        $this->assertSame(8, $d['count'], 'q1, q2 and six more');
        $this->assertCount(5, $d['recent']);
        $this->assertSame(8, $this->indexTotal($this->viewer));
    }

    // ---- 2. member without R sees only quotes they authored inside member projects --

    #[DataProvider('modes')]
    public function test_a_member_without_read_sees_only_own_quotes_inside_member_projects(string $mode): void
    {
        $this->setMode($mode);
        $own = $this->q($this->super, $this->p1, 'sent', 'S-OWN');
        $this->q($this->super, $this->p2, 'draft', 'S-P2');           // authored, but super is not in P2
        $this->q($this->super, null, 'draft', 'S-NULL');              // authored, no project
        $this->q($this->est, $this->p1, 'draft', 'TEAMMATE');          // teammate in the same project

        $svc = app(\App\Services\Rbac\PermissionService::class);
        $this->assertFalse($svc->checkPermission($this->super->id, $this->orgA->id, 'estimate_management', 'R'), 'fixture: super has no read');

        $d = $this->assertDashboard($this->super, [$own], [0, 1, 0], [], "noR/$mode");

        foreach (['QT-2', 'QT-3', 'S-P2', 'S-NULL', 'TEAMMATE'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $d['html'], "$hidden must not be on the dashboard");
        }
        $this->assertStringContainsString('S-OWN', $d['html']);
    }

    #[DataProvider('modes')]
    public function test_a_member_without_read_and_without_own_quotes_gets_zero(string $mode): void
    {
        $this->setMode($mode);

        $this->assertDashboard($this->super, [], [0, 0, 0], [], "noR-none/$mode");
    }

    // ---- 3. removed from the project or project trashed: nothing, with or without R --

    /** @return array<string, array{0:string,1:string}> */
    public static function removedCases(): array
    {
        return [
            'R, membership deactivated' => ['est', 'deactivate'],
            'R, membership deleted' => ['est', 'delete'],
            'no R, membership deactivated' => ['super', 'deactivate'],
            'no R, membership deleted' => ['super', 'delete'],
        ];
    }

    #[DataProvider('removedCases')]
    public function test_an_author_removed_from_the_project_no_longer_sees_their_quote(string $who, string $how): void
    {
        $u = $this->$who;
        $own = $this->q($u, $this->p1, 'draft', 'GONE-OWN');
        $this->assertDashboard($u, $who === 'est' ? [$this->q1, $this->q2, $own] : [$own], null, [], 'before');

        $rows = DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $u->id);
        $how === 'delete' ? $rows->delete() : $rows->update(['is_active' => false]);

        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $d = $this->dash($u);
            $this->assertSame(0, $d['count'], "$mode counter");
            $this->assertSame([], $d['recent'], "$mode recent");
            $this->assertSame([0, 0, 0], $d['chart'], "$mode chart");
            $this->assertStringNotContainsString('GONE-OWN', $d['html']);
        }
        $this->assertSame(0, $this->indexTotal($u));
    }

    public function test_a_member_of_another_orgs_row_on_the_project_gives_nothing(): void
    {
        $u = $this->mkUser($this->orgA, 'estimator');
        $this->q($u, $this->p1);
        DB::table('project_members')->insert(['project_id' => $this->p1, 'quote_id' => null, 'user_id' => $u->id, 'org_id' => $this->orgB->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertDashboard($u, [], [0, 0, 0], [], 'org-mismatch member row');
    }

    #[DataProvider('modes')]
    public function test_a_soft_deleted_project_takes_its_quotes_off_the_dashboard_for_everybody(string $mode): void
    {
        $this->setMode($mode);
        $own = $this->q($this->est, $this->p1, 'draft', 'TRASHED-OWN');
        $ownSuper = $this->q($this->super, $this->p1, 'draft', 'TRASHED-SUPER');
        $keep = $this->q($this->owner, $this->p2, 'draft', 'P2-LIVE');
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);

        foreach ([$this->est, $this->super, $this->viewer] as $u) {
            $this->assertDashboard($u, [], [0, 0, 0], [], "trashed/{$u->id}/$mode");
        }
        $d = $this->assertDashboard($this->owner, [$this->q3, $keep], [2, 0, 0], [], "trashed/owner/$mode");
        $this->assertStringNotContainsString('TRASHED-OWN', $d['html']);
        $this->assertStringNotContainsString('TRASHED-SUPER', $d['html']);
        $this->assertNotContains($own, $d['recent']);
        $this->assertNotContains($ownSuper, $d['recent']);
    }

    // ---- 4. a NULL-project quote is never counted ---------------------------------

    #[DataProvider('modes')]
    public function test_a_null_project_quote_is_never_counted_or_listed(string $mode): void
    {
        $this->setMode($mode);
        $this->q($this->est, null, 'sent', 'NULL-EST');
        $this->q($this->super, null, 'sent', 'NULL-SUPER');
        $this->q($this->owner, null, 'sent', 'NULL-OWNER');

        $est = $this->assertDashboard($this->est, [$this->q1, $this->q2], [2, 0, 0], [], "null/est/$mode");
        $this->assertDashboard($this->super, [], [0, 0, 0], [], "null/super/$mode");
        $this->assertDashboard($this->owner, [$this->q1, $this->q2, $this->q3], [3, 0, 0], [], "null/owner/$mode");
        $this->assertStringNotContainsString('NULL-', $est['html']);
    }

    // ---- 5. another org context, or no org: nothing --------------------------------

    #[DataProvider('modes')]
    public function test_the_other_org_context_shows_zero_although_the_user_authored_quotes_in_the_first_org(string $mode): void
    {
        $this->setMode($mode);
        $own = $this->q($this->multi, $this->p1, 'sent', 'MULTI-OWN');

        $this->assertDashboard($this->multi, [$this->q1, $this->q2, $own], [2, 1, 0], $this->orgSession($this->orgA->id), "multi/A/$mode");
        $d = $this->assertDashboard($this->multi, [], [0, 0, 0], $this->orgSession($this->orgB->id), "multi/B/$mode");
        $this->assertStringNotContainsString('MULTI-OWN', $d['html']);
        $this->assertSame(0, $d['count']);
    }

    #[DataProvider('modes')]
    public function test_a_user_without_an_org_context_gets_zero_zero_zero_even_with_authored_quotes(string $mode): void
    {
        $this->setMode($mode);
        $none = $this->mkUser();
        $this->q($none, $this->p1, 'sent', 'ORGLESS-OWN');
        $this->q($none, null, 'sent', 'ORGLESS-NULL');

        $d = $this->dash($none);

        $this->assertSame(0, $d['count']);
        $this->assertSame([], $d['recent']);
        $this->assertSame([0, 0, 0], $d['chart']);
        $this->assertStringNotContainsString('ORGLESS', $d['html']);
        $this->assertSame(0, $this->indexTotal($none));
    }

    public function test_a_users_org_b_role_does_not_leak_org_a_project_quotes_into_org_b(): void
    {
        $this->q($this->outsider, $this->p3, 'sent', 'B-OWN');

        $this->assertDashboard($this->outsider, [DB::table('quotes')->where('quote_number', 'B-OWN')->value('id'), $this->q4], [1, 1, 0], [], "outsider/B");
        $this->assertDashboard($this->est, [$this->q1, $this->q2], [2, 0, 0], [], 'est/A does not see org B');
    }

    // ---- 6. orders and lists counters stay author-scoped ---------------------------

    public function test_orders_and_lists_counters_stay_author_scoped(): void
    {
        DB::table('orders')->insert([
            ['user_id' => $this->viewer->id, 'total' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->viewer->id, 'total' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->owner->id, 'total' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('saved_lists')->insert([
            ['user_id' => $this->viewer->id, 'name' => 'mine', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->owner->id, 'name' => 'theirs', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->owner->id, 'name' => 'theirs2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $html = $this->dash($this->viewer)['html'];

        $this->assertSame(1, preg_match('#<span class="fw-semibold">(\d+)</span> Orders#', $html, $o));
        $this->assertSame('2', $o[1], 'only the viewer\'s own orders');
        $this->assertSame(1, preg_match('#<span class="fw-semibold">(\d+)</span> Lists#', $html, $l));
        $this->assertSame('1', $l[1], 'only the viewer\'s own lists');
        $this->assertSame(1, preg_match('#<span class="fw-semibold">(\d+)</span> Quotes#', $html, $qm));
        $this->assertSame('2', $qm[1], 'the quote counter follows projects instead');
    }

    // ---- 7. the dashboard reads the same scope as Quote::visibleTo, org-dependent ---

    public function test_the_dashboard_recent_quotes_are_exactly_the_scope_result(): void
    {
        $this->q($this->est, $this->p1);
        $this->q($this->est, null);

        foreach ([$this->est, $this->viewer, $this->super, $this->owner, $this->multi] as $u) {
            $can = app(\App\Services\Rbac\PermissionService::class)->checkPermission($u->id, $this->orgA->id, 'estimate_management', 'R');
            $expected = Quote::visibleTo($u->id, $this->orgA->id, $can)->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();

            $this->assertSame(count($expected), $this->dash($u)['count'], "user {$u->id}");
            $this->assertSame($expected, $this->dash($u)['recent'], "user {$u->id}");
        }
    }

    public function test_recent_quotes_with_equal_created_at_are_ordered_by_id_descending(): void
    {
        $stamp = now()->addDay()->startOfSecond();
        $ids = [];
        for ($i = 0; $i < 7; $i++) {
            $ids[] = $this->mkQuote($this->owner, $this->p1, ['quote_number' => "TIE-$i", 'created_at' => $stamp, 'updated_at' => $stamp]);
        }

        $recent = $this->actingAs($this->viewer)->get('user-dashboard')->assertOk()->viewData('recentQuotes')->pluck('id')->map(fn ($v) => (int) $v)->all();

        $this->assertSame(array_slice(array_reverse($ids), 0, 5), $recent, 'newest id first among equal timestamps, exactly five');
    }
}

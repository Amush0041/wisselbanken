<?php

namespace Tests\Feature\Rbac;

use App\Models\Project;
use App\Models\Rbac\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 3 step 7 (PLAN D7, D8, D10 R1-R13): every dual-read query, the per-route allow/deny matrix
 * for the 9 renamed routes plus the workspace, and the audit-mode guarantee. Goes through the real
 * routes (web middleware, RbacAudit, controllers, views) on in-memory sqlite.
 */
class ProjectReadPathsTest extends ProjectTestCase
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
        // The dashboard view uses MySQL's DATE_FORMAT; sqlite needs a stand-in for the test.
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($d, $f) => date(str_replace(['%Y', '%m'], ['Y', 'm'], $f), strtotime($d)), 2);

        Storage::fake('public');
        $this->setMode('audit');
    }

    // ---- helpers ----------------------------------------------------------------

    private function asJson(User $u, string $method, string $uri, array $session = [], array $data = [])
    {
        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function orgSession(int $orgId): array
    {
        return [config('rbac.current_org_session_key') => $orgId];
    }

    private function item(int $quoteId, float $subtotal): void
    {
        DB::table('quote_items')->insert(['quote_id' => $quoteId, 'quantity' => 1, 'unit_price' => $subtotal, 'subtotal' => $subtotal, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function listedIds(User $u, array $session = []): array
    {
        $ids = array_column($this->asJson($u, 'GET', 'quotes/list', $session)->assertOk()->json('quotes'), 'id');
        sort($ids);

        return $ids;
    }

    private function crosswalk(int $quoteId, ?int $projectId, int $orgId, string $code, ?int $creator = null): int
    {
        return (int) DB::table('plan_crosswalk')->insertGetId([
            'org_id' => $orgId, 'quote_id' => $quoteId ?: null, 'project_id' => $projectId, 'plan_line_code' => $code,
            'created_by' => $creator ?? $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ---- per-route matrix (the 9 renamed routes plus the workspace) --------------

    public static function routes(): array
    {
        return [
            'details' => ['GET', 'quotes/{q}/details', 'R'],
            'pdf-preview' => ['GET', 'quotes/{q}/pdf-preview', 'R'],
            'pdf' => ['GET', 'quotes/{q}/pdf', 'R'],
            'duplicate' => ['POST', 'quotes/{q}/duplicate', 'O'],
            'update' => ['PUT', 'quotes/{q}', 'O'],
            'editor' => ['PUT', 'quotes/{q}/editor', 'O'],
            'update-item' => ['PUT', 'quotes/{q}/items/1', 'O'],
            'destroy' => ['DELETE', 'quotes/{q}', 'F'],
            'destroy-item' => ['DELETE', 'quotes/{q}/items/1', 'F'],
            'workspace' => ['GET', 'projects/{q}/workspace', 'R'],
        ];
    }

    private function uri(string $tpl, int $quoteId): string
    {
        return str_replace('{q}', (string) $quoteId, $tpl);
    }

    #[DataProvider('routes')]
    public function test_route_allowed_for_a_member_reaches_the_controller(string $method, string $tpl, string $level): void
    {
        $this->setMode('enforce');
        $this->item($this->q1, 10);

        $r = $this->asJson($this->est, $method, $this->uri($tpl, $this->q1));

        $this->assertSame(0, AuditLog::count());
        // Phase 4 rule 6: a member holding the level acts on a teammate's quote (the write effects are pinned in
        // ProjectWritePathsTest). The old workspace URL is a 301 to the project page.
        if ($tpl === 'projects/{q}/workspace') {
            $r->assertStatus(301);

            return;
        }
        $this->assertNotContains($r->getStatusCode(), [403, 404, 500], 'a member holding the level must reach the controller and succeed or fail validation only');
        if ($level === 'R') {
            $r->assertOk();
        }
    }

    #[DataProvider('routes')]
    public function test_route_denies_the_wrong_role(string $method, string $tpl, string $level): void
    {
        $this->setMode('enforce');
        // viewer_read_only holds R only; superintendent holds no estimate_management at all.
        $user = $level === 'R' ? $this->super : $this->viewer;

        $this->asJson($user, $method, $this->uri($tpl, $this->q1))->assertForbidden()->assertJson(['rbac_error' => true]);

        $row = AuditLog::firstOrFail();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame([$this->q1, $this->p1], [(int) $row->quote_id, (int) $row->project_id]);
        $this->assertSame($level, $row->required_level);
    }

    #[DataProvider('routes')]
    public function test_route_denies_the_wrong_org(string $method, string $tpl): void
    {
        $this->setMode('enforce');

        $this->asJson($this->outsider, $method, $this->uri($tpl, $this->q1))->assertForbidden();

        $row = AuditLog::firstOrFail();
        $this->assertSame($this->orgB->id, (int) $row->org_id);
        $this->assertSame('no_grant_or_not_project_member', $row->reason);
    }

    #[DataProvider('routes')]
    public function test_route_denies_a_missing_membership(string $method, string $tpl): void
    {
        $this->setMode('enforce');

        $this->asJson($this->stranger, $method, $this->uri($tpl, $this->q1))->assertForbidden();

        $row = AuditLog::firstOrFail();
        $this->assertSame('no_grant_or_not_project_member', $row->reason);
        $this->assertSame([$this->q1, $this->p1], [(int) $row->quote_id, (int) $row->project_id]);
    }

    #[DataProvider('routes')]
    public function test_route_denies_an_inactive_membership(string $method, string $tpl): void
    {
        $this->setMode('enforce');
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->update(['is_active' => false]);

        $this->asJson($this->est, $method, $this->uri($tpl, $this->q1))->assertForbidden();
    }

    #[DataProvider('routes')]
    public function test_route_denies_a_quote_in_a_trashed_project(string $method, string $tpl): void
    {
        $this->setMode('enforce');
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);

        $this->asJson($this->est, $method, $this->uri($tpl, $this->q1))->assertForbidden();
    }

    #[DataProvider('routes')]
    public function test_route_denies_a_legacy_quote_only_row(string $method, string $tpl): void
    {
        $this->setMode('enforce');
        $this->legacyMember($this->q1, $this->stranger, $this->orgA);

        $this->asJson($this->stranger, $method, $this->uri($tpl, $this->q1))->assertForbidden();
    }

    #[DataProvider('routes')]
    public function test_route_fails_closed_for_a_null_project_quote_even_for_its_owner(string $method, string $tpl): void
    {
        $this->setMode('enforce');

        $this->asJson($this->owner, $method, $this->uri($tpl, $this->q0))->assertForbidden();

        $row = AuditLog::firstOrFail();
        $this->assertSame('project_unresolved', $row->reason);
        $this->assertSame($this->q0, (int) $row->quote_id);
        $this->assertNull($row->project_id);
    }

    // ---- R1 list and total ------------------------------------------------------

    private function listFixture(): array
    {
        $ownNull = $this->mkQuote($this->est, null, ['name' => 'est own null']);
        $ownInP2 = $this->mkQuote($this->est, $this->p2, ['name' => 'est own in P2 (not a member)']);
        $this->member($this->p3, $this->est, $this->orgA); // row org A on a project of org B: mismatch, grants nothing
        foreach ([[$this->q0, 3], [$this->q1, 100], [$this->q2, 50], [$this->q3, 7], [$this->q4, 9], [$ownNull, 11], [$ownInP2, 5]] as [$q, $amount]) {
            $this->item($q, $amount);
        }

        return [$ownNull, $ownInP2];
    }

    public function test_r1_list_shows_only_quotes_in_visible_projects_and_the_total_matches(): void
    {
        [$ownNull, $ownInP2] = $this->listFixture();
        $expected = [$this->q1, $this->q2];
        sort($expected);

        $r = $this->asJson($this->est, 'GET', 'quotes/list')->assertOk();

        $ids = array_column($r->json('quotes'), 'id');
        sort($ids);
        $this->assertSame($expected, $ids);
        $this->assertSame('150.00', $r->json('total_amount'), '100 + 50 = the same set as the list');
        $this->assertSame(2, $r->json('pagination.total'));
        foreach ([$this->q0, $this->q3, $this->q4, $ownNull, $ownInP2] as $hidden) {
            $this->assertNotContains($hidden, $ids, "quote $hidden must not be visible: NULL project, project without membership and other org stay hidden, own or not");
        }
    }

    public function test_r1_list_is_isolated_per_org_and_follows_the_session_org(): void
    {
        $this->assertSame([$this->q4], $this->listedIds($this->outsider));
        $this->assertSame([$this->q1, $this->q2], $this->listedIds($this->multi, $this->orgSession($this->orgA->id)));
        $this->assertSame([], $this->listedIds($this->multi, $this->orgSession($this->orgB->id)), 'org B: no P1 membership and no own quotes');
        $this->assertSame([$this->q1, $this->q2], $this->listedIds($this->multi, $this->orgSession(99999)), 'stale session org falls back to the first active org');
    }

    public function test_r1_owner_sees_their_quotes_only_through_project_membership_never_a_null_project_quote(): void
    {
        $this->assertSame([$this->q1, $this->q2, $this->q3], $this->listedIds($this->owner));
        $this->assertSame([$this->q1, $this->q2, $this->q3], $this->listedIds($this->owner, $this->orgSession(99999)), 'a stale session org falls back to org A');

        DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $this->owner->id)->update(['is_active' => false]);
        $this->assertSame([$this->q1, $this->q2], $this->listedIds($this->owner), 'an author removed from P2 no longer sees their own Q3');
    }

    public function test_r1_inactive_member_and_stranger_see_nothing_of_the_project(): void
    {
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->update(['is_active' => false]);

        $this->assertSame([], $this->listedIds($this->est));
        $this->assertSame([], $this->listedIds($this->stranger));
    }

    public function test_r1_legacy_only_membership_shows_nothing_in_the_list(): void
    {
        $this->legacyMember($this->q3, $this->stranger, $this->orgA);

        $this->assertSame([], $this->listedIds($this->stranger));
    }

    // ---- R2 details, PDF, preview -----------------------------------------------

    public static function readRoutes(): array
    {
        return ['details' => ['quotes/{q}/details'], 'pdf' => ['quotes/{q}/pdf'], 'preview' => ['quotes/{q}/pdf-preview']];
    }

    #[DataProvider('readRoutes')]
    public function test_r2_owner_and_member_get_200_and_others_get_404(string $tpl): void
    {
        $this->asJson($this->owner, 'GET', $this->uri($tpl, $this->q1))->assertOk();
        $this->asJson($this->est, 'GET', $this->uri($tpl, $this->q1))->assertOk();

        $this->asJson($this->stranger, 'GET', $this->uri($tpl, $this->q1))->assertNotFound();
        $this->asJson($this->est, 'GET', $this->uri($tpl, $this->q0))->assertNotFound();  // owner's NULL-project quote
        $this->asJson($this->est, 'GET', $this->uri($tpl, $this->q3))->assertNotFound();  // project est is not in
        $this->asJson($this->est, 'GET', $this->uri($tpl, $this->q4))->assertNotFound();  // another org's project
        $this->asJson($this->outsider, 'GET', $this->uri($tpl, $this->q1))->assertNotFound();
        $this->asJson($this->est, 'GET', $this->uri($tpl, 99999))->assertNotFound();
    }

    #[DataProvider('readRoutes')]
    public function test_r2_owner_gets_404_for_their_own_null_project_quote(string $tpl): void
    {
        $this->asJson($this->owner, 'GET', $this->uri($tpl, $this->q0))->assertNotFound();
    }

    public function test_r2_details_of_a_teammates_quote_return_the_quote(): void
    {
        $r = $this->asJson($this->est, 'GET', "quotes/{$this->q1}/details")->assertOk();

        $this->assertSame($this->q1, $r->json('quote.id'));
    }

    // ---- R3 dashboard My Projects -----------------------------------------------

    public function test_r3_my_projects_is_only_the_visible_project_quotes_without_duplicates(): void
    {
        $this->legacyMember($this->q1, $this->viewer, $this->orgA);   // also reachable through P1: must not double
        $this->legacyMember($this->q3, $this->viewer, $this->orgA);   // legacy only: grants nothing (D18.6)

        $r = $this->asJson($this->viewer, 'GET', 'user-dashboard')->assertOk();

        $ids = $r->viewData('myProjects')->pluck('id')->all();
        sort($ids);
        $this->assertSame([$this->q1, $this->q2], $ids);
    }

    public function test_r3_stale_session_org_falls_back_to_the_first_active_org(): void
    {
        $r = $this->asJson($this->viewer, 'GET', 'user-dashboard', $this->orgSession($this->orgB->id))->assertOk();

        $this->assertSame($this->orgA->id, $r->viewData('orgId'));
        $this->assertEqualsCanonicalizing([$this->q1, $this->q2], $r->viewData('myProjects')->pluck('id')->all());
    }

    public function test_r3_user_with_no_org_gets_an_empty_panel(): void
    {
        $orgless = $this->mkUser();

        $r = $this->asJson($orgless, 'GET', 'user-dashboard')->assertOk();

        $this->assertNull($r->viewData('orgId'));
        $this->assertTrue($r->viewData('myProjects')->isEmpty());
    }

    public function test_r3_other_orgs_and_mismatched_rows_do_not_appear(): void
    {
        $this->member($this->p3, $this->viewer, $this->orgA); // mismatch row on org B's project

        $r = $this->asJson($this->viewer, 'GET', 'user-dashboard')->assertOk();

        $this->assertNotContains($this->q4, $r->viewData('myProjects')->pluck('id')->all());
    }

    // ---- R11 dashboard counters (H8 superseded by Phase 5 A5: the counters follow the quotes index) ----

    /** Human-confirmed-at-Checkpoint-2 change: the Phase 3 H8 owner-only counters are replaced by the quotes-index scope. */
    public function test_r11_counters_follow_the_quotes_index_while_a_null_project_own_quote_is_never_counted(): void
    {
        $this->mkQuote($this->viewer, null, ['name' => 'viewer own', 'status' => 'sent', 'quote_number' => 'OWN-1']);

        $r = $this->asJson($this->viewer, 'GET', 'user-dashboard')->assertOk();
        $html = $r->getContent();

        $this->assertEqualsCanonicalizing([$this->q1, $this->q2], $r->viewData('myProjects')->pluck('id')->all());
        $this->assertSame(1, preg_match('#<span class="fw-semibold">(\d+)</span> Quotes#', $html, $m));
        $this->assertSame('2', $m[1], 'viewer holds estimate_management R: both P1 quotes count, the NULL-project own quote does not');
        $this->assertStringContainsString('QT-2', $html);
        $this->assertStringContainsString('QT-3', $html);
        $this->assertStringNotContainsString('OWN-1', $html, 'a NULL-project quote is listed for nobody');
        $this->assertSame(1, preg_match('#var chartQuoteOpts = \{\s*series: \[(\d+),(\d+),(\d+)\]#', $html, $c), 'status chart series present');
        $this->assertSame(['2', '0', '0'], [$c[1], $c[2], $c[3]], 'two draft P1 quotes, the sent NULL-project quote is absent');
        $this->assertSame(2, $this->asJson($this->viewer, 'GET', 'quotes/list')->json('pagination.total'), 'the counter equals the quotes index');
    }

    // ---- R14 dashboard for a project_management F member (D16) ------------------

    public function test_r14_dashboard_renders_for_a_project_member_who_can_manage_projects(): void
    {
        $r = $this->asJson($this->est, 'GET', 'user-dashboard');

        $r->assertOk();
        $this->assertTrue($r->viewData('canManageProjects'), 'estimator holds F on project_management');
        $this->assertEqualsCanonicalizing([$this->q1, $this->q2], $r->viewData('myProjects')->pluck('id')->all());
        $this->assertStringContainsString('href="'.route('org-admin.projects.index').'"', $r->getContent());
    }

    // ---- R4 OrgAdminController::projects (project-keyed since Phase 4) ---------

    public function test_r4_projects_page_lists_the_orgs_live_projects_and_never_another_orgs(): void
    {
        $trashedProject = $this->mkProject($this->orgA, $this->owner, 'gone', now()->toDateTimeString());

        $r = $this->asJson($this->est, 'GET', 'org-admin/projects')->assertOk();
        $ids = $r->viewData('projects')->pluck('id')->all();
        sort($ids);

        $this->assertSame([$this->p1, $this->p2], $ids);
        $this->assertNotContains($this->p3, $ids, 'org B project');
        $this->assertNotContains($trashedProject, $ids, 'a trashed project does not appear');
        $this->assertArrayNotHasKey('quotes', $r->original->getData(), 'the old quote-shaped variable is gone');
    }

    public function test_r4_chips_show_active_project_rows_only_and_legacy_rows_never(): void
    {
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->super->id)->update(['is_active' => false]);
        $this->legacyMember($this->q1, $this->stranger, $this->orgA);
        $this->legacyMember($this->q0, $this->stranger, $this->orgA);

        $projects = $this->asJson($this->est, 'GET', 'org-admin/projects')->assertOk()->viewData('projects')->keyBy('id');

        $onP1 = $projects[$this->p1]->project_members_list->pluck('user_id')->all();
        sort($onP1);
        $expected = [$this->est->id, $this->viewer->id, $this->multi->id, $this->owner->id];
        sort($expected);
        $this->assertSame($expected, $onP1, 'project rows only, active only');
        $this->assertSame([$this->owner->id], $projects[$this->p2]->project_members_list->pluck('user_id')->all());
        foreach ($projects as $p) {
            $this->assertNotContains($this->stranger->id, $p->project_members_list->pluck('user_id')->all(), 'legacy quote-only rows never show');
        }
    }

    public function test_r4_other_orgs_rows_are_absent_from_the_chips(): void
    {
        $this->member($this->p1, $this->outsider, $this->orgB);   // a row filed under org B on org A's project

        $projects = $this->asJson($this->est, 'GET', 'org-admin/projects')->assertOk()->viewData('projects')->keyBy('id');

        $this->assertNotContains($this->outsider->id, $projects[$this->p1]->project_members_list->pluck('user_id')->all());
        $this->assertFalse($projects->has($this->p3));
    }

    public function test_r12_a_backfilled_member_shows_once_and_removing_the_chip_removes_access(): void
    {
        $legacyRow = $this->legacyMember($this->q1, $this->est, $this->orgA); // the backfill leaves the legacy row behind

        $projects = $this->asJson($this->est, 'GET', 'org-admin/projects')->assertOk()->viewData('projects')->keyBy('id');
        $chips = $projects[$this->p1]->project_members_list->where('user_id', $this->est->id);

        $this->assertCount(1, $chips, 'shown once');
        $chip = $chips->first();
        $this->assertSame($this->p1, (int) $chip->project_id, 'the project row, not the legacy row');
        $this->assertNotSame($legacyRow, (int) $chip->id);
        $this->assertTrue(Project::visibleTo($this->est->id, $this->orgA->id)->whereKey($this->p1)->exists());

        $this->actingAs($this->owner)->delete(route('org-admin.projects.members.destroy', $chip->id))->assertRedirect();

        $this->assertFalse(Project::visibleTo($this->est->id, $this->orgA->id)->whereKey($this->p1)->exists(), 'removing the only chip removes the access');
    }

    // ---- R5 plan-crosswalk index ------------------------------------------------

    public function test_r5_crosswalk_index_no_longer_throws_and_shows_only_visible_rows(): void
    {
        $ownNull = $this->mkQuote($this->est, null, ['name' => 'est own']);
        $backfilled = $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'B1');
        $native = $this->crosswalk(0, $this->p1, $this->orgA->id, 'N1');
        $own = $this->crosswalk($ownNull, null, $this->orgA->id, 'O1');
        $hiddenProject = $this->crosswalk($this->q3, $this->p2, $this->orgA->id, 'H1');
        $hiddenNull = $this->crosswalk($this->q0, null, $this->orgA->id, 'H2');
        $otherOrg = $this->crosswalk($this->q1, $this->p1, $this->orgB->id, 'X1');
        $orgBQuote = $this->crosswalk($this->q4, $this->p3, $this->orgB->id, 'X2');

        $r = $this->asJson($this->est, 'GET', 'plan-crosswalk')->assertOk();

        $ids = $r->viewData('rows')->pluck('id')->all();
        sort($ids);
        $this->assertSame([$backfilled, $native], $ids, 'only rows of visible projects; a quote-only row without a project is dead');
        foreach ([$own, $hiddenProject, $hiddenNull, $otherOrg, $orgBQuote] as $hidden) {
            $this->assertNotContains($hidden, $ids);
        }
        $projects = $r->viewData('projects');
        $this->assertSame([$this->p1], $projects->pluck('id')->all(), 'the filter list is the visible projects');
        $this->assertSame('P1', $projects->firstWhere('id', $this->p1)->name);
    }

    public function test_r5_crosswalk_filter_is_keyed_on_project_id_and_never_widens(): void
    {
        $backfilled = $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'B1');
        $native = $this->crosswalk(0, $this->p1, $this->orgA->id, 'N1');
        $hidden = $this->crosswalk(0, $this->p2, $this->orgA->id, 'H1');

        $filtered = $this->asJson($this->est, 'GET', 'plan-crosswalk?project_id='.$this->p1)->assertOk();
        $invisible = $this->asJson($this->est, 'GET', 'plan-crosswalk?project_id='.$this->p2)->assertOk()->viewData('rows');

        $ids = $filtered->viewData('rows')->pluck('id')->all();
        sort($ids);
        $this->assertSame([$backfilled, $native], $ids, 'project-keyed rows with a NULL quote_id are kept');
        $this->assertSame($this->p1, $filtered->viewData('selectedProjectId'));
        $this->assertNotContains($hidden, $invisible->pluck('id')->all());
        $this->assertSame([$backfilled, $native], $invisible->pluck('id')->sort()->values()->all(), 'an invisible project id is ignored, never widens');
        $this->assertNull($this->asJson($this->est, 'GET', 'plan-crosswalk?project_id='.$this->p2)->viewData('selectedProjectId'));
    }

    public function test_r5_crosswalk_index_for_a_user_without_an_org_is_empty(): void
    {
        $orgless = $this->mkUser();
        $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'B1');

        $r = $this->asJson($orgless, 'GET', 'plan-crosswalk')->assertOk();

        $this->assertTrue($r->viewData('rows')->isEmpty());
        $this->assertTrue($r->viewData('projects')->isEmpty());
    }

    public function test_r5_crosswalk_index_is_isolated_per_org(): void
    {
        $this->crosswalk($this->q4, $this->p3, $this->orgB->id, 'X2');
        $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'B1');

        $rows = $this->asJson($this->outsider, 'GET', 'plan-crosswalk')->assertOk()->viewData('rows');

        $this->assertSame(['X2'], $rows->pluck('plan_line_code')->all());
    }

    // ---- R6 project page (project-id keyed; the old quote URL is a 301) -----------

    public function test_r6_project_member_gets_the_project_page_and_the_old_url_redirects_to_it(): void
    {
        $this->asJson($this->est, 'GET', "projects/{$this->p1}")->assertOk();
        $this->actingAs($this->est)->get("projects/{$this->p1}")->assertOk()->assertViewIs('user.project-workspace.show');
        $this->actingAs($this->est)->get("projects/{$this->q1}/workspace")->assertStatus(301)->assertRedirect(route('projects.show', $this->p1));
    }

    public function test_r6_legacy_only_member_gets_404_on_both_urls(): void
    {
        $this->legacyMember($this->q1, $this->stranger, $this->orgA);

        $this->actingAs($this->stranger)->get("projects/{$this->q1}/workspace")->assertNotFound();
        $this->actingAs($this->stranger)->get("projects/{$this->p1}")->assertNotFound();
    }

    public function test_r6_null_project_quote_old_url_is_404_in_audit_and_denied_in_enforce_even_for_the_owner(): void
    {
        $this->assertNull(DB::table('quotes')->where('id', $this->q0)->value('project_id'));

        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $r = $this->actingAs($this->owner)->get("projects/{$this->q0}/workspace");
            $mode === 'audit' ? $r->assertNotFound() : $r->assertStatus(302); // enforce: the middleware denies first (project_unresolved)
        }
    }

    public function test_r6_the_project_id_not_the_quote_id_is_checked(): void
    {
        // Quote 3 (Q2) is in P1 but its id equals P3's id, and outsider is a member of P3 in org B.
        $this->assertSame($this->p3, $this->q2);

        $this->actingAs($this->outsider)->get("projects/{$this->q2}/workspace")->assertNotFound();
        $this->actingAs($this->est)->get("projects/{$this->q2}/workspace")->assertStatus(301)->assertRedirect(route('projects.show', $this->p1));
        // and the project URL with id 3 is org B's project: the outsider sees it, the org A member does not
        $this->actingAs($this->outsider)->get("projects/{$this->p3}")->assertOk();
        $this->actingAs($this->est)->get("projects/{$this->p3}")->assertNotFound();
    }

    public function test_r6_project_page_uses_the_validated_org(): void
    {
        $this->actingAs($this->multi)->withSession($this->orgSession(99999))->get("projects/{$this->p1}")->assertOk();
        $this->actingAs($this->multi)->withSession($this->orgSession($this->orgB->id))->get("projects/{$this->p1}")->assertNotFound();
    }

    // ---- R7 member writes (Phase 4 rule 6: a member holding the level may edit a teammate's quote) --

    public static function writeRoutes(): array
    {
        return [
            'duplicate' => ['POST', 'quotes/{q}/duplicate'],
            'update' => ['PUT', 'quotes/{q}'],
            'editor' => ['PUT', 'quotes/{q}/editor'],
            'update-item' => ['PUT', 'quotes/{q}/items/1'],
            'destroy' => ['DELETE', 'quotes/{q}'],
            'destroy-item' => ['DELETE', 'quotes/{q}/items/1'],
        ];
    }

    private function writePayload(string $tpl): array
    {
        return match (true) {
            $tpl === 'quotes/{q}' => ['notes' => 'edited by a member'],
            str_ends_with($tpl, '/editor') => ['customer_id' => 1, 'notes' => 'edited by a member'],
            str_contains($tpl, '/items/') => ['quantity' => 3],
            default => [],
        };
    }

    /** @return array<string,mixed> snapshot of everything a write route could touch */
    private function snapshot(): array
    {
        return [
            'quotes' => DB::table('quotes')->orderBy('id')->get()->all(),
            'items' => DB::table('quote_items')->orderBy('id')->get()->all(),
        ];
    }

    #[DataProvider('writeRoutes')]
    public function test_r7_a_project_member_with_the_level_can_write_a_teammates_quote(string $method, string $tpl): void
    {
        DB::table('customers')->insert(['id' => 1, 'user_id' => $this->est->id, 'company_name' => 'ACME', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $this->item($this->q1, 10);
            $quote = $this->mkQuote($this->owner, $this->p1, ['name' => "target $mode"]);
            $this->item($quote, 10);
            $itemId = (int) DB::table('quote_items')->where('quote_id', $quote)->value('id');
            $uri = str_replace('/items/1', "/items/$itemId", $this->uri($tpl, $quote));

            $this->asJson($this->est, $method, $uri, [], $this->writePayload($tpl))->assertOk();

            $row = DB::table('quotes')->where('id', $quote)->first();
            match (true) {
                $tpl === 'quotes/{q}/duplicate' => $this->assertSame(1, DB::table('quotes')->where('name', "target $mode (Copy)")->where('project_id', $this->p1)->where('user_id', $this->est->id)->count()),
                $tpl === 'quotes/{q}' && $method === 'DELETE' => $this->assertNotNull($row->deleted_at),
                $tpl === 'quotes/{q}' => $this->assertSame('edited by a member', $row->notes),
                str_ends_with($tpl, '/editor') => $this->assertSame('edited by a member', $row->notes),
                $method === 'PUT' => $this->assertSame('3.00', number_format((float) DB::table('quote_items')->where('id', $itemId)->value('quantity'), 2, '.', '')),
                default => $this->assertNull(DB::table('quote_items')->where('id', $itemId)->first()),
            };
            $this->assertSame($this->owner->id, (int) $row->user_id, 'ownership does not move');
            $this->assertSame($this->p1, (int) $row->project_id);
        }
    }

    #[DataProvider('writeRoutes')]
    public function test_r7_denied_cases_change_nothing_in_audit_and_enforce(string $method, string $tpl): void
    {
        $this->item($this->q1, 10);
        // wrong org, non-member, role without the level (viewer holds R), inactive member, legacy-only row
        $legacy = $this->mkUser($this->orgA, 'estimator');
        $this->legacyMember($this->q1, $legacy, $this->orgA);
        $inactive = $this->mkUser($this->orgA, 'estimator');
        $this->member($this->p1, $inactive, $this->orgA, false);
        $denied = ['wrong org' => $this->outsider, 'non-member' => $this->stranger, 'role without the level' => $this->viewer, 'inactive' => $inactive, 'legacy row' => $legacy];
        $before = $this->snapshot();

        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            foreach ($denied as $label => $user) {
                $r = $this->asJson($user, $method, $this->uri($tpl, $this->q1), [], $this->writePayload($tpl));
                // enforce: the middleware answers 403; audit: the controller scope answers 404
                $expected = $mode === 'enforce' ? 403 : 404;
                $this->assertSame($expected, $r->getStatusCode(), "$mode / $label");
                $this->assertEquals($before, $this->snapshot(), "$mode / $label wrote something");
            }
        }
    }

    public function test_r7_o_without_f_can_edit_but_not_delete_a_teammates_quote(): void
    {
        $eng = $this->mkUser($this->orgA, 'project_engineer'); // estimate_management O
        $this->member($this->p1, $eng, $this->orgA);
        $this->item($this->q1, 10);

        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $this->asJson($eng, 'PUT', "quotes/{$this->q1}", [], ['notes' => "eng $mode"])->assertOk();
            $this->assertSame("eng $mode", DB::table('quotes')->where('id', $this->q1)->value('notes'));

            $this->asJson($eng, 'DELETE', "quotes/{$this->q1}")->assertStatus($mode === 'enforce' ? 403 : 404);
            $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('deleted_at'));
        }
    }

    public function test_r7_the_owner_keeps_writing_their_own_quote(): void
    {
        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $this->asJson($this->owner, 'PUT', "quotes/{$this->q1}", [], ['notes' => "owner $mode"])->assertOk();
        }
    }

    // ---- R8-R10 audit-mode guarantee and enforce --------------------------------

    public function test_r8_audit_mode_non_member_gets_404_from_the_controller_and_a_would_block_row(): void
    {
        $this->asJson($this->stranger, 'GET', "quotes/{$this->q1}/details")->assertNotFound();

        $row = AuditLog::firstOrFail();
        $this->assertSame('would_block', $row->outcome);
        $this->assertSame('no_grant_or_not_project_member', $row->reason);
        $this->assertSame([$this->q1, $this->p1], [(int) $row->quote_id, (int) $row->project_id]);
    }

    public function test_r9_enforce_json_is_403_and_the_controller_never_runs(): void
    {
        $this->setMode('enforce');

        $r = $this->asJson($this->stranger, 'GET', "quotes/{$this->q1}/pdf");

        $r->assertForbidden()->assertJson(['rbac_error' => true]);
        $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('pdf_path'));
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame('blocked', AuditLog::firstOrFail()->outcome);
    }

    public function test_r9_enforce_non_json_is_a_302_back_not_a_403(): void
    {
        $this->setMode('enforce');

        $this->actingAs($this->stranger)->from('/previous')->get("quotes/{$this->q1}/details")
            ->assertStatus(302)->assertRedirect('/previous')->assertSessionHas('rbac_denied');
    }

    public function test_r10_owner_of_a_null_project_quote_in_audit_mode_gets_404_and_a_would_block_row(): void
    {
        $this->asJson($this->owner, 'GET', "quotes/{$this->q0}/details")->assertNotFound();

        $row = AuditLog::firstOrFail();
        $this->assertSame('would_block', $row->outcome);
        $this->assertSame('project_unresolved', $row->reason);
        $this->assertSame($this->q0, (int) $row->quote_id);
        $this->assertNull($row->project_id);
    }

    public function test_r10_member_reads_generate_no_audit_rows(): void
    {
        $this->asJson($this->est, 'GET', "quotes/{$this->q1}/details")->assertOk();
        $this->asJson($this->est, 'GET', 'quotes/list')->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    // ---- R13 trashed project ----------------------------------------------------

    public function test_r13_a_quote_in_a_trashed_project_is_visible_to_nobody_its_author_included(): void
    {
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);

        $this->assertSame([], $this->listedIds($this->est), 'a member no longer sees it');
        $this->asJson($this->est, 'GET', "quotes/{$this->q1}/details")->assertNotFound();
        $this->assertNotContains($this->q1, $this->listedIds($this->owner), 'the author no longer does either (no user_id clause)');
        $this->assertSame([$this->q3], $this->listedIds($this->owner), 'only the quote of the live project P2 remains');
        $this->asJson($this->owner, 'GET', "quotes/{$this->q1}/details")->assertNotFound();

        $dash = $this->asJson($this->viewer, 'GET', 'user-dashboard')->assertOk();
        $this->assertTrue($dash->viewData('myProjects')->isEmpty());
    }

    // ---- D18: H9 (role gate on member reads), H7, T-dash1, T-xw1 ------------------

    private function d18(): void
    {
        DB::table('customers')->insert(['id' => 1, 'company_name' => 'ACME Corp', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotes')->where('id', $this->q1)->update([
            'staff_notes' => 'STAFF-ONLY', 'notes' => 'PUBLIC-NOTES', 'terms_and_conditions' => 'TERMS', 'customer_id' => 1,
            'customer_address' => '1 Customer Way', 'updated_at' => '2025-01-01 00:00:00',
            'attachments' => json_encode([['name' => 'plan.pdf', 'path' => 'att/plan.pdf']]),
        ]);
        $this->item($this->q1, 40);
    }

    /** A role-less member: superintendent in A (no estimate_management), member of P1. */
    private function ownQuote(User $u, ?int $projectId = null, float $amount = 0): int
    {
        $id = $this->mkQuote($u, $projectId, ['name' => 'own of '.$u->id]);
        if ($amount) {
            $this->item($id, $amount);
        }

        return $id;
    }

    public function test_h9_1_list_filters_the_member_clause_by_the_role_and_the_total_matches(): void
    {
        $ownSuper = $this->ownQuote($this->super, $this->p1, 8);
        $ownEst = $this->ownQuote($this->est, $this->p1, 11);
        $nullSuper = $this->ownQuote($this->super, null, 3);
        $this->item($this->q1, 100);
        $this->item($this->q2, 50);

        // audit mode
        $with = $this->asJson($this->est, 'GET', 'quotes/list')->assertOk();
        $without = $this->asJson($this->super, 'GET', 'quotes/list')->assertOk();

        $ids = array_column($with->json('quotes'), 'id');
        sort($ids);
        $expected = [$this->q1, $this->q2, $ownSuper, $ownEst];
        sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertSame('169.00', $with->json('total_amount'));
        $this->assertSame([$ownSuper], array_column($without->json('quotes'), 'id'), 'role-less member sees only their own quote inside their project, not the teammates\' and not the NULL-project one');
        $this->assertNotContains($nullSuper, array_column($without->json('quotes'), 'id'));
        $this->assertSame('8.00', $without->json('total_amount'), 'total equals the list set');
        $this->assertSame(1, $without->json('pagination.total'));

        // enforce mode: the role-less member is blocked by the middleware before the controller
        $this->setMode('enforce');
        $this->asJson($this->super, 'GET', 'quotes/list')->assertForbidden();
        $this->asJson($this->est, 'GET', 'quotes/list')->assertOk();
    }

    public function test_h9_1_role_less_member_who_owns_nothing_sees_an_empty_list(): void
    {
        $r = $this->asJson($this->super, 'GET', 'quotes/list')->assertOk();

        $this->assertSame([], $r->json('quotes'));
        $this->assertSame('0.00', $r->json('total_amount'));
    }

    public static function readUris(): array
    {
        return ['details' => ['quotes/{q}/details'], 'pdf' => ['quotes/{q}/pdf'], 'preview' => ['quotes/{q}/pdf-preview']];
    }

    #[DataProvider('readUris')]
    public function test_h9_2_and_3_role_less_member_gets_404_in_audit_and_403_in_enforce(string $tpl): void
    {
        $this->d18();
        $uri = $this->uri($tpl, $this->q1);

        // audit: controller 404 plus a would_block row
        $this->asJson($this->super, 'GET', $uri)->assertNotFound();
        $row = AuditLog::firstOrFail();
        $this->assertSame('would_block', $row->outcome);
        $this->assertSame('no_grant_or_not_project_member', $row->reason);
        $this->assertSame([$this->q1, $this->p1], [(int) $row->quote_id, (int) $row->project_id]);

        // the members and owner are unaffected in audit mode
        $this->asJson($this->est, 'GET', $uri)->assertOk();
        $this->asJson($this->owner, 'GET', $uri)->assertOk();
        $this->asJson($this->stranger, 'GET', $uri)->assertNotFound();
        $this->assertSame(1 + 1, AuditLog::count(), 'the stranger adds a would_block row; members and owner add none');

        // enforce: the middleware blocks first and the controller never runs
        $this->setMode('enforce');
        AuditLog::query()->delete();
        Storage::fake('public');
        DB::table('quotes')->where('id', $this->q1)->update(['pdf_path' => null]);
        $this->asJson($this->super, 'GET', $uri)->assertForbidden()->assertJson(['rbac_error' => true]);
        $this->assertSame('blocked', AuditLog::firstOrFail()->outcome);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('pdf_path'));
        $this->asJson($this->est, 'GET', $uri)->assertOk();
        $this->asJson($this->owner, 'GET', $uri)->assertOk();
    }

    public function test_h9_2_the_role_gate_uses_the_org_level_role_of_the_current_org(): void
    {
        // A user who is a P1 member in org A with a role-less role there, but an estimator in org B.
        $twoOrgs = $this->mkUser($this->orgA, 'superintendent');
        $this->assignRole($twoOrgs, $this->orgB, 'estimator');
        $this->member($this->p1, $twoOrgs, $this->orgA);
        $uri = "quotes/{$this->q1}/details";

        $this->asJson($twoOrgs, 'GET', $uri, $this->orgSession($this->orgA->id))->assertNotFound();
        $this->asJson($twoOrgs, 'GET', $uri, $this->orgSession($this->orgB->id))->assertNotFound();   // B has the role, but no P1 membership in B
        $this->asJson($twoOrgs, 'GET', $uri, $this->orgSession(99999))->assertNotFound();             // stale -> first active org (A)
        // and the same user with the role in org A gets in
        $this->assignRole($twoOrgs, $this->orgA, 'estimator');
        $this->asJson($twoOrgs, 'GET', $uri, $this->orgSession($this->orgA->id))->assertOk();
    }

    public function test_h9_2_role_gate_is_not_bypassed_by_a_null_project_or_trashed_project_quote(): void
    {
        $this->assertNull(DB::table('quotes')->where('id', $this->q0)->value('project_id'));
        $this->asJson($this->est, 'GET', "quotes/{$this->q0}/details")->assertNotFound();

        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);
        $this->asJson($this->est, 'GET', "quotes/{$this->q1}/details")->assertNotFound();
        $this->asJson($this->super, 'GET', "quotes/{$this->q1}/details")->assertNotFound();
    }

    public function test_h9_2_a_user_without_any_org_reads_nothing_not_even_their_own_quotes(): void
    {
        $orgless = $this->mkUser();
        $own = $this->ownQuote($orgless, $this->p1);
        $ownNull = $this->ownQuote($orgless);

        $this->assertSame([], $this->listedIds($orgless));
        $this->asJson($orgless, 'GET', "quotes/$own/details")->assertNotFound();
        $this->asJson($orgless, 'GET', "quotes/$ownNull/details")->assertNotFound();
        $this->asJson($orgless, 'GET', "quotes/{$this->q1}/details")->assertNotFound();
    }

    public function test_h9_4_crosswalk_index_for_a_role_less_member_shows_no_rows(): void
    {
        $ownSuper = $this->ownQuote($this->super);
        $this->crosswalk($ownSuper, null, $this->orgA->id, 'MINE');
        $backfilled = $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'B1');
        $native = $this->crosswalk(0, $this->p1, $this->orgA->id, 'N1');

        $r = $this->asJson($this->super, 'GET', 'plan-crosswalk')->assertOk();

        $this->assertSame([], $r->viewData('rows')->pluck('id')->all(), 'no estimate_management R: no rows at all, not even the own quote-only row');
        $this->assertSame([$this->p1], $r->viewData('projects')->pluck('id')->all(), 'the project list is unconditional: visible projects only, rows stay narrow');

        $member = $this->asJson($this->est, 'GET', 'plan-crosswalk')->assertOk();
        $ids = $member->viewData('rows')->pluck('id')->all();
        sort($ids);
        $this->assertSame([$backfilled, $native], $ids, 'a member with the role sees the project rows');
        $this->assertSame([$this->p1], $member->viewData('projects')->pluck('id')->all());
    }

    public function test_h9_5_an_owner_without_the_role_still_reads_their_own_quote_in_audit_mode(): void
    {
        $own = $this->ownQuote($this->super, $this->p1, 9);

        $this->asJson($this->super, 'GET', "quotes/$own/details")->assertOk();
        $this->asJson($this->super, 'GET', "quotes/$own/pdf-preview")->assertOk();
        $this->assertSame([$own], $this->listedIds($this->super));
        $this->assertSame('9.00', $this->asJson($this->super, 'GET', 'quotes/list')->json('total_amount'));
    }

    public function test_h9_6_workspace_and_dashboard_stay_closed_for_a_role_less_member(): void
    {
        // superintendent holds project_management R only, no estimate_management: the page renders but with no estimate or crosswalk panel data
        $page = $this->actingAs($this->super)->get("projects/{$this->p1}")->assertOk();
        $this->assertFalse($page->viewData('canReadEstimates'));
        $this->assertTrue($page->viewData('quotes')->isEmpty());
        $this->assertTrue($page->viewData('crosswalkEntries')->isEmpty());

        $dash = $this->asJson($this->super, 'GET', 'user-dashboard')->assertOk();
        $this->assertTrue($dash->viewData('myProjects')->isEmpty(), 'canReadEstimates gate');
    }

    public function test_h7_1_staff_notes_are_hidden_from_non_owners_and_the_rest_stays_visible(): void
    {
        $this->d18();

        $member = $this->asJson($this->est, 'GET', "quotes/{$this->q1}/details")->assertOk();
        $owner = $this->asJson($this->owner, 'GET', "quotes/{$this->q1}/details")->assertOk();

        $this->assertArrayHasKey('staff_notes', $member->json('quote'), 'the key stays present');
        $this->assertNull($member->json('quote.staff_notes'));
        $this->assertSame('STAFF-ONLY', $owner->json('quote.staff_notes'));
        $this->assertStringNotContainsString('STAFF-ONLY', $member->getContent());
        $this->assertSame('PUBLIC-NOTES', $member->json('quote.notes'));
        $this->assertSame('TERMS', $member->json('quote.terms_and_conditions'));
        $this->assertSame('ACME Corp', $member->json('quote.customer_name'));
        $this->assertSame('1 Customer Way', $member->json('quote.customer_address'));
        $this->assertSame('plan.pdf', $member->json('quote.attachments.0.name'));
        $this->assertNotEmpty($member->json('quote.attachments.0.url'));
        $this->assertSame('40.00', $member->json('total'), 'items still visible');
    }

    public function test_h7_1_the_rendered_pdf_has_no_staff_notes(): void
    {
        $this->d18();
        $data = \App\Support\QuotePdfPresenter::present(\App\Models\Quote::with(['items', 'customer'])->findOrFail($this->q1));

        $html = view('frontend.quotes.pdf-template', $data)->render();

        $this->assertStringContainsString('QT-2', $html, 'the template rendered the quote');
        $this->assertStringNotContainsString('STAFF-ONLY', $html, 'staff_notes never reach the PDF');
        $this->assertStringContainsString('PUBLIC-NOTES', $html, 'notes still do');
    }

    public function test_h7_2_a_member_download_writes_nothing_and_the_owner_download_persists(): void
    {
        $this->d18();

        $this->asJson($this->est, 'GET', "quotes/{$this->q1}/pdf")->assertOk();

        $row = DB::table('quotes')->where('id', $this->q1)->first();
        $this->assertNull($row->pdf_path);
        $this->assertSame('2025-01-01 00:00:00', $row->updated_at, 'updated_at unchanged');
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->asJson($this->owner, 'GET', "quotes/{$this->q1}/pdf")->assertOk();

        $row = DB::table('quotes')->where('id', $this->q1)->first();
        $this->assertSame('quotes/QT-2.pdf', $row->pdf_path);
        Storage::disk('public')->assertExists('quotes/QT-2.pdf');
    }

    public function test_h7_2_preview_never_writes_for_anyone(): void
    {
        $this->d18();

        $this->asJson($this->est, 'GET', "quotes/{$this->q1}/pdf-preview")->assertOk();
        $this->asJson($this->owner, 'GET', "quotes/{$this->q1}/pdf-preview")->assertOk();

        $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('pdf_path'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_t_dash1_legacy_rows_put_nothing_on_the_dashboard(): void
    {
        $this->legacyMember($this->q4, $this->stranger, $this->orgA);           // active, current org, quote of another org's project
        $this->legacyMember($this->q3, $this->stranger, $this->orgA, false);   // inactive
        $this->legacyMember($this->q1, $this->stranger, $this->orgB);           // another org
        $this->legacyMember($this->q0, $this->stranger, $this->orgA);           // NULL-project quote

        $r = $this->asJson($this->stranger, 'GET', 'user-dashboard')->assertOk();

        $this->assertTrue($r->viewData('myProjects')->isEmpty());
        $this->assertTrue($r->viewData('pendingApprovals')->isEmpty());
    }

    public function test_t_dash1_project_members_still_see_the_project_quotes_once(): void
    {
        $this->legacyMember($this->q1, $this->viewer, $this->orgA);
        $this->legacyMember($this->q2, $this->viewer, $this->orgA);

        $r = $this->asJson($this->viewer, 'GET', 'user-dashboard')->assertOk();

        $ids = $r->viewData('myProjects')->pluck('id')->all();
        sort($ids);
        $this->assertSame([$this->q1, $this->q2], $ids);
    }

    public function test_t_xw1_stale_session_org_acts_on_the_real_orgs_row_and_never_on_another_orgs(): void
    {
        $rowA = $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'A1');
        $rowA2 = $this->crosswalk($this->q2, $this->p1, $this->orgA->id, 'A2');
        $rowB = $this->crosswalk($this->q4, $this->p3, $this->orgB->id, 'B1');
        $stale = $this->orgSession($this->orgB->id);   // est has no active role in org B

        $this->actingAs($this->est)->withSession($stale)->put("plan-crosswalk/$rowA", ['plan_line_code' => 'A1-EDITED'])->assertRedirect();
        $this->assertSame('A1-EDITED', DB::table('plan_crosswalk')->where('id', $rowA)->value('plan_line_code'));

        $this->actingAs($this->est)->withSession($stale)->delete("plan-crosswalk/$rowA2")->assertRedirect();
        $this->assertNull(DB::table('plan_crosswalk')->where('id', $rowA2)->first());

        $this->actingAs($this->est)->withSession($stale)->put("plan-crosswalk/$rowB", ['plan_line_code' => 'HACK'])->assertNotFound();
        $this->actingAs($this->est)->withSession($stale)->delete("plan-crosswalk/$rowB")->assertNotFound();
        $this->assertSame('B1', DB::table('plan_crosswalk')->where('id', $rowB)->value('plan_line_code'));
    }

    // ---- D19: H9-7 / H9-8 group separation (P3F-01) ----------------------------

    /** @return array{0:User,1:User} [procurement-only member, estimate-only member], both enrolled in P1 */
    private function groupFixture(): array
    {
        $csr = $this->mkUser($this->orgA, 'order_fulfillment_csr');
        $architect = $this->mkUser($this->orgA, 'architect');
        $this->member($this->p1, $csr, $this->orgA);
        $this->member($this->p1, $architect, $this->orgA);

        // Guard: the fixtures only mean something while the seeded matrix says so.
        $svc = app(\App\Services\Rbac\PermissionService::class);
        $this->assertTrue($svc->checkPermission($csr->id, $this->orgA->id, 'procurement', 'R'));
        $this->assertFalse($svc->checkPermission($csr->id, $this->orgA->id, 'estimate_management', 'R'), 'csr must lack estimate_management');
        $this->assertFalse($svc->checkPermission($csr->id, $this->orgA->id, 'procurement', 'O'), 'csr holds procurement R only');
        $this->assertTrue($svc->checkPermission($architect->id, $this->orgA->id, 'estimate_management', 'R'));
        $this->assertFalse($svc->checkPermission($architect->id, $this->orgA->id, 'estimate_management', 'S'), 'architect holds estimate_management R only');
        $this->assertFalse($svc->checkPermission($architect->id, $this->orgA->id, 'procurement', 'R'), 'architect must lack procurement');

        return [$csr, $architect];
    }

    public function test_h9_7_procurement_only_member_is_denied_on_every_read_path(): void
    {
        [$csr] = $this->groupFixture();
        $this->d18();
        $this->item($this->q1, 100);
        $this->item($this->q2, 50);
        $own = $this->ownQuote($csr, $this->p1, 8);
        $this->crosswalk($own, null, $this->orgA->id, 'MINE');
        $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'B1');
        $this->crosswalk(0, $this->p1, $this->orgA->id, 'N1');

        // audit mode: the controller's group check is the deciding one
        foreach (['details', 'pdf-preview', 'pdf'] as $tail) {
            AuditLog::query()->delete();
            $this->asJson($csr, 'GET', "quotes/{$this->q1}/$tail")->assertNotFound();
            $row = AuditLog::firstOrFail();
            $this->assertSame('would_block', $row->outcome, $tail);
            $this->assertSame([$this->q1, $this->p1], [(int) $row->quote_id, (int) $row->project_id]);
        }
        $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('pdf_path'));
        $this->assertSame([], Storage::disk('public')->allFiles());

        AuditLog::query()->delete();
        $list = $this->asJson($csr, 'GET', 'quotes/list')->assertOk();
        $this->assertSame([$own], array_column($list->json('quotes'), 'id'), 'teammates\' quotes excluded');
        $this->assertSame('8.00', $list->json('total_amount'), 'total equals the list set');
        $this->assertSame('would_block', AuditLog::firstOrFail()->outcome);

        $xw = $this->asJson($csr, 'GET', 'plan-crosswalk')->assertOk();
        $this->assertSame([], $xw->viewData('rows')->pluck('id')->all(), 'no estimate_management R: no crosswalk rows');
        $this->assertSame([$this->p1], $xw->viewData('projects')->pluck('id')->all(), 'unconditional visible-project list; P2 stays hidden');

        // enforce mode (JSON): blocked by the middleware; agrees with audit
        $this->setMode('enforce');
        foreach (['quotes/list', "quotes/{$this->q1}/details", "quotes/{$this->q1}/pdf-preview", "quotes/{$this->q1}/pdf", 'plan-crosswalk'] as $uri) {
            $this->asJson($csr, 'GET', $uri)->assertForbidden();
        }
    }

    public function test_h9_8_estimate_only_member_is_allowed_on_every_read_path(): void
    {
        [, $architect] = $this->groupFixture();
        $this->d18();
        $this->item($this->q2, 50);
        $own = $this->ownQuote($architect, $this->p1, 8);
        $this->crosswalk($own, null, $this->orgA->id, 'MINE');
        $backfilled = $this->crosswalk($this->q1, $this->p1, $this->orgA->id, 'B1');
        $native = $this->crosswalk(0, $this->p1, $this->orgA->id, 'N1');
        $hidden = $this->crosswalk($this->q3, $this->p2, $this->orgA->id, 'H1');

        foreach (['details', 'pdf-preview', 'pdf'] as $tail) {
            $this->asJson($architect, 'GET', "quotes/{$this->q1}/$tail")->assertOk();
        }
        $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('pdf_path'), 'a member download never persists');

        $list = $this->asJson($architect, 'GET', 'quotes/list')->assertOk();
        $ids = array_column($list->json('quotes'), 'id');
        sort($ids);
        $this->assertSame([$this->q1, $this->q2, $own], $ids);
        $this->assertSame('98.00', $list->json('total_amount'), '40 + 50 + 8: the total covers the same set');

        $xw = $this->asJson($architect, 'GET', 'plan-crosswalk')->assertOk();
        $rows = $xw->viewData('rows')->pluck('id')->all();
        sort($rows);
        $this->assertSame([$backfilled, $native], $rows);
        $this->assertNotContains($hidden, $rows);
        $this->assertSame(0, AuditLog::count(), 'no would_block row for a member with the group');

        $this->setMode('enforce');
        foreach (['quotes/list', "quotes/{$this->q1}/details", "quotes/{$this->q1}/pdf-preview", "quotes/{$this->q1}/pdf", 'plan-crosswalk'] as $uri) {
            $this->asJson($architect, 'GET', $uri)->assertOk();
        }
        $this->assertSame(0, AuditLog::count());
    }
}

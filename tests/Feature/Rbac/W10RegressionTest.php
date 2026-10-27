<?php

namespace Tests\Feature\Rbac;

use App\Models\Project;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\User;
use App\Services\Rbac\PermissionMatrix;
use App\Services\Rbac\PermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * QA pins for W10 (PLAN 10.14) that OrgWideProjectVisibilityTest does not carry: roles that must stay
 * membership-scoped in every list and route, org-wide holders against every id-keyed route of another org,
 * the membership and creation screens for org-wide holders, universal admin and delegation accounting,
 * the dashboard counters, and levels A / O / S on organization_management (no seeded role holds them).
 *
 * Fixture (ProjectTestCase): P1, P2 in org A, P3 in org B; Q0 (NULL project), Q1, Q2 (P1), Q3 (P2), Q4 (P3);
 * est is a member of P1 only. Added here: RFQs on P1 and P2, crosswalk rows on P1 and P2, a pending estimate
 * on P2, customers for both orgs. No actor added here is enrolled anywhere unless the test says so.
 */
class W10RegressionTest extends ProjectTestCase
{
    private User $ownerNm;
    private User $adminNm;
    private User $auditorNm;
    private User $ownerB;
    private $sellerOrg;
    private User $sellerOwner;

    private int $rfq1;
    private int $rfq2;
    private int $cw1;
    private int $cw2;
    private int $pending2;

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
        Storage::fake('public');

        $this->ownerNm = $this->mkUser($this->orgA, 'organization_owner');
        $this->adminNm = $this->mkUser($this->orgA, 'organization_admin');
        $this->auditorNm = $this->mkUser($this->orgA, 'auditor_read_all');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->sellerOrg = $this->mkOrg('Seller Org');
        $this->sellerOwner = $this->mkUser($this->sellerOrg, 'organization_owner');
        DB::table('org_relationships')->insert([
            'from_org_id' => $this->orgA->id, 'to_org_id' => $this->sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([[1, $this->orgA, $this->owner], [2, $this->orgB, $this->outsider]] as [$id, $org, $by]) {
            DB::table('customers')->insert(['id' => $id, 'user_id' => $by->id, 'org_id' => $org->id, 'company_name' => "C$id", 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->rfq1 = $this->mkRfq($this->p1, 'RFQ on P1');
        $this->rfq2 = $this->mkRfq($this->p2, 'RFQ on P2');
        $this->cw1 = $this->mkCrosswalk($this->p1, 'CW1');
        $this->cw2 = $this->mkCrosswalk($this->p2, 'CW2');
        $this->pending2 = $this->mkQuote($this->owner, $this->p2, ['status' => 'pending_approval', 'name' => 'Pending on P2']);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function memberScopedRolesByMode(): array
    {
        $out = [];
        foreach (['estimator', 'project_manager', 'procurement_manager', 'requisitioner', 'viewer_read_only', 'executive_approver',
            'financial_admin', 'budget_owner', 'superintendent', 'procurement_coordinator', 'contract_manager', 'project_engineer'] as $role) {
            foreach (['audit', 'enforce'] as $mode) {
                $out["$role $mode"] = [$role, $mode];
            }
        }

        return $out;
    }

    // ---- helpers ----------------------------------------------------------------

    private function req(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        $session = $org ? [config('rbac.current_org_session_key') => $org] : [];

        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function mkRfq(?int $projectId, string $title): int
    {
        return (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $this->orgA->id, 'project_id' => $projectId, 'created_by' => $this->owner->id,
            'title' => $title, 'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function mkCrosswalk(int $projectId, string $code): int
    {
        return (int) DB::table('plan_crosswalk')->insertGetId([
            'org_id' => $this->orgA->id, 'project_id' => $projectId, 'quote_id' => null, 'plan_line_code' => $code,
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function delegate(User $from, User $to): Delegation
    {
        return Delegation::create([
            'from_user_id' => $from->id, 'to_user_id' => $to->id, 'org_id' => $this->orgA->id, 'granted_by' => $from->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);
    }

    private function ints(iterable $ids): array
    {
        $out = array_map('intval', is_array($ids) ? $ids : iterator_to_array($ids));
        sort($out);

        return $out;
    }

    private function visibleProjectIds(User $u, ?int $org = null): array
    {
        return $this->ints(Project::visibleTo($u->id, $org ?? $this->orgA->id)->pluck('projects.id')->all());
    }

    /** ids from a 200 response; a role below the screen's own level answers 403 (middleware in enforce mode, controller check in both) and yields no ids */
    private function idsOrDenied($response, callable $pick): array
    {
        if ($response->status() === 200) {
            return $this->ints($pick($response));
        }

        $this->assertSame(403, $response->status());

        return [];
    }

    /** every table a write could touch, with every column, so an update that keeps the row count is seen too */
    private function state(): array
    {
        $snap = fn (string $t) => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return [
            'projects' => $snap('projects'), 'members' => $snap('project_members'), 'logs' => $snap('project_member_logs'),
            'quotes' => $snap('quotes'), 'crosswalk' => $snap('plan_crosswalk'), 'rfq' => $snap('rfq_requests'),
        ];
    }

    private function universalRows()
    {
        return AuditLog::where('outcome', 'allowed_universal_admin')->orderBy('id')->get();
    }

    private function dash(User $u): array
    {
        $r = $this->actingAs($u)->get('user-dashboard')->assertOk();
        $html = $r->getContent();
        $this->assertSame(1, preg_match('#<span class="fw-semibold">(\d+)</span> Quotes#', $html, $m));
        $recent = $this->ints($r->viewData('recentQuotes')->pluck('id')->all());

        return ['count' => (int) $m[1], 'recent' => $recent, 'view' => (int) $r->viewData('quotesCount')];
    }

    private function quotesIndexTotal(User $u): int
    {
        return (int) $this->req($u, 'GET', 'quotes/list')->assertOk()->json('pagination.total');
    }

    // ---- 1. roles that must stay membership-scoped, every list and route ----------

    #[DataProvider('memberScopedRolesByMode')]
    public function test_member_scoped_roles_see_only_their_member_project_in_every_list_and_route(string $role, string $mode): void
    {
        $this->setMode($mode);
        $a = $this->orgA->id;
        $u = $this->mkUser($this->orgA, $role);
        $this->member($this->p1, $u, $this->orgA);

        $this->assertFalse(app(PermissionService::class)->seesAllOrgProjects($u->id, $a));
        $this->assertSame([$this->p1], $this->visibleProjectIds($u), 'the scope itself');

        $list = $this->idsOrDenied($this->req($u, 'GET', 'projects/list'), fn ($r) => array_column($r->json('projects'), 'id'));
        $this->assertTrue($list === [] ? $mode === 'enforce' : $list === [$this->p1], "projects/list: $role (an empty answer is only the enforce-mode 403)");
        $page = $this->idsOrDenied($this->req($u, 'GET', 'projects'), fn ($r) => $r->viewData('projects')->pluck('id')->all());
        $this->assertSame([], array_diff($page, [$this->p1]), 'projects page');

        $quotes = $this->idsOrDenied($this->req($u, 'GET', 'quotes/list'), fn ($r) => array_column($r->json('quotes'), 'id'));
        $this->assertSame([], array_diff($quotes, [$this->q1, $this->q2]), 'quotes/list never leaves P1');

        $rfqs = $this->idsOrDenied($this->req($u, 'GET', 'rfq'), fn ($r) => $r->viewData('rfqs')->pluck('id')->all());
        $this->assertNotContains($this->rfq2, $rfqs, 'rfq index');
        $rows = $this->idsOrDenied($this->req($u, 'GET', 'plan-crosswalk'), fn ($r) => $r->viewData('rows')->pluck('id')->all());
        $this->assertNotContains($this->cw2, $rows, 'crosswalk index');

        $dash = $this->req($u, 'GET', 'user-dashboard');
        $this->assertSame(200, $dash->status(), 'the landing page is open to every org member');
        $this->assertSame([], array_diff($this->ints($dash->viewData('myProjects')->pluck('id')->all()), [$this->q1, $this->q2]), 'dashboard My Projects');
        $this->assertNotContains($this->pending2, $this->ints($dash->viewData('pendingApprovals')->pluck('id')->all()), 'dashboard approvals');
        $this->assertNotContains($this->rfq2, $this->ints($dash->viewData('pendingRfqs')->pluck('id')->all()), 'dashboard RFQs');

        foreach ([
            ['GET', "projects/{$this->p2}", []],
            ['GET', "quotes/{$this->q3}/details", []],
            ['GET', "quotes/{$this->q3}/pdf", []],
            ['GET', "projects/{$this->q3}/workspace", []],
            ['GET', "rfq/{$this->rfq2}", []],
        ] as [$m, $uri, $data]) {
            $this->assertContains($this->req($u, $m, $uri, $data)->status(), [403, 404], "$m $uri");
        }

        $before = $this->state();
        foreach ([
            ['PUT', "projects/{$this->p2}", ['name' => 'Hijacked', 'status' => 'active']],
            ['DELETE', "projects/{$this->p2}", []],
            ['POST', "projects/{$this->p2}/members", ['user_id' => $this->stranger->id]],
            ['POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->stranger->id]],
            ['POST', "projects/{$this->p2}/quotes", ['customer_id' => 1]],
            ['POST', "projects/{$this->p2}/crosswalk", ['plan_line_code' => 'ZZ']],
            ['PUT', "quotes/{$this->q3}", ['notes' => 'x']],
            ['DELETE', "quotes/{$this->q3}", []],
            ['POST', "quotes/{$this->q3}/duplicate", []],
            ['PUT', "plan-crosswalk/{$this->cw2}", ['plan_line_code' => 'HACK']],
            ['DELETE', "plan-crosswalk/{$this->cw2}", []],
            ['POST', "rfq/{$this->rfq2}/convert", []],
        ] as [$m, $uri, $data]) {
            $status = $this->req($u, $m, $uri, $data)->status();
            $this->assertContains($status, [403, 404], "$role $m $uri answered $status");
        }
        $this->assertSame($before, $this->state(), 'no write reached the database');

        $this->member($this->p2, $u, $this->orgA);
        $this->assertSame([$this->p1, $this->p2], $this->visibleProjectIds($u), 'control: once enrolled the project shows');
    }

    // ---- 2. org-wide holders of another org / a seller org against every id-keyed route ---

    #[DataProvider('foreignOwnerByMode')]
    public function test_the_owner_of_another_org_or_a_seller_org_cannot_reach_any_id_route_of_this_org(string $actor, string $mode): void
    {
        $this->setMode($mode);
        $u = $actor === 'ownerB' ? $this->ownerB : $this->sellerOwner;
        $memberRow = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->value('id');
        $before = $this->state();

        foreach ([
            ['GET', "projects/{$this->p1}", []],
            ['PUT', "projects/{$this->p1}", ['name' => 'Hijacked', 'status' => 'active']],
            ['DELETE', "projects/{$this->p1}", []],
            ['POST', "projects/{$this->p1}/members", ['user_id' => $this->stranger->id]],
            ['DELETE', "projects/{$this->p1}/members/$memberRow", []],
            ['POST', "projects/{$this->p1}/quotes", ['customer_id' => 1]],
            ['POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'ZZ']],
            ['GET', "projects/{$this->q1}/workspace", []],
            ['GET', "quotes/{$this->q1}/details", []],
            ['GET', "quotes/{$this->q1}/pdf", []],
            ['PUT', "quotes/{$this->q1}", ['notes' => 'x']],
            ['DELETE', "quotes/{$this->q1}", []],
            ['POST', "quotes/{$this->q1}/duplicate", []],
            ['PUT', "plan-crosswalk/{$this->cw1}", ['plan_line_code' => 'HACK']],
            ['DELETE', "plan-crosswalk/{$this->cw1}", []],
            ['GET', "rfq/{$this->rfq1}", []],
            ['POST', "rfq/{$this->rfq1}/convert", []],
            ['POST', 'org-admin/projects/members', ['project_id' => $this->p1, 'user_id' => $this->stranger->id]],
            ['DELETE', "org-admin/projects/members/$memberRow", []],
        ] as [$m, $uri, $data]) {
            $this->assertSame(404, $this->req($u, $m, $uri, $data)->status(), "$actor $m $uri");
        }

        $this->assertSame($before, $this->state(), 'nothing of org A changed');
    }

    public static function foreignOwnerByMode(): array
    {
        return [
            'ownerB audit' => ['ownerB', 'audit'], 'ownerB enforce' => ['ownerB', 'enforce'],
            'sellerOwner audit' => ['sellerOwner', 'audit'], 'sellerOwner enforce' => ['sellerOwner', 'enforce'],
        ];
    }

    #[DataProvider('modes')]
    public function test_a_forged_session_org_does_not_widen_an_owner_of_another_org(string $mode): void
    {
        $this->setMode($mode);
        $a = $this->orgA->id;

        $list = $this->req($this->ownerB, 'GET', 'projects/list', [], $a)->assertOk()->json('projects');
        $this->assertSame([$this->p3], $this->ints(array_column($list, 'id')));
        $this->req($this->ownerB, 'GET', "projects/{$this->p1}", [], $a)->assertNotFound();
        $this->req($this->ownerB, 'GET', "quotes/{$this->q1}/details", [], $a)->assertNotFound();
        $this->assertSame([$this->q4], $this->ints(array_column($this->req($this->ownerB, 'GET', 'quotes/list', [], $a)->assertOk()->json('quotes'), 'id')));

        $this->assertSame([$this->p1, $this->p2], $this->ints(array_column($this->req($this->ownerNm, 'GET', 'projects/list', [], $this->orgB->id)->assertOk()->json('projects'), 'id')), 'an owner with no role in B stays in A');
    }

    // ---- 3. quote creation, project creation, membership screens ------------------

    #[DataProvider('modes')]
    public function test_quote_creation_is_allowed_in_a_visible_project_at_the_estimate_level_and_never_elsewhere(string $mode): void
    {
        $this->setMode($mode);
        $count = fn () => DB::table('quotes')->count();

        $n = $count();
        $id = (int) $this->req($this->ownerNm, 'POST', "projects/{$this->p2}/quotes", ['customer_id' => 1])->assertOk()->json('quote_id');
        $this->assertSame($n + 1, $count());
        $this->assertSame($this->p2, (int) DB::table('quotes')->where('id', $id)->value('project_id'));
        $this->assertSame($this->ownerNm->id, (int) DB::table('quotes')->where('id', $id)->value('user_id'));
        $this->assertContains($id, $this->ints(array_column($this->req($this->ownerNm, 'GET', 'quotes/list')->assertOk()->json('quotes'), 'id')));
        $this->assertSame(0, DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $this->ownerNm->id)->count(), 'creating does not enrol the owner');

        $n = $count();
        $this->req($this->ownerNm, 'POST', "projects/{$this->p3}/quotes", ['customer_id' => 1])->assertNotFound();
        $this->req($this->ownerB, 'POST', "projects/{$this->p2}/quotes", ['customer_id' => 2])->assertNotFound();
        $this->req($this->sellerOwner, 'POST', "projects/{$this->p2}/quotes", ['customer_id' => 1])->assertNotFound();
        $this->assertSame($n, $count());

        foreach ([$this->adminNm, $this->auditorNm] as $u) {
            $this->assertSame(403, $this->req($u, 'POST', "projects/{$this->p2}/quotes", ['customer_id' => 1])->status(), 'estimate_management R is below S');
        }
        $this->assertContains($this->req($this->est, 'POST', "projects/{$this->p2}/quotes", ['customer_id' => 1])->status(), [403, 404], 'a member-scoped estimator outside P2');
        $this->assertSame($n, $count());
    }

    #[DataProvider('modes')]
    public function test_creating_a_project_enrols_the_creator_and_nobody_else_whoever_creates_it(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->ownerNm, $this->adminNm, $this->est] as $creator) {
            $logs = DB::table('project_member_logs')->count();
            $id = (int) $this->req($creator, 'POST', 'projects', ['name' => 'By '.$creator->id, 'status' => 'active'])->assertStatus(201)->json('project_id');

            $this->assertSame([$creator->id], DB::table('project_members')->where('project_id', $id)->where('is_active', true)->pluck('user_id')->map(fn ($i) => (int) $i)->all());
            $this->assertSame($logs + 1, DB::table('project_member_logs')->count());
            $log = DB::table('project_member_logs')->where('project_id', $id)->first();
            $this->assertSame('added', $log->action);
            $this->assertSame($creator->id, (int) $log->performed_by);
            $this->assertNotContains($id, $this->visibleProjectIds($this->stranger), 'a member-scoped colleague does not see it');
        }

        $projects = DB::table('projects')->count();
        $this->assertSame(403, $this->req($this->auditorNm, 'POST', 'projects', ['name' => 'Auditor project', 'status' => 'active'])->status());
        $this->assertSame($projects, DB::table('projects')->count(), 'read access cannot create');
    }

    #[DataProvider('modes')]
    public function test_the_membership_screens_keep_their_validation_for_an_owner(string $mode): void
    {
        $this->setMode($mode);
        $gone = $this->mkUser($this->orgA, 'viewer_read_only');
        DB::table('user_org_roles')->where('user_id', $gone->id)->update(['is_active' => false]);
        $p1Row = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->value('id');
        $p3Row = (int) DB::table('project_members')->where('project_id', $this->p3)->where('user_id', $this->outsider->id)->value('id');
        $before = $this->state();

        foreach ([$this->outsider, $gone] as $notAMember) {
            $this->req($this->ownerNm, 'POST', "projects/{$this->p2}/members", ['user_id' => $notAMember->id])->assertForbidden();
            $this->req($this->ownerNm, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $notAMember->id])->assertForbidden();
        }
        $this->req($this->ownerNm, 'DELETE', "projects/{$this->p2}/members/$p1Row")->assertNotFound();
        $this->req($this->ownerNm, 'DELETE', "org-admin/projects/members/$p3Row")->assertNotFound();
        $this->req($this->ownerNm, 'POST', 'org-admin/projects/members', ['project_id' => $this->p3, 'user_id' => $this->est->id])->assertNotFound();

        $this->assertSame($before, $this->state(), 'every refusal left the data untouched');
    }

    #[DataProvider('modes')]
    public function test_deleting_a_project_that_only_has_an_rfq_is_still_refused_for_the_owner(string $mode): void
    {
        $this->setMode($mode);
        $empty = $this->mkProject($this->orgA, $this->owner, 'Only an RFQ');
        $this->mkRfq($empty, 'RFQ keeps the project');

        $this->req($this->ownerNm, 'DELETE', "projects/$empty")->assertStatus(422)->assertJson(['message' => 'Delete or move the RFQs in this project first.']);
        $this->assertNull(DB::table('projects')->where('id', $empty)->value('deleted_at'));
    }

    // ---- 4. universal admin and delegation accounting ----------------------------

    public function test_a_listed_user_with_a_member_scoped_role_is_unchanged_and_seesall_has_no_universal_admin_logic(): void
    {
        $ua = $this->est;
        config(['rbac.universal_admin_user_ids' => [$ua->id]]);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;
        app()->instance('request', Request::create('/probe', 'GET'));

        $this->assertFalse($perm->seesAllOrgProjects($ua->id, $a), 'the rule is the role matrix only; the callers keep the universal-admin branch');
        $this->assertTrue($perm->checkPermission($ua->id, $a, 'project_management', 'R', $this->p1));
        $this->assertCount(0, $this->universalRows(), 'allowed by the role and the membership: no bypass row');

        $this->assertTrue($perm->checkPermission($ua->id, $a, 'project_management', 'R', $this->p2));
        $this->assertCount(1, $this->universalRows(), 'allowed only by the bypass: one row');
        $this->assertSame($this->p2, (int) $this->universalRows()->first()->project_id);

        $this->assertSame([$this->p1, $this->p2], $this->visibleProjectIds($ua), 'a listed user lists the whole org, as before');
        $this->assertFalse($perm->checkPermission($ua->id, $a, 'project_management', 'R', $this->p3), 'another org needs an existing project of THIS org');
    }

    #[DataProvider('modes')]
    public function test_a_delegate_of_a_member_scoped_principal_is_checked_as_the_principal(string $mode): void
    {
        $this->setMode($mode);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;
        $d = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->delegate($this->est, $d);

        $this->assertTrue($perm->checkPermission($d->id, $a, 'project_management', 'F', $this->p1), 'the principal is a member of P1 with F');
        $this->assertFalse($perm->checkPermission($d->id, $a, 'project_management', 'R', $this->p2));
        $this->assertFalse($perm->seesAllOrgProjects($d->id, $a));
        $this->assertSame([], $this->visibleProjectIds($d), 'the delegate keeps his own, empty, list');
    }

    #[DataProvider('modes')]
    public function test_an_owner_who_is_a_delegate_of_a_member_scoped_principal_is_checked_as_the_principal_by_the_middleware(string $mode): void
    {
        $this->setMode($mode);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;
        $d = $this->mkUser($this->orgA, 'organization_owner');
        $this->delegate($this->est, $d);

        $this->assertFalse($perm->checkPermission($d->id, $a, 'project_management', 'R', $this->p2), 'effective user is the estimator, who is not in P2');
        $this->assertTrue($perm->checkPermission($d->id, $a, 'project_management', 'R', $this->p1));
        $this->assertTrue($perm->seesAllOrgProjects($d->id, $a), 'his own roles');

        $status = $this->req($d, 'GET', "projects/{$this->p2}")->status();
        $row = AuditLog::where('route_uri', 'projects/{project}')->where('reason', 'no_grant_or_not_project_member')->first();
        $this->assertNotNull($row, 'the principal\'s denial is recorded');
        if ($mode === 'enforce') {
            $this->assertSame(403, $status);
            $this->assertSame('blocked', $row->outcome);
        } else {
            $this->assertSame('would_block', $row->outcome);
        }
    }

    // ---- 5. the dashboard counters ----------------------------------------------

    public function test_dashboard_counters_equal_the_quotes_index_for_member_scoped_and_org_wide_users(): void
    {
        $this->setMode('audit');
        $pm = $this->mkUser($this->orgA, 'project_manager');
        $this->member($this->p1, $pm, $this->orgA);
        $mfr = $this->mkUser($this->orgA, 'manufacturer_admin');
        $mfrQuote = $this->mkQuote($mfr, $this->p2, ['name' => 'Authored by the manufacturer admin']);

        $wholeOrg = $this->ints([$this->q1, $this->q2, $this->q3, $this->pending2, $mfrQuote]);
        foreach ([$this->ownerNm, $this->adminNm, $this->auditorNm] as $u) {
            $d = $this->dash($u);
            $this->assertSame($wholeOrg, $d['recent'], 'every estimate of the org, nothing from Q0 (NULL project) or Q4 (org B)');
            $this->assertSame(count($wholeOrg), $d['count']);
            $this->assertSame($d['count'], $d['view']);
            $this->assertSame($d['count'], $this->quotesIndexTotal($u));
        }

        foreach ([$this->est, $pm] as $u) {
            $d = $this->dash($u);
            $this->assertSame($d['count'], $d['view']);
            $this->assertSame($d['count'], $this->quotesIndexTotal($u));
            $this->assertSame([], array_diff($d['recent'], [$this->q1, $this->q2]), 'only P1');
        }

        $d = $this->dash($mfr);
        $this->assertSame([$mfrQuote], $d['recent'], 'no estimate group: own estimates only, on the dashboard and the index alike');
        $this->assertSame(1, $d['count']);
        $this->assertSame(1, $this->quotesIndexTotal($mfr));
    }

    // ---- 6. org-wide roles through the routes ------------------------------------

    public function test_manufacturer_admin_sees_the_projects_but_holds_neither_project_nor_estimate_rights(): void
    {
        $mfr = $this->mkUser($this->orgA, 'manufacturer_admin');
        $a = $this->orgA->id;
        $perm = app(PermissionService::class);

        $this->assertTrue($perm->seesAllOrgProjects($mfr->id, $a));
        $this->assertSame([$this->p1, $this->p2], $this->visibleProjectIds($mfr));
        $this->assertFalse($perm->checkPermission($mfr->id, $a, 'estimate_management', 'R', $this->p2), 'no estimate group');
        $this->assertFalse($perm->checkPermission($mfr->id, $a, 'project_management', 'R', $this->p2), 'no project group');

        $this->setMode('audit');
        $this->assertSame([$this->p1, $this->p2], $this->ints(array_column($this->req($mfr, 'GET', 'projects/list')->assertOk()->json('projects'), 'id')));
        $this->assertSame([], $this->ints(array_column($this->req($mfr, 'GET', 'quotes/list')->assertOk()->json('quotes'), 'id')), 'visible projects, but no estimate group: own estimates only');

        $this->setMode('enforce');
        $this->req($mfr, 'GET', "projects/{$this->p2}")->assertForbidden();
        $this->req($mfr, 'PUT', "projects/{$this->p2}", ['name' => 'Hijacked', 'status' => 'active'])->assertForbidden();
        $this->assertSame('P2', DB::table('projects')->where('id', $this->p2)->value('name'));
    }

    #[DataProvider('modes')]
    public function test_a_union_of_roles_is_org_wide_until_the_granting_role_is_deactivated(string $mode): void
    {
        $this->setMode($mode);
        $u = $this->mkUser($this->orgA, 'estimator');
        $this->member($this->p1, $u, $this->orgA);
        $this->assignRole($u, $this->orgA, 'organization_admin');

        $this->assertSame([$this->p1, $this->p2], $this->ints(array_column($this->req($u, 'GET', 'projects/list')->assertOk()->json('projects'), 'id')));

        DB::table('user_org_roles')->where('user_id', $u->id)
            ->where('role_id', DB::table('roles')->where('slug', 'organization_admin')->value('id'))->update(['is_active' => false]);

        $this->assertSame([$this->p1], $this->ints(array_column($this->req($u, 'GET', 'projects/list')->assertOk()->json('projects'), 'id')));
        $this->assertContains($this->req($u, 'GET', "projects/{$this->p2}")->status(), [403, 404]);
    }

    // ---- 7. levels A / O / S on organization_management (no seeded role holds them) ---

    public static function levelsBelowFull(): array
    {
        return ['A' => ['A'], 'O' => ['O'], 'S' => ['S'], 'R' => ['R']];
    }

    #[DataProvider('levelsBelowFull')]
    public function test_a_role_holding_organization_management_below_full_stays_membership_scoped(string $level): void
    {
        $this->probeRole("probe_org_mgmt_$level", $level);
        $u = $this->mkUser($this->orgA, "probe_org_mgmt_$level");
        $this->member($this->p1, $u, $this->orgA);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;

        $this->assertFalse($perm->seesAllOrgProjects($u->id, $a));
        $this->assertSame([$this->p1], $this->visibleProjectIds($u));
        $this->assertTrue($perm->checkPermission($u->id, $a, 'organization_management', $level), 'the probe role really holds the level');
        $this->assertTrue($perm->checkPermission($u->id, $a, 'organization_management', $level, $this->p1));
        $this->assertFalse($perm->checkPermission($u->id, $a, 'organization_management', $level, $this->p2), 'not a member of P2');
    }

    public function test_control_a_probe_role_holding_organization_management_full_is_org_wide(): void
    {
        $this->probeRole('probe_org_mgmt_F', 'F');
        $u = $this->mkUser($this->orgA, 'probe_org_mgmt_F');

        $this->assertTrue(app(PermissionService::class)->seesAllOrgProjects($u->id, $this->orgA->id), 'proves the injected matrix row is read');
        $this->assertSame([$this->p1, $this->p2], $this->visibleProjectIds($u));
        $this->assertTrue(app(PermissionService::class)->checkPermission($u->id, $this->orgA->id, 'organization_management', 'R', $this->p2));
    }

    private function probeRole(string $slug, string $level): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => $slug, 'slug' => $slug, 'category' => 'Organization', 'phase' => 'P1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $roleId, 'permission_group_id' => DB::table('permission_groups')->where('slug', 'organization_management')->value('id'),
            'access_level' => $level, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PermissionMatrix::class)->flush();
        Cache::forget('rbac.permission_matrix.v1');
        app()->forgetInstance(PermissionMatrix::class);
        app()->forgetInstance(PermissionService::class);
    }
}

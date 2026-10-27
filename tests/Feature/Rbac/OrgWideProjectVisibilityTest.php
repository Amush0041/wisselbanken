<?php

namespace Tests\Feature\Rbac;

use App\Models\Project;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\RbacSetting;
use App\Models\User;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\UniversalAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * W10 (PLAN 10.14): a user whose OWN active roles grant organization_management F sees every live project of
 * the acting org without a project_members row. Visibility only: writes still need their group level, nobody
 * is auto-enrolled, delegation is never resolved for the rule, other orgs and sellers see nothing.
 *
 * Base fixture (ProjectTestCase): P1, P2 in org A, P3 in org B; Q0 (NULL project), Q1, Q2 (P1), Q3 (P2), Q4 (P3);
 * est is a member of P1 only, stranger of nothing. None of the actors added here is enrolled anywhere.
 */
class OrgWideProjectVisibilityTest extends ProjectTestCase
{
    private const SECRET = 'Confidential Skyline Tower';

    /** organization_owner in A */
    private User $ownerNm;
    /** organization_admin in A (project_management F, estimate_management R) */
    private User $adminNm;
    /** auditor_read_all in A (organization_management F, everything else R) */
    private User $auditorNm;
    /** project_manager in A (project_management F, no organization_management) */
    private User $pmNm;
    /** procurement_manager in A (organization_management R, project_management R) */
    private User $procNm;
    /** organization_owner in B */
    private User $ownerB;
    private $sellerOrg;
    /** organization_owner of the seller org */
    private User $sellerOwner;

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
        $this->pmNm = $this->mkUser($this->orgA, 'project_manager');
        $this->procNm = $this->mkUser($this->orgA, 'procurement_manager');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');

        $this->sellerOrg = $this->mkOrg('Seller Org');
        $this->sellerOwner = $this->mkUser($this->sellerOrg, 'organization_owner');
        DB::table('org_relationships')->insert([
            'from_org_id' => $this->orgA->id, 'to_org_id' => $this->sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    // ---- helpers ----------------------------------------------------------------

    private function req(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        $session = $org ? [config('rbac.current_org_session_key') => $org] : [];

        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function projectIds(User $u, ?int $org = null): array
    {
        $ids = array_map('intval', array_column($this->req($u, 'GET', 'projects/list', [], $org)->assertOk()->json('projects'), 'id'));
        sort($ids);

        return $ids;
    }

    private function quoteIds(User $u, ?int $org = null): array
    {
        $ids = array_map('intval', array_column($this->req($u, 'GET', 'quotes/list', [], $org)->assertOk()->json('quotes'), 'id'));
        sort($ids);

        return $ids;
    }

    private function hiddenStatus(string $mode): int
    {
        return $mode === 'enforce' ? 403 : 404;
    }

    private function projectName(int $id): ?string
    {
        return DB::table('projects')->where('id', $id)->value('name');
    }

    private function mkRfq(?int $projectId, string $title, User $creator): int
    {
        return (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $this->orgA->id, 'project_id' => $projectId, 'created_by' => $creator->id,
            'title' => $title, 'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function recipient(int $rfqId, int $sellerOrgId): void
    {
        DB::table('rfq_recipients')->insert([
            'rfq_request_id' => $rfqId, 'seller_org_id' => $sellerOrgId, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function delegate(User $from, User $to): Delegation
    {
        return Delegation::create([
            'from_user_id' => $from->id, 'to_user_id' => $to->id, 'org_id' => $this->orgA->id, 'granted_by' => $from->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);
    }

    private function universalRows()
    {
        return AuditLog::where('outcome', 'allowed_universal_admin')->orderBy('id')->get();
    }

    // ---- who gets the rule ---------------------------------------------------------

    public function test_exactly_the_five_seeded_organization_management_f_roles_see_every_project_of_their_own_org(): void
    {
        $perm = app(PermissionService::class);
        $granted = ['organization_owner', 'organization_admin', 'auditor_read_all', 'manufacturer_admin', 'platform_super_admin'];

        foreach (DB::table('roles')->pluck('slug') as $slug) {
            $u = $this->mkUser($this->orgA, $slug);

            $this->assertSame(in_array($slug, $granted, true), $perm->seesAllOrgProjects($u->id, $this->orgA->id), $slug);
            $this->assertFalse($perm->seesAllOrgProjects($u->id, $this->orgB->id), "$slug has no role in org B");
        }

        $union = $this->mkUser($this->orgA, 'estimator');
        $this->assignRole($union, $this->orgA, 'organization_admin');
        $this->assertTrue($perm->seesAllOrgProjects($union->id, $this->orgA->id), 'any one granting role is enough');

        $none = $this->mkUser($this->orgA, 'estimator');
        $this->assignRole($none, $this->orgA, 'procurement_manager');
        $this->assertFalse($perm->seesAllOrgProjects($none->id, $this->orgA->id), 'organization_management R is not enough');
    }

    // ---- T1 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t1_the_owner_lists_opens_and_edits_a_project_they_were_never_enrolled_in(string $mode): void
    {
        $this->setMode($mode);
        DB::table('projects')->where('id', $this->p2)->update(['name' => 'Harbor Crossing']);
        DB::table('projects')->where('id', $this->p3)->update(['name' => 'Orchard Row']);

        $this->assertSame([$this->p1, $this->p2], $this->projectIds($this->ownerNm));

        $page = $this->actingAs($this->ownerNm)->get('projects')->assertOk();
        $page->assertSee('Harbor Crossing')->assertDontSee('Orchard Row');
        $this->assertSame([$this->p1, $this->p2], $page->viewData('projects')->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all());

        $this->req($this->ownerNm, 'GET', "projects/{$this->p2}")->assertOk();
        $this->req($this->ownerNm, 'PUT', "projects/{$this->p2}", ['name' => 'Renamed by owner', 'status' => 'active'])->assertOk();

        $this->assertSame('Renamed by owner', $this->projectName($this->p2));
        $this->assertSame(0, AuditLog::count(), 'the middleware itself passes the owner, in both modes');
        $this->assertSame(0, DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $this->ownerNm->id)->count());
    }

    #[DataProvider('modes')]
    public function test_t1b_a_project_an_estimator_creates_is_visible_to_the_owner_and_the_owner_is_not_enrolled(string $mode): void
    {
        $this->setMode($mode);

        $id = (int) $this->req($this->est, 'POST', 'projects', ['name' => 'Project 4', 'status' => 'active'])->assertStatus(201)->json('project_id');
        $members = fn () => DB::table('project_members')->where('project_id', $id)->where('is_active', true)->pluck('user_id')->map(fn ($i) => (int) $i)->all();
        $this->assertSame([$this->est->id], $members());
        $logs = DB::table('project_member_logs')->count();
        $this->assertSame(1, $logs, 'the creator enrolment');

        $this->assertContains($id, $this->projectIds($this->ownerNm));
        $this->req($this->ownerNm, 'GET', "projects/$id")->assertOk();
        $this->req($this->ownerNm, 'PUT', "projects/$id", ['name' => 'Project 4 edited', 'status' => 'on_hold'])->assertOk();

        $this->assertSame([$this->est->id], $members(), 'the owner is not auto-enrolled');
        $this->assertSame($logs, DB::table('project_member_logs')->count());
        $this->assertSame('Project 4 edited', $this->projectName($id));
    }

    // ---- T2 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t2_the_owner_reads_and_edits_the_quotes_of_those_projects_and_no_others(string $mode): void
    {
        $this->setMode($mode);

        $this->assertSame([$this->q1, $this->q2, $this->q3], $this->quoteIds($this->ownerNm));

        $this->req($this->ownerNm, 'GET', "quotes/{$this->q3}/details")->assertOk();
        $this->req($this->ownerNm, 'PUT', "quotes/{$this->q3}", ['notes' => 'owner note'])->assertOk();
        $this->assertSame('owner note', DB::table('quotes')->where('id', $this->q3)->value('notes'));

        $this->req($this->ownerNm, 'GET', "quotes/{$this->q0}/details")->assertNotFound();
        $this->req($this->ownerNm, 'GET', "quotes/{$this->q4}/details")->assertNotFound();
        $this->req($this->ownerNm, 'GET', "projects/{$this->p3}")->assertNotFound();
        $this->req($this->ownerNm, 'PUT', "projects/{$this->p3}", ['name' => 'Hijacked', 'status' => 'active'])->assertNotFound();
        $this->assertSame('P3', $this->projectName($this->p3));
    }

    // ---- T3 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t3_visibility_is_not_authority(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->adminNm, $this->auditorNm] as $u) {
            $this->assertSame([$this->p1, $this->p2], $this->projectIds($u));
            $this->assertSame([$this->q1, $this->q2, $this->q3], $this->quoteIds($u));
            $this->req($u, 'GET', "quotes/{$this->q3}/details")->assertOk();
        }

        $this->req($this->auditorNm, 'PUT', "projects/{$this->p2}", ['name' => 'Auditor edit', 'status' => 'active'])->assertForbidden();
        $this->assertSame('P2', $this->projectName($this->p2));

        $this->req($this->adminNm, 'PUT', "projects/{$this->p2}", ['name' => 'Admin edit', 'status' => 'active'])->assertOk();
        $this->assertSame('Admin edit', $this->projectName($this->p2));

        $this->req($this->adminNm, 'PUT', "quotes/{$this->q3}", ['notes' => 'admin note'])->assertStatus($this->hiddenStatus($mode));
        $this->assertNull(DB::table('quotes')->where('id', $this->q3)->value('notes'));
    }

    // ---- T4 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t4_member_scoped_roles_keep_their_membership_scope(string $mode): void
    {
        $this->setMode($mode);

        $this->assertSame([$this->p1], $this->projectIds($this->est));
        foreach ([$this->pmNm, $this->procNm, $this->stranger] as $u) {
            $this->assertSame([], $this->projectIds($u));
        }
        foreach ([$this->est, $this->stranger, $this->pmNm, $this->procNm] as $u) {
            $this->req($u, 'GET', "projects/{$this->p2}")->assertStatus($this->hiddenStatus($mode));
        }
        $this->assertSame([], $this->quoteIds($this->pmNm));
    }

    // ---- T5 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t5_the_owner_of_another_org_sees_only_their_own_org(string $mode): void
    {
        $this->setMode($mode);

        $this->assertSame([$this->p3], $this->projectIds($this->ownerB));
        $this->assertSame([$this->q4], $this->quoteIds($this->ownerB));
        $this->req($this->ownerB, 'GET', "projects/{$this->p1}")->assertNotFound();
        $this->req($this->ownerB, 'PUT', "projects/{$this->p1}", ['name' => 'Hijacked', 'status' => 'active'])->assertNotFound();
        $this->req($this->ownerB, 'GET', "quotes/{$this->q1}/details")->assertNotFound();
        $this->assertSame('P1', $this->projectName($this->p1));
    }

    #[DataProvider('modes')]
    public function test_t5_a_seller_org_owner_sees_nothing_of_the_buyer_org(string $mode): void
    {
        $this->setMode($mode);

        $this->assertSame([], $this->projectIds($this->sellerOwner));
        $this->assertSame([], $this->quoteIds($this->sellerOwner));
        $this->req($this->sellerOwner, 'GET', "projects/{$this->p1}")->assertNotFound();
        $this->req($this->sellerOwner, 'PUT', "projects/{$this->p1}", ['name' => 'Hijacked', 'status' => 'active'])->assertNotFound();
        $this->req($this->sellerOwner, 'GET', "quotes/{$this->q1}/details")->assertNotFound();
        $this->assertSame('P1', $this->projectName($this->p1));
    }

    #[DataProvider('modes')]
    public function test_t5_a_seller_never_sees_the_buyers_project_on_a_received_rfq(string $mode): void
    {
        $this->setMode($mode);
        $secret = $this->mkProject($this->orgA, $this->est, self::SECRET);
        $rfq = $this->mkRfq($secret, 'Steel bid package', $this->est);
        $this->recipient($rfq, $this->sellerOrg->id);

        $incoming = $this->actingAs($this->sellerOwner)->get('rfq-incoming')->assertOk();
        $this->assertSame([$rfq], $incoming->viewData('recipients')->pluck('rfq_request_id')->map(fn ($i) => (int) $i)->all());
        $this->assertStringNotContainsString(self::SECRET, $incoming->getContent());

        $this->req($this->sellerOwner, 'GET', "rfq/$rfq")->assertNotFound();
        $this->req($this->sellerOwner, 'GET', "projects/$secret")->assertNotFound();
        $this->assertSame([], $this->projectIds($this->sellerOwner));
    }

    #[DataProvider('modes')]
    public function test_t5_a_multi_org_owner_is_org_wide_only_in_the_org_that_grants_it(string $mode): void
    {
        $this->setMode($mode);
        $multi = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($multi, $this->orgB, 'viewer_read_only');
        $this->member($this->p3, $multi, $this->orgB);
        $perm = app(PermissionService::class);

        $this->assertSame([$this->p1, $this->p2], $this->projectIds($multi, $this->orgA->id));
        $this->assertSame([$this->p3], $this->projectIds($multi, $this->orgB->id));
        $this->req($multi, 'GET', "projects/{$this->p1}", [], $this->orgB->id)->assertNotFound();
        $this->req($multi, 'GET', "projects/{$this->p2}", [], $this->orgA->id)->assertOk();

        $this->assertTrue($perm->seesAllOrgProjects($multi->id, $this->orgA->id));
        $this->assertFalse($perm->seesAllOrgProjects($multi->id, $this->orgB->id));
        $this->assertFalse($perm->checkPermission($multi->id, $this->orgB->id, 'project_management', 'R', $this->p1));
    }

    // ---- T6 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t6_a_soft_deleted_project_stays_invisible_to_the_owner(string $mode): void
    {
        $this->setMode($mode);
        $gone = $this->mkProject($this->orgA, $this->owner, 'Gone', now()->toDateTimeString());
        $goneQuote = $this->mkQuote($this->owner, $gone, ['name' => 'Quote in a deleted project']);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;

        $this->req($this->ownerNm, 'GET', "projects/$gone")->assertNotFound();
        $this->req($this->ownerNm, 'GET', "quotes/$goneQuote/details")->assertNotFound();
        $this->assertSame([$this->p1, $this->p2], $this->projectIds($this->ownerNm));
        $this->assertNotContains($goneQuote, $this->quoteIds($this->ownerNm));

        $this->assertFalse($perm->checkPermission($this->ownerNm->id, $a, 'project_management', 'R', $gone));
        $this->assertTrue($perm->checkPermission($this->ownerNm->id, $a, 'project_management', 'R', $this->p2));
        $this->assertFalse($perm->checkPermission($this->ownerNm->id, $a, 'project_management', 'R', $this->p3), 'P3 is in org B');
        $this->assertFalse($perm->checkPermission($this->ownerNm->id, $a, 'project_management', 'R', 99999));
    }

    // ---- T7 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t7_a_deactivated_owner_role_grants_nothing(string $mode): void
    {
        $this->setMode($mode);
        DB::table('user_org_roles')->where('user_id', $this->ownerNm->id)->update(['is_active' => false]);

        $this->assertFalse(app(PermissionService::class)->seesAllOrgProjects($this->ownerNm->id, $this->orgA->id));
        $this->assertSame(0, Project::visibleTo($this->ownerNm->id, $this->orgA->id)->count());
        $this->req($this->ownerNm, 'GET', "projects/{$this->p2}", [], $this->orgA->id)->assertStatus($this->hiddenStatus($mode));
    }

    // ---- T8 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t8_project_param_and_quote_param_routes_pass_for_the_owner_without_an_audit_row(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->ownerNm, 'GET', "projects/{$this->p2}")->assertOk();
        $this->req($this->ownerNm, 'PUT', "projects/{$this->p2}", ['name' => 'P2 edited', 'status' => 'active'])->assertOk();
        $this->req($this->ownerNm, 'GET', "quotes/{$this->q3}/details")->assertOk();
        $this->req($this->ownerNm, 'PUT', "quotes/{$this->q3}", ['notes' => 'n'])->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_t8_per_org_enforcement_passes_the_owner_and_still_blocks_a_non_member(): void
    {
        $this->setMode('audit');
        RbacSetting::set('rbac_enforced_org_ids', json_encode([$this->orgA->id]));

        $this->req($this->ownerNm, 'GET', "projects/{$this->p2}")->assertOk();
        $this->req($this->ownerNm, 'PUT', "quotes/{$this->q3}", ['notes' => 'n'])->assertOk();
        $this->assertSame(0, AuditLog::count());

        $this->req($this->est, 'GET', "projects/{$this->p2}")->assertForbidden();
        $this->assertSame(['blocked'], AuditLog::pluck('outcome')->all());
    }

    // ---- T9 ------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t9_a_delegate_of_the_owner_keeps_his_own_list_and_delegation_is_not_transitive(string $mode): void
    {
        $this->setMode($mode);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;
        $d1 = $this->viewer;
        $this->delegate($this->ownerNm, $d1);

        $this->assertSame([$this->p1], $this->projectIds($d1), 'the delegate list is not widened');
        $this->req($d1, 'GET', "projects/{$this->p2}")->assertNotFound();
        $this->req($d1, 'GET', "quotes/{$this->q3}/details")->assertNotFound();
        $this->assertTrue($perm->checkPermission($d1->id, $a, 'project_management', 'R', $this->p2), 'checked as the principal');
        $this->assertFalse($perm->checkPermission($d1->id, $a, 'project_management', 'R', $this->p3));
        $this->assertFalse($perm->seesAllOrgProjects($d1->id, $a));

        $d2 = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->delegate($d1, $d2);

        $this->assertSame([], $this->projectIds($d2));
        $this->assertFalse($perm->checkPermission($d2->id, $a, 'project_management', 'R', $this->p2), 'a delegation from a delegate is not a second hop');
        $this->req($d2, 'GET', "projects/{$this->p2}")->assertStatus($this->hiddenStatus($mode));
    }

    // ---- T10 -----------------------------------------------------------------------

    public function test_t10_a_listed_user_without_a_role_is_unchanged_and_one_with_the_owner_role_writes_no_bypass_row(): void
    {
        $ua = User::factory()->create(['role' => 'user']);
        config(['rbac.universal_admin_user_ids' => [$ua->id]]);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;

        app()->instance('request', Request::create('/probe', 'GET'));
        $this->assertTrue($perm->checkPermission($ua->id, $a, 'project_management', 'R', $this->p2));
        $this->assertCount(1, $this->universalRows(), 'no role: one bypass row per check, as before');
        $this->assertSame($this->p2, (int) $this->universalRows()->first()->project_id);

        $this->assertSame([$this->p1, $this->p2], $this->projectIds($ua, $a));

        AuditLog::query()->delete();
        config(['rbac.universal_admin_user_ids' => [$this->ownerNm->id]]);
        app()->instance('request', Request::create('/probe', 'GET'));
        $this->assertTrue($perm->checkPermission($this->ownerNm->id, $a, 'project_management', 'R', $this->p2));
        $this->assertCount(0, $this->universalRows(), 'the role grants it: no bypass row');
        $this->assertSame([$this->p1, $this->p2], $this->projectIds($this->ownerNm, $a));
    }

    #[DataProvider('modes')]
    public function test_t10_a_non_listed_owner_is_not_a_universal_admin(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [User::factory()->create(['role' => 'user'])->id]]);

        $this->assertFalse(UniversalAdmin::is($this->ownerNm->id));
        $this->assertSame([$this->p1, $this->p2], $this->projectIds($this->ownerNm));
        $this->assertSame([$this->p3], $this->projectIds($this->ownerB));
        $this->req($this->ownerNm, 'GET', "projects/{$this->p3}")->assertNotFound();
        $this->assertCount(0, $this->universalRows());
    }

    // ---- T11 -----------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t11_the_owner_sees_the_rfqs_of_every_project_and_a_non_member_does_not(string $mode): void
    {
        $this->setMode($mode);
        $onP2 = $this->mkRfq($this->p2, 'Rfq on P2', $this->est);
        $legacy = $this->mkRfq(null, 'Legacy rfq', $this->est);
        $this->recipient($onP2, $this->sellerOrg->id);
        $this->recipient($legacy, $this->sellerOrg->id);
        $ids = fn ($response) => $response->viewData('rfqs')->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $this->assertSame([$onP2, $legacy], $ids($this->actingAs($this->ownerNm)->get('rfq')->assertOk()));
        $this->req($this->ownerNm, 'GET', "rfq/$onP2")->assertOk();

        $this->assertSame([$legacy], $ids($this->actingAs($this->stranger)->get('rfq')->assertOk()));
        $this->req($this->stranger, 'GET', "rfq/$onP2")->assertNotFound();
    }

    // ---- T12 -----------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t12_the_membership_screens_work_for_the_owner_and_never_enrol_them(string $mode): void
    {
        $this->setMode($mode);
        $logs = fn () => DB::table('project_member_logs')->where('project_id', $this->p2)->orderBy('id')->get();
        $memberRow = fn (User $u) => DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $u->id)->first();

        $this->req($this->ownerNm, 'POST', "projects/{$this->p2}/members", ['user_id' => $this->est->id])->assertOk();
        $this->assertSame(['added'], $logs()->pluck('action')->all());
        $this->assertSame($this->ownerNm->id, (int) $logs()->first()->performed_by);

        $this->actingAs($this->ownerNm)->post('org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->viewer->id])
            ->assertRedirect()->assertSessionHas('success', 'Member added to project.');
        $this->assertSame(['added', 'added'], $logs()->pluck('action')->all());

        $estRow = $memberRow($this->est)->id;
        $viewerRow = $memberRow($this->viewer)->id;

        $this->req($this->ownerNm, 'DELETE', "projects/{$this->p2}/members/$estRow")->assertOk();
        $this->assertFalse((bool) $memberRow($this->est)->is_active, 'the row is kept and deactivated');

        $this->actingAs($this->ownerNm)->delete("org-admin/projects/members/$viewerRow")
            ->assertRedirect()->assertSessionHas('success', 'Member removed from project.');
        $this->assertFalse((bool) $memberRow($this->viewer)->is_active);
        $this->assertSame(['added', 'added', 'removed', 'removed'], $logs()->pluck('action')->all());

        $this->assertNull($memberRow($this->ownerNm), 'the owner is still not a member of P2');
    }

    #[DataProvider('modes')]
    public function test_t12_a_project_management_f_holder_who_is_not_in_the_project_still_cannot_manage_its_members(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->stranger, 'POST', "projects/{$this->p2}/members", ['user_id' => $this->viewer->id])->assertStatus($this->hiddenStatus($mode));

        $this->assertSame(0, DB::table('project_member_logs')->count());
        $this->assertSame(0, DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $this->viewer->id)->count());
    }

    // ---- T13 -----------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_t13_deleting_a_project_keeps_its_refusals_and_the_owner_may_delete_an_empty_one(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->ownerNm, 'DELETE', "projects/{$this->p2}")
            ->assertStatus(422)->assertJson(['message' => 'Delete or move the estimates in this project first.']);
        $this->assertNull(DB::table('projects')->where('id', $this->p2)->value('deleted_at'));

        $empty = (int) $this->req($this->est, 'POST', 'projects', ['name' => 'Empty project', 'status' => 'active'])->assertStatus(201)->json('project_id');
        $this->req($this->ownerNm, 'DELETE', "projects/$empty")->assertOk();
        $this->assertNotNull(DB::table('projects')->where('id', $empty)->value('deleted_at'));
    }
}

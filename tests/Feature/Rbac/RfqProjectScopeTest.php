<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Services\Rbac\ApprovalRoutingService;
use App\Services\Rbac\PermissionMatrix;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * RFQ scope-document requirements (plan 6.6): G1 approval routing on convert, G3 quote_rfq_management F
 * plus procurement S on convert, G2 RFQs belong to a project (visibility, seller isolation, project deletion
 * refusal) and the server-side level mirrors that hold in audit mode as well as enforce mode.
 */
class RfqProjectScopeTest extends ProjectTestCase
{
    private const SECRET = 'Confidential Skyline Tower';

    /** project_manager (F/F) in A, member of P1 */
    private User $pm;
    /** procurement_coordinator (rfq F, procurement O) in A, member of P1 */
    private User $coord;
    /** requisitioner (S/S) in A, member of P1 */
    private User $requisitioner;
    /** sales_rep (rfq F, no procurement grant) in A, member of P1 */
    private User $salesRep;
    /** procurement_manager (F/F, approval_authority A) in A, member of P1 */
    private User $procMgr;
    /** organization_owner (F/F, approval_authority A) in A, member of P1 */
    private User $orgOwner;
    /** sales_rep (rfq F) in the seller org */
    private User $sellerRep;
    /** viewer_read_only in the seller org */
    private User $sellerViewer;
    /** superintendent (no rfq grant) in the seller org */
    private User $sellerNone;

    private $sellerOrg;
    /** projects.id of SECRET (org A; est, pm, coord, requisitioner, salesRep, procMgr, orgOwner are members) */
    private int $secret;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('org_id')->nullable();
            $t->string('order_number')->unique();
            $t->string('name')->nullable();
            $t->string('project_title')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('company')->nullable();
            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postcode')->nullable();
            $t->text('notes')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('tax', 10, 2)->default(0);
            $t->decimal('tax_rate', 5, 3)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->unsignedBigInteger('rejected_by')->nullable();
            $t->text('approval_note')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->timestamps();
        });

        $this->pm = $this->mkUser($this->orgA, 'project_manager');
        $this->coord = $this->mkUser($this->orgA, 'procurement_coordinator');
        $this->requisitioner = $this->mkUser($this->orgA, 'requisitioner');
        $this->salesRep = $this->mkUser($this->orgA, 'sales_rep');
        $this->procMgr = $this->mkUser($this->orgA, 'procurement_manager');

        $this->sellerOrg = $this->mkOrg('Seller Org');
        $this->sellerRep = $this->mkUser($this->sellerOrg, 'sales_rep');
        $this->sellerViewer = $this->mkUser($this->sellerOrg, 'viewer_read_only');
        $this->sellerNone = $this->mkUser($this->sellerOrg, 'superintendent');

        foreach ([$this->pm, $this->coord, $this->requisitioner, $this->salesRep, $this->procMgr] as $u) {
            $this->member($this->p1, $u, $this->orgA);
        }

        DB::table('org_relationships')->insert([
            'from_org_id' => $this->orgA->id, 'to_org_id' => $this->sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->secret = $this->mkProject($this->orgA, $this->owner, self::SECRET);
        foreach ([$this->est, $this->pm, $this->coord, $this->requisitioner, $this->salesRep, $this->procMgr, $this->owner] as $u) {
            $this->member($this->secret, $u, $this->orgA);
        }

        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    // ---- helpers ----------------------------------------------------------------

    private function req(User $u, string $method, string $uri, array $data = [], array $session = [])
    {
        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function orgSession(int $orgId): array
    {
        return [config('rbac.current_org_session_key') => $orgId];
    }

    private function mkRfq(?int $projectId, ?User $creator = null, array $attrs = []): int
    {
        return (int) DB::table('rfq_requests')->insertGetId($attrs + [
            'org_id' => $this->orgA->id, 'project_id' => $projectId, 'created_by' => ($creator ?? $this->est)->id,
            'title' => 'RFQ '.uniqid(), 'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function recipient(int $rfqId, ?int $sellerOrgId = null, string $status = 'pending'): int
    {
        return (int) DB::table('rfq_recipients')->insertGetId([
            'rfq_request_id' => $rfqId, 'seller_org_id' => $sellerOrgId ?? $this->sellerOrg->id, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function response(int $rfqId, string $status = 'pending_review', float $price = 250.00): int
    {
        return (int) DB::table('rfq_responses')->insertGetId([
            'rfq_request_id' => $rfqId, 'seller_org_id' => $this->sellerOrg->id, 'created_by' => $this->sellerRep->id,
            'total_price' => $price, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** An RFQ with a selected response, ready to convert. */
    private function convertible(?int $projectId): int
    {
        $rfq = $this->mkRfq($projectId, null, ['status' => 'closed']);
        $this->recipient($rfq, null, 'responded');
        $this->response($rfq, 'selected');

        return $rfq;
    }

    /** An RFQ sent to the seller org with one pending recipient row. */
    private function sent(?int $projectId): int
    {
        $rfq = $this->mkRfq($projectId);
        $this->recipient($rfq);

        return $rfq;
    }

    private function snapshot(): array
    {
        return [
            'rfq_requests' => DB::table('rfq_requests')->orderBy('id')->get(['id', 'project_id', 'status', 'title'])->map(fn ($r) => (array) $r)->all(),
            'rfq_recipients' => DB::table('rfq_recipients')->orderBy('id')->get(['id', 'status'])->map(fn ($r) => (array) $r)->all(),
            'rfq_responses' => DB::table('rfq_responses')->orderBy('id')->get(['id', 'status'])->map(fn ($r) => (array) $r)->all(),
            'orders' => DB::table('orders')->orderBy('id')->get(['id', 'status'])->map(fn ($r) => (array) $r)->all(),
        ];
    }

    private function storePayload(array $over = []): array
    {
        return $over + [
            'project_id' => $this->p1, 'title' => 'New RFQ', 'notes' => 'n',
            'seller_org_ids' => [$this->sellerOrg->id],
        ];
    }

    private function extraApprover(): User
    {
        $u = $this->mkUser($this->orgA, 'organization_owner');
        $this->member($this->p1, $u, $this->orgA);

        return $u;
    }

    // ---- fixture sanity: verify the seeded levels the tests rely on --------------

    public function test_seeded_role_levels_used_by_these_tests(): void
    {
        $level = fn (string $role, string $group) => DB::table('role_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('permission_groups as g', 'g.id', '=', 'rp.permission_group_id')
            ->where('r.slug', $role)->where('g.slug', $group)->value('rp.access_level');

        $this->assertSame(['F', 'F'], [$level('procurement_manager', 'quote_rfq_management'), $level('procurement_manager', 'procurement')]);
        $this->assertSame(['F', 'O'], [$level('procurement_coordinator', 'quote_rfq_management'), $level('procurement_coordinator', 'procurement')]);
        $this->assertSame(['S', 'S'], [$level('requisitioner', 'quote_rfq_management'), $level('requisitioner', 'procurement')]);
        $this->assertSame(['S', 'F'], [$level('estimator', 'quote_rfq_management'), $level('estimator', 'procurement')]);
        $this->assertSame(['R', 'R'], [$level('viewer_read_only', 'quote_rfq_management'), $level('viewer_read_only', 'procurement')]);
        $this->assertSame(['F', null], [$level('sales_rep', 'quote_rfq_management'), $level('sales_rep', 'procurement')]);
    }

    // ---- schema ------------------------------------------------------------------

    public function test_rfq_project_id_is_nullable_indexed_to_projects_and_nulls_on_hard_delete(): void
    {
        $this->assertTrue(Schema::hasColumn('rfq_requests', 'project_id'));

        $legacy = $this->mkRfq(null);
        $doomed = $this->mkProject($this->orgA, $this->owner, 'Doomed');
        $scoped = $this->mkRfq($doomed);
        $this->assertNull(DB::table('rfq_requests')->where('id', $legacy)->value('project_id'));

        DB::table('projects')->where('id', $doomed)->delete();

        $this->assertNull(DB::table('rfq_requests')->where('id', $scoped)->value('project_id'));
        $this->assertSame(1, DB::table('rfq_requests')->where('id', $scoped)->count());
    }

    public function test_migration_up_is_re_runnable_and_down_drops_the_column(): void
    {
        $migration = require database_path('migrations/2026_09_26_000001_add_project_id_to_rfq_requests_table.php');

        $migration->up();
        $this->assertTrue(Schema::hasColumn('rfq_requests', 'project_id'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('rfq_requests', 'project_id'));
        $migration->down();

        $migration->up();
        $this->assertTrue(Schema::hasColumn('rfq_requests', 'project_id'));
    }

    // ---- G1: approval routing on convert -----------------------------------------

    #[DataProvider('modes')]
    public function test_g1_sole_approver_requester_gets_an_auto_approved_pending_order(string $mode): void
    {
        $this->setMode($mode);
        $this->assertSame([$this->procMgr->id], (new ApprovalRoutingService)->approverPool($this->orgA->id));
        $rfq = $this->convertible($this->p1);

        $r = $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq));

        $order = DB::table('orders')->first();
        $r->assertRedirect(route('order.details', $order->id));
        $this->assertSame('pending', $order->status);
        $this->assertSame($this->orgA->id, (int) $order->org_id);
        $this->assertSame($this->procMgr->id, (int) $order->user_id);
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
        $r->assertSessionHas('success', 'RFQ converted to order #'.$order->order_number.'.');
    }

    #[DataProvider('modes')]
    public function test_g1_with_several_approvers_the_order_is_pending_approval_and_shows_in_the_queue(string $mode): void
    {
        $this->setMode($mode);
        $other = $this->extraApprover();
        $rfq = $this->convertible($this->p1);

        $r = $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq));

        $order = DB::table('orders')->first();
        $this->assertSame('pending_approval', $order->status);
        $r->assertRedirect(route('order.details', $order->id));
        $r->assertSessionHas('success', 'RFQ converted to order #'.$order->order_number.' and submitted for approval by your organization.');
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));

        $queue = $this->actingAs($other)->get(route('order.approvals'))->assertOk()->viewData('pendingOrders');
        $this->assertSame([(int) $order->id], $queue->pluck('id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_g1_orders_in_other_orgs_or_other_statuses_stay_out_of_the_approver_queue(): void
    {
        $other = $this->extraApprover();
        $rfq = $this->convertible($this->p1);
        $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq));
        DB::table('orders')->insert([
            'org_id' => $this->orgB->id, 'order_number' => 'ORD-B', 'status' => 'pending_approval', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('orders')->insert([
            'org_id' => $this->orgA->id, 'order_number' => 'ORD-PENDING', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $queue = $this->actingAs($other)->get(route('order.approvals'))->assertOk()->viewData('pendingOrders');

        $this->assertSame(1, $queue->count());
        $this->assertSame($this->orgA->id, (int) $queue->first()->org_id);
    }

    public function test_g1_requester_is_excluded_from_the_approver_pool_and_a_non_approver_routes_to_everyone(): void
    {
        $other = $this->extraApprover();
        $svc = new ApprovalRoutingService;

        $asApprover = $svc->route($this->orgA->id, $this->procMgr->id);
        $this->assertFalse($asApprover['auto_approve']);
        $this->assertSame([$other->id], $asApprover['approver_ids']);

        $asOther = $svc->route($this->orgA->id, $other->id);
        $this->assertFalse($asOther['auto_approve']);
        $this->assertSame([$this->procMgr->id], $asOther['approver_ids']);

        $asCoord = $svc->route($this->orgA->id, $this->coord->id);
        $this->assertFalse($asCoord['auto_approve']);
        $ids = $asCoord['approver_ids'];
        sort($ids);
        $expected = [$this->procMgr->id, $other->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_g1_a_non_approver_requester_with_approvers_present_gets_pending_approval(): void
    {
        $this->extraApprover();
        $rfq = $this->convertible($this->p1);

        $this->actingAs($this->coord)->post(route('rfq.convert', $rfq))->assertRedirect();

        $this->assertSame('pending_approval', DB::table('orders')->value('status'));
    }

    public function test_g1_convert_is_refused_when_the_org_has_no_approver_at_all(): void
    {
        DB::table('user_org_roles')->where('user_id', $this->procMgr->id)->update(['is_active' => false]);
        $this->assertSame([], (new ApprovalRoutingService)->approverPool($this->orgA->id));
        $rfq = $this->convertible($this->p1);

        $this->actingAs($this->pm)->from('/rfq')->post(route('rfq.convert', $rfq))
            ->assertRedirect('/rfq')
            ->assertSessionHas('error', 'Your organization has no one who can approve orders. Ask an organization owner to assign an Executive Approver (or another role with approval authority), then try again.');

        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame('closed', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    // ---- G3: convert needs quote_rfq_management F and procurement S ---------------

    #[DataProvider('modes')]
    public function test_g3_convert_is_allowed_with_both_grants(string $mode): void
    {
        $this->setMode($mode);
        $this->extraApprover();

        foreach ([[$this->pm, 'F/F'], [$this->procMgr, 'F/F'], [$this->coord, 'F/O (O outranks S)']] as [$user, $why]) {
            $rfq = $this->convertible($this->p1);
            $this->actingAs($user)->post(route('rfq.convert', $rfq))->assertRedirect();
            $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'), $why);
        }
        $this->assertSame(3, DB::table('orders')->count());
    }

    #[DataProvider('modes')]
    public function test_g3_convert_is_denied_with_f_but_no_procurement_grant(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible($this->p1);
        $legacy = $this->convertible(null);
        $before = $this->snapshot();

        $this->req($this->salesRep, 'POST', route('rfq.convert', $rfq))->assertForbidden();
        $this->req($this->salesRep, 'POST', route('rfq.convert', $legacy))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_g3_convert_is_denied_with_procurement_s_but_no_f(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible($this->p1);
        $before = $this->snapshot();

        foreach ([$this->requisitioner, $this->est, $this->viewer] as $user) {
            $this->req($user, 'POST', route('rfq.convert', $rfq))->assertForbidden();
        }

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_g3_a_role_with_f_but_procurement_only_at_r_is_denied_via_a_custom_grant(string $mode): void
    {
        $this->setMode($mode);
        $this->grantProcurement('sales_rep', 'R');
        $rfq = $this->convertible($this->p1);
        $before = $this->snapshot();

        $this->req($this->salesRep, 'POST', route('rfq.convert', $rfq))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_g3_the_same_role_with_procurement_at_s_is_allowed_via_a_custom_grant(string $mode): void
    {
        $this->setMode($mode);
        $this->grantProcurement('sales_rep', 'S');
        $rfq = $this->convertible($this->p1);

        $this->actingAs($this->salesRep)->post(route('rfq.convert', $rfq))->assertRedirect();

        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    private function grantProcurement(string $role, string $level): void
    {
        DB::table('role_permissions')->insert([
            'role_id' => DB::table('roles')->where('slug', $role)->value('id'),
            'permission_group_id' => DB::table('permission_groups')->where('slug', 'procurement')->value('id'),
            'access_level' => $level, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PermissionMatrix::class)->flush();
    }

    #[DataProvider('modes')]
    public function test_g3_legacy_null_project_convert_applies_the_same_two_level_rule(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible(null);

        $this->req($this->requisitioner, 'POST', route('rfq.convert', $rfq))->assertForbidden();
        $this->assertSame('closed', DB::table('rfq_requests')->where('id', $rfq)->value('status'));

        $this->actingAs($this->pm)->post(route('rfq.convert', $rfq))->assertRedirect();
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    // ---- G2: create ---------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_g2_store_saves_the_project_and_recipients(string $mode): void
    {
        $this->setMode($mode);

        $this->actingAs($this->est)->post(route('rfq.store'), $this->storePayload())->assertRedirect(route('rfq.index'));

        $rfq = DB::table('rfq_requests')->first();
        $this->assertSame($this->p1, (int) $rfq->project_id);
        $this->assertSame($this->orgA->id, (int) $rfq->org_id);
        $this->assertSame($this->est->id, (int) $rfq->created_by);
        $this->assertSame([$this->sellerOrg->id], DB::table('rfq_recipients')->pluck('seller_org_id')->map(fn ($i) => (int) $i)->all());
    }

    #[DataProvider('modes')]
    public function test_g2_store_rejects_a_missing_or_malformed_project_id(string $mode): void
    {
        $this->setMode($mode);
        $payload = $this->storePayload();

        unset($payload['project_id']);
        $this->req($this->est, 'POST', route('rfq.store'), $payload)->assertStatus(422)->assertJsonValidationErrors('project_id');
        $this->req($this->est, 'POST', route('rfq.store'), $payload + ['project_id' => ''])->assertStatus(422)->assertJsonValidationErrors('project_id');
        $this->req($this->est, 'POST', route('rfq.store'), $payload + ['project_id' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('project_id');
        $this->req($this->est, 'POST', route('rfq.store'), $payload + ['project_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('project_id');

        $this->assertSame(0, DB::table('rfq_requests')->count());
        $this->assertSame(0, DB::table('rfq_recipients')->count());
    }

    #[DataProvider('modes')]
    public function test_g2_store_rejects_a_foreign_org_project(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->est, 'POST', route('rfq.store'), $this->storePayload(['project_id' => $this->p3]))
            ->assertStatus(422)->assertJsonValidationErrors('project_id');
        $this->req($this->outsider, 'POST', route('rfq.store'), $this->storePayload(['project_id' => $this->p1]))
            ->assertStatus(422)->assertJsonValidationErrors('project_id');
        $this->req($this->multi, 'POST', route('rfq.store'), $this->storePayload(['project_id' => $this->p1]), $this->orgSession($this->orgB->id))
            ->assertStatus(422)->assertJsonValidationErrors('project_id');

        $this->assertSame(0, DB::table('rfq_requests')->count());
    }

    #[DataProvider('modes')]
    public function test_g2_store_rejects_a_project_the_user_is_not_a_member_of_or_that_is_trashed(string $mode): void
    {
        $this->setMode($mode);
        $trashed = $this->mkProject($this->orgA, $this->est, 'Trashed', now()->toDateTimeString());
        $this->member($trashed, $this->est, $this->orgA);
        $inactive = $this->mkProject($this->orgA, $this->owner, 'Inactive membership');
        $this->member($inactive, $this->est, $this->orgA, false);

        foreach ([$this->p2, $trashed, $inactive] as $pid) {
            $this->req($this->est, 'POST', route('rfq.store'), $this->storePayload(['project_id' => $pid]))
                ->assertStatus(422)->assertJsonValidationErrors('project_id');
        }
        $this->req($this->stranger, 'POST', route('rfq.store'), $this->storePayload(['project_id' => $this->p1]))
            ->assertStatus(422)->assertJsonValidationErrors('project_id');

        $this->assertSame(0, DB::table('rfq_requests')->count());
    }

    #[DataProvider('modes')]
    public function test_g2_a_project_member_without_s_is_denied_create_and_store(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->viewer, 'POST', route('rfq.store'), $this->storePayload())->assertForbidden();
        $this->req($this->viewer, 'GET', route('rfq.create'))->assertForbidden();
        $this->req($this->super, 'POST', route('rfq.store'), $this->storePayload())->assertForbidden();

        $this->assertSame(0, DB::table('rfq_requests')->count());
    }

    #[DataProvider('modes')]
    public function test_g2_store_denies_a_user_without_an_org(string $mode): void
    {
        $this->setMode($mode);
        $none = $this->mkUser();

        $this->req($none, 'POST', route('rfq.store'), $this->storePayload())->assertForbidden();
        $this->req($none, 'GET', route('rfq.create'))->assertForbidden();
        $this->req($none, 'GET', route('rfq.index'))->assertForbidden();

        $this->assertSame(0, DB::table('rfq_requests')->count());
    }

    public function test_g2_create_form_lists_only_visible_projects_and_preselects_the_query_project(): void
    {
        $r = $this->actingAs($this->est)->get(route('rfq.create').'?project_id='.$this->p1)->assertOk();

        $this->assertSame([$this->p1, $this->secret], $r->viewData('projects')->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all());
        $this->assertSame($this->p1, $r->viewData('selectedProjectId'));
        $html = $r->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$this->p1.'"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="'.$this->secret.'"\s+selected/', $html);
        $this->assertStringNotContainsString('No projects available.', $html);
        $this->assertStringContainsString('name="project_id"', $html);
    }

    public function test_g2_create_form_without_a_query_project_selects_nothing_and_ignores_an_invisible_one(): void
    {
        $html = $this->actingAs($this->est)->get(route('rfq.create'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<option value="\d+"\s+selected/', $html);

        $r = $this->actingAs($this->est)->get(route('rfq.create').'?project_id='.$this->p3)->assertOk();
        $this->assertStringNotContainsString('<option value="'.$this->p3.'"', $r->getContent());
        $this->assertDoesNotMatchRegularExpression('/<option value="\d+"\s+selected/', $r->getContent());
    }

    public function test_g2_create_form_shows_the_no_projects_message_when_the_user_sees_none(): void
    {
        $r = $this->actingAs($this->stranger)->get(route('rfq.create'))->assertOk();

        $r->assertSee('No projects available.', false);
        $r->assertSee('An RFQ must belong to a project.', false);
        $r->assertSee('href="'.route('projects.index').'"', false);
        $r->assertDontSee('name="project_id"', false);
    }

    public function test_g2_store_validation_error_rerenders_the_form_with_the_project_error(): void
    {
        $payload = $this->storePayload();
        unset($payload['project_id']);

        $r = $this->actingAs($this->est)->from(route('rfq.create'))->post(route('rfq.store'), $payload);
        $r->assertRedirect(route('rfq.create'))->assertSessionHasErrors('project_id');

        $this->followingRedirects();
        $this->actingAs($this->est)->from(route('rfq.create'))->post(route('rfq.store'), $payload)->assertOk()->assertSee('invalid-feedback', false);
    }

    // ---- G2: buyer visibility -----------------------------------------------------

    #[DataProvider('modes')]
    public function test_g2_index_hides_rfqs_of_projects_the_user_cannot_see_and_keeps_legacy_rfqs(string $mode): void
    {
        $this->setMode($mode);
        $visible = $this->mkRfq($this->p1, null, ['title' => 'Visible one']);
        $hidden = $this->mkRfq($this->p2, null, ['title' => 'Hidden one']);
        $legacy = $this->mkRfq(null, null, ['title' => 'Legacy one']);
        $foreignOrg = $this->mkRfq($this->p3, $this->outsider, ['org_id' => $this->orgB->id, 'title' => 'Other org one']);

        $ids = fn (User $u) => $this->actingAs($u)->get(route('rfq.index'))->assertOk()->viewData('rfqs')->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $this->assertSame([$visible, $legacy], $ids($this->est));
        $this->assertSame([$legacy], $ids($this->stranger));
        $this->assertSame([$visible, $hidden, $legacy], $ids($this->owner));
        $this->assertSame([$foreignOrg], $ids($this->outsider));

        $html = $this->actingAs($this->est)->get(route('rfq.index'))->getContent();
        $this->assertStringContainsString('Visible one', $html);
        $this->assertStringContainsString('Legacy one', $html);
        $this->assertStringNotContainsString('Hidden one', $html);
        $this->assertStringNotContainsString('Other org one', $html);
    }

    #[DataProvider('modes')]
    public function test_g2_a_non_member_gets_404_on_show_select_and_convert(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->mkRfq($this->p2, $this->owner, ['status' => 'closed']);
        $this->recipient($rfq, null, 'responded');
        $response = $this->response($rfq, 'pending_review');
        $selected = $this->response($rfq, 'selected');
        $before = $this->snapshot();

        foreach ([$this->est, $this->pm, $this->stranger, $this->procMgr] as $user) {
            $this->req($user, 'GET', route('rfq.show', $rfq))->assertNotFound();
        }
        foreach ([$this->pm, $this->coord, $this->procMgr] as $user) {
            $this->req($user, 'POST', route('rfq.response.select', [$rfq, $response]))->assertNotFound();
            $this->req($user, 'POST', route('rfq.convert', $rfq))->assertNotFound();
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(200, $this->req($this->owner, 'GET', route('rfq.show', $rfq))->status());
    }

    #[DataProvider('modes')]
    public function test_g2_an_inactive_project_membership_is_a_404(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible($this->p1);
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->pm->id)->update(['is_active' => false]);

        $this->req($this->pm, 'GET', route('rfq.show', $rfq))->assertNotFound();
        $this->req($this->pm, 'POST', route('rfq.convert', $rfq))->assertNotFound();
        $this->assertSame('closed', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_g2_a_legacy_null_project_rfq_stays_accessible_to_org_users(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->mkRfq(null, null, ['status' => 'closed']);
        $this->recipient($rfq, null, 'responded');
        $pending = $this->response($rfq, 'pending_review');

        $this->actingAs($this->stranger)->get(route('rfq.show', $rfq))->assertOk();
        $this->actingAs($this->viewer)->get(route('rfq.show', $rfq))->assertOk();

        $this->actingAs($this->coord)->post(route('rfq.response.select', [$rfq, $pending]))->assertRedirect(route('rfq.show', $rfq));
        $this->assertSame('selected', DB::table('rfq_responses')->where('id', $pending)->value('status'));

        $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq))->assertRedirect();
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_g2_select_response_by_a_member_at_o_or_more_works_and_rejects_the_others(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->mkRfq($this->p1);
        $this->recipient($rfq, null, 'responded');
        $a = $this->response($rfq, 'pending_review');
        $b = $this->response($rfq, 'pending_review');

        $this->actingAs($this->coord)->post(route('rfq.response.select', [$rfq, $a]))->assertRedirect(route('rfq.show', $rfq));

        $this->assertSame('selected', DB::table('rfq_responses')->where('id', $a)->value('status'));
        $this->assertSame('rejected', DB::table('rfq_responses')->where('id', $b)->value('status'));
        $this->assertSame('closed', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_g2_select_with_a_response_of_another_rfq_is_403_and_changes_nothing(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->mkRfq($this->p1);
        $other = $this->mkRfq($this->p1);
        $foreign = $this->response($other);
        $before = $this->snapshot();

        $this->req($this->coord, 'POST', route('rfq.response.select', [$rfq, $foreign]))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_g2_wrong_org_is_denied_on_show_select_and_convert(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible($this->p1);
        $legacy = $this->convertible(null);
        $resp = $this->response($rfq);
        $before = $this->snapshot();

        foreach ([$rfq, $legacy] as $id) {
            $this->req($this->outsider, 'GET', route('rfq.show', $id))->assertForbidden();
            $this->req($this->outsider, 'POST', route('rfq.convert', $id))->assertForbidden();
            $this->req($this->multi, 'GET', route('rfq.show', $id), [], $this->orgSession($this->orgB->id))->assertForbidden();
        }
        $this->req($this->outsider, 'POST', route('rfq.response.select', [$rfq, $resp]))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_g2_a_user_with_no_org_is_denied_on_show_select_and_convert(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible(null);
        $resp = $this->response($rfq);
        $none = $this->mkUser();
        $before = $this->snapshot();

        $this->req($none, 'GET', route('rfq.show', $rfq))->assertForbidden();
        $this->req($none, 'POST', route('rfq.response.select', [$rfq, $resp]))->assertForbidden();
        $this->req($none, 'POST', route('rfq.convert', $rfq))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_g2_the_project_name_links_from_buyer_index_and_show_and_legacy_rfqs_have_none(): void
    {
        $scoped = $this->mkRfq($this->secret, null, ['title' => 'Scoped RFQ']);
        $this->recipient($scoped);
        $legacy = $this->mkRfq(null, null, ['title' => 'Legacy RFQ']);
        $this->recipient($legacy);
        $link = 'href="'.route('projects.show', $this->secret).'"';

        $index = $this->actingAs($this->est)->get(route('rfq.index'))->assertOk();
        $index->assertSee('<th>Project</th>', false)->assertSee($link, false)->assertSee(self::SECRET, false);

        $show = $this->actingAs($this->est)->get(route('rfq.show', $scoped))->assertOk();
        $show->assertSee('Project: ', false)->assertSee($link, false)->assertSee(self::SECRET, false);

        $legacyShow = $this->actingAs($this->est)->get(route('rfq.show', $legacy))->assertOk();
        $legacyShow->assertDontSee('Project: ', false)->assertDontSee(self::SECRET, false);
    }

    public function test_g2_show_renders_recipients_responses_and_actions_for_a_scoped_rfq(): void
    {
        $rfq = $this->mkRfq($this->p1, null, ['status' => 'closed']);
        $this->recipient($rfq, null, 'responded');
        $this->response($rfq, 'selected');

        $this->actingAs($this->pm)->get(route('rfq.show', $rfq))->assertOk()->assertSee('Seller Org');
        $this->actingAs($this->est)->get(route('rfq.index'))->assertOk();
    }

    // ---- G2: sellers never see the buyer's project -------------------------------

    private function assertNoProjectLeak($response): void
    {
        $body = $response->getContent();
        $this->assertStringNotContainsString(self::SECRET, $body);
        $this->assertStringNotContainsString('Project: ', $body);
        $this->assertStringNotContainsString(route('projects.show', $this->secret), $body);
    }

    #[DataProvider('modes')]
    public function test_g2_seller_incoming_never_carries_the_project_id_or_name(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->sent($this->secret);
        $legacy = $this->sent(null);

        $r = $this->actingAs($this->sellerRep)->get(route('rfq.seller.incoming'))->assertOk();

        $this->assertNoProjectLeak($r);
        $recipients = $r->viewData('recipients');
        $this->assertSame(2, $recipients->count());
        foreach ($recipients as $recipient) {
            $this->assertFalse($recipient->rfqRequest->relationLoaded('project'));
            $this->assertArrayNotHasKey('project_id', $recipient->rfqRequest->getAttributes());
            $this->assertArrayNotHasKey('project_id', $recipient->rfqRequest->toArray());
            $this->assertArrayNotHasKey('project', $recipient->rfqRequest->toArray());
            $this->assertArrayNotHasKey('project_id', $recipient->toArray()['rfq_request']);
            $this->assertNull($recipient->rfqRequest->project_id);
        }
        $this->assertContains($rfq, $recipients->pluck('rfq_request_id')->map(fn ($i) => (int) $i)->all());
        $this->assertContains($legacy, $recipients->pluck('rfq_request_id')->map(fn ($i) => (int) $i)->all());
        $r->assertSee($recipients->first()->rfqRequest->title);
    }

    #[DataProvider('modes')]
    public function test_g2_seller_incoming_over_json_carries_no_project_key_or_name(string $mode): void
    {
        $this->setMode($mode);
        $this->sent($this->secret);

        $r = $this->req($this->sellerRep, 'GET', route('rfq.seller.incoming'))->assertOk();

        $this->assertNoProjectLeak($r);
        $this->assertStringNotContainsString('project_id', $r->getContent());
        $this->assertStringNotContainsString('"project"', $r->getContent());
    }

    #[DataProvider('modes')]
    public function test_g2_respond_allowed_for_seller_s_and_the_flow_leaks_no_project(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->sent($this->secret);

        $r = $this->actingAs($this->sellerRep)->post(route('rfq.seller.respond', $rfq), ['total_price' => '123.45', 'notes' => 'ok']);

        $r->assertRedirect(route('rfq.seller.incoming'))->assertSessionHas('success', 'Your quote has been submitted to the buyer.');
        $this->assertNoProjectLeak($r);
        $this->assertSame('responded', DB::table('rfq_recipients')->value('status'));
        $this->assertSame('pending_review', DB::table('rfq_responses')->where('rfq_request_id', $rfq)->value('status'));
        $this->assertNoProjectLeak($this->actingAs($this->sellerRep)->get(route('rfq.seller.incoming'))->assertOk());
    }

    #[DataProvider('modes')]
    public function test_g2_respond_json_validation_and_success_leak_no_project(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->sent($this->secret);

        $bad = $this->req($this->sellerRep, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 'x'])->assertStatus(422);
        $this->assertNoProjectLeak($bad);
        $this->assertStringNotContainsString('project_id', $bad->getContent());

        $ok = $this->req($this->sellerRep, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 10]);
        $this->assertNoProjectLeak($ok);
        $this->assertStringNotContainsString('project_id', $ok->getContent());
    }

    #[DataProvider('modes')]
    public function test_g2_decline_allowed_for_seller_s_and_leaks_no_project(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->sent($this->secret);

        $r = $this->actingAs($this->sellerRep)->post(route('rfq.seller.decline', $rfq));

        $r->assertRedirect(route('rfq.seller.incoming'))->assertSessionHas('success', 'You have declined this RFQ.');
        $this->assertNoProjectLeak($r);
        $this->assertSame('declined', DB::table('rfq_recipients')->value('status'));
        $this->assertNoProjectLeak($this->actingAs($this->sellerRep)->get(route('rfq.seller.incoming'))->assertOk());
    }

    #[DataProvider('modes')]
    public function test_g2_seller_side_wrong_org_and_no_org_are_denied(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->sent($this->secret);
        $none = $this->mkUser();
        $before = $this->snapshot();

        $this->req($this->est, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 5])->assertNotFound();
        $this->req($this->est, 'POST', route('rfq.seller.decline', $rfq))->assertNotFound();
        $this->req($this->outsider, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 5])->assertNotFound();
        $this->req($this->outsider, 'POST', route('rfq.seller.decline', $rfq))->assertNotFound();
        $this->req($none, 'GET', route('rfq.seller.incoming'))->assertForbidden();
        $this->req($none, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 5])->assertForbidden();
        $this->req($none, 'POST', route('rfq.seller.decline', $rfq))->assertForbidden();

        $incomingForBuyer = $this->req($this->est, 'GET', route('rfq.seller.incoming'))->assertOk();
        $this->assertNoProjectLeak($incomingForBuyer);
        $this->assertSame(0, $this->actingAs($this->est)->get(route('rfq.seller.incoming'))->viewData('recipients')->count());

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_g2_respond_still_needs_an_active_buyer_seller_relationship(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->sent($this->secret);
        DB::table('org_relationships')->update(['is_active' => false]);
        $before = $this->snapshot();

        $this->req($this->sellerRep, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 5])->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    // ---- G2: project deletion refusal --------------------------------------------

    private function freshProject(string $name = 'Deletable'): int
    {
        $id = $this->mkProject($this->orgA, $this->est, $name);
        $this->member($id, $this->est, $this->orgA);

        return $id;
    }

    #[DataProvider('modes')]
    public function test_g2_destroy_refuses_a_project_with_rfqs_over_json(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();
        $this->mkRfq($id);

        $r = $this->req($this->est, 'DELETE', "projects/$id")->assertStatus(422);

        $this->assertSame('Delete or move the RFQs in this project first.', $r->json('message'));
        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
        $this->assertSame(1, DB::table('rfq_requests')->where('project_id', $id)->count());
    }

    #[DataProvider('modes')]
    public function test_g2_destroy_refuses_a_project_with_rfqs_with_redirect_and_flash(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();
        $this->mkRfq($id);

        $this->actingAs($this->est)->delete("projects/$id")
            ->assertRedirect(route('projects.show', $id))
            ->assertSessionHas('error', 'Delete or move the RFQs in this project first.');

        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    #[DataProvider('modes')]
    public function test_g2_estimates_keep_precedence_over_rfqs_in_the_refusal_message(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();
        $this->mkRfq($id);
        $this->mkQuote($this->est, $id);

        $this->req($this->est, 'DELETE', "projects/$id")->assertStatus(422)
            ->assertJsonPath('message', 'Delete or move the estimates in this project first.');
        $this->actingAs($this->est)->delete("projects/$id")
            ->assertSessionHas('error', 'Delete or move the estimates in this project first.');

        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    #[DataProvider('modes')]
    public function test_g2_a_project_without_rfqs_or_estimates_is_still_deletable_and_other_projects_rfqs_do_not_block(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();
        $this->mkRfq($this->p1);

        $this->req($this->est, 'DELETE', "projects/$id")->assertOk();

        $this->assertNotNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    public function test_g2_a_project_frees_up_for_deletion_after_its_rfq_is_moved_out(): void
    {
        $id = $this->freshProject();
        $rfq = $this->mkRfq($id);

        $this->req($this->est, 'DELETE', "projects/$id")->assertStatus(422);
        DB::table('rfq_requests')->where('id', $rfq)->update(['project_id' => null]);
        $this->req($this->est, 'DELETE', "projects/$id")->assertOk();
    }

    // ---- server-side level mirrors: audit and enforce ----------------------------

    #[DataProvider('modes')]
    public function test_mirror_index_and_show_deny_a_role_without_quote_rfq_management(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->mkRfq(null);
        $scoped = $this->mkRfq($this->p1);
        $before = $this->snapshot();

        $this->req($this->super, 'GET', route('rfq.index'))->assertForbidden();
        $this->req($this->super, 'GET', route('rfq.show', $rfq))->assertForbidden();
        $this->req($this->super, 'GET', route('rfq.show', $scoped))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_mirror_select_denies_roles_below_o(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->mkRfq($this->p1);
        $this->recipient($rfq, null, 'responded');
        $resp = $this->response($rfq);
        $legacy = $this->mkRfq(null);
        $legacyResp = $this->response($legacy);
        $before = $this->snapshot();

        foreach ([$this->viewer, $this->est, $this->requisitioner] as $user) {
            $this->req($user, 'POST', route('rfq.response.select', [$rfq, $resp]))->assertForbidden();
            $this->req($user, 'POST', route('rfq.response.select', [$legacy, $legacyResp]))->assertForbidden();
        }

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_mirror_convert_denies_roles_below_f(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible($this->p1);
        $legacy = $this->convertible(null);
        $before = $this->snapshot();

        foreach ([$this->viewer, $this->est, $this->requisitioner] as $user) {
            $this->req($user, 'POST', route('rfq.convert', $rfq))->assertForbidden();
            $this->req($user, 'POST', route('rfq.convert', $legacy))->assertForbidden();
        }

        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_mirror_incoming_respond_and_decline_deny_roles_below_the_level(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->sent($this->secret);
        $before = $this->snapshot();

        $this->req($this->sellerNone, 'GET', route('rfq.seller.incoming'))->assertForbidden();
        $this->req($this->sellerNone, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 5])->assertForbidden();
        $this->req($this->sellerNone, 'POST', route('rfq.seller.decline', $rfq))->assertForbidden();

        $this->req($this->sellerViewer, 'POST', route('rfq.seller.respond', $rfq), ['total_price' => 5])->assertForbidden();
        $this->req($this->sellerViewer, 'POST', route('rfq.seller.decline', $rfq))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(200, $this->req($this->sellerViewer, 'GET', route('rfq.seller.incoming'))->status());
    }

    #[DataProvider('modes')]
    public function test_mirror_create_and_store_deny_roles_below_s(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->viewer, 'GET', route('rfq.create'))->assertForbidden();
        $this->req($this->viewer, 'POST', route('rfq.store'), $this->storePayload())->assertForbidden();

        $this->assertSame(0, DB::table('rfq_requests')->count());
    }

    // ---- views --------------------------------------------------------------------

    public function test_every_changed_rfq_view_renders(): void
    {
        $rfq = $this->mkRfq($this->secret, null, ['status' => 'closed']);
        $this->recipient($rfq, null, 'responded');
        $this->response($rfq, 'pending_review');
        $this->response($rfq, 'selected');
        $legacy = $this->mkRfq(null);
        $this->recipient($legacy);

        $this->actingAs($this->pm)->get(route('rfq.create'))->assertOk()->assertSee('Project');
        $this->actingAs($this->pm)->get(route('rfq.index'))->assertOk()->assertSee(self::SECRET);
        $this->actingAs($this->pm)->get(route('rfq.show', $rfq))->assertOk()->assertSee(self::SECRET);
        $this->actingAs($this->pm)->get(route('rfq.show', $legacy))->assertOk();
        $this->actingAs($this->sellerRep)->get(route('rfq.seller.incoming'))->assertOk();
        $this->actingAs($this->sellerViewer)->get(route('rfq.seller.incoming'))->assertOk();
        $this->actingAs($this->stranger)->get(route('rfq.create'))->assertOk()->assertSee('No projects available.');
        $this->actingAs($this->est)->get(route('projects.show', $this->secret))->assertOk();
    }

    // ---- round 2: convert/select idempotency, dashboard visibility, O-level convert ------

    private function orders(): int
    {
        return DB::table('orders')->count();
    }

    #[DataProvider('modes')]
    public function test_r2_a_second_convert_is_422_and_exactly_one_order_exists(string $mode): void
    {
        $this->setMode($mode);
        $this->extraApprover();
        $rfq = $this->convertible($this->p1);

        $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq))->assertRedirect();
        $this->assertSame(1, $this->orders());
        $orderId = DB::table('orders')->value('id');

        $second = $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq));
        $second->assertStatus(422);
        $this->req($this->procMgr, 'POST', route('rfq.convert', $rfq))->assertStatus(422)
            ->assertJsonPath('message', 'This RFQ has already been converted to an order.');
        $this->req($this->pm, 'POST', route('rfq.convert', $rfq))->assertStatus(422);

        $this->assertSame(1, $this->orders());
        $this->assertSame([(int) $orderId], DB::table('orders')->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    public function test_r2_a_stale_model_double_convert_is_refused_by_the_locked_re_read(): void
    {
        $rfq = $this->convertible($this->p1);
        $stale = \App\Models\RfqRequest::findOrFail($rfq);
        $this->assertSame('closed', $stale->status);

        $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq))->assertRedirect();
        $this->assertSame('closed', $stale->status);

        $this->actingAs($this->procMgr);
        try {
            app(\App\Http\Controllers\Frontend\RfqController::class)->convertToOrder($stale);
            $this->fail('the second convert on a stale model must abort');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(1, $this->orders());
    }

    #[DataProvider('modes')]
    public function test_r2_convert_needs_status_closed_with_a_selected_response(string $mode): void
    {
        $this->setMode($mode);

        $cases = [];
        foreach (['draft', 'sent', 'cancelled'] as $status) {
            $rfq = $this->mkRfq($this->p1, null, ['status' => $status]);
            $this->recipient($rfq, null, 'responded');
            $this->response($rfq, 'selected');
            $cases["$status with a selected response"] = $rfq;
        }
        $noSelection = $this->mkRfq($this->p1, null, ['status' => 'closed']);
        $this->response($noSelection, 'pending_review');
        $cases['closed without a selected response'] = $noSelection;
        $bare = $this->mkRfq(null, null, ['status' => 'sent']);
        $cases['legacy sent, no responses'] = $bare;
        $before = $this->snapshot();

        foreach ($cases as $label => $id) {
            $this->req($this->procMgr, 'POST', route('rfq.convert', $id))->assertStatus(422, $label);
        }

        $this->assertSame(0, $this->orders());
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_r2_select_after_converted_is_422_and_the_selection_is_unchanged(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->mkRfq($this->p1);
        $this->recipient($rfq, null, 'responded');
        $a = $this->response($rfq);
        $b = $this->response($rfq);
        $this->actingAs($this->coord)->post(route('rfq.response.select', [$rfq, $a]))->assertRedirect();
        $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq))->assertRedirect();
        $before = $this->snapshot();

        $this->req($this->coord, 'POST', route('rfq.response.select', [$rfq, $b]))->assertStatus(422);
        $this->req($this->coord, 'POST', route('rfq.response.select', [$rfq, $a]))->assertStatus(422);

        $this->assertSame($before, $this->snapshot());
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
        $this->assertSame('selected', DB::table('rfq_responses')->where('id', $a)->value('status'));
        $this->assertSame('rejected', DB::table('rfq_responses')->where('id', $b)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_r2_convert_with_quote_rfq_o_or_a_and_procurement_s_is_403_and_creates_no_order(string $mode): void
    {
        $this->setMode($mode);
        $rfqGroup = DB::table('permission_groups')->where('slug', 'quote_rfq_management')->value('id');
        $salesRepRole = DB::table('roles')->where('slug', 'sales_rep')->value('id');
        $rfq = $this->convertible($this->p1);
        $legacy = $this->convertible(null);
        $before = $this->snapshot();

        foreach (['O', 'A'] as $level) {
            DB::table('role_permissions')->where('role_id', $salesRepRole)->where('permission_group_id', $rfqGroup)->update(['access_level' => $level]);
            $this->grantProcurementOnce('sales_rep', 'S');
            app(PermissionMatrix::class)->flush();

            $this->req($this->salesRep, 'POST', route('rfq.convert', $rfq))->assertForbidden();
            $this->req($this->salesRep, 'POST', route('rfq.convert', $legacy))->assertForbidden();
        }

        $this->assertSame(0, $this->orders());
        $this->assertSame($before, $this->snapshot());
    }

    private function grantProcurementOnce(string $role, string $level): void
    {
        $roleId = DB::table('roles')->where('slug', $role)->value('id');
        $group = DB::table('permission_groups')->where('slug', 'procurement')->value('id');
        DB::table('role_permissions')->updateOrInsert(
            ['role_id' => $roleId, 'permission_group_id' => $group],
            ['access_level' => $level, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function test_r2_the_same_o_role_converts_once_raised_to_f(): void
    {
        $rfqGroup = DB::table('permission_groups')->where('slug', 'quote_rfq_management')->value('id');
        $roleId = DB::table('roles')->where('slug', 'sales_rep')->value('id');
        $this->grantProcurementOnce('sales_rep', 'S');
        DB::table('role_permissions')->where('role_id', $roleId)->where('permission_group_id', $rfqGroup)->update(['access_level' => 'F']);
        app(PermissionMatrix::class)->flush();

        $this->actingAs($this->salesRep)->post(route('rfq.convert', $this->convertible($this->p1)))->assertRedirect();
        $this->assertSame(1, $this->orders());
    }

    // ---- round 2: dashboard pendingRfqs -------------------------------------------

    private function dashboardRfqIds(User $u, array $session = []): array
    {
        if (! Schema::hasTable('saved_list_items')) {
            Schema::create('saved_list_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('saved_list_id')->nullable();
                $t->timestamps();
            });
            DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($d, $f) => date(str_replace(['%Y', '%m'], ['Y', 'm'], $f), strtotime($d)), 2);
        }
        $ids = $this->actingAs($u)->withSession($session)->get('user-dashboard')->assertOk()
            ->viewData('pendingRfqs')->pluck('id')->map(fn ($i) => (int) $i)->all();
        sort($ids);

        return $ids;
    }

    #[DataProvider('modes')]
    public function test_r2_dashboard_hides_rfqs_of_invisible_projects_and_keeps_null_and_visible_ones(string $mode): void
    {
        $this->setMode($mode);
        $visible = $this->mkRfq($this->p1, null, ['status' => 'sent']);
        $hidden = $this->mkRfq($this->p2, $this->owner, ['status' => 'sent', 'title' => 'Hidden dashboard RFQ']);
        $legacy = $this->mkRfq(null, null, ['status' => 'sent']);
        $converted = $this->mkRfq($this->p1, null, ['status' => 'converted']);
        $foreign = $this->mkRfq($this->p3, $this->outsider, ['status' => 'sent', 'org_id' => $this->orgB->id]);

        $this->assertSame([$visible, $legacy], $this->dashboardRfqIds($this->est));
        $this->assertSame([$legacy], $this->dashboardRfqIds($this->stranger));
        $this->assertSame([$visible, $hidden, $legacy], $this->dashboardRfqIds($this->owner));
        $this->assertSame([$foreign], $this->dashboardRfqIds($this->outsider));
        $this->assertNotContains($converted, $this->dashboardRfqIds($this->est));

        $html = $this->actingAs($this->est)->get('user-dashboard')->getContent();
        $this->assertStringNotContainsString('Hidden dashboard RFQ', $html);
    }

    // ---- round 2: store()'s project-scoped S check (Verifier M11) ------------------

    public function test_r2_store_checks_the_effective_user_s_on_the_project_not_only_the_delegates_membership(): void
    {
        DB::table('delegations')->insert([
            'from_user_id' => $this->stranger->id, 'to_user_id' => $this->est->id, 'org_id' => $this->orgA->id,
            'granted_by' => $this->owner->id, 'starts_at' => now()->subHour(), 'expires_at' => now()->addHour(),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->req($this->est, 'POST', route('rfq.store'), $this->storePayload())->assertForbidden();

        $this->assertSame(0, DB::table('rfq_requests')->count());
    }
}

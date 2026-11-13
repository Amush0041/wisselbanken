<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\User;
use App\Services\Rbac\ApprovalRoutingService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2 round 2, verification only (PLAN V1-V3): W10, L05, L06 and S13 were built and deployed before this round.
 * These cases replay them in the client's own words, as the Owner of an organization with several members, so that a later
 * change shows up as a red test in plain language. No production code is involved. Known limitation, pinned on purpose: a
 * delegated_action row carries the route PATTERN and the principal, not the record id (PLAN R9, not approved).
 *
 * Not testable here: the HTML error page depends on the server's APP_DEBUG and on a stale bootstrap/cache/config.php.
 */
class Round2ClientWordingReplayTest extends ProjectTestCase
{
    /** organization_owner in A, enrolled in no project, the only approver of A */
    private User $ownerA;
    /** viewer_read_only in A: holds nothing, can be given a delegation */
    private User $delegate;
    private $sellerOrg;
    private User $sellerRep;

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

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->delegate = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->sellerOrg = $this->mkOrg('Seller Org');
        $this->sellerRep = $this->mkUser($this->sellerOrg, 'sales_rep');
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

    private function as(User $u, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])->json($method, $uri, $data);
    }

    private function enforcementPage(User $u): string
    {
        return $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get('org-admin/audit-log?tab=enforcement')->assertOk()->getContent();
    }

    private function pendingOrder(User $submitter): array
    {
        $number = 'ORD-'.strtoupper(uniqid());
        $id = (int) DB::table('orders')->insertGetId([
            'user_id' => $submitter->id, 'org_id' => $this->orgA->id, 'order_number' => $number,
            'name' => 'Order', 'status' => 'pending_approval', 'total' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$id, $number];
    }

    private function closedRfq(): int
    {
        $rfq = (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $this->orgA->id, 'project_id' => $this->p1, 'created_by' => $this->est->id,
            'title' => 'RFQ '.uniqid(), 'status' => 'closed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rfq_recipients')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'status' => 'responded', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rfq_responses')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'created_by' => $this->sellerRep->id,
            'total_price' => 250, 'status' => 'selected', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $rfq;
    }

    // ---- V1, W10: "The Owner must see all projects of the organization" -------------------------------------------

    #[DataProvider('modes')]
    public function test_w10_an_estimator_creates_a_project_and_the_owner_who_is_not_a_member_sees_it_lists_it_and_opens_it(string $mode): void
    {
        $this->setMode($mode);
        $id = (int) $this->as($this->est, 'POST', 'projects', ['name' => 'Skyline Tower', 'status' => 'active'])->assertStatus(201)->json('project_id');
        $this->assertSame([], DB::table('project_members')->where('project_id', $id)->where('user_id', $this->ownerA->id)->get()->all());
        $auditBefore = AuditLog::count();

        $listed = array_column($this->as($this->ownerA, 'GET', 'projects/list')->assertOk()->json('projects'), 'id');
        $index = $this->actingAs($this->ownerA)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get('projects')->assertOk()->viewData('projects')->pluck('id')->all();
        $this->as($this->ownerA, 'GET', "projects/$id")->assertOk();

        $this->assertContains($id, array_map('intval', $listed));
        $this->assertContains($id, array_map('intval', $index));
        $this->assertSame([], DB::table('project_members')->where('project_id', $id)->where('user_id', $this->ownerA->id)->get()->all(), 'opening a project does not enrol the Owner');
        $this->assertSame($auditBefore, AuditLog::count(), 'no would_block or blocked row for the Owner');
        $this->assertSame(0, AuditLog::whereIn('outcome', ['would_block', 'blocked'])->where('user_id', $this->ownerA->id)->count());
    }

    #[DataProvider('modes')]
    public function test_w10_the_same_project_stays_hidden_from_a_non_member_who_is_not_org_wide_and_from_another_org(string $mode): void
    {
        $this->setMode($mode);
        $id = (int) $this->as($this->est, 'POST', 'projects', ['name' => 'Skyline Tower', 'status' => 'active'])->assertStatus(201)->json('project_id');
        $ownerB = $this->mkUser($this->orgB, 'organization_owner');

        $this->assertNotContains($id, array_map('intval', array_column($this->as($this->stranger, 'GET', 'projects/list')->assertOk()->json('projects'), 'id')));
        $this->as($this->stranger, 'GET', "projects/$id")->assertStatus($mode === 'enforce' ? 403 : 404);
        $this->actingAs($ownerB)->withSession([config('rbac.current_org_session_key') => $this->orgB->id])->getJson("projects/$id")->assertNotFound();
        $this->assertNotContains($id, array_map('intval', array_column($this->actingAs($ownerB)->withSession([config('rbac.current_org_session_key') => $this->orgB->id])->getJson('projects/list')->assertOk()->json('projects'), 'id')));
    }

    // ---- V2, L05: "Log auto-approvals: who, when, which order" ------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_l05_a_sole_approver_places_an_order_and_the_owner_reads_who_when_and_which_order_on_the_audit_page(string $mode): void
    {
        $this->setMode($mode);
        $this->assertSame([$this->ownerA->id], app(ApprovalRoutingService::class)->approverPool($this->orgA->id), 'the Owner is the only approver of an organization with several members');
        $rfq = $this->closedRfq();

        $this->actingAs($this->ownerA)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])->post("rfq/$rfq/convert")->assertRedirect();

        $order = DB::table('orders')->first();
        $this->assertSame('processing', $order->status);
        $rows = AuditLog::where('outcome', 'auto_approved')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(
            [$this->ownerA->id, $this->orgA->id, 'sole_approver order '.$order->order_number, 'approval_authority', 'A'],
            [(int) $rows[0]->user_id, (int) $rows[0]->org_id, $rows[0]->reason, $rows[0]->permission_group, $rows[0]->required_level]
        );
        $this->assertTrue($rows[0]->created_at->isToday());

        $html = $this->enforcementPage($this->ownerA);
        $this->assertStringContainsString('>Auto-approved</span>', $html, 'what happened');
        $this->assertStringContainsString(e($this->ownerA->name), $html, 'who');
        $this->assertStringContainsString($rows[0]->created_at->format('M d, Y'), $html, 'when');
        $this->assertStringContainsString('sole approver order '.$order->order_number, $html, 'which order');
        $this->assertStringNotContainsString('>auto_approved</span>', $html);
    }

    #[DataProvider('modes')]
    public function test_l05_a_sole_approver_approving_their_own_pending_order_is_logged_and_shown_the_same_way(string $mode): void
    {
        $this->setMode($mode);
        [$id, $number] = $this->pendingOrder($this->ownerA);

        $this->as($this->ownerA, 'POST', "orders/$id/approve", ['note' => 'ok'])->assertRedirect(route('order.approvals'));

        $rows = AuditLog::where('outcome', 'self_approved')->get();
        $this->assertCount(1, $rows);
        $this->assertSame([$this->ownerA->id, $this->orgA->id, 'sole_approver_self_approval order '.$number], [(int) $rows[0]->user_id, (int) $rows[0]->org_id, $rows[0]->reason]);

        $html = $this->enforcementPage($this->ownerA);
        $this->assertStringContainsString('>Self-approved (sole approver)</span>', $html);
        $this->assertStringContainsString('sole approver self approval order '.$number, $html);
        $this->assertStringContainsString(e($this->ownerA->name), $html);
    }

    #[DataProvider('modes')]
    public function test_l05_an_order_that_goes_to_another_approver_leaves_no_auto_approval_row(string $mode): void
    {
        $this->setMode($mode);
        $this->mkUser($this->orgA, 'executive_approver');
        $rfq = $this->closedRfq();

        $this->actingAs($this->ownerA)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])->post("rfq/$rfq/convert")->assertRedirect();

        $this->assertSame('pending_approval', DB::table('orders')->value('status'));
        $this->assertSame(0, AuditLog::where('outcome', 'auto_approved')->count());
        $this->assertStringNotContainsString('>Auto-approved</span>', $this->enforcementPage($this->ownerA));
    }

    // ---- V2, L06: "Log delegations: granting, revoking and what a delegate does" ----------------------------------------------

    #[DataProvider('modes')]
    public function test_l06_granting_and_revoking_a_delegation_each_leave_a_row_the_owner_can_read_on_the_audit_page(string $mode): void
    {
        $this->setMode($mode);

        $this->as($this->ownerA, 'POST', 'org-admin/delegations', [
            'to_user_id' => $this->delegate->id,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $granted = AuditLog::where('outcome', 'delegation_granted')->get();
        $this->assertCount(1, $granted);
        $this->assertSame([$this->ownerA->id, $this->orgA->id], [(int) $granted[0]->user_id, (int) $granted[0]->org_id]);
        $html = $this->enforcementPage($this->ownerA);
        $this->assertStringContainsString('>Delegation granted</span>', $html);
        $this->assertStringContainsString(e(str_replace('_', ' ', $granted[0]->reason)), $html, 'who is the delegate, for whom, and the window');

        $delegation = Delegation::firstOrFail();
        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$delegation->id}")->assertRedirect();

        $revoked = AuditLog::where('outcome', 'delegation_revoked')->get();
        $this->assertCount(1, $revoked);
        $html = $this->enforcementPage($this->ownerA);
        $this->assertStringContainsString('>Delegation revoked</span>', $html);
        $this->assertStringContainsString('>Delegation granted</span>', $html, 'the grant is still on the record');
    }

    #[DataProvider('modes')]
    public function test_l06_what_a_delegate_does_is_a_row_naming_the_principal_and_is_shown_on_the_audit_page(string $mode): void
    {
        $this->setMode($mode);
        Delegation::create([
            'from_user_id' => $this->ownerA->id, 'to_user_id' => $this->delegate->id, 'org_id' => $this->orgA->id, 'granted_by' => $this->ownerA->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);

        $this->as($this->delegate, 'GET', 'org-admin/delegations')->assertOk();

        $rows = AuditLog::where('outcome', 'delegated_action')->get();
        $this->assertCount(1, $rows);
        $this->assertSame([$this->delegate->id, $this->orgA->id, "acting for {$this->ownerA->name} (#{$this->ownerA->id})"], [(int) $rows[0]->user_id, (int) $rows[0]->org_id, $rows[0]->reason]);
        $html = $this->enforcementPage($this->ownerA);
        $this->assertStringContainsString('>Delegated action</span>', $html);
        $this->assertStringContainsString(e(str_replace('_', ' ', $rows[0]->reason)), $html);
        $this->assertStringContainsString(e($this->delegate->name), $html, 'the delegate is the user of the row');
    }

    #[DataProvider('modes')]
    public function test_l06_known_limitation_a_delegated_approval_row_names_the_route_and_the_principal_but_not_the_order(string $mode): void
    {
        $this->setMode($mode);
        $principal = $this->mkUser($this->orgA, 'executive_approver');
        Delegation::create([
            'from_user_id' => $principal->id, 'to_user_id' => $this->delegate->id, 'org_id' => $this->orgA->id, 'granted_by' => $principal->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);
        [$id, $number] = $this->pendingOrder($this->est);

        $this->as($this->delegate, 'POST', "orders/$id/approve", ['note' => 'ok'])->assertRedirect(route('order.approvals'));

        $order = DB::table('orders')->where('id', $id)->first();
        $this->assertSame(['processing', $this->delegate->id], [$order->status, (int) $order->approved_by], 'the order itself records the person who approved it');
        $rows = AuditLog::where('outcome', 'delegated_action')->get();
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame([$this->delegate->id, 'POST', 'orders/{order}/approve', "acting for {$principal->name} (#{$principal->id})"], [(int) $row->user_id, $row->method, $row->route_uri, $row->reason]);
        $this->assertStringNotContainsString($number, (string) $row->reason, 'R9: the order number is not in the row');
        $this->assertNull($row->project_id);
        $this->assertNull($row->quote_id);
        $this->assertStringNotContainsString($number, $this->enforcementPage($this->ownerA), 'so the audit page cannot say which order either');
    }

    // ---- V3, S13: "JSON errors show debug details" -----------------------------------------------------------------------------

    private const DEBUG_KEYS = ['exception', 'file', 'line', 'trace'];
    private const LEAKS = ['Illuminate\\', 'App\\Models', 'ModelNotFoundException', 'QueryException', 'No query results', 'SQLSTATE', 'no such table', 'Stack trace', '.php', 'vendor/', 'secret-detail'];

    private function assertNothingToDebug($response, string $label): void
    {
        $body = (string) $response->getContent();
        $json = $response->json();

        $this->assertIsArray($json, $label);
        foreach (self::DEBUG_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $json, "$label has a $key key");
        }
        foreach (self::LEAKS as $needle) {
            $this->assertStringNotContainsString($needle, $body, "$label leaks $needle");
        }
        $this->assertStringNotContainsString(base_path(), $body, "$label leaks the server path");
        $this->assertSame([], array_diff(array_keys($json), ['message', 'errors', 'rbac_error', 'group', 'level']), $label);
    }

    private function viaOrgA(User $u)
    {
        return $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $this->orgA->id]);
    }

    #[DataProvider('modes')]
    public function test_s13_with_app_debug_on_json_404_403_422_500_and_a_thrown_non_http_exception_carry_no_debug_data(string $mode): void
    {
        config(['app.debug' => true]);
        $this->setMode($mode);
        $this->assertTrue(config('app.debug'));
        Route::get('_replay/type-error', fn () => (fn (int $n) => $n)('not a number'));
        Route::get('_replay/sql', fn () => DB::select('select secret-detail from missing_table'));
        Route::get('_replay/runtime', fn () => throw new \RuntimeException('secret-detail in /var/www/app/Secret.php'));
        Route::get('_replay/not-found-model', fn () => \App\Models\Quote::findOrFail(987654));

        $this->assertNothingToDebug($this->viaOrgA($this->est)->getJson('quotes/987654/details')->assertNotFound(), '404 missing record');
        $this->assertNothingToDebug($this->viaOrgA($this->est)->getJson('no/such/page')->assertNotFound(), '404 unknown url');
        $this->assertNothingToDebug($this->viaOrgA($this->est)->getJson('_replay/not-found-model')->assertNotFound(), '404 model not found');
        $this->assertNothingToDebug($this->viaOrgA($this->viewer)->postJson("projects/{$this->p1}/quotes", ['customer_id' => 1])->assertForbidden(), '403');
        $this->assertNothingToDebug($this->viaOrgA($this->ownerA)->postJson('org-admin/roles', [])->assertStatus(422), '422');
        $this->assertNothingToDebug($this->viaOrgA($this->ownerA)->getJson('_replay/runtime')->assertStatus(500), '500 thrown exception');
        $this->assertNothingToDebug($this->viaOrgA($this->ownerA)->getJson('_replay/type-error')->assertStatus(500), '500 TypeError');
        $this->assertNothingToDebug($this->viaOrgA($this->ownerA)->getJson('_replay/sql')->assertStatus(500), '500 QueryException');
    }

    public function test_s13_the_thrown_exceptions_are_really_thrown_and_not_swallowed_by_the_test_setup(): void
    {
        config(['app.debug' => true]);
        $this->withoutExceptionHandling();
        Route::get('_replay/sql', fn () => DB::select('select 1 from missing_table'));

        $this->expectException(QueryException::class);
        $this->viaOrgA($this->ownerA)->getJson('_replay/sql');
    }

    public function test_s13_the_example_environment_file_ships_production_with_debug_off(): void
    {
        $lines = preg_split('/\R/', (string) file_get_contents(base_path('.env.example')));

        $this->assertContains('APP_DEBUG=false', $lines);
        $this->assertContains('APP_ENV=production', $lines);
        $this->assertNotContains('APP_DEBUG=true', $lines);
    }
}

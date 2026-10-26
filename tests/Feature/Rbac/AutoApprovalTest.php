<?php

namespace Tests\Feature\Rbac;

use App\Models\Order;
use App\Models\Rbac\AuditLog;
use App\Models\User;
use App\Services\Rbac\ApprovalRoutingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, A01/A07: an order placed by the only approver of an organization is approved at once (status
 * processing, approved_by the requester, a note, one auto_approved audit row) instead of sitting in "pending" forever.
 * Checkout and RFQ conversion behave the same way; every other routing result is unchanged.
 */
class AutoApprovalTest extends ProjectTestCase
{
    private const NOTE = 'Auto-approved: you are the only approver in your organization.';
    private const NO_APPROVER = 'Your organization has no one who can approve orders. Ask an organization owner to assign an Executive Approver (or another role with approval authority), then try again.';

    /** organization_owner + project_manager + estimator + executive_approver in A (the onboarding bundle), member of P1 */
    private User $solo;
    /** executive_approver, second approver when activated */
    private User $mitch;
    /** project_manager in A (rfq F, procurement F, no approval authority), member of P1 */
    private User $pm;
    private $sellerOrg;
    private User $sellerRep;
    private int $variationColor;
    private string $tmp;
    private string $origStorage;
    private string $origEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->origStorage = $this->app->storagePath();
        $this->origEnv = $this->app['env'];
        $this->tmp = sys_get_temp_dir().'/orders-backfill-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
        $this->app->useStoragePath($this->tmp);

        $this->createStubs();

        $this->solo = $this->mkUser($this->orgA, 'organization_owner');
        foreach (['project_manager', 'estimator', 'executive_approver'] as $slug) {
            $this->assignRole($this->solo, $this->orgA, $slug);
        }
        $this->pm = $this->mkUser($this->orgA, 'project_manager');
        $this->mitch = $this->mkUser($this->orgA, 'executive_approver');
        DB::table('user_org_roles')->where('user_id', $this->mitch->id)->update(['is_active' => false]);
        foreach ([$this->solo, $this->pm, $this->mitch] as $u) {
            $this->member($this->p1, $u, $this->orgA);
        }

        $this->sellerOrg = $this->mkOrg('Seller Org');
        $this->sellerRep = $this->mkUser($this->sellerOrg, 'sales_rep');
        DB::table('org_relationships')->insert([
            'from_org_id' => $this->orgA->id, 'to_org_id' => $this->sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->origStorage);
        $this->app['env'] = $this->origEnv;
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function paths(): array
    {
        $out = [];
        foreach (['checkout', 'rfq'] as $path) {
            foreach (['audit', 'enforce'] as $mode) {
                $out["$path $mode"] = [$path, $mode];
            }
        }

        return $out;
    }

    private function createStubs(): void
    {
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
            $t->unsignedBigInteger('product_variation_color_id')->nullable();
            $t->integer('quantity')->default(1);
            $t->decimal('price', 10, 2)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('product_variations', function (Blueprint $t) {
            $t->id();
            $t->decimal('pricing', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('product_variation_color', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_variation_id');
            $t->unsignedBigInteger('color_id')->nullable();
            $t->timestamps();
        });
        Schema::create('palletes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('product_variation_color_id')->nullable();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->unsignedBigInteger('pallet_address_id')->nullable();
            $t->integer('quantity')->default(1);
            $t->timestamps();
        });
        Schema::create('state_taxes', function (Blueprint $t) {
            $t->id();
            $t->string('state')->nullable();
            $t->decimal('state_tax_rate', 8, 3)->nullable();
            $t->decimal('avg_local_tax_rate', 8, 3)->nullable();
            $t->decimal('max_local', 8, 3)->nullable();
            $t->decimal('combined_tax_rate', 8, 3)->nullable();
            $t->timestamps();
        });

        $pv = DB::table('product_variations')->insertGetId(['pricing' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $this->variationColor = (int) DB::table('product_variation_color')->insertGetId([
            'product_variation_id' => $pv, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ---- helpers ----------------------------------------------------------------------------

    private function secondApprover(): void
    {
        DB::table('user_org_roles')->where('user_id', $this->mitch->id)->update(['is_active' => true]);
    }

    private function noApprover(): void
    {
        DB::table('user_org_roles')->whereIn('user_id', [$this->solo->id, $this->mitch->id])->update(['is_active' => false]);
        $this->assertSame([], app(ApprovalRoutingService::class)->approverPool($this->orgA->id));
    }

    private function stockPallet(User $u): void
    {
        DB::table('palletes')->insert([
            'user_id' => $u->id, 'product_variation_color_id' => $this->variationColor, 'quantity' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function convertible(): int
    {
        $rfq = (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $this->orgA->id, 'project_id' => $this->p1, 'created_by' => $this->est->id,
            'title' => 'RFQ '.uniqid(), 'status' => 'closed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rfq_recipients')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'status' => 'responded',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rfq_responses')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'created_by' => $this->sellerRep->id,
            'total_price' => 250, 'status' => 'selected', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $rfq;
    }

    /** Submits an order through the given path as $u; returns the response. */
    private function submit(string $path, User $u, bool $json = false)
    {
        $client = $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $this->orgA->id]);

        if ($path === 'checkout') {
            $this->stockPallet($u);
            $body = [
                'name' => 'Jobsite GC', 'project_name' => 'Tower', 'email' => 'a@b.test', 'phone' => '555',
                'address1' => '1 Main', 'city' => 'Town', 'state' => 'TX', 'postcode' => '75001',
            ];

            return $json ? $client->postJson(route('checkout.process'), $body) : $client->from('/checkout')->post(route('checkout.process'), $body);
        }

        $rfq = $this->convertible();

        return $json ? $client->postJson(route('rfq.convert', $rfq)) : $client->from('/rfq')->post(route('rfq.convert', $rfq));
    }

    private function rows(string $table): int
    {
        return DB::table($table)->count();
    }

    private function autoRows()
    {
        return AuditLog::where('outcome', 'auto_approved')->get();
    }

    // ---- the sole approver --------------------------------------------------------------------

    #[DataProvider('paths')]
    public function test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row(string $path, string $mode): void
    {
        $this->setMode($mode);
        $this->assertSame([$this->solo->id], app(ApprovalRoutingService::class)->approverPool($this->orgA->id));

        $this->submit($path, $this->solo)->assertRedirect();

        $order = DB::table('orders')->first();
        $this->assertSame('processing', $order->status);
        $this->assertSame($this->solo->id, (int) $order->approved_by);
        $this->assertNull($order->rejected_by);
        $this->assertSame(self::NOTE, $order->approval_note);
        $this->assertSame($this->orgA->id, (int) $order->org_id);
        $this->assertSame($this->solo->id, (int) $order->user_id);

        $rows = $this->autoRows();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($this->solo->id, (int) $row->user_id);
        $this->assertSame($this->orgA->id, (int) $row->org_id);
        $this->assertSame('approval_authority', $row->permission_group);
        $this->assertSame('A', $row->required_level);
        $this->assertSame('sole_approver order '.$order->order_number, $row->reason);
        $this->assertSame('POST', $row->method);
        $this->assertSame($path === 'checkout' ? 'checkout/process' : 'rfq/{rfq}/convert', $row->route_uri);
        $this->assertSame(0, AuditLog::where('outcome', 'blocked')->count());
    }

    #[DataProvider('modes')]
    public function test_the_checkout_messages_stay_as_they_were(string $mode): void
    {
        $this->setMode($mode);

        $r = $this->submit('checkout', $this->solo);

        $order = DB::table('orders')->first();
        $r->assertRedirect(route('checkout.success', ['order' => $order->order_number]))
            ->assertSessionHas('success', 'Your order has been placed successfully!')
            ->assertSessionHas('order_status', 'processing');
        $this->assertSame(1, $this->rows('order_items'));
        $this->assertSame(0, DB::table('palletes')->where('user_id', $this->solo->id)->count());
    }

    #[DataProvider('modes')]
    public function test_the_rfq_message_stays_as_it_was_and_the_rfq_is_converted(string $mode): void
    {
        $this->setMode($mode);

        $r = $this->submit('rfq', $this->solo);

        $order = DB::table('orders')->first();
        $r->assertRedirect(route('order.details', $order->id))
            ->assertSessionHas('success', 'RFQ converted to order #'.$order->order_number.'.');
        $this->assertSame('converted', DB::table('rfq_requests')->value('status'));
    }

    #[DataProvider('modes')]
    public function test_an_auto_approved_order_shows_as_approved_and_not_in_the_approval_queue(string $mode): void
    {
        $this->setMode($mode);
        $this->submit('checkout', $this->solo);
        $order = Order::first();

        $html = $this->actingAs($this->solo)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('view.orders'))->assertOk()->getContent();
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('by '.e($this->solo->name), $html);
        $this->assertStringContainsString('Auto-approved: you are the only approver', $html);

        $queue = $this->actingAs($this->solo)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('order.approvals'))->assertOk()->viewData('pendingOrders');
        $this->assertSame([], $queue->pluck('id')->all());
        $this->assertSame('processing', $order->status);
    }

    // ---- everything else is unchanged ------------------------------------------------------------

    #[DataProvider('paths')]
    public function test_an_approver_with_a_second_approver_goes_to_pending_approval_without_a_row(string $path, string $mode): void
    {
        $this->setMode($mode);
        $this->secondApprover();

        $this->submit($path, $this->solo)->assertRedirect();

        $order = DB::table('orders')->first();
        $this->assertSame('pending_approval', $order->status);
        $this->assertNull($order->approved_by);
        $this->assertNull($order->approval_note);
        $this->assertSame(0, AuditLog::where('outcome', 'auto_approved')->count());
    }

    #[DataProvider('paths')]
    public function test_a_non_approver_goes_to_pending_approval_without_a_row(string $path, string $mode): void
    {
        $this->setMode($mode);

        $this->submit($path, $this->pm)->assertRedirect();

        $order = DB::table('orders')->first();
        $this->assertSame('pending_approval', $order->status);
        $this->assertNull($order->approved_by);
        $this->assertSame(0, AuditLog::where('outcome', 'auto_approved')->count());
    }

    #[DataProvider('paths')]
    public function test_with_no_approver_the_order_is_refused_and_nothing_is_written(string $path, string $mode): void
    {
        $this->setMode($mode);
        $this->noApprover();

        $this->submit($path, $this->pm, true)->assertStatus(422)->assertJson(['message' => self::NO_APPROVER]);

        $this->assertSame(0, $this->rows('orders'));
        $this->assertSame(0, AuditLog::where('outcome', 'auto_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_a_solo_user_with_four_roles_is_one_approver(string $mode): void
    {
        $this->setMode($mode);

        $this->assertSame(4, DB::table('user_org_roles')->where('user_id', $this->solo->id)->where('is_active', true)->count());
        $this->assertSame([$this->solo->id], app(ApprovalRoutingService::class)->approverPool($this->orgA->id));
        $this->assertTrue(app(ApprovalRoutingService::class)->shouldAutoApprove($this->orgA->id, $this->solo->id));
    }

    #[DataProvider('modes')]
    public function test_a_second_approver_in_another_org_does_not_prevent_auto_approval(string $mode): void
    {
        $this->setMode($mode);
        $this->mkUser($this->orgB, 'executive_approver');

        $this->submit('checkout', $this->solo)->assertRedirect();

        $this->assertSame('processing', DB::table('orders')->value('status'));
    }

    // ---- service helpers --------------------------------------------------------------------------

    public function test_initial_order_state_for_each_routing_result(): void
    {
        $svc = new ApprovalRoutingService;

        $this->assertSame(
            ['status' => 'processing', 'approved_by' => 7, 'approval_note' => self::NOTE],
            $svc->initialOrderState(['auto_approve' => true, 'approver_ids' => []], 7)
        );
        $this->assertSame(['status' => 'pending_approval'], $svc->initialOrderState(['auto_approve' => false, 'approver_ids' => [3]], 7));
    }

    // ---- audit failure rolls the order back ---------------------------------------------------------

    public function test_an_audit_failure_rolls_the_checkout_order_back(): void
    {
        AuditLog::creating(function (AuditLog $row) {
            if ($row->outcome === 'auto_approved') {
                throw new \RuntimeException('audit store is down');
            }
        });

        $r = $this->submit('checkout', $this->solo);

        $r->assertRedirect('/checkout')->assertSessionHas('error');
        $this->assertSame(0, $this->rows('orders'));
        $this->assertSame(0, $this->rows('order_items'));
        $this->assertSame(1, DB::table('palletes')->where('user_id', $this->solo->id)->count(), 'pallet is kept');
    }

    public function test_an_audit_failure_rolls_the_rfq_conversion_back(): void
    {
        AuditLog::creating(function (AuditLog $row) {
            if ($row->outcome === 'auto_approved') {
                throw new \RuntimeException('audit store is down');
            }
        });

        $this->submit('rfq', $this->solo)->assertStatus(500);

        $this->assertSame(0, $this->rows('orders'));
        $this->assertSame('closed', DB::table('rfq_requests')->value('status'));
    }

    // ---- audit labels ---------------------------------------------------------------------------------

    private function logRow(string $outcome, string $reason): void
    {
        AuditLog::create([
            'user_id' => $this->solo->id, 'org_id' => $this->orgA->id, 'method' => 'POST', 'route_uri' => 'checkout/process',
            'permission_group' => 'approval_authority', 'required_level' => 'A', 'outcome' => $outcome, 'reason' => $reason,
        ]);
    }

    public function test_the_org_audit_page_labels_both_outcomes_and_shows_the_details(): void
    {
        $this->logRow('auto_approved', 'sole_approver order ORD-AAA111');
        $this->logRow('self_approved', 'sole_approver_self_approval order ORD-BBB222');

        $html = $this->actingAs($this->solo)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get('org-admin/audit-log?tab=enforcement')->assertOk()->getContent();

        $this->assertStringContainsString('>Auto-approved</span>', $html);
        $this->assertStringContainsString('>Self-approved (sole approver)</span>', $html);
        $this->assertStringContainsString('sole approver order ORD-AAA111', $html);
        $this->assertStringContainsString('sole approver self approval order ORD-BBB222', $html);
        $this->assertStringNotContainsString('Would Block</span>', $html);
        $this->assertStringNotContainsString('>auto_approved</span>', $html);
        $this->assertStringNotContainsString('>self_approved</span>', $html);
    }

    public function test_the_admin_audit_pages_label_both_outcomes_and_never_call_them_would_block(): void
    {
        $this->logRow('auto_approved', 'sole_approver order ORD-AAA111');
        $this->logRow('self_approved', 'sole_approver_self_approval order ORD-BBB222');
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['admin/rbac/audit-logs', 'admin/rbac/enforcement'] as $uri) {
            $html = $this->actingAs($admin)->get($uri)->assertOk()->getContent();
            $this->assertStringContainsString('Auto-approved', $html, $uri);
            $this->assertStringContainsString('Self-approved (sole approver)', $html, $uri);
            $this->assertStringNotContainsString('Would Block</span>', $html, $uri);
        }

        $filtered = $this->actingAs($admin)->get('admin/rbac/audit-logs?outcome=auto_approved')->assertOk()->getContent();
        $this->assertStringContainsString('value="auto_approved"', $filtered);
        $this->assertStringContainsString('value="self_approved"', $filtered);
        $this->assertStringContainsString('ORD-AAA111', $filtered);
        $this->assertStringNotContainsString('ORD-BBB222', $filtered);
    }

    // ---- orders:backfill-auto-approval ------------------------------------------------------------

    private function stuck(array $over = []): int
    {
        return (int) DB::table('orders')->insertGetId($over + [
            'user_id' => $this->solo->id, 'org_id' => $this->orgA->id, 'order_number' => 'ORD-'.uniqid(), 'name' => 'Stuck',
            'status' => 'pending', 'total' => 10, 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
        ]);
    }

    /** @return array{0: array<string,int>, 1: int} the ids per case and the number of ids that must be selected */
    private function backfillFixture(): array
    {
        $ids = [
            'stuck_a' => $this->stuck(),
            'stuck_b' => $this->stuck(['user_id' => $this->pm->id]),
            'stuck_other_org' => $this->stuck(['org_id' => $this->orgB->id, 'user_id' => $this->outsider->id]),
            'no_org' => $this->stuck(['org_id' => null]),
            'processing' => $this->stuck(['status' => 'processing', 'approved_by' => $this->mitch->id]),
            'pending_with_approver' => $this->stuck(['approved_by' => $this->mitch->id]),
            'pending_rejected' => $this->stuck(['rejected_by' => $this->mitch->id]),
            'pending_approval' => $this->stuck(['status' => 'pending_approval']),
            'no_user' => $this->stuck(['user_id' => null]),
            'cancelled' => $this->stuck(['status' => 'cancelled']),
        ];

        return [$ids, 3];
    }

    /** @param string[] $expect lines that must appear in the console output */
    private function bf(array $opts = [], array $expect = []): int
    {
        $pending = $this->artisan('orders:backfill-auto-approval', $opts);
        foreach ($expect as $line) {
            $pending->expectsOutputToContain($line);
        }

        return $pending->run();
    }

    /** @return array<int, array<string,string>> report rows keyed by position */
    private function reportRows(): array
    {
        $lines = array_map('str_getcsv', file($this->reportDir().'/orders.csv', FILE_IGNORE_NEW_LINES));
        $header = array_shift($lines);

        return array_map(fn ($l) => array_combine($header, $l), $lines);
    }

    private function ordersSnapshot(): array
    {
        return DB::table('orders')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    private function reportDir(): string
    {
        $dirs = glob($this->tmp.'/app/backfill/orders-auto-approval/*', GLOB_ONLYDIR);
        $this->assertNotEmpty($dirs, 'report directory written');
        sort($dirs);

        return end($dirs);
    }

    public function test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report(): void
    {
        [$ids] = $this->backfillFixture();
        $before = $this->ordersSnapshot();

        $this->assertSame(0, $this->bf(['--dry-run' => true], ['DRY RUN: selected 3, approved 0, skipped 0, failed 0']));

        $this->assertSame($before, $this->ordersSnapshot());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame('order_id,order_number,org_id,user_id,result', file($this->reportDir().'/orders.csv', FILE_IGNORE_NEW_LINES)[0]);
        $rows = $this->reportRows();
        $this->assertSame([$ids['stuck_a'], $ids['stuck_b'], $ids['stuck_other_org']], array_map(fn ($r) => (int) $r['order_id'], $rows));
        $this->assertSame(['would_approve'], array_values(array_unique(array_column($rows, 'result'))));
    }

    public function test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each(): void
    {
        [$ids] = $this->backfillFixture();
        $before = collect($this->ordersSnapshot())->keyBy('id');

        $this->assertSame(0, $this->bf([], ['selected 3, approved 3, skipped 0, failed 0']));

        foreach (['stuck_a', 'stuck_b', 'stuck_other_org'] as $key) {
            $row = DB::table('orders')->where('id', $ids[$key])->first();
            $this->assertSame('processing', $row->status, $key);
            $this->assertSame((int) $row->user_id, (int) $row->approved_by, $key);
            $this->assertNull($row->rejected_by);
            $this->assertSame(self::NOTE, $row->approval_note, $key);
            $audit = AuditLog::where('outcome', 'auto_approved')->where('reason', 'sole_approver_backfill order '.$row->order_number)->get();
            $this->assertCount(1, $audit, $key);
            $this->assertSame((int) $row->user_id, (int) $audit->first()->user_id);
            $this->assertSame((int) $row->org_id, (int) $audit->first()->org_id);
            $this->assertSame('CLI', $audit->first()->method);
            $this->assertSame('artisan orders:backfill-auto-approval', $audit->first()->route_uri);
            $this->assertSame('approval_authority', $audit->first()->permission_group);
            $this->assertSame('A', $audit->first()->required_level);
        }
        foreach (['no_org', 'processing', 'pending_with_approver', 'pending_rejected', 'pending_approval', 'no_user', 'cancelled'] as $key) {
            $this->assertEquals((array) $before[$ids[$key]], (array) DB::table('orders')->where('id', $ids[$key])->first(), "$key must be untouched");
        }
        $this->assertSame(3, AuditLog::count());
        $this->assertSame(['approved'], array_values(array_unique(array_column($this->reportRows(), 'result'))));
        $this->assertCount(3, $this->reportRows());
        $this->assertStringContainsString('selected 3, approved 3', file_get_contents($this->reportDir().'/summary.txt'));
    }

    public function test_a_second_run_finds_nothing_and_changes_nothing(): void
    {
        $this->backfillFixture();
        $this->bf();
        $after = $this->ordersSnapshot();
        $auditRows = AuditLog::count();

        $this->assertSame(0, $this->bf([], ['selected 0, approved 0, skipped 0, failed 0', 'nothing to do']));

        $this->assertSame($after, $this->ordersSnapshot());
        $this->assertSame($auditRows, AuditLog::count());
    }

    public function test_production_needs_force_for_a_real_run_but_not_for_a_dry_run(): void
    {
        [$ids] = $this->backfillFixture();
        $this->app['env'] = 'production';

        $this->assertSame(2, $this->bf());
        $this->assertSame('pending', DB::table('orders')->where('id', $ids['stuck_a'])->value('status'));
        $this->assertSame(0, AuditLog::count());

        $this->assertSame(0, $this->bf(['--dry-run' => true]));
        $this->assertSame('pending', DB::table('orders')->where('id', $ids['stuck_a'])->value('status'));

        $this->assertSame(0, $this->bf(['--force' => true]));
        $this->assertSame('processing', DB::table('orders')->where('id', $ids['stuck_a'])->value('status'));
    }

    public function test_an_audit_failure_rolls_back_that_order_only_and_the_run_reports_it(): void
    {
        [$ids] = $this->backfillFixture();
        $failFor = DB::table('orders')->where('id', $ids['stuck_b'])->value('order_number');
        AuditLog::creating(function (AuditLog $row) use ($failFor) {
            if ($row->outcome === 'auto_approved' && str_ends_with((string) $row->reason, $failFor)) {
                throw new \RuntimeException('audit store is down');
            }
        });

        $this->assertSame(1, $this->bf([], ['selected 3, approved 2, skipped 0, failed 1']));

        $this->assertSame('pending', DB::table('orders')->where('id', $ids['stuck_b'])->value('status'));
        $this->assertNull(DB::table('orders')->where('id', $ids['stuck_b'])->value('approved_by'));
        $this->assertSame('processing', DB::table('orders')->where('id', $ids['stuck_a'])->value('status'));
        $this->assertSame('processing', DB::table('orders')->where('id', $ids['stuck_other_org'])->value('status'));
        $this->assertSame(2, AuditLog::count());
    }

    public function test_exit_2_when_the_approval_columns_are_missing_and_nothing_is_written(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('approval_note'));

        $this->artisan('orders:backfill-auto-approval')->expectsOutputToContain('approval columns are missing')->assertExitCode(2);
        $this->assertDirectoryDoesNotExist($this->tmp.'/app/backfill');
    }

    public function test_an_order_that_changes_between_selection_and_write_is_skipped(): void
    {
        [$ids] = $this->backfillFixture();
        $stolen = false;
        DB::listen(function ($query) use (&$stolen, $ids) {
            if (! $stolen && str_contains($query->sql, 'select') && str_contains($query->sql, 'from "orders"')) {
                $stolen = true;
                DB::table('orders')->where('id', $ids['stuck_a'])->update(['status' => 'cancelled']);
            }
        });

        $this->assertSame(0, $this->bf([], ['selected 3, approved 2, skipped 1, failed 0']));

        $this->assertSame('cancelled', DB::table('orders')->where('id', $ids['stuck_a'])->value('status'));
        $this->assertSame(2, AuditLog::count());
    }

    // ---- AuditLog::event --------------------------------------------------------------------------------

    public function test_event_outside_a_request_records_cli_and_artisan(): void
    {
        AuditLog::event('auto_approved', $this->solo->id, $this->orgA->id, 'approval_authority', 'A', str_repeat('x', 400));

        $row = AuditLog::first();
        $this->assertSame('CLI', $row->method);
        $this->assertStringStartsWith('artisan', $row->route_uri);
        $this->assertSame(255, strlen($row->reason));
    }

    public function test_event_accepts_explicit_method_and_route(): void
    {
        AuditLog::event('auto_approved', $this->solo->id, null, null, null, 'r', 'CLI', 'artisan orders:backfill-auto-approval');

        $row = AuditLog::first();
        $this->assertSame('CLI', $row->method);
        $this->assertSame('artisan orders:backfill-auto-approval', $row->route_uri);
        $this->assertNull($row->org_id);
        $this->assertNull($row->permission_group);
    }

    public function test_event_explicit_method_overrides_the_derived_one(): void
    {
        AuditLog::event('auto_approved', $this->solo->id, null, null, null, 'r', 'PATCH', 'some/route');

        $row = AuditLog::first();
        $this->assertSame('PATCH', $row->method);
        $this->assertSame('some/route', $row->route_uri);
    }

    public function test_a_user_without_an_organization_cannot_place_an_order_and_nothing_is_auto_approved(): void
    {
        $loner = User::factory()->create(['role' => 'user']);
        $this->stockPallet($loner);

        $this->actingAs($loner)->from('/checkout')->post(route('checkout.process'), [
            'name' => 'Jobsite GC', 'project_name' => 'Tower', 'email' => 'a@b.test', 'phone' => '555',
            'address1' => '1 Main', 'city' => 'Town', 'state' => 'TX', 'postcode' => '75001',
        ])->assertRedirect('/checkout')->assertSessionHas('rbac_denied', 'You do not have permission to perform this action.');

        $this->assertSame(0, $this->rows('orders'));
        $this->assertSame(0, AuditLog::where('outcome', 'auto_approved')->count());
        $this->assertSame(1, DB::table('palletes')->where('user_id', $loner->id)->count(), 'pallet is kept');
    }

    #[DataProvider('missingApprovalColumns')]
    public function test_exit_2_for_each_missing_approval_column(string $column): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($column));

        $this->artisan('orders:backfill-auto-approval')->expectsOutputToContain('approval columns are missing')->assertExitCode(2);
        $this->assertDirectoryDoesNotExist($this->tmp.'/app/backfill');
    }

    public static function missingApprovalColumns(): array
    {
        return ['approved_by' => ['approved_by'], 'rejected_by' => ['rejected_by'], 'approval_note' => ['approval_note']];
    }

    public function test_a_real_run_approves_every_stuck_order_even_when_there_are_more_than_one_chunk(): void
    {
        $rows = [];
        for ($i = 0; $i < 430; $i++) {
            $rows[] = [
                'user_id' => $this->solo->id, 'org_id' => $this->orgA->id, 'order_number' => 'ORD-BULK-'.$i, 'name' => 'Stuck',
                'status' => 'pending', 'total' => 10, 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $this->assertSame(0, $this->bf([], ['selected 430, approved 430, skipped 0, failed 0']));

        $this->assertSame(0, DB::table('orders')->where('status', 'pending')->count());
        $this->assertSame(430, AuditLog::where('outcome', 'auto_approved')->count());
    }
}

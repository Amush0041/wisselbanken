<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Services\Rbac\PermissionMatrix;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * P2-B and P2-C: delete-user membership log, dashboard pendingApprovals (approval_authority A) and pendingRfqs
 * (sent/closed only), RfqSellerController::respond/decline state guards, and the "no one can approve" refusal at
 * both CheckoutController::processCheckout and RfqController::convertToOrder (audit and enforce mode).
 */
class P2CleanupBCTest extends ProjectTestCase
{
    private const NO_APPROVER = 'Your organization has no one who can approve orders. Ask an organization owner to assign an Executive Approver (or another role with approval authority), then try again.';

    private User $admin;
    /** procurement_manager (procurement F, approval_authority A) in A, member of P1 */
    private User $procMgr;
    /** organization_owner (approval_authority A) in A, member of P1 */
    private User $orgOwner;
    /** requisitioner (procurement S, no approval authority) in A, member of P1 */
    private User $requisitioner;
    /** project_manager (quote_rfq F, procurement F, no approval authority) in A, member of P1 */
    private User $pm;
    private User $sellerRep;
    private $sellerOrg;
    private int $variationColor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createStubs();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->procMgr = $this->mkUser($this->orgA, 'procurement_manager');
        $this->orgOwner = $this->mkUser($this->orgA, 'organization_owner');
        $this->requisitioner = $this->mkUser($this->orgA, 'requisitioner');
        $this->pm = $this->mkUser($this->orgA, 'project_manager');
        foreach ([$this->procMgr, $this->orgOwner, $this->requisitioner, $this->pm] as $u) {
            $this->member($this->p1, $u, $this->orgA);
        }

        $this->sellerOrg = $this->mkOrg('Seller Org');
        $this->sellerRep = $this->mkUser($this->sellerOrg, 'sales_rep');
        DB::table('org_relationships')->insert([
            'from_org_id' => $this->orgA->id, 'to_org_id' => $this->sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
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
        Schema::create('saved_list_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->timestamps();
        });
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($d, $f) => date(str_replace(['%Y', '%m'], ['Y', 'm'], $f), strtotime($d)), 2);

        $pv = DB::table('product_variations')->insertGetId(['pricing' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $this->variationColor = (int) DB::table('product_variation_color')->insertGetId([
            'product_variation_id' => $pv, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function orgSession(int $orgId): array
    {
        return [config('rbac.current_org_session_key') => $orgId];
    }

    private function rows(string $table): int
    {
        return DB::table($table)->count();
    }

    // ---- (11) admin delete-user writes 'removed' logs ---------------------------------

    #[DataProvider('modes')]
    public function test_delete_user_logs_removed_for_each_active_membership_only(string $mode): void
    {
        $this->setMode($mode);
        $victim = $this->mkUser($this->orgA, 'estimator');
        $this->member($this->p1, $victim, $this->orgA);
        $this->member($this->p2, $victim, $this->orgA);
        $inactiveProject = $this->mkProject($this->orgA, $this->owner, 'Old');
        $this->member($inactiveProject, $victim, $this->orgA, false);
        $this->legacyMember($this->q1, $victim, $this->orgA);
        $bystander = $this->mkUser($this->orgA, 'estimator');
        $this->member($this->p1, $bystander, $this->orgA);

        $this->actingAs($this->admin)->delete(route('admin.rbac.users.destroy', $victim))->assertSessionMissing('error');

        $this->assertFalse(User::whereKey($victim->id)->exists());
        $this->assertSame(0, DB::table('project_members')->where('user_id', $victim->id)->count());
        $rows = DB::table('project_member_logs')->orderBy('project_id')->get();
        $this->assertSame([$this->p1, $this->p2], $rows->pluck('project_id')->map(fn ($i) => (int) $i)->all());
        foreach ($rows as $row) {
            $this->assertSame('removed', $row->action);
            $this->assertSame($this->admin->id, (int) $row->performed_by);
            $this->assertSame($victim->id, (int) $row->target_user_id);
            $this->assertSame($this->orgA->id, (int) $row->org_id);
        }
        $this->assertSame(1, DB::table('project_members')->where('user_id', $bystander->id)->where('is_active', true)->count());
    }

    #[DataProvider('modes')]
    public function test_delete_user_with_only_inactive_memberships_writes_no_log(string $mode): void
    {
        $this->setMode($mode);
        $victim = $this->mkUser($this->orgA, 'estimator');
        $this->member($this->p1, $victim, $this->orgA, false);

        $this->actingAs($this->admin)->delete(route('admin.rbac.users.destroy', $victim))->assertSessionMissing('error');

        $this->assertFalse(User::whereKey($victim->id)->exists());
        $this->assertSame(0, $this->rows('project_member_logs'));
    }

    #[DataProvider('modes')]
    public function test_a_non_admin_cannot_delete_a_user_and_nothing_is_logged(string $mode): void
    {
        $this->setMode($mode);
        $victim = $this->mkUser($this->orgA, 'estimator');
        $this->member($this->p1, $victim, $this->orgA);

        $this->actingAs($this->orgOwner)->deleteJson(route('admin.rbac.users.destroy', $victim))->assertStatus(403);

        $this->assertTrue(User::whereKey($victim->id)->exists());
        $this->assertSame(1, DB::table('project_members')->where('user_id', $victim->id)->count());
        $this->assertSame(0, $this->rows('project_member_logs'));
    }

    // ---- (12) dashboard pendingApprovals needs approval_authority A ---------------------

    private function pendingQuote(int $projectId, string $status = 'pending_approval'): int
    {
        return $this->mkQuote($this->owner, $projectId, ['status' => $status]);
    }

    private function approvals(User $u, array $session = []): array
    {
        $ids = $this->actingAs($u)->withSession($session)->get('user-dashboard')->assertOk()
            ->viewData('pendingApprovals')->pluck('id')->map(fn ($i) => (int) $i)->all();
        sort($ids);

        return $ids;
    }

    public function test_seeded_approval_levels_used_here(): void
    {
        $level = fn (string $role) => DB::table('role_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')->join('permission_groups as g', 'g.id', '=', 'rp.permission_group_id')
            ->where('r.slug', $role)->where('g.slug', 'approval_authority')->value('rp.access_level');

        $this->assertContains($level('procurement_manager'), ['A', 'F']);
        $this->assertContains($level('organization_owner'), ['A', 'F']);
        foreach (['requisitioner', 'project_manager', 'viewer_read_only', 'estimator'] as $role) {
            $this->assertNotContains($level($role), ['A', 'F'], $role);
        }
    }

    #[DataProvider('modes')]
    public function test_pending_approvals_are_listed_for_approval_authority_a_within_visible_projects(string $mode): void
    {
        $this->setMode($mode);
        $mine = $this->pendingQuote($this->p1);
        $draft = $this->pendingQuote($this->p1, 'draft');
        $otherProject = $this->pendingQuote($this->p2);
        $foreign = $this->mkQuote($this->outsider, $this->p3, ['status' => 'pending_approval']);

        $this->assertSame([$mine], $this->approvals($this->procMgr));
        $this->assertSame([$mine], $this->approvals($this->orgOwner));
        $this->assertNotContains($draft, $this->approvals($this->procMgr));
        $this->assertNotContains($otherProject, $this->approvals($this->procMgr));
        $this->assertNotContains($foreign, $this->approvals($this->procMgr));
    }

    #[DataProvider('modes')]
    public function test_pending_approvals_are_empty_for_roles_below_a(string $mode): void
    {
        $this->setMode($mode);
        $this->pendingQuote($this->p1);

        foreach ([$this->requisitioner, $this->pm, $this->viewer, $this->est] as $u) {
            $this->assertSame([], $this->approvals($u));
        }
    }

    public static function belowA(): array
    {
        return ['R' => ['R'], 'S' => ['S'], 'O' => ['O']];
    }

    #[DataProvider('belowA')]
    public function test_pending_approvals_stay_empty_for_a_custom_grant_below_a(string $level): void
    {
        $this->pendingQuote($this->p1);
        DB::table('role_permissions')->updateOrInsert(
            [
                'role_id' => DB::table('roles')->where('slug', 'requisitioner')->value('id'),
                'permission_group_id' => DB::table('permission_groups')->where('slug', 'approval_authority')->value('id'),
            ],
            ['access_level' => $level, 'created_at' => now(), 'updated_at' => now()]
        );
        app(PermissionMatrix::class)->flush();

        $this->assertSame([], $this->approvals($this->requisitioner));
    }

    #[DataProvider('modes')]
    public function test_pending_approvals_wrong_org_and_no_project_membership_see_nothing(string $mode): void
    {
        $this->setMode($mode);
        $this->pendingQuote($this->p1);
        $bOwner = $this->mkUser($this->orgB, 'organization_owner');
        $notMember = $this->mkUser($this->orgA, 'organization_owner');

        $this->assertSame([], $this->approvals($bOwner));
        $this->assertSame([], $this->approvals($notMember));
    }

    // ---- P2-A: quote creation checks estimate S before revealing whether the project exists ----------

    #[DataProvider('modes')]
    public function test_quote_creation_below_s_is_403_even_for_a_project_the_user_cannot_see(string $mode): void
    {
        $this->setMode($mode);

        $this->actingAs($this->viewer)->postJson(route('projects.quotes.store', $this->p2), [])->assertStatus(403);
        $this->actingAs($this->viewer)->postJson(route('projects.quotes.store', $this->p1), [])->assertStatus(403);
    }

    // ---- (13) dashboard pendingRfqs ------------------------------------------------------

    private function mkRfq(?int $projectId, string $status, array $attrs = []): int
    {
        return (int) DB::table('rfq_requests')->insertGetId($attrs + [
            'org_id' => $this->orgA->id, 'project_id' => $projectId, 'created_by' => $this->est->id,
            'title' => 'RFQ '.uniqid(), 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pendingRfqIds(User $u): array
    {
        $ids = $this->actingAs($u)->get('user-dashboard')->assertOk()->viewData('pendingRfqs')->pluck('id')->map(fn ($i) => (int) $i)->all();
        sort($ids);

        return $ids;
    }

    #[DataProvider('modes')]
    public function test_pending_rfqs_show_sent_and_closed_and_hide_converted_draft_and_open(string $mode): void
    {
        $this->setMode($mode);
        $sent = $this->mkRfq($this->p1, 'sent');
        $closed = $this->mkRfq($this->p1, 'closed');
        $this->mkRfq($this->p1, 'converted');
        $this->mkRfq($this->p1, 'draft');

        $this->assertSame([$sent, $closed], $this->pendingRfqIds($this->est));
    }

    #[DataProvider('modes')]
    public function test_pending_rfqs_keep_the_project_visibility_rule_and_org_scope(string $mode): void
    {
        $this->setMode($mode);
        $visible = $this->mkRfq($this->p1, 'sent');
        $hidden = $this->mkRfq($this->p2, 'closed');
        $legacy = $this->mkRfq(null, 'sent');
        $foreign = $this->mkRfq($this->p3, 'sent', ['org_id' => $this->orgB->id, 'created_by' => $this->outsider->id]);

        $this->assertSame([$visible, $legacy], $this->pendingRfqIds($this->est));
        $this->assertSame([$legacy], $this->pendingRfqIds($this->stranger));
        $this->assertSame([$visible, $hidden, $legacy], $this->pendingRfqIds($this->owner));
        $this->assertSame([$foreign], $this->pendingRfqIds($this->outsider));
    }

    #[DataProvider('modes')]
    public function test_pending_rfqs_are_empty_below_quote_rfq_s(string $mode): void
    {
        $this->setMode($mode);
        $this->mkRfq($this->p1, 'sent');

        $this->assertSame([], $this->pendingRfqIds($this->viewer));
    }

    // ---- (16) RfqSellerController::respond / decline state guards -----------------------------

    private function incomingRfq(string $rfqStatus = 'sent', string $recipientStatus = 'pending'): int
    {
        $rfq = $this->mkRfq($this->p1, $rfqStatus);
        DB::table('rfq_recipients')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'status' => $recipientStatus,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $rfq;
    }

    private function respond(int $rfq, bool $json = true)
    {
        $payload = ['total_price' => '99.50', 'notes' => 'n'];
        $uri = route('rfq.seller.respond', $rfq);

        return $json ? $this->actingAs($this->sellerRep)->postJson($uri, $payload) : $this->actingAs($this->sellerRep)->post($uri, $payload);
    }

    #[DataProvider('modes')]
    public function test_respond_happy_path_on_a_sent_rfq_with_a_pending_recipient(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->incomingRfq();

        $this->respond($rfq, false)->assertRedirect(route('rfq.seller.incoming'))->assertSessionHas('success');

        $this->assertSame('responded', DB::table('rfq_recipients')->value('status'));
        $this->assertSame(1, DB::table('rfq_responses')->where('rfq_request_id', $rfq)->count());
    }

    public static function unopenStates(): array
    {
        $cases = [];
        foreach (['audit', 'enforce'] as $mode) {
            $cases["$mode: closed rfq"] = [$mode, 'closed', 'pending'];
            $cases["$mode: converted rfq"] = [$mode, 'converted', 'pending'];
            $cases["$mode: draft rfq"] = [$mode, 'draft', 'pending'];
            $cases["$mode: already responded"] = [$mode, 'sent', 'responded'];
            $cases["$mode: already declined"] = [$mode, 'sent', 'declined'];
        }

        return $cases;
    }

    #[DataProvider('unopenStates')]
    public function test_respond_is_422_unless_rfq_sent_and_recipient_pending(string $mode, string $rfqStatus, string $recipientStatus): void
    {
        $this->setMode($mode);
        $rfq = $this->incomingRfq($rfqStatus, $recipientStatus);

        $this->respond($rfq)->assertStatus(422);

        $this->assertSame(0, $this->rows('rfq_responses'));
        $this->assertSame($recipientStatus, DB::table('rfq_recipients')->value('status'));
        $this->assertSame($rfqStatus, DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_respond_twice_the_second_is_422_and_only_one_response_exists(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->incomingRfq();

        $this->respond($rfq, false)->assertRedirect();
        $this->respond($rfq)->assertStatus(422);

        $this->assertSame(1, $this->rows('rfq_responses'));
    }

    #[DataProvider('modes')]
    public function test_decline_on_a_converted_rfq_is_422_and_leaves_the_recipient_alone(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->incomingRfq('converted', 'responded');

        $this->actingAs($this->sellerRep)->postJson(route('rfq.seller.decline', $rfq))->assertStatus(422);

        $this->assertSame('responded', DB::table('rfq_recipients')->value('status'));
    }

    #[DataProvider('modes')]
    public function test_decline_on_a_sent_rfq_still_works(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->incomingRfq();

        $this->actingAs($this->sellerRep)->post(route('rfq.seller.decline', $rfq))->assertRedirect(route('rfq.seller.incoming'));

        $this->assertSame('declined', DB::table('rfq_recipients')->value('status'));
    }

    /**
     * Verifier finding (2026-09-26): decline only checked the RFQ's own status ('converted'),
     * so a seller who had already responded could still decline afterwards, leaving a
     * 'declined' recipient whose response the buyer could still select or convert. Declining
     * is now only valid while the recipient is still 'pending', same as responding.
     */
    #[DataProvider('modes')]
    public function test_decline_after_already_responding_is_422_and_the_recipient_stays_responded(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->incomingRfq('sent', 'responded');

        $this->actingAs($this->sellerRep)->postJson(route('rfq.seller.decline', $rfq))->assertStatus(422);

        $this->assertSame('responded', DB::table('rfq_recipients')->value('status'));
    }

    // ---- P2-C: no approver refusal ---------------------------------------------------------------

    private function validCheckout(): array
    {
        return [
            'name' => 'Jobsite GC', 'project_name' => 'Tower', 'email' => 'a@b.test', 'phone' => '555',
            'address1' => '1 Main', 'city' => 'Town', 'state' => 'TX', 'postcode' => '75001',
        ];
    }

    private function stockPallet(User $u): void
    {
        DB::table('palletes')->insert([
            'user_id' => $u->id, 'product_variation_color_id' => $this->variationColor, 'quantity' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function checkout(User $u, bool $json, array $body = []): \Illuminate\Testing\TestResponse
    {
        $body = $body ?: $this->validCheckout();
        $client = $this->actingAs($u)->withSession($this->orgSession($this->orgA->id));

        return $json ? $client->postJson(route('checkout.process'), $body) : $client->from('/checkout')->post(route('checkout.process'), $body);
    }

    private function assertNothingOrdered(User $u, int $palletRows = 1): void
    {
        $this->assertSame(0, $this->rows('orders'));
        $this->assertSame(0, $this->rows('order_items'));
        $this->assertSame($palletRows, DB::table('palletes')->where('user_id', $u->id)->count(), 'pallet is kept');
    }

    #[DataProvider('modes')]
    public function test_checkout_without_any_approver_is_refused_with_a_redirect_error(string $mode): void
    {
        $this->setMode($mode);
        $this->stockPallet($this->requisitioner);
        $this->removeApprovers();

        $r = $this->checkout($this->requisitioner, false);

        $r->assertRedirect('/checkout')->assertSessionHas('error', self::NO_APPROVER);
        $this->assertNothingOrdered($this->requisitioner);
    }

    #[DataProvider('modes')]
    public function test_checkout_without_any_approver_is_422_for_json(string $mode): void
    {
        $this->setMode($mode);
        $this->stockPallet($this->requisitioner);
        $this->removeApprovers();

        $this->checkout($this->requisitioner, true)->assertStatus(422)->assertJson(['message' => self::NO_APPROVER]);

        $this->assertNothingOrdered($this->requisitioner);
    }

    private function removeApprovers(): void
    {
        DB::table('user_org_roles')->whereIn('user_id', [$this->procMgr->id, $this->orgOwner->id])->update(['is_active' => false]);
        $this->assertSame([], (new \App\Services\Rbac\ApprovalRoutingService)->approverPool($this->orgA->id));
    }

    #[DataProvider('modes')]
    public function test_checkout_sole_approver_requester_auto_approves(string $mode): void
    {
        $this->setMode($mode);
        DB::table('user_org_roles')->where('user_id', $this->orgOwner->id)->update(['is_active' => false]);
        $this->stockPallet($this->procMgr);

        $r = $this->checkout($this->procMgr, false);

        $order = DB::table('orders')->first();
        $r->assertRedirect(route('checkout.success', ['order' => $order->order_number]));
        $this->assertSame('pending', $order->status);
        $this->assertSame(1, $this->rows('order_items'));
        $this->assertSame(0, DB::table('palletes')->where('user_id', $this->procMgr->id)->count());
    }

    #[DataProvider('modes')]
    public function test_checkout_with_several_approvers_is_pending_approval(string $mode): void
    {
        $this->setMode($mode);
        $this->stockPallet($this->procMgr);
        $this->stockPallet($this->requisitioner);

        $this->checkout($this->procMgr, false)->assertRedirect();
        $this->checkout($this->requisitioner, false)->assertRedirect();

        $this->assertSame(['pending_approval', 'pending_approval'], DB::table('orders')->orderBy('id')->pluck('status')->all());
    }

    #[DataProvider('modes')]
    public function test_checkout_by_a_non_approver_with_an_approver_present_is_pending_approval(string $mode): void
    {
        $this->setMode($mode);
        DB::table('user_org_roles')->where('user_id', $this->orgOwner->id)->update(['is_active' => false]);
        $this->stockPallet($this->requisitioner);

        $this->checkout($this->requisitioner, false)->assertRedirect();

        $this->assertSame('pending_approval', DB::table('orders')->value('status'));
    }

    // ---- processCheckout: procurement S is checked before validation -----------------------------

    #[DataProvider('modes')]
    public function test_process_checkout_denies_viewer_with_an_invalid_body_as_403_not_a_validation_error(string $mode): void
    {
        $this->setMode($mode);
        $this->stockPallet($this->viewer);

        $this->actingAs($this->viewer)->withSession($this->orgSession($this->orgA->id))
            ->postJson(route('checkout.process'), [])->assertStatus(403);

        $this->assertNothingOrdered($this->viewer);
    }

    public function test_process_checkout_denies_viewer_with_an_invalid_body_in_audit_mode_for_browsers_too(): void
    {
        $r = $this->actingAs($this->viewer)->withSession($this->orgSession($this->orgA->id))
            ->from('/checkout')->post(route('checkout.process'), []);

        $r->assertStatus(403);
        $r->assertSessionHasNoErrors();
    }

    #[DataProvider('modes')]
    public function test_process_checkout_with_the_grant_and_an_invalid_body_gets_the_validation_error(string $mode): void
    {
        $this->setMode($mode);

        $this->actingAs($this->requisitioner)->withSession($this->orgSession($this->orgA->id))
            ->postJson(route('checkout.process'), [])->assertRedirect()->assertSessionHasErrors(['name', 'email']);
    }

    #[DataProvider('modes')]
    public function test_process_checkout_wrong_org_role_is_denied(string $mode): void
    {
        $this->setMode($mode);
        $bViewer = $this->mkUser($this->orgB, 'viewer_read_only');
        $this->stockPallet($bViewer);

        $this->actingAs($bViewer)->withSession($this->orgSession($this->orgB->id))
            ->postJson(route('checkout.process'), $this->validCheckout())->assertStatus(403);

        $this->assertSame(0, $this->rows('orders'));
    }

    // ---- P2-C: RFQ convert ---------------------------------------------------------------------

    /** An RFQ in project P1 with a selected response, ready to convert. */
    private function convertible(): int
    {
        $rfq = $this->mkRfq($this->p1, 'closed');
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

    private function noApprovers(): void
    {
        DB::table('user_org_roles')->whereIn('user_id', [$this->procMgr->id, $this->orgOwner->id])->update(['is_active' => false]);
    }

    private function assertRfqUntouched(int $rfq): void
    {
        $this->assertSame(0, $this->rows('orders'));
        $this->assertSame('closed', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
        $this->assertSame('selected', DB::table('rfq_responses')->where('rfq_request_id', $rfq)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_convert_without_any_approver_is_refused_with_a_redirect_error(string $mode): void
    {
        $this->setMode($mode);
        $this->noApprovers();
        $rfq = $this->convertible();

        $r = $this->actingAs($this->pm)->from('/rfq')->post(route('rfq.convert', $rfq));

        $r->assertRedirect('/rfq')->assertSessionHas('error', self::NO_APPROVER);
        $this->assertRfqUntouched($rfq);
    }

    #[DataProvider('modes')]
    public function test_convert_without_any_approver_is_422_for_json(string $mode): void
    {
        $this->setMode($mode);
        $this->noApprovers();
        $rfq = $this->convertible();

        $this->actingAs($this->pm)->postJson(route('rfq.convert', $rfq))->assertStatus(422)->assertJson(['message' => self::NO_APPROVER]);

        $this->assertRfqUntouched($rfq);
    }

    #[DataProvider('modes')]
    public function test_convert_by_the_sole_approver_auto_approves(string $mode): void
    {
        $this->setMode($mode);
        DB::table('user_org_roles')->where('user_id', $this->orgOwner->id)->update(['is_active' => false]);
        $rfq = $this->convertible();

        $this->actingAs($this->procMgr)->post(route('rfq.convert', $rfq))->assertRedirect();

        $this->assertSame('pending', DB::table('orders')->value('status'));
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_convert_with_several_approvers_is_pending_approval(string $mode): void
    {
        $this->setMode($mode);
        $rfq = $this->convertible();

        $this->actingAs($this->pm)->post(route('rfq.convert', $rfq))->assertRedirect();

        $this->assertSame('pending_approval', DB::table('orders')->value('status'));
        $this->assertSame('converted', DB::table('rfq_requests')->where('id', $rfq)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_a_refused_convert_can_be_retried_once_an_approver_exists(string $mode): void
    {
        $this->setMode($mode);
        $this->noApprovers();
        $rfq = $this->convertible();
        $this->actingAs($this->pm)->postJson(route('rfq.convert', $rfq))->assertStatus(422);

        DB::table('user_org_roles')->where('user_id', $this->orgOwner->id)->update(['is_active' => true]);
        $this->actingAs($this->pm)->postJson(route('rfq.convert', $rfq))->assertRedirect();

        $this->assertSame('pending_approval', DB::table('orders')->value('status'));
    }
}

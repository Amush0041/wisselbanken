<?php

namespace Tests\Feature\Rbac;

use App\Mail\OrgInviteMail;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\ApprovalRoutingService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, Phase A: flows that worked before the change and must keep working. Each test pins the legitimate
 * path next to the guard that now sits beside it (role assignment, invites, signup, delegation, ownership, the order
 * workflow with several approvers, and the old audit-log labels).
 */
class PhaseARegressionTest extends ProjectTestCase
{
    private User $ownerA;
    /** project_manager in A (procurement F, quote_rfq_management F, no approval authority), member of P1 */
    private User $requester;
    private User $approverA;
    private User $approverB;
    private $sellerOrg;
    private User $sellerRep;
    private int $variationColor;

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_08_03_135044_create_org_invites_table.php'))->up();
        $this->createOrderStubs();
        Mail::fake();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->requester = $this->mkUser($this->orgA, 'project_manager');
        $this->approverA = $this->mkUser($this->orgA, 'executive_approver');
        $this->approverB = $this->mkUser($this->orgA, 'executive_approver');
        $this->member($this->p1, $this->requester, $this->orgA);

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

    private function createOrderStubs(): void
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

    // ---- helpers ------------------------------------------------------------------------------

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    private function as(User $u, string $method, string $uri, array $data = [], ?int $orgId = null)
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->json($method, $uri, $data);
    }

    private function assignVia(User $actor, int $targetId, string $slug, ?int $orgId = null)
    {
        return $this->as($actor, 'POST', 'org-admin/roles', ['user_id' => $targetId, 'role_id' => $this->roleId($slug)], $orgId);
    }

    private function holds(User $u, int $orgId, string $slug): bool
    {
        return DB::table('user_org_roles')->where('user_id', $u->id)->where('org_id', $orgId)
            ->where('role_id', $this->roleId($slug))->where('is_active', true)->exists();
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

    private function submit(string $path, User $u)
    {
        $client = $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $this->orgA->id]);

        if ($path === 'checkout') {
            $this->stockPallet($u);

            return $client->from('/checkout')->post(route('checkout.process'), [
                'name' => 'Jobsite GC', 'project_name' => 'Tower', 'email' => 'a@b.test', 'phone' => '555',
                'address1' => '1 Main', 'city' => 'Town', 'state' => 'TX', 'postcode' => '75001',
            ]);
        }

        return $client->from('/rfq')->post(route('rfq.convert', $this->convertible()));
    }

    private function lastOrder(): object
    {
        return DB::table('orders')->orderByDesc('id')->first();
    }

    // ---- role assignment: what an owner could assign before is still assignable ---------------------

    #[DataProvider('modes')]
    public function test_an_owner_can_still_assign_every_role_except_platform_and_seller_roles_to_a_member(string $mode): void
    {
        $this->setMode($mode);
        $roles = Role::whereNotIn('category', ['Platform', 'Manufacturer/Seller'])->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(30, $roles->count());

        foreach ($roles as $role) {
            $member = $this->mkUser($this->orgA, 'viewer_read_only');

            $this->assignVia($this->ownerA, $member->id, $role->slug)->assertOk()->assertJson(['success' => true, 'role_id' => $role->id]);

            $this->assertTrue($this->holds($member, $this->orgA->id, $role->slug), $role->slug);
        }

        $this->assertSame(0, AuditLog::where('outcome', 'blocked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_manufacturer_owner_can_still_assign_every_non_platform_role_including_sales_rep(string $mode): void
    {
        $this->setMode($mode);
        $mfr = Organization::create(['name' => 'Mfr', 'org_type' => 'manufacturer', 'team_size' => 'solo']);
        $owner = $this->mkUser($mfr, 'manufacturer_admin');
        $this->assignRole($owner, $mfr, 'organization_owner');
        $roles = Role::where('category', '!=', 'Platform')->orderBy('id')->get();
        $this->assertTrue($roles->contains('slug', 'sales_rep'));

        foreach ($roles as $role) {
            $member = $this->mkUser($mfr, 'viewer_read_only');

            $this->assignVia($owner, $member->id, $role->slug, $mfr->id)->assertOk()->assertJson(['success' => true]);

            $this->assertTrue($this->holds($member, $mfr->id, $role->slug), $role->slug);
        }
    }

    #[DataProvider('modes')]
    public function test_the_seller_role_rule_follows_the_configured_org_type_list(string $mode): void
    {
        $this->setMode($mode);
        $agency = Organization::create(['name' => 'Rep Agency', 'org_type' => 'manufacturer_s_rep_sales_agency', 'team_size' => 'solo']);
        $owner = $this->mkUser($agency, 'organization_owner');
        $staff = $this->mkUser($agency, 'viewer_read_only');

        $this->assignVia($owner, $staff->id, 'sales_rep', $agency->id)->assertForbidden();
        $this->assertFalse($this->holds($staff, $agency->id, 'sales_rep'));

        config(['rbac.onboarding.manufacturer_org_types' => [...config('rbac.onboarding.manufacturer_org_types'), 'manufacturer_s_rep_sales_agency']]);

        $this->assignVia($owner, $staff->id, 'sales_rep', $agency->id)->assertOk();
        $this->assertTrue($this->holds($staff, $agency->id, 'sales_rep'));
    }

    #[DataProvider('modes')]
    public function test_the_assign_answer_keeps_its_shape_and_the_peel_off_hint(string $mode): void
    {
        $this->setMode($mode);
        $member = $this->mkUser($this->orgA, 'viewer_read_only');

        $r = $this->assignVia($this->ownerA, $member->id, 'organization_owner')->assertOk();

        $this->assertSame(['success', 'message', 'peel_off_candidate', 'role_id', 'role_name'], array_keys($r->json()));
        $this->assertSame('Assigned Organization Owner.', $r->json('message'));
        $this->assertTrue($r->json('peel_off_candidate'));

        $other = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->assertFalse($this->assignVia($this->ownerA, $other->id, 'foreman')->assertOk()->json('peel_off_candidate'));
    }

    #[DataProvider('modes')]
    public function test_separation_of_duties_still_refuses_both_directions_of_every_active_rule_on_the_org_screen(string $mode): void
    {
        $this->setMode($mode);
        $rules = DB::table('sod_conflict_rules')->where('is_active', true)->get();
        $this->assertGreaterThanOrEqual(8, $rules->count());

        foreach ($rules as $rule) {
            $a = Role::findOrFail($rule->role_id_a);
            $b = Role::findOrFail($rule->role_id_b);

            foreach ([[$a, $b], [$b, $a]] as [$held, $incoming]) {
                $member = $this->mkUser($this->orgA, $held->slug);

                $r = $this->assignVia($this->ownerA, $member->id, $incoming->slug)->assertStatus(422);

                $this->assertStringContainsString('SoD conflict', (string) $r->json('message'), "$held->slug then $incoming->slug");
                $this->assertFalse($this->holds($member, $this->orgA->id, $incoming->slug), "$held->slug then $incoming->slug");
                $this->assertTrue($this->holds($member, $this->orgA->id, $held->slug));
            }
        }
    }

    #[DataProvider('modes')]
    public function test_the_platform_admin_screen_still_assigns_platform_and_seller_roles_to_anyone_in_any_org(string $mode): void
    {
        $this->setMode($mode);
        $admin = User::factory()->create(['role' => 'admin']);
        $nonMember = $this->mkUser($this->orgB, 'viewer_read_only');

        $this->actingAs($admin)->post(route('admin.rbac.roles.assign', $this->orgA), [
            'user_id' => $nonMember->id, 'role_id' => $this->roleId('platform_super_admin'),
        ])->assertSessionHas('success', 'Role assigned.');
        $this->actingAs($admin)->post(route('admin.rbac.roles.assign', $this->orgA), [
            'user_id' => $nonMember->id, 'role_id' => $this->roleId('sales_rep'),
        ])->assertSessionHas('success', 'Role assigned.');

        $this->assertTrue($this->holds($nonMember, $this->orgA->id, 'platform_super_admin'));
        $this->assertTrue($this->holds($nonMember, $this->orgA->id, 'sales_rep'));
    }

    // ---- invites -----------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_brand_new_user_invited_by_e_mail_still_registers_through_the_register_link_with_the_invited_role(string $mode): void
    {
        $this->setMode($mode);

        $r = $this->as($this->ownerA, 'POST', 'org-admin/invite', [
            'email' => 'new.hire@example.test', 'name' => 'New Hire', 'role_id' => $this->roleId('procurement_coordinator'),
        ])->assertOk()->assertJson(['type' => 'invite', 'email_sent' => true]);

        $token = DB::table('org_invites')->where('email', 'new.hire@example.test')->value('token');
        $this->assertSame(url("/register/invite/$token"), $r->json('link'));
        Mail::assertSent(OrgInviteMail::class, fn ($m) => $m->hasTo('new.hire@example.test')
            && $m->existingAccount === false && $m->inviteUrl === url("/register/invite/$token"));

        $this->app['auth']->forgetGuards();
        $this->get($r->json('link'))->assertOk()->assertSee('Procurement Coordinator');
        $this->post($r->json('link'), ['name' => 'New Hire', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas(config('rbac.current_org_session_key'), $this->orgA->id);

        $user = User::where('email', 'new.hire@example.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($this->holds($user, $this->orgA->id, 'procurement_coordinator'));
        $this->assertNotNull(DB::table('org_invites')->where('token', $token)->value('used_at'));
        $this->assertSame($this->ownerA->id, (int) DB::table('role_assignment_logs')->where('target_user_id', $user->id)->value('performed_by'));
    }

    public function test_a_manufacturer_owner_can_still_invite_a_new_hire_into_a_seller_role(): void
    {
        $mfr = Organization::create(['name' => 'Mfr', 'org_type' => 'manufacturer', 'team_size' => 'solo']);
        $owner = $this->mkUser($mfr, 'manufacturer_admin');

        $r = $this->as($owner, 'POST', 'org-admin/invite', [
            'email' => 'rep@example.test', 'name' => 'Rep', 'role_id' => $this->roleId('sales_rep'),
        ], $mfr->id)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->post($r->json('link'), ['name' => 'Rep', 'password' => 'password123', 'password_confirmation' => 'password123'])
            ->assertRedirect(route('user.dashboard'));
        $this->assertTrue($this->holds(User::where('email', 'rep@example.test')->first(), $mfr->id, 'sales_rep'));
    }

    // ---- signup -------------------------------------------------------------------------------------

    public static function signups(): array
    {
        return [
            'small buyer gets the four role bundle' => ['subcontractor', 'solo', ['organization_owner', 'procurement_manager', 'estimator', 'executive_approver']],
            'small manufacturer gets the manufacturer bundle' => ['manufacturer', '2-5', ['manufacturer_admin', 'product_manager', 'catalog_data_steward']],
            'larger team gets the owner only' => ['general_contractor', '20+', ['organization_owner']],
        ];
    }

    #[DataProvider('signups')]
    public function test_signup_still_creates_the_organization_and_the_owner_bundle_and_sends_the_verification_mail(string $orgType, string $teamSize, array $bundle): void
    {
        Notification::fake();

        $this->post('register', [
            'name' => 'Fresh Owner', 'email' => 'fresh.owner@example.test', 'password' => 'password123', 'password_confirmation' => 'password123',
            'company_name' => 'Fresh Co', 'org_type' => $orgType, 'team_size' => $teamSize,
        ])->assertRedirect(route('verification.notice'))
            ->assertSessionHas(config('rbac.current_org_session_key'));

        $user = User::where('email', 'fresh.owner@example.test')->first();
        $org = Organization::where('name', 'Fresh Co')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($org);
        $this->assertSame($orgType, $org->org_type);
        $this->assertSame($teamSize, $org->team_size);
        $slugs = DB::table('user_org_roles')->join('roles', 'roles.id', '=', 'user_org_roles.role_id')
            ->where('user_org_roles.user_id', $user->id)->where('user_org_roles.org_id', $org->id)->where('user_org_roles.is_active', true)
            ->orderBy('user_org_roles.id')->pluck('roles.slug')->all();
        $this->assertSame($bundle, $slugs);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    // ---- ownership and delegation to a legitimate member -----------------------------------------------

    #[DataProvider('modes')]
    public function test_after_a_transfer_to_a_member_the_new_owner_holds_owner_powers_and_the_old_owner_does_not(string $mode): void
    {
        $this->setMode($mode);
        $heir = $this->mkUser($this->orgA, 'estimator');
        $target = $this->mkUser($this->orgA, 'viewer_read_only');

        $this->as($this->ownerA, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $heir->email])->assertStatus(302);

        $this->assertTrue($this->holds($heir, $this->orgA->id, 'organization_owner'));
        $this->assertFalse($this->holds($this->ownerA, $this->orgA->id, 'organization_owner'));
        $this->assertSame(200, $this->assignVia($heir, $target->id, 'foreman')->getStatusCode());
        $this->assertSame(403, $this->assignVia($this->ownerA, $target->id, 'architect')->getStatusCode());
    }

    #[DataProvider('modes')]
    public function test_an_owner_delegates_to_a_member_who_then_acts_with_the_owners_authority_until_it_is_revoked(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'viewer_read_only');
        $target = $this->mkUser($this->orgA, 'viewer_read_only');

        $this->assignVia($delegate, $target->id, 'foreman')->assertForbidden();
        $this->assertFalse($this->holds($target, $this->orgA->id, 'foreman'));

        $this->as($this->ownerA, 'POST', 'org-admin/delegations', [
            'to_user_id' => $delegate->id, 'starts_at' => now()->subHour()->toDateTimeString(), 'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(302);

        $this->assignVia($delegate, $target->id, 'foreman')->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($this->holds($target, $this->orgA->id, 'foreman'));

        $delegationId = (int) DB::table('delegations')->where('from_user_id', $this->ownerA->id)->where('to_user_id', $delegate->id)->value('id');
        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/$delegationId")->assertStatus(302);

        $this->assignVia($delegate, $target->id, 'architect')->assertForbidden();
        $this->assertFalse($this->holds($target, $this->orgA->id, 'architect'));
    }

    // ---- the order workflow with several approvers -----------------------------------------------------

    #[DataProvider('paths')]
    public function test_a_non_approver_order_is_routed_to_the_approvers_and_one_of_them_approves_it(string $path, string $mode): void
    {
        $this->setMode($mode);
        $pool = app(ApprovalRoutingService::class)->approverPool($this->orgA->id);
        $this->assertContains($this->approverA->id, $pool);
        $this->assertContains($this->approverB->id, $pool);
        $this->assertNotContains($this->requester->id, $pool);

        $this->submit($path, $this->requester)->assertRedirect();

        $order = $this->lastOrder();
        $this->assertSame('pending_approval', $order->status);
        $this->assertNull($order->approved_by);
        $this->assertSame($this->requester->id, (int) $order->user_id);
        $this->assertSame($this->orgA->id, (int) $order->org_id);
        $this->assertSame(0, AuditLog::whereIn('outcome', ['auto_approved', 'self_approved'])->count());

        $queue = $this->actingAs($this->approverA)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('order.approvals'))->assertOk();
        $this->assertContains((int) $order->id, $queue->viewData('pendingOrders')->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([], $queue->viewData('awaitingOthers'));
        $queue->assertSee('approveModal'.$order->id)->assertDontSee('Awaiting another approver');

        $this->as($this->approverA, 'POST', "orders/{$order->id}/approve", ['note' => 'fine'])->assertRedirect(route('order.approvals'));

        $after = DB::table('orders')->where('id', $order->id)->first();
        $this->assertSame('processing', $after->status);
        $this->assertSame($this->approverA->id, (int) $after->approved_by);
        $this->assertSame('fine', $after->approval_note);
        $this->assertSame(0, AuditLog::whereIn('outcome', ['auto_approved', 'self_approved'])->count());
        $this->as($this->approverB, 'POST', "orders/{$order->id}/approve", ['note' => 'again'])->assertForbidden();
        $this->assertSame($this->approverA->id, (int) DB::table('orders')->where('id', $order->id)->value('approved_by'));
    }

    #[DataProvider('modes')]
    public function test_an_approver_who_is_not_the_submitter_can_still_reject_an_order(string $mode): void
    {
        $this->setMode($mode);
        $this->submit('checkout', $this->requester)->assertRedirect();
        $order = $this->lastOrder();

        $this->as($this->approverB, 'POST', "orders/{$order->id}/reject", ['note' => 'no'])->assertRedirect(route('order.approvals'));

        $after = DB::table('orders')->where('id', $order->id)->first();
        $this->assertSame('cancelled', $after->status);
        $this->assertSame($this->approverB->id, (int) $after->rejected_by);
        $this->assertNull($after->approved_by);
    }

    #[DataProvider('modes')]
    public function test_the_approvals_queue_lists_pending_approval_orders_of_the_org_only_with_buttons(string $mode): void
    {
        $this->setMode($mode);
        $row = fn (array $over) => (int) DB::table('orders')->insertGetId($over + [
            'user_id' => $this->requester->id, 'org_id' => $this->orgA->id, 'order_number' => 'ORD-'.uniqid(), 'name' => 'O',
            'status' => 'pending_approval', 'total' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $listed = $row([]);
        $legacyPending = $row(['status' => 'pending']);
        $otherOrg = $row(['org_id' => $this->orgB->id, 'user_id' => $this->outsider->id]);
        $processing = $row(['status' => 'processing', 'approved_by' => $this->approverB->id]);

        $queue = $this->actingAs($this->approverA)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('order.approvals'))->assertOk();

        $this->assertSame([$listed], $queue->viewData('pendingOrders')->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([], $queue->viewData('awaitingOthers'));
        $queue->assertSee('approveModal'.$listed)->assertSee('rejectModal'.$listed)->assertDontSee('Awaiting another approver');
        $this->assertNotContains($legacyPending, [$listed]);
        $this->assertNotSame($otherOrg, $processing);
    }

    #[DataProvider('modes')]
    public function test_checkout_by_a_user_whose_org_has_no_approver_is_still_refused(string $mode): void
    {
        $this->setMode($mode);
        DB::table('user_org_roles')->whereIn('user_id', [$this->ownerA->id, $this->approverA->id, $this->approverB->id])->update(['is_active' => false]);
        $this->assertSame([], app(ApprovalRoutingService::class)->approverPool($this->orgA->id));

        $this->submit('checkout', $this->requester)->assertRedirect('/checkout')->assertSessionHas('error');

        $this->assertSame(0, DB::table('orders')->count());
    }

    // ---- audit pages still label the old rows the old way ---------------------------------------------------

    private function oldRows(): void
    {
        foreach ([
            ['would_block', 'no_grant'],
            ['blocked', 'controller_denied'],
            ['allowed_universal_admin', 'universal_assign_non_member'],
            ['enforcement_changed', 'mode=audit'],
        ] as [$outcome, $reason]) {
            AuditLog::create([
                'user_id' => $this->ownerA->id, 'org_id' => $this->orgA->id, 'method' => 'POST', 'route_uri' => 'org-admin/roles',
                'permission_group' => 'user_management', 'required_level' => 'F', 'outcome' => $outcome, 'reason' => $reason,
            ]);
        }
    }

    public function test_the_org_audit_page_labels_old_rows_with_the_old_labels(): void
    {
        $this->oldRows();

        $html = $this->actingAs($this->ownerA)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get('org-admin/audit-log?tab=enforcement')->assertOk()->getContent();

        foreach (['>Would Block</span>', '>Blocked</span>', '>Platform admin</span>', '>Setting changed</span>'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('controller denied', $html);
        $this->assertStringContainsString('universal assign non member', $html);
        $this->assertStringNotContainsString('>Auto-approved</span>', $html);
        $this->assertStringNotContainsString('>Self-approved (sole approver)</span>', $html);
    }

    public function test_the_admin_audit_pages_label_old_rows_with_the_old_labels(): void
    {
        $this->oldRows();
        $admin = User::factory()->create(['role' => 'admin']);

        $logs = $this->actingAs($admin)->get('admin/rbac/audit-logs')->assertOk()->getContent();
        foreach (['badge bg-warning text-dark">Would Block</span>', 'badge bg-danger">Denied</span>', 'badge bg-info">Platform admin</span>', 'badge bg-secondary">Setting changed</span>'] as $label) {
            $this->assertStringContainsString($label, $logs);
        }
        $this->assertStringNotContainsString('badge bg-success">Auto-approved</span>', $logs);

        $enforcement = $this->actingAs($admin)->get('admin/rbac/enforcement')->assertOk()->getContent();
        foreach (['>Blocked</span>', '>Would Block</span>', '>Platform admin</span>', '>Setting changed</span>'] as $label) {
            $this->assertStringContainsString($label, $enforcement);
        }
        $this->assertStringNotContainsString('bg-label-success me-1">Auto-approved</span>', $enforcement);
    }
}

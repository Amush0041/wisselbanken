<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\User;
use App\Services\Rbac\ApprovalRoutingService;
use App\Services\Rbac\DelegationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, A05: an approver cannot approve or reject their own order (or the order of the principal they
 * act for) while another approver exists. The sole approver of an org still can, and the approval is logged.
 */
class OrderSelfApprovalTest extends ProjectTestCase
{
    private const OWN_APPROVE = 'You cannot approve your own order while another approver is available.';
    private const OWN_REJECT = 'You cannot reject your own order while another approver is available.';

    /** requisitioner who later also got executive_approver by a direct row insert (bypasses SoD) */
    private User $sub;
    /** executive_approver, the other approver */
    private User $mitch;
    private User $ua;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('org_id')->nullable();
            $t->string('order_number')->unique();
            $t->string('name')->nullable();
            $t->string('status')->default('pending');
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

        $this->sub = $this->mkUser($this->orgA, 'requisitioner');
        $this->assignRole($this->sub, $this->orgA, 'executive_approver');
        $this->mitch = $this->mkUser($this->orgA, 'executive_approver');
        $this->ua = User::factory()->create(['role' => 'user']);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function order(User $submitter, ?int $orgId = null, string $status = 'pending_approval'): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => $submitter->id, 'org_id' => $orgId ?? $this->orgA->id, 'order_number' => 'ORD-'.uniqid(),
            'name' => 'Order', 'status' => $status, 'total' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function act(User $actor, string $verb, int $orderId, ?int $orgId = null)
    {
        return $this->actingAs($actor)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->postJson("orders/$orderId/$verb", ['note' => 'ok']);
    }

    private function row(int $orderId): object
    {
        return DB::table('orders')->where('id', $orderId)->first();
    }

    private function onlyApprover(User $keep): void
    {
        DB::table('user_org_roles')
            ->whereIn('user_id', DB::table('user_org_roles')->where('org_id', $this->orgA->id)->pluck('user_id')->reject(fn ($id) => (int) $id === $keep->id)->all())
            ->where('org_id', $this->orgA->id)
            ->whereIn('role_id', DB::table('role_permissions')
                ->join('permission_groups', 'permission_groups.id', '=', 'role_permissions.permission_group_id')
                ->where('permission_groups.slug', 'approval_authority')->whereIn('role_permissions.access_level', ['F', 'A'])
                ->pluck('role_permissions.role_id')->all())
            ->update(['is_active' => false]);
        $this->assertSame([$keep->id], app(ApprovalRoutingService::class)->approverPool($this->orgA->id));
    }

    private function assertDeniedAsOwnOrder(int $orderId, User $actor): void
    {
        $order = $this->row($orderId);
        $this->assertSame('pending_approval', $order->status);
        $this->assertNull($order->approved_by);
        $this->assertNull($order->rejected_by);
        $this->assertNull($order->approval_note);
        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
        $denied = AuditLog::where('user_id', $actor->id)->where('outcome', 'blocked')->where('reason', 'controller_denied')->latest('id')->first();
        $this->assertNotNull($denied, 'a controller_denied Blocked row must exist');
    }

    // ---- the guard -----------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_an_approver_cannot_approve_their_own_order_while_another_approver_exists(string $mode): void
    {
        $this->setMode($mode);
        $order = $this->order($this->sub);

        $this->act($this->sub, 'approve', $order)->assertForbidden()->assertJson(['message' => self::OWN_APPROVE]);

        $this->assertDeniedAsOwnOrder($order, $this->sub);
    }

    #[DataProvider('modes')]
    public function test_an_approver_cannot_reject_their_own_order_while_another_approver_exists(string $mode): void
    {
        $this->setMode($mode);
        $order = $this->order($this->sub);

        $this->act($this->sub, 'reject', $order)->assertForbidden()->assertJson(['message' => self::OWN_REJECT]);

        $this->assertDeniedAsOwnOrder($order, $this->sub);
    }

    #[DataProvider('modes')]
    public function test_the_other_approver_can_still_approve_and_reject_it(string $mode): void
    {
        $this->setMode($mode);
        $approve = $this->order($this->sub);
        $reject = $this->order($this->sub);

        $this->act($this->mitch, 'approve', $approve)->assertRedirect(route('order.approvals'));
        $this->act($this->mitch, 'reject', $reject)->assertRedirect(route('order.approvals'));

        $a = $this->row($approve);
        $this->assertSame('processing', $a->status);
        $this->assertSame($this->mitch->id, (int) $a->approved_by);
        $r = $this->row($reject);
        $this->assertSame('cancelled', $r->status);
        $this->assertSame($this->mitch->id, (int) $r->rejected_by);
        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_the_sole_approver_may_approve_their_own_order_and_it_is_logged(string $mode): void
    {
        $this->setMode($mode);
        $this->onlyApprover($this->sub);
        $order = $this->order($this->sub);

        $this->act($this->sub, 'approve', $order)->assertRedirect(route('order.approvals'));

        $row = $this->row($order);
        $this->assertSame('processing', $row->status);
        $this->assertSame($this->sub->id, (int) $row->approved_by);
        $logs = AuditLog::where('outcome', 'self_approved')->get();
        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame($this->sub->id, (int) $log->user_id);
        $this->assertSame($this->orgA->id, (int) $log->org_id);
        $this->assertSame('approval_authority', $log->permission_group);
        $this->assertSame('A', $log->required_level);
        $this->assertSame('sole_approver_self_approval order '.$row->order_number, $log->reason);
        $this->assertSame('POST', $log->method);
        $this->assertSame('orders/{order}/approve', $log->route_uri);
    }

    #[DataProvider('modes')]
    public function test_the_sole_approver_may_reject_their_own_order_without_a_log_row(string $mode): void
    {
        $this->setMode($mode);
        $this->onlyApprover($this->sub);
        $order = $this->order($this->sub);

        $this->act($this->sub, 'reject', $order)->assertRedirect(route('order.approvals'));

        $row = $this->row($order);
        $this->assertSame('cancelled', $row->status);
        $this->assertSame($this->sub->id, (int) $row->rejected_by);
        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_a_submitter_holding_several_approver_roles_is_still_one_person(string $mode): void
    {
        $this->setMode($mode);
        $this->onlyApprover($this->sub);
        $this->assignRole($this->sub, $this->orgA, 'organization_owner');
        $this->assignRole($this->sub, $this->orgA, 'procurement_manager');
        $this->assertSame(4, DB::table('user_org_roles')->where('user_id', $this->sub->id)->where('org_id', $this->orgA->id)->where('is_active', true)->count());
        $this->assertSame([$this->sub->id], app(ApprovalRoutingService::class)->approverPool($this->orgA->id));
        $order = $this->order($this->sub);

        $this->act($this->sub, 'approve', $order)->assertRedirect(route('order.approvals'));

        $this->assertSame('processing', $this->row($order)->status);
        $this->assertSame(1, AuditLog::where('outcome', 'self_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_a_second_approver_with_inactive_rows_only_does_not_count(string $mode): void
    {
        $this->setMode($mode);
        DB::table('user_org_roles')->where('user_id', $this->mitch->id)->update(['is_active' => false]);
        $order = $this->order($this->sub);

        $this->act($this->sub, 'approve', $order)->assertRedirect(route('order.approvals'));

        $this->assertSame('processing', $this->row($order)->status);
    }

    #[DataProvider('modes')]
    public function test_an_approver_in_another_org_does_not_count_as_another_approver(string $mode): void
    {
        $this->setMode($mode);
        $this->mkUser($this->orgB, 'executive_approver');
        DB::table('user_org_roles')->where('user_id', $this->mitch->id)->update(['is_active' => false]);
        $order = $this->order($this->sub);

        $this->act($this->sub, 'approve', $order)->assertRedirect(route('order.approvals'));

        $this->assertSame('processing', $this->row($order)->status);
    }

    #[DataProvider('modes')]
    public function test_a_delegate_of_the_submitter_cannot_approve_or_reject_the_principals_order(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'estimator');
        app(DelegationService::class)->grant($this->sub->id, $this->sub->id, $delegate->id, $this->orgA->id, now()->subHour(), now()->addDay());
        $order = $this->order($this->sub);

        $this->act($delegate, 'approve', $order)->assertForbidden()->assertJson(['message' => self::OWN_APPROVE]);
        $this->act($delegate, 'reject', $order)->assertForbidden()->assertJson(['message' => self::OWN_REJECT]);

        $this->assertDeniedAsOwnOrder($order, $delegate);
    }

    #[DataProvider('modes')]
    public function test_a_delegate_of_the_sole_approver_may_decide_the_principals_order(string $mode): void
    {
        $this->setMode($mode);
        $this->onlyApprover($this->sub);
        $delegate = $this->mkUser($this->orgA, 'estimator');
        app(DelegationService::class)->grant($this->sub->id, $this->sub->id, $delegate->id, $this->orgA->id, now()->subHour(), now()->addDay());
        $order = $this->order($this->sub);

        $this->act($delegate, 'approve', $order)->assertRedirect(route('order.approvals'));

        $row = $this->row($order);
        $this->assertSame('processing', $row->status);
        $this->assertSame($delegate->id, (int) $row->approved_by);
        $this->assertSame(1, AuditLog::where('outcome', 'self_approved')->where('user_id', $delegate->id)->count());
    }

    #[DataProvider('modes')]
    public function test_a_delegate_of_another_approver_may_decide_a_third_partys_order(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'estimator');
        app(DelegationService::class)->grant($this->mitch->id, $this->mitch->id, $delegate->id, $this->orgA->id, now()->subHour(), now()->addDay());
        $order = $this->order($this->sub);

        $this->act($delegate, 'approve', $order)->assertRedirect(route('order.approvals'));

        $this->assertSame('processing', $this->row($order)->status);
        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_an_expired_or_revoked_delegation_gives_no_own_order_status(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'estimator');
        $svc = app(DelegationService::class);
        $svc->grant($this->sub->id, $this->sub->id, $delegate->id, $this->orgA->id, now()->subDays(3), now()->subDay());
        $order = $this->order($this->sub);

        $this->act($delegate, 'approve', $order)->assertForbidden()->assertJsonMissing(['message' => self::OWN_APPROVE]);

        $this->assertSame('pending_approval', $this->row($order)->status);
    }

    #[DataProvider('modes')]
    public function test_an_order_of_another_org_is_still_refused(string $mode): void
    {
        $this->setMode($mode);
        $outsider = $this->mkUser($this->orgB, 'estimator');
        $order = $this->order($outsider, $this->orgB->id);

        $this->act($this->mitch, 'approve', $order)->assertForbidden();
        $this->act($this->mitch, 'reject', $order)->assertForbidden();

        $this->assertSame('pending_approval', $this->row($order)->status);
    }

    #[DataProvider('modes')]
    public function test_a_non_approver_is_still_refused(string $mode): void
    {
        $this->setMode($mode);
        $order = $this->order($this->sub);

        $this->act($this->viewer, 'approve', $order)->assertForbidden();
        $this->act($this->viewer, 'reject', $order)->assertForbidden();

        $this->assertSame('pending_approval', $this->row($order)->status);
    }

    #[DataProvider('modes')]
    public function test_an_order_that_is_no_longer_pending_is_still_refused(string $mode): void
    {
        $this->setMode($mode);
        $this->onlyApprover($this->sub);
        $order = $this->order($this->sub, null, 'processing');

        $this->act($this->sub, 'approve', $order)->assertForbidden();

        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_gets_no_exemption_for_their_own_order_but_may_decide_others(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);
        $own = $this->order($this->ua);
        $others = $this->order($this->sub);

        $this->act($this->ua, 'approve', $own)->assertForbidden()->assertJson(['message' => self::OWN_APPROVE]);
        $this->act($this->ua, 'reject', $own)->assertForbidden()->assertJson(['message' => self::OWN_REJECT]);
        $this->assertSame('pending_approval', $this->row($own)->status);

        $this->act($this->ua, 'approve', $others)->assertRedirect(route('order.approvals'));
        $this->assertSame('processing', $this->row($others)->status);
        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
    }

    // ---- the approvals list --------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_list_flags_own_orders_while_another_approver_exists(string $mode): void
    {
        $this->setMode($mode);
        $own = $this->order($this->sub);
        $other = $this->order($this->mitch);
        $third = $this->order($this->mkUser($this->orgA, 'requisitioner'));

        $response = $this->actingAs($this->sub)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('order.approvals'))->assertOk();

        $this->assertSame([$own], $response->viewData('awaitingOthers'));
        $this->assertEqualsCanonicalizing([$own, $other, $third], $response->viewData('pendingOrders')->pluck('id')->all());
        $response->assertSee('Awaiting another approver');
    }

    #[DataProvider('modes')]
    public function test_the_list_flags_nothing_for_the_sole_approver_or_for_a_plain_approver(string $mode): void
    {
        $this->setMode($mode);
        $own = $this->order($this->sub);
        $this->actingAs($this->mitch)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('order.approvals'))->assertOk()->assertViewHas('awaitingOthers', []);

        $this->onlyApprover($this->sub);
        $this->actingAs($this->sub)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('order.approvals'))->assertOk()->assertViewHas('awaitingOthers', []);
        $this->assertSame('pending_approval', $this->row($own)->status);
    }

    #[DataProvider('modes')]
    public function test_the_list_flags_the_principals_orders_for_a_delegate(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'estimator');
        app(DelegationService::class)->grant($this->sub->id, $this->sub->id, $delegate->id, $this->orgA->id, now()->subHour(), now()->addDay());
        $principals = $this->order($this->sub);
        $other = $this->order($this->mitch);

        $this->actingAs($delegate)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get(route('order.approvals'))->assertOk()->assertViewHas('awaitingOthers', [$principals]);
        $this->assertNotSame($principals, $other);
    }

    #[DataProvider('modes')]
    public function test_a_delegate_cannot_decide_their_own_order_through_the_delegation(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'project_manager');
        app(DelegationService::class)->grant($this->mitch->id, $this->mitch->id, $delegate->id, $this->orgA->id, now()->subHour(), now()->addDay());
        $own = $this->order($delegate);

        $this->act($delegate, 'approve', $own)->assertForbidden()->assertJson(['message' => self::OWN_APPROVE]);
        $this->act($delegate, 'reject', $own)->assertForbidden()->assertJson(['message' => self::OWN_REJECT]);

        $this->assertDeniedAsOwnOrder($own, $delegate);
    }

    #[DataProvider('modes')]
    public function test_a_delegate_of_the_sole_approver_cannot_decide_their_own_order_either(string $mode): void
    {
        $this->setMode($mode);
        $this->onlyApprover($this->sub);
        $delegate = $this->mkUser($this->orgA, 'project_manager');
        app(DelegationService::class)->grant($this->sub->id, $this->sub->id, $delegate->id, $this->orgA->id, now()->subHour(), now()->addDay());
        $own = $this->order($delegate);

        $this->act($delegate, 'approve', $own)->assertForbidden()->assertJson(['message' => self::OWN_APPROVE]);
        $this->act($delegate, 'reject', $own)->assertForbidden()->assertJson(['message' => self::OWN_REJECT]);

        $this->assertDeniedAsOwnOrder($own, $delegate);
    }

    #[DataProvider('modes')]
    public function test_an_audit_failure_leaves_the_sole_approvers_own_order_pending(string $mode): void
    {
        $this->setMode($mode);
        $this->onlyApprover($this->sub);
        $order = $this->order($this->sub);
        AuditLog::creating(function (AuditLog $row) {
            if ($row->outcome === 'self_approved') {
                throw new \RuntimeException('audit store is down');
            }
        });

        $this->act($this->sub, 'approve', $order)->assertStatus(500);

        $row = $this->row($order);
        $this->assertSame('pending_approval', $row->status);
        $this->assertNull($row->approved_by);
        $this->assertSame(0, AuditLog::where('outcome', 'self_approved')->count());
    }

    #[DataProvider('modes')]
    public function test_an_audit_outage_does_not_block_an_ordinary_approval(string $mode): void
    {
        $this->setMode($mode);
        $order = $this->order($this->sub);
        AuditLog::creating(function (AuditLog $row) {
            if ($row->outcome === 'self_approved') {
                throw new \RuntimeException('audit store is down');
            }
        });

        $this->act($this->mitch, 'approve', $order)->assertRedirect(route('order.approvals'));

        $this->assertSame('processing', $this->row($order)->status);
        $this->assertSame($this->mitch->id, (int) $this->row($order)->approved_by);
    }
}

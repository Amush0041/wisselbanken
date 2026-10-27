<?php

namespace Tests\Feature\Rbac;

use App\Http\Middleware\RbacAudit;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\OrgRelationship;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, O05: a record, project or quote of another organization answers 404, the same as one that does not
 * exist, instead of a "not a member" 403. A same-org non-member still gets the membership message, and a role below the
 * level still gets the generic 403. Middleware cases run in enforce mode (audit mode reaches the controllers); controller
 * cases run in both modes.
 */
class CrossOrgNotFoundTest extends ProjectTestCase
{
    private const MEMBERSHIP = 'You are not a member of this project. Ask an organization owner or project manager to add you before you can continue.';
    private const NOT_PENDING = 'This order is no longer pending approval.';

    private User $ownerA;
    private User $approverA;
    private User $ownerB;
    private int $trashedProject;

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

        config(['route_permission_map' => config('route_permission_map') + [
            'GET probe/q/{id}' => ['estimate_management', 'R', 'batch' => 'read', 'quote_param' => 'id'],
            'GET probe/p/{id}' => ['estimate_management', 'R', 'batch' => 'read', 'project_param' => 'id'],
            'PUT probe/w/{id}' => ['estimate_management', 'O', 'batch' => 'write', 'quote_param' => 'id'],
        ]]);
        Route::middleware([StartSession::class, SubstituteBindings::class, RbacAudit::class])->group(function () {
            Route::get('probe/q/{id}', fn () => 'ran');
            Route::get('probe/p/{id}', fn () => 'ran');
            Route::put('probe/w/{id}', fn () => 'ran');
        });

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->approverA = $this->mkUser($this->orgA, 'executive_approver');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->trashedProject = $this->mkProject($this->orgA, $this->est, 'Trashed', now()->toDateTimeString());

        config(['rbac.universal_admin_user_ids' => [], 'app.debug' => false]);
        $this->setMode('enforce');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function asUser(User $u, string $method, string $uri, ?int $orgId = null, array $data = [])
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->json($method, $uri, $data);
    }

    private function page(User $u, string $uri, ?int $orgId = null)
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->get($uri);
    }

    private function lastRow(): AuditLog
    {
        return AuditLog::orderByDesc('id')->firstOrFail();
    }

    private function controllerDenials(): int
    {
        return AuditLog::where('reason', 'controller_denied')->count();
    }

    private function assertDenialRow(AuditLog $row, User $actor, int $orgId, string $pattern, string $group, string $level): void
    {
        $this->assertSame(
            ['blocked', 'controller_denied', $actor->id, $orgId, substr($pattern, strpos($pattern, ' ') + 1), $pattern, $group, $level],
            [$row->outcome, $row->reason, (int) $row->user_id, (int) $row->org_id, $row->route_uri, $row->matched_pattern, $row->permission_group, $row->required_level]
        );
    }

    // ---- middleware: scoped project / quote routes ------------------------------------------------------------

    public static function hiddenScopes(): array
    {
        return [
            'another org project' => ['probe/p/{p3}', 'no_grant_or_not_project_member'],
            'missing project' => ['probe/p/999999', 'no_grant_or_not_project_member'],
            'soft deleted project' => ['probe/p/{trashed}', 'no_grant_or_not_project_member'],
            'another org quote' => ['probe/q/{q4}', 'no_grant_or_not_project_member'],
            'missing quote' => ['probe/q/999999', 'project_unresolved'],
            'quote without a project' => ['probe/q/{q0}', 'project_unresolved'],
        ];
    }

    private function resolve(string $uri): string
    {
        return strtr($uri, [
            '{p3}' => $this->p3, '{trashed}' => $this->trashedProject, '{q4}' => $this->q4, '{q0}' => $this->q0,
        ]);
    }

    #[DataProvider('hiddenScopes')]
    public function test_a_scope_outside_the_acting_org_answers_a_json_404_and_keeps_its_audit_row(string $uri, string $reason): void
    {
        $r = $this->asUser($this->est, 'GET', $this->resolve($uri))->assertNotFound();

        $this->assertSame(['message' => 'Not found.'], $r->json());
        $row = $this->lastRow();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame($reason, $row->reason);
        $this->assertSame($this->orgA->id, (int) $row->org_id);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_every_hidden_scope_gives_the_same_json_body_and_the_same_html_page(): void
    {
        $json = [];
        $html = [];
        foreach (self::hiddenScopes() as $label => [$uri]) {
            $json[$label] = $this->asUser($this->est, 'GET', $this->resolve($uri))->assertNotFound()->getContent();
            $html[$label] = $this->page($this->est, $this->resolve($uri))->assertNotFound()->getContent();
        }

        $this->assertCount(1, array_unique($json), 'JSON bodies differ');
        $this->assertCount(1, array_unique($html), 'HTML pages differ');
        $this->assertStringNotContainsString('not a member', strtolower(reset($html)));
    }

    #[DataProvider('hiddenScopes')]
    public function test_a_browser_request_for_a_scope_outside_the_org_is_a_404_not_a_redirect(string $uri): void
    {
        $this->actingAs($this->est)->from('/previous')->get($this->resolve($uri))
            ->assertNotFound()
            ->assertSessionMissing('rbac_denied');
    }

    public function test_a_same_org_non_member_keeps_the_403_with_the_membership_text(): void
    {
        $json = $this->asUser($this->stranger, 'GET', "probe/p/{$this->p1}")->assertForbidden();
        $this->assertTrue($json->json('rbac_error'));
        $this->assertSame(self::MEMBERSHIP, $json->json('message'));
        $this->assertSame('no_grant_or_not_project_member', $this->lastRow()->reason);

        $this->asUser($this->stranger, 'GET', "probe/q/{$this->q1}")->assertForbidden()->assertJsonPath('message', self::MEMBERSHIP);
        $this->asUser($this->est, 'GET', "probe/p/{$this->p2}")->assertForbidden()->assertJsonPath('message', self::MEMBERSHIP);

        $browser = $this->actingAs($this->stranger)->from('/previous')->get("probe/p/{$this->p1}");
        $browser->assertStatus(302)->assertRedirect('/previous');
        $this->assertSame(self::MEMBERSHIP, $browser->getSession()->get('rbac_denied'));
    }

    public function test_a_member_below_the_level_keeps_the_generic_403(): void
    {
        $r = $this->asUser($this->viewer, 'PUT', "probe/w/{$this->q1}")->assertForbidden();

        $this->assertStringContainsString("don't have permission", $r->json('message'));
        $this->assertNotSame(self::MEMBERSHIP, $r->json('message'));
        $this->assertSame('no_grant_or_not_project_member', $this->lastRow()->reason);
    }

    public function test_a_user_acting_in_another_org_gets_a_404_for_the_first_orgs_project(): void
    {
        $this->asUser($this->multi, 'GET', "probe/p/{$this->p1}", $this->orgA->id)->assertOk();

        $this->asUser($this->multi, 'GET', "probe/p/{$this->p1}", $this->orgB->id)->assertNotFound();
        $this->assertSame($this->orgB->id, (int) $this->lastRow()->org_id);
    }

    public function test_audit_mode_still_lets_every_scope_through_and_only_records_would_block(): void
    {
        $this->setMode('audit');
        $before = AuditLog::count();

        foreach (self::hiddenScopes() as [$uri, $reason]) {
            $this->asUser($this->est, 'GET', $this->resolve($uri))->assertOk();
            $row = $this->lastRow();
            $this->assertSame('would_block', $row->outcome);
            $this->assertSame($reason, $row->reason);
        }
        $this->assertSame($before + count(self::hiddenScopes()), AuditLog::count());
    }

    public function test_a_member_of_the_project_is_not_affected(): void
    {
        $this->asUser($this->est, 'GET', "probe/p/{$this->p1}")->assertOk();
        $this->asUser($this->est, 'GET', "probe/q/{$this->q1}")->assertOk();
        $this->assertSame(0, AuditLog::count());
    }

    // ---- controller sites: role removal ---------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_removing_a_role_row_of_another_org_is_a_404_like_a_missing_row(string $mode): void
    {
        $this->setMode($mode);
        $foreign = (int) DB::table('user_org_roles')->where('user_id', $this->outsider->id)->value('id');
        $own = (int) DB::table('user_org_roles')->where('user_id', $this->viewer->id)->value('id');

        $other = $this->asUser($this->ownerA, 'DELETE', "org-admin/roles/$foreign")->assertNotFound();
        $this->assertSame(1, $this->controllerDenials());
        $this->assertDenialRow($this->lastRow(), $this->ownerA, $this->orgA->id, 'DELETE org-admin/roles/{userOrgRole}', 'user_management', 'F');

        $missing = $this->asUser($this->ownerA, 'DELETE', 'org-admin/roles/999999')->assertNotFound();

        $this->assertSame($missing->getContent(), $other->getContent());
        $this->assertSame(1, (int) DB::table('user_org_roles')->where('id', $foreign)->value('is_active'));
        $this->assertSame(1, $this->controllerDenials(), 'a missing id writes nothing');

        $this->asUser($this->ownerA, 'DELETE', "org-admin/roles/$own")->assertRedirect();
        $this->assertSame(0, (int) DB::table('user_org_roles')->where('id', $own)->value('is_active'));
        $this->assertSame(1, $this->controllerDenials(), 'the own-org success writes nothing');
        $this->assertSame(1, AuditLog::count());
    }

    #[DataProvider('modes')]
    public function test_removing_an_own_org_role_row_below_the_level_is_still_403(string $mode): void
    {
        $this->setMode($mode);
        $own = (int) DB::table('user_org_roles')->where('user_id', $this->stranger->id)->value('id');

        $this->asUser($this->viewer, 'DELETE', "org-admin/roles/$own")->assertForbidden();

        $this->assertSame(1, (int) DB::table('user_org_roles')->where('id', $own)->value('is_active'));
    }

    // ---- controller sites: connections -----------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_deactivating_a_connection_of_another_org_is_a_404(string $mode): void
    {
        $this->setMode($mode);
        $foreign = OrgRelationship::create(['from_org_id' => $this->orgB->id, 'to_org_id' => $this->orgA->id, 'relationship_type' => 'buyer_seller', 'is_active' => true])->id;
        $own = OrgRelationship::create(['from_org_id' => $this->orgA->id, 'to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller', 'is_active' => true])->id;

        $this->asUser($this->ownerA, 'DELETE', "org-admin/connections/$foreign")->assertNotFound();
        $this->assertDenialRow($this->lastRow(), $this->ownerA, $this->orgA->id, 'DELETE org-admin/connections/{orgRelationship}', 'organization_management', 'F');
        $this->asUser($this->ownerB, 'DELETE', "org-admin/connections/$own", $this->orgB->id)->assertNotFound();
        $this->assertDenialRow($this->lastRow(), $this->ownerB, $this->orgB->id, 'DELETE org-admin/connections/{orgRelationship}', 'organization_management', 'F');
        $this->assertTrue((bool) OrgRelationship::find($foreign)->is_active);
        $this->assertTrue((bool) OrgRelationship::find($own)->is_active);
        $this->assertSame(2, $this->controllerDenials());

        $this->asUser($this->viewer, 'DELETE', "org-admin/connections/$own")->assertForbidden();
        $this->assertTrue((bool) OrgRelationship::find($own)->is_active);
        $before = AuditLog::count();

        $this->asUser($this->ownerA, 'DELETE', "org-admin/connections/$own")->assertRedirect();
        $this->assertFalse((bool) OrgRelationship::find($own)->is_active);
        $this->assertSame($before, AuditLog::count(), 'the own-org success writes nothing');
    }

    // ---- controller sites: project members -------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_removing_a_project_member_row_of_another_org_is_a_404(string $mode): void
    {
        $this->setMode($mode);
        $foreign = (int) DB::table('project_members')->where('project_id', $this->p3)->where('user_id', $this->outsider->id)->value('id');
        $own = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->viewer->id)->value('id');

        $other = $this->asUser($this->est, 'DELETE', "org-admin/projects/members/$foreign")->assertNotFound();
        $this->assertSame(1, $this->controllerDenials());
        $this->assertDenialRow($this->lastRow(), $this->est, $this->orgA->id, 'DELETE org-admin/projects/members/{projectMember}', 'project_management', 'F');

        $missing = $this->asUser($this->est, 'DELETE', 'org-admin/projects/members/999999')->assertNotFound();

        $this->assertSame($missing->getContent(), $other->getContent());
        $this->assertTrue($this->activeMember($this->p3, $this->outsider->id));
        $this->assertSame(1, $this->controllerDenials(), 'a missing id writes nothing');

        $this->asUser($this->viewer, 'DELETE', "org-admin/projects/members/$own")->assertForbidden();
        $this->assertTrue($this->activeMember($this->p1, $this->viewer->id));
        $before = AuditLog::count();

        $this->asUser($this->est, 'DELETE', "org-admin/projects/members/$own")->assertRedirect();
        $this->assertFalse($this->activeMember($this->p1, $this->viewer->id));
        $this->assertSame($before, AuditLog::count(), 'the own-org success writes nothing');
    }

    private function activeMember(int $projectId, int $userId): bool
    {
        return DB::table('project_members')->where('project_id', $projectId)->where('user_id', $userId)->where('is_active', true)->exists();
    }

    // ---- controller sites: RFQ -------------------------------------------------------------------------------------

    private function rfq(int $orgId, ?int $projectId, int $creatorId): int
    {
        return (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $orgId, 'project_id' => $projectId, 'created_by' => $creatorId, 'title' => 'RFQ', 'status' => 'closed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function response(int $rfqId, int $sellerOrgId, int $creatorId): int
    {
        return (int) DB::table('rfq_responses')->insertGetId([
            'rfq_request_id' => $rfqId, 'seller_org_id' => $sellerOrgId, 'created_by' => $creatorId, 'total_price' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[DataProvider('modes')]
    public function test_a_buyer_action_on_an_rfq_of_another_org_is_a_404(string $mode): void
    {
        $this->setMode($mode);
        $foreign = $this->rfq($this->orgB->id, $this->p3, $this->outsider->id);
        $response = $this->response($foreign, $this->orgA->id, $this->est->id);

        $this->asUser($this->ownerA, 'GET', "rfq/$foreign")->assertNotFound();
        $this->assertDenialRow($this->lastRow(), $this->ownerA, $this->orgA->id, 'GET rfq/{rfq}', 'quote_rfq_management', 'R');
        $this->asUser($this->ownerA, 'POST', "rfq/$foreign/responses/$response/select")->assertNotFound();
        $this->assertDenialRow($this->lastRow(), $this->ownerA, $this->orgA->id, 'POST rfq/{rfq}/responses/{response}/select', 'quote_rfq_management', 'O');
        $this->asUser($this->ownerA, 'POST', "rfq/$foreign/convert")->assertNotFound();
        $this->assertDenialRow($this->lastRow(), $this->ownerA, $this->orgA->id, 'POST rfq/{rfq}/convert', 'quote_rfq_management', 'F');
        $this->assertSame('closed', DB::table('rfq_requests')->where('id', $foreign)->value('status'));
        $this->assertSame(3, $this->controllerDenials());

        $ownRfq = $this->rfq($this->orgA->id, null, $this->est->id);
        $this->asUser($this->est, 'GET', "rfq/$ownRfq")->assertOk();
        $this->assertSame(3, AuditLog::count(), 'the own-org success writes nothing');
    }

    #[DataProvider('modes')]
    public function test_a_buyer_action_on_an_own_rfq_below_the_level_is_still_403(string $mode): void
    {
        $this->setMode($mode);
        $own = $this->rfq($this->orgA->id, null, $this->est->id);

        $this->asUser($this->viewer, 'POST', "rfq/$own/convert")->assertForbidden();
        $this->assertSame('closed', DB::table('rfq_requests')->where('id', $own)->value('status'));
    }

    // ---- controller sites: order approval --------------------------------------------------------------------------

    private function order(int $orgId, int $userId, string $status = 'pending_approval'): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => $userId, 'org_id' => $orgId, 'order_number' => 'ORD-'.uniqid(), 'name' => 'Order', 'status' => $status,
            'total' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[DataProvider('modes')]
    public function test_approving_or_rejecting_an_order_of_another_org_is_a_404(string $mode): void
    {
        $this->setMode($mode);
        $foreign = $this->order($this->orgB->id, $this->outsider->id);

        foreach (['approve', 'reject'] as $verb) {
            $this->asUser($this->approverA, 'POST', "orders/$foreign/$verb", null, ['note' => 'x'])->assertNotFound();
        }

        $row = DB::table('orders')->where('id', $foreign)->first();
        $this->assertSame('pending_approval', $row->status);
        $this->assertNull($row->approved_by);
        $this->assertNull($row->rejected_by);
        $this->assertSame(2, $this->controllerDenials());
        $this->assertDenialRow($this->lastRow(), $this->approverA, $this->orgA->id, 'POST orders/{order}/reject', 'approval_authority', 'A');
        $this->assertSame(
            ['POST orders/{order}/approve', 'POST orders/{order}/reject'],
            AuditLog::where('reason', 'controller_denied')->orderBy('id')->pluck('matched_pattern')->all()
        );
        $own = $this->order($this->orgA->id, $this->est->id);
        $this->asUser($this->approverA, 'POST', "orders/$own/approve", null, ['note' => 'x'])->assertRedirect();
        $this->assertSame(2, AuditLog::count(), 'the own-org success writes nothing');
    }

    #[DataProvider('modes')]
    public function test_an_own_org_order_that_is_not_pending_keeps_its_403_and_message(string $mode): void
    {
        $this->setMode($mode);
        $done = $this->order($this->orgA->id, $this->est->id, 'processing');

        foreach (['approve', 'reject'] as $verb) {
            $this->asUser($this->approverA, 'POST', "orders/$done/$verb", null, ['note' => 'x'])
                ->assertForbidden()->assertJsonPath('message', self::NOT_PENDING);
        }
        $this->assertSame('processing', DB::table('orders')->where('id', $done)->value('status'));
    }

    #[DataProvider('modes')]
    public function test_an_order_of_another_org_that_is_not_pending_is_still_a_404(string $mode): void
    {
        $this->setMode($mode);
        $foreignDone = $this->order($this->orgB->id, $this->outsider->id, 'processing');

        $this->asUser($this->approverA, 'POST', "orders/$foreignDone/approve", null, ['note' => 'x'])->assertNotFound();
    }

    #[DataProvider('modes')]
    public function test_a_non_approver_is_still_403_on_an_own_org_order(string $mode): void
    {
        $this->setMode($mode);
        $pending = $this->order($this->orgA->id, $this->est->id);

        $this->asUser($this->viewer, 'POST', "orders/$pending/approve", null, ['note' => 'x'])->assertForbidden();

        $this->assertSame('pending_approval', DB::table('orders')->where('id', $pending)->value('status'));
    }

    // ---- the audit row of the 404 (PLAN 10.13) ----------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_an_own_org_order_that_is_not_pending_leaves_exactly_one_controller_denied_row(string $mode): void
    {
        $this->setMode($mode);
        $done = $this->order($this->orgA->id, $this->est->id, 'processing');

        $this->asUser($this->approverA, 'POST', "orders/$done/approve", null, ['note' => 'x'])->assertForbidden();

        $this->assertSame(1, AuditLog::count());
        $this->assertDenialRow($this->lastRow(), $this->approverA, $this->orgA->id, 'POST orders/{order}/approve', 'approval_authority', 'A');
    }

    public static function belowLevelProbes(): array
    {
        $cases = [];
        foreach (['role', 'connection', 'member', 'rfq'] as $site) {
            $cases["$site audit"] = [$site, 'audit', 404];
            $cases["$site enforce"] = [$site, 'enforce', 403];
        }
        $cases['order audit'] = ['order', 'audit', 403];
        $cases['order enforce'] = ['order', 'enforce', 403];

        return $cases;
    }

    #[DataProvider('belowLevelProbes')]
    public function test_a_below_level_actor_probing_another_orgs_record_leaves_one_no_grant_row(string $site, string $mode, int $status): void
    {
        $this->setMode($mode);

        $r = match ($site) {
            'role' => $this->asUser($this->viewer, 'DELETE', 'org-admin/roles/'.DB::table('user_org_roles')->where('user_id', $this->outsider->id)->value('id')),
            'connection' => $this->asUser($this->viewer, 'DELETE', 'org-admin/connections/'.OrgRelationship::create(['from_org_id' => $this->orgB->id, 'to_org_id' => $this->orgA->id, 'relationship_type' => 'buyer_seller', 'is_active' => true])->id),
            'member' => $this->asUser($this->viewer, 'DELETE', 'org-admin/projects/members/'.DB::table('project_members')->where('project_id', $this->p3)->where('user_id', $this->outsider->id)->value('id')),
            'rfq' => $this->asUser($this->viewer, 'POST', 'rfq/'.$this->rfq($this->orgB->id, $this->p3, $this->outsider->id).'/convert'),
            'order' => $this->asUser($this->viewer, 'POST', 'orders/'.$this->order($this->orgB->id, $this->outsider->id).'/approve', null, ['note' => 'x']),
        };

        $r->assertStatus($status);
        $this->assertSame(1, AuditLog::count(), 'exactly one row');
        $row = AuditLog::firstOrFail();
        $this->assertSame(['blocked', 'no_grant', $this->viewer->id, $this->orgA->id], [$row->outcome, $row->reason, (int) $row->user_id, (int) $row->org_id]);
        $this->assertSame(0, $this->controllerDenials());
    }

    #[DataProvider('modes')]
    public function test_a_hidden_project_404_in_the_buyer_rfq_check_writes_no_row(string $mode): void
    {
        $this->setMode($mode);
        $hidden = $this->rfq($this->orgA->id, $this->p2, $this->owner->id);

        $this->asUser($this->est, 'GET', "rfq/$hidden")->assertNotFound();

        $this->assertSame(0, AuditLog::count());
    }
}

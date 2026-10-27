<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\OrgRelationship;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * QA, PLAN 10.13 edges that CrossOrgNotFoundTest (JSON only, healthy audit table) does not pin: the audit row of the
 * five controller 404s for browser requests, the 404 staying a 404 when the audit table is broken, the validated acting
 * org on the row, and same-org 404s that must not leave a controller_denied row.
 */
class CrossOrgDenialRowEdgesTest extends ProjectTestCase
{
    private User $ownerA;
    private User $approverA;
    private User $ownerB;

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

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->approverA = $this->mkUser($this->orgA, 'executive_approver');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');

        config(['rbac.universal_admin_user_ids' => [], 'app.debug' => false]);
        $this->setMode('enforce');
    }

    public static function siteAndMode(): array
    {
        $cases = [];
        foreach (['role', 'connection', 'member', 'rfq', 'order'] as $site) {
            foreach (['audit', 'enforce'] as $mode) {
                $cases["$site $mode"] = [$site, $mode];
            }
        }

        return $cases;
    }

    /** @return array{0: User, 1: string, 2: string, 3: array, 4: string, 5: string} actor, method, foreign uri, data, missing uri, route template */
    private function target(string $site): array
    {
        return match ($site) {
            'role' => [
                $this->ownerA, 'DELETE', 'org-admin/roles/'.DB::table('user_org_roles')->where('user_id', $this->outsider->id)->value('id'), [],
                'org-admin/roles/999999', 'DELETE org-admin/roles/{userOrgRole}',
            ],
            'connection' => [
                $this->ownerA, 'DELETE', 'org-admin/connections/'.OrgRelationship::create(['from_org_id' => $this->orgB->id, 'to_org_id' => $this->orgA->id, 'relationship_type' => 'buyer_seller', 'is_active' => true])->id, [],
                'org-admin/connections/999999', 'DELETE org-admin/connections/{orgRelationship}',
            ],
            'member' => [
                $this->est, 'DELETE', 'org-admin/projects/members/'.DB::table('project_members')->where('project_id', $this->p3)->where('user_id', $this->outsider->id)->value('id'), [],
                'org-admin/projects/members/999999', 'DELETE org-admin/projects/members/{projectMember}',
            ],
            'rfq' => [
                $this->ownerA, 'POST', 'rfq/'.$this->foreignRfq().'/convert', [],
                'rfq/999999/convert', 'POST rfq/{rfq}/convert',
            ],
            'order' => [
                $this->approverA, 'POST', 'orders/'.$this->order($this->orgB->id, $this->outsider->id).'/approve', ['note' => 'x'],
                'orders/999999/approve', 'POST orders/{order}/approve',
            ],
        };
    }

    private function foreignRfq(): int
    {
        return (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $this->orgB->id, 'project_id' => $this->p3, 'created_by' => $this->outsider->id, 'title' => 'RFQ', 'status' => 'closed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function order(int $orgId, int $userId): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => $userId, 'org_id' => $orgId, 'order_number' => 'ORD-'.uniqid(), 'name' => 'Order', 'status' => 'pending_approval',
            'total' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function asJson(User $u, string $method, string $uri, array $data = [], ?int $orgId = null)
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->json($method, $uri, $data);
    }

    private function browser(User $u, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->from('/previous')
            ->call($method, $uri, $data);
    }

    private function denials(): \Illuminate\Support\Collection
    {
        return AuditLog::where('reason', 'controller_denied')->orderBy('id')->get();
    }

    // ---- browser requests -------------------------------------------------------------------------------------------

    #[DataProvider('siteAndMode')]
    public function test_a_browser_request_at_the_site_is_a_plain_404_with_one_denial_row_and_the_missing_id_page(string $site, string $mode): void
    {
        $this->setMode($mode);
        [$actor, $method, $uri, $data, $missingUri, $pattern] = $this->target($site);

        $foreign = $this->browser($actor, $method, $uri, $data);
        $foreign->assertNotFound()->assertSessionMissing('rbac_denied')->assertSessionHasNoErrors();

        $rows = $this->denials();
        $this->assertCount(1, $rows);
        $this->assertSame(1, AuditLog::count());
        $this->assertSame(
            ['blocked', 'controller_denied', $actor->id, $this->orgA->id, $pattern],
            [$rows[0]->outcome, $rows[0]->reason, (int) $rows[0]->user_id, (int) $rows[0]->org_id, $rows[0]->matched_pattern]
        );

        $missing = $this->browser($actor, $method, $missingUri, $data);
        $missing->assertNotFound();
        $this->assertSame($missing->getContent(), $foreign->getContent(), 'the 404 page differs between a missing and a foreign id');
        $this->assertSame(1, AuditLog::count(), 'a missing id writes nothing');
    }

    // ---- a broken audit table ---------------------------------------------------------------------------------------

    #[DataProvider('siteAndMode')]
    public function test_a_broken_audit_table_never_turns_the_404_into_an_error(string $site, string $mode): void
    {
        $this->setMode($mode);
        [$actor, $method, $uri, $data] = $this->target($site);
        Schema::drop('rbac_audit_logs');
        Log::spy();

        $json = $this->asJson($actor, $method, $uri, $data)->assertNotFound();
        $this->assertSame(['message' => 'Not found.'], $json->json());
        $this->browser($actor, $method, $uri, $data)->assertNotFound();

        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'denial recorder failed')->atLeast()->once();
    }

    // ---- the acting org on the row ----------------------------------------------------------------------------------

    public function test_the_row_carries_the_validated_acting_org_not_a_stale_session_value(): void
    {
        $foreign = (int) DB::table('user_org_roles')->where('user_id', $this->outsider->id)->value('id');

        $this->asJson($this->ownerA, 'DELETE', "org-admin/roles/$foreign", [], $this->orgB->id)->assertNotFound();

        $rows = $this->denials();
        $this->assertCount(1, $rows);
        $this->assertSame($this->orgA->id, (int) $rows[0]->org_id, 'ownerA holds no role in org B, so the acting org is org A');
        $this->assertSame(0, AuditLog::where('org_id', $this->orgB->id)->count());
    }

    // ---- same-org 404s are not cross-org probes ---------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_member_row_of_an_own_org_trashed_project_is_a_404_without_a_denial_row(string $mode): void
    {
        $this->setMode($mode);
        $trashed = $this->mkProject($this->orgA, $this->est, 'Trashed', now()->toDateTimeString());
        $row = $this->member($trashed, $this->viewer, $this->orgA);

        $this->asJson($this->est, 'DELETE', "org-admin/projects/members/$row")->assertNotFound();

        $this->assertSame(0, AuditLog::count());
        $this->assertTrue((bool) DB::table('project_members')->where('id', $row)->value('is_active'));
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }
}

<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\OrgRelationship;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, D21: the partner picker that listed every organization is gone. A connection is created from the
 * exact name of the partner; zero, several or self all answer the same generic message, and the POST is throttled.
 */
class ConnectionsLookupTest extends ProjectTestCase
{
    private const NO_MATCH = 'No organization matches that name. Check the exact spelling with your partner.';

    private User $ownerA;
    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->mkOrg('Org Beta');
        $this->mkOrg('Dup Co');
        $this->mkOrg('Dup Co');
        $this->mkOrg('Secret Rival Inc');

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function store(User $u, array $data, ?int $orgId = null)
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->from('org-admin/connections')
            ->post('org-admin/connections', $data + ['relationship_type' => 'buyer_seller']);
    }

    private function connections(?User $u = null, ?int $orgId = null)
    {
        return $this->actingAs($u ?? $this->ownerA)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->get('org-admin/connections');
    }

    public static function rejectedNames(): array
    {
        return [
            'prefix of a real name' => ['Org'],
            'prefix that also matches a real exact name' => ['Org Bet'],
            'substring' => ['rg B'],
            'lower-case variant part' => ['secret rival'],
            'own organization' => ['Org A'],
            'two organizations with that name' => ['Dup Co'],
            'no such organization' => ['Nowhere Ltd'],
            'wildcard characters' => ['%'],
            'wildcard underscore' => ['Org_B'],
        ];
    }

    // ---- lookup -------------------------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_exact_name_creates_the_connection_even_when_another_name_starts_with_it(string $mode): void
    {
        $this->setMode($mode);

        $this->store($this->ownerA, ['partner_name' => 'Org B', 'priority' => 'High'])
            ->assertRedirect('org-admin/connections')->assertSessionHas('success', 'Connection created.')->assertSessionMissing('error');

        $rel = OrgRelationship::firstOrFail();
        $this->assertSame([$this->orgA->id, $this->orgB->id, 'buyer_seller', 'High', true], [
            (int) $rel->from_org_id, (int) $rel->to_org_id, $rel->relationship_type, $rel->priority, (bool) $rel->is_active,
        ]);
        $this->assertSame(1, OrgRelationship::count());
    }

    #[DataProvider('rejectedNames')]
    public function test_every_other_name_gets_the_same_generic_answer_and_creates_nothing(string $name): void
    {
        $r = $this->store($this->ownerA, ['partner_name' => $name]);

        $r->assertStatus(302)->assertRedirect('org-admin/connections')->assertSessionHas('error', self::NO_MATCH)->assertSessionMissing('success');
        $r->assertSessionHasNoErrors();
        $this->assertSame(0, OrgRelationship::count());
    }

    public function test_the_answers_are_indistinguishable_between_the_rejected_cases(): void
    {
        $seen = [];
        foreach (self::rejectedNames() as $label => [$name]) {
            $r = $this->store($this->ownerA, ['partner_name' => $name]);
            $seen[$label] = [$r->getStatusCode(), $r->headers->get('Location'), $r->getSession()->get('error'), array_keys($r->getSession()->get('errors')?->getBag('default')->toArray() ?? [])];
        }

        $this->assertCount(1, array_unique(array_map('json_encode', $seen)), 'rejected cases differ: '.json_encode($seen));
    }

    public function test_the_message_sits_only_in_the_flash_and_the_typed_name_is_kept(): void
    {
        $page = $this->actingAs($this->ownerA)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->followingRedirects()->from('org-admin/connections')
            ->post('org-admin/connections', ['partner_name' => 'Org Be', 'relationship_type' => 'buyer_seller'])
            ->assertOk();

        $this->assertSame(1, substr_count((string) $page->getContent(), self::NO_MATCH), 'the message must show once');
        $page->assertSee('value="Org Be"', false);
    }

    #[DataProvider('modes')]
    public function test_validation_errors_belong_to_the_partner_name_bag(string $mode): void
    {
        $this->setMode($mode);

        $this->store($this->ownerA, ['partner_name' => ''])->assertSessionHasErrors('partner_name')->assertSessionMissing('error');
        $this->store($this->ownerA, ['partner_name' => str_repeat('x', 256)])->assertSessionHasErrors('partner_name')->assertSessionMissing('error');
        $this->store($this->ownerA, ['partner_name' => 'Org B', 'relationship_type' => 'nonsense'])->assertSessionHasErrors('relationship_type');
        $this->assertSame(0, OrgRelationship::count());
    }

    public function test_the_old_to_org_id_field_creates_nothing(): void
    {
        $this->store($this->ownerA, ['to_org_id' => $this->orgB->id])->assertSessionHasErrors('partner_name');

        $this->assertSame(0, OrgRelationship::count());
    }

    #[DataProvider('modes')]
    public function test_an_existing_active_connection_keeps_its_own_message(string $mode): void
    {
        $this->setMode($mode);
        OrgRelationship::create(['from_org_id' => $this->orgA->id, 'to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller', 'is_active' => true]);

        $this->store($this->ownerA, ['partner_name' => 'Org B'])->assertSessionHas('error', 'This relationship already exists and is active.');

        $this->assertSame(1, OrgRelationship::count());
    }

    // ---- permission -------------------------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_below_full_organization_management_gets_403_before_any_lookup(string $mode): void
    {
        $this->setMode($mode);
        $proc = $this->mkUser($this->orgA, 'procurement_manager');

        foreach ([$proc, $this->viewer, $this->est] as $u) {
            foreach (['Org B', 'Nowhere Ltd', 'Org A', 'Dup Co'] as $name) {
                $this->actingAs($u)
                    ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
                    ->postJson('org-admin/connections', ['partner_name' => $name, 'relationship_type' => 'buyer_seller'])
                    ->assertForbidden();
            }
        }

        $this->assertSame(0, OrgRelationship::count());
    }

    #[DataProvider('modes')]
    public function test_a_session_naming_an_org_the_user_does_not_belong_to_never_creates_a_connection_for_it(string $mode): void
    {
        $this->setMode($mode);

        $this->store($this->ownerB, ['partner_name' => 'Org A'], $this->orgA->id)->assertSessionHas('success', 'Connection created.');

        $rel = OrgRelationship::firstOrFail();
        $this->assertSame([$this->orgB->id, $this->orgA->id], [(int) $rel->from_org_id, (int) $rel->to_org_id]);
        $this->assertSame(0, OrgRelationship::where('from_org_id', $this->orgA->id)->count());
    }

    // ---- the page ---------------------------------------------------------------------------------------------------

    public function test_the_page_lists_no_organization_that_is_not_a_partner(): void
    {
        $page = $this->connections()->assertOk()->assertViewMissing('allOrgs');

        foreach (['Org B', 'Org Beta', 'Dup Co', 'Secret Rival Inc'] as $name) {
            $page->assertDontSee($name);
        }
        $page->assertSee('name="partner_name"', false)->assertDontSee('name="to_org_id"', false);
    }

    public function test_a_connected_partner_still_shows_under_its_list(): void
    {
        OrgRelationship::create(['from_org_id' => $this->orgA->id, 'to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller', 'is_active' => true]);

        $this->connections()->assertOk()->assertSee('Org B')->assertDontSee('Secret Rival Inc');
        $this->connections($this->ownerB, $this->orgB->id)->assertOk()->assertSee('Org A');
    }

    // ---- throttle ---------------------------------------------------------------------------------------------------

    public function test_the_twenty_first_post_in_a_minute_is_throttled(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->store($this->ownerA, ['partner_name' => 'Nowhere Ltd'])->assertStatus(302);
        }

        $this->store($this->ownerA, ['partner_name' => 'Org B'])->assertStatus(429);
        $this->assertSame(0, OrgRelationship::count());
    }

    public function test_the_throttle_is_per_user_and_leaves_the_other_connection_routes_alone(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            $this->store($this->ownerA, ['partner_name' => 'Nowhere Ltd']);
        }

        $this->store($this->ownerB, ['partner_name' => 'Org A'], $this->orgB->id)->assertSessionHas('success', 'Connection created.');

        $middleware = fn (string $name) => Route::getRoutes()->getByName($name)->gatherMiddleware();
        $this->assertContains('throttle:20,1', $middleware('org-admin.connections.store'));
        $this->assertNotContains('throttle:20,1', $middleware('org-admin.connections.index'));
        $this->assertNotContains('throttle:20,1', $middleware('org-admin.connections.destroy'));
    }

    public function test_a_refused_lookup_leaves_no_audit_row_in_audit_mode(): void
    {
        $this->store($this->ownerA, ['partner_name' => 'Nowhere Ltd']);

        $this->assertSame(0, AuditLog::count());
    }
}

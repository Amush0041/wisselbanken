<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Customers belong to an organization (customers.org_id): everyone in the org with the map's level shares them,
 * no other org ever sees or touches them, legacy NULL-org rows fail closed.
 * Fixture (ProjectTestCase): est/owner/stranger/viewer/super/multi in org A (multi also in B), outsider in org B.
 */
class OrgCustomersTest extends ProjectTestCase
{
    private User $noOrg;
    private User $ua;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('customers', function (Blueprint $t) {
            foreach (['first_name', 'last_name', 'email', 'phone', 'billing_street_1', 'billing_street_2', 'billing_city', 'billing_state', 'billing_zip', 'billing_country',
                'shipping_street_1', 'shipping_street_2', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country'] as $col) {
                $t->string($col)->nullable();
            }
        });

        Schema::create('customer_activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });

        $this->noOrg = User::factory()->create(['role' => 'user']);
        $this->ua = User::factory()->create(['role' => 'user']);
        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function hit(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        $c = $this->actingAs($u);
        if ($org !== null) {
            $c = $c->withSession([config('rbac.current_org_session_key') => $org]);
        }

        return $c->json($method, $uri, $data);
    }

    private function cust(?int $orgId, int $creatorId, string $name, bool $active = true, ?string $deletedAt = null): int
    {
        return (int) DB::table('customers')->insertGetId([
            'user_id' => $creatorId, 'org_id' => $orgId, 'company_name' => $name, 'is_active' => $active,
            'deleted_at' => $deletedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(int $id): ?object
    {
        return DB::table('customers')->where('id', $id)->first();
    }

    private function names($res, string $key = 'customers'): array
    {
        $n = collect($res->json($key))->map(fn ($c) => $c['company_name'] ?? $c['name'] ?? null)->all();
        sort($n);

        return $n;
    }

    // ---- teammate sharing (allowed path) --------------------------------------------

    #[DataProvider('modes')]
    public function test_a_teammate_sees_the_creators_customer_in_list_show_and_the_estimate_picker(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');
        $this->cust($this->orgA->id, $this->owner->id, 'InactiveCo', false);
        $this->cust($this->orgA->id, $this->owner->id, 'TrashedCo', true, now()->toDateTimeString());
        $this->cust($this->orgB->id, $this->outsider->id, 'OrgBCo');

        $list = $this->hit($this->stranger, 'GET', 'customers/list')->assertOk();
        $this->assertContains('SharedCo', $this->names($list));
        $this->assertNotContains('OrgBCo', $this->names($list));
        $this->assertNotContains('TrashedCo', $this->names($list));

        $this->hit($this->stranger, 'GET', "customers/$c")->assertOk()->assertJsonPath('customer.company_name', 'SharedCo');

        $pick = $this->hit($this->stranger, 'GET', 'quotes/customers')->assertOk();
        $json = json_encode($pick->json());
        $this->assertStringContainsString('SharedCo', $json);
        $this->assertStringNotContainsString('OrgBCo', $json);
        $this->assertStringNotContainsString('InactiveCo', $json);
        $this->assertStringNotContainsString('TrashedCo', $json);
    }

    #[DataProvider('modes')]
    public function test_a_teammate_can_attach_the_customer_on_create_and_on_save_editor(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');

        $id = $this->hit($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c])->assertOk()->json('quote_id');
        $this->assertSame($c, (int) DB::table('quotes')->where('id', $id)->value('customer_id'));

        $other = $this->cust($this->orgA->id, $this->est->id, 'EstCo');
        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $other])->assertOk();
        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $c])->assertOk();
        $this->assertSame($c, (int) DB::table('quotes')->where('id', $this->q1)->value('customer_id'));
    }

    #[DataProvider('modes')]
    public function test_the_project_workspace_dropdown_lists_the_orgs_customers_only(string $mode): void
    {
        $this->setMode($mode);
        $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');
        $this->cust($this->orgB->id, $this->outsider->id, 'OrgBCo');
        $this->cust(null, $this->est->id, 'LegacyCo');

        $page = $this->actingAs($this->est)->get("projects/{$this->p1}")->assertOk();
        $this->assertSame(['SharedCo'], $page->viewData('customers')->pluck('name')->all());
        $page->assertDontSee('OrgBCo');
        $page->assertDontSee('LegacyCo');
    }

    #[DataProvider('modes')]
    public function test_a_teammate_can_edit_and_delete_a_shared_customer_with_the_level(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');

        $this->hit($this->stranger, 'PUT', "customers/$c", ['company_name' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $this->row($c)->company_name);
        $this->assertSame($this->owner->id, (int) $this->row($c)->user_id, 'creator stays');

        $this->hit($this->stranger, 'DELETE', "customers/$c")->assertOk();
        $this->assertNotNull($this->row($c)->deleted_at);
    }

    // ---- other org: hidden everywhere (IDOR) ----------------------------------------

    #[DataProvider('modes')]
    public function test_another_orgs_user_cannot_see_attach_edit_or_delete_by_id(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');
        $before = (array) $this->row($c);
        $quotes = DB::table('quotes')->count();

        $this->assertNotContains('SharedCo', $this->names($this->hit($this->outsider, 'GET', 'customers/list')->assertOk()));
        $this->assertStringNotContainsString('SharedCo', json_encode($this->hit($this->outsider, 'GET', 'quotes/customers')->assertOk()->json()));
        $this->hit($this->outsider, 'GET', "customers/$c")->assertNotFound();
        $this->hit($this->outsider, 'PUT', "customers/$c", ['company_name' => 'Hacked'])->assertNotFound();
        $this->hit($this->outsider, 'DELETE', "customers/$c")->assertNotFound();
        $this->hit($this->outsider, 'POST', "projects/{$this->p3}/quotes", ['customer_id' => $c])->assertNotFound();
        $this->hit($this->outsider, 'PUT', "quotes/{$this->q4}/editor", ['customer_id' => $c])->assertNotFound();

        $this->assertSame($before, (array) $this->row($c), 'customer untouched');
        $this->assertSame($quotes, DB::table('quotes')->count());
        $this->assertNull(DB::table('quotes')->where('id', $this->q4)->value('customer_id'));
        $this->assertSame(0, DB::table('customer_activity_logs')->count(), 'no activity logged');
    }

    #[DataProvider('modes')]
    public function test_an_org_a_member_cannot_attach_an_org_b_customer_to_their_own_estimate(string $mode): void
    {
        $this->setMode($mode);
        $b = $this->cust($this->orgB->id, $this->outsider->id, 'OrgBCo');

        $this->hit($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $b])->assertNotFound();
        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $b])->assertNotFound();
        $this->hit($this->est, 'GET', "customers/$b")->assertNotFound();
        $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('customer_id'));
    }

    // ---- store: org source ------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_store_takes_org_from_the_current_org_keeps_the_creator_and_ignores_injected_org(string $mode): void
    {
        $this->setMode($mode);

        $id = $this->hit($this->est, 'POST', 'customers', ['company_name' => 'NewCo', 'org_id' => $this->orgB->id, 'user_id' => $this->outsider->id])
            ->assertOk()->json('customer_id');

        $this->assertSame($this->orgA->id, (int) $this->row($id)->org_id);
        $this->assertSame($this->est->id, (int) $this->row($id)->user_id);

        $this->hit($this->stranger, 'GET', "customers/$id")->assertOk();
        $this->hit($this->outsider, 'GET', "customers/$id")->assertNotFound();
    }

    #[DataProvider('modes')]
    public function test_store_for_a_multi_org_user_uses_the_switched_org(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->hit($this->multi, 'POST', 'customers', ['company_name' => 'InB'], $this->orgB->id)->assertOk()->json('customer_id');
        $this->assertSame($this->orgB->id, (int) $this->row($id)->org_id);
    }

    #[DataProvider('modes')]
    public function test_update_cannot_move_a_customer_to_another_org_via_input(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');

        $this->hit($this->est, 'PUT', "customers/$c", ['company_name' => 'Same', 'org_id' => $this->orgB->id])->assertOk();
        $this->assertSame($this->orgA->id, (int) $this->row($c)->org_id);
    }

    // ---- map levels unchanged ---------------------------------------------------------

    public static function levelCases(): array
    {
        // role => [list(R quote_rfq), create(S user_mgmt), update(O), delete(F)]
        $cases = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach ([
                'viewer_read_only' => [true, false, false, false],
                'superintendent' => [false, false, false, false],
                'project_manager' => [true, true, false, false],
                'financial_admin' => [true, true, true, false],
                'estimator' => [true, true, true, true],
            ] as $role => $exp) {
                $cases["$mode $role"] = [$mode, $role, $exp];
            }
        }

        return $cases;
    }

    #[DataProvider('levelCases')]
    public function test_route_levels_are_unchanged_viewer_denied_writes_and_roles_with_the_level_allowed(string $mode, string $role, array $exp): void
    {
        $this->setMode($mode);
        $u = $this->mkUser($this->orgA, $role);
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');

        $status = fn (bool $ok) => $ok ? 200 : 403;
        $this->hit($u, 'GET', 'customers/list')->assertStatus($status($exp[0]));
        $this->hit($u, 'GET', "customers/$c")->assertStatus($status($exp[0]));
        $this->hit($u, 'POST', 'customers', ['company_name' => 'N'])->assertStatus($status($exp[1]));
        $this->hit($u, 'PUT', "customers/$c", ['company_name' => 'U'])->assertStatus($status($exp[2]));
        $this->hit($u, 'DELETE', "customers/$c")->assertStatus($status($exp[3]));

        $this->assertSame($exp[2] ? 'U' : 'SharedCo', $this->row($c)->company_name);
        $this->assertSame($exp[3], $this->row($c)->deleted_at !== null);
    }

    #[DataProvider('modes')]
    public function test_a_user_without_an_org_is_denied_everything(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');

        $this->hit($this->noOrg, 'GET', 'customers/list')->assertForbidden();
        $this->hit($this->noOrg, 'GET', "customers/$c")->assertForbidden();
        $this->hit($this->noOrg, 'POST', 'customers', ['company_name' => 'N'])->assertForbidden();
        $this->hit($this->noOrg, 'PUT', "customers/$c", ['company_name' => 'U'])->assertForbidden();
        $this->hit($this->noOrg, 'DELETE', "customers/$c")->assertForbidden();
        $this->assertSame(1, DB::table('customers')->count());
    }

    #[DataProvider('modes')]
    public function test_a_viewer_in_the_wrong_org_for_the_customer_is_still_a_404_not_a_leak(string $mode): void
    {
        $this->setMode($mode);
        $viewerB = $this->mkUser($this->orgB, 'viewer_read_only');
        $c = $this->cust($this->orgA->id, $this->owner->id, 'SharedCo');

        $this->hit($viewerB, 'GET', "customers/$c")->assertNotFound();
        $this->assertNotContains('SharedCo', $this->names($this->hit($viewerB, 'GET', 'customers/list')->assertOk()));
    }

    // ---- legacy NULL-org rows ----------------------------------------------------------

    #[DataProvider('modes')]
    public function test_null_org_customers_are_invisible_and_not_attachable_to_anyone(string $mode): void
    {
        $this->setMode($mode);
        $n = $this->cust(null, $this->est->id, 'LegacyCo');

        foreach ([$this->est, $this->owner, $this->outsider] as $u) {
            $this->assertNotContains('LegacyCo', $this->names($this->hit($u, 'GET', 'customers/list')->assertOk()));
            $this->assertStringNotContainsString('LegacyCo', json_encode($this->hit($u, 'GET', 'quotes/customers')->assertOk()->json()));
        }
        $this->hit($this->est, 'GET', "customers/$n")->assertNotFound();
        $this->hit($this->est, 'PUT', "customers/$n", ['company_name' => 'X'])->assertNotFound();
        $this->hit($this->est, 'DELETE', "customers/$n")->assertNotFound();
        $this->hit($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $n])->assertNotFound();
        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $n])->assertNotFound();
        $this->assertNull($this->row($n)->org_id);
        $this->assertSame('LegacyCo', $this->row($n)->company_name);
        $this->assertNull(DB::table('quotes')->where('id', $this->q1)->value('customer_id'));
    }

    #[DataProvider('modes')]
    public function test_an_existing_quote_with_a_null_org_customer_can_be_resaved_unchanged_but_not_repointed(string $mode): void
    {
        $this->setMode($mode);
        $legacy = $this->cust(null, $this->owner->id, 'LegacyCo');
        $legacy2 = $this->cust(null, $this->owner->id, 'LegacyTwo');
        $foreign = $this->cust($this->orgB->id, $this->outsider->id, 'OrgBCo');
        DB::table('quotes')->where('id', $this->q1)->update(['customer_id' => $legacy]);

        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $legacy, 'notes' => 'edited'])->assertOk();
        $this->assertSame($legacy, (int) DB::table('quotes')->where('id', $this->q1)->value('customer_id'));
        $this->assertSame('edited', DB::table('quotes')->where('id', $this->q1)->value('notes'));

        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $legacy2])->assertNotFound();
        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $foreign])->assertNotFound();
        $this->assertSame($legacy, (int) DB::table('quotes')->where('id', $this->q1)->value('customer_id'));

        $mine = $this->cust($this->orgA->id, $this->est->id, 'EstCo');
        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $mine])->assertOk();
        $this->assertSame($mine, (int) DB::table('quotes')->where('id', $this->q1)->value('customer_id'));
        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $legacy])->assertNotFound();
    }

    // ---- multi-org and universal admin --------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_multi_org_user_sees_only_the_active_orgs_customers_after_switching(string $mode): void
    {
        $this->setMode($mode);
        $this->cust($this->orgA->id, $this->multi->id, 'InA');
        $b = $this->cust($this->orgB->id, $this->multi->id, 'InB');

        $this->assertSame(['InA'], $this->names($this->hit($this->multi, 'GET', 'customers/list', [], $this->orgA->id)));
        $this->assertSame(['InB'], $this->names($this->hit($this->multi, 'GET', 'customers/list', [], $this->orgB->id)));
        $this->assertStringNotContainsString('InA', json_encode($this->hit($this->multi, 'GET', 'quotes/customers', [], $this->orgB->id)->json()));
        $this->assertStringContainsString('InB', json_encode($this->hit($this->multi, 'GET', 'quotes/customers', [], $this->orgB->id)->json()));
        $this->hit($this->multi, 'GET', "customers/$b", [], $this->orgA->id)->assertNotFound();
        $this->hit($this->multi, 'GET', "customers/$b", [], $this->orgB->id)->assertOk();
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_sees_only_the_switched_into_orgs_customers(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);
        $this->cust($this->orgA->id, $this->owner->id, 'InA');
        $this->cust($this->orgB->id, $this->outsider->id, 'InB');
        $this->cust($this->orgA->id, $this->ua->id, 'UaOwnInA');
        $a = (int) DB::table('customers')->where('company_name', 'InA')->value('id');
        $b = (int) DB::table('customers')->where('company_name', 'InB')->value('id');

        $this->assertSame(['InA', 'UaOwnInA'], $this->names($this->hit($this->ua, 'GET', 'customers/list', [], $this->orgA->id)->assertOk()));
        $this->assertSame(['InB'], $this->names($this->hit($this->ua, 'GET', 'customers/list', [], $this->orgB->id)->assertOk()));
        $this->hit($this->ua, 'GET', "customers/$a", [], $this->orgB->id)->assertNotFound();
        $this->hit($this->ua, 'GET', "customers/$b", [], $this->orgB->id)->assertOk();
        $pick = json_encode($this->hit($this->ua, 'GET', 'quotes/customers', [], $this->orgB->id)->assertOk()->json());
        $this->assertStringContainsString('InB', $pick);
        $this->assertStringNotContainsString('InA', $pick);
    }

    // ---- org deletion / unrelated behaviour ----------------------------------------------

    public function test_other_customer_behaviour_is_unchanged_validation_soft_delete_and_activity_log(): void
    {
        $this->hit($this->est, 'POST', 'customers', ['email' => 'not-an-email'])->assertStatus(422);
        $this->assertSame(0, DB::table('customers')->count());

        $id = $this->hit($this->est, 'POST', 'customers', ['company_name' => 'LogCo'])->assertOk()->json('customer_id');
        $this->hit($this->stranger, 'PUT', "customers/$id", ['company_name' => 'LogCo2'])->assertOk();
        $this->hit($this->stranger, 'DELETE', "customers/$id")->assertOk();

        $this->assertSame(['created', 'updated', 'deleted'], DB::table('customer_activity_logs')->where('customer_id', $id)->orderBy('id')->pluck('action')->all());
        $this->assertSame([$this->est->id, $this->stranger->id, $this->stranger->id], DB::table('customer_activity_logs')->orderBy('id')->pluck('user_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(1, DB::table('customers')->count(), 'soft delete keeps the row');
    }

    public function test_a_quote_store_in_a_project_uses_the_projects_org_even_for_a_multi_org_user_with_another_org_active(): void
    {
        $inA = $this->cust($this->orgA->id, $this->multi->id, 'InA');
        $inB = $this->cust($this->orgB->id, $this->multi->id, 'InB');

        $this->hit($this->multi, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $inB], $this->orgA->id)->assertNotFound();
        $this->hit($this->multi, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $inA], $this->orgA->id)->assertOk();
    }

    #[DataProvider('modes')]
    public function test_save_editor_cannot_keep_a_foreign_orgs_customer_through_the_legacy_branch(string $mode): void
    {
        $this->setMode($mode);
        $foreign = $this->cust($this->orgB->id, $this->outsider->id, 'OrgBCo');
        DB::table('quotes')->where('id', $this->q1)->update(['customer_id' => $foreign]);

        $this->hit($this->est, 'PUT', "quotes/{$this->q1}/editor", ['customer_id' => $foreign])->assertNotFound();
        $this->assertSame($foreign, (int) DB::table('quotes')->where('id', $this->q1)->value('customer_id'));
    }

    #[DataProvider('modes')]
    public function test_the_estimate_picker_is_closed_to_users_without_an_org_including_a_universal_admin(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);
        $this->cust(null, $this->est->id, 'LegacyCo');

        $this->hit($this->noOrg, 'GET', 'quotes/customers')->assertForbidden();
        $this->hit($this->ua, 'GET', 'quotes/customers')->assertForbidden();
        $this->hit($this->ua, 'GET', 'customers/list')->assertForbidden();
    }
}

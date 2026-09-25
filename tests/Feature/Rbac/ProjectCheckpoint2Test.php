<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 4 Checkpoint 2 changes: owner-level check in QuoteController::writableQuote, RbacController::destroyUser
 * created_by refusal and project_members cleanup, the inline Add-estimate customers on the project page, and the
 * unconditional crosswalk `$projects` list.
 */
class ProjectCheckpoint2Test extends ProjectTestCase
{
    /** architect in A: estimate_management R only, member of P1 */
    private User $architect;
    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->architect = $this->mkUser($this->orgA, 'architect');
        $this->member($this->p1, $this->architect, $this->orgA);
        $this->platformAdmin = User::factory()->create(['role' => 'admin']);
        $this->setMode('audit');

        $svc = app(\App\Services\Rbac\PermissionService::class);
        $this->assertTrue($svc->checkPermission($this->architect->id, $this->orgA->id, 'estimate_management', 'R'));
        $this->assertFalse($svc->checkPermission($this->architect->id, $this->orgA->id, 'estimate_management', 'O'), 'architect must not reach O');
        $this->assertTrue($svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'F'), 'estimator must hold F for the allowed paths');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function req(User $u, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($u)->json($method, $uri, $data);
    }

    private function addItem(int $quoteId): int
    {
        return (int) DB::table('quote_items')->insertGetId([
            'quote_id' => $quoteId, 'quantity' => 1, 'unit_price' => 5, 'subtotal' => 5, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function customer(User $u, string $name, bool $active = true, ?string $deletedAt = null): int
    {
        return (int) DB::table('customers')->insertGetId([
            'user_id' => $u->id, 'company_name' => $name, 'is_active' => $active, 'deleted_at' => $deletedAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ---- 1. writableQuote owner level check ------------------------------------

    /** @return array<string, array{0:string,1:string,2:array}> */
    public static function writeRoutes(): array
    {
        return [
            'update' => ['PUT', 'quotes/{q}', ['notes' => 'x']],
            'editor' => ['PUT', 'quotes/{q}/editor', ['customer_id' => '{c}']],
            'update item' => ['PUT', 'quotes/{q}/items/{i}', ['quantity' => 3]],
            'duplicate' => ['POST', 'quotes/{q}/duplicate', []],
            'destroy item (F)' => ['DELETE', 'quotes/{q}/items/{i}', []],
            'destroy (F)' => ['DELETE', 'quotes/{q}', []],
        ];
    }

    private function fill(string $uri, array $data, int $q, int $i, int $c): array
    {
        $data = array_map(fn ($v) => $v === '{c}' ? $c : $v, $data);

        return [str_replace(['{q}', '{i}'], [$q, $i], $uri), $data];
    }

    #[DataProvider('writeRoutes')]
    public function test_an_r_only_owner_is_403_on_their_own_project_quote_in_audit_and_enforce(string $method, string $uri, array $data): void
    {
        $own = $this->mkQuote($this->architect, $this->p1);
        $item = $this->addItem($own);
        $c = $this->customer($this->architect, 'AC');

        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            [$u, $d] = $this->fill($uri, $data, $own, $item, $c);
            $this->req($this->architect, $method, $u, $d)->assertForbidden();
        }

        $this->assertTrue(DB::table('quotes')->where('id', $own)->whereNull('deleted_at')->exists(), 'quote survives');
        $this->assertSame(1, DB::table('quote_items')->where('quote_id', $own)->count());
        $this->assertSame(1, DB::table('quotes')->where('project_id', $this->p1)->where('user_id', $this->architect->id)->count(), 'no duplicate created');
        $this->assertNull(DB::table('quotes')->where('id', $own)->value('notes'));
    }

    #[DataProvider('writeRoutes')]
    public function test_an_r_only_owner_is_403_on_their_own_legacy_null_project_quote_in_audit_mode(string $method, string $uri, array $data): void
    {
        $own = $this->mkQuote($this->architect, null);
        $item = $this->addItem($own);
        $c = $this->customer($this->architect, 'AC');
        [$u, $d] = $this->fill($uri, $data, $own, $item, $c);

        $this->req($this->architect, $method, $u, $d)->assertForbidden();
        $this->assertTrue(DB::table('quotes')->where('id', $own)->whereNull('deleted_at')->exists());
    }

    public function test_an_owner_with_the_right_level_can_change_their_own_quote(): void
    {
        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $q = $this->mkQuote($this->est, $this->p1);
            $i = $this->addItem($q);
            $c = $this->customer($this->est, "C$mode");

            $this->req($this->est, 'PUT', "quotes/$q", ['notes' => "n-$mode"])->assertOk()->assertJson(['success' => true]);
            $this->assertSame("n-$mode", DB::table('quotes')->where('id', $q)->value('notes'));
            $this->req($this->est, 'PUT', "quotes/$q/items/$i", ['quantity' => 4])->assertOk();
            $this->assertSame(4, (int) DB::table('quote_items')->where('id', $i)->value('quantity'));
            $i2 = $this->addItem($q);
            $this->req($this->est, 'DELETE', "quotes/$q/items/$i2")->assertOk()->assertJson(['success' => true]);
            $this->assertSame(0, DB::table('quote_items')->where('id', $i2)->count());
            $this->req($this->est, 'PUT', "quotes/$q/editor", ['customer_id' => $c])->assertOk();
            $dup = $this->req($this->est, 'POST', "quotes/$q/duplicate")->assertOk()->json('quote_id');
            $this->assertSame($this->p1, (int) DB::table('quotes')->where('id', $dup)->value('project_id'));
            $this->req($this->est, 'DELETE', "quotes/$q")->assertOk();
            $this->assertNull(DB::table('quotes')->where('id', $q)->whereNull('deleted_at')->value('id'));
        }
    }

    public function test_destroy_item_for_an_r_only_owner_is_a_403_not_a_500(): void
    {
        $own = $this->mkQuote($this->architect, $this->p1);
        $item = $this->addItem($own);

        $r = $this->req($this->architect, 'DELETE', "quotes/$own/items/$item")->assertForbidden();
        $this->assertNotSame(500, $r->getStatusCode());
        $this->assertNotSame(false, $r->json('success') ?? null, 'the 500 error body must not be produced');
        $this->assertSame(1, DB::table('quote_items')->where('id', $item)->count());
    }

    #[DataProvider('modes')]
    public function test_an_invisible_quote_stays_404_even_for_a_role_holder(string $mode): void
    {
        $this->setMode($mode);
        // q3 is in P2 (est is not a member), q4 is in org B
        foreach ([$this->q3, $this->q4] as $q) {
            $this->req($this->est, 'PUT', "quotes/$q", ['notes' => 'x'])->assertStatus($mode === 'enforce' ? 403 : 404);
            $this->req($this->est, 'DELETE', "quotes/$q")->assertStatus($mode === 'enforce' ? 403 : 404);
        }
        $this->assertNull(DB::table('quotes')->where('id', $this->q3)->value('notes'));
        $this->assertTrue(DB::table('quotes')->where('id', $this->q3)->whereNull('deleted_at')->exists());
    }

    public function test_a_legacy_null_project_quote_gets_the_org_level_check_in_audit_and_fails_closed_in_enforce(): void
    {
        $own = $this->mkQuote($this->est, null);

        $this->setMode('audit');
        $this->req($this->est, 'PUT', "quotes/$own", ['notes' => 'audit'])->assertOk();
        $this->assertSame('audit', DB::table('quotes')->where('id', $own)->value('notes'));

        $this->setMode('enforce');
        $this->req($this->est, 'PUT', "quotes/$own", ['notes' => 'enforce'])->assertForbidden();
        $this->assertSame('audit', DB::table('quotes')->where('id', $own)->value('notes'), 'enforce writes nothing');
    }

    public function test_an_owner_without_any_org_role_is_403_on_their_own_quote(): void
    {
        $none = $this->mkUser();
        $own = $this->mkQuote($none, null);

        $this->req($none, 'PUT', "quotes/$own", ['notes' => 'x'])->assertForbidden();
        $this->req($none, 'DELETE', "quotes/$own")->assertForbidden();
        $this->assertNull(DB::table('quotes')->where('id', $own)->value('notes'));
    }

    // ---- 2. RbacController::destroyUser ----------------------------------------

    private function deleteUser(User $u)
    {
        return $this->actingAs($this->platformAdmin)->from('/admin/rbac/users')->delete(route('admin.rbac.users.destroy', $u));
    }

    #[DataProvider('modes')]
    public function test_delete_user_is_refused_for_a_project_creator(string $mode): void
    {
        $this->setMode($mode);
        // owner created P1 and P2 and is not the sole holder of org A
        $before = DB::table('project_members')->where('user_id', $this->owner->id)->count();
        $this->assertGreaterThan(0, $before);

        $this->deleteUser($this->owner)->assertRedirect('/admin/rbac/users')
            ->assertSessionHas('error', 'This user created projects and cannot be deleted. Reassign or delete those projects first.');

        $this->assertTrue(User::whereKey($this->owner->id)->exists());
        $this->assertSame($before, DB::table('project_members')->where('user_id', $this->owner->id)->count());
        $this->assertSame(1, DB::table('user_org_roles')->where('user_id', $this->owner->id)->count());
        $this->assertSame(2, DB::table('projects')->where('created_by', $this->owner->id)->where('org_id', $this->orgA->id)->count());
    }

    public function test_delete_user_is_refused_when_only_a_trashed_project_was_created_by_them(): void
    {
        $creator = $this->mkUser($this->orgA, 'estimator');
        $this->mkProject($this->orgA, $creator, 'gone', now()->toDateTimeString());
        $this->member($this->p1, $creator, $this->orgA);

        $this->deleteUser($creator)->assertSessionHas('error', 'This user created projects and cannot be deleted. Reassign or delete those projects first.');

        $this->assertTrue(User::whereKey($creator->id)->exists());
        $this->assertSame(1, DB::table('project_members')->where('user_id', $creator->id)->count());
    }

    #[DataProvider('modes')]
    public function test_delete_user_removes_a_plain_members_project_members_rows(string $mode): void
    {
        $this->setMode($mode);
        $this->member($this->p2, $this->est, $this->orgA, false);
        $this->legacyMember($this->q1, $this->est, $this->orgA);
        $this->assertSame(0, DB::table('projects')->where('created_by', $this->est->id)->count());
        $keep = DB::table('project_members')->where('user_id', $this->architect->id)->count();

        $this->deleteUser($this->est)->assertRedirect(route('admin.rbac.users'))->assertSessionHas('success');

        $this->assertFalse(User::whereKey($this->est->id)->exists());
        $this->assertSame(0, DB::table('project_members')->where('user_id', $this->est->id)->count(), 'active, inactive and legacy rows gone');
        $this->assertSame($keep, DB::table('project_members')->where('user_id', $this->architect->id)->count(), 'other users untouched');
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $this->est->id)->count());
        $this->assertSame(0, DB::table('project_members')->whereNotIn('user_id', DB::table('users')->pluck('id'))->count(), 'no dangling member rows');
        $this->assertSame(0, DB::table('projects')->whereNotIn('created_by', DB::table('users')->pluck('id'))->count(), 'no dangling created_by');
    }

    public function test_delete_user_removes_project_members_itself_and_does_not_rely_on_the_fk_cascade(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        $this->assertSame(0, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys, 'guard: cascades are off');
        $this->legacyMember($this->q1, $this->est, $this->orgA);
        $this->assertGreaterThan(1, DB::table('project_members')->where('user_id', $this->est->id)->count());

        $this->deleteUser($this->est)->assertSessionHas('success');

        $this->assertSame(0, DB::table('project_members')->where('user_id', $this->est->id)->count());
    }

    public function test_delete_user_rolls_project_members_back_if_the_deletion_fails(): void
    {
        $before = DB::table('project_members')->where('user_id', $this->est->id)->count();
        $this->assertGreaterThan(0, $before);
        User::deleting(function (User $u) {
            if ($u->id === $this->est->id) {
                throw new \RuntimeException('forced failure');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->deleteUser($this->est);
            $surfaced = false;
        } catch (\RuntimeException $e) {
            $surfaced = $e->getMessage() === 'forced failure';
        } finally {
            User::flushEventListeners();
        }

        $this->assertTrue($surfaced, 'the forced failure surfaced');
        $this->assertTrue(User::whereKey($this->est->id)->exists());
        $this->assertSame($before, DB::table('project_members')->where('user_id', $this->est->id)->count(), 'rows restored by the rollback');
        $this->assertSame(1, DB::table('user_org_roles')->where('user_id', $this->est->id)->count());
    }

    public function test_delete_user_sole_owner_of_an_org_with_projects_is_still_refused(): void
    {
        $org = $this->mkOrg('Solo');
        $solo = $this->mkUser($org, 'organization_owner');
        $other = $this->mkUser($this->orgA, 'estimator');
        $this->mkProject($org, $other, 'solo project');
        $this->member(DB::table('projects')->where('org_id', $org->id)->value('id'), $solo, $org);

        $this->deleteUser($solo)->assertSessionHas('error', 'This user solely owns an organization that still has projects and cannot be deleted.');

        $this->assertTrue(User::whereKey($solo->id)->exists());
        $this->assertSame(1, DB::table('project_members')->where('user_id', $solo->id)->count());
        $this->assertTrue(DB::table('organizations')->where('id', $org->id)->exists());
    }

    public function test_delete_user_is_closed_to_a_non_admin(): void
    {
        $this->actingAs($this->est)->deleteJson(route('admin.rbac.users.destroy', $this->architect))->assertForbidden();
        $this->assertTrue(User::whereKey($this->architect->id)->exists());
        $this->assertSame(1, DB::table('project_members')->where('user_id', $this->architect->id)->count());
    }

    // ---- 2b. destroyUser: owner of quotes inside projects -----------------------

    private const QUOTE_REFUSAL = 'This user owns quotes inside projects and cannot be deleted. Reassign or delete those quotes first.';

    /** @return array<string,int> */
    private function footprint(User $u): array
    {
        return [
            'users' => DB::table('users')->where('id', $u->id)->count(),
            'quotes_all' => DB::table('quotes')->where('user_id', $u->id)->count(),
            'members' => DB::table('project_members')->where('user_id', $u->id)->count(),
            'roles' => DB::table('user_org_roles')->where('user_id', $u->id)->count(),
            'orgs' => DB::table('organizations')->count(),
            'projects' => DB::table('projects')->count(),
        ];
    }

    #[DataProvider('modes')]
    public function test_delete_user_is_refused_for_an_owner_of_a_live_project_quote_and_nothing_is_deleted(string $mode): void
    {
        $this->setMode($mode);
        $this->mkQuote($this->est, $this->p1);
        $this->mkQuote($this->est, null);
        $before = $this->footprint($this->est);
        $this->assertSame(2, $before['quotes_all']);
        $this->assertGreaterThan(0, $before['members']);

        $this->deleteUser($this->est)->assertRedirect('/admin/rbac/users')->assertSessionHas('error', self::QUOTE_REFUSAL);

        $this->assertSame($before, $this->footprint($this->est));
        $this->assertSame(1, DB::table('quotes')->where('user_id', $this->est->id)->where('project_id', $this->p1)->count());
    }

    #[DataProvider('modes')]
    public function test_delete_user_is_refused_when_only_a_soft_deleted_project_quote_remains(string $mode): void
    {
        $this->setMode($mode);
        $gone = $this->mkQuote($this->est, $this->p1, ['deleted_at' => now()->toDateTimeString()]);
        $before = $this->footprint($this->est);

        $this->deleteUser($this->est)->assertRedirect('/admin/rbac/users')->assertSessionHas('error', self::QUOTE_REFUSAL);

        $this->assertSame($before, $this->footprint($this->est));
        $this->assertTrue(DB::table('quotes')->where('id', $gone)->whereNotNull('deleted_at')->exists(), 'trashed quote survives');
    }

    #[DataProvider('modes')]
    public function test_delete_user_is_allowed_when_all_their_quotes_have_a_null_project_id(string $mode): void
    {
        $this->setMode($mode);
        $this->mkQuote($this->est, null);
        $this->mkQuote($this->est, null, ['deleted_at' => now()->toDateTimeString()]);

        $this->deleteUser($this->est)->assertRedirect(route('admin.rbac.users'))->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertFalse(User::whereKey($this->est->id)->exists());
        $this->assertSame(0, DB::table('project_members')->where('user_id', $this->est->id)->count());
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $this->est->id)->count());
        $this->assertSame(2, DB::table('quotes')->where('user_id', $this->owner->id)->where('project_id', $this->p1)->count(), 'others project quotes untouched');
    }

    #[DataProvider('modes')]
    public function test_delete_user_is_allowed_when_they_only_have_project_members_rows(string $mode): void
    {
        $this->setMode($mode);
        $this->assertSame(0, DB::table('quotes')->where('user_id', $this->est->id)->count());
        $this->assertGreaterThan(0, DB::table('project_members')->where('user_id', $this->est->id)->count());

        $this->deleteUser($this->est)->assertRedirect(route('admin.rbac.users'))->assertSessionHas('success');

        $this->assertFalse(User::whereKey($this->est->id)->exists());
        $this->assertSame(0, DB::table('project_members')->where('user_id', $this->est->id)->count());
    }

    public function test_the_sole_org_refusal_comes_before_the_quote_refusal(): void
    {
        $org = $this->mkOrg('Solo');
        $solo = $this->mkUser($org, 'organization_owner');
        $pid = $this->mkProject($org, $this->est, 'solo project');
        $this->mkQuote($solo, $pid);

        $this->deleteUser($solo)->assertSessionHas('error', 'This user solely owns an organization that still has projects and cannot be deleted.');

        $this->assertTrue(User::whereKey($solo->id)->exists());
    }

    public function test_the_created_by_refusal_comes_before_the_quote_refusal(): void
    {
        $creator = $this->mkUser($this->orgA, 'estimator');
        $this->mkProject($this->orgA, $creator, 'theirs');
        $this->mkQuote($creator, $this->p1);

        $this->deleteUser($creator)->assertSessionHas('error', 'This user created projects and cannot be deleted. Reassign or delete those projects first.');

        $this->assertTrue(User::whereKey($creator->id)->exists());
    }

    public function test_the_quote_refusal_applies_when_the_project_belongs_to_another_org(): void
    {
        $this->mkQuote($this->multi, $this->p3);

        $this->deleteUser($this->multi)->assertSessionHas('error', self::QUOTE_REFUSAL);

        $this->assertTrue(User::whereKey($this->multi->id)->exists());
    }

    public function test_delete_user_with_a_project_quote_is_still_403_for_a_non_admin(): void
    {
        $this->mkQuote($this->architect, $this->p1);

        $this->actingAs($this->est)->deleteJson(route('admin.rbac.users.destroy', $this->architect))->assertForbidden();

        $this->assertTrue(User::whereKey($this->architect->id)->exists());
        $this->assertSame(1, DB::table('quotes')->where('user_id', $this->architect->id)->count());
    }

    // ---- 3. inline Add estimate --------------------------------------------------

    #[DataProvider('modes')]
    public function test_show_passes_only_the_users_own_active_customers_to_a_creator(string $mode): void
    {
        $this->setMode($mode);
        $mine = $this->customer($this->est, 'Mine');
        $mine2 = $this->customer($this->est, 'Alpha');
        $this->customer($this->est, 'Inactive', false);
        $this->customer($this->est, 'Trashed', true, now()->toDateTimeString());
        $this->customer($this->owner, 'Owners');
        $this->customer($this->architect, 'Architects');

        $page = $this->actingAs($this->est)->get("projects/{$this->p1}")->assertOk();

        $this->assertTrue($page->viewData('canCreateEstimates'));
        $this->assertSame(['Alpha', 'Mine'], $page->viewData('customers')->pluck('name')->all());
        $this->assertSame([$mine2, $mine], $page->viewData('customers')->pluck('id')->all());
        $page->assertSee('id="addEstimateModal"', false);
        $page->assertSee(route('projects.quotes.store', $this->p1), false);
        $page->assertDontSee('Owners');
        $page->assertDontSee('Architects');
        $page->assertDontSee('Inactive');
        $page->assertDontSee('Trashed');
    }

    #[DataProvider('modes')]
    public function test_show_gives_no_customers_and_no_modal_without_the_create_level(string $mode): void
    {
        $this->setMode($mode);
        $this->customer($this->architect, 'Architects');
        $this->customer($this->super, 'Supers');
        $this->customer($this->est, 'Ests');

        // architect: estimate_management R only; super: no estimate_management at all; viewer: read only
        foreach ([$this->architect, $this->super, $this->viewer] as $u) {
            $page = $this->actingAs($u)->get("projects/{$this->p1}")->assertOk();
            $this->assertFalse($page->viewData('canCreateEstimates'), "user {$u->id}");
            $this->assertTrue($page->viewData('customers')->isEmpty(), "user {$u->id}");
            $page->assertDontSee('id="addEstimateModal"', false);
            $page->assertDontSee('Architects');
            $page->assertDontSee('Supers');
            $page->assertDontSee('Ests');
        }
    }

    public function test_show_does_not_list_another_orgs_or_users_customers_for_a_multi_org_user(): void
    {
        $this->customer($this->outsider, 'OutsiderCo');
        $mine = $this->customer($this->multi, 'MultiCo');

        $page = $this->actingAs($this->multi)->get("projects/{$this->p1}")->assertOk();

        $this->assertSame([$mine], $page->viewData('customers')->pluck('id')->all());
        $page->assertDontSee('OutsiderCo');
    }

    #[DataProvider('modes')]
    public function test_the_modal_form_posts_to_the_store_route_and_creates_the_quote_in_that_project(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->customer($this->est, 'Mine');
        $html = $this->actingAs($this->est)->get("projects/{$this->p1}")->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<form id="addEstimateForm" action="([^"]+)" method="POST">/', $html, $m));
        $this->assertSame(route('projects.quotes.store', $this->p1), html_entity_decode($m[1]));
        $this->assertStringContainsString('name="customer_id"', $html);
        $this->assertStringContainsString('name="estimate_label"', $html);
        $this->assertStringNotContainsString('name="project_id"', substr($html, strpos($html, 'id="addEstimateForm"'), 1500));

        $before = DB::table('quotes')->count();
        $id = $this->req($this->est, 'POST', $m[1], ['customer_id' => $c, 'estimate_label' => 'Inline'])->assertOk()->json('quote_id');

        $this->assertSame($before + 1, DB::table('quotes')->count());
        $row = DB::table('quotes')->where('id', $id)->first();
        $this->assertSame($this->p1, (int) $row->project_id);
        $this->assertSame($this->est->id, (int) $row->user_id);
        $this->assertSame('Inline', $row->name);
    }

    public function test_posting_another_users_customer_through_the_form_is_refused(): void
    {
        $theirs = $this->customer($this->owner, 'Owners');
        $before = DB::table('quotes')->count();

        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $theirs])->assertNotFound();
        $this->assertSame($before, DB::table('quotes')->count());
    }

    // ---- 4. crosswalk index `$projects` ------------------------------------------

    private function xwRow(?int $projectId, string $code, ?int $quoteId = null): int
    {
        return (int) DB::table('plan_crosswalk')->insertGetId([
            'org_id' => $this->orgA->id, 'quote_id' => $quoteId, 'project_id' => $projectId, 'plan_line_code' => $code,
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[DataProvider('modes')]
    public function test_crosswalk_projects_list_is_unconditional_for_a_member_and_never_shows_invisible_projects(string $mode): void
    {
        $this->setMode($mode);
        $this->mkProject($this->orgA, $this->est, 'trashed', now()->toDateTimeString());
        $p4 = $this->mkProject($this->orgA, $this->owner, 'P4 not mine');
        $this->member($p4, $this->owner, $this->orgA);
        $this->member($p4, $this->est, $this->orgA, false);
        $this->xwRow($this->p1, 'H1');
        $this->xwRow($this->p2, 'H2');

        $rows = $this->req($this->est, 'GET', 'plan-crosswalk')->assertOk();
        $this->assertSame([$this->p1], $rows->viewData('projects')->pluck('id')->all());
        $this->assertSame(['H1'], $rows->viewData('rows')->pluck('plan_line_code')->all(), 'rows scoping unchanged');

        $owner = $this->req($this->owner, 'GET', 'plan-crosswalk')->assertOk();
        $ids = $owner->viewData('projects')->pluck('id')->all();
        sort($ids);
        $this->assertSame([$this->p1, $this->p2, $p4], $ids);
    }

    public function test_crosswalk_projects_list_for_a_member_is_empty_for_a_stranger_and_scoped_to_the_org_for_a_multi_org_user(): void
    {
        $this->assertTrue($this->req($this->stranger, 'GET', 'plan-crosswalk')->assertOk()->viewData('projects')->isEmpty());

        $mine = $this->req($this->multi, 'GET', 'plan-crosswalk')->assertOk()->viewData('projects')->pluck('id')->all();
        $this->assertSame([$this->p1], $mine, 'org B project P3 never appears in org A context');
        $this->assertNotContains($this->p3, $mine);
    }

    public function test_crosswalk_projects_list_is_shown_for_a_role_less_owner_and_member_but_rows_stay_narrow(): void
    {
        $ownSuper = $this->mkQuote($this->super, null);
        $mine = $this->xwRow(null, 'MINE', $ownSuper);
        $this->xwRow($this->p1, 'B1');
        $this->xwRow($this->p2, 'H2');

        $r = $this->req($this->super, 'GET', 'plan-crosswalk')->assertOk();

        $this->assertSame([$this->p1], $r->viewData('projects')->pluck('id')->all(), 'visible projects only, no P2/P3');
        $this->assertSame([$mine], $r->viewData('rows')->pluck('id')->all(), 'rows must not widen');
    }

    public function test_crosswalk_projects_filter_does_not_widen_rows_for_a_role_less_member(): void
    {
        $ownSuper = $this->mkQuote($this->super, null);
        $mine = $this->xwRow(null, 'MINE', $ownSuper);
        $this->xwRow($this->p1, 'B1');

        $r = $this->req($this->super, 'GET', 'plan-crosswalk?project_id='.$this->p1)->assertOk();
        $this->assertNotContains('B1', $r->viewData('rows')->pluck('plan_line_code')->all());

        $r = $this->req($this->super, 'GET', 'plan-crosswalk?project_id='.$this->p2)->assertOk();
        $this->assertSame([$mine], $r->viewData('rows')->pluck('id')->all(), 'an invisible project id is ignored, not widened');
        $this->assertSame([$this->p1], $r->viewData('projects')->pluck('id')->all());
    }

    public function test_crosswalk_projects_list_is_denied_to_a_role_less_member_in_enforce_mode(): void
    {
        $this->setMode('enforce');
        $csr = $this->mkUser($this->orgA, 'order_fulfillment_csr');
        $this->member($this->p1, $csr, $this->orgA);

        $this->req($csr, 'GET', 'plan-crosswalk')->assertForbidden();
    }
}

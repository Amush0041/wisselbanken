<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\RbacSetting;
use App\Models\User;
use App\Support\Rbac\DenialRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Per-organization enforcement, single-truth denial recording and /org-admin/projects scoping
 * (.claude/tasks/per-org-enforcement/PLAN.md sections 3 to 5 and 8).
 *
 * The system is in production: with rbac_enforced_org_ids empty or unset nothing may change. Org ids from the
 * ProjectTestCase fixture: A = 1, B = 2 (the enforced org in these tests, "org 2"), C = 3 (the bystander).
 * Probe routes use the 'web' group, so the global RbacAudit middleware runs on them exactly once.
 */
class PerOrgEnforcementTest extends ProjectTestCase
{
    private const SET_KEY = 'rbac_enforced_org_ids';

    private $orgC;
    /** viewer_read_only in A, B and C (the same person, so only the session org differs) */
    private User $mv;
    private User $adminAcct;
    private User $ua;
    private User $ownerA;
    private User $ownerB;
    private User $noOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgC = $this->mkOrg('Org C');
        $this->assertSame([1, 2, 3], [$this->orgA->id, $this->orgB->id, $this->orgC->id]);

        $this->mv = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->assignRole($this->mv, $this->orgB, 'viewer_read_only');
        $this->assignRole($this->mv, $this->orgC, 'viewer_read_only');
        $this->adminAcct = User::factory()->create(['role' => 'admin']);
        $this->ua = User::factory()->create(['role' => 'user']);
        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->noOrg = User::factory()->create(['role' => 'user']);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');

        config(['route_permission_map' => config('route_permission_map') + [
            'GET pe-read'          => ['user_management', 'F', 'batch' => 'read'],
            'POST pe-write'        => ['user_management', 'F', 'batch' => 'write'],
            'POST pe-admin'        => ['user_management', 'F', 'batch' => 'admin'],
            'POST pe-approve'      => ['user_management', 'F', 'batch' => 'approve'],
            'GET pe-mapped-pass'   => ['project_management', 'R', 'batch' => 'read'],
            'GET pe-mapped-drop'   => ['project_management', 'R', 'batch' => 'read'],
        ]]);

        Route::middleware('web')->group(function () {
            Route::get('pe-read', fn () => 'ok');
            Route::post('pe-write', fn () => 'ok');
            Route::post('pe-admin', fn () => 'ok');
            Route::post('pe-approve', fn () => 'ok');
            Route::get('pe-mapped-pass', fn () => abort(403, 'Controller says no.'));
            Route::get('pe-mapped-drop', function () {
                Schema::drop('rbac_audit_logs');
                abort(403, 'Controller says no.');
            });
            Route::get('pe-unmapped-twice', function (Request $r) {
                DenialRecorder::record($r);
                DenialRecorder::record($r);
                abort(403, 'Twice.');
            });
            Route::get('pe-unmapped-403', fn () => abort(403, 'Unmapped no.'));
            Route::get('pe-unmapped-default', fn () => abort(403));
            Route::get('pe-unmapped-404', fn () => abort(404));
            Route::get('pe-unmapped-401', fn () => abort(401));
            Route::get('pe-unmapped-422', fn () => abort(422));
            Route::get('pe-unmapped-500', fn () => abort(500));
        });
    }

    // ---- helpers ------------------------------------------------------------------------------

    private function sess(?int $orgId): array
    {
        return $orgId ? [config('rbac.current_org_session_key') => $orgId] : [];
    }

    private function asJson(User $u, string $method, string $uri, ?int $org = null, array $data = [])
    {
        return $this->actingAs($u)->withSession($this->sess($org))->json($method, $uri, $data);
    }

    private function asWeb(User $u, string $method, string $uri, ?int $org = null, array $data = [])
    {
        return $this->actingAs($u)->withSession($this->sess($org))->from(url('/pe-prev'))->call($method, $uri, $data);
    }

    private function enforceSet(array|string|null $ids): void
    {
        if ($ids === null) {
            RbacSetting::where('key', self::SET_KEY)->delete();
            Cache::forget('rbac.setting.' . self::SET_KEY);

            return;
        }
        RbacSetting::set(self::SET_KEY, is_array($ids) ? json_encode($ids) : $ids);
    }

    private function rowsFor(string $outcome): int
    {
        return AuditLog::where('outcome', $outcome)->count();
    }

    // =============================================================================================
    // (A1) RbacSetting::enforcedOrgIds parsing
    // =============================================================================================

    public static function parseCases(): array
    {
        return [
            'empty string'         => ['', []],
            'empty array'          => ['[]', []],
            'invalid json'         => ['[2,', []],
            'plain word'           => ['abc', []],
            'json string scalar'   => ['"abc"', []],
            'json int scalar'      => ['2', []],
            'json null'            => ['null', []],
            'plain ints'           => ['[2]', [2]],
            'duplicates'           => ['[2,3,2,3]', [2, 3]],
            'mixed junk dropped'   => ['[2,"3",2.5,null,true,-1,0,[4],5,2]', [2, 5]],
            'only junk'            => ['["a",null,false,1.5]', []],
        ];
    }

    #[DataProvider('parseCases')]
    public function test_a_enforced_org_ids_parsing_is_defensive(string $raw, array $expected): void
    {
        $this->enforceSet($raw);

        $this->assertSame($expected, RbacSetting::enforcedOrgIds());
    }

    public function test_a_json_object_is_not_a_list_and_parses_to_empty(): void
    {
        $this->enforceSet('{"a":2}');

        $this->assertSame([], RbacSetting::enforcedOrgIds());
    }

    public function test_a_enforced_org_ids_missing_row_is_empty(): void
    {
        $this->enforceSet(null);

        $this->assertSame([], RbacSetting::enforcedOrgIds());
    }

    public function test_a_enforced_org_ids_returns_a_list_not_a_keyed_array(): void
    {
        $this->enforceSet('[3,2,3]');

        $this->assertSame([3, 2], RbacSetting::enforcedOrgIds());
    }

    // =============================================================================================
    // (A2) isEnforcing through the middleware
    // =============================================================================================

    public static function batches(): array
    {
        return [
            'read'    => ['GET', 'pe-read', 'read'],
            'write'   => ['POST', 'pe-write', 'write'],
            'admin'   => ['POST', 'pe-admin', 'admin'],
            'approve' => ['POST', 'pe-approve', 'approve'],
        ];
    }

    public static function emptySets(): array
    {
        return [
            'missing row' => [null],
            'empty'       => [''],
            'array'       => ['[]'],
            'garbage'     => ['[2,'],
            'strings'     => ['["2","3"]'],
        ];
    }

    #[DataProvider('emptySets')]
    public function test_a_global_audit_and_empty_set_never_enforces_in_any_batch(?string $raw): void
    {
        $this->enforceSet($raw);

        foreach ($this->batchRoutes() as [$method, $uri, $batch]) {
            foreach ([[], ['read'], ['write'], ['admin'], ['approve']] as $batches) {
                config(['rbac.enforce_batches' => $batches]);
                AuditLog::query()->delete();

                $this->asWeb($this->mv, $method, $uri, $this->orgB->id)->assertOk()->assertSee('ok');

                $row = AuditLog::sole();
                $this->assertSame('would_block', $row->outcome, "$batch with batches ".json_encode($batches));
                $this->assertSame('no_grant', $row->reason);
                $this->assertSame($this->orgB->id, (int) $row->org_id);
            }
        }
    }

    private function batchRoutes(): array
    {
        return array_values(self::batches());
    }

    #[DataProvider('batches')]
    public function test_a_org_in_set_is_enforced_in_every_batch_even_with_enforce_batches_set(string $method, string $uri, string $batch): void
    {
        $this->enforceSet([2]);
        $other = array_values(array_diff(['read', 'write', 'admin', 'approve'], [$batch]));
        config(['rbac.enforce_batches' => [$other[0]]]);

        $this->asJson($this->mv, $method, $uri, $this->orgB->id)
            ->assertForbidden()
            ->assertJson(['rbac_error' => true, 'group' => 'user_management', 'level' => 'F']);

        $row = AuditLog::sole();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame($batch, $row->batch);
        $this->assertSame($this->orgB->id, (int) $row->org_id);
    }

    #[DataProvider('batches')]
    public function test_a_other_org_stays_audit_while_org_2_is_enforced(string $method, string $uri, string $batch): void
    {
        $this->enforceSet([2]);

        $this->asWeb($this->mv, $method, $uri, $this->orgC->id)->assertOk()->assertSee('ok');

        $this->assertSame('would_block', AuditLog::sole()->outcome);
        $this->assertSame($this->orgC->id, (int) AuditLog::sole()->org_id);

        AuditLog::query()->delete();
        $this->asWeb($this->mv, $method, $uri, $this->orgA->id)->assertOk();
        $this->assertSame('would_block', AuditLog::sole()->outcome);
    }

    public function test_a_the_same_user_is_blocked_in_org_2_and_passed_through_in_org_3_in_one_run(): void
    {
        $this->enforceSet([2]);

        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertForbidden();
        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgC->id)->assertOk();

        $this->assertSame([[2, 'blocked'], [3, 'would_block']], AuditLog::orderBy('id')->get()->map(fn ($r) => [(int) $r->org_id, $r->outcome])->all());
    }

    public function test_a_enforced_org_gets_the_json_enforce_response_and_one_blocked_row(): void
    {
        $this->enforceSet([2]);

        $res = $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertForbidden();

        $this->assertTrue($res->json('rbac_error'));
        $this->assertNotSame('', (string) $res->json('message'));
        $this->assertSame(1, AuditLog::count());
        $this->assertSame('blocked', AuditLog::first()->outcome);
    }

    public function test_a_enforced_org_non_json_denial_redirects_back_with_rbac_denied(): void
    {
        $this->enforceSet([2]);

        $this->asWeb($this->mv, 'GET', 'pe-read', $this->orgB->id)
            ->assertRedirect(url('/pe-prev'))
            ->assertSessionHas('rbac_denied');

        $this->assertSame('blocked', AuditLog::sole()->outcome);
    }

    public function test_a_non_json_post_denial_in_an_enforced_org_redirects_back_with_input(): void
    {
        $this->enforceSet([2]);

        $this->asWeb($this->mv, 'POST', 'pe-write', $this->orgB->id, ['keep' => 'me'])
            ->assertRedirect(url('/pe-prev'))
            ->assertSessionHas('rbac_denied')
            ->assertSessionHasInput('keep', 'me');
    }

    public function test_a_global_enforce_is_unchanged_and_the_set_cannot_weaken_it(): void
    {
        $this->setMode('enforce');

        foreach ([null, '[]', '[3]', '[2]'] as $raw) {
            $this->enforceSet($raw);
            AuditLog::query()->delete();

            $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertForbidden();
            $this->assertSame('blocked', AuditLog::sole()->outcome, "set=".var_export($raw, true));
        }
    }

    public function test_a_global_enforce_with_batches_keeps_other_batches_in_audit_for_orgs_not_in_the_set(): void
    {
        $this->setMode('enforce');
        config(['rbac.enforce_batches' => ['write']]);
        $this->enforceSet([3]);

        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertOk();
        $this->assertSame('would_block', AuditLog::sole()->outcome);

        AuditLog::query()->delete();
        $this->asJson($this->mv, 'POST', 'pe-write', $this->orgB->id)->assertForbidden();
        $this->assertSame('blocked', AuditLog::sole()->outcome);

        AuditLog::query()->delete();
        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgC->id)->assertForbidden();
        $this->assertSame('blocked', AuditLog::sole()->outcome, 'org in the set ignores enforce_batches even under global enforce');
    }

    public function test_a_no_org_is_governed_by_the_global_rule_only(): void
    {
        $this->enforceSet([1, 2, 3]);

        $this->asJson($this->noOrg, 'GET', 'pe-read')->assertOk();
        $row = AuditLog::sole();
        $this->assertSame('would_block', $row->outcome);
        $this->assertSame('no_org', $row->reason);
        $this->assertNull($row->org_id);

        AuditLog::query()->delete();
        $this->setMode('enforce');
        $this->asJson($this->noOrg, 'GET', 'pe-read')->assertForbidden();
        $this->assertSame('blocked', AuditLog::sole()->outcome);
    }

    public function test_a_universal_admin_and_platform_admin_are_unaffected_by_the_set(): void
    {
        $this->enforceSet([1, 2, 3]);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        foreach ([$this->orgA->id, $this->orgB->id, $this->orgC->id] as $org) {
            $this->asJson($this->ua, 'GET', 'pe-read', $org)->assertOk()->assertSee('ok');
        }
        $this->assertSame(0, $this->rowsFor('blocked'));
        $this->assertSame(0, $this->rowsFor('would_block'));

        $this->asJson($this->adminAcct, 'GET', 'pe-read', $this->orgB->id)->assertOk()->assertSee('ok');
        $this->assertSame(0, $this->rowsFor('blocked'));
        $this->assertSame(0, $this->rowsFor('would_block'));
    }

    public function test_a_an_allowed_user_in_an_enforced_org_passes_and_writes_nothing(): void
    {
        $this->enforceSet([2]);

        $this->asJson($this->ownerB, 'GET', 'pe-read', $this->orgB->id)->assertOk()->assertSee('ok');

        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_a_listed_org_still_blocks_a_wrong_role_but_not_the_owner_of_that_org(): void
    {
        $this->enforceSet([1]);

        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgA->id)->assertForbidden();
        $this->asJson($this->ownerA, 'GET', 'pe-read', $this->orgA->id)->assertOk();
    }

    public function test_a_session_org_that_is_not_theirs_falls_back_to_their_own_unenforced_org(): void
    {
        $this->enforceSet([2]);

        $this->asJson($this->viewer, 'GET', 'pe-read', $this->orgB->id)->assertOk();

        $row = AuditLog::sole();
        $this->assertSame('would_block', $row->outcome);
        $this->assertSame($this->orgA->id, (int) $row->org_id);
    }

    // =============================================================================================
    // (A3) The admin card
    // =============================================================================================

    private function save(?User $u, $orgIds, bool $json = true)
    {
        $payload = $orgIds === null ? [] : ['org_ids' => $orgIds];

        return $json
            ? $this->actingAs($u)->postJson('admin/rbac/enforcement/orgs', $payload)
            : $this->actingAs($u)->post('admin/rbac/enforcement/orgs', $payload);
    }

    public function test_a_platform_admin_saves_sorted_unique_ids(): void
    {
        $this->save($this->adminAcct, [3, 1, 3, 2, 1], false)->assertSessionHas('success');

        $this->assertSame('[1,2,3]', RbacSetting::find(self::SET_KEY)->value);
        $this->assertSame([1, 2, 3], RbacSetting::enforcedOrgIds());
    }

    public function test_a_string_ids_from_the_form_are_stored_as_ints(): void
    {
        $this->save($this->adminAcct, ['3', '2'], false)->assertSessionHas('success');

        $this->assertSame('[2,3]', RbacSetting::find(self::SET_KEY)->value);
    }

    public function test_a_unknown_org_is_rejected_and_nothing_is_saved(): void
    {
        $this->enforceSet([2]);

        $this->save($this->adminAcct, [2, 999])->assertStatus(422)->assertJsonValidationErrors(['org_ids.1']);

        $this->assertSame('[2]', RbacSetting::find(self::SET_KEY)->value);
        $this->assertSame(0, $this->rowsFor('enforcement_changed'));
    }

    public function test_a_non_array_and_non_int_payloads_are_rejected(): void
    {
        $this->enforceSet([2]);

        $this->save($this->adminAcct, 'abc')->assertStatus(422)->assertJsonValidationErrors(['org_ids']);
        $this->save($this->adminAcct, ['x'])->assertStatus(422);
        $this->save($this->adminAcct, [[1]])->assertStatus(422);
        $this->save($this->adminAcct, [1.5])->assertStatus(422);

        $this->assertSame('[2]', RbacSetting::find(self::SET_KEY)->value);
    }

    public function test_a_empty_selection_stores_an_empty_list(): void
    {
        $this->enforceSet([2, 3]);

        $this->save($this->adminAcct, null, false)->assertSessionHas('success');

        $this->assertSame('[]', RbacSetting::find(self::SET_KEY)->value);
        $this->assertSame([], RbacSetting::enforcedOrgIds());
    }

    public function test_a_saving_when_the_row_does_not_exist_creates_it(): void
    {
        $this->assertNull(RbacSetting::find(self::SET_KEY));

        $this->save($this->adminAcct, [], false)->assertSessionHas('success');

        $this->assertSame('[]', RbacSetting::find(self::SET_KEY)->value);
    }

    public function test_a_non_admins_are_denied_and_nothing_changes(): void
    {
        $this->enforceSet([3]);

        foreach ([$this->ownerA, $this->ownerB, $this->mv, $this->est, $this->noOrg] as $u) {
            $this->save($u, [1, 2])->assertForbidden();
        }
        $this->actingAs($this->ownerA)->post('admin/rbac/enforcement/orgs', ['org_ids' => [1]])->assertRedirect();
        $this->actingAs($this->ownerA)->get('admin/rbac/enforcement')->assertRedirect();

        $this->assertSame('[3]', RbacSetting::find(self::SET_KEY)->value);
        $this->assertSame(0, $this->rowsFor('enforcement_changed'));
    }

    public function test_a_guest_cannot_save(): void
    {
        $this->postJson('admin/rbac/enforcement/orgs', ['org_ids' => [1]])->assertStatus(401);

        $this->assertNull(RbacSetting::find(self::SET_KEY));
    }

    public function test_a_listed_universal_admin_can_save(): void
    {
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->save($this->ua, [2], false)->assertSessionHas('success');

        $this->assertSame([2], RbacSetting::enforcedOrgIds());
    }

    public function test_a_each_add_and_remove_writes_exactly_one_change_row_with_actor_and_reason(): void
    {
        $this->save($this->adminAcct, [2, 3], false);
        $rows = AuditLog::where('outcome', 'enforcement_changed')->orderBy('org_id')->get();

        $this->assertSame([2, 3], $rows->pluck('org_id')->map(fn ($v) => (int) $v)->all());
        foreach ($rows as $r) {
            $this->assertSame('enforcement_enabled', $r->reason);
            $this->assertSame($this->adminAcct->id, (int) $r->user_id);
            $this->assertSame('system_administration', $r->permission_group);
            $this->assertSame('F', $r->required_level);
            $this->assertSame('admin', $r->batch);
            $this->assertSame('POST', $r->method);
            $this->assertSame('admin/rbac/enforcement/orgs', $r->route_uri);
        }

        AuditLog::query()->delete();
        $this->save($this->adminAcct, [3, 1], false);
        $byOrg = AuditLog::where('outcome', 'enforcement_changed')->get()->mapWithKeys(fn ($r) => [(int) $r->org_id => $r->reason])->all();
        $this->assertSame([1 => 'enforcement_enabled', 2 => 'enforcement_disabled'], $byOrg, 'org 3 unchanged writes nothing');
        $this->assertSame(2, AuditLog::count());

        AuditLog::query()->delete();
        $this->save($this->adminAcct, [3, 1], false);
        $this->assertSame(0, AuditLog::count(), 'saving the same set writes no rows');

        $this->save($this->adminAcct, null, false);
        $byOrg = AuditLog::where('outcome', 'enforcement_changed')->get()->mapWithKeys(fn ($r) => [(int) $r->org_id => $r->reason])->all();
        $this->assertSame([1 => 'enforcement_disabled', 3 => 'enforcement_disabled'], $byOrg);
    }

    public function test_a_each_change_is_also_written_to_the_log_channel(): void
    {
        Log::spy();

        $this->save($this->adminAcct, [2], false);

        Log::shouldHaveReceived('info')->withArgs(fn ($m, $c = []) => $m === 'rbac-enforcement-change'
            && $c['org_id'] === 2 && $c['reason'] === 'enforcement_enabled' && $c['user_id'] === $this->adminAcct->id)->once();
    }

    public function test_a_logging_failure_does_not_block_the_save(): void
    {
        Schema::drop('rbac_audit_logs');

        $this->save($this->adminAcct, [2, 3], false)->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertSame([2, 3], RbacSetting::enforcedOrgIds());
    }

    public function test_a_a_save_takes_effect_immediately_on_this_server_and_off_again(): void
    {
        $this->assertSame([], RbacSetting::enforcedOrgIds());
        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertOk();

        $this->save($this->adminAcct, [2], false);
        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertForbidden();

        $this->save($this->adminAcct, [], false);
        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertOk();
    }

    public function test_a_route_map_entry_and_admin_gate(): void
    {
        $this->assertSame(['system_administration', 'F', 'batch' => 'admin'], config('route_permission_map')['POST admin/rbac/enforcement/orgs']);

        $route = Route::getRoutes()->getByName('admin.rbac.enforcement.orgs');
        $this->assertNotNull($route);
        $this->assertSame('admin/rbac/enforcement/orgs', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertContains('checkRole:admin', $route->gatherMiddleware());
    }

    public function test_a_enforcement_page_lists_orgs_with_the_enforced_ones_selected(): void
    {
        $this->enforceSet([2]);

        $res = $this->actingAs($this->adminAcct)->get('admin/rbac/enforcement')->assertOk();

        $res->assertSee('Enforced organizations')->assertSee(route('admin.rbac.enforcement.orgs'), false);
        $this->assertSame([2], $res->viewData('enforcedOrgIds'));
        $html = $res->getContent();
        $this->assertMatchesRegularExpression('/<option value="2"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="3"\s+selected/', $html);
        $this->assertStringContainsString('Org C (#3)', $html);
    }

    public function test_a_rbac_index_names_enforced_orgs_only_when_there_are_some(): void
    {
        $this->actingAs($this->adminAcct)->get('admin/rbac')->assertOk()->assertDontSee('Enforced organizations:');

        $this->enforceSet([2, 3]);
        $this->actingAs($this->adminAcct)->get('admin/rbac')->assertOk()->assertSee('Enforced organizations:')->assertSee('Org B, Org C');
    }

    // =============================================================================================
    // (B) DenialRecorder
    // =============================================================================================

    public function test_b_controller_403_in_audit_mode_upgrades_the_middleware_row_instead_of_duplicating(): void
    {
        $res = $this->asJson($this->mv, 'POST', 'org-admin/projects/members', $this->orgA->id, ['project_id' => $this->p1, 'user_id' => $this->est->id]);

        $res->assertForbidden()->assertExactJson([
            'rbac_error' => true,
            'message' => 'You need full project management access to change project members.',
        ]);
        $row = AuditLog::sole();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame('no_grant', $row->reason);
        $this->assertSame('project_management', $row->permission_group);
        $this->assertSame('F', $row->required_level);
        $this->assertSame('POST org-admin/projects/members', $row->matched_pattern);
        $this->assertSame(0, DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->where('id', '>', 6)->count());
    }

    public function test_b_unmapped_route_abort_inserts_one_controller_denied_row(): void
    {
        $this->asJson($this->mv, 'GET', 'pe-unmapped-403', $this->orgC->id)->assertForbidden()
            ->assertExactJson(['rbac_error' => true, 'message' => 'Unmapped no.']);

        $row = AuditLog::sole();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame('controller_denied', $row->reason);
        $this->assertSame($this->mv->id, (int) $row->user_id);
        $this->assertSame($this->orgC->id, (int) $row->org_id);
        $this->assertSame('GET', $row->method);
        $this->assertSame('pe-unmapped-403', $row->route_uri);
        $this->assertNull($row->matched_pattern);
        $this->assertNull($row->permission_group);
        $this->assertNull($row->required_level);
        $this->assertNull($row->batch);
    }

    public function test_b_mapped_route_where_the_middleware_passed_but_the_controller_refused_records_the_rule(): void
    {
        $this->asJson($this->mv, 'GET', 'pe-mapped-pass', $this->orgA->id)->assertForbidden();

        $row = AuditLog::sole();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame('controller_denied', $row->reason);
        $this->assertSame('GET pe-mapped-pass', $row->matched_pattern);
        $this->assertSame('project_management', $row->permission_group);
        $this->assertSame('R', $row->required_level);
        $this->assertSame('read', $row->batch);
    }

    public function test_b_two_recorder_calls_in_one_request_write_one_row(): void
    {
        $this->asJson($this->mv, 'GET', 'pe-unmapped-twice', $this->orgA->id)->assertForbidden();

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('controller_denied', AuditLog::first()->reason);
    }

    public function test_b_the_recorder_ignores_a_request_without_a_user(): void
    {
        $request = Request::create('/x', 'GET');

        DenialRecorder::record($request);

        $this->assertSame(0, AuditLog::count());
        $this->assertNull($request->attributes->get('rbac_denial_recorded'));
    }

    public function test_b_each_request_gets_its_own_row(): void
    {
        $this->asJson($this->mv, 'GET', 'pe-unmapped-403', $this->orgA->id)->assertForbidden();
        $this->asJson($this->mv, 'GET', 'pe-unmapped-403', $this->orgA->id)->assertForbidden();

        $this->assertSame(2, AuditLog::count());
    }

    public function test_b_default_403_message_is_replaced_exactly_as_before(): void
    {
        $this->asJson($this->mv, 'GET', 'pe-unmapped-default', $this->orgA->id)->assertForbidden()
            ->assertExactJson(['rbac_error' => true, 'message' => 'You do not have permission to perform this action.']);
    }

    public function test_b_non_json_responses_are_unchanged_redirect_and_error_page(): void
    {
        $this->asWeb($this->mv, 'GET', 'pe-unmapped-403', $this->orgA->id)
            ->assertRedirect(url('/pe-prev'))->assertSessionHas('rbac_denied', 'Unmapped no.');

        $this->assertSame(1, AuditLog::count());
    }

    public function test_b_without_a_usable_previous_page_the_403_view_is_rendered_as_before(): void
    {
        $this->actingAs($this->mv)->withSession($this->sess($this->orgA->id))->get('pe-unmapped-403')
            ->assertForbidden()->assertViewIs('errors.rbac-403')->assertViewHas('message', 'Unmapped no.');

        $this->assertSame(1, AuditLog::count());
    }

    public function test_b_recorder_failure_never_changes_the_response_insert_path(): void
    {
        $ok = $this->asJson($this->mv, 'GET', 'pe-unmapped-403', $this->orgA->id);
        $okJson = $ok->json();
        $web = $this->asWeb($this->mv, 'GET', 'pe-unmapped-403', $this->orgA->id);
        $webMsg = session('rbac_denied');

        Schema::drop('rbac_audit_logs');

        $broken = $this->asJson($this->mv, 'GET', 'pe-unmapped-403', $this->orgA->id);
        $broken->assertForbidden();
        $this->assertSame($okJson, $broken->json());
        $this->asWeb($this->mv, 'GET', 'pe-unmapped-403', $this->orgA->id)
            ->assertRedirect(url('/pe-prev'))->assertSessionHas('rbac_denied', $webMsg);
    }

    public function test_b_recorder_failure_never_changes_the_response_upgrade_path(): void
    {
        $this->asJson($this->mv, 'GET', 'pe-mapped-drop', $this->orgA->id)
            ->assertForbidden()
            ->assertExactJson(['rbac_error' => true, 'message' => 'Controller says no.']);
    }

    public function test_b_recorder_does_not_throw_when_called_directly_with_a_broken_table(): void
    {
        $request = Request::create('/x', 'GET');
        $request->setUserResolver(fn () => $this->mv);
        Schema::drop('rbac_audit_logs');

        DenialRecorder::record($request);

        $this->assertTrue(true);
    }

    public static function otherStatuses(): array
    {
        return ['404' => ['pe-unmapped-404', 404], '401' => ['pe-unmapped-401', 401], '422' => ['pe-unmapped-422', 422], '500' => ['pe-unmapped-500', 500]];
    }

    #[DataProvider('otherStatuses')]
    public function test_b_other_statuses_are_not_recorded(string $uri, int $status): void
    {
        $this->asJson($this->mv, 'GET', $uri, $this->orgA->id)->assertStatus($status);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_b_a_missing_page_is_not_recorded(): void
    {
        $this->asJson($this->mv, 'GET', 'no-such-page-at-all', $this->orgA->id)->assertNotFound();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_b_guests_are_not_recorded(): void
    {
        $this->getJson('pe-unmapped-403')->assertForbidden();
        $this->get('pe-unmapped-403')->assertForbidden();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_b_enforce_mode_middleware_block_is_one_row_and_unchanged(): void
    {
        $this->setMode('enforce');

        $this->asJson($this->mv, 'POST', 'org-admin/projects/members', $this->orgA->id, ['project_id' => $this->p1, 'user_id' => $this->est->id])
            ->assertForbidden();

        $row = AuditLog::sole();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame('no_grant', $row->reason, 'the middleware row, not a controller_denied row');
    }

    public function test_b_org_enforced_middleware_block_is_one_row_and_never_reaches_the_controller(): void
    {
        $this->enforceSet([1]);

        $this->asJson($this->mv, 'POST', 'org-admin/projects/members', $this->orgA->id, ['project_id' => $this->p1, 'user_id' => $this->est->id])
            ->assertForbidden()->assertJsonPath('group', 'project_management');

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('no_grant', AuditLog::first()->reason);
    }

    public function test_b_allowed_requests_write_nothing(): void
    {
        $this->asJson($this->ownerA, 'GET', 'org-admin/projects', $this->orgA->id)->assertOk();
        $this->asJson($this->ownerA, 'GET', 'pe-read', $this->orgA->id)->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_b_a_denied_universal_admin_integrity_abort_is_still_recorded_but_allowed_requests_are_not(): void
    {
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $this->asJson($this->ua, 'GET', 'pe-read', $this->orgA->id)->assertOk();
        $this->assertSame(0, $this->rowsFor('blocked'));

        $this->asJson($this->ua, 'GET', 'pe-unmapped-403', $this->orgA->id)->assertForbidden();
        $this->assertSame(1, $this->rowsFor('blocked'));
    }

    // ---- logs and banners ----------------------------------------------------------------------

    private function seedLogRows(int $orgId): void
    {
        $base = ['user_id' => $this->ownerA->id, 'org_id' => $orgId, 'method' => 'GET', 'permission_group' => 'user_management', 'required_level' => 'F'];
        AuditLog::create($base + ['route_uri' => 'pe/blocked-route', 'outcome' => 'blocked', 'reason' => 'controller_denied']);
        AuditLog::create($base + ['route_uri' => 'pe/would-route', 'outcome' => 'would_block', 'reason' => 'no_grant']);
        AuditLog::create($base + ['route_uri' => 'pe/changed-route', 'outcome' => 'enforcement_changed', 'reason' => 'enforcement_enabled']);
    }

    private function orgPage(User $u, int $org): string
    {
        return $this->actingAs($u)->withSession($this->sess($org))->get('org-admin/audit-log?tab=enforcement')->assertOk()->getContent();
    }

    public function test_b_org_audit_log_labels_blocked_would_block_and_setting_changed(): void
    {
        $this->seedLogRows($this->orgA->id);

        $html = $this->orgPage($this->ownerA, $this->orgA->id);

        $this->assertStringContainsString('>Blocked</span>', $html);
        $this->assertStringContainsString('>Would Block</span>', $html);
        $this->assertStringContainsString('>Setting changed</span>', $html);
        $this->assertStringContainsString('pe/blocked-route', $html);
    }

    public function test_b_admin_enforcement_log_labels_and_filter(): void
    {
        $this->seedLogRows($this->orgA->id);

        $html = $this->actingAs($this->adminAcct)->get('admin/rbac/audit-logs')->assertOk()->getContent();
        $this->assertStringContainsString('Setting changed</span>', $html);
        $this->assertStringContainsString('Would Block</span>', $html);
        $this->assertStringContainsString('value="enforcement_changed"', $html);

        $only = $this->actingAs($this->adminAcct)->get('admin/rbac/audit-logs?outcome=enforcement_changed')->assertOk()->getContent();
        $this->assertStringContainsString('pe/changed-route', $only);
        $this->assertStringNotContainsString('pe/would-route', $only);

        $enf = $this->actingAs($this->adminAcct)->get('admin/rbac/enforcement')->assertOk()->getContent();
        $this->assertStringContainsString('Setting changed</span>', $enf);
    }

    public function test_b_banner_audit_mode_has_the_reworded_text(): void
    {
        $html = $this->orgPage($this->ownerA, $this->orgA->id);

        $this->assertStringContainsString('<strong>Audit mode:</strong>', $html);
        $this->assertStringContainsString('Some actions are already refused by controller-level checks', $html);
        $this->assertStringNotContainsString('No users are currently blocked', $html);
        $this->assertStringNotContainsString('Enforcement ON for this organization', $html);
    }

    public function test_b_banner_org_enforced_shows_for_that_org_only_in_the_same_run(): void
    {
        $this->enforceSet([2]);

        $b = $this->orgPage($this->ownerB, $this->orgB->id);
        $a = $this->orgPage($this->ownerA, $this->orgA->id);

        $this->assertStringContainsString('Enforcement ON for this organization (all batches)', $b);
        $this->assertStringNotContainsString('<strong>Audit mode:</strong>', $b);
        $this->assertStringContainsString('<strong>Audit mode:</strong>', $a);
        $this->assertStringNotContainsString('Enforcement ON for this organization', $a);
    }

    public function test_b_banner_global_enforce_and_batches_are_unchanged(): void
    {
        $this->setMode('enforce');
        $all = $this->orgPage($this->ownerA, $this->orgA->id);
        $this->assertStringContainsString('Enforcement is ON for all route groups', $all);
        $this->assertStringNotContainsString('Enforcement ON for this organization', $all);

        config(['rbac.enforce_batches' => ['write']]);
        $batches = $this->orgPage($this->ownerA, $this->orgA->id);
        $this->assertStringContainsString('Enforcement is ON for batches write', $batches);

        $this->enforceSet([1]);
        $org = $this->orgPage($this->ownerA, $this->orgA->id);
        $this->assertStringContainsString('Enforcement ON for this organization (all batches)', $org);
        $this->assertStringNotContainsString('Enforcement is ON for batches', $org);
    }

    // =============================================================================================
    // (C) /org-admin/projects scoping
    // =============================================================================================

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function projectPage(User $u, ?int $org = null)
    {
        return $this->actingAs($u)->withSession($this->sess($org ?? $this->orgA->id))->get('org-admin/projects');
    }

    private function projectIds($res): array
    {
        return $res->viewData('projects')->pluck('id')->sort()->values()->all();
    }

    private function memberNamesFor($res, int $projectId): array
    {
        return $res->viewData('projects')->firstWhere('id', $projectId)->project_members_list->map(fn ($m) => $m->user->name)->sort()->values()->all();
    }

    private function nameUsers(): void
    {
        $this->stranger->update(['name' => 'Zed Dropdown Only']);
        $this->owner->update(['name' => 'Owen Owner']);
    }

    #[DataProvider('modes')]
    public function test_c_a_viewer_sees_only_the_projects_they_belong_to_and_their_members(string $mode): void
    {
        $this->setMode($mode);
        $this->nameUsers();
        $p2only = $this->mkUser($this->orgA, 'estimator');
        $p2only->update(['name' => 'Pee Two Only']);
        $this->member($this->p2, $p2only, $this->orgA);

        $res = $this->projectPage($this->viewer)->assertOk();

        $this->assertSame([$this->p1], $this->projectIds($res));
        $this->assertContains('Owen Owner', $this->memberNamesFor($res, $this->p1));
        $res->assertDontSee('Pee Two Only')->assertDontSee('Zed Dropdown Only');
        $this->assertFalse($res->viewData('canManageProjects'));
        $this->assertCount(0, $res->viewData('members'));
        $res->assertDontSee('Add member');
    }

    #[DataProvider('modes')]
    public function test_c_an_engineer_in_project_2_only_sees_project_2(string $mode): void
    {
        $this->setMode($mode);
        $eng = $this->mkUser($this->orgA, 'engineer');
        $this->member($this->p2, $eng, $this->orgA);

        $res = $this->projectPage($eng)->assertOk();

        $this->assertSame([$this->p2], $this->projectIds($res));
        $this->assertSame(1, $res->viewData('projects')->first()->quotes_count);
        $this->assertCount(0, $res->viewData('members'));
        $this->assertFalse($res->viewData('canManageProjects'));
    }

    #[DataProvider('modes')]
    public function test_c_a_viewer_with_no_membership_sees_nothing_and_no_org_user_list(string $mode): void
    {
        $this->setMode($mode);
        $this->nameUsers();
        $nobody = $this->mkUser($this->orgA, 'viewer_read_only');

        $res = $this->projectPage($nobody)->assertOk();

        $this->assertSame([], $this->projectIds($res));
        $res->assertSee('No projects found in this organization.')->assertDontSee('Zed Dropdown Only')->assertDontSee('Owen Owner');
        $this->assertCount(0, $res->viewData('members'));
    }

    public function test_c_an_inactive_membership_or_trashed_project_is_not_shown_to_a_viewer(): void
    {
        $this->setMode('audit');
        $v = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->member($this->p1, $v, $this->orgA, false);
        $trashed = $this->mkProject($this->orgA, $this->owner, 'Trashed', now()->toDateTimeString());
        $this->member($trashed, $v, $this->orgA);

        $this->assertSame([], $this->projectIds($this->projectPage($v)->assertOk()));
    }

    public function test_c_a_viewer_member_row_in_another_org_does_not_open_that_orgs_projects(): void
    {
        $this->setMode('audit');
        $res = $this->projectPage($this->mv, $this->orgB->id)->assertOk();
        $this->assertSame([], $this->projectIds($res), 'mv has no membership rows at all');

        $this->member($this->p3, $this->mv, $this->orgB);
        $this->assertSame([$this->p3], $this->projectIds($this->projectPage($this->mv, $this->orgB->id)));
        $this->assertSame([], $this->projectIds($this->projectPage($this->mv, $this->orgA->id)));
    }

    #[DataProvider('modes')]
    public function test_c_project_management_f_roles_see_all_org_projects_as_before(string $mode): void
    {
        $this->setMode($mode);
        $this->nameUsers();
        $pm = $this->mkUser($this->orgA, 'project_manager');
        $admin = $this->mkUser($this->orgA, 'organization_admin');

        foreach ([$this->est, $this->stranger, $this->ownerA, $pm, $admin] as $u) {
            $res = $this->projectPage($u)->assertOk();

            $this->assertSame([$this->p1, $this->p2], $this->projectIds($res), "user {$u->id}");
            $this->assertTrue($res->viewData('canManageProjects'));
            $this->assertSame([2, 1], $res->viewData('projects')->sortByDesc('id')->sortBy(fn ($p) => $p->id === $this->p1 ? 0 : 1)->pluck('quotes_count')->all());
            $res->assertSee('Zed Dropdown Only')->assertSee('Add member');
        }
    }

    public function test_c_f_roles_never_see_another_orgs_projects_or_their_members(): void
    {
        $this->setMode('audit');
        $res = $this->projectPage($this->est)->assertOk();
        $this->assertNotContains($this->p3, $this->projectIds($res));
        $this->assertNotContains($this->outsider->name, $res->viewData('members')->pluck('name')->all());

        $this->assertSame([$this->p3], $this->projectIds($this->projectPage($this->outsider, $this->orgB->id)->assertOk()));
        $this->assertSame([$this->p3], $this->projectIds($this->projectPage($this->multi, $this->orgB->id)->assertOk()));
        $this->assertSame([$this->p1, $this->p2], $this->projectIds($this->projectPage($this->multi, $this->orgA->id)->assertOk()));
    }

    public function test_c_an_outsider_cannot_read_org_a_by_session_forgery(): void
    {
        $this->setMode('audit');

        $res = $this->projectPage($this->outsider, $this->orgA->id)->assertOk();

        $this->assertSame([$this->p3], $this->projectIds($res));
    }

    public function test_c_universal_admin_sees_all_projects_of_the_org_but_never_another_orgs(): void
    {
        $this->setMode('audit');
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);

        $res = $this->projectPage($this->ua, $this->orgA->id)->assertOk();
        $this->assertSame([$this->p1, $this->p2], $this->projectIds($res));
        $this->assertTrue($res->viewData('canManageProjects'));

        $this->assertSame([$this->p3], $this->projectIds($this->projectPage($this->ua, $this->orgB->id)));
    }

    public function test_c_a_membership_row_filed_under_the_wrong_org_does_not_open_another_orgs_project(): void
    {
        $this->setMode('audit');
        $v = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->member($this->p3, $v, $this->orgA);

        $this->assertSame([], $this->projectIds($this->projectPage($v)->assertOk()));
    }

    public function test_c_a_viewer_is_still_denied_without_project_management_read(): void
    {
        $this->setMode('audit');
        $mfr = $this->mkOrg('Mfr');
        $u = $this->mkUser($mfr, 'sales_rep');

        $this->actingAs($u)->withSession($this->sess($mfr->id))->getJson('org-admin/projects')->assertForbidden();
    }

    // =============================================================================================
    // No-regression with an empty set
    // =============================================================================================

    private function signature(): array
    {
        $sig = [];
        $this->setMode('audit');
        foreach ([['GET', 'pe-read'], ['POST', 'pe-write']] as [$m, $u]) {
            $r = $this->asJson($this->mv, $m, $u, $this->orgB->id);
            $sig[] = [$m.$u.'-audit', $r->getStatusCode(), $r->getContent()];
        }
        $r = $this->asJson($this->mv, 'POST', 'org-admin/projects/members', $this->orgB->id, ['project_id' => $this->p3, 'user_id' => $this->outsider->id]);
        $sig[] = ['members-audit', $r->getStatusCode(), $r->getContent()];
        $r = $this->asWeb($this->mv, 'GET', 'pe-unmapped-403', $this->orgB->id);
        $sig[] = ['unmapped-web', $r->getStatusCode(), $r->headers->get('Location'), session('rbac_denied')];

        $this->setMode('enforce');
        foreach ([['GET', 'pe-read'], ['POST', 'pe-admin']] as [$m, $u]) {
            $r = $this->asJson($this->mv, $m, $u, $this->orgB->id);
            $sig[] = [$m.$u.'-enforce', $r->getStatusCode(), $r->getContent()];
        }
        $r = $this->asWeb($this->mv, 'GET', 'pe-read', $this->orgB->id);
        $sig[] = ['read-web-enforce', $r->getStatusCode(), $r->headers->get('Location'), session('rbac_denied')];
        $r = $this->asJson($this->ownerB, 'GET', 'pe-read', $this->orgB->id);
        $sig[] = ['allowed', $r->getStatusCode(), $r->getContent()];

        $sig[] = ['rows', AuditLog::orderBy('id')->get()->map(fn ($x) => [$x->outcome, $x->reason, $x->route_uri, (int) $x->org_id])->all()];
        $sig[] = ['members', DB::table('project_members')->count()];

        return $sig;
    }

    public function test_n_every_empty_or_junk_value_behaves_exactly_like_a_missing_row(): void
    {
        $this->enforceSet(null);
        $baseline = $this->signature();

        foreach (['', '[]', 'abc', '[2,', '"2"', '["2"]', 'null', '[0,-2]'] as $raw) {
            AuditLog::query()->delete();
            $this->setMode('audit');
            $this->enforceSet($raw);

            $this->assertSame($baseline, $this->signature(), 'value: '.$raw);
        }
    }

    public function test_n_with_the_set_empty_audit_mode_responses_and_rows_are_the_old_ones(): void
    {
        $this->enforceSet(null);

        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertOk()->assertSee('ok');
        $this->asWeb($this->mv, 'POST', 'pe-write', $this->orgB->id)->assertOk();
        $this->assertSame(['would_block', 'would_block'], AuditLog::orderBy('id')->pluck('outcome')->all());
        $this->assertSame(0, $this->rowsFor('blocked'));
        $this->assertSame(0, $this->rowsFor('enforcement_changed'));
    }

    public function test_n_with_the_set_empty_a_controller_refusal_keeps_its_message_and_changes_no_data(): void
    {
        $this->enforceSet(null);
        $before = DB::table('project_members')->count();

        $this->asJson($this->mv, 'POST', 'org-admin/projects/members', $this->orgA->id, ['project_id' => $this->p1, 'user_id' => $this->stranger->id])
            ->assertForbidden()
            ->assertExactJson(['rbac_error' => true, 'message' => 'You need full project management access to change project members.']);

        $this->assertSame($before, DB::table('project_members')->count());
        $this->assertSame(1, AuditLog::count(), 'one row for one refused request, upgraded not duplicated');
    }

    public function test_n_global_enforce_with_the_set_empty_blocks_exactly_as_before(): void
    {
        $this->setMode('enforce');

        $this->asJson($this->mv, 'GET', 'pe-read', $this->orgB->id)->assertForbidden()
            ->assertJsonStructure(['rbac_error', 'message', 'group', 'level']);
        $this->assertSame(['blocked'], AuditLog::pluck('outcome')->all());
    }
}

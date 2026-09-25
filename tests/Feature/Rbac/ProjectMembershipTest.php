<?php

namespace Tests\Feature\Rbac;

use App\Http\Middleware\RbacAudit;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Rbac\AuditLog;
use App\Services\Rbac\DelegationService;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 3 step 5 and 6 (PLAN D3, D4, D10 M1-M11, A1-A11): project membership in
 * PermissionService::isActiveProjectMember and the quote_param/project_param resolution in RbacAudit.
 */
class ProjectMembershipTest extends ProjectTestCase
{
    private PermissionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(PermissionService::class);

        config(['route_permission_map' => [
            'GET probe/q/{id}' => ['estimate_management', 'R', 'batch' => 'read', 'quote_param' => 'id'],
            'GET probe/qm/{quote}' => ['estimate_management', 'R', 'batch' => 'read', 'quote_param' => 'quote'],
            'GET probe/p/{id}' => ['estimate_management', 'R', 'batch' => 'read', 'project_param' => 'id'],
            'GET probe/pm/{project}' => ['estimate_management', 'R', 'batch' => 'read', 'project_param' => 'project'],
            'GET probe/none' => ['estimate_management', 'R', 'batch' => 'read'],
            'GET probe/absent-q' => ['estimate_management', 'R', 'batch' => 'read', 'quote_param' => 'nope'],
            'GET probe/absent-p' => ['estimate_management', 'R', 'batch' => 'read', 'project_param' => 'nope'],
            'PUT probe/w/{id}' => ['estimate_management', 'O', 'batch' => 'write', 'quote_param' => 'id'],
        ]]);

        Route::middleware([StartSession::class, SubstituteBindings::class, RbacAudit::class])->group(function () {
            Route::get('probe/q/{id}', fn () => 'ran');
            Route::get('probe/qm/{quote}', fn (Quote $quote) => 'ran');
            Route::get('probe/p/{id}', fn () => 'ran');
            Route::get('probe/pm/{project}', fn (Project $project) => 'ran');
            Route::get('probe/none', fn () => 'ran');
            Route::get('probe/absent-q', fn () => 'ran');
            Route::get('probe/absent-p', fn () => 'ran');
            Route::put('probe/w/{id}', fn () => 'ran');
        });
    }

    private function noCheckPermission(): void
    {
        $this->mock(PermissionService::class, fn ($m) => $m->shouldReceive('checkPermission')->never());
    }

    private function lastRow(): AuditLog
    {
        return AuditLog::orderByDesc('id')->firstOrFail();
    }

    // ---- M1-M11 service ---------------------------------------------------------

    public function test_m1_member_is_allowed_on_the_project(): void
    {
        $this->assertTrue($this->svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
        $this->assertTrue($this->svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'F', $this->p1));
    }

    public function test_m2_non_member_is_denied_even_with_the_org_role(): void
    {
        $this->assertTrue($this->svc->checkPermission($this->stranger->id, $this->orgA->id, 'estimate_management', 'F'));
        $this->assertFalse($this->svc->checkPermission($this->stranger->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
    }

    public function test_m3_member_of_p1_is_denied_on_p2(): void
    {
        $this->assertFalse($this->svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'R', $this->p2));
    }

    public function test_m4_member_row_with_another_org_id_is_denied(): void
    {
        // multi is a P1 member with an org A row; checked in org B (where P1 is not) it is denied.
        $this->assertFalse($this->svc->checkPermission($this->multi->id, $this->orgB->id, 'estimate_management', 'R', $this->p1));
        // and a row filed under org B for a project that lives in org A grants nothing in org A.
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->multi->id)->update(['org_id' => $this->orgB->id]);
        $this->assertFalse($this->svc->checkPermission($this->multi->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
    }

    public function test_m5_row_org_differing_from_project_org_grants_nothing_in_either_org(): void
    {
        // multi holds roles in A and B. Re-file the P1 row under org B (P1 lives in A), inserted directly:
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->multi->id)->update(['org_id' => $this->orgB->id]);

        $this->assertFalse($this->svc->checkPermission($this->multi->id, $this->orgB->id, 'estimate_management', 'R', $this->p1), 'org B: projects.org_id is A');
        $this->assertFalse($this->svc->checkPermission($this->multi->id, $this->orgA->id, 'estimate_management', 'R', $this->p1), 'org A: row org is B');
    }

    public function test_m6_inactive_row_is_denied(): void
    {
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->update(['is_active' => false]);

        $this->assertFalse($this->svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
    }

    public function test_m7_legacy_quote_only_row_never_grants_even_on_an_id_collision(): void
    {
        // Legacy row on quote id 3 (= Q2, in P1). Project id 3 is P3 (org B) and quote id 1 (Q0) equals P1's id.
        $this->assertSame($this->p3, $this->q2);
        $this->assertSame($this->p1, $this->q0);
        $this->legacyMember($this->q2, $this->stranger, $this->orgA);
        $this->legacyMember($this->q0, $this->stranger, $this->orgA);

        foreach ([$this->p1, $this->p2, $this->p3, $this->q0, $this->q2] as $id) {
            $this->assertFalse($this->svc->checkPermission($this->stranger->id, $this->orgA->id, 'estimate_management', 'R', $id), "legacy row must not grant project $id");
        }
        // the legacy row is real: a project row for the same numeric id does grant
        $this->member($this->p1, $this->stranger, $this->orgA);
        $this->assertTrue($this->svc->checkPermission($this->stranger->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
    }

    public function test_m8_soft_deleted_project_denies_a_member(): void
    {
        $this->assertTrue($this->svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);

        $this->assertFalse($this->svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
    }

    public function test_m9_wrong_role_is_denied_even_for_a_member(): void
    {
        $this->assertTrue($this->svc->checkPermission($this->viewer->id, $this->orgA->id, 'estimate_management', 'R', $this->p1));
        $this->assertFalse($this->svc->checkPermission($this->viewer->id, $this->orgA->id, 'estimate_management', 'O', $this->p1));
        $this->assertFalse($this->svc->checkPermission($this->viewer->id, $this->orgA->id, 'estimate_management', 'F', $this->p1));
        $this->assertFalse($this->svc->checkPermission($this->super->id, $this->orgA->id, 'estimate_management', 'R', $this->p1), 'superintendent has no estimate_management');
    }

    public function test_m10_delegate_is_checked_against_the_principals_membership(): void
    {
        app(DelegationService::class)->grant(
            grantedBy: $this->est->id, fromUserId: $this->est->id, toUserId: $this->stranger->id, orgId: $this->orgA->id,
            startsAt: Carbon::now()->subMinute(), expiresAt: Carbon::now()->addHour(),
        );

        $this->assertTrue($this->svc->checkPermission($this->stranger->id, $this->orgA->id, 'estimate_management', 'R', $this->p1), 'principal is a P1 member');
        $this->assertFalse($this->svc->checkPermission($this->stranger->id, $this->orgA->id, 'estimate_management', 'R', $this->p2), 'principal is not a P2 member');
    }

    public function test_m11_no_project_id_keeps_the_org_level_check(): void
    {
        $this->assertTrue($this->svc->checkPermission($this->stranger->id, $this->orgA->id, 'estimate_management', 'F'));
        $this->assertFalse($this->svc->checkPermission($this->stranger->id, $this->orgB->id, 'estimate_management', 'R'));
        $this->assertFalse($this->svc->checkPermission($this->viewer->id, $this->orgA->id, 'estimate_management', 'F'));
    }

    public function test_project_in_another_org_denies_the_member_of_the_other_org(): void
    {
        $this->assertFalse($this->svc->checkPermission($this->est->id, $this->orgA->id, 'estimate_management', 'R', $this->p3), 'wrong org: P3 lives in B');
        $this->assertTrue($this->svc->checkPermission($this->outsider->id, $this->orgB->id, 'estimate_management', 'R', $this->p3));
        $this->assertFalse($this->svc->checkPermission($this->outsider->id, $this->orgB->id, 'estimate_management', 'R', $this->p1));
    }

    // ---- A1-A11 middleware ------------------------------------------------------

    public function test_a1_member_on_a_scalar_quote_route_is_allowed_with_no_audit_row(): void
    {
        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $this->actingAs($this->est)->get("probe/q/{$this->q1}")->assertOk()->assertSee('ran');
        }
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a2_non_member_in_audit_mode_passes_through_with_a_full_would_block_row(): void
    {
        $this->setMode('audit');

        $this->actingAs($this->stranger)->get("probe/q/{$this->q1}")->assertOk()->assertSee('ran');

        $row = $this->lastRow();
        $this->assertSame('would_block', $row->outcome);
        $this->assertSame('no_grant_or_not_project_member', $row->reason);
        $this->assertSame($this->p1, (int) $row->project_id, 'project_id is the projects.id');
        $this->assertSame($this->q1, (int) $row->quote_id, 'quote_id is the quotes.id');
        $this->assertSame($this->orgA->id, (int) $row->org_id);
        $this->assertSame('estimate_management', $row->permission_group);
    }

    public function test_a2_non_member_in_enforce_mode_gets_json_403_and_a_blocked_row(): void
    {
        $this->setMode('enforce');

        $this->actingAs($this->stranger)->getJson("probe/q/{$this->q1}")->assertForbidden()->assertJson(['rbac_error' => true]);

        $row = $this->lastRow();
        $this->assertSame('blocked', $row->outcome);
        $this->assertSame($this->p1, (int) $row->project_id);
        $this->assertSame($this->q1, (int) $row->quote_id);
    }

    public function test_a2_non_member_in_enforce_mode_gets_302_back_for_a_non_json_request(): void
    {
        $this->setMode('enforce');

        $r = $this->actingAs($this->stranger)->from('/previous')->get("probe/q/{$this->q1}");

        $r->assertStatus(302)->assertRedirect('/previous')->assertSessionHas('rbac_denied');
        $this->assertSame('blocked', $this->lastRow()->outcome);
    }

    #[DataProvider('modes')]
    public function test_a3_null_project_quote_is_unresolved_and_never_reaches_check_permission(string $mode): void
    {
        $this->setMode($mode);
        $this->noCheckPermission();

        $r = $this->actingAs($this->owner)->getJson("probe/q/{$this->q0}");

        $mode === 'audit' ? $r->assertOk() : $r->assertForbidden();
        $row = $this->lastRow();
        $this->assertSame('project_unresolved', $row->reason);
        $this->assertSame($mode === 'audit' ? 'would_block' : 'blocked', $row->outcome);
        $this->assertSame($this->q0, (int) $row->quote_id);
        $this->assertNull($row->project_id);
    }

    #[DataProvider('modes')]
    public function test_a4_non_existent_quote_id_is_unresolved(string $mode): void
    {
        $this->setMode($mode);
        $this->noCheckPermission();

        $r = $this->actingAs($this->est)->getJson('probe/q/99999');

        $mode === 'audit' ? $r->assertOk() : $r->assertForbidden();
        $row = $this->lastRow();
        $this->assertSame('project_unresolved', $row->reason);
        $this->assertSame(99999, (int) $row->quote_id);
        $this->assertNull($row->project_id);
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public function test_a5_soft_deleted_quote_still_resolves_to_its_project(): void
    {
        $this->setMode('audit');
        DB::table('quotes')->where('id', $this->q1)->update(['deleted_at' => now()]);

        $this->actingAs($this->est)->get("probe/q/{$this->q1}")->assertOk();
        $this->assertSame(0, AuditLog::count(), 'member of the quote\'s project is allowed');

        $this->actingAs($this->stranger)->get("probe/q/{$this->q1}")->assertOk();
        $row = $this->lastRow();
        $this->assertSame('no_grant_or_not_project_member', $row->reason, 'resolved to the project, not project_unresolved');
        $this->assertSame($this->p1, (int) $row->project_id);
    }

    public function test_a6_bound_quote_model_resolves_without_a_second_quote_query(): void
    {
        $this->setMode('audit');
        $lookups = 0;
        DB::listen(function ($q) use (&$lookups) {
            if (str_starts_with($q->sql, 'select "project_id" from "quotes"')) {
                $lookups++;
            }
        });

        $this->actingAs($this->stranger)->get("probe/qm/{$this->q1}")->assertOk();

        $this->assertSame(0, $lookups);
        $row = $this->lastRow();
        $this->assertSame($this->q1, (int) $row->quote_id);
        $this->assertSame($this->p1, (int) $row->project_id);
        $this->assertSame('no_grant_or_not_project_member', $row->reason);

        $this->actingAs($this->est)->get("probe/qm/{$this->q1}")->assertOk();
        $this->assertSame(1, AuditLog::count());
    }

    public function test_a6b_bound_quote_model_with_null_project_is_unresolved(): void
    {
        $this->setMode('enforce');
        $this->noCheckPermission();

        $this->actingAs($this->owner)->getJson("probe/qm/{$this->q0}")->assertForbidden();

        $this->assertSame('project_unresolved', $this->lastRow()->reason);
    }

    public function test_a7_project_param_resolves_a_scalar_and_a_bound_project_model(): void
    {
        $this->setMode('audit');

        $this->actingAs($this->est)->get("probe/p/{$this->p1}")->assertOk();
        $this->actingAs($this->est)->get("probe/pm/{$this->p1}")->assertOk();
        $this->assertSame(0, AuditLog::count(), 'member allowed by scalar and by model');

        $this->actingAs($this->stranger)->get("probe/p/{$this->p1}")->assertOk();
        $scalar = $this->lastRow();
        $this->actingAs($this->stranger)->get("probe/pm/{$this->p1}")->assertOk();
        $model = $this->lastRow();

        foreach ([$scalar, $model] as $row) {
            $this->assertSame($this->p1, (int) $row->project_id);
            $this->assertNull($row->quote_id, 'project_param routes carry no quote id');
            $this->assertSame('no_grant_or_not_project_member', $row->reason);
        }
    }

    public function test_a7b_well_formed_project_id_of_a_missing_project_is_no_membership(): void
    {
        $this->setMode('audit');

        $this->actingAs($this->est)->get('probe/p/99999')->assertOk();

        $row = $this->lastRow();
        $this->assertSame('no_grant_or_not_project_member', $row->reason);
        $this->assertSame(99999, (int) $row->project_id);
    }

    public static function badIds(): array
    {
        $cases = [];
        foreach (['quote' => 'probe/q/', 'project' => 'probe/p/'] as $kind => $base) {
            foreach (['abc', '0', '-5', '1.5', '1e3'] as $bad) {
                foreach (['audit', 'enforce'] as $mode) {
                    $cases["$kind $bad $mode"] = [$base.$bad, $mode];
                }
            }
        }
        foreach (['probe/absent-q', 'probe/absent-p'] as $uri) {
            foreach (['audit', 'enforce'] as $mode) {
                $cases["$uri $mode"] = [$uri, $mode];
            }
        }

        return $cases;
    }

    #[DataProvider('badIds')]
    public function test_a8_non_numeric_or_absent_id_is_unresolved_in_both_modes(string $uri, string $mode): void
    {
        $this->setMode($mode);
        $this->noCheckPermission();

        $json = $this->actingAs($this->est)->getJson($uri);
        $mode === 'audit' ? $json->assertOk() : $json->assertForbidden();

        $row = $this->lastRow();
        $this->assertSame('project_unresolved', $row->reason);
        $this->assertSame($mode === 'audit' ? 'would_block' : 'blocked', $row->outcome);
        $this->assertNull($row->project_id);
        $this->assertNull($row->quote_id);

        if ($mode === 'enforce') {
            $this->actingAs($this->est)->from('/previous')->get($uri)->assertStatus(302)->assertRedirect('/previous');
        }
    }

    public function test_a8b_a_zero_id_is_never_checked_as_project_zero(): void
    {
        $this->setMode('audit');
        DB::table('project_members')->insert([
            'project_id' => null, 'quote_id' => null, 'user_id' => $this->est->id, 'org_id' => $this->orgA->id,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->est)->get('probe/p/abc')->assertOk();

        $this->assertSame('project_unresolved', $this->lastRow()->reason);
    }

    public function test_a9_no_org_takes_precedence_and_still_records_the_quote_id(): void
    {
        $this->setMode('audit');
        $orgless = $this->mkUser();

        $this->actingAs($orgless)->get("probe/q/{$this->q0}")->assertOk();
        $noOrgNullProject = $this->lastRow();
        $this->actingAs($orgless)->get("probe/q/{$this->q1}")->assertOk();
        $noOrg = $this->lastRow();

        $this->assertSame('no_org', $noOrgNullProject->reason, 'no_org wins over project_unresolved');
        $this->assertSame($this->q0, (int) $noOrgNullProject->quote_id);
        $this->assertSame('no_org', $noOrg->reason);
        $this->assertSame($this->q1, (int) $noOrg->quote_id);
        $this->assertSame($this->p1, (int) $noOrg->project_id);
        $this->assertNull($noOrg->org_id);
    }

    public function test_a10_audit_row_carries_quote_id_for_quote_routes_only(): void
    {
        $this->setMode('audit');

        $this->actingAs($this->stranger)->get("probe/q/{$this->q2}")->assertOk();
        $quoteRow = $this->lastRow();
        $this->actingAs($this->stranger)->get("probe/p/{$this->p2}")->assertOk();
        $projectRow = $this->lastRow();
        $this->actingAs($this->viewer)->put("probe/w/{$this->q1}")->assertOk();
        $writeRow = $this->lastRow();
        DB::table('user_org_roles')->where('user_id', $this->stranger->id)->update(['is_active' => false]);
        $this->actingAs($this->stranger)->get('probe/none')->assertOk();
        $unscoped = $this->lastRow();

        $this->assertSame([$this->q2, $this->p1], [(int) $quoteRow->quote_id, (int) $quoteRow->project_id]);
        $this->assertSame([null, $this->p2], [$projectRow->quote_id, (int) $projectRow->project_id]);
        $this->assertSame([$this->q1, $this->p1], [(int) $writeRow->quote_id, (int) $writeRow->project_id]);
        $this->assertSame('O', $writeRow->required_level);
        $this->assertNull($unscoped->quote_id);
        $this->assertNull($unscoped->project_id);
    }

    public function test_a10b_wrong_role_on_a_scoped_route_records_no_grant_or_not_project_member(): void
    {
        $this->setMode('audit');

        $this->actingAs($this->super)->get("probe/q/{$this->q1}")->assertOk();

        $row = $this->lastRow();
        $this->assertSame('no_grant_or_not_project_member', $row->reason);
        $this->assertSame([$this->q1, $this->p1], [(int) $row->quote_id, (int) $row->project_id]);
    }

    public function test_a10c_wrong_org_user_is_denied_on_a_quote_of_another_org(): void
    {
        $this->setMode('enforce');

        $this->actingAs($this->outsider)->getJson("probe/q/{$this->q1}")->assertForbidden();
        $this->actingAs($this->est)->getJson("probe/q/{$this->q4}")->assertForbidden();

        $this->assertSame(2, AuditLog::count());
    }

    public function test_multi_org_user_is_checked_in_the_org_the_session_selects(): void
    {
        $this->setMode('audit');

        $this->actingAs($this->multi)->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get("probe/q/{$this->q1}")->assertOk();
        $this->assertSame(0, AuditLog::count(), 'member of P1 in org A');

        $this->actingAs($this->multi)->withSession([config('rbac.current_org_session_key') => $this->orgB->id])
            ->get("probe/q/{$this->q1}")->assertOk();
        $row = $this->lastRow();
        $this->assertSame($this->orgB->id, (int) $row->org_id);
        $this->assertSame('no_grant_or_not_project_member', $row->reason, 'P1 lives in A, so org B has no membership');
    }

    public function test_org_tie_mismatch_between_member_row_and_project_denies_at_the_middleware(): void
    {
        $this->setMode('enforce');
        DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->update(['org_id' => $this->orgB->id]);

        $this->actingAs($this->est)->getJson("probe/q/{$this->q1}")->assertForbidden();
    }

    public function test_a11_rbac_audit_and_current_org_agree_on_the_org(): void
    {
        $key = config('rbac.current_org_session_key');
        $this->setMode('audit');
        $orgless = $this->mkUser();
        $stale = $this->mkUser($this->orgA, 'estimator');
        $this->assignRole($stale, $this->orgB, 'estimator', active: false);

        $cases = [
            'valid session org' => [$this->multi, [$key => $this->orgB->id], $this->orgB->id],
            'stale session org' => [$stale, [$key => $this->orgB->id], $this->orgA->id],
            'no session' => [$this->multi, [], $this->orgA->id],
            'no orgs' => [$orgless, [], null],
        ];

        foreach ($cases as $label => [$user, $session, $expected]) {
            AuditLog::query()->delete();
            $this->withSession($session)->actingAs($user);
            $this->assertSame($expected, CurrentOrg::id($user->id), "$label: CurrentOrg");

            // a NULL-project quote always audits, so the row shows which org the middleware used
            $this->actingAs($user)->withSession($session)->get("probe/q/{$this->q0}")->assertOk();
            $row = $this->lastRow();
            $this->assertSame($expected, $row->org_id === null ? null : (int) $row->org_id, "$label: RbacAudit");
            $this->flushSession();
        }
    }
}

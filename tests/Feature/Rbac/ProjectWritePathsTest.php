<?php

namespace Tests\Feature\Rbac;

use App\Models\Project;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\ProjectMember;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Phase 4 (PHASE4.md sections 2-6): project CRUD, project-keyed membership, quote creation inside a
 * project, crosswalk by project, org-admin add member, the workspace 301 and IDOR on nested routes.
 * Every test runs in audit and in enforce mode. Denials are JSON: enforce answers 403 from the
 * middleware; audit lets the request through, so the controller scoping answers (404 / 403).
 */
class ProjectWritePathsTest extends ProjectTestCase
{
    /** organization_admin in A (project_management F, estimate_management R), member of P1 */
    private User $admin;
    /** project_engineer in A (project_management O, estimate_management O), member of P1 */
    private User $eng;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('saved_list_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->timestamps();
        });

        $this->admin = $this->mkUser($this->orgA, 'organization_admin');
        $this->eng = $this->mkUser($this->orgA, 'project_engineer');
        $this->member($this->p1, $this->admin, $this->orgA);
        $this->member($this->p1, $this->eng, $this->orgA);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    // ---- helpers ----------------------------------------------------------------

    private function req(User $u, string $method, string $uri, array $data = [], array $session = [])
    {
        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function anon(string $method, string $uri)
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, $uri);
    }

    private function orgSession(int $orgId): array
    {
        return [config('rbac.current_org_session_key') => $orgId];
    }

    /** A project in org A that $est administers (member) and nobody else is in. */
    private function freshProject(string $name = 'Fresh'): int
    {
        $id = $this->mkProject($this->orgA, $this->est, $name);
        $this->member($id, $this->est, $this->orgA);

        return $id;
    }

    private function customerFor(User $u, int $id = 1): int
    {
        DB::table('customers')->insert(['id' => $id, 'user_id' => $u->id, 'company_name' => "C$id", 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function crosswalkRow(?int $projectId, int $orgId, string $code, ?int $quoteId = null): int
    {
        return (int) DB::table('plan_crosswalk')->insertGetId([
            'org_id' => $orgId, 'project_id' => $projectId, 'quote_id' => $quoteId, 'plan_line_code' => $code,
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function activeMember(int $projectId, int $userId): bool
    {
        return DB::table('project_members')->where('project_id', $projectId)->where('user_id', $userId)->where('is_active', true)->exists();
    }

    // ---- projects index / list / show ------------------------------------------

    #[DataProvider('modes')]
    public function test_index_and_list_show_only_the_projects_the_user_is_a_member_of(string $mode): void
    {
        $this->setMode($mode);
        $this->mkProject($this->orgA, $this->owner, 'trashed', now()->toDateTimeString());

        $cases = [
            [$this->est, [$this->p1]],
            [$this->owner, [$this->p1, $this->p2]],
            [$this->stranger, []],
            [$this->outsider, [$this->p3]],
        ];
        foreach ($cases as [$user, $expected]) {
            $this->actingAs($user);
            $ids = $this->get('projects')->assertOk()->viewData('projects')->pluck('id')->all();
            sort($ids);
            $this->assertSame($expected, $ids, "index for user {$user->id}");

            $listed = array_column($this->req($user, 'GET', 'projects/list')->assertOk()->json('projects'), 'id');
            sort($listed);
            $this->assertSame($expected, $listed, "list for user {$user->id}");
        }
    }

    #[DataProvider('modes')]
    public function test_index_and_list_deny_a_user_without_an_org_and_anonymous_visitors(string $mode): void
    {
        $this->setMode($mode);
        $none = $this->mkUser();

        if ($mode === 'enforce') {
            $this->req($none, 'GET', 'projects')->assertForbidden();
            $this->req($none, 'GET', 'projects/list')->assertForbidden();
        } else {
            $this->assertSame([], $this->req($none, 'GET', 'projects/list')->assertOk()->json('projects'));
        }
        $this->anon('GET', 'projects/list')->assertUnauthorized();
    }

    #[DataProvider('modes')]
    public function test_show_allows_members_and_denies_wrong_org_non_member_and_trashed(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;

        $this->req($this->est, 'GET', "projects/{$this->p1}")->assertOk();
        $this->req($this->viewer, 'GET', "projects/{$this->p1}")->assertOk();
        $this->req($this->outsider, 'GET', "projects/{$this->p1}")->assertStatus($denied);
        $this->req($this->stranger, 'GET', "projects/{$this->p1}")->assertStatus($denied);
        $this->req($this->est, 'GET', "projects/{$this->p3}")->assertStatus($denied);
        $this->req($this->est, 'GET', "projects/{$this->p2}")->assertStatus($denied);
        $this->anon('GET', "projects/{$this->p1}")->assertUnauthorized();

        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);
        $this->req($this->est, 'GET', 'projects/'.$this->p1)->assertNotFound();
    }

    public function test_show_in_enforce_mode_logs_a_blocked_row_with_the_project_id(): void
    {
        $this->setMode('enforce');

        $this->req($this->stranger, 'GET', "projects/{$this->p1}")->assertForbidden()->assertJson(['rbac_error' => true]);

        $row = AuditLog::firstOrFail();
        $this->assertSame([$this->p1, 'no_grant_or_not_project_member', 'blocked'], [(int) $row->project_id, $row->reason, $row->outcome]);
    }

    public function test_show_in_audit_mode_logs_would_block_and_the_controller_still_404s(): void
    {
        $this->req($this->stranger, 'GET', "projects/{$this->p1}")->assertNotFound();

        $row = AuditLog::firstOrFail();
        $this->assertSame([$this->p1, 'would_block'], [(int) $row->project_id, $row->outcome]);
    }

    // ---- store ------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_store_creates_the_project_and_enrols_the_creator(string $mode): void
    {
        $this->setMode($mode);

        $r = $this->req($this->est, 'POST', 'projects', [
            'name' => 'New job', 'status' => 'awarded', 'address' => '1 Main St', 'bid_due_at' => '2026-12-01 10:00:00',
            'org_id' => $this->orgB->id, 'created_by' => $this->owner->id,
        ])->assertCreated();

        $project = Project::findOrFail($r->json('project_id'));
        $this->assertSame([$this->orgA->id, $this->est->id, 'awarded', 'New job'], [(int) $project->org_id, (int) $project->created_by, $project->status, $project->name], 'org_id and created_by inputs ignored');
        $rows = DB::table('project_members')->where('project_id', $project->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame([$this->est->id, $this->orgA->id, $this->est->id, 1], [(int) $rows[0]->user_id, (int) $rows[0]->org_id, (int) $rows[0]->granted_by, (int) $rows[0]->is_active]);
        $this->assertNull($rows[0]->quote_id);
        $this->assertTrue(Project::visibleTo($this->est->id, $this->orgA->id)->whereKey($project->id)->exists(), 'creator can open it at once');
        $this->assertFalse(Project::visibleTo($this->stranger->id, $this->orgA->id)->whereKey($project->id)->exists());
    }

    #[DataProvider('modes')]
    public function test_store_creates_the_project_in_the_validated_current_org(string $mode): void
    {
        $this->setMode($mode);

        $id = $this->req($this->multi, 'POST', 'projects', ['name' => 'In B', 'status' => 'active'], $this->orgSession($this->orgB->id))->assertCreated()->json('project_id');
        $this->assertSame($this->orgB->id, (int) DB::table('projects')->where('id', $id)->value('org_id'));
        $this->assertSame($this->orgB->id, (int) DB::table('project_members')->where('project_id', $id)->value('org_id'));

        $stale = $this->req($this->multi, 'POST', 'projects', ['name' => 'Stale', 'status' => 'active'], $this->orgSession(99999))->assertCreated()->json('project_id');
        $this->assertSame($this->orgA->id, (int) DB::table('projects')->where('id', $stale)->value('org_id'), 'a stale session org falls back to the first active org');
    }

    #[DataProvider('modes')]
    public function test_store_is_one_transaction_so_a_failed_enrol_leaves_no_project(string $mode): void
    {
        $this->setMode($mode);
        $before = DB::table('projects')->count();
        ProjectMember::creating(function () {
            throw new \RuntimeException('enrol failed');
        });

        $this->req($this->est, 'POST', 'projects', ['name' => 'Half', 'status' => 'active'])->assertStatus(500);

        ProjectMember::flushEventListeners();
        $this->assertSame($before, DB::table('projects')->count());
        $this->assertNull(DB::table('projects')->where('name', 'Half')->first());
    }

    #[DataProvider('modes')]
    public function test_store_validates_name_and_status(string $mode): void
    {
        $this->setMode($mode);
        $before = DB::table('projects')->count();

        foreach ([['status' => 'active'], ['name' => 'x'], ['name' => 'x', 'status' => 'bogus'], ['name' => 'x', 'status' => 'active', 'bid_due_at' => 'not a date']] as $payload) {
            $this->req($this->est, 'POST', 'projects', $payload)->assertStatus(422);
        }
        $this->assertSame($before, DB::table('projects')->count());
        foreach (Project::STATUSES as $status) {
            $this->req($this->est, 'POST', 'projects', ['name' => "s $status", 'status' => $status])->assertCreated();
        }
    }

    public function test_store_denies_a_wrong_role_and_a_user_without_an_org_in_enforce(): void
    {
        $this->setMode('enforce');
        $before = DB::table('projects')->count();

        $this->req($this->viewer, 'POST', 'projects', ['name' => 'x', 'status' => 'active'])->assertForbidden()->assertJson(['rbac_error' => true]);
        $this->req($this->super, 'POST', 'projects', ['name' => 'x', 'status' => 'active'])->assertForbidden();
        $this->req($this->mkUser(), 'POST', 'projects', ['name' => 'x', 'status' => 'active'])->assertForbidden();
        $this->anon('POST', 'projects')->assertUnauthorized();
        $this->assertSame($before, DB::table('projects')->count());
    }

    public function test_store_without_an_org_is_refused_in_audit_too(): void
    {
        $before = DB::table('projects')->count();

        $this->req($this->mkUser(), 'POST', 'projects', ['name' => 'x', 'status' => 'active'])->assertForbidden();
        $this->assertSame($before, DB::table('projects')->count());
    }

    // ---- update -----------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_update_allows_o_and_ignores_org_and_creator_inputs(string $mode): void
    {
        $this->setMode($mode);
        $payload = ['name' => 'Renamed', 'status' => 'on_hold', 'address' => 'New addr', 'org_id' => $this->orgB->id, 'created_by' => $this->outsider->id];

        foreach ([$this->est, $this->eng] as $user) {
            $this->req($user, 'PUT', "projects/{$this->p1}", $payload)->assertOk();
            $row = DB::table('projects')->where('id', $this->p1)->first();
            $this->assertSame(['Renamed', 'on_hold', 'New addr', $this->orgA->id, $this->owner->id], [$row->name, $row->status, $row->address, (int) $row->org_id, (int) $row->created_by]);
        }
        $this->req($this->est, 'PUT', "projects/{$this->p1}", ['name' => '', 'status' => 'active'])->assertStatus(422);
        $this->req($this->est, 'PUT', "projects/{$this->p1}", ['name' => 'x', 'status' => 'nope'])->assertStatus(422);
    }

    public function test_update_denies_a_role_below_o_in_enforce(): void
    {
        $this->setMode('enforce');

        $this->req($this->viewer, 'PUT', "projects/{$this->p1}", ['name' => 'Hacked', 'status' => 'active'])->assertForbidden()->assertJson(['rbac_error' => true]);
        $this->req($this->super, 'PUT', "projects/{$this->p1}", ['name' => 'Hacked', 'status' => 'active'])->assertForbidden();
        $this->assertSame('P1', DB::table('projects')->where('id', $this->p1)->value('name'));
    }

    #[DataProvider('modes')]
    public function test_update_denies_wrong_org_and_non_member_and_trashed(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;
        $payload = ['name' => 'Hacked', 'status' => 'lost'];

        $this->req($this->outsider, 'PUT', "projects/{$this->p1}", $payload)->assertStatus($denied);
        $this->req($this->stranger, 'PUT', "projects/{$this->p1}", $payload)->assertStatus($denied);
        $this->req($this->est, 'PUT', "projects/{$this->p2}", $payload)->assertStatus($denied);
        $this->req($this->est, 'PUT', "projects/{$this->p3}", $payload)->assertStatus($denied);
        $this->legacyMember($this->q1, $this->stranger, $this->orgA);
        $this->req($this->stranger, 'PUT', "projects/{$this->p1}", $payload)->assertStatus($denied);
        $this->assertSame(['P1', 'P2', 'P3'], [DB::table('projects')->where('id', $this->p1)->value('name'), DB::table('projects')->where('id', $this->p2)->value('name'), DB::table('projects')->where('id', $this->p3)->value('name')]);

        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);
        $this->req($this->est, 'PUT', "projects/{$this->p1}", $payload)->assertNotFound();
    }

    // ---- destroy ----------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_destroy_soft_deletes_an_empty_project_for_f(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();

        $this->req($this->est, 'DELETE', "projects/$id")->assertOk();

        $this->assertNotNull(DB::table('projects')->where('id', $id)->value('deleted_at'), 'soft delete, the row stays');
        $this->req($this->est, 'GET', "projects/$id")->assertNotFound();
        $this->assertSame(1, DB::table('project_members')->where('project_id', $id)->count(), 'members are left in place');
    }

    #[DataProvider('modes')]
    public function test_destroy_is_422_while_a_live_quote_exists_and_allowed_once_they_are_trashed(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();
        $q = $this->mkQuote($this->est, $id);

        $this->req($this->est, 'DELETE', "projects/$id")->assertStatus(422);
        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));

        DB::table('quotes')->where('id', $q)->update(['deleted_at' => now()]);
        $this->req($this->est, 'DELETE', "projects/$id")->assertOk();
        $this->assertNotNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    public function test_destroy_checks_quotes_and_deletes_inside_one_transaction(): void
    {
        $id = $this->freshProject();
        $levels = [];
        DB::listen(function ($q) use (&$levels) {
            $sql = strtolower($q->sql);
            if (str_contains($sql, 'from "quotes"') || str_contains($sql, 'from `quotes`') || (str_starts_with($sql, 'update') && str_contains($sql, 'projects') && str_contains($sql, 'deleted_at'))) {
                $levels[] = DB::transactionLevel();
            }
        });

        $this->req($this->est, 'DELETE', "projects/$id")->assertOk();

        $this->assertCount(2, $levels, 'the quote existence check and the soft delete');
        $this->assertSame([true, true], array_map(fn ($l) => $l >= 1, $levels));
    }

    public function test_destroy_denies_a_role_below_f_in_enforce(): void
    {
        $this->setMode('enforce');
        $id = $this->freshProject();
        foreach ([$this->eng, $this->viewer] as $user) {
            $this->member($id, $user, $this->orgA);
        }

        $this->req($this->eng, 'DELETE', "projects/$id")->assertForbidden()->assertJson(['rbac_error' => true]);
        $this->req($this->viewer, 'DELETE', "projects/$id")->assertForbidden();
        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    #[DataProvider('modes')]
    public function test_destroy_denies_wrong_org_non_member_and_trashed(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;
        $id = $this->freshProject();

        $this->req($this->outsider, 'DELETE', "projects/$id")->assertStatus($denied);
        $this->req($this->stranger, 'DELETE', "projects/$id")->assertStatus($denied);
        $this->req($this->est, 'DELETE', "projects/{$this->p3}")->assertStatus($denied);
        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
        $this->assertNull(DB::table('projects')->where('id', $this->p3)->value('deleted_at'));

        DB::table('projects')->where('id', $id)->update(['deleted_at' => now()]);
        $this->req($this->est, 'DELETE', "projects/$id")->assertNotFound();
    }

    /**
     * DEFECT P4-QA-1 (reported in REVIEW.md, unfixed): in audit mode ProjectController::store/update/destroy do
     * no permission check of their own, so a project member holding only R can rename or delete the project
     * (enforce mode blocks it in the middleware). Remove the skip once the Architect's plan closes it.
     */
    public function test_audit_mode_role_gate_on_project_update_and_destroy(): void
    {
        $this->setMode('audit');
        $id = $this->freshProject();
        $this->member($id, $this->viewer, $this->orgA);

        $this->req($this->viewer, 'PUT', "projects/$id", ['name' => 'Hacked', 'status' => 'active'])->assertForbidden();
        $this->req($this->viewer, 'DELETE', "projects/$id")->assertForbidden();
        $this->assertSame('Fresh', DB::table('projects')->where('id', $id)->value('name'));
        $this->assertNull(DB::table('projects')->where('id', $id)->value('deleted_at'));
    }

    public function test_enforce_blocks_are_logged_with_the_mapped_group_level_and_project(): void
    {
        $this->setMode('enforce');
        $rowId = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->viewer->id)->value('id');
        $cases = [
            ['POST', "projects/{$this->p1}/members", ['user_id' => $this->stranger->id], $this->eng, 'project_management', 'F'],
            ['DELETE', "projects/{$this->p1}/members/$rowId", [], $this->eng, 'project_management', 'F'],
            ['DELETE', "projects/{$this->p1}", [], $this->eng, 'project_management', 'F'],
            ['PUT', "projects/{$this->p1}", ['name' => 'x', 'status' => 'active'], $this->viewer, 'project_management', 'O'],
            ['POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'Z'], $this->eng, 'estimate_management', 'F'],
            ['POST', "projects/{$this->p1}/quotes", ['customer_id' => 1], $this->super, 'estimate_management', 'S'],
        ];

        foreach ($cases as [$method, $uri, $data, $user, $group, $level]) {
            AuditLog::query()->delete();
            $this->req($user, $method, $uri, $data)->assertForbidden()->assertJson(['rbac_error' => true, 'group' => $group, 'level' => $level]);
            $row = AuditLog::firstOrFail();
            $this->assertSame([$group, $level, $this->p1, 'blocked'], [$row->permission_group, $row->required_level, (int) $row->project_id, $row->outcome], "$method $uri");
        }
    }

    // ---- members store ----------------------------------------------------------

    #[DataProvider('modes')]
    public function test_members_store_enrols_and_reports_added_already_and_reactivated(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();

        $r = $this->req($this->est, 'POST', "projects/$id/members", ['user_id' => $this->stranger->id])->assertOk();
        $this->assertSame('Member added to project.', $r->json('message'));
        $row = DB::table('project_members')->where('project_id', $id)->where('user_id', $this->stranger->id)->first();
        $this->assertSame([$this->orgA->id, $this->est->id, 1], [(int) $row->org_id, (int) $row->granted_by, (int) $row->is_active]);
        $this->assertNull($row->quote_id);

        $r = $this->req($this->est, 'POST', "projects/$id/members", ['user_id' => $this->stranger->id])->assertOk();
        $this->assertSame('User is already a member of this project.', $r->json('message'));
        $this->assertSame(1, DB::table('project_members')->where('project_id', $id)->where('user_id', $this->stranger->id)->count());

        DB::table('project_members')->where('id', $row->id)->update(['is_active' => false]);
        $r = $this->req($this->est, 'POST', "projects/$id/members", ['user_id' => $this->stranger->id])->assertOk();
        $this->assertSame('Member re-activated on project.', $r->json('message'));
        $this->assertTrue($this->activeMember($id, $this->stranger->id));
        $this->assertSame(1, DB::table('project_members')->where('project_id', $id)->where('user_id', $this->stranger->id)->count(), 're-activated, not duplicated');
    }

    #[DataProvider('modes')]
    public function test_members_store_allows_an_f_holder_who_is_not_the_creator(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->admin, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->stranger->id])->assertOk();
        $this->assertTrue($this->activeMember($this->p1, $this->stranger->id));
    }

    #[DataProvider('modes')]
    public function test_members_store_refuses_a_target_outside_the_project_org(string $mode): void
    {
        $this->setMode($mode);
        $inactiveHere = $this->mkUser();
        $this->assignRole($inactiveHere, $this->orgA, 'estimator', false);

        foreach ([$this->outsider, $inactiveHere, $this->mkUser()] as $target) {
            $this->req($this->est, 'POST', "projects/{$this->p1}/members", ['user_id' => $target->id])->assertForbidden();
            $this->assertFalse(DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $target->id)->exists());
        }
        $this->req($this->est, 'POST', "projects/{$this->p1}/members", ['user_id' => 987654])->assertStatus(422);
        $this->req($this->est, 'POST', "projects/{$this->p1}/members", [])->assertStatus(422);
    }

    #[DataProvider('modes')]
    public function test_members_store_denies_a_level_below_f_in_both_modes(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->eng, $this->viewer, $this->super] as $user) {
            $this->req($user, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->stranger->id])->assertForbidden();
        }
        $this->assertFalse($this->activeMember($this->p1, $this->stranger->id));
    }

    #[DataProvider('modes')]
    public function test_members_store_denies_a_non_member_and_the_wrong_org(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;
        $newcomer = $this->mkUser($this->orgA, 'estimator');

        // stranger holds estimator (F) in org A but is not on P1; outsider holds F in org B
        $this->req($this->stranger, 'POST', "projects/{$this->p1}/members", ['user_id' => $newcomer->id])->assertStatus($denied);
        $this->req($this->outsider, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->outsider->id])->assertStatus($denied);
        $this->req($this->est, 'POST', "projects/{$this->p2}/members", ['user_id' => $newcomer->id])->assertStatus($denied);
        $this->req($this->est, 'POST', "projects/{$this->p3}/members", ['user_id' => $newcomer->id])->assertStatus($denied);
        $this->legacyMember($this->q1, $this->stranger, $this->orgA);
        $this->req($this->stranger, 'POST', "projects/{$this->p1}/members", ['user_id' => $newcomer->id])->assertStatus($denied);

        foreach ([$this->p1, $this->p2, $this->p3] as $p) {
            $this->assertFalse($this->activeMember($p, $newcomer->id));
        }
        $this->assertFalse($this->activeMember($this->p1, $this->outsider->id));
    }

    #[DataProvider('modes')]
    public function test_members_store_ignores_org_and_project_inputs_and_refuses_trashed_and_anonymous(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->est, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->stranger->id, 'org_id' => $this->orgB->id, 'project_id' => $this->p2])->assertOk();
        $row = DB::table('project_members')->where('user_id', $this->stranger->id)->first();
        $this->assertSame([$this->p1, $this->orgA->id], [(int) $row->project_id, (int) $row->org_id]);

        $this->anon('POST', "projects/{$this->p1}/members")->assertUnauthorized();
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);
        $this->req($this->est, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->mkUser($this->orgA, 'estimator')->id])->assertNotFound();
    }

    // ---- members destroy --------------------------------------------------------

    #[DataProvider('modes')]
    public function test_members_destroy_deactivates_only_that_row(string $mode): void
    {
        $this->setMode($mode);
        $rowId = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->viewer->id)->value('id');

        $this->req($this->est, 'DELETE', "projects/{$this->p1}/members/$rowId")->assertOk();

        $this->assertFalse($this->activeMember($this->p1, $this->viewer->id));
        $this->assertSame(1, DB::table('project_members')->where('id', $rowId)->count(), 'the row is kept, only deactivated');
        foreach ([$this->est, $this->owner, $this->admin] as $u) {
            $this->assertTrue($this->activeMember($this->p1, $u->id));
        }
        $this->assertTrue($this->activeMember($this->p2, $this->owner->id), 'other projects are untouched');
        $this->req($this->viewer, 'GET', "projects/{$this->p1}")->assertStatus($mode === 'enforce' ? 403 : 404);
    }

    #[DataProvider('modes')]
    public function test_members_destroy_allows_removing_the_creator_and_the_last_active_member(string $mode): void
    {
        $this->setMode($mode);
        $creatorRow = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->owner->id)->value('id');
        $this->assertSame($this->owner->id, (int) DB::table('projects')->where('id', $this->p1)->value('created_by'));

        $this->req($this->est, 'DELETE', "projects/{$this->p1}/members/$creatorRow")->assertOk();
        $this->assertFalse($this->activeMember($this->p1, $this->owner->id), 'the creator can be removed');

        $solo = $this->freshProject('Solo');
        $soloRow = (int) DB::table('project_members')->where('project_id', $solo)->where('user_id', $this->est->id)->value('id');
        $this->req($this->est, 'DELETE', "projects/$solo/members/$soloRow")->assertOk();
        $this->assertSame(0, DB::table('project_members')->where('project_id', $solo)->where('is_active', true)->count(), 'the last active member can be removed');
        $this->assertFalse(Project::visibleTo($this->est->id, $this->orgA->id)->whereKey($solo)->exists());
    }

    #[DataProvider('modes')]
    public function test_members_destroy_denies_a_level_below_f_wrong_org_and_non_member(string $mode): void
    {
        $this->setMode($mode);
        $rowId = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->viewer->id)->value('id');
        $uri = "projects/{$this->p1}/members/$rowId";
        $denied = $mode === 'enforce' ? 403 : 404;

        foreach ([$this->eng, $this->viewer, $this->super] as $user) {
            $this->req($user, 'DELETE', $uri)->assertForbidden();
        }
        $this->req($this->stranger, 'DELETE', $uri)->assertStatus($denied);
        $this->req($this->outsider, 'DELETE', $uri)->assertStatus($denied);
        $this->anon('DELETE', $uri)->assertUnauthorized();
        $this->assertTrue($this->activeMember($this->p1, $this->viewer->id));
    }

    #[DataProvider('modes')]
    public function test_members_destroy_idor_a_row_of_another_project_is_404_and_untouched(string $mode): void
    {
        $this->setMode($mode);
        $ownerOnP2 = (int) DB::table('project_members')->where('project_id', $this->p2)->where('user_id', $this->owner->id)->value('id');
        $outsiderOnP3 = (int) DB::table('project_members')->where('project_id', $this->p3)->where('user_id', $this->outsider->id)->value('id');
        $legacy = $this->legacyMember($this->q1, $this->stranger, $this->orgA);

        // est administers P1 only: every foreign or legacy row addressed through P1 is a 404, never a deactivation
        foreach ([$ownerOnP2, $outsiderOnP3, $legacy, 999999] as $rowId) {
            $this->req($this->est, 'DELETE', "projects/{$this->p1}/members/$rowId")->assertNotFound();
        }
        $this->assertSame(0, DB::table('project_members')->where('is_active', false)->count(), 'nothing was deactivated');
    }

    // ---- quotes inside a project ------------------------------------------------

    #[DataProvider('modes')]
    public function test_quote_store_creates_the_quote_inside_the_route_project_only(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->customerFor($this->est);

        $id = $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c, 'project_id' => $this->p2, 'user_id' => $this->owner->id])
            ->assertOk()->json('quote_id');

        $q = DB::table('quotes')->where('id', $id)->first();
        $this->assertSame([$this->p1, $this->est->id], [(int) $q->project_id, (int) $q->user_id], 'project from the route, owner is the caller, request project_id ignored');
    }

    #[DataProvider('modes')]
    public function test_quote_store_ignores_a_quote_id_or_foreign_project_id_in_the_body(string $mode): void
    {
        $this->setMode($mode);
        $c = $this->customerFor($this->est);

        foreach ([$this->q1, $this->p3, 0, 'abc'] as $bogus) {
            $id = $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c, 'project_id' => $bogus])->assertOk()->json('quote_id');
            $this->assertSame($this->p1, (int) DB::table('quotes')->where('id', $id)->value('project_id'));
        }
    }

    #[DataProvider('modes')]
    public function test_quote_store_404s_or_403s_for_an_invisible_project_and_writes_nothing(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;
        $c = $this->customerFor($this->est);
        $before = DB::table('quotes')->count();

        $this->req($this->est, 'POST', "projects/{$this->p2}/quotes", ['customer_id' => $c])->assertStatus($denied);     // same org, not a member
        $this->req($this->est, 'POST', "projects/{$this->p3}/quotes", ['customer_id' => $c])->assertStatus($denied);     // other org
        $this->req($this->outsider, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c])->assertStatus($denied);
        $this->req($this->stranger, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c])->assertStatus($denied);
        $this->legacyMember($this->q1, $this->stranger, $this->orgA);
        $this->req($this->stranger, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c])->assertStatus($denied);
        $this->req($this->est, 'POST', 'projects/999999/quotes', ['customer_id' => $c])->assertNotFound();
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);
        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c])->assertNotFound();
        $this->assertSame($before, DB::table('quotes')->count());
    }

    public function test_quote_store_denies_a_role_below_s_in_enforce(): void
    {
        $this->setMode('enforce');
        $before = DB::table('quotes')->count();

        // superintendent has no estimate_management; a project member cannot create an estimate through the project
        $this->req($this->super, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => 1])->assertForbidden()->assertJson(['rbac_error' => true]);
        $this->assertSame($before, DB::table('quotes')->count());
    }

    public function test_quote_store_denies_a_viewer_with_r_in_enforce(): void
    {
        $this->setMode('enforce');
        $c = $this->customerFor($this->viewer);
        $before = DB::table('quotes')->count();

        $this->req($this->viewer, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c])->assertForbidden()->assertJson(['rbac_error' => true]);
        $this->assertSame($before, DB::table('quotes')->count());
    }

    /**
     * DEFECT P4-QA-2 / P4-QA-3 (reported in REVIEW.md, unfixed): in audit mode QuoteController::store/createFromList and
     * OrgAdminController::addProjectMember/removeProjectMember have no role check of their own, so an R-only member
     * creates estimates and manages project membership; enforce mode blocks all of it in the middleware.
     */
    public function test_audit_mode_role_gate_on_quote_store_and_org_admin_membership(): void
    {
        $this->setMode('audit');
        $c = $this->customerFor($this->viewer);
        $rowId = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->est->id)->value('id');

        $this->req($this->viewer, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $c])->assertForbidden();
        $this->req($this->viewer, 'POST', route('org-admin.projects.members.store'), ['project_id' => $this->p1, 'user_id' => $this->stranger->id])->assertForbidden();
        $this->req($this->viewer, 'DELETE', route('org-admin.projects.members.destroy', $rowId))->assertForbidden();
        $this->assertTrue($this->activeMember($this->p1, $this->est->id));
        $this->assertFalse($this->activeMember($this->p1, $this->stranger->id));
    }

    #[DataProvider('modes')]
    public function test_org_admin_remove_member_refuses_another_orgs_row_and_removes_its_own(string $mode): void
    {
        $this->setMode($mode);
        $orgB = (int) DB::table('project_members')->where('project_id', $this->p3)->where('user_id', $this->outsider->id)->value('id');
        $orgA = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->viewer->id)->value('id');

        $this->req($this->est, 'DELETE', route('org-admin.projects.members.destroy', $orgB))->assertForbidden();
        $this->assertTrue($this->activeMember($this->p3, $this->outsider->id));

        $this->actingAs($this->est)->delete(route('org-admin.projects.members.destroy', $orgA))->assertSessionHas('success', 'Member removed from project.');
        $this->assertFalse($this->activeMember($this->p1, $this->viewer->id));
    }

    #[DataProvider('modes')]
    public function test_quote_store_refuses_a_customer_that_is_not_the_callers(string $mode): void
    {
        $this->setMode($mode);
        $foreign = $this->customerFor($this->owner);
        $before = DB::table('quotes')->count();

        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $foreign])->assertNotFound();
        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", [])->assertStatus(422);
        $this->assertSame($before, DB::table('quotes')->count());
    }

    #[DataProvider('modes')]
    public function test_quote_store_refuses_a_saved_list_that_is_not_the_callers(string $mode): void
    {
        $this->setMode($mode);
        $customer = $this->customerFor($this->est);
        $theirs = (int) DB::table('saved_lists')->insertGetId(['user_id' => $this->owner->id, 'name' => 'Theirs', 'created_at' => now(), 'updated_at' => now()]);
        $mine = (int) DB::table('saved_lists')->insertGetId(['user_id' => $this->est->id, 'name' => 'Mine', 'created_at' => now(), 'updated_at' => now()]);
        $before = DB::table('quotes')->count();

        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $customer, 'saved_list_id' => $theirs])->assertNotFound();
        $this->assertSame($before, DB::table('quotes')->count());

        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes", ['customer_id' => $customer, 'saved_list_id' => $mine])->assertSuccessful();
        $this->assertSame($mine, (int) DB::table('quotes')->orderByDesc('id')->value('saved_list_id'));
    }

    #[DataProvider('modes')]
    public function test_create_from_list_creates_in_the_route_project_and_refuses_foreign_lists(string $mode): void
    {
        $this->setMode($mode);
        $mine = (int) DB::table('saved_lists')->insertGetId(['user_id' => $this->est->id, 'name' => 'Mine', 'created_at' => now(), 'updated_at' => now()]);
        $theirs = (int) DB::table('saved_lists')->insertGetId(['user_id' => $this->owner->id, 'name' => 'Theirs', 'created_at' => now(), 'updated_at' => now()]);

        $id = $this->req($this->est, 'POST', "projects/{$this->p1}/quotes/create-from-list/$mine", ['project_id' => $this->p2])->assertOk()->json('quote_id');
        $q = DB::table('quotes')->where('id', $id)->first();
        $this->assertSame([$this->p1, $this->est->id, $mine], [(int) $q->project_id, (int) $q->user_id, (int) $q->saved_list_id]);

        $before = DB::table('quotes')->count();
        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes/create-from-list/$theirs")->assertNotFound();   // IDOR on {listId}
        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes/create-from-list/999999")->assertNotFound();
        $this->assertSame($before, DB::table('quotes')->count());
    }

    #[DataProvider('modes')]
    public function test_create_from_list_denies_invisible_projects_and_wrong_roles(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;
        $mine = (int) DB::table('saved_lists')->insertGetId(['user_id' => $this->est->id, 'name' => 'Mine', 'created_at' => now(), 'updated_at' => now()]);
        $before = DB::table('quotes')->count();

        $this->req($this->est, 'POST', "projects/{$this->p2}/quotes/create-from-list/$mine")->assertStatus($denied);
        $this->req($this->est, 'POST', "projects/{$this->p3}/quotes/create-from-list/$mine")->assertStatus($denied);
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);
        $this->req($this->est, 'POST', "projects/{$this->p1}/quotes/create-from-list/$mine")->assertNotFound();
        $this->assertSame($before, DB::table('quotes')->count());
    }

    public function test_create_from_list_denies_a_role_without_estimate_management_in_enforce(): void
    {
        $this->setMode('enforce');
        $list = (int) DB::table('saved_lists')->insertGetId(['user_id' => $this->super->id, 'name' => 'S', 'created_at' => now(), 'updated_at' => now()]);

        $this->req($this->super, 'POST', "projects/{$this->p1}/quotes/create-from-list/$list")->assertForbidden();
    }

    public function test_no_route_can_create_a_null_project_quote(): void
    {
        $this->assertFalse(Route::has('quotes.store'));
        $this->assertFalse(Route::has('quotes.create-from-list'));
        $this->assertFalse(Route::has('plan-crosswalk.store'));
        $this->assertArrayNotHasKey('POST quotes', config('route_permission_map'));
        $this->assertArrayNotHasKey('POST quotes/create-from-list/{listId}', config('route_permission_map'));
        $this->assertArrayNotHasKey('POST plan-crosswalk', config('route_permission_map'));

        foreach (['audit', 'enforce'] as $mode) {
            $this->setMode($mode);
            $c = $this->customerFor($this->est, $mode === 'audit' ? 1 : 2);
            $before = DB::table('quotes')->count();
            $this->req($this->est, 'POST', 'quotes', ['customer_id' => $c, 'project_id' => $this->p1])->assertStatus(405);
            $this->req($this->est, 'POST', 'quotes/create-from-list/1', ['project_id' => $this->p1])->assertStatus(404);
            $this->req($this->est, 'POST', 'plan-crosswalk', ['plan_line_code' => 'X', 'project_id' => $this->p1])->assertStatus(405);
            $this->assertSame($before, DB::table('quotes')->count());
        }
        $this->assertSame(0, DB::table('quotes')->whereNull('project_id')->where('id', '>', $this->q4)->count());
    }

    // ---- crosswalk by project ---------------------------------------------------

    #[DataProvider('modes')]
    public function test_crosswalk_store_sets_project_id_and_leaves_quote_id_null(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->est, 'POST', "projects/{$this->p1}/crosswalk", [
            'plan_line_code' => 'L-1', 'manufacturer_part_number' => 'MPN', 'description' => 'd',
            'project_id' => $this->p2, 'quote_id' => $this->q1, 'org_id' => $this->orgB->id,
        ])->assertStatus(302);

        $row = DB::table('plan_crosswalk')->where('plan_line_code', 'L-1')->first();
        $this->assertSame([$this->p1, null, $this->orgA->id, $this->est->id], [(int) $row->project_id, $row->quote_id, (int) $row->org_id, (int) $row->created_by]);
    }

    #[DataProvider('modes')]
    public function test_crosswalk_store_ignores_a_quote_id_sent_as_project_id(string $mode): void
    {
        $this->setMode($mode);

        // quote 3 (Q2) has the id of P3, another org's project; the route project wins
        $this->req($this->est, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'IDC', 'project_id' => $this->q2])->assertStatus(302);

        $this->assertSame($this->p1, (int) DB::table('plan_crosswalk')->where('plan_line_code', 'IDC')->value('project_id'));
        $this->assertSame(0, DB::table('plan_crosswalk')->where('project_id', $this->p3)->count());
    }

    #[DataProvider('modes')]
    public function test_crosswalk_store_duplicate_code_is_422_per_project_only(string $mode): void
    {
        $this->setMode($mode);

        $this->req($this->owner, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'DUP'])->assertStatus(302);
        $this->req($this->owner, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'DUP'])->assertStatus(422);
        $this->assertSame(1, DB::table('plan_crosswalk')->where('project_id', $this->p1)->where('plan_line_code', 'DUP')->count());

        $this->req($this->owner, 'POST', "projects/{$this->p2}/crosswalk", ['plan_line_code' => 'DUP'])->assertStatus(302);
        $this->assertSame(2, DB::table('plan_crosswalk')->where('plan_line_code', 'DUP')->count(), 'same code in another project is fine');
        $this->req($this->est, 'POST', "projects/{$this->p1}/crosswalk", [])->assertStatus(422);
    }

    #[DataProvider('modes')]
    public function test_crosswalk_update_duplicate_code_is_422_per_project_only(string $mode): void
    {
        $this->setMode($mode);
        $a = $this->crosswalkRow($this->p1, $this->orgA->id, 'A');
        $this->crosswalkRow($this->p1, $this->orgA->id, 'B');
        $other = $this->crosswalkRow($this->p2, $this->orgA->id, 'C');

        $this->req($this->owner, 'PUT', "plan-crosswalk/$a", ['plan_line_code' => 'B'])->assertStatus(422);
        $this->assertSame('A', DB::table('plan_crosswalk')->where('id', $a)->value('plan_line_code'));

        $this->req($this->owner, 'PUT', "plan-crosswalk/$a", ['plan_line_code' => 'A', 'description' => 'same code, own row'])->assertStatus(302);
        $this->req($this->owner, 'PUT', "plan-crosswalk/$a", ['plan_line_code' => 'C'])->assertStatus(302);
        $this->assertSame('C', DB::table('plan_crosswalk')->where('id', $a)->value('plan_line_code'), 'same code in another project is fine');
        $this->assertSame('C', DB::table('plan_crosswalk')->where('id', $other)->value('plan_line_code'));
    }

    #[DataProvider('modes')]
    public function test_crosswalk_store_requires_estimate_management_f(string $mode): void
    {
        $this->setMode($mode);

        // R holder, O holder and a member without estimate_management all fail on the level (403 from the middleware in enforce, the controller in audit)
        foreach ([$this->viewer, $this->eng, $this->super, $this->admin] as $user) {
            $this->req($user, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'NOPE'])->assertForbidden();
        }
        $this->assertSame(0, DB::table('plan_crosswalk')->where('plan_line_code', 'NOPE')->count());
    }

    #[DataProvider('modes')]
    public function test_crosswalk_store_denies_wrong_org_non_member_and_trashed(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;

        $this->req($this->outsider, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'X'])->assertStatus($denied);
        $this->req($this->stranger, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'X'])->assertStatus($denied);
        $this->req($this->est, 'POST', "projects/{$this->p2}/crosswalk", ['plan_line_code' => 'X'])->assertStatus($denied);
        $this->req($this->est, 'POST', "projects/{$this->p3}/crosswalk", ['plan_line_code' => 'X'])->assertStatus($denied);
        $this->legacyMember($this->q1, $this->stranger, $this->orgA);
        $this->req($this->stranger, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'X'])->assertStatus($denied);
        DB::table('projects')->where('id', $this->p1)->update(['deleted_at' => now()]);
        $this->req($this->est, 'POST', "projects/{$this->p1}/crosswalk", ['plan_line_code' => 'X'])->assertNotFound();
        $this->assertSame(0, DB::table('plan_crosswalk')->count());
    }

    #[DataProvider('modes')]
    public function test_crosswalk_update_and_destroy_allowed_for_f_on_the_rows_project(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->crosswalkRow($this->p1, $this->orgA->id, 'U1');

        $this->req($this->est, 'PUT', "plan-crosswalk/$row", ['plan_line_code' => 'U1-EDITED'])->assertStatus(302);
        $this->assertSame('U1-EDITED', DB::table('plan_crosswalk')->where('id', $row)->value('plan_line_code'));

        $this->req($this->est, 'DELETE', "plan-crosswalk/$row")->assertStatus(302);
        $this->assertNull(DB::table('plan_crosswalk')->where('id', $row)->first());
    }

    #[DataProvider('modes')]
    public function test_crosswalk_update_and_destroy_deny_a_level_below_f(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->crosswalkRow($this->p1, $this->orgA->id, 'LV');

        foreach ([$this->viewer, $this->eng, $this->admin] as $user) {
            $this->req($user, 'PUT', "plan-crosswalk/$row", ['plan_line_code' => 'HACK'])->assertForbidden();
            $this->req($user, 'DELETE', "plan-crosswalk/$row")->assertForbidden();
        }
        $this->assertSame('LV', DB::table('plan_crosswalk')->where('id', $row)->value('plan_line_code'));
    }

    #[DataProvider('modes')]
    public function test_crosswalk_idor_rows_outside_the_callers_visible_projects_are_404(string $mode): void
    {
        $this->setMode($mode);
        $inP2 = $this->crosswalkRow($this->p2, $this->orgA->id, 'P2');                       // same org, est is not a member
        $inP3 = $this->crosswalkRow($this->p3, $this->orgB->id, 'P3');                       // other org
        $legacy = $this->crosswalkRow(null, $this->orgA->id, 'LEG', $this->q1);               // quote-only legacy row
        $mismatch = $this->crosswalkRow($this->p1, $this->orgB->id, 'MIS');                   // project of org A, row filed under org B
        $trashedProject = $this->mkProject($this->orgA, $this->owner, 'gone', now()->toDateTimeString());
        $this->member($trashedProject, $this->est, $this->orgA);
        $inTrashed = $this->crosswalkRow($trashedProject, $this->orgA->id, 'TR');

        foreach ([$inP2, $inP3, $legacy, $mismatch, $inTrashed, 999999] as $row) {
            $this->req($this->est, 'PUT', "plan-crosswalk/$row", ['plan_line_code' => 'HACK'])->assertNotFound();
            $this->req($this->est, 'DELETE', "plan-crosswalk/$row")->assertNotFound();
        }
        $this->assertSame(['P2', 'P3', 'LEG', 'MIS', 'TR'], DB::table('plan_crosswalk')->orderBy('id')->pluck('plan_line_code')->all());
    }

    // ---- org-admin add member (recovery route) ---------------------------------

    #[DataProvider('modes')]
    public function test_org_admin_add_member_goes_through_enrol_and_reports_the_flags(string $mode): void
    {
        $this->setMode($mode);
        $id = $this->freshProject();
        $post = fn () => $this->actingAs($this->est)->from('/back')->post(route('org-admin.projects.members.store'), ['project_id' => $id, 'user_id' => $this->stranger->id]);

        $post()->assertRedirect('/back')->assertSessionHas('success', 'Member added to project.');
        $row = DB::table('project_members')->where('project_id', $id)->where('user_id', $this->stranger->id)->first();
        $this->assertSame([$this->orgA->id, $this->est->id, 1], [(int) $row->org_id, (int) $row->granted_by, (int) $row->is_active]);
        $this->assertNull($row->quote_id, 'never a legacy quote-keyed row');

        $post()->assertSessionHas('success', 'User is already a member of this project.');
        DB::table('project_members')->where('id', $row->id)->update(['is_active' => false]);
        $post()->assertSessionHas('success', 'Member re-activated on project.');
        $this->assertSame(1, DB::table('project_members')->where('project_id', $id)->where('user_id', $this->stranger->id)->count());
    }

    #[DataProvider('modes')]
    public function test_org_admin_add_member_refuses_foreign_projects_and_foreign_targets(string $mode): void
    {
        $this->setMode($mode);
        $url = route('org-admin.projects.members.store');
        $trashed = $this->mkProject($this->orgA, $this->est, 'gone', now()->toDateTimeString());
        $newcomer = $this->mkUser($this->orgA, 'estimator');

        $this->req($this->est, 'POST', $url, ['project_id' => $this->p3, 'user_id' => $newcomer->id])->assertNotFound();
        $this->req($this->est, 'POST', $url, ['project_id' => $trashed, 'user_id' => $newcomer->id])->assertNotFound();
        $this->req($this->est, 'POST', $url, ['project_id' => 999999, 'user_id' => $newcomer->id])->assertNotFound();
        $this->req($this->est, 'POST', $url, ['project_id' => $this->p1, 'user_id' => $this->outsider->id])->assertForbidden();
        $this->req($this->est, 'POST', $url, ['project_id' => $this->p1, 'user_id' => 987654])->assertStatus(422);
        $this->req($this->est, 'POST', $url, ['user_id' => $newcomer->id])->assertStatus(422);
        $this->req($this->est, 'POST', $url, ['quote_id' => $this->q1, 'user_id' => $newcomer->id])->assertStatus(422);
        $this->assertSame(0, DB::table('project_members')->where('user_id', $newcomer->id)->count());
        $this->assertSame(0, DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->outsider->id)->count());
    }

    public function test_org_admin_add_member_denies_a_level_below_f_in_enforce(): void
    {
        $this->setMode('enforce');
        $url = route('org-admin.projects.members.store');

        foreach ([$this->viewer, $this->eng, $this->super] as $user) {
            $this->req($user, 'POST', $url, ['project_id' => $this->p1, 'user_id' => $this->stranger->id])->assertForbidden()->assertJson(['rbac_error' => true]);
        }
        $this->req($this->outsider, 'POST', $url, ['project_id' => $this->p1, 'user_id' => $this->stranger->id])->assertNotFound();
        $this->assertFalse($this->activeMember($this->p1, $this->stranger->id));
    }

    // ---- workspace 301 ----------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_old_quote_workspace_url_redirects_301_to_the_project_only_when_visible(string $mode): void
    {
        $this->setMode($mode);
        $denied = $mode === 'enforce' ? 403 : 404;

        $this->req($this->est, 'GET', "projects/{$this->q1}/workspace")->assertStatus(301)->assertRedirect(route('projects.show', $this->p1));
        $this->req($this->est, 'GET', "projects/{$this->q3}/workspace")->assertStatus($denied);        // quote of P2, est not a member
        $this->req($this->est, 'GET', "projects/{$this->q4}/workspace")->assertStatus($denied);        // quote of another org's project
        $this->req($this->outsider, 'GET', "projects/{$this->q1}/workspace")->assertStatus($denied);
        $this->req($this->stranger, 'GET', "projects/{$this->q1}/workspace")->assertStatus($denied);
        $this->req($this->est, 'GET', "projects/{$this->q0}/workspace")->assertStatus($denied);        // NULL project: unresolved
        $this->req($this->est, 'GET', 'projects/999999/workspace')->assertNotFound();
        $this->anon('GET', "projects/{$this->q1}/workspace")->assertUnauthorized();
    }

    // ---- project page panels ----------------------------------------------------

    public function test_the_project_page_carries_per_panel_permission_flags_and_only_its_own_rows(): void
    {
        $this->crosswalkRow($this->p1, $this->orgA->id, 'ON-P1');
        $this->crosswalkRow($this->p2, $this->orgA->id, 'ON-P2');
        $this->crosswalkRow(null, $this->orgA->id, 'LEGACY', $this->q1);

        $est = $this->actingAs($this->est)->get("projects/{$this->p1}")->assertOk();
        $this->assertSame(['ON-P1'], $est->viewData('crosswalkEntries')->pluck('plan_line_code')->all());
        $this->assertEqualsCanonicalizing([$this->q1, $this->q2], $est->viewData('quotes')->pluck('id')->all());
        $this->assertSame([true, true, true, true, true, true], [
            $est->viewData('canReadEstimates'), $est->viewData('canCreateEstimates'), $est->viewData('canManageCrosswalk'),
            $est->viewData('canManageMembers'), $est->viewData('canUpdateProject'), $est->viewData('canDeleteProject'),
        ]);

        $viewer = $this->actingAs($this->viewer)->get("projects/{$this->p1}")->assertOk();
        $this->assertSame([true, false, false, false, false, false], [
            $viewer->viewData('canReadEstimates'), $viewer->viewData('canCreateEstimates'), $viewer->viewData('canManageCrosswalk'),
            $viewer->viewData('canManageMembers'), $viewer->viewData('canUpdateProject'), $viewer->viewData('canDeleteProject'),
        ]);
        $this->assertTrue($viewer->viewData('orgMembers')->isEmpty(), 'the org member picker is only for F holders');
    }
}

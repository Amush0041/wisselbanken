<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * W10 amendment (PLAN 10.14.12): the two org-admin member screens look the project up through the ACTING user's
 * own Project::visibleTo, so a delegate of an Owner cannot add, remove or enrol himself on a project he cannot
 * see (V1); plus the Organization Admin / Auditor / Manufacturer Admin behaviour the Verifier found (V4, V6).
 *
 * Fixture (ProjectTestCase): P1, P2 in org A, P3 in org B; Q3 is the estimate of P2. est is a member of P1 only;
 * viewer (viewer_read_only, member of P1) is the delegate; stranger is an org A member of no project.
 */
class W10AmendmentTest extends ProjectTestCase
{
    private User $ownerNm;
    private User $adminNm;
    private User $auditorNm;
    private User $pmNm;
    private User $procNm;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->decimal('total', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('saved_list_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->timestamps();
        });
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($d, $f) => date(str_replace(['%Y', '%m'], ['Y', 'm'], $f), strtotime($d)), 2);
        Storage::fake('public');

        $this->ownerNm = $this->mkUser($this->orgA, 'organization_owner');
        $this->adminNm = $this->mkUser($this->orgA, 'organization_admin');
        $this->auditorNm = $this->mkUser($this->orgA, 'auditor_read_all');
        $this->pmNm = $this->mkUser($this->orgA, 'project_manager');
        $this->procNm = $this->mkUser($this->orgA, 'procurement_manager');

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    // ---- helpers ----------------------------------------------------------------

    private function req(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        $session = $org ? [config('rbac.current_org_session_key') => $org] : [];

        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function addMember(User $actor, int $projectId, User $target)
    {
        return $this->actingAs($actor)->post('org-admin/projects/members', ['project_id' => $projectId, 'user_id' => $target->id]);
    }

    private function removeMember(User $actor, int $memberRowId)
    {
        return $this->actingAs($actor)->delete("org-admin/projects/members/$memberRowId");
    }

    private function rowId(int $projectId, User $u): ?int
    {
        $id = DB::table('project_members')->where('project_id', $projectId)->where('user_id', $u->id)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function active(int $projectId, User $u): bool
    {
        return DB::table('project_members')->where('project_id', $projectId)->where('user_id', $u->id)->where('is_active', true)->exists();
    }

    private function delegate(User $from, User $to, bool $active = true, $starts = null, $expires = null): Delegation
    {
        return Delegation::create([
            'from_user_id' => $from->id, 'to_user_id' => $to->id, 'org_id' => $this->orgA->id, 'granted_by' => $from->id,
            'starts_at' => $starts ?? now()->subHour(), 'expires_at' => $expires ?? now()->addDay(), 'is_active' => $active,
        ]);
    }

    private function projectIds(User $u): array
    {
        $ids = array_map('intval', array_column($this->req($u, 'GET', 'projects/list')->assertOk()->json('projects'), 'id'));
        sort($ids);

        return $ids;
    }

    private function logActions(): array
    {
        return DB::table('project_member_logs')->orderBy('id')->pluck('action')->all();
    }

    private function newOrgMember(string $role = 'estimator'): User
    {
        return $this->mkUser($this->orgA, $role);
    }

    // ---- A. a delegate of a non-member Owner (V1) -------------------------------

    #[DataProvider('modes')]
    public function test_a_a_delegate_of_an_owner_cannot_touch_the_members_of_a_project_he_cannot_see(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->viewer;
        $this->delegate($this->ownerNm, $d);
        $this->member($this->p2, $this->est, $this->orgA);
        $estRow = $this->rowId($this->p2, $this->est);

        $this->req($d, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->stranger->id])->assertNotFound();
        $this->req($d, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $d->id])->assertNotFound();
        $this->req($d, 'DELETE', "org-admin/projects/members/$estRow")->assertNotFound();

        $this->assertNull($this->rowId($this->p2, $this->stranger));
        $this->assertNull($this->rowId($this->p2, $d));
        $this->assertTrue($this->active($this->p2, $this->est), 'the member row is untouched');
        $this->assertSame([], $this->logActions());

        $outcomes = AuditLog::orderBy('id')->pluck('outcome')->all();
        $this->assertSame(['delegated_action', 'delegated_action', 'delegated_action'], $outcomes, 'the middleware row only: no denial row of its own');

        $this->assertSame([$this->p1], $this->projectIds($d), 'the delegate list is not widened');
    }

    // ---- B. the same delegate on a project he is in ---------------------------------

    #[DataProvider('modes')]
    public function test_b_the_same_delegate_manages_the_members_of_a_project_he_is_in_as_the_principal(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->viewer;
        $this->delegate($this->ownerNm, $d);

        $this->addMember($d, $this->p1, $this->stranger)->assertRedirect()->assertSessionHas('success', 'Member added to project.');
        $this->assertTrue($this->active($this->p1, $this->stranger));

        $this->removeMember($d, $this->rowId($this->p1, $this->stranger))->assertRedirect()->assertSessionHas('success', 'Member removed from project.');
        $this->assertFalse($this->active($this->p1, $this->stranger));

        $this->assertSame(['added', 'removed'], $this->logActions());
        $this->assertSame([$d->id, $d->id], DB::table('project_member_logs')->orderBy('id')->pluck('performed_by')->map(fn ($i) => (int) $i)->all());
        $this->assertSame($d->id, (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->stranger->id)->value('granted_by'));
    }

    // ---- C. Owner and Organization Admin unchanged ------------------------------------

    #[DataProvider('modes')]
    public function test_c_owner_and_organization_admin_still_manage_the_members_of_any_project_of_the_org(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->ownerNm, $this->adminNm] as $actor) {
            $target = $this->newOrgMember();

            $this->addMember($actor, $this->p2, $target)->assertRedirect()->assertSessionHas('success', 'Member added to project.');
            $this->assertTrue($this->active($this->p2, $target));

            $this->removeMember($actor, $this->rowId($this->p2, $target))->assertRedirect()->assertSessionHas('success', 'Member removed from project.');
            $this->assertFalse($this->active($this->p2, $target));
            $this->assertNull($this->rowId($this->p2, $actor), 'the actor is still not a member of P2');
        }
    }

    // ---- D. same-org non-members: 403 became 404 ---------------------------------------

    #[DataProvider('modes')]
    public function test_d_a_same_org_non_member_with_project_management_f_gets_404_and_nothing_is_written(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->est, $this->pmNm] as $actor) {
            $target = $this->newOrgMember();

            $this->req($actor, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $target->id])->assertNotFound();
            $this->assertFalse($this->active($this->p2, $target));
        }

        $this->assertSame([], $this->logActions());
    }

    // ---- E. expired and revoked delegation ------------------------------------------------

    #[DataProvider('modes')]
    public function test_e_an_expired_or_revoked_delegation_leaves_the_delegate_judged_on_his_own_roles(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->viewer;
        $delegation = $this->delegate($this->ownerNm, $d, true, now()->subDays(2), now()->subHour());

        $this->req($d, 'POST', 'org-admin/projects/members', ['project_id' => $this->p1, 'user_id' => $this->stranger->id])->assertForbidden();
        $this->req($d, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->stranger->id])->assertForbidden();

        $delegation->update(['expires_at' => now()->addDay(), 'is_active' => false]);

        $this->req($d, 'POST', 'org-admin/projects/members', ['project_id' => $this->p1, 'user_id' => $this->stranger->id])->assertForbidden();
        $this->req($d, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->stranger->id])->assertForbidden();

        $this->assertNull($this->rowId($this->p1, $this->stranger));
        $this->assertNull($this->rowId($this->p2, $this->stranger));
        $this->assertSame([], $this->logActions());
    }

    // ---- F. an Owner who is the delegate of a member-scoped principal fails closed ---------

    #[DataProvider('modes')]
    public function test_f_an_owner_acting_for_a_member_scoped_principal_is_checked_as_that_principal(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->newOrgMember('organization_owner');
        $this->delegate($this->est, $d);

        $this->req($d, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->stranger->id])->assertForbidden();
        $this->assertNull($this->rowId($this->p2, $this->stranger));

        $this->addMember($d, $this->p1, $this->stranger)->assertRedirect()->assertSessionHas('success', 'Member added to project.');
        $this->assertTrue($this->active($this->p1, $this->stranger));

        $this->member($this->p2, $this->viewer, $this->orgA);
        $this->req($d, 'DELETE', 'org-admin/projects/members/'.$this->rowId($this->p2, $this->viewer))->assertForbidden();
        $this->assertTrue($this->active($this->p2, $this->viewer));

        $this->removeMember($d, $this->rowId($this->p1, $this->stranger))->assertRedirect()->assertSessionHas('success', 'Member removed from project.');
        $this->assertFalse($this->active($this->p1, $this->stranger));
    }

    // ---- H. V6: GET org-admin/projects ----------------------------------------------------------

    #[DataProvider('modes')]
    public function test_h_the_org_admin_projects_page_lists_the_whole_org_for_the_auditor_and_nothing_for_procurement(string $mode): void
    {
        $this->setMode($mode);
        $ids = fn ($response) => $response->viewData('projects')->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $auditor = $this->actingAs($this->auditorNm)->get('org-admin/projects')->assertOk();
        $this->assertSame([$this->p1, $this->p2], $ids($auditor));
        $this->assertFalse($auditor->viewData('canManageProjects'));

        $proc = $this->actingAs($this->procNm)->get('org-admin/projects')->assertOk();
        $this->assertSame([], $ids($proc));
    }

    // ---- I. V6: Organization Admin deletes an empty project --------------------------------------

    #[DataProvider('modes')]
    public function test_i_an_organization_admin_may_delete_an_empty_project_but_not_one_with_an_estimate(string $mode): void
    {
        $this->setMode($mode);

        $empty = (int) $this->req($this->est, 'POST', 'projects', ['name' => 'Empty project', 'status' => 'active'])->assertStatus(201)->json('project_id');

        $this->req($this->adminNm, 'DELETE', "projects/$empty")->assertOk();
        $this->assertNotNull(DB::table('projects')->where('id', $empty)->value('deleted_at'));

        $this->req($this->adminNm, 'DELETE', "projects/{$this->p2}")->assertStatus(422);
        $this->assertNull(DB::table('projects')->where('id', $this->p2)->value('deleted_at'));
    }

    // ---- J. V4: Manufacturer Admin differs between audit and enforce mode --------------------------

    /**
     * Pins the ACCEPTED V4 behaviour (PLAN 10.14.12.4): the rule is keyed on organization_management F alone, so a
     * Manufacturer Admin (no project_management, no estimate_management) sees the project pages in audit mode,
     * where the middleware only logs, and is refused by the middleware in enforce mode. If the human later keys the
     * rule on project_management R as well, this is the test to change.
     */
    #[DataProvider('modes')]
    public function test_j_a_manufacturer_admin_reads_projects_in_audit_mode_and_is_refused_in_enforce_mode(string $mode): void
    {
        $this->setMode($mode);
        $mfr = $this->newOrgMember('manufacturer_admin');

        $this->req($mfr, 'GET', "projects/{$this->p2}")->assertStatus($mode === 'audit' ? 200 : 403);
        $this->req($mfr, 'GET', 'plan-crosswalk')->assertStatus($mode === 'audit' ? 200 : 403);
        $this->req($mfr, 'GET', "quotes/{$this->q3}/details")->assertStatus($mode === 'audit' ? 404 : 403);

        $projects = $this->req($mfr, 'GET', 'projects/list');
        $quotes = $this->req($mfr, 'GET', 'quotes/list');
        if ($mode === 'audit') {
            $projects->assertOk();
            $this->assertSame([$this->p1, $this->p2], collect($projects->json('projects'))->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all());
            $quotes->assertOk();
            $this->assertSame([], $quotes->json('quotes'));
        } else {
            $projects->assertForbidden();
            $quotes->assertForbidden();
        }

        $this->req($mfr, 'GET', 'rfq')->assertOk();
    }
}

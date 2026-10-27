<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * QA edges of the W10 amendment (PLAN 10.14.12, Z7) that W10AmendmentTest does not pin: the project lookup of the two
 * org-admin member screens through the ACTING user's Project::visibleTo, for a soft-deleted project, a former member's
 * own deactivated row, and users who act in a second organization.
 */
class W10AmendmentQaTest extends ProjectTestCase
{
    private User $ownerNm;
    private User $adminNm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerNm = $this->mkUser($this->orgA, 'organization_owner');
        $this->adminNm = $this->mkUser($this->orgA, 'organization_admin');

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function req(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        $session = $org ? [config('rbac.current_org_session_key') => $org] : [];

        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
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

    private function logActions(): array
    {
        return DB::table('project_member_logs')->orderBy('id')->pluck('action')->all();
    }

    #[DataProvider('modes')]
    public function test_q1_a_soft_deleted_project_is_a_404_for_add_and_remove_even_for_its_members_and_org_wide_holders(string $mode): void
    {
        $this->setMode($mode);
        $trashed = $this->mkProject($this->orgA, $this->owner, 'Trashed by QA', now()->toDateTimeString());
        $this->member($trashed, $this->est, $this->orgA);
        $victimRow = $this->member($trashed, $this->viewer, $this->orgA);

        foreach ([$this->ownerNm, $this->adminNm, $this->est] as $actor) {
            $target = $this->mkUser($this->orgA, 'estimator');

            $this->req($actor, 'POST', 'org-admin/projects/members', ['project_id' => $trashed, 'user_id' => $target->id])->assertNotFound();
            $this->assertNull($this->rowId($trashed, $target));

            $this->req($actor, 'DELETE', "org-admin/projects/members/$victimRow")->assertNotFound();
            $this->assertTrue($this->active($trashed, $this->viewer));
        }

        $this->assertSame([], $this->logActions());
        $this->assertSame(0, AuditLog::count(), 'a same-org 404 is not a cross-org probe: no denial row');
    }

    #[DataProvider('modes')]
    public function test_q2_a_former_member_gets_404_on_his_own_deactivated_row_and_cannot_re_enrol_himself(string $mode): void
    {
        $this->setMode($mode);

        $oldRow = $this->member($this->p2, $this->est, $this->orgA, false);
        $this->req($this->est, 'DELETE', "org-admin/projects/members/$oldRow")->assertNotFound();
        $this->req($this->est, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->est->id])->assertNotFound();
        $this->assertFalse($this->active($this->p2, $this->est));

        $delegate = $this->viewer;
        Delegation::create([
            'from_user_id' => $this->ownerNm->id, 'to_user_id' => $delegate->id, 'org_id' => $this->orgA->id, 'granted_by' => $this->ownerNm->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);
        $delegateRow = $this->member($this->p2, $delegate, $this->orgA, false);
        $this->req($delegate, 'DELETE', "org-admin/projects/members/$delegateRow")->assertNotFound();
        $this->req($delegate, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $delegate->id])->assertNotFound();
        $this->assertFalse($this->active($this->p2, $delegate));

        $this->assertSame([], $this->logActions());
    }

    #[DataProvider('modes')]
    public function test_q3_an_owner_of_two_orgs_manages_members_in_the_acting_org_and_probes_the_other_with_404(string $mode): void
    {
        $this->setMode($mode);
        $x = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($x, $this->orgB, 'organization_owner');
        $inA = $this->mkUser($this->orgA, 'estimator');
        $inB = $this->mkUser($this->orgB, 'estimator');
        $a = $this->orgA->id;
        $b = $this->orgB->id;

        $this->req($x, 'POST', 'org-admin/projects/members', ['project_id' => $this->p3, 'user_id' => $inB->id], $b)->assertRedirect();
        $this->assertTrue($this->active($this->p3, $inB));
        $this->req($x, 'DELETE', 'org-admin/projects/members/'.$this->rowId($this->p3, $inB), [], $b)->assertRedirect();
        $this->assertFalse($this->active($this->p3, $inB));

        $this->req($x, 'POST', 'org-admin/projects/members', ['project_id' => $this->p1, 'user_id' => $inA->id], $a)->assertRedirect();
        $this->assertTrue($this->active($this->p1, $inA));
        $this->req($x, 'DELETE', 'org-admin/projects/members/'.$this->rowId($this->p1, $inA), [], $a)->assertRedirect();
        $this->assertFalse($this->active($this->p1, $inA));

        $this->req($x, 'POST', 'org-admin/projects/members', ['project_id' => $this->p3, 'user_id' => $inA->id], $a)->assertNotFound();
        $this->req($x, 'POST', 'org-admin/projects/members', ['project_id' => $this->p1, 'user_id' => $inB->id], $b)->assertNotFound();
        $this->assertNull($this->rowId($this->p3, $inA));
        $this->assertNull($this->rowId($this->p1, $inB));
        $this->assertNull($this->rowId($this->p3, $x), 'the Owner is never enrolled by the screens');
    }

    #[DataProvider('modes')]
    public function test_q4_a_member_scoped_project_manager_of_two_orgs_is_judged_in_the_acting_org(string $mode): void
    {
        $this->setMode($mode);
        $pm = $this->mkUser($this->orgA, 'project_manager');
        $this->assignRole($pm, $this->orgB, 'project_manager');
        $this->member($this->p3, $pm, $this->orgB);
        $inB = $this->mkUser($this->orgB, 'estimator');
        $b = $this->orgB->id;
        $a = $this->orgA->id;

        $this->req($pm, 'POST', 'org-admin/projects/members', ['project_id' => $this->p3, 'user_id' => $inB->id], $b)->assertRedirect();
        $this->assertTrue($this->active($this->p3, $inB));
        $this->req($pm, 'DELETE', 'org-admin/projects/members/'.$this->rowId($this->p3, $inB), [], $b)->assertRedirect();
        $this->assertFalse($this->active($this->p3, $inB));

        $this->req($pm, 'POST', 'org-admin/projects/members', ['project_id' => $this->p3, 'user_id' => $inB->id], $a)->assertNotFound();
        $this->req($pm, 'POST', 'org-admin/projects/members', ['project_id' => $this->p2, 'user_id' => $this->stranger->id], $a)->assertNotFound();
        $this->assertNull($this->rowId($this->p2, $this->stranger));
        $this->assertSame(['added', 'removed'], $this->logActions());
    }
}

<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\ProjectMember;
use App\Models\Rbac\ProjectMemberLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class AuditMembershipLogTest extends ProjectTestCase
{
    private User $adminA;
    private User $newbie;
    private int $p;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminA = $this->mkUser($this->orgA, 'organization_admin');
        $this->newbie = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->p = $this->mkProject($this->orgA, $this->est, 'Fresh');
        $this->member($this->p, $this->est, $this->orgA);
        $this->member($this->p, $this->adminA, $this->orgA);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function logs(): array
    {
        return DB::table('project_member_logs')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    private function assertOneLog(string $action, User $target, ?int $by, ?int $project = null): void
    {
        $rows = $this->logs();
        $this->assertCount(1, $rows);
        $this->assertSame(
            [$this->orgA->id, $project ?? $this->p, $target->id, $action, $by],
            [(int) $rows[0]['org_id'], (int) $rows[0]['project_id'], (int) $rows[0]['target_user_id'], $rows[0]['action'], $rows[0]['performed_by'] === null ? null : (int) $rows[0]['performed_by']]
        );
        $this->assertNotNull($rows[0]['created_at']);
    }

    private function rowId(User $u, ?int $project = null): int
    {
        return (int) DB::table('project_members')->where('project_id', $project ?? $this->p)->where('user_id', $u->id)->value('id');
    }

    private function active(User $u): bool
    {
        return DB::table('project_members')->where('project_id', $this->p)->where('user_id', $u->id)->where('is_active', true)->exists();
    }

    private function addVia(string $how, User $actor, User $target)
    {
        return $how === 'projects'
            ? $this->actingAs($actor)->postJson("projects/{$this->p}/members", ['user_id' => $target->id])
            : $this->actingAs($actor)->post(route('org-admin.projects.members.store'), ['project_id' => $this->p, 'user_id' => $target->id]);
    }

    private function removeVia(string $how, User $actor, int $rowId)
    {
        return $how === 'projects'
            ? $this->actingAs($actor)->deleteJson("projects/{$this->p}/members/$rowId")
            : $this->actingAs($actor)->delete(route('org-admin.projects.members.destroy', $rowId));
    }

    public static function hows(): array
    {
        $o = [];
        foreach (['projects', 'org-admin'] as $h) {
            foreach (['audit', 'enforce'] as $m) {
                $o["$h $m"] = [$h, $m];
            }
        }

        return $o;
    }

    // ---- added / reactivated / no-op ----------------------------------------------

    #[DataProvider('modes')]
    public function test_creator_auto_enrol_logs_added(string $mode): void
    {
        $this->setMode($mode);

        $id = $this->actingAs($this->est)->postJson('projects', ['name' => 'New', 'status' => 'active'])->assertCreated()->json('project_id');

        $this->assertOneLog('added', $this->est, $this->est->id, $id);
    }

    #[DataProvider('hows')]
    public function test_add_logs_one_added_row_then_reactivated_then_nothing_when_already_active(string $how, string $mode): void
    {
        $this->setMode($mode);

        $this->addVia($how, $this->est, $this->newbie)->assertStatus($how === 'projects' ? 200 : 302);
        $this->assertOneLog('added', $this->newbie, $this->est->id);

        $this->addVia($how, $this->est, $this->newbie);
        $this->assertCount(1, $this->logs(), 'already active: no new row');
        $this->assertTrue($this->active($this->newbie));
        $this->assertSame(1, DB::table('project_members')->where('project_id', $this->p)->where('user_id', $this->newbie->id)->count());

        $this->removeVia($how, $this->est, $this->rowId($this->newbie));
        $this->assertSame(['added', 'removed'], array_column($this->logs(), 'action'));

        $this->addVia($how, $this->adminA, $this->newbie);
        $rows = $this->logs();
        $this->assertSame(['added', 'removed', 'reactivated'], array_column($rows, 'action'));
        $this->assertSame($this->adminA->id, (int) $rows[2]['performed_by']);
        $this->assertTrue($this->active($this->newbie));
    }

    #[DataProvider('hows')]
    public function test_removal_logs_one_removed_row_and_removing_an_inactive_member_logs_nothing(string $how, string $mode): void
    {
        $this->setMode($mode);
        $row = $this->member($this->p, $this->newbie, $this->orgA);

        $this->removeVia($how, $this->est, $row);
        $this->assertOneLog('removed', $this->newbie, $this->est->id);
        $this->assertFalse($this->active($this->newbie));

        $this->removeVia($how, $this->est, $row);
        $this->assertCount(1, $this->logs(), 'already inactive: no new row');
    }

    public function test_denied_actions_write_no_log(): void
    {
        $this->setMode('enforce');
        $row = $this->member($this->p, $this->newbie, $this->orgA);
        $this->member($this->p, $this->viewer, $this->orgA);

        $this->actingAs($this->viewer)->postJson("projects/{$this->p}/members", ['user_id' => $this->stranger->id])->assertForbidden();
        $this->actingAs($this->viewer)->deleteJson("projects/{$this->p}/members/$row")->assertForbidden();
        $this->actingAs($this->outsider)->deleteJson("projects/{$this->p}/members/$row");

        $this->assertSame([], $this->logs());
        $this->assertTrue($this->active($this->newbie));
    }

    // ---- race ---------------------------------------------------------------------

    private function raceInsert(bool $active): void
    {
        $fired = false;
        ProjectMember::creating(function () use (&$fired, $active) {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('project_members')->insert([
                'project_id' => $this->p, 'user_id' => $this->newbie->id, 'org_id' => $this->orgA->id,
                'granted_by' => $this->adminA->id, 'granted_at' => now(), 'is_active' => $active,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function test_race_lost_to_an_active_row_logs_nothing(): void
    {
        $this->raceInsert(true);

        $m = ProjectMember::enrol(\App\Models\Project::find($this->p), $this->newbie->id, $this->orgA->id, $this->est->id);

        $this->assertFalse($m->wasRecentlyCreated);
        $this->assertSame([], $this->logs());
    }

    public function test_race_lost_to_an_inactive_row_logs_only_reactivated(): void
    {
        $this->raceInsert(false);

        ProjectMember::enrol(\App\Models\Project::find($this->p), $this->newbie->id, $this->orgA->id, $this->est->id);

        $this->assertOneLog('reactivated', $this->newbie, $this->est->id);
        $this->assertTrue($this->active($this->newbie));
    }

    // ---- rollback / fail soft --------------------------------------------------------

    public function test_a_non_missing_table_log_failure_rolls_the_membership_change_back(): void
    {
        DB::statement('CREATE TRIGGER pml_fail BEFORE INSERT ON project_member_logs BEGIN SELECT RAISE(ABORT, \'boom\'); END');
        $project = \App\Models\Project::find($this->p);
        $row = $this->member($this->p, $this->newbie, $this->orgA);

        try {
            ProjectMember::enrol($project, $this->stranger->id, $this->orgA->id, $this->est->id);
            $this->fail('enrol must rethrow');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
        }
        $this->assertSame(0, DB::table('project_members')->where('project_id', $this->p)->where('user_id', $this->stranger->id)->count(), 'added row rolled back');

        try {
            ProjectMember::find($row)->deactivate($this->est->id);
            $this->fail('deactivate must rethrow');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertTrue($this->active($this->newbie), 'removal rolled back');

        DB::table('project_members')->where('id', $row)->update(['is_active' => false]);
        try {
            ProjectMember::enrol($project, $this->newbie->id, $this->orgA->id, $this->est->id);
            $this->fail('reactivate must rethrow');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertFalse($this->active($this->newbie), 'reactivation rolled back');
    }

    public function test_a_failing_log_over_http_does_not_leave_a_member(): void
    {
        DB::statement('CREATE TRIGGER pml_fail BEFORE INSERT ON project_member_logs BEGIN SELECT RAISE(ABORT, \'boom\'); END');
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->est)->post(route('org-admin.projects.members.store'), ['project_id' => $this->p, 'user_id' => $this->newbie->id]);
            $this->fail('expected exception');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertFalse($this->active($this->newbie));
    }

    #[DataProvider('modes')]
    public function test_a_missing_table_fails_soft_with_a_warning_and_the_flows_still_work(string $mode): void
    {
        $this->setMode($mode);
        Schema::drop('project_member_logs');
        Log::spy();

        $this->addVia('projects', $this->est, $this->newbie)->assertOk();
        $this->assertTrue($this->active($this->newbie));
        $this->removeVia('projects', $this->est, $this->rowId($this->newbie))->assertOk();
        $this->assertFalse($this->active($this->newbie));
        $this->addVia('org-admin', $this->est, $this->newbie);
        $this->assertTrue($this->active($this->newbie));
        $this->removeVia('org-admin', $this->est, $this->rowId($this->newbie));
        $this->assertFalse($this->active($this->newbie));
        $this->actingAs($this->est)->postJson('projects', ['name' => 'NoTable', 'status' => 'active'])->assertCreated();

        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'project_member_logs') && str_contains($m, '2026_09_24_000006'))->atLeast()->times(5);
    }

    // ---- audit page -------------------------------------------------------------------

    private function seedLog(int $org, int $project, User $target, string $action, ?int $by, string $at = null): void
    {
        DB::table('project_member_logs')->insert([
            'org_id' => $org, 'project_id' => $project, 'target_user_id' => $target->id, 'action' => $action,
            'performed_by' => $by, 'created_at' => $at ?? now(),
        ]);
    }

    private function auditPage(User $u, ?int $org = null)
    {
        $s = $org ? [config('rbac.current_org_session_key') => $org] : [];

        return $this->actingAs($u)->withSession($s)->get('org-admin/audit-log');
    }

    #[DataProvider('modes')]
    public function test_audit_page_shows_this_orgs_rows_only_newest_first_with_system_for_null(string $mode): void
    {
        $this->setMode($mode);
        $this->newbie->forceFill(['name' => 'Nora Newbie'])->save();
        $this->outsider->forceFill(['name' => 'Olga Outsider'])->save();
        $this->est->forceFill(['name' => 'Eddie Est'])->save();
        $this->seedLog($this->orgA->id, $this->p, $this->newbie, 'added', $this->est->id, '2026-01-01 10:00:00');
        $this->seedLog($this->orgA->id, $this->p, $this->newbie, 'removed', null, '2026-01-02 10:00:00');
        $this->seedLog($this->orgB->id, $this->p3, $this->outsider, 'added', $this->outsider->id, '2026-01-03 10:00:00');

        $r = $this->auditPage($this->adminA)->assertOk();
        $r->assertSee('Project membership changes')->assertSee('Nora Newbie')->assertSee('Eddie Est')->assertSee('System')->assertSee('Fresh');
        $r->assertDontSee('Olga Outsider')->assertDontSee('P3');
        $this->assertSame(['removed', 'added'], $r->viewData('projectMemberLog')->pluck('action')->all());
        $this->assertSame([$this->orgA->id], $r->viewData('projectMemberLog')->pluck('org_id')->unique()->values()->all());

    }

    #[DataProvider('modes')]
    public function test_another_orgs_auditor_never_sees_these_rows(string $mode): void
    {
        $this->setMode($mode);
        $this->seedLog($this->orgA->id, $this->p, $this->newbie, 'added', $this->est->id);
        $bOwner = $this->mkUser($this->orgB, 'organization_owner');
        $this->outsider->forceFill(['name' => 'Olga Outsider'])->save();
        $this->seedLog($this->orgB->id, $this->p3, $this->outsider, 'added', null);

        $r = $this->auditPage($bOwner)->assertOk();
        $this->assertSame(1, $r->viewData('projectMemberLog')->total());
        $this->assertSame($this->orgB->id, (int) $r->viewData('projectMemberLog')->first()->org_id);
        $r->assertSee('Olga Outsider')->assertDontSee($this->newbie->name);
    }

    #[DataProvider('modes')]
    public function test_a_dual_org_user_sees_only_the_current_orgs_rows(string $mode): void
    {
        $this->setMode($mode);
        $this->seedLog($this->orgA->id, $this->p, $this->newbie, 'added', $this->est->id);
        $this->seedLog($this->orgB->id, $this->p3, $this->outsider, 'added', null);
        $dual = $this->mkUser($this->orgA, 'organization_owner');
        $this->assignRole($dual, $this->orgB, 'organization_owner');

        $a = $this->auditPage($dual, $this->orgA->id)->assertOk()->viewData('projectMemberLog');
        $b = $this->auditPage($dual, $this->orgB->id)->assertOk()->viewData('projectMemberLog');

        $this->assertSame([$this->orgA->id], $a->pluck('org_id')->map(fn ($x) => (int) $x)->all());
        $this->assertSame([$this->orgB->id], $b->pluck('org_id')->map(fn ($x) => (int) $x)->all());
    }

    #[DataProvider('modes')]
    public function test_roles_without_audit_and_logging_r_are_denied(string $mode): void
    {
        $this->setMode($mode);
        $this->seedLog($this->orgA->id, $this->p, $this->newbie, 'added', $this->est->id);

        foreach ([$this->viewer, $this->est, $this->super] as $u) {
            $this->actingAs($u)->getJson('org-admin/audit-log')->assertForbidden();
        }
        $this->auditPage($this->adminA)->assertOk();
    }

    public function test_the_audit_page_paginates_20_per_page_with_ppage(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->seedLog($this->orgA->id, $this->p, $this->newbie, 'added', $this->est->id, now()->subMinutes(30 - $i)->toDateTimeString());
        }
        $this->seedLog($this->orgA->id, $this->p, $this->newbie, 'removed', $this->est->id, now()->addDay()->toDateTimeString());

        $one = $this->auditPage($this->adminA)->assertOk()->viewData('projectMemberLog');
        $this->assertSame(26, $one->total());
        $this->assertCount(20, $one);
        $this->assertSame('removed', $one->first()->action);

        $two = $this->actingAs($this->adminA)->get('org-admin/audit-log?ppage=2')->assertOk()->viewData('projectMemberLog');
        $this->assertCount(6, $two);
        $this->assertSame(2, $two->currentPage());
        $this->assertSame([], array_intersect($one->pluck('id')->all(), $two->pluck('id')->all()));
    }

    public function test_the_role_change_table_is_still_rendered(): void
    {
        $r = $this->auditPage($this->adminA)->assertOk();
        $this->assertNotNull($r->viewData('history'));
        $r->assertSee('No project membership changes recorded');
    }

    // ---- debug route ------------------------------------------------------------------

    public function test_test_session_route_is_gone(): void
    {
        $this->actingAs($this->est)->get('test-session')->assertNotFound();
        $uris = collect(Route::getRoutes()->getRoutes())->map->uri()->all();
        $this->assertNotContains('test-session', $uris);
    }
}

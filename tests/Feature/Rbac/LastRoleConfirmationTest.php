<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Role;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2 round 2, L08: removing a member's last active role (which removes the member from the organization) is
 * refused unless the request carries a truthy confirm_last_role, on DELETE org-admin/roles/{id} and on POST
 * org-admin/peel-off, in audit and enforce mode. The confirmation sits AFTER the org-mismatch 404 and the level check, so a
 * request that was going to be denied is still denied (403 / 404 with its audit row), never a 422. None of the requests
 * below that expect a refusal send the parameter on purpose: the fixtures of older classes now send it.
 */
class LastRoleConfirmationTest extends ProjectTestCase
{
    private User $ownerA;
    /** procurement_coordinator only */
    private User $one;
    /** procurement_coordinator and project_engineer */
    private User $two;
    /** viewer_read_only: no user_management grant */
    private User $low;
    /** project_manager: user_management S, below F */
    private User $lowS;
    private User $ownerB;
    private User $ua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->one = $this->mkUser($this->orgA, 'procurement_coordinator');
        $this->two = $this->mkUser($this->orgA, 'procurement_coordinator');
        $this->assignRole($this->two, $this->orgA, 'project_engineer');
        $this->low = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->lowS = $this->mkUser($this->orgA, 'project_manager');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->ua = User::factory()->create(['role' => 'user']);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function parameterForms(): array
    {
        $forms = [
            'absent' => [[], false],
            'string 0' => [['confirm_last_role' => '0'], false],
            'string false' => [['confirm_last_role' => 'false'], false],
            'empty string' => [['confirm_last_role' => ''], false],
            'integer 0' => [['confirm_last_role' => 0], false],
            'boolean false' => [['confirm_last_role' => false], false],
            'null' => [['confirm_last_role' => null], false],
            'string 1' => [['confirm_last_role' => '1'], true],
            'string true' => [['confirm_last_role' => 'true'], true],
            'integer 1' => [['confirm_last_role' => 1], true],
            'boolean true' => [['confirm_last_role' => true], true],
            'string on' => [['confirm_last_role' => 'on'], true],
        ];

        $out = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach ($forms as $label => [$data, $removes]) {
                $out["$label $mode"] = [$mode, $data, $removes];
            }
        }

        return $out;
    }

    private function as(User $u, string $method, string $uri, array $data = [], ?int $orgId = null)
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->json($method, $uri, $data);
    }

    private function browser(User $u, string $uri, array $data = [])
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->from('/org-admin')
            ->delete($uri, $data);
    }

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    private function rowId(User $u, string $slug, ?int $orgId = null): int
    {
        return (int) DB::table('user_org_roles')
            ->where('user_id', $u->id)->where('org_id', $orgId ?? $this->orgA->id)->where('role_id', $this->roleId($slug))->value('id');
    }

    private function active(int $rowId): bool
    {
        return (bool) DB::table('user_org_roles')->where('id', $rowId)->value('is_active');
    }

    private function logs(): int
    {
        return DB::table('role_assignment_logs')->count();
    }

    private function removedLogs(User $u): array
    {
        return DB::table('role_assignment_logs')->where('target_user_id', $u->id)->where('action', 'removed')->get()->all();
    }

    private function isMember(User $u): bool
    {
        return app(RoleAssignmentService::class)->isActiveMember($u->id, $this->orgA->id);
    }

    private function otherMessage(User $u): string
    {
        return "{$u->name} has no other role in Org A. Removing this role removes {$u->name} from the organization. Confirm to continue.";
    }

    private const SELF_MESSAGE = 'You have no other role in Org A. Removing this role removes you from the organization. Confirm to continue.';

    private function assertDenialRow(AuditLog $row, User $actor, int $orgId, string $pattern, string $group, string $level): void
    {
        $this->assertSame(
            ['blocked', 'controller_denied', $actor->id, $orgId, substr($pattern, strpos($pattern, ' ') + 1), $pattern, $group, $level],
            [$row->outcome, $row->reason, (int) $row->user_id, (int) $row->org_id, $row->route_uri, $row->matched_pattern, $row->permission_group, $row->required_level]
        );
    }

    // ---- DELETE org-admin/roles/{id}: the refusal ------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_members_last_role_is_refused_without_the_parameter_and_nothing_is_written(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->one, 'procurement_coordinator');
        $logs = $this->logs();
        $audit = AuditLog::count();

        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row")
            ->assertStatus(422)
            ->assertExactJson(['last_role' => true, 'message' => $this->otherMessage($this->one)]);

        $this->assertTrue($this->active($row));
        $this->assertTrue($this->isMember($this->one));
        $this->assertSame($logs, $this->logs());
        $this->assertSame([], $this->removedLogs($this->one));
        $this->assertSame($audit, AuditLog::count(), 'a refusal that asks for confirmation is not an access denial');
    }

    #[DataProvider('modes')]
    public function test_a_browser_request_is_sent_back_with_the_warning_naming_the_member(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->one, 'procurement_coordinator');
        $logs = $this->logs();

        $this->browser($this->ownerA, "org-admin/roles/$row")
            ->assertRedirect(url('/org-admin'))
            ->assertSessionHas('error', $this->otherMessage($this->one))
            ->assertSessionMissing('success');

        $this->assertTrue($this->active($row));
        $this->assertSame($logs, $this->logs());
    }

    #[DataProvider('parameterForms')]
    public function test_only_a_truthy_confirm_last_role_removes_the_last_role(string $mode, array $data, bool $removes): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->one, 'procurement_coordinator');

        $r = $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row", $data);

        if ($removes) {
            $r->assertRedirect();
            $this->assertFalse($this->active($row));
            $this->assertCount(1, $this->removedLogs($this->one));
        } else {
            $r->assertStatus(422)->assertJson(['last_role' => true]);
            $this->assertTrue($this->active($row));
            $this->assertSame([], $this->removedLogs($this->one));
        }
    }

    #[DataProvider('modes')]
    public function test_the_same_request_as_a_form_post_with_the_checkbox_value_confirms(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->one, 'procurement_coordinator');

        $this->browser($this->ownerA, "org-admin/roles/$row", ['confirm_last_role' => '1'])
            ->assertRedirect(url('/org-admin'))
            ->assertSessionHas('success', 'Role removed.');

        $this->assertFalse($this->active($row));
    }

    // ---- confirmed removal ---------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_with_the_parameter_the_last_role_is_removed_logged_once_and_the_member_leaves_the_org(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->one, 'procurement_coordinator');

        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row", ['confirm_last_role' => 1])->assertRedirect();

        $this->assertFalse($this->active($row));
        $this->assertFalse($this->isMember($this->one));
        $logs = $this->removedLogs($this->one);
        $this->assertCount(1, $logs);
        $this->assertSame(
            [$this->orgA->id, $this->roleId('procurement_coordinator'), $this->ownerA->id],
            [(int) $logs[0]->org_id, (int) $logs[0]->role_id, (int) $logs[0]->performed_by]
        );
    }

    #[DataProvider('modes')]
    public function test_once_removed_the_member_cannot_be_given_a_role_directly_only_re_invited(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->one, 'procurement_coordinator');
        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row", ['confirm_last_role' => 1])->assertRedirect();

        $this->as($this->ownerA, 'POST', 'org-admin/roles', ['user_id' => $this->one->id, 'role_id' => $this->roleId('estimator')])->assertForbidden();

        $this->assertFalse($this->isMember($this->one));
    }

    // ---- what counts as the last role ----------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_member_with_two_active_roles_loses_one_without_the_parameter_and_it_is_logged_once(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->two, 'project_engineer');
        $keep = $this->rowId($this->two, 'procurement_coordinator');

        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row")->assertRedirect();

        $this->assertFalse($this->active($row));
        $this->assertTrue($this->active($keep));
        $this->assertTrue($this->isMember($this->two));
        $this->assertCount(1, $this->removedLogs($this->two));
    }

    #[DataProvider('modes')]
    public function test_an_inactive_second_row_does_not_make_it_a_second_role(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->one, $this->orgA, 'project_engineer', false);
        $row = $this->rowId($this->one, 'procurement_coordinator');

        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row")->assertStatus(422)->assertJson(['last_role' => true]);

        $this->assertTrue($this->active($row));
        $this->assertSame([], $this->removedLogs($this->one));
    }

    #[DataProvider('modes')]
    public function test_an_active_row_in_another_org_does_not_make_it_a_second_role_here(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->one, $this->orgB, 'estimator');
        $row = $this->rowId($this->one, 'procurement_coordinator');

        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row")->assertStatus(422)->assertJson(['last_role' => true]);

        $this->assertTrue($this->active($row));
        $this->assertTrue($this->active($this->rowId($this->one, 'estimator', $this->orgB->id)));
    }

    #[DataProvider('modes')]
    public function test_an_already_inactive_row_is_not_a_last_role_and_answers_as_before(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->one, $this->orgA, 'project_engineer', false);
        $row = $this->rowId($this->one, 'project_engineer');
        $logs = $this->logs();

        $this->browser($this->ownerA, "org-admin/roles/$row")
            ->assertRedirect(url('/org-admin'))
            ->assertSessionHas('success', 'Role removed.')
            ->assertSessionMissing('error');

        $this->assertSame($logs, $this->logs(), 'deactivate() finds nothing to remove and logs nothing');
        $this->assertTrue($this->isMember($this->one));
    }

    #[DataProvider('modes')]
    public function test_the_inactive_row_of_a_former_member_with_no_other_row_is_not_a_last_role_either(string $mode): void
    {
        $this->setMode($mode);
        $former = $this->mkUser();
        $this->assignRole($former, $this->orgA, 'project_engineer', false);
        $row = $this->rowId($former, 'project_engineer');
        $logs = $this->logs();

        $this->browser($this->ownerA, "org-admin/roles/$row")
            ->assertRedirect(url('/org-admin'))
            ->assertSessionHas('success', 'Role removed.')
            ->assertSessionMissing('error');

        $this->assertSame($logs, $this->logs());
        $this->assertFalse($this->isMember($former));
    }

    public function test_the_service_decides_last_role_by_active_rows_of_the_same_user_in_the_same_org(): void
    {
        $service = app(RoleAssignmentService::class);
        $row = fn (User $u, string $slug, ?int $orgId = null) => UserOrgRole::findOrFail($this->rowId($u, $slug, $orgId));

        $this->assertTrue($service->isLastActiveRole($row($this->one, 'procurement_coordinator')), 'the only active row');
        $this->assertFalse($service->isLastActiveRole($row($this->two, 'project_engineer')), 'another active row in the org');

        $this->assignRole($this->one, $this->orgA, 'project_engineer', false);
        $this->assertTrue($service->isLastActiveRole($row($this->one, 'procurement_coordinator')), 'an inactive row is not a role');
        $this->assertFalse($service->isLastActiveRole($row($this->one, 'project_engineer')), 'an inactive row is never the last role');

        $this->assignRole($this->one, $this->orgB, 'estimator');
        $this->assertTrue($service->isLastActiveRole($row($this->one, 'procurement_coordinator')), 'a row in another org does not count');

        $former = $this->mkUser();
        $this->assignRole($former, $this->orgA, 'project_engineer', false);
        $this->assertFalse($service->isLastActiveRole($row($former, 'project_engineer')), 'an inactive row with no other row is not the last role of anybody');
    }

    // ---- own role, sole owner, universal admin -------------------------------------------------------

    #[DataProvider('modes')]
    public function test_removing_your_own_last_role_gets_the_self_variant_of_the_warning(string $mode): void
    {
        $this->setMode($mode);
        $admin = $this->mkUser($this->orgA, 'organization_admin');
        $row = $this->rowId($admin, 'organization_admin');

        $this->as($admin, 'DELETE', "org-admin/roles/$row")
            ->assertStatus(422)
            ->assertExactJson(['last_role' => true, 'message' => self::SELF_MESSAGE]);
        $this->assertTrue($this->active($row));

        $this->as($admin, 'DELETE', "org-admin/roles/$row", ['confirm_last_role' => true])->assertRedirect();

        $this->assertFalse($this->active($row));
        $this->assertFalse($this->isMember($admin));
        $log = $this->removedLogs($admin);
        $this->assertCount(1, $log);
        $this->assertSame($admin->id, (int) $log[0]->performed_by);
    }

    #[DataProvider('modes')]
    public function test_the_only_owners_last_role_is_removable_with_the_parameter_and_leaves_no_owner(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->ownerA, 'organization_owner');
        $owners = fn () => DB::table('user_org_roles')->where('org_id', $this->orgA->id)->where('role_id', $this->roleId('organization_owner'))->where('is_active', true)->count();
        $this->assertSame(1, $owners());

        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row")->assertStatus(422);
        $this->assertSame(1, $owners());

        $this->as($this->ownerA, 'DELETE', "org-admin/roles/$row", ['confirm_last_role' => 1])->assertRedirect();

        $this->assertSame(0, $owners(), 'no code requires an organization to keep an Owner (D6: warn only)');
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_gets_the_same_confirmation(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);
        $row = $this->rowId($this->one, 'procurement_coordinator');

        $this->as($this->ua, 'DELETE', "org-admin/roles/$row")
            ->assertStatus(422)
            ->assertExactJson(['last_role' => true, 'message' => $this->otherMessage($this->one)]);
        $this->assertTrue($this->active($row));
        $this->assertSame([], $this->removedLogs($this->one));

        $this->as($this->ua, 'DELETE', "org-admin/roles/$row", ['confirm_last_role' => 1])->assertRedirect();

        $this->assertFalse($this->active($row));
        $this->assertSame($this->ua->id, (int) $this->removedLogs($this->one)[0]->performed_by);
    }

    // ---- denial still comes first ---------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_below_the_level_is_403_not_422_for_a_last_role_and_nothing_is_written(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->one, 'procurement_coordinator');
        $logs = $this->logs();

        foreach ([$this->low, $this->lowS] as $actor) {
            $this->as($actor, 'DELETE', "org-admin/roles/$row")->assertForbidden();
            $this->assertTrue($this->active($row), $actor->id);
        }

        $own = $this->rowId($this->low, 'viewer_read_only');
        $this->as($this->low, 'DELETE', "org-admin/roles/$own")->assertForbidden();
        $this->assertTrue($this->active($own), 'a member cannot remove their own last role without the level either');
        $this->assertSame($logs, $this->logs());
    }

    #[DataProvider('modes')]
    public function test_below_the_level_is_403_not_422_on_peel_off_for_the_actors_own_last_role(string $mode): void
    {
        $this->setMode($mode);
        $viewerRole = $this->roleId('viewer_read_only');
        $own = $this->rowId($this->low, 'viewer_read_only');
        $logs = $this->logs();

        $this->as($this->low, 'POST', 'org-admin/peel-off', ['role_id' => $viewerRole])->assertForbidden();
        $this->as($this->lowS, 'POST', 'org-admin/peel-off', ['role_id' => $this->roleId('project_manager')])->assertForbidden();

        $this->assertTrue($this->active($own));
        $this->assertTrue($this->active($this->rowId($this->lowS, 'project_manager')));
        $this->assertSame($logs, $this->logs());
    }

    #[DataProvider('modes')]
    public function test_another_orgs_last_role_is_a_404_with_one_controller_denied_row_not_a_422(string $mode): void
    {
        $this->setMode($mode);
        $foreign = $this->rowId($this->outsider, 'estimator', $this->orgB->id);
        $this->assertTrue(app(RoleAssignmentService::class)->isLastActiveRole(UserOrgRole::findOrFail($foreign)), 'the fixture row is the outsider\'s only role');

        $other = $this->as($this->ownerA, 'DELETE', "org-admin/roles/$foreign")->assertNotFound();

        $rows = AuditLog::where('reason', 'controller_denied')->get();
        $this->assertCount(1, $rows);
        $this->assertDenialRow($rows[0], $this->ownerA, $this->orgA->id, 'DELETE org-admin/roles/{userOrgRole}', 'user_management', 'F');
        $this->assertTrue($this->active($foreign));
        $this->assertSame([], $this->removedLogs($this->outsider));

        $missing = $this->as($this->ownerA, 'DELETE', 'org-admin/roles/999999')->assertNotFound();
        $this->assertSame($missing->getContent(), $other->getContent(), 'the same answer as a missing id');
        $this->assertSame(1, AuditLog::where('reason', 'controller_denied')->count(), 'a missing id writes nothing');

        $this->as($this->ownerB, 'DELETE', 'org-admin/roles/'.$this->rowId($this->one, 'procurement_coordinator'), [], $this->orgB->id)->assertNotFound();
        $this->assertSame(2, AuditLog::where('reason', 'controller_denied')->count());
        $this->assertTrue($this->active($this->rowId($this->one, 'procurement_coordinator')));
    }

    // ---- POST org-admin/peel-off ----------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_peel_off_of_the_actors_last_role_is_refused_without_the_parameter(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->ownerA, 'organization_owner');
        $logs = $this->logs();

        $this->as($this->ownerA, 'POST', 'org-admin/peel-off', ['role_id' => $this->roleId('organization_owner')])
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'last_role' => true, 'message' => self::SELF_MESSAGE]);

        $this->assertTrue($this->active($row));
        $this->assertSame($logs, $this->logs());
    }

    #[DataProvider('modes')]
    public function test_peel_off_of_the_last_role_works_with_the_parameter_and_logs_once(string $mode): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->ownerA, 'organization_owner');

        $this->as($this->ownerA, 'POST', 'org-admin/peel-off', ['role_id' => $this->roleId('organization_owner'), 'confirm_last_role' => true])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Role removed from your account.']);

        $this->assertFalse($this->active($row));
        $this->assertFalse($this->isMember($this->ownerA));
        $log = $this->removedLogs($this->ownerA);
        $this->assertCount(1, $log);
        $this->assertSame($this->ownerA->id, (int) $log[0]->performed_by);
    }

    #[DataProvider('parameterForms')]
    public function test_peel_off_reads_the_parameter_the_same_way(string $mode, array $data, bool $removes): void
    {
        $this->setMode($mode);
        $row = $this->rowId($this->ownerA, 'organization_owner');

        $r = $this->as($this->ownerA, 'POST', 'org-admin/peel-off', $data + ['role_id' => $this->roleId('organization_owner')]);

        if ($removes) {
            $r->assertOk()->assertJson(['success' => true]);
            $this->assertFalse($this->active($row));
        } else {
            $r->assertStatus(422)->assertJson(['success' => false, 'last_role' => true]);
            $this->assertTrue($this->active($row));
        }
    }

    #[DataProvider('modes')]
    public function test_peel_off_of_a_role_that_is_not_the_last_is_unchanged(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->ownerA, $this->orgA, 'executive_approver');
        $row = $this->rowId($this->ownerA, 'executive_approver');

        $this->as($this->ownerA, 'POST', 'org-admin/peel-off', ['role_id' => $this->roleId('executive_approver')])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Role removed from your account.']);

        $this->assertFalse($this->active($row));
        $this->assertTrue($this->isMember($this->ownerA));
        $this->assertCount(1, $this->removedLogs($this->ownerA));
    }

    #[DataProvider('modes')]
    public function test_peel_off_of_a_role_the_actor_does_not_hold_is_a_200_not_a_422(string $mode): void
    {
        $this->setMode($mode);
        $logs = $this->logs();

        $this->as($this->ownerA, 'POST', 'org-admin/peel-off', ['role_id' => $this->roleId('estimator')])
            ->assertOk()
            ->assertExactJson(['success' => false, 'message' => 'You no longer hold that role.']);

        $this->assertSame($logs, $this->logs());
        $this->assertTrue($this->isMember($this->ownerA));
    }

    #[DataProvider('modes')]
    public function test_peel_off_of_an_inactive_own_row_is_not_a_last_role(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->ownerA, $this->orgA, 'executive_approver', false);

        $this->as($this->ownerA, 'POST', 'org-admin/peel-off', ['role_id' => $this->roleId('executive_approver')])
            ->assertOk()
            ->assertJson(['success' => false]);

        $this->assertSame([], $this->removedLogs($this->ownerA));
    }

    // ---- POST org-admin/roles: the peel_off_last_role hint -------------------------------------------------------

    #[DataProvider('modes')]
    public function test_the_assign_answer_flags_a_peel_off_that_would_be_the_assigners_last_role(string $mode): void
    {
        $this->setMode($mode);
        $keys = ['success', 'message', 'peel_off_candidate', 'peel_off_last_role', 'role_id', 'role_name'];

        $only = $this->as($this->ownerA, 'POST', 'org-admin/roles', ['user_id' => $this->low->id, 'role_id' => $this->roleId('organization_owner')])->assertOk();
        $this->assertSame($keys, array_keys($only->json()));
        $this->assertTrue($only->json('peel_off_candidate'));
        $this->assertTrue($only->json('peel_off_last_role'), 'the Owner row is the assigner\'s only role');
    }

    #[DataProvider('modes')]
    public function test_the_assign_answer_says_not_last_when_the_assigner_has_another_role_or_is_no_candidate(string $mode): void
    {
        $this->setMode($mode);
        $this->assignRole($this->ownerA, $this->orgA, 'executive_approver');

        $other = $this->as($this->ownerA, 'POST', 'org-admin/roles', ['user_id' => $this->low->id, 'role_id' => $this->roleId('organization_owner')])->assertOk();
        $this->assertTrue($other->json('peel_off_candidate'));
        $this->assertFalse($other->json('peel_off_last_role'), 'the assigner keeps another role');

        $notCandidate = $this->as($this->ownerA, 'POST', 'org-admin/roles', ['user_id' => $this->low->id, 'role_id' => $this->roleId('estimator')])->assertOk();
        $this->assertFalse($notCandidate->json('peel_off_candidate'));
        $this->assertFalse($notCandidate->json('peel_off_last_role'), 'only a candidate can be asked to peel off');
    }
}

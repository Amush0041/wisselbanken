<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\OrgRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 1.0 fixes. Every new controller check runs in audit and in enforce mode. Denials are
 * JSON requests: enforce answers 403 from the middleware, audit lets the request through so the
 * controller check answers 403. Renders use browser requests so Blade errors cannot hide.
 *
 * Grants used (database/seeders/Rbac/data/role_permission_matrix.php):
 *   organization_owner / organization_admin : OM F, UM F, PM F
 *   manufacturer_admin       : OM F, UM F, PM none     (right org level, wrong project group)
 *   estimator                : UM F, PM F, OM none     (wrong group for org settings / connections)
 *   procurement_manager      : OM R, UM R, PM R, QR F  (R only on the org groups)
 *   procurement_coordinator  : QR F, PM R, UM none     (wrong group for overview)
 *   project_engineer         : PM O, QR S              viewer_read_only / superintendent : PM R
 *   product_manager          : PRD F   catalog_data_steward : PRD O   viewer_read_only : PRD R
 */
class AuditFindingsTest extends ProjectTestCase
{
    private User $ownerA;
    private User $adminA;
    private User $mfr;
    private User $proc;
    private User $coord;
    private User $eng;
    private User $ownerB;
    private User $dual;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->adminA = $this->mkUser($this->orgA, 'organization_admin');
        $this->mfr = $this->mkUser($this->orgA, 'manufacturer_admin');
        $this->proc = $this->mkUser($this->orgA, 'procurement_manager');
        $this->coord = $this->mkUser($this->orgA, 'procurement_coordinator');
        $this->eng = $this->mkUser($this->orgA, 'project_engineer');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->dual = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->assignRole($this->dual, $this->orgB, 'organization_owner');

        foreach ([$this->ownerA, $this->adminA, $this->mfr, $this->proc, $this->coord, $this->eng, $this->dual] as $u) {
            $this->member($this->p1, $u, $this->orgA);
        }
        $this->member($this->p3, $this->ownerB, $this->orgB);
        $this->member($this->p3, $this->dual, $this->orgB);
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function asJ(User $u, string $method, string $uri, array $data = [], ?int $orgId = null)
    {
        $session = $orgId ? [config('rbac.current_org_session_key') => $orgId] : [];

        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function page(User $u, string $uri, ?int $orgId = null)
    {
        $session = $orgId ? [config('rbac.current_org_session_key') => $orgId] : [];

        return $this->actingAs($u)->withSession($session)->get($uri);
    }

    private function memberRowId(int $projectId, User $u): int
    {
        return (int) DB::table('project_members')->where('project_id', $projectId)->where('user_id', $u->id)->value('id');
    }

    private function active(int $projectId, User $u): bool
    {
        return DB::table('project_members')->where('project_id', $projectId)->where('user_id', $u->id)->where('is_active', true)->exists();
    }

    private function connect(int $from, int $to, string $type = 'buyer_seller'): int
    {
        return (int) OrgRelationship::create([
            'from_org_id' => $from, 'to_org_id' => $to, 'relationship_type' => $type, 'is_active' => true,
        ])->id;
    }

    // ---- (a) project member add / remove ------------------------------------------

    #[DataProvider('modes')]
    public function test_a_add_member_is_allowed_for_project_management_f_members(string $mode): void
    {
        $this->setMode($mode);
        $newcomer = $this->mkUser($this->orgA, 'estimator');

        foreach ([$this->est, $this->adminA, $this->ownerA] as $actor) {
            $this->actingAs($actor)->post(route('org-admin.projects.members.store'), ['project_id' => $this->p1, 'user_id' => $newcomer->id])
                ->assertSessionHas('success', 'Member added to project.');
            $this->assertTrue($this->active($this->p1, $newcomer));
            DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $newcomer->id)->delete();
        }
    }

    #[DataProvider('modes')]
    public function test_a_add_member_is_denied_below_org_level_f_and_for_the_wrong_group(string $mode): void
    {
        $this->setMode($mode);
        $target = $this->mkUser($this->orgA, 'estimator');

        foreach ([$this->viewer, $this->super, $this->eng, $this->proc, $this->mfr] as $actor) {
            $this->asJ($actor, 'POST', route('org-admin.projects.members.store'), ['project_id' => $this->p1, 'user_id' => $target->id])
                ->assertForbidden();
        }
        $this->assertFalse($this->active($this->p1, $target));
    }

    #[DataProvider('modes')]
    public function test_a_add_member_org_check_runs_before_validation_and_project_lookup(string $mode): void
    {
        $this->setMode($mode);
        $url = route('org-admin.projects.members.store');
        $target = $this->mkUser($this->orgA, 'estimator');

        foreach ([$this->viewer, $this->eng] as $actor) {
            $this->asJ($actor, 'POST', $url, [])->assertForbidden();
            $this->asJ($actor, 'POST', $url, ['project_id' => 999999, 'user_id' => $target->id])->assertForbidden();
            $this->asJ($actor, 'POST', $url, ['project_id' => $this->p3, 'user_id' => $target->id])->assertForbidden();
        }
    }

    #[DataProvider('modes')]
    public function test_a_add_member_wrong_org_and_missing_project_membership(string $mode): void
    {
        $this->setMode($mode);
        $url = route('org-admin.projects.members.store');
        $target = $this->mkUser($this->orgA, 'estimator');

        $this->asJ($this->outsider, 'POST', $url, ['project_id' => $this->p1, 'user_id' => $target->id])->assertNotFound();
        $this->asJ($this->ownerB, 'POST', $url, ['project_id' => $this->p1, 'user_id' => $target->id])->assertNotFound();
        $this->asJ($this->dual, 'POST', $url, ['project_id' => $this->p1, 'user_id' => $target->id], $this->orgB->id)->assertNotFound();
        $this->asJ($this->dual, 'POST', $url, ['project_id' => $this->p1, 'user_id' => $target->id], $this->orgA->id)->assertForbidden();
        $this->assertFalse($this->active($this->p1, $target));

        $this->asJ($this->est, 'POST', $url, ['project_id' => $this->p2, 'user_id' => $target->id])->assertForbidden();
        $this->assertFalse($this->active($this->p2, $target));
    }

    #[DataProvider('modes')]
    public function test_a_remove_member_is_allowed_for_project_management_f_members(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->est, $this->adminA, $this->ownerA] as $actor) {
            $victim = $this->mkUser($this->orgA, 'estimator');
            $rowId = $this->member($this->p1, $victim, $this->orgA);
            $this->actingAs($actor)->delete(route('org-admin.projects.members.destroy', $rowId))->assertSessionHas('success', 'Member removed from project.');
            $this->assertFalse($this->active($this->p1, $victim));
        }
    }

    #[DataProvider('modes')]
    public function test_a_remove_member_is_denied_below_org_level_f_and_for_the_wrong_group(string $mode): void
    {
        $this->setMode($mode);
        $rowId = $this->memberRowId($this->p1, $this->est);

        foreach ([$this->viewer, $this->super, $this->eng, $this->proc, $this->mfr] as $actor) {
            $this->asJ($actor, 'DELETE', route('org-admin.projects.members.destroy', $rowId))->assertForbidden();
        }
        $this->assertTrue($this->active($this->p1, $this->est));
    }

    #[DataProvider('modes')]
    public function test_a_remove_member_org_check_runs_before_the_project_lookup(string $mode): void
    {
        $this->setMode($mode);
        $victim = $this->mkUser($this->orgA, 'estimator');
        $trashed = $this->mkProject($this->orgA, $this->owner, 'gone', now()->toDateTimeString());
        $rowId = $this->member($trashed, $victim, $this->orgA);

        $this->asJ($this->viewer, 'DELETE', route('org-admin.projects.members.destroy', $rowId))->assertForbidden();
        $this->asJ($this->est, 'DELETE', route('org-admin.projects.members.destroy', $rowId))->assertNotFound();
        $this->assertTrue($this->active($trashed, $victim));
    }

    #[DataProvider('modes')]
    public function test_a_remove_member_wrong_org_and_missing_project_membership(string $mode): void
    {
        $this->setMode($mode);
        $orgARow = $this->memberRowId($this->p1, $this->viewer);
        $orgBRow = $this->memberRowId($this->p3, $this->outsider);

        $this->asJ($this->outsider, 'DELETE', route('org-admin.projects.members.destroy', $orgARow))->assertForbidden();
        $this->asJ($this->ownerB, 'DELETE', route('org-admin.projects.members.destroy', $orgARow))->assertForbidden();
        $this->asJ($this->est, 'DELETE', route('org-admin.projects.members.destroy', $orgBRow))->assertForbidden();
        $this->assertTrue($this->active($this->p1, $this->viewer));
        $this->assertTrue($this->active($this->p3, $this->outsider));

        $victim = $this->mkUser($this->orgA, 'estimator');
        $p2Row = $this->member($this->p2, $victim, $this->orgA);
        $this->asJ($this->est, 'DELETE', route('org-admin.projects.members.destroy', $p2Row))->assertForbidden();
        $this->assertTrue($this->active($this->p2, $victim));
    }

    public function test_a_projects_view_shows_add_and_remove_controls_only_at_f(): void
    {
        $this->setMode('audit');
        $add = 'action="'.route('org-admin.projects.members.store').'"';
        $remove = 'action="'.route('org-admin.projects.members.destroy', $this->memberRowId($this->p1, $this->est)).'"';

        $r = $this->page($this->est, 'org-admin/projects')->assertOk();
        $this->assertTrue($r->viewData('canManageProjects'));
        $r->assertSee($add, false)->assertSee($remove, false)->assertSee('title="Remove"', false);

        $this->page($this->mfr, 'org-admin/projects')->assertForbidden();

        foreach ([$this->viewer, $this->eng] as $u) {
            $r = $this->page($u, 'org-admin/projects')->assertOk();
            $this->assertFalse($r->viewData('canManageProjects'));
            $r->assertDontSee($add, false)->assertDontSee($remove, false)->assertDontSee('title="Remove"', false);
            $r->assertSee('P1');
        }
    }

    // ---- (b) org settings ---------------------------------------------------------

    private function settingsPayload(string $name): array
    {
        return ['name' => $name, 'org_type' => 'subcontractor', 'team_size' => config('rbac.team_sizes')[0]];
    }

    #[DataProvider('modes')]
    public function test_b_settings_update_is_allowed_for_organization_management_f(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->ownerA, $this->adminA] as $i => $actor) {
            $this->actingAs($actor)->post('org-admin/settings', $this->settingsPayload("Renamed $i"))->assertSessionHas('success');
            $this->assertSame("Renamed $i", DB::table('organizations')->where('id', $this->orgA->id)->value('name'));
        }
    }

    #[DataProvider('modes')]
    public function test_b_settings_update_is_denied_below_f_and_for_the_wrong_group(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->viewer, $this->est, $this->proc, $this->eng, $this->coord] as $actor) {
            $this->asJ($actor, 'POST', 'org-admin/settings', $this->settingsPayload('Hijacked'))->assertForbidden();
            $this->asJ($actor, 'POST', 'org-admin/settings', [])->assertForbidden();
        }
        $this->assertSame('Org A', DB::table('organizations')->where('id', $this->orgA->id)->value('name'));
    }

    #[DataProvider('modes')]
    public function test_b_settings_update_never_crosses_orgs(string $mode): void
    {
        $this->setMode($mode);

        $this->asJ($this->dual, 'POST', 'org-admin/settings', $this->settingsPayload('Via A'), $this->orgA->id)->assertForbidden();
        $this->assertSame(['Org A', 'Org B'], [DB::table('organizations')->where('id', $this->orgA->id)->value('name'), DB::table('organizations')->where('id', $this->orgB->id)->value('name')]);

        $this->actingAs($this->ownerB)->post('org-admin/settings', $this->settingsPayload('B only'))->assertSessionHas('success');
        $this->assertSame(['Org A', 'B only'], [DB::table('organizations')->where('id', $this->orgA->id)->value('name'), DB::table('organizations')->where('id', $this->orgB->id)->value('name')]);
    }

    public function test_b_settings_view_is_editable_with_a_danger_zone_for_an_owner(): void
    {
        $this->setMode('audit');

        $r = $this->page($this->ownerA, 'org-admin/settings')->assertOk();
        $this->assertTrue($r->viewData('canManageOrg'));
        $r->assertSee('Save Changes')->assertSee('Discard changes')->assertSee('Danger Zone')->assertSee('id="transferModal"', false)->assertSee('href="#danger"', false);
        $this->assertDoesNotMatchRegularExpression('/<(input|select)[^>]*id="(org_name|org_type|team_size)"[^>]*\bdisabled\b/', $r->getContent());
    }

    public function test_b_settings_view_is_read_only_below_f_in_audit_mode(): void
    {
        $this->setMode('audit');

        $this->page($this->viewer, 'org-admin/settings')->assertForbidden();

        foreach ([$this->proc] as $u) {
            $r = $this->page($u, 'org-admin/settings')->assertOk();
            $this->assertFalse($r->viewData('canManageOrg'));
            $r->assertDontSee('Save Changes')->assertDontSee('Discard changes')->assertDontSee('Danger Zone')
                ->assertDontSee('id="transferModal"', false)->assertDontSee('id="deleteModal"', false)->assertDontSee('href="#danger"', false);
            foreach (['org_name', 'org_type', 'team_size'] as $id) {
                $this->assertMatchesRegularExpression('/<(input|select)[^>]*id="'.$id.'"[^>]*\bdisabled\b/', $r->getContent(), "$id is disabled");
            }
            $r->assertSee('Org A');
        }
    }

    public function test_b_settings_page_read_needs_organization_management_r_in_enforce_mode(): void
    {
        $this->setMode('enforce');

        $this->asJ($this->proc, 'GET', 'org-admin/settings')->assertOk();
        $this->asJ($this->viewer, 'GET', 'org-admin/settings')->assertForbidden();
        $this->asJ($this->est, 'GET', 'org-admin/settings')->assertForbidden();
        $r = $this->page($this->proc, 'org-admin/settings')->assertOk();
        $r->assertDontSee('Save Changes')->assertDontSee('Danger Zone');
    }

    #[DataProvider('modes')]
    public function test_b_transfer_and_delete_stay_owner_only(string $mode): void
    {
        $this->setMode($mode);

        $this->asJ($this->adminA, 'DELETE', 'org-admin/settings/delete')->assertForbidden();
        $this->asJ($this->viewer, 'DELETE', 'org-admin/settings/delete')->assertForbidden();
        $this->asJ($this->viewer, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->adminA->email])->assertForbidden();
        $this->assertNotNull(DB::table('organizations')->where('id', $this->orgA->id)->first());
    }

    // ---- (c) my-roles and overview ------------------------------------------------

    public function test_c_my_roles_is_deliberately_unmapped_and_every_other_org_admin_route_is_mapped(): void
    {
        $registered = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->methods() as $m) {
                if ($m !== 'HEAD' && str_starts_with($route->uri(), 'org-admin')) {
                    $registered[] = "$m {$route->uri()}";
                }
            }
        }
        $map = config('route_permission_map');

        $this->assertContains('GET org-admin/my-roles', $registered);
        $this->assertArrayNotHasKey('GET org-admin/my-roles', $map);
        $this->assertArrayNotHasKey('* org-admin/my-roles', $map);
        $this->assertSame([], array_values(array_filter(
            $registered,
            fn ($k) => $k !== 'GET org-admin/my-roles' && ! isset($map[$k]) && ! isset($map['* '.explode(' ', $k, 2)[1]])
        )), 'every other org-admin route stays mapped (fails closed in enforce)');
    }

    #[DataProvider('modes')]
    public function test_c_my_roles_is_open_to_a_plain_member_and_shows_only_their_own_data(string $mode): void
    {
        $this->setMode($mode);
        $this->est->forceFill(['name' => 'Ester Estimator', 'email' => 'ester@example.test'])->save();
        $this->viewer->forceFill(['name' => 'Vera Viewer', 'email' => 'vera@example.test'])->save();
        $this->ownerA->forceFill(['name' => 'Olaf Ownerman', 'email' => 'olaf@example.test'])->save();

        $r = $this->page($this->viewer, 'org-admin/my-roles')->assertOk();
        $r->assertSee('Viewer');
        $groups = $r->viewData('orgGroups');
        $this->assertSame([$this->viewer->id], $groups->flatten()->pluck('user_id')->unique()->values()->all());
        foreach (['Ester Estimator', 'ester@example.test', 'Olaf Ownerman', 'olaf@example.test'] as $other) {
            $r->assertDontSee($other);
        }
        $this->asJ($this->viewer, 'GET', 'org-admin/my-roles')->assertOk();
        $this->asJ($this->outsider, 'GET', 'org-admin/my-roles')->assertOk();
    }

    #[DataProvider('modes')]
    public function test_c_overview_is_allowed_with_user_management_r_or_more(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->proc, $this->est, $this->ownerA, $this->adminA] as $u) {
            $this->page($u, 'org-admin/overview')->assertOk();
        }
    }

    #[DataProvider('modes')]
    public function test_c_overview_is_denied_without_user_management_r(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->viewer, $this->super, $this->eng, $this->coord] as $u) {
            $this->asJ($u, 'GET', 'org-admin/overview')->assertForbidden();
        }
    }

    #[DataProvider('modes')]
    public function test_c_overview_wrong_org_shows_only_the_callers_org(string $mode): void
    {
        $this->setMode($mode);

        $this->asJ($this->dual, 'GET', 'org-admin/overview', [], $this->orgA->id)->assertForbidden();
        $r = $this->page($this->dual, 'org-admin/overview', $this->orgB->id)->assertOk();
        $this->assertSame($this->orgB->id, $r->viewData('org')->id);
        $bUsers = DB::table('user_org_roles')->where('org_id', $this->orgB->id)->pluck('user_id')->all();
        $this->assertSame(count(array_unique($bUsers)), $r->viewData('stats')['members'], 'only org B members are counted');
        $this->assertLessThan(DB::table('user_org_roles')->distinct()->count('user_id'), $r->viewData('stats')['members']);
        $this->assertSame([], array_diff($r->viewData('recentActivity')->pluck('user_id')->all(), $bUsers));
        $this->assertSame([], array_diff($r->viewData('allRoles')->pluck('assignees')->flatten()->pluck('user_id')->all(), $bUsers));
        $this->assertSame($this->orgB->id, $this->page($this->outsider, 'org-admin/overview')->assertOk()->viewData('org')->id);
    }

    public function test_c_nav_and_sidebar_hide_overview_below_user_management_r(): void
    {
        $this->setMode('audit');
        $navOverview = '<i class="ti ti-layout-dashboard"></i> Overview';
        $sideOverview = '<div>Overview</div>';

        $r = $this->page($this->est, 'org-admin/projects')->assertOk();
        $r->assertSee($navOverview, false)->assertSee($sideOverview, false);

        foreach ([$this->viewer, $this->eng] as $u) {
            $r = $this->page($u, 'org-admin/projects')->assertOk();
            $r->assertDontSee($navOverview, false)->assertDontSee($sideOverview, false);
            $r->assertSee('My Roles');
        }
    }

    // ---- (d) connections ----------------------------------------------------------

    #[DataProvider('modes')]
    public function test_d_connections_page_needs_organization_management_r(string $mode): void
    {
        $this->setMode($mode);

        foreach ([$this->proc, $this->ownerA, $this->adminA] as $u) {
            $this->page($u, 'org-admin/connections')->assertOk();
        }
        foreach ([$this->viewer, $this->est, $this->eng, $this->coord] as $u) {
            $this->asJ($u, 'GET', 'org-admin/connections')->assertForbidden();
        }
    }

    #[DataProvider('modes')]
    public function test_d_connection_store_needs_organization_management_f(string $mode): void
    {
        $this->setMode($mode);
        $payload = ['to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller'];

        foreach ([$this->proc, $this->viewer, $this->est, $this->eng] as $u) {
            $this->asJ($u, 'POST', 'org-admin/connections', $payload)->assertForbidden();
            $this->asJ($u, 'POST', 'org-admin/connections', [])->assertForbidden();
        }
        $this->assertSame(0, OrgRelationship::count());

        $this->actingAs($this->ownerA)->post('org-admin/connections', $payload)->assertSessionHas('success', 'Connection created.');
        $this->assertSame(1, OrgRelationship::where('from_org_id', $this->orgA->id)->count());
    }

    #[DataProvider('modes')]
    public function test_d_connection_destroy_needs_organization_management_f_and_the_same_org(string $mode): void
    {
        $this->setMode($mode);
        $rel = $this->connect($this->orgA->id, $this->orgB->id);

        foreach ([$this->proc, $this->viewer, $this->est] as $u) {
            $this->asJ($u, 'DELETE', route('org-admin.connections.destroy', $rel))->assertForbidden();
        }
        $this->asJ($this->ownerB, 'DELETE', route('org-admin.connections.destroy', $rel))->assertForbidden();
        $this->asJ($this->dual, 'DELETE', route('org-admin.connections.destroy', $rel), [], $this->orgB->id)->assertForbidden();
        $this->asJ($this->dual, 'DELETE', route('org-admin.connections.destroy', $rel), [], $this->orgA->id)->assertForbidden();
        $this->assertTrue((bool) OrgRelationship::find($rel)->is_active);

        $this->actingAs($this->adminA)->delete(route('org-admin.connections.destroy', $rel))->assertSessionHas('success', 'Connection deactivated.');
        $this->assertFalse((bool) OrgRelationship::find($rel)->is_active);
    }

    public function test_d_connections_view_shows_add_form_and_deactivate_only_at_f(): void
    {
        $this->setMode('audit');
        $this->connect($this->orgA->id, $this->orgB->id);

        $r = $this->page($this->ownerA, 'org-admin/connections')->assertOk();
        $this->assertTrue($r->viewData('canManageConnections'));
        $r->assertSee('Add New Connection')->assertSee('Deactivate');

        $r = $this->page($this->proc, 'org-admin/connections')->assertOk();
        $this->assertFalse($r->viewData('canManageConnections'));
        $r->assertDontSee('Add New Connection')->assertDontSee('Deactivate')
            ->assertDontSee('action="'.route('org-admin.connections.store').'"', false);
        $r->assertSee('Org B');
    }

    public function test_d_nav_connections_link_follows_organization_management_r(): void
    {
        $this->setMode('audit');
        $link = '<i class="ti ti-network"></i> Connections';

        $this->page($this->proc, 'org-admin/projects')->assertOk()->assertSee($link, false);
        $this->page($this->ownerA, 'org-admin/projects')->assertOk()->assertSee($link, false);
        $this->page($this->est, 'org-admin/projects')->assertOk()->assertDontSee($link, false);
        $this->page($this->viewer, 'org-admin/projects')->assertOk()->assertDontSee($link, false);
    }

    // ---- (e) customers ------------------------------------------------------------

    public function test_e_customer_read_routes_map_to_quote_rfq_management_r_and_writes_are_unchanged(): void
    {
        $map = config('route_permission_map');

        foreach (['GET customers', 'GET customers/list'] as $key) {
            $this->assertSame(['quote_rfq_management', 'R', 'read'], [$map[$key][0], $map[$key][1], $map[$key]['batch']], $key);
        }
        $this->assertSame(['quote_rfq_management', 'R'], [$map['GET customers/{id}'][0], $map['GET customers/{id}'][1]]);
        $this->assertSame(['user_management', 'S'], [$map['POST customers'][0], $map['POST customers'][1]]);
        $this->assertSame(['user_management', 'O'], [$map['PUT customers/{id}'][0], $map['PUT customers/{id}'][1]]);
        $this->assertSame(['user_management', 'F'], [$map['DELETE customers/{id}'][0], $map['DELETE customers/{id}'][1]]);
    }

    public function test_e_customer_lists_follow_quote_rfq_management_in_enforce_mode(): void
    {
        $this->setMode('enforce');

        foreach ([$this->viewer, $this->proc, $this->est, $this->eng] as $u) {
            $this->page($u, 'customers')->assertOk();
            $this->asJ($u, 'GET', 'customers/list')->assertOk();
        }
        $this->asJ($this->super, 'GET', 'customers')->assertForbidden();
        $this->asJ($this->super, 'GET', 'customers/list')->assertForbidden();
        $this->asJ($this->outsider, 'GET', 'customers/list', [], $this->orgB->id)->assertOk();
    }

    // ---- (f) rfq empty state ------------------------------------------------------

    public function test_f_rfq_empty_state_offers_the_create_link_only_with_quote_rfq_management_s(): void
    {
        $this->setMode('audit');
        $link = 'href="'.route('rfq.create').'"';

        foreach ([$this->est, $this->eng, $this->ownerA] as $u) {
            $this->page($u, 'rfq')->assertOk()->assertSee('No RFQs yet.')->assertSee('Send your first one.')->assertSee($link, false);
        }
        foreach ([$this->viewer, $this->adminA] as $u) {
            $this->page($u, 'rfq')->assertOk()->assertSee('No RFQs yet.')->assertDontSee('Send your first one.')->assertDontSee($link, false);
        }
    }

    // ---- (g) user-services --------------------------------------------------------

    public function test_g_user_services_create_pane_needs_product_management_s(): void
    {
        $this->setMode('audit');
        $fab = 'class="uss-new-fab"';
        $cancel = '<button type="button" class="uss-cancel"';
        $save = '<button type="button" class="uss-save"';
        $delete = 'id="serviceDeleteBtn"';

        $pm = $this->mkUser($this->orgA, 'product_manager');
        $steward = $this->mkUser($this->orgA, 'catalog_data_steward');

        $r = $this->page($pm, 'user-services')->assertOk();
        $r->assertSee($fab, false)->assertSee($cancel, false)->assertSee($save, false)->assertSee($delete, false)->assertSee('const CAN_CREATE = true;', false);

        $r = $this->page($steward, 'user-services')->assertOk();
        $r->assertSee($fab, false)->assertSee($cancel, false)->assertSee($save, false)->assertDontSee($delete, false)->assertSee('const CAN_CREATE = true;', false);

        foreach ([$this->viewer, $this->est] as $u) {
            $r = $this->page($u, 'user-services')->assertOk();
            $r->assertDontSee($fab, false)->assertDontSee($cancel, false)->assertDontSee($save, false)->assertDontSee($delete, false)
                ->assertSee('const CAN_CREATE = false;', false)
                ->assertSee('Select a service to view its details.', false)
                ->assertSee('if (delBtn) delBtn.style.display', false);
        }
    }

    public function test_g_user_services_create_route_stays_mapped_at_s(): void
    {
        $this->setMode('enforce');

        $this->asJ($this->viewer, 'POST', 'user-services', ['service_name' => 'x'])->assertForbidden();
        $this->assertSame(['product_management', 'S'], [config('route_permission_map')['POST user-services'][0], config('route_permission_map')['POST user-services'][1]]);
    }

    // ---- (h) also held by ---------------------------------------------------------

    public function test_h_owner_and_admin_slug_chips_show_a_view_only_co_holder_flag(): void
    {
        $this->setMode('audit');
        $a2 = $this->mkUser($this->orgA, 'organization_admin');
        $a2->forceFill(['name' => 'Second Admin'])->save();
        $this->adminA->forceFill(['name' => 'First Admin'])->save();
        $this->member($this->p1, $a2, $this->orgA);

        $r = $this->page($this->ownerA, 'org-admin')->assertOk();
        $html = $r->getContent();

        $this->assertSame(2, substr_count($html, '</i> also held by '), 'exactly the two organization_admin holders are flagged');
        $this->assertStringContainsString('also held by Second Admin', $html);
        $this->assertStringContainsString('also held by First Admin', $html);
        $this->assertStringContainsString('title="Also held by Second Admin"', $html);
        $this->assertStringNotContainsString('also held by '.$this->ownerA->name, $html);
        $this->assertStringNotContainsString('also held by '.$this->est->name, $html);
    }

    public function test_h_no_flag_for_a_sole_holder_or_for_a_role_without_owner_or_admin_in_its_slug(): void
    {
        $this->setMode('audit');

        $html = $this->page($this->ownerA, 'org-admin')->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, '</i> also held by '), 'estimator is held by several users but is not an owner/admin slug');
        $this->assertSame(1, DB::table('user_org_roles')->join('roles', 'roles.id', '=', 'user_org_roles.role_id')->where('roles.slug', 'organization_admin')->where('org_id', $this->orgA->id)->count());
    }

    public function test_h_flag_counts_extra_holders_and_ignores_holders_in_other_orgs(): void
    {
        $this->setMode('audit');
        $a2 = $this->mkUser($this->orgA, 'organization_admin');
        $a3 = $this->mkUser($this->orgA, 'organization_admin');
        $bAdmin = $this->mkUser($this->orgB, 'organization_admin');
        $this->assertGreaterThan(0, $bAdmin->id);

        $html = $this->page($this->ownerA, 'org-admin')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '</i> also held by '));
        $this->assertStringContainsString(' +1</small>', $html);
        $this->assertStringNotContainsString($bAdmin->name, $html);
    }
}

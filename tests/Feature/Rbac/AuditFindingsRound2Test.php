<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\ApiToken;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\Role;
use App\Models\Rbac\RolePermission;
use App\Models\User;
use App\Services\Rbac\DelegationService;
use App\Services\Rbac\PermissionService;
use App\Services\Rbac\ServiceAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verifier finding: in AUDIT mode several org-admin actions had no controller-level check. Each check
 * now mirrors the route's entry in config/route_permission_map.php. Every method is tested in audit and
 * enforce mode: below the level = 403 and the database is byte-identical afterwards; at the level = success.
 *
 * Grants (database/seeders/Rbac/data/role_permission_matrix.php):
 *   organization_owner  : UM F, DI F, AL R, OM F      organization_admin : UM F, DI O, AL R, OM F
 *   estimator           : UM F (no AL, DI, OM)        procurement_manager: UM R, AL R, OM R (no DI)
 *   auditor_read_all    : UM R, DI R, AL R, OM F      manufacturer_admin : UM F, AL R, OM F (no DI)
 *   viewer_read_only / project_engineer / procurement_coordinator: none of UM, AL, DI, OM
 *   sales_rep           : QR F, no UM
 */
class AuditFindingsRound2Test extends ProjectTestCase
{
    private User $ownerA;
    private User $adminA;
    private User $mfr;
    private User $proc;
    private User $coord;
    private User $eng;
    private User $auditor;
    private User $sales;
    private User $none;
    private User $dual;

    private const TABLES = [
        'users', 'organizations', 'roles', 'role_permissions', 'user_org_roles', 'role_assignment_logs', 'org_invites',
        'delegations', 'api_tokens', 'org_relationships', 'project_members', 'projects',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_08_03_135044_create_org_invites_table.php'))->up();
        Mail::fake();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->adminA = $this->mkUser($this->orgA, 'organization_admin');
        $this->mfr = $this->mkUser($this->orgA, 'manufacturer_admin');
        $this->proc = $this->mkUser($this->orgA, 'procurement_manager');
        $this->coord = $this->mkUser($this->orgA, 'procurement_coordinator');
        $this->eng = $this->mkUser($this->orgA, 'project_engineer');
        $this->auditor = $this->mkUser($this->orgA, 'auditor_read_all');
        $this->sales = $this->mkUser($this->orgA, 'sales_rep');
        $this->none = $this->mkUser();
        $this->dual = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->assignRole($this->dual, $this->orgB, 'organization_owner');
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function snapshot(): array
    {
        $out = [];
        foreach (self::TABLES as $t) {
            $out[$t] = DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $out;
    }

    private function req2(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        $org ??= DB::table('user_org_roles')->where('user_id', $u->id)->where('is_active', true)->value('org_id');
        $session = $org ? [config('rbac.current_org_session_key') => $org] : [];

        return $this->actingAs($u)->withSession($session)->json($method, $uri, $data);
    }

    private function delegationFrom(User $u): int
    {
        return (int) app(DelegationService::class)->grant($u->id, $u->id, $this->stranger->id, $this->orgA->id, now()->subHour(), now()->addDay())->id;
    }

    private function tokenFor(User $u): int
    {
        app(ServiceAccountService::class)->createToken($u->id, 'tok');

        return (int) ApiToken::where('user_id', $u->id)->latest('id')->value('id');
    }

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    /**
     * @return array<string, array{fn: \Closure, allowed: string[], denied: string[]}> case name => spec.
     * fn(User $actor): array [method, uri, data]; user names are properties of this test.
     */
    private function cases(): array
    {
        $low = ['viewer', 'coord', 'eng', 'none'];
        $um = fn (User $u) => ['GET', 'org-admin', []];

        return [
            'index' => ['fn' => fn () => ['GET', 'org-admin', []], 'allowed' => ['est', 'proc', 'ownerA'], 'denied' => [...$low, 'dual']],
            'rolesList' => ['fn' => fn () => ['GET', 'org-admin/roles-list', []], 'allowed' => ['est', 'proc', 'ownerA'], 'denied' => [...$low, 'dual']],
            'auditLog' => ['fn' => fn () => ['GET', 'org-admin/audit-log', []], 'allowed' => ['ownerA', 'adminA', 'proc', 'mfr'], 'denied' => ['viewer', 'est', 'eng', 'coord', 'none', 'dual']],
            'assignRole' => ['fn' => fn (User $u) => ['POST', 'org-admin/roles', ['user_id' => $u->id === $this->viewer->id ? $u->id : $this->stranger->id, 'role_id' => $u->id === $this->viewer->id ? $this->roleId('organization_owner') : $this->roleId('superintendent')]],
                'allowed' => ['est', 'ownerA'], 'denied' => ['viewer', 'proc', 'auditor', 'coord', 'eng', 'none', 'dual']],
            'peelOff' => ['fn' => fn () => ['POST', 'org-admin/peel-off', ['role_id' => $this->roleId('estimator')]], 'allowed' => ['est'], 'denied' => ['viewer', 'proc', 'auditor', 'eng', 'none', 'dual']],
            'removeRole' => ['fn' => fn () => ['DELETE', 'org-admin/roles/'.(int) DB::table('user_org_roles')->where('user_id', $this->super->id)->value('id'), []],
                'allowed' => ['est', 'ownerA'], 'denied' => ['viewer', 'proc', 'auditor', 'eng', 'none', 'dual']],
            'updateRolePermission' => ['fn' => fn () => ['PUT', 'org-admin/roles-list/permissions', ['role_id' => $this->roleId('viewer_read_only'), 'permission_group_id' => (int) DB::table('permission_groups')->value('id'), 'access_level' => 'F']],
                'allowed' => ['est', 'ownerA'], 'denied' => ['viewer', 'proc', 'auditor', 'eng', 'none', 'dual']],
            'storeRole' => ['fn' => fn () => ['POST', 'org-admin/roles-list/create', ['name' => 'Custom X', 'category' => 'Cross-Functional', 'phase' => 'P1']],
                'allowed' => ['est', 'ownerA'], 'denied' => ['viewer', 'proc', 'auditor', 'eng', 'none', 'dual']],
            'generateInvite' => ['fn' => fn () => ['POST', 'org-admin/invite', ['email' => 'new@example.test', 'name' => 'New', 'role_id' => $this->roleId('viewer_read_only'), 'send_email' => false]],
                'allowed' => ['est', 'ownerA'], 'denied' => ['viewer', 'proc', 'auditor', 'eng', 'none', 'dual']],
            'delegations.index' => ['fn' => fn () => ['GET', 'org-admin/delegations', []], 'allowed' => ['auditor', 'adminA', 'ownerA'], 'denied' => ['viewer', 'est', 'proc', 'coord', 'none', 'dual']],
            'delegations.store' => ['fn' => fn () => ['POST', 'org-admin/delegations', ['to_user_id' => $this->stranger->id, 'starts_at' => now()->subHour()->toDateTimeString(), 'expires_at' => now()->addDay()->toDateTimeString()]],
                'allowed' => ['adminA', 'ownerA'], 'denied' => ['auditor', 'viewer', 'est', 'proc', 'none', 'dual']],
            'delegations.destroy' => ['fn' => fn (User $u) => ['DELETE', 'org-admin/delegations/'.($u->id === $this->none->id ? 999 : $this->delegationFrom($u)), []],
                'allowed' => ['adminA', 'ownerA'], 'denied' => ['auditor', 'viewer', 'est', 'proc', 'dual']],
            'apiTokens.index' => ['fn' => fn () => ['GET', 'org-admin/api-tokens', []], 'allowed' => ['auditor', 'adminA', 'ownerA'], 'denied' => ['viewer', 'est', 'proc', 'coord', 'none', 'dual']],
            'apiTokens.store' => ['fn' => fn () => ['POST', 'org-admin/api-tokens', ['name' => 'ci']], 'allowed' => ['ownerA'], 'denied' => ['adminA', 'auditor', 'viewer', 'est', 'proc', 'none', 'dual']],
            'apiTokens.destroy' => ['fn' => fn (User $u) => ['DELETE', 'org-admin/api-tokens/'.($u->id === $this->none->id ? 999 : $this->tokenFor($u)), []],
                'allowed' => ['ownerA'], 'denied' => ['adminA', 'auditor', 'viewer', 'est', 'proc', 'dual']],
            'settings.index' => ['fn' => fn () => ['GET', 'org-admin/settings', []], 'allowed' => ['proc', 'auditor', 'adminA', 'ownerA'], 'denied' => ['viewer', 'est', 'eng', 'coord', 'none', 'dual']],
            'settings.transfer' => ['fn' => fn () => ['POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->est->email]],
                'allowed' => ['ownerA'], 'denied' => ['viewer', 'proc', 'auditor', 'adminA', 'est', 'eng', 'none', 'dual']],
        ];
    }

    private function actor(string $name): User
    {
        return $this->$name;
    }

    public static function caseNames(): array
    {
        $names = ['index', 'rolesList', 'auditLog', 'assignRole', 'peelOff', 'removeRole', 'updateRolePermission', 'storeRole', 'generateInvite',
            'delegations.index', 'delegations.store', 'delegations.destroy', 'apiTokens.index', 'apiTokens.store', 'apiTokens.destroy', 'settings.index', 'settings.transfer'];
        $out = [];
        foreach ($names as $n) {
            foreach (['audit', 'enforce'] as $mode) {
                $out["$n $mode"] = [$n, $mode];
            }
        }

        return $out;
    }

    #[DataProvider('caseNames')]
    public function test_below_the_level_is_403_and_writes_nothing_then_the_level_succeeds(string $case, string $mode): void
    {
        $this->setMode($mode);
        $spec = $this->cases()[$case];

        $before = $this->snapshot();
        foreach ($spec['denied'] as $name) {
            $u = $this->actor($name);
            [$method, $uri, $data] = $spec['fn']($u);
            if ($case === 'delegations.destroy' || $case === 'apiTokens.destroy') {
                $before = $this->snapshot();
            }
            $r = $this->req2($u, $method, $uri, $data, $name === 'dual' ? $this->orgA->id : null);
            if ($name === 'none') {
                $this->assertContains($r->getStatusCode(), [302, 403], "$case none: {$r->getStatusCode()}");
            } else {
                $this->assertSame(403, $r->getStatusCode(), "$case $name ($mode) must be 403, got {$r->getStatusCode()}");
            }
            $this->assertSame($before, $this->snapshot(), "$case $name ($mode) changed the database");
        }

        $u = $this->actor($spec['allowed'][0]);
        [$method, $uri, $data] = $spec['fn']($u);
        $r = $this->req2($u, $method, $uri, $data);
        $this->assertLessThan(400, $r->getStatusCode(), "$case {$spec['allowed'][0]} ($mode) must succeed, got {$r->getStatusCode()}");
    }

    #[DataProvider('modes')]
    public function test_every_listed_allowed_user_passes(string $mode): void
    {
        $this->setMode($mode);
        foreach (['index', 'rolesList', 'auditLog', 'delegations.index', 'apiTokens.index', 'settings.index'] as $case) {
            $spec = $this->cases()[$case];
            foreach ($spec['allowed'] as $name) {
                [$m, $uri, $d] = $spec['fn']($this->actor($name));
                $this->assertLessThan(400, $this->req2($this->actor($name), $m, $uri, $d)->getStatusCode(), "$case $name ($mode)");
            }
        }
    }

    #[DataProvider('modes')]
    public function test_self_assigning_owner_by_a_viewer_creates_no_row_and_the_matrix_is_untouched(string $mode): void
    {
        $this->setMode($mode);
        $owner = $this->roleId('organization_owner');
        $rp = RolePermission::count();

        $this->req2($this->viewer, 'POST', 'org-admin/roles', ['user_id' => $this->viewer->id, 'role_id' => $owner])->assertForbidden();
        $this->req2($this->viewer, 'PUT', 'org-admin/roles-list/permissions', ['role_id' => $this->roleId('viewer_read_only'), 'permission_group_id' => (int) DB::table('permission_groups')->value('id'), 'access_level' => 'F'])->assertForbidden();
        $this->req2($this->viewer, 'POST', 'org-admin/invite', ['email' => 'x@example.test', 'name' => 'X', 'role_id' => $owner])->assertForbidden();

        $this->assertFalse(DB::table('user_org_roles')->where('user_id', $this->viewer->id)->where('role_id', $owner)->exists());
        $this->assertSame($rp, RolePermission::count());
        $this->assertSame(0, DB::table('org_invites')->count());
    }

    #[DataProvider('modes')]
    public function test_wrong_group_is_denied_for_each_group(string $mode): void
    {
        $this->setMode($mode);
        $this->req2($this->proc, 'POST', 'org-admin/roles', ['user_id' => $this->stranger->id, 'role_id' => $this->roleId('superintendent')])->assertForbidden();
        $this->req2($this->est, 'GET', 'org-admin/audit-log')->assertForbidden();
        $this->req2($this->proc, 'GET', 'org-admin/delegations')->assertForbidden();
        $this->req2($this->est, 'GET', 'org-admin/settings')->assertForbidden();
        $this->req2($this->mfr, 'POST', 'org-admin/delegations', ['to_user_id' => $this->stranger->id, 'starts_at' => now()->toDateTimeString(), 'expires_at' => now()->addDay()->toDateTimeString()])->assertForbidden();
    }

    #[DataProvider('modes')]
    public function test_removing_a_role_row_of_another_org_is_refused(string $mode): void
    {
        $this->setMode($mode);
        $rowB = (int) DB::table('user_org_roles')->where('user_id', $this->outsider->id)->value('id');
        $before = $this->snapshot();

        $this->req2($this->ownerA, 'DELETE', "org-admin/roles/$rowB")->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('modes')]
    public function test_settings_destroy_needs_user_management_f_and_the_owner_role(string $mode): void
    {
        $this->setMode($mode);
        $orgC = $this->mkOrg('Org C');
        $ownerC = $this->mkUser($orgC, 'organization_owner');
        $adminC = $this->mkUser($orgC, 'organization_admin');
        $estC = $this->mkUser($orgC, 'estimator');
        $viewerC = $this->mkUser($orgC, 'viewer_read_only');
        $before = $this->snapshot();

        foreach ([$adminC, $estC, $viewerC, $this->none] as $u) {
            $r = $this->req2($u, 'DELETE', 'org-admin/settings/delete');
            $this->assertContains($r->getStatusCode(), $u->id === $this->none->id ? [302, 403] : [403]);
            $this->assertSame($before, $this->snapshot());
        }

        $this->actingAs($ownerC)->delete('org-admin/settings/delete')->assertRedirect(route('user.dashboard'));
        $this->assertNull(DB::table('organizations')->where('id', $orgC->id)->first());
        $this->assertNotNull(DB::table('organizations')->where('id', $this->orgA->id)->first());
    }

    // ---- customers ----------------------------------------------------------------

    public function test_customers_show_maps_to_quote_rfq_management_r_and_writes_are_unchanged(): void
    {
        $map = config('route_permission_map');

        $this->assertSame(['quote_rfq_management', 'R', 'read'], [$map['GET customers/{id}'][0], $map['GET customers/{id}'][1], $map['GET customers/{id}']['batch']]);
        $this->assertSame([['user_management', 'S', 'write'], ['user_management', 'O', 'write'], ['user_management', 'F', 'approve']], [
            [$map['POST customers'][0], $map['POST customers'][1], $map['POST customers']['batch']],
            [$map['PUT customers/{id}'][0], $map['PUT customers/{id}'][1], $map['PUT customers/{id}']['batch']],
            [$map['DELETE customers/{id}'][0], $map['DELETE customers/{id}'][1], $map['DELETE customers/{id}']['batch']],
        ]);
    }

    public function test_customer_show_in_enforce_follows_quote_rfq_management_not_user_management(): void
    {
        Schema::create('customer_activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });
        $this->setMode('enforce');
        foreach ([$this->sales, $this->viewer, $this->super] as $i => $u) {
            DB::table('customers')->insert(['id' => 10 + $i, 'user_id' => $u->id, 'company_name' => "C$i", 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->req2($this->sales, 'GET', 'customers/10')->assertOk();
        $this->req2($this->viewer, 'GET', 'customers/11')->assertOk();
        $this->req2($this->super, 'GET', 'customers/12')->assertForbidden();
    }

    public function test_sidebar_customers_link_and_page_for_quote_rfq_r_without_user_management(): void
    {
        $link = '<div data-i18n="Customers">Customers</div>';

        $this->actingAs($this->sales)->get('org-admin/my-roles')->assertOk()->assertSee($link, false);
        $this->actingAs($this->viewer)->get('org-admin/my-roles')->assertOk()->assertSee($link, false);
        $this->actingAs($this->super)->get('org-admin/my-roles')->assertOk()->assertDontSee($link, false);

        $html = $this->actingAs($this->sales)->get('customers')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/canAddCustomer\s*=\s*false/', $html);
        $this->assertMatchesRegularExpression('/canEditCustomer\s*=\s*false/', $html);
        $this->assertMatchesRegularExpression('/canDeleteCustomer\s*=\s*false/', $html);

        $html = $this->actingAs($this->est)->get('customers')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/canAddCustomer\s*=\s*true/', $html);
    }

    // ---- generic guard --------------------------------------------------------------

    private const GUARDED = [
        'App\Http\Controllers\Frontend\OrgAdminController',
        'App\Http\Controllers\Frontend\DelegationController',
        'App\Http\Controllers\Frontend\ApiTokenController',
        'App\Http\Controllers\Frontend\OrgSettingsController',
    ];

    /** Concrete URI for a mapped route; unknown parameters fail the test so a new route forces an update here. */
    private function concreteUri(string $uri, User $viewer, array &$unknown): string
    {
        $cache = [];
        $resolvers = [
            'userOrgRole' => fn () => (int) DB::table('user_org_roles')->where('org_id', $this->orgA->id)->where('user_id', $this->super->id)->value('id'),
            'orgRelationship' => fn () => (int) DB::table('org_relationships')->insertGetId(['from_org_id' => $this->orgA->id, 'to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]),
            'projectMember' => fn () => $this->memberRowId($this->p1, $this->est),
            'delegation' => fn () => $this->delegationFrom($viewer),
            'apiToken' => fn () => $this->tokenFor($viewer),
        ];

        return preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($resolvers, &$unknown) {
            if (! isset($resolvers[$m[1]])) {
                $unknown[] = $m[1];

                return '0';
            }

            return (string) $resolvers[$m[1]]();
        }, $uri);
    }

    private function memberRowId(int $projectId, User $u): int
    {
        return (int) DB::table('project_members')->where('project_id', $projectId)->where('user_id', $u->id)->value('id');
    }

    #[DataProvider('modes')]
    public function test_guard_no_mapped_org_admin_route_serves_a_viewer_the_map_denies(string $mode): void
    {
        $this->setMode($mode);
        $perm = app(PermissionService::class);
        $exercised = [];
        $unknown = [];
        $skipped = [];

        foreach (config('route_permission_map') as $key => $rule) {
            [$method, $uri] = explode(' ', $key, 2);
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri && in_array($method, $r->methods(), true));
            if (! $route || ! in_array(ltrim((string) $route->getControllerClass(), '\\'), self::GUARDED, true)) {
                continue;
            }
            if ($perm->checkPermission($this->viewer->id, $this->orgA->id, $rule[0], $rule[1])) {
                $skipped[] = $key;
                continue;
            }

            $before = $this->snapshot();
            $url = $this->concreteUri($uri, $this->viewer, $unknown);
            $snapAfterSetup = $this->snapshot();
            $r = $this->req2($this->viewer, $method, $url, []);

            $this->assertSame(403, $r->getStatusCode(), "$key served viewer_read_only with {$r->getStatusCode()} ($mode)");
            $this->assertSame($snapAfterSetup, $this->snapshot(), "$key changed the database ($mode)");
            $exercised[] = $key;
        }

        $this->assertSame([], $unknown, 'route parameters the guard cannot resolve; add a resolver');
        $this->assertSame(['GET org-admin/projects'], $skipped, 'the only guarded route a viewer may legitimately read (project_management R)');
        $this->assertCount(25, $exercised, implode(', ', $exercised));
        foreach (['POST org-admin/roles', 'PUT org-admin/roles-list/permissions', 'POST org-admin/invite', 'POST org-admin/delegations', 'POST org-admin/api-tokens', 'DELETE org-admin/settings/delete', 'GET org-admin/audit-log'] as $must) {
            $this->assertContains($must, $exercised);
        }
    }

    public function test_guard_every_route_of_the_four_controllers_is_mapped_or_explicitly_open(): void
    {
        $map = config('route_permission_map');
        $open = ['GET org-admin/my-roles'];
        $unmapped = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array(ltrim((string) $route->getControllerClass(), '\\'), self::GUARDED, true)) {
                continue;
            }
            foreach ($route->methods() as $m) {
                $key = "$m {$route->uri()}";
                if ($m !== 'HEAD' && ! isset($map[$key]) && ! in_array($key, $open, true)) {
                    $unmapped[] = $key;
                }
            }
        }

        $this->assertSame([], $unmapped);
    }

    // ---- every mapped route (P2-A) ---------------------------------------------------

    /**
     * Authenticated routes that are intentionally NOT in config/route_permission_map.php, with the reason.
     * Any other authenticated route without a map entry fails test_guard_no_authenticated_route_is_silently_unmapped.
     */
    private const OPEN_ROUTES = [
        'POST logout' => 'ends the caller\'s own session',
        'GET password/confirm' => 'Laravel auth scaffolding (self-service, own credentials)',
        'POST password/confirm' => 'Laravel auth scaffolding (self-service, own credentials)',
        'GET email/verify' => 'Laravel auth scaffolding (own account)',
        'POST email/resend' => 'Laravel auth scaffolding (own account)',
        'GET register-complete' => 'registration wizard for the caller\'s own new account',
        'GET user-dashboard' => 'landing page for any org member; lists only the caller\'s own data',
        'GET workspace' => 'static workspace view for any org member',
        'POST org/switch' => 'switches between orgs the caller already belongs to (membership is verified)',
        'GET org-admin/my-roles' => 'self-scope: caller\'s own roles and delegations',
        'GET get-list-count' => 'navbar badge on every page; caller\'s own list count',
        'GET profile' => 'caller\'s own profile',
        'PUT profile' => 'caller\'s own profile',
    ];

    /**
     * Mapped reads that are NOT hard-denied in audit mode. They scope their data to the caller's visible projects
     * (Project::visibleTo / Quote::visibleTo), and existing suites (ProjectReadPathsTest, ProjectCheckpoint2Test,
     * ProjectDashboardCountersTest, AuditFindingsTest) pin that behaviour: an org member without the level gets an
     * empty result or a 404 in audit mode, and the map's 403 only in enforce mode. Awaiting an Architect decision on
     * whether to add a hard 403 here (ruled: accepted as is). The guard still requires that these leak no fixture data.
     *
     * Also accepted as is: writes on project/quote/RFQ/crosswalk-scoped objects resolve visibility first, so a hidden
     * object answers 404 instead of 403 (still a denial, nothing written); see $hiddenObject in the guard below.
     */
    private const DATA_SCOPED_READS = [
        'GET quotes/list', 'GET quotes/{id}/details', 'GET quotes/{id}/pdf', 'GET quotes/{id}/pdf-preview',
        'GET projects', 'GET projects/list', 'GET projects/{project}', 'GET projects/{quote}/workspace', 'GET plan-crosswalk',
    ];

    private const SNAPSHOT_TABLES = [
        'quotes', 'quote_items', 'customers', 'saved_lists', 'plan_crosswalk', 'rfq_requests', 'rfq_responses', 'orders',
        'project_member_logs',
    ];

    private function fullSnapshot(): array
    {
        $out = $this->snapshot();
        foreach (self::SNAPSHOT_TABLES as $t) {
            if (Schema::hasTable($t)) {
                $out[$t] = DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
            }
        }

        return $out;
    }

    private function mappedRoutes(): array
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $out = [];
        foreach (config('route_permission_map') as $key => $rule) {
            [$method, $uri] = explode(' ', $key, 2);
            $route = $routes->first(fn ($r) => $r->uri() === $uri && ($method === '*' || in_array($method, $r->methods(), true)));
            $out[$key] = ['method' => $method === '*' ? 'GET' : $method, 'uri' => $uri, 'rule' => $rule, 'route' => $route];
        }

        return $out;
    }

    /** @return array<string, int|string> */
    private function mappedFixtures(User $viewer): array
    {
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->unsignedBigInteger('org_id')->nullable();
                $t->string('order_number')->nullable();
                $t->string('status')->nullable();
                $t->timestamps();
            });
        }

        foreach (DB::table('projects')->pluck('id') as $id) {
            DB::table('projects')->where('id', $id)->update(['name' => "LEAK-P$id"]);
        }
        foreach (DB::table('quotes')->pluck('id') as $id) {
            DB::table('quotes')->where('id', $id)->update(['quote_number' => "LEAK-Q$id"]);
        }

        $now = now();
        $rfq = (int) DB::table('rfq_requests')->insertGetId([
            'org_id' => $this->orgA->id, 'created_by' => $this->est->id, 'project_id' => $this->p1, 'title' => 'R', 'status' => 'sent',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return [
            'id' => 1,
            'quoteId' => $this->q1,
            'quote' => $this->q1,
            'itemId' => 1,
            'listId' => 1,
            'slug' => 'x',
            'project' => $this->p1,
            'projectMember' => $this->memberRowId($this->p1, $this->est),
            'order' => (int) DB::table('orders')->insertGetId(['user_id' => $this->est->id, 'org_id' => $this->orgA->id, 'order_number' => 'O-1', 'status' => 'pending_approval', 'created_at' => $now, 'updated_at' => $now]),
            'rfq' => $rfq,
            'response' => (int) DB::table('rfq_responses')->insertGetId(['rfq_request_id' => $rfq, 'seller_org_id' => $this->orgB->id, 'created_by' => $this->outsider->id, 'total_price' => 1, 'created_at' => $now, 'updated_at' => $now]),
            'planCrosswalk' => (int) DB::table('plan_crosswalk')->insertGetId(['org_id' => $this->orgA->id, 'quote_id' => $this->q1, 'project_id' => $this->p1, 'plan_line_code' => 'LEAK-L1', 'created_by' => $this->est->id, 'created_at' => $now, 'updated_at' => $now]),
            'userOrgRole' => (int) DB::table('user_org_roles')->where('org_id', $this->orgA->id)->where('user_id', $this->super->id)->value('id'),
            'orgRelationship' => (int) DB::table('org_relationships')->insertGetId(['from_org_id' => $this->orgA->id, 'to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]),
            'delegation' => $this->delegationFrom($viewer),
            'apiToken' => $this->tokenFor($viewer),
        ];
    }

    private function mappedUri(string $uri, array $fixtures): string
    {
        return preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($fixtures, $uri) {
            $this->assertArrayHasKey($m[1], $fixtures, "no fixture for {{$m[1]}} in $uri; add one so the guard can reach the check");

            return (string) $fixtures[$m[1]];
        }, $uri);
    }

    private function isPlatformAdminRoute(array $entry): bool
    {
        return $entry['route'] !== null && in_array('checkRole:admin', $entry['route']->gatherMiddleware(), true);
    }

    #[DataProvider('modes')]
    public function test_guard_every_mapped_route_denies_roles_below_its_level_and_writes_nothing(string $mode): void
    {
        $this->setMode($mode);
        $lowest = $this->mkUser($this->orgA, 'inventory_manager');
        $fixtures = $this->mappedFixtures($this->viewer);
        $perm = app(PermissionService::class);
        $quoteProject = [$this->q1 => $this->p1];
        $exercised = ['viewer' => 0, 'lowest' => 0];
        $viewerSkipped = [];
        $unresolved = [];
        $covered = 0;

        foreach ($this->mappedRoutes() as $key => $e) {
            if ($e['route'] === null) {
                $unresolved[] = $key;
                continue;
            }
            if ($this->isPlatformAdminRoute($e) || ! method_exists($e['route']->getControllerClass() ?? '', $e['route']->getActionMethod())) {
                continue;
            }

            $covered++;
            $rule = $e['rule'];
            $projectId = isset($rule['project_param']) ? $this->p1 : (isset($rule['quote_param']) ? $quoteProject[$this->q1] : null);
            $url = $this->mappedUri($e['uri'], $fixtures);

            foreach (['viewer' => $this->viewer, 'lowest' => $lowest] as $label => $actor) {
                $grants = $perm->checkPermission($actor->id, $this->orgA->id, $rule[0], $rule[1], $projectId);
                if ($grants) {
                    $this->assertSame('viewer', $label, "$key: the lowest role must hold none of the mapped groups");
                    $viewerSkipped[] = $key;
                    continue;
                }

                $before = $this->fullSnapshot();
                $r = $this->req2($actor, $e['method'], $url, []);
                $status = $r->getStatusCode();

                // Writes on project/quote/RFQ-scoped objects resolve visibility first, so a hidden object is a 404 (still a denial, nothing written).
                $hiddenObject = isset($rule['quote_param']) || ($label === 'lowest' && (isset($rule['project_param']) || str_contains($e['uri'], '{rfq}') || str_contains($e['uri'], '{planCrosswalk}')));
                if (in_array($key, self::DATA_SCOPED_READS, true)) {
                    $this->assertContains($status, [200, 403, 404], "$key ($mode)");
                    $this->assertStringNotContainsString('LEAK-', (string) $r->getContent(), "$key leaked project data to $label ($mode)");
                } else {
                    $this->assertContains($status, $hiddenObject ? [403, 404] : [403], "$key served $label with $status ($mode)");
                }
                $this->assertSame($before, $this->fullSnapshot(), "$key changed the database for $label ($mode)");
                $exercised[$label]++;
            }
        }

        $this->assertSame([], $unresolved, 'mapped routes that do not exist; fix the map or the route');
        foreach ($viewerSkipped as $key) {
            $this->assertSame('R', config('route_permission_map')[$key][1], "$key is a state-changing or higher-level route that viewer_read_only was NOT denied");
        }
        $this->assertSame($covered, $exercised['lowest']);
        $this->assertSame($covered, $exercised['viewer'] + count($viewerSkipped));
        $this->assertGreaterThan(100, $covered);
    }

    public function test_guard_the_actors_of_these_guards_are_not_universal_admins_and_the_list_is_empty_by_default(): void
    {
        $this->assertSame([], config('rbac.universal_admin_user_ids'), 'tests must start with the feature off');
        foreach ([$this->viewer, $this->mkUser($this->orgA, 'inventory_manager'), $this->est, $this->stranger] as $u) {
            $this->assertFalse(\App\Support\Rbac\UniversalAdmin::is($u));
        }
    }

    /**
     * A listed, verified universal admin passes every mapped route (not denied) in both modes, apart from the
     * routes the plan deliberately keeps owner-only. Anything else answering 403 means a mapped route is not
     * reached by the central bypass (a controller that checks a role directly, for instance).
     */
    #[DataProvider('modes')]
    public function test_guard_a_listed_universal_admin_is_not_denied_on_any_mapped_route(string $mode): void
    {
        $this->setMode($mode);
        $ua = $this->mkUser();
        config(['rbac.universal_admin_user_ids' => [$ua->id]]);
        $fixtures = $this->mappedFixtures($this->viewer);
        $ownerOnly = [];
        $checked = 0;
        $denied = [];

        foreach ($this->mappedRoutes() as $key => $e) {
            if ($e['route'] === null || $this->isPlatformAdminRoute($e) || ! method_exists($e['route']->getControllerClass() ?? '', $e['route']->getActionMethod())) {
                continue;
            }

            // {id} on quotes/* must be a quote with a project: a NULL-project quote fails closed as project_unresolved for everyone.
            $uriFixtures = str_starts_with($e['uri'], 'quotes/') ? ['id' => $this->q1] + $fixtures : $fixtures;
            $r = $this->req2($ua, $e['method'], $this->mappedUri($e['uri'], $uriFixtures), [], $this->orgA->id);
            $checked++;
            if (in_array($key, $ownerOnly, true)) {
                $this->assertSame(403, $r->getStatusCode(), "$key stays owner-only for a universal admin ($mode)");
                continue;
            }
            if ($r->getStatusCode() === 403) {
                $denied[] = $key;
            }
        }

        $this->assertSame([], $denied, "mapped routes that denied a listed universal admin ($mode)");
        $this->assertGreaterThan(100, $checked);
    }

    public function test_guard_mapped_platform_admin_routes_sit_behind_check_role_admin(): void
    {
        $unguarded = [];
        foreach ($this->mappedRoutes() as $key => $e) {
            if (str_starts_with($e['uri'], 'admin/') && $e['route'] !== null && ! $this->isPlatformAdminRoute($e)) {
                $unguarded[] = $key;
            }
        }
        $this->assertSame([], $unguarded);

        $this->setMode('audit');
        $before = $this->fullSnapshot();
        foreach (['GET admin/dashboard', 'GET admin/rbac', 'POST admin/rbac/enforcement/toggle-mode'] as $key) {
            [$method, $uri] = explode(' ', $key, 2);
            $r = $this->req2($this->viewer, $method, $uri, ['mode' => 'enforce']);
            $this->assertContains($r->getStatusCode(), [302, 403], "$key served an org user with {$r->getStatusCode()}");
        }
        $this->assertSame($before, $this->fullSnapshot());
        $this->assertSame('audit', \App\Models\Rbac\RbacSetting::get('rbac_mode'));
    }

    public function test_guard_no_authenticated_route_is_silently_unmapped(): void
    {
        $map = config('route_permission_map');
        $silent = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }
            foreach ($route->methods() as $m) {
                $key = "$m {$route->uri()}";
                if ($m !== 'HEAD' && ! isset($map[$key]) && ! isset($map["* {$route->uri()}"]) && ! isset(self::OPEN_ROUTES[$key])) {
                    $silent[] = $key;
                }
            }
        }

        $this->assertSame([], $silent, 'authenticated routes need a route_permission_map entry (and a controller check) or an OPEN_ROUTES entry with a reason');
    }
}

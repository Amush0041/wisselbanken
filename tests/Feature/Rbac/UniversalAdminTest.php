<?php

namespace Tests\Feature\Rbac;

use App\Models\Project;
use App\Models\Quote;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\ApprovalRoutingService;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use App\Support\Rbac\UniversalAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Universal (platform) admin: plan .claude/tasks/universal-admin/PLAN.md sections 7 and 11.
 *
 * The system is in production and the hard requirement is that NOTHING changes for non-listed users, so part (a)
 * runs every no-regression check with the allow-list empty AND with it holding somebody else's id. Part (b) covers
 * the listed, verified user. Fixture: ProjectTestCase (projects P1, P2 in org A, P3 in org B; est, viewer, stranger,
 * outsider, multi, owner), plus the extra actors below.
 */
class UniversalAdminTest extends ProjectTestCase
{
    private const NO_APPROVER = 'Your organization has no one who can approve orders. Ask an organization owner to assign an Executive Approver (or another role with approval authority), then try again.';

    /** listed, verified, holds no role and no membership anywhere */
    private User $ua;
    /** a different account that is the only listed id in the "someone else" variant */
    private User $other;
    /** organization_owner in A, member of P1 only */
    private User $ownerA;
    /** users.role = 'admin', not listed, no org role */
    private User $adminAcct;
    /** procurement_manager (approval_authority A) in A, member of P1 */
    private User $procMgr;
    /** organization_owner in A (approval_authority A), member of P1 */
    private User $orgOwner;
    private User $sellerRep;
    private $sellerOrg;
    private int $variationColor;
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createStubs();

        $this->ua = User::factory()->create(['role' => 'user']);
        $this->other = User::factory()->create(['role' => 'user']);
        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->adminAcct = User::factory()->create(['role' => 'admin']);
        $this->procMgr = $this->mkUser($this->orgA, 'procurement_manager');
        $this->orgOwner = $this->mkUser($this->orgA, 'organization_owner');
        foreach ([$this->ownerA, $this->procMgr, $this->orgOwner] as $u) {
            $this->member($this->p1, $u, $this->orgA);
        }

        $this->sellerOrg = $this->mkOrg('Seller Org');
        $this->sellerRep = $this->mkUser($this->sellerOrg, 'sales_rep');
        DB::table('org_relationships')->insert([
            'from_org_id' => $this->orgA->id, 'to_org_id' => $this->sellerOrg->id, 'relationship_type' => 'buyer_seller',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->logPath = sys_get_temp_dir().'/ua-test-'.uniqid().'.log';
        config(['logging.channels.universal_admin' => ['driver' => 'single', 'path' => $this->logPath, 'level' => 'info']]);
        Log::forgetChannel('universal_admin');

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
        parent::tearDown();
    }

    // ---- fixtures / helpers ---------------------------------------------------------------------

    private function createStubs(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('org_id')->nullable();
            $t->string('order_number')->unique();
            $t->string('name')->nullable();
            $t->string('project_title')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('company')->nullable();
            $t->string('address1')->nullable();
            $t->string('address2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postcode')->nullable();
            $t->text('notes')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('tax', 10, 2)->default(0);
            $t->decimal('tax_rate', 5, 3)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->unsignedBigInteger('rejected_by')->nullable();
            $t->text('approval_note')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_variation_color_id')->nullable();
            $t->integer('quantity')->default(1);
            $t->decimal('price', 10, 2)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('product_variations', function (Blueprint $t) {
            $t->id();
            $t->decimal('pricing', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('product_variation_color', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_variation_id');
            $t->unsignedBigInteger('color_id')->nullable();
            $t->timestamps();
        });
        Schema::create('palletes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('product_variation_color_id')->nullable();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->unsignedBigInteger('pallet_address_id')->nullable();
            $t->integer('quantity')->default(1);
            $t->timestamps();
        });
        Schema::create('state_taxes', function (Blueprint $t) {
            $t->id();
            $t->string('state')->nullable();
            $t->decimal('state_tax_rate', 8, 3)->nullable();
            $t->decimal('avg_local_tax_rate', 8, 3)->nullable();
            $t->decimal('max_local', 8, 3)->nullable();
            $t->decimal('combined_tax_rate', 8, 3)->nullable();
            $t->timestamps();
        });
        Schema::create('saved_list_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('saved_list_id')->nullable();
            $t->timestamps();
        });
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($d, $f) => date(str_replace(['%Y', '%m'], ['Y', 'm'], $f), strtotime($d)), 2);

        $pv = DB::table('product_variations')->insertGetId(['pricing' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $this->variationColor = (int) DB::table('product_variation_color')->insertGetId([
            'product_variation_id' => $pv, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    /** No-regression runs with the list empty and with somebody else's id, each in both RBAC modes. */
    public static function variants(): array
    {
        $out = [];
        foreach (['empty', 'other'] as $variant) {
            foreach (['audit', 'enforce'] as $mode) {
                $out["list $variant, $mode"] = [$variant, $mode];
            }
        }

        return $out;
    }

    private function applyVariant(string $variant, string $mode): void
    {
        config(['rbac.universal_admin_user_ids' => $variant === 'empty' ? [] : [$this->other->id]]);
        $this->setMode($mode);
    }

    private function listed(User ...$users): void
    {
        config(['rbac.universal_admin_user_ids' => array_map(fn (User $u) => $u->id, $users)]);
    }

    private function key(): string
    {
        return config('rbac.current_org_session_key');
    }

    private function orgSession(int $orgId): array
    {
        return [$this->key() => $orgId];
    }

    private function req(User $u, string $method, string $uri, array $data = [], ?int $org = null)
    {
        return $this->actingAs($u)->withSession($org ? $this->orgSession($org) : [])->json($method, $uri, $data);
    }

    private function universalRows()
    {
        return AuditLog::where('outcome', 'allowed_universal_admin')->orderBy('id')->get();
    }

    private function rows(string $table): int
    {
        return DB::table($table)->count();
    }

    private function allGroups(): array
    {
        return DB::table('permission_groups')->pluck('slug')->all();
    }

    private function mkRfq(?int $projectId, string $status = 'sent', array $attrs = []): int
    {
        return (int) DB::table('rfq_requests')->insertGetId($attrs + [
            'org_id' => $this->orgA->id, 'project_id' => $projectId, 'created_by' => $this->est->id,
            'title' => 'RFQ '.uniqid(), 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function convertible(int $projectId): int
    {
        $rfq = $this->mkRfq($projectId, 'closed');
        DB::table('rfq_recipients')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'status' => 'responded',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rfq_responses')->insert([
            'rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'created_by' => $this->sellerRep->id,
            'total_price' => 250, 'status' => 'selected', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $rfq;
    }

    private function mkOrder(int $orgId, User $owner, string $status = 'pending_approval'): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => $owner->id, 'org_id' => $orgId, 'order_number' => 'ORD-'.uniqid(), 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function validCheckout(): array
    {
        return [
            'name' => 'Jobsite GC', 'project_name' => 'Tower', 'email' => 'a@b.test', 'phone' => '555',
            'address1' => '1 Main', 'city' => 'Town', 'state' => 'TX', 'postcode' => '75001',
        ];
    }

    private function stockPallet(User $u): void
    {
        DB::table('palletes')->insert([
            'user_id' => $u->id, 'product_variation_color_id' => $this->variationColor, 'quantity' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function checkout(User $u, int $sessionOrg)
    {
        return $this->actingAs($u)->withSession($this->orgSession($sessionOrg))
            ->from('/checkout')->post(route('checkout.process'), $this->validCheckout());
    }

    private function logContents(): string
    {
        return is_file($this->logPath) ? (string) file_get_contents($this->logPath) : '';
    }

    // =================================================================================================
    // (a) NO REGRESSION for non-listed users
    // =================================================================================================

    #[DataProvider('variants')]
    public function test_a_non_listed_users_are_never_universal_admins(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);

        foreach ([$this->ownerA, $this->adminAcct, $this->viewer, $this->est, $this->stranger, $this->outsider] as $u) {
            $this->assertFalse(UniversalAdmin::is($u->id), "user {$u->id}");
            $this->assertFalse(UniversalAdmin::is($u), "user {$u->id}");
        }
        $this->assertSame($variant === 'other', UniversalAdmin::is($this->other));
        $this->assertFalse(UniversalAdmin::is($this->ua));
        $this->assertFalse(UniversalAdmin::is(null));
    }

    #[DataProvider('variants')]
    public function test_a_owner_admin_account_and_viewer_are_denied_cross_org_and_cross_project(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $perm = app(PermissionService::class);

        foreach ($this->allGroups() as $g) {
            $this->assertFalse($perm->checkPermission($this->ownerA->id, $this->orgB->id, $g, 'R'), "owner A in org B: $g");
            $this->assertFalse($perm->checkPermission($this->adminAcct->id, $this->orgA->id, $g, 'R'), "role=admin account in org A: $g");
            $this->assertFalse($perm->checkPermission($this->adminAcct->id, $this->orgB->id, $g, 'R'), "role=admin account in org B: $g");
            $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgA->id, $g, 'R'), "unlisted ua in org A: $g");
            $this->assertFalse($perm->checkPermission($this->viewer->id, $this->orgB->id, $g, 'R'), "viewer in org B: $g");
        }

        $this->assertFalse($perm->checkPermission($this->viewer->id, $this->orgA->id, 'user_management', 'F'));
        $this->assertFalse($perm->checkPermission($this->ownerA->id, $this->orgA->id, 'project_management', 'R', $this->p2), 'owner A is not a member of P2');
        $this->assertFalse($perm->checkPermission($this->ownerA->id, $this->orgA->id, 'project_management', 'R', $this->p3), 'P3 is in org B');
        $this->assertFalse($perm->checkPermission($this->stranger->id, $this->orgA->id, 'project_management', 'R', $this->p1));
        $this->assertFalse($perm->checkPermission($this->ownerA->id, $this->orgA->id, 'project_management', 'R', 99999));
        $this->assertSame(0, $this->universalRows()->count());
    }

    #[DataProvider('variants')]
    public function test_a_project_and_quote_visibility_is_unchanged(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $ids = fn ($q) => $q->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        $a = $this->orgA->id;

        $this->assertSame([$this->p1], $ids(Project::visibleTo($this->viewer->id, $a)));
        $this->assertSame([$this->p1], $ids(Project::visibleTo($this->est->id, $a)));
        $this->assertSame([$this->p1, $this->p2], $ids(Project::visibleTo($this->owner->id, $a)));
        $this->assertSame([], $ids(Project::visibleTo($this->stranger->id, $a)));
        $this->assertSame([], $ids(Project::visibleTo($this->owner->id, $this->orgB->id)));
        $this->assertSame([], $ids(Project::visibleTo($this->adminAcct->id, $a)));
        $this->assertSame([], $ids(Project::visibleTo($this->ownerA->id, $this->orgB->id)));
        $this->assertSame([$this->p1], $ids(Project::visibleTo($this->ownerA->id, $a)));
        $this->assertSame([$this->p3], $ids(Project::visibleTo($this->outsider->id, $this->orgB->id)));

        $this->assertSame([$this->q1, $this->q2], $ids(Quote::visibleTo($this->viewer->id, $a)));
        $this->assertSame([$this->q1, $this->q2, $this->q3], $ids(Quote::visibleTo($this->owner->id, $a)));
        $this->assertSame([], $ids(Quote::visibleTo($this->stranger->id, $a)));
        $this->assertSame([], $ids(Quote::visibleTo($this->owner->id, null)));
        $this->assertNotContains($this->q0, $ids(Quote::visibleTo($this->owner->id, $a)), 'NULL-project quotes stay hidden');
    }

    #[DataProvider('variants')]
    public function test_a_project_routes_still_hide_other_projects_from_non_listed_users(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);

        foreach (['GET' => [], 'PUT' => ['name' => 'X', 'status' => 'active']] as $method => $data) {
            $this->assertContains($this->req($this->stranger, $method, "projects/{$this->p1}", $data, $this->orgA->id)->getStatusCode(), [403, 404]);
            $this->assertContains($this->req($this->est, $method, "projects/{$this->p3}", $data, $this->orgA->id)->getStatusCode(), [403, 404]);
            $this->assertContains($this->req($this->ownerA, $method, "projects/{$this->p2}", $data, $this->orgA->id)->getStatusCode(), [403, 404]);
        }
        $this->assertContains($this->req($this->stranger, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->est->id], $this->orgA->id)->getStatusCode(), [403, 404]);
        $this->assertContains($this->req($this->ownerA, 'POST', "projects/{$this->p2}/quotes", [], $this->orgA->id)->getStatusCode(), [403, 404]);
        $this->assertSame('P1', DB::table('projects')->where('id', $this->p1)->value('name'));
        $this->assertSame('P2', DB::table('projects')->where('id', $this->p2)->value('name'));

        $list = $this->req($this->ownerA, 'GET', 'projects/list', [], $this->orgA->id)->assertOk()->json('projects');
        $this->assertSame([$this->p1], array_map('intval', array_column($list, 'id')));
    }

    #[DataProvider('variants')]
    public function test_a_cross_org_requests_stay_denied_for_non_listed_users(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);

        $this->req($this->viewer, 'GET', 'org-admin/audit-log', [], $this->orgA->id)->assertForbidden();
        $this->req($this->viewer, 'GET', 'org-admin/roles-list', [], $this->orgA->id)->assertForbidden();
        $this->assertContains($this->req($this->adminAcct, 'GET', 'org-admin/roles-list', [], $this->orgA->id)->getStatusCode(), [302, 403]);
        $this->req($this->outsider, 'GET', 'org-admin/audit-log', [], $this->orgA->id)->assertForbidden();

        $before = DB::table('organizations')->pluck('name', 'id')->all();
        $this->req($this->ownerA, 'POST', 'org-admin/settings', ['name' => 'Renamed', 'org_type' => 'subcontractor', 'team_size' => 'solo'], $this->orgB->id);
        $after = DB::table('organizations')->pluck('name', 'id')->all();
        $this->assertSame($before[$this->orgB->id], $after[$this->orgB->id], 'a stale session org must never let an owner of A edit org B');
        $this->assertSame('Renamed', $after[$this->orgA->id], 'the old fallback rule: a stale session org falls back to the user\'s own org');
    }

    #[DataProvider('variants')]
    public function test_a_org_switch_to_a_non_member_org_is_refused_and_logged_nowhere(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);

        foreach ([$this->orgB->id, 99999] as $target) {
            $r = $this->actingAs($this->est)->from('/user-dashboard')->post(route('org.switch'), ['org_id' => $target]);
            $r->assertRedirect('/user-dashboard')->assertSessionHas('error', 'You do not have access to that organization.');
            $this->assertNotSame($target, session($this->key()), 'the session org never becomes the refused org');
        }
        $this->flushSession();
        $this->actingAs($this->adminAcct)->post(route('org.switch'), ['org_id' => $this->orgA->id])->assertSessionMissing($this->key());

        $this->actingAs($this->multi)->post(route('org.switch'), ['org_id' => $this->orgB->id])
            ->assertRedirect(route('org-admin.overview'))->assertSessionHas($this->key(), $this->orgB->id);

        $this->assertSame(0, $this->universalRows()->count());
        $this->assertSame('', $this->logContents());
    }

    #[DataProvider('variants')]
    public function test_a_current_org_rules_are_the_old_rules(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $key = $this->key();
        $stale = $this->mkUser($this->orgA, 'estimator');
        $this->assignRole($stale, $this->orgB, 'estimator', active: false);
        $orgless = $this->mkUser();
        $dead = $this->mkUser($this->orgA, 'estimator');
        DB::table('user_org_roles')->where('user_id', $dead->id)->update(['is_active' => false]);

        $cases = [
            'valid session org' => [$this->multi, [$key => $this->orgB->id], $this->orgB->id, $this->orgB->id],
            'stale session org (no role row)' => [$this->est, [$key => $this->orgB->id], $this->orgA->id, $this->orgB->id],
            'deactivated role in the session org' => [$stale, [$key => $this->orgB->id], $this->orgA->id, $this->orgB->id],
            'nonexistent session org' => [$this->est, [$key => 99999], $this->orgA->id, 99999],
            'unset key' => [$this->multi, [], $this->orgA->id, null],
            'empty string key' => [$this->est, [$key => ''], $this->orgA->id, ''],
            'no orgs' => [$orgless, [], null, null],
            'all roles deactivated' => [$dead, [$key => $this->orgA->id], null, $this->orgA->id],
            'role=admin without roles, stale key' => [$this->adminAcct, [$key => $this->orgA->id], null, $this->orgA->id],
        ];

        foreach ($cases as $label => [$user, $session, $expectedId, $expectedRaw]) {
            $this->flushSession();
            $this->withSession($session)->actingAs($user);
            $this->assertSame($expectedId, CurrentOrg::id($user->id), "$label: CurrentOrg::id");
            $this->assertSame($expectedRaw, CurrentOrg::sessionOrg($user->id), "$label: sessionOrg is the raw session value for a non-listed user");
        }
    }

    #[DataProvider('variants')]
    public function test_a_rbac_audit_org_equals_current_org(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $this->setMode('audit');
        $key = $this->key();
        $stale = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->assignRole($stale, $this->orgB, 'viewer_read_only', active: false);
        $bViewer = $this->mkUser($this->orgB, 'viewer_read_only');
        $this->assignRole($bViewer, $this->orgA, 'viewer_read_only');

        $cases = [
            'valid session org' => [$bViewer, [$key => $this->orgA->id], $this->orgA->id],
            'stale session org' => [$stale, [$key => $this->orgB->id], $this->orgA->id],
            'no session' => [$this->est, [], $this->orgA->id],
        ];

        foreach ($cases as $label => [$user, $session, $expected]) {
            AuditLog::query()->delete();
            $this->flushSession();
            $this->withSession($session)->actingAs($user);
            $this->assertSame($expected, CurrentOrg::id($user->id), "$label: CurrentOrg");
            $this->actingAs($user)->withSession($session)->getJson('org-admin/audit-log')->assertForbidden();
            $row = AuditLog::orderByDesc('id')->first();
            $this->assertNotNull($row, "$label: no audit row");
            $this->assertSame($expected, (int) $row->org_id, "$label: RbacAudit org");
            $this->assertNotSame('allowed_universal_admin', $row->outcome);
        }
    }

    #[DataProvider('variants')]
    public function test_a_check_role_still_redirects_non_listed_non_admin_users_from_admin_routes(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);

        foreach (['admin/dashboard', 'admin/rbac'] as $uri) {
            foreach ([$this->est, $this->ownerA, $this->viewer] as $u) {
                $this->actingAs($u)->get($uri)->assertRedirect();
                $this->actingAs($u)->getJson($uri)->assertStatus(403);
            }
        }
        $this->actingAs($this->adminAcct)->get('org-admin')->assertRedirect();
        $this->actingAs($this->adminAcct)->get('projects')->assertRedirect();
    }

    #[DataProvider('variants')]
    public function test_a_normal_traffic_writes_no_universal_audit_row_nor_log_line(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $a = $this->orgA->id;

        foreach ([$this->ownerA, $this->viewer, $this->stranger, $this->est, $this->adminAcct] as $u) {
            foreach (['org-admin', 'org-admin/roles-list', 'org-admin/audit-log', 'org-admin/settings', 'projects/list', 'quotes/list', 'org-admin/api-tokens', "projects/{$this->p2}", 'org-admin/my-roles'] as $uri) {
                $this->req($u, 'GET', $uri, [], $a);
            }
            $this->req($u, 'POST', 'org-admin/api-tokens', ['name' => 'x'], $a);
            $this->req($u, 'POST', "projects/{$this->p1}/members", ['user_id' => $this->stranger->id], $a);
            $this->req($u, 'POST', 'org/switch', ['org_id' => $this->orgB->id], $a);
        }

        $this->assertSame(0, $this->universalRows()->count());
        $this->assertSame(0, AuditLog::where('reason', 'universal_org_switch')->orWhere('reason', 'acted_as_platform_admin')->count());
        $this->assertSame('', $this->logContents());
    }

    #[DataProvider('variants')]
    public function test_a_navbar_has_no_picker_and_no_banner_for_normal_users(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);

        foreach ([$this->ownerA, $this->multi, $this->viewer] as $u) {
            $html = $this->actingAs($u)->withSession($this->orgSession($this->orgA->id))->get('org-admin/my-roles')->assertOk()->getContent();
            $this->assertStringNotContainsString('Platform admin mode', $html);
            $this->assertStringNotContainsString('platformOrgSelect', $html);
            $this->assertStringNotContainsString('Select organization', $html);
        }
        $html = $this->actingAs($this->ownerA)->get('user-dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Platform admin mode', $html);
        $this->assertStringNotContainsString('select an organization from the picker', $html);

        $multiHtml = $this->actingAs($this->multi)->get('org-admin/my-roles')->assertOk()->getContent();
        $this->assertStringContainsString('Org A', $multiHtml);
        $this->assertStringContainsString('Org B', $multiHtml);
        $this->assertStringNotContainsString('Seller Org', $multiHtml, 'a normal user only lists their own orgs');
    }

    #[DataProvider('variants')]
    public function test_a_approvals_orders_and_checkout_follow_the_old_session_rule_for_normal_users(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $a = $this->orgA->id;
        $b = $this->orgB->id;

        $inA = $this->mkOrder($a, $this->est);
        $inB = $this->mkOrder($b, $this->outsider);

        $pending = $this->actingAs($this->orgOwner)->withSession($this->orgSession($a))->get('order-approvals')->assertOk()->viewData('pendingOrders');
        $this->assertSame([$inA], $pending->pluck('id')->map(fn ($i) => (int) $i)->all());

        $this->req($this->orgOwner, 'POST', "orders/$inB/approve", [], $a)->assertForbidden();
        $this->assertSame('pending_approval', DB::table('orders')->where('id', $inB)->value('status'));
        $this->req($this->orgOwner, 'POST', "orders/$inA/approve", [], $a)->assertStatus(302);
        $this->assertSame('processing', DB::table('orders')->where('id', $inA)->value('status'));

        $mine = $this->actingAs($this->orgOwner)->withSession($this->orgSession($a))->get('view-orders')->assertOk()->viewData('orders');
        $this->assertSame([$inA], $mine->pluck('id')->map(fn ($i) => (int) $i)->all());

        // Stale session org (no role row): checkout still routes and writes by the RAW session value, exactly as before this feature.
        $requisitioner = $this->mkUser($this->orgA, 'requisitioner');
        $this->member($this->p1, $requisitioner, $this->orgA);
        $this->stockPallet($requisitioner);
        DB::table('orders')->delete();
        $this->assertSame([], (new ApprovalRoutingService)->approverPool($b));

        $this->checkout($requisitioner, $b)->assertRedirect('/checkout')->assertSessionHas('error', self::NO_APPROVER);
        $this->assertSame(0, $this->rows('orders'), 'org B (the raw session org) has no approver, org A would have');

        $bOwner = $this->mkUser($this->orgB, 'organization_owner');
        $this->checkout($requisitioner, $b)->assertRedirect();

        $order = DB::table('orders')->first();
        $this->assertNotNull($order, 'checkout with a stale session key still produces an order as before');
        $this->assertSame($b, (int) $order->org_id, 'normal user: orders.org_id is the raw session value');
        $this->assertSame('pending_approval', $order->status);
        $this->assertNotNull($bOwner);
    }

    /**
     * Characterization of the PRE-EXISTING rule: OrderController and OrderApprovalController read the raw session
     * org for the data scope (a stale key survives a role deactivation), while the permission check uses CurrentOrg.
     * The universal-admin change must leave this exactly as it was for non-listed users.
     */
    #[DataProvider('variants')]
    public function test_a_order_and_approval_pages_read_the_raw_session_org_for_non_listed_users(string $variant, string $mode): void
    {
        $this->applyVariant($variant, $mode);
        $a = $this->orgA->id;
        $b = $this->orgB->id;
        $inA = $this->mkOrder($a, $this->est);
        $inB = $this->mkOrder($b, $this->outsider);
        $ownOrder = $this->mkOrder($b, $this->orgOwner);

        $ids = fn ($c) => $c->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $approvals = $this->actingAs($this->orgOwner)->withSession($this->orgSession($b))->get('order-approvals')->assertOk()->viewData('pendingOrders');
        $this->assertSame([$inB, $ownOrder], $ids($approvals), 'stale session org B: the raw value scopes the list');

        $orders = $this->actingAs($this->orgOwner)->withSession($this->orgSession($b))->get('view-orders')->assertOk()->viewData('orders');
        $this->assertSame([$ownOrder], $ids($orders), 'stale session org B: not an org admin there, so only own orders');

        $this->flushSession();
        $unset = $this->actingAs($this->orgOwner)->get('order-approvals')->assertOk()->viewData('pendingOrders');
        $this->assertSame([$inA], $ids($unset), 'no session key: RbacAudit seeds the own first org before the controller reads it');

        $this->assertSame([$inA], $ids($this->actingAs($this->orgOwner)->withSession($this->orgSession($a))->get('order-approvals')->viewData('pendingOrders')));
    }

    // =================================================================================================
    // (b) the listed, verified user
    // =================================================================================================

    public function test_b_is_requires_list_membership_and_a_verified_email_and_is_id_based(): void
    {
        $this->assertFalse(UniversalAdmin::is($this->ua), 'empty list');

        $this->listed($this->ua);
        $this->assertTrue(UniversalAdmin::is($this->ua));
        $this->assertTrue(UniversalAdmin::is($this->ua->id));
        $this->assertFalse(UniversalAdmin::is($this->ownerA));

        DB::table('users')->where('id', $this->ua->id)->update(['email' => 'someone.else@example.test']);
        $this->assertTrue(UniversalAdmin::is($this->ua), 'listed by id: changing the email does not matter');
        $stranger = User::factory()->create(['email' => $this->ua->email]);
        $this->assertFalse(UniversalAdmin::is($stranger), 'an account that takes over the old email is not listed');

        DB::table('users')->where('id', $this->ua->id)->update(['email_verified_at' => null]);
        $this->assertFalse(UniversalAdmin::is($this->ua), 'unverified');
        $this->assertFalse(UniversalAdmin::is(User::find($this->ua->id)));

        DB::table('users')->where('id', $this->ua->id)->update(['email_verified_at' => now()]);
        $this->assertTrue(UniversalAdmin::is($this->ua));

        config(['rbac.universal_admin_user_ids' => []]);
        $this->assertFalse(UniversalAdmin::is($this->ua));

        config(['rbac.universal_admin_user_ids' => [99999]]);
        $this->assertFalse(UniversalAdmin::is(99999), 'listed id without a user row');
    }

    public function test_b_config_parses_the_env_list_defensively(): void
    {
        $load = function (?string $raw) {
            $old = $_ENV['UNIVERSAL_ADMIN_USER_IDS'] ?? null;
            $oldServer = $_SERVER['UNIVERSAL_ADMIN_USER_IDS'] ?? null;
            if ($raw === null) {
                unset($_ENV['UNIVERSAL_ADMIN_USER_IDS'], $_SERVER['UNIVERSAL_ADMIN_USER_IDS']);
            } else {
                $_ENV['UNIVERSAL_ADMIN_USER_IDS'] = $_SERVER['UNIVERSAL_ADMIN_USER_IDS'] = $raw;
            }
            try {
                return (require base_path('config/rbac.php'))['universal_admin_user_ids'];
            } finally {
                $old === null ? ($_ENV['UNIVERSAL_ADMIN_USER_IDS'] = $old) : null;
                unset($_ENV['UNIVERSAL_ADMIN_USER_IDS'], $_SERVER['UNIVERSAL_ADMIN_USER_IDS']);
                if ($old !== null) {
                    $_ENV['UNIVERSAL_ADMIN_USER_IDS'] = $old;
                }
                if ($oldServer !== null) {
                    $_SERVER['UNIVERSAL_ADMIN_USER_IDS'] = $oldServer;
                }
            }
        };

        $this->assertSame([], $load(null), 'not set: the feature is off');
        $this->assertSame([], $load(''));
        $this->assertSame([3, 7], $load(' 3, 7 ,x,-1,0,,1.5'));
        $this->assertSame([12], $load('12'));
    }

    public function test_b_allowed_in_any_existing_org_and_project_of_that_org_for_all_groups(): void
    {
        $this->listed($this->ua);
        $perm = app(PermissionService::class);

        foreach ($this->allGroups() as $g) {
            foreach ([$this->orgA, $this->orgB, $this->sellerOrg] as $org) {
                $this->assertTrue($perm->checkPermission($this->ua->id, $org->id, $g, 'F'), "org {$org->id} $g");
            }
            $this->assertTrue($perm->checkPermission($this->ua->id, $this->orgA->id, $g, 'F', $this->p1), "P1 $g");
            $this->assertTrue($perm->checkPermission($this->ua->id, $this->orgA->id, $g, 'F', $this->p2), "P2 (never a member) $g");
            $this->assertTrue($perm->checkPermission($this->ua->id, $this->orgB->id, $g, 'F', $this->p3), "P3 in org B $g");
        }
        $this->assertCount(25, $this->allGroups());
    }

    public function test_b_denied_for_a_missing_org_a_soft_deleted_project_a_foreign_project_or_a_missing_project(): void
    {
        $this->listed($this->ua);
        $perm = app(PermissionService::class);
        $trashed = $this->mkProject($this->orgA, $this->owner, 'Trashed', now()->toDateTimeString());

        $this->assertFalse($perm->checkPermission($this->ua->id, 99999, 'user_management', 'R'));
        $this->assertFalse($perm->checkPermission($this->ua->id, 99999, 'project_management', 'R', $this->p1));
        $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgA->id, 'project_management', 'R', $trashed));
        $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgA->id, 'project_management', 'R', $this->p3), 'P3 belongs to org B');
        $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgB->id, 'project_management', 'R', $this->p1), 'P1 belongs to org A');
        $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgA->id, 'project_management', 'R', 99999));
        $this->assertSame(0, $this->universalRows()->count(), 'denied checks write no bypass row');
    }

    public function test_b_an_unverified_listed_user_is_denied_everywhere(): void
    {
        $this->listed($this->ua);
        DB::table('users')->where('id', $this->ua->id)->update(['email_verified_at' => null]);
        $perm = app(PermissionService::class);

        $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'R'));
        $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgA->id, 'project_management', 'R', $this->p1));
        $this->assertSame([], Project::visibleTo($this->ua->id, $this->orgA->id)->pluck('id')->all());
        $this->assertNull(CurrentOrg::id($this->ua->id));

        $this->setMode('enforce');
        $this->assertNotSame(200, $this->req($this->ua, 'GET', 'org-admin/roles-list', [], $this->orgA->id)->getStatusCode());
        $this->flushSession();
        $this->actingAs($this->ua)->post(route('org.switch'), ['org_id' => $this->orgA->id])->assertSessionMissing($this->key());
    }

    public function test_b_a_delegate_of_a_listed_user_does_not_inherit_the_bypass(): void
    {
        $this->listed($this->ua);
        $delegate = $this->mkUser();
        Delegation::create([
            'from_user_id' => $this->ua->id, 'to_user_id' => $delegate->id, 'org_id' => $this->orgA->id,
            'granted_by' => $this->ua->id, 'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);
        $perm = app(PermissionService::class);

        $this->assertFalse(UniversalAdmin::is($delegate));
        foreach ($this->allGroups() as $g) {
            $this->assertFalse($perm->checkPermission($delegate->id, $this->orgA->id, $g, 'R'), $g);
            $this->assertFalse($perm->checkPermission($delegate->id, $this->orgA->id, $g, 'R', $this->p1), $g);
        }
        $this->assertSame([], Project::visibleTo($delegate->id, $this->orgA->id)->pluck('id')->all());
        $this->assertSame(0, $this->universalRows()->count());

        $this->assertContains($this->req($delegate, 'GET', 'org-admin/roles-list', [], $this->orgA->id)->getStatusCode(), [302, 403]);
    }

    public function test_b_a_listed_user_who_is_a_delegate_of_someone_keeps_the_bypass_and_gives_nothing_extra(): void
    {
        $this->listed($this->ua);
        Delegation::create([
            'from_user_id' => $this->viewer->id, 'to_user_id' => $this->ua->id, 'org_id' => $this->orgA->id,
            'granted_by' => $this->viewer->id, 'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true,
        ]);

        $this->assertTrue(app(PermissionService::class)->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'F'));
        $this->assertFalse(app(PermissionService::class)->checkPermission($this->viewer->id, $this->orgA->id, 'user_management', 'F'), 'the principal gains nothing');
    }

    public static function familyCases(): array
    {
        $cases = [];
        foreach (['audit', 'enforce'] as $mode) {
            foreach (['roles-list', 'audit-log', 'settings', 'delegations', 'api-tokens', 'projects-p2', 'projects-list', 'quotes-list', 'project-update', 'member-add', 'quote-create', 'crosswalk', 'settings-update',
                'delegation-store', 'token-store', 'rfq-store', 'rfq-convert', 'order-approve', 'order-reject', 'approvals-index', 'view-orders'] as $case) {
                $cases["$case $mode"] = [$case, $mode];
            }
        }

        return $cases;
    }

    #[DataProvider('familyCases')]
    public function test_b_listed_user_works_in_an_org_they_never_joined_and_a_normal_user_is_denied_the_same_call(string $case, string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $a = $this->orgA->id;
        $this->actingAs($this->ua);

        switch ($case) {
            case 'roles-list':
            case 'audit-log':
            case 'settings':
            case 'delegations':
            case 'api-tokens':
                $this->req($this->ua, 'GET', "org-admin/$case", [], $a)->assertOk();
                $this->req($this->viewer, 'GET', "org-admin/$case", [], $a)->assertForbidden();
                break;

            case 'settings-update':
                $this->req($this->viewer, 'POST', 'org-admin/settings', ['name' => 'Hacked', 'org_type' => 'subcontractor', 'team_size' => 'solo'], $a)->assertForbidden();
                $this->assertSame('Org A', DB::table('organizations')->where('id', $a)->value('name'));
                $this->req($this->ua, 'POST', 'org-admin/settings', ['name' => 'Set By Platform', 'org_type' => 'subcontractor', 'team_size' => 'solo'], $a)->assertStatus(302);
                $this->assertSame('Set By Platform', DB::table('organizations')->where('id', $a)->value('name'));
                break;

            case 'delegation-store':
                $body = ['to_user_id' => $this->stranger->id, 'starts_at' => now()->subHour()->toDateTimeString(), 'expires_at' => now()->addDay()->toDateTimeString()];
                $this->req($this->viewer, 'POST', 'org-admin/delegations', $body, $a)->assertForbidden();
                $this->assertSame(0, $this->rows('delegations'));
                $this->assertLessThan(400, $this->req($this->ua, 'POST', 'org-admin/delegations', $body, $a)->getStatusCode());
                $this->assertSame(1, $this->rows('delegations'));
                break;

            case 'token-store':
                $this->req($this->viewer, 'POST', 'org-admin/api-tokens', ['name' => 'ci'], $a)->assertForbidden();
                $this->assertSame(0, $this->rows('api_tokens'));
                $this->assertLessThan(400, $this->req($this->ua, 'POST', 'org-admin/api-tokens', ['name' => 'ci'], $a)->getStatusCode());
                $this->assertSame(1, $this->rows('api_tokens'));
                break;

            case 'projects-p2':
                $this->req($this->ua, 'GET', "projects/{$this->p2}", [], $a)->assertOk();
                $this->assertContains($this->req($this->outsider, 'GET', "projects/{$this->p2}", [], $this->orgB->id)->getStatusCode(), [403, 404]);
                $this->assertContains($this->req($this->stranger, 'GET', "projects/{$this->p2}", [], $a)->getStatusCode(), [403, 404]);
                break;

            case 'projects-list':
                $json = $this->req($this->ua, 'GET', 'projects/list', [], $a)->assertOk()->json('projects');
                $ids = array_map('intval', array_column($json, 'id'));
                sort($ids);
                $this->assertSame([$this->p1, $this->p2], $ids, 'every project of org A, none of org B');
                break;

            case 'quotes-list':
                $this->req($this->ua, 'GET', 'quotes/list', [], $a)->assertOk();
                $this->req($this->viewer, 'GET', 'quotes/list', [], $a)->assertOk();
                $visible = Quote::visibleTo($this->ua->id, $a)->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
                $this->assertSame([$this->q1, $this->q2, $this->q3], $visible, 'all quotes of org A, not q0 (NULL project), not q4 (org B)');
                break;

            case 'project-update':
                $this->assertContains($this->req($this->stranger, 'PUT', "projects/{$this->p2}", ['name' => 'Hacked', 'status' => 'active'], $a)->getStatusCode(), [403, 404]);
                $this->assertSame('P2', DB::table('projects')->where('id', $this->p2)->value('name'));
                $this->assertLessThan(400, $this->req($this->ua, 'PUT', "projects/{$this->p2}", ['name' => 'Renamed', 'status' => 'active'], $a)->getStatusCode());
                $this->assertSame('Renamed', DB::table('projects')->where('id', $this->p2)->value('name'));
                $this->assertSame('P1', DB::table('projects')->where('id', $this->p1)->value('name'));
                $this->assertSame('P3', DB::table('projects')->where('id', $this->p3)->value('name'));
                break;

            case 'member-add':
                $this->assertContains($this->req($this->est, 'POST', "projects/{$this->p2}/members", ['user_id' => $this->stranger->id], $a)->getStatusCode(), [403, 404]);
                $this->req($this->ua, 'POST', "projects/{$this->p2}/members", ['user_id' => $this->stranger->id], $a)->assertOk();
                $log = DB::table('project_member_logs')->where('project_id', $this->p2)->where('target_user_id', $this->stranger->id)->first();
                $this->assertNotNull($log);
                $this->assertSame($this->ua->id, (int) $log->performed_by, 'the real person is recorded');
                $this->assertContains($this->p2, Project::visibleTo($this->stranger->id, $a)->pluck('id')->map(fn ($i) => (int) $i)->all());
                break;

            case 'quote-create':
                $this->assertContains($this->req($this->stranger, 'POST', "projects/{$this->p2}/quotes", [], $a)->getStatusCode(), [403, 404]);
                $r = $this->req($this->ua, 'POST', "projects/{$this->p2}/quotes", [], $a);
                $this->assertSame(422, $r->getStatusCode(), 'past the permission and visibility gate: only the empty body is rejected');
                break;

            case 'crosswalk':
                $this->assertContains($this->req($this->stranger, 'POST', "projects/{$this->p2}/crosswalk", ['plan_line_code' => 'L1'], $a)->getStatusCode(), [403, 404]);
                $this->assertSame(0, $this->rows('plan_crosswalk'));
                $this->assertLessThan(400, $this->req($this->ua, 'POST', "projects/{$this->p2}/crosswalk", ['plan_line_code' => 'L1'], $a)->getStatusCode());
                $row = DB::table('plan_crosswalk')->first();
                $this->assertSame([$a, $this->p2], [(int) $row->org_id, (int) $row->project_id]);
                break;

            case 'rfq-store':
                $body = ['project_id' => $this->p2, 'title' => 'New RFQ', 'seller_org_ids' => [$this->sellerOrg->id]];
                $before = $this->rows('rfq_requests');
                $this->assertContains($this->req($this->stranger, 'POST', 'rfq', $body, $a)->getStatusCode(), [403, 422]);
                $this->assertSame($before, $this->rows('rfq_requests'));
                $this->assertLessThan(400, $this->req($this->ua, 'POST', 'rfq', $body, $a)->getStatusCode());
                $this->assertSame($before + 1, $this->rows('rfq_requests'));
                $this->assertSame($this->p2, (int) DB::table('rfq_requests')->latest('id')->value('project_id'));
                $this->assertSame($a, (int) DB::table('rfq_requests')->latest('id')->value('org_id'));
                break;

            case 'rfq-convert':
                $rfq = $this->convertible($this->p2);
                $this->assertContains($this->req($this->stranger, 'POST', "rfq/$rfq/convert", [], $a)->getStatusCode(), [403, 404]);
                $this->assertSame(0, $this->rows('orders'));
                $this->actingAs($this->ua)->withSession($this->orgSession($a))->post("rfq/$rfq/convert")->assertRedirect();
                $order = DB::table('orders')->first();
                $this->assertNotNull($order);
                $this->assertSame($a, (int) $order->org_id);
                $this->assertSame($this->ua->id, (int) $order->user_id);
                $this->assertSame('pending_approval', $order->status, 'the platform admin is never an approver: a real approver must approve');
                break;

            case 'order-approve':
            case 'order-reject':
                $verb = $case === 'order-approve' ? 'approve' : 'reject';
                $order = $this->mkOrder($a, $this->est);
                $otherOrg = $this->mkOrder($this->orgB->id, $this->outsider);
                $this->req($this->viewer, 'POST', "orders/$order/$verb", [], $a)->assertForbidden();
                $this->assertSame('pending_approval', DB::table('orders')->where('id', $order)->value('status'));
                $this->req($this->ua, 'POST', "orders/$otherOrg/$verb", [], $a)->assertForbidden();
                $this->assertSame('pending_approval', DB::table('orders')->where('id', $otherOrg)->value('status'), 'only the order of the org being worked in');
                $this->req($this->ua, 'POST', "orders/$order/$verb", ['note' => 'unblocked'], $a)->assertStatus(302);
                $row = DB::table('orders')->where('id', $order)->first();
                $this->assertSame($verb === 'approve' ? 'processing' : 'cancelled', $row->status);
                $this->assertSame($this->ua->id, (int) ($verb === 'approve' ? $row->approved_by : $row->rejected_by));
                $logged = $this->universalRows()->where('permission_group', 'approval_authority');
                $this->assertGreaterThan(0, $logged->count(), 'the approval is recorded as a platform admin act');
                break;

            case 'approvals-index':
                $order = $this->mkOrder($a, $this->est);
                $this->mkOrder($this->orgB->id, $this->outsider);
                $pending = $this->actingAs($this->ua)->withSession($this->orgSession($a))->get('order-approvals')->assertOk()->viewData('pendingOrders');
                $this->assertSame([$order], $pending->pluck('id')->map(fn ($i) => (int) $i)->all());
                break;

            case 'view-orders':
                $order = $this->mkOrder($a, $this->est);
                $this->mkOrder($this->orgB->id, $this->outsider);
                $orders = $this->actingAs($this->ua)->withSession($this->orgSession($a))->get('view-orders')->assertOk()->viewData('orders');
                $this->assertSame([$order], $orders->pluck('id')->map(fn ($i) => (int) $i)->all());
                break;
        }
    }

    public function test_b_listed_user_with_the_group_denied_for_a_non_existent_org_session_gets_nothing(): void
    {
        $this->listed($this->ua);
        $this->setMode('enforce');

        $this->assertNull(CurrentOrg::id($this->ua->id));
        $r = $this->req($this->ua, 'GET', 'org-admin/roles-list', [], 99999);
        $this->assertContains($r->getStatusCode(), [302, 403]);
        $this->assertSame(0, $this->universalRows()->count());
    }

    #[DataProvider('modes')]
    public function test_b_org_switch_to_any_org_is_allowed_and_logged_with_the_universal_reason(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);

        $this->actingAs($this->ua)->post(route('org.switch'), ['org_id' => $this->orgB->id])
            ->assertRedirect(route('org-admin.overview'))->assertSessionHas($this->key(), $this->orgB->id);

        $rows = $this->universalRows();
        $this->assertCount(1, $rows);
        $this->assertSame('universal_org_switch', $rows[0]->reason);
        $this->assertSame([$this->ua->id, $this->orgB->id], [(int) $rows[0]->user_id, (int) $rows[0]->org_id]);
        $this->assertSame('POST', $rows[0]->method);
        $this->assertStringContainsString('universal_org_switch', $this->logContents());

        $this->actingAs($this->ua)->post(route('org.switch'), ['org_id' => 99999])->assertSessionHas('error');
        $this->assertCount(1, $this->universalRows(), 'a non-existent org is refused and writes no row');
    }

    public function test_b_org_switch_to_an_org_the_listed_user_belongs_to_is_not_a_bypass(): void
    {
        $this->listed($this->ua);
        $this->assignRole($this->ua, $this->orgA, 'viewer_read_only');

        $this->actingAs($this->ua)->post(route('org.switch'), ['org_id' => $this->orgA->id])->assertSessionHas($this->key(), $this->orgA->id);

        $this->assertSame(0, $this->universalRows()->count());
    }

    public function test_b_current_org_for_a_listed_user_accepts_any_existing_org_else_own_membership(): void
    {
        $this->listed($this->ua);
        $key = $this->key();

        $this->withSession([$key => $this->orgB->id])->actingAs($this->ua);
        $this->assertSame($this->orgB->id, CurrentOrg::id($this->ua->id));
        $this->assertSame($this->orgB->id, CurrentOrg::sessionOrg($this->ua->id));

        $this->flushSession();
        $this->withSession([$key => 99999])->actingAs($this->ua);
        $this->assertNull(CurrentOrg::id($this->ua->id), 'a non-existent org is not valid, and the listed user has no org of their own');
        $this->assertNull(CurrentOrg::sessionOrg($this->ua->id));

        $this->flushSession();
        $this->actingAs($this->ua);
        $this->assertNull(CurrentOrg::id($this->ua->id), 'nothing selected, no membership');

        $this->assignRole($this->ua, $this->sellerOrg, 'viewer_read_only');
        $this->assertSame($this->sellerOrg->id, CurrentOrg::id($this->ua->id), 'nothing selected: own first membership');
    }

    public function test_b_rbac_audit_and_current_org_agree_for_a_listed_user_in_an_org_without_a_role_row(): void
    {
        $this->listed($this->ua);
        $this->setMode('audit');
        $key = $this->key();

        $this->withSession([$key => $this->orgB->id])->actingAs($this->ua);
        $this->assertSame($this->orgB->id, CurrentOrg::id($this->ua->id));

        $this->actingAs($this->ua)->withSession([$key => $this->orgB->id])->getJson('org-admin/roles-list')->assertOk();

        $rows = $this->universalRows();
        $this->assertGreaterThan(0, $rows->count());
        foreach ($rows as $row) {
            $this->assertSame($this->orgB->id, (int) $row->org_id, 'the middleware judged the user in org B, not in some other org');
        }
        $this->assertSame(0, AuditLog::whereIn('outcome', ['would_block', 'blocked'])->count(), 'nothing was denied');
    }

    #[DataProvider('modes')]
    public function test_b_audit_row_is_written_once_per_request_and_distinct_check_and_only_when_the_bypass_changed_the_answer(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $perm = app(PermissionService::class);
        $a = $this->orgA->id;

        app()->instance('request', Request::create('/probe', 'GET'));
        $perm->checkPermission($this->ua->id, $a, 'user_management', 'F');
        $perm->checkPermission($this->ua->id, $a, 'user_management', 'f');
        $perm->checkPermission($this->ua->id, $a, 'user_management', 'F');
        $this->assertCount(1, $this->universalRows(), 'the same check twice in one request');

        $perm->checkPermission($this->ua->id, $a, 'user_management', 'R');
        $perm->checkPermission($this->ua->id, $a, 'procurement', 'F');
        $perm->checkPermission($this->ua->id, $this->orgB->id, 'user_management', 'F');
        $perm->checkPermission($this->ua->id, $a, 'project_management', 'F', $this->p1);
        $perm->checkPermission($this->ua->id, $a, 'project_management', 'F', $this->p2);
        $this->assertCount(6, $this->universalRows(), 'level, group, org and project each make a distinct check');

        $row = $this->universalRows()->first();
        $this->assertSame(
            [$this->ua->id, $a, 'user_management', 'F', null, 'allowed_universal_admin', 'acted_as_platform_admin', 'GET', 'probe'],
            [(int) $row->user_id, (int) $row->org_id, $row->permission_group, $row->required_level, $row->project_id, $row->outcome, $row->reason, $row->method, $row->route_uri]
        );

        app()->instance('request', Request::create('/probe', 'GET'));
        $perm->checkPermission($this->ua->id, $a, 'user_management', 'F');
        $this->assertCount(7, $this->universalRows(), 'a new request logs the same check again');
    }

    public function test_b_no_audit_row_when_the_listed_user_holds_the_grant_naturally(): void
    {
        $this->listed($this->ua);
        $this->assignRole($this->ua, $this->orgA, 'viewer_read_only');
        $perm = app(PermissionService::class);

        $this->assertTrue($perm->checkPermission($this->ua->id, $this->orgA->id, 'quote_rfq_management', 'R'));
        $this->assertCount(0, $this->universalRows(), 'granted by the real role: no bypass, no row');

        $this->assertTrue($perm->checkPermission($this->ua->id, $this->orgA->id, 'quote_rfq_management', 'F'));
        $this->assertCount(1, $this->universalRows(), 'the viewer role does not reach F: the bypass changed the answer');
    }

    public function test_b_universal_admin_log_channel_receives_each_bypass_with_the_real_user(): void
    {
        $this->listed($this->ua);
        $this->assertSame('', $this->logContents());

        app(PermissionService::class)->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'F');
        $this->actingAs($this->ua)->post(route('org.switch'), ['org_id' => $this->orgB->id]);

        $log = $this->logContents();
        $this->assertStringContainsString('acted_as_platform_admin', $log);
        $this->assertStringContainsString('universal_org_switch', $log);
        $this->assertStringContainsString('"user_id":'.$this->ua->id, $log);
        $this->assertStringContainsString('"org_id":'.$this->orgA->id, $log);
        $this->assertStringContainsString('"org_id":'.$this->orgB->id, $log);
        $this->assertStringContainsString('"permission_group":"user_management"', $log);
    }

    public function test_b_the_log_channel_is_never_pruned(): void
    {
        $channel = (require base_path('config/logging.php'))['channels']['universal_admin'];

        $this->assertSame('daily', $channel['driver']);
        $this->assertSame(0, $channel['days']);
    }

    #[DataProvider('modes')]
    public function test_b_org_delete_and_ownership_transfer_stay_owner_only(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $orgC = $this->mkOrg('Org C');
        $ownerC = $this->mkUser($orgC, 'organization_owner');
        $before = [DB::table('organizations')->count(), DB::table('user_org_roles')->count()];

        $this->req($this->ua, 'DELETE', 'org-admin/settings/delete', [], $orgC->id)->assertForbidden();
        $this->req($this->ua, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->est->email], $orgC->id)->assertForbidden();

        $this->assertSame($before, [DB::table('organizations')->count(), DB::table('user_org_roles')->count()]);
        $this->assertNotNull(DB::table('organizations')->where('id', $orgC->id)->first());
        $this->assertSame(1, DB::table('user_org_roles')->where('org_id', $orgC->id)->where('user_id', $ownerC->id)->count());

        $this->req($ownerC, 'POST', 'org-admin/settings/transfer', ['new_owner_email' => $this->est->email], $orgC->id)->assertStatus(302);
        $this->assertSame(0, DB::table('user_org_roles')->where('org_id', $orgC->id)->where('user_id', $ownerC->id)->count(), 'the real owner still can');
    }

    #[DataProvider('modes')]
    public function test_b_sod_is_untouched_when_the_listed_user_assigns_roles_in_both_directions(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $a = $this->orgA->id;
        $procurementManager = Role::where('slug', 'procurement_manager')->value('id');
        $executiveApprover = Role::where('slug', 'executive_approver')->value('id');

        $holderPm = $this->mkUser($this->orgA, 'procurement_manager');
        $holderEa = $this->mkUser($this->orgA, 'executive_approver');
        $free = $this->mkUser($this->orgA, 'estimator');

        $r1 = $this->req($this->ua, 'POST', 'org-admin/roles', ['user_id' => $holderPm->id, 'role_id' => $executiveApprover], $a);
        $r2 = $this->req($this->ua, 'POST', 'org-admin/roles', ['user_id' => $holderEa->id, 'role_id' => $procurementManager], $a);
        $this->assertSame(422, $r1->getStatusCode());
        $this->assertSame(422, $r2->getStatusCode());
        $this->assertTrue($r1->json('sod_conflict'));
        $this->assertTrue($r2->json('sod_conflict'));
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $holderPm->id)->where('role_id', $executiveApprover)->count());
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $holderEa->id)->where('role_id', $procurementManager)->count());

        $this->req($this->ua, 'POST', 'org-admin/roles', ['user_id' => $free->id, 'role_id' => $procurementManager], $a)->assertOk();
        $this->assertSame(1, DB::table('user_org_roles')->where('user_id', $free->id)->where('role_id', $procurementManager)->count());
        $this->assertSame(0, DB::table('user_org_roles')->where('user_id', $this->ua->id)->count(), 'the platform admin holds no role row');
    }

    public function test_b_not_in_the_approver_pool_and_a_zero_approver_org_still_refuses_their_checkout(): void
    {
        $this->listed($this->ua);
        $routing = new ApprovalRoutingService;

        $this->assertNotContains($this->ua->id, $routing->approverPool($this->orgA->id));
        $this->assertContains($this->procMgr->id, $routing->approverPool($this->orgA->id));
        $this->assertNotContains($this->ua->id, $routing->approverPool($this->orgB->id));

        DB::table('user_org_roles')->whereIn('user_id', [$this->procMgr->id, $this->orgOwner->id, $this->ownerA->id])->update(['is_active' => false]);
        $this->assertSame([], $routing->approverPool($this->orgA->id));
        $this->stockPallet($this->ua);

        $this->checkout($this->ua, $this->orgA->id)->assertRedirect('/checkout')->assertSessionHas('error', self::NO_APPROVER);

        $this->assertSame(0, $this->rows('orders'));
        $this->assertSame(1, DB::table('palletes')->where('user_id', $this->ua->id)->count());
    }

    #[DataProvider('modes')]
    public function test_b_checkout_in_a_switched_org_carries_that_org_and_routes_through_its_pool(string $mode): void
    {
        $this->setMode($mode);
        $this->listed($this->ua);
        $this->assignRole($this->ua, $this->orgB, 'viewer_read_only');
        $this->stockPallet($this->ua);

        $this->checkout($this->ua, $this->orgA->id)->assertRedirect();

        $order = DB::table('orders')->first();
        $this->assertNotNull($order);
        $this->assertSame($this->orgA->id, (int) $order->org_id, 'the switched org, not the first membership (org B)');
        $this->assertSame($this->ua->id, (int) $order->user_id);
        $this->assertSame('pending_approval', $order->status, 'routed through org A\'s pool, the admin is not an approver');
    }

    public function test_b_sellers_never_see_the_buyer_project_even_for_a_listed_user_in_the_seller_org(): void
    {
        $this->listed($this->ua);
        DB::table('projects')->where('id', $this->p1)->update(['name' => 'Confidential Skyline Tower']);
        $rfq = $this->mkRfq($this->p1);
        DB::table('rfq_recipients')->insert(['rfq_request_id' => $rfq, 'seller_org_id' => $this->sellerOrg->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $r = $this->actingAs($this->ua)->withSession($this->orgSession($this->sellerOrg->id))->get('rfq-incoming')->assertOk();

        $r->assertDontSee('Confidential Skyline Tower');
        $recipients = $r->viewData('recipients');
        $this->assertCount(1, $recipients);
        $this->assertNull($recipients->first()->rfqRequest->project_id, 'the seller payload does not select project_id');
        $this->assertStringNotContainsString('Skyline', $r->getContent());

        $buyer = $this->actingAs($this->ua)->withSession($this->orgSession($this->orgA->id))->get('rfq')->assertOk();
        $buyer->assertSee('Confidential Skyline Tower', false);
    }

    public function test_b_listed_user_with_no_org_selected_sees_the_dashboard_notice_and_no_org_data(): void
    {
        $this->listed($this->ua);
        $q = $this->mkQuote($this->est, $this->p1, ['name' => 'LEAK-QUOTE']);
        $this->mkOrder($this->orgA->id, $this->est);

        $r = $this->actingAs($this->ua)->get('user-dashboard')->assertOk();
        $r->assertSee('Platform admin mode: select an organization', false);
        $r->assertSee('Select organization', false);
        $this->assertStringNotContainsString('LEAK-QUOTE', $r->getContent());

        $this->assertSame([], Quote::visibleTo($this->ua->id, CurrentOrg::id($this->ua->id))->pluck('id')->all());
        $this->actingAs($this->ua)->getJson('projects/list')->assertOk()->assertJson(['projects' => []]);
        $this->assertContains($this->actingAs($this->ua)->get('order-approvals')->getStatusCode(), [302, 403]);
        $this->assertNotNull($q);
    }

    public function test_b_navbar_shows_the_picker_with_every_org_and_the_banner_for_a_listed_user(): void
    {
        $this->listed($this->ua);

        $html = $this->actingAs($this->ua)->withSession($this->orgSession($this->orgB->id))->get('org-admin/my-roles')->assertOk()->getContent();

        $this->assertStringContainsString('Platform admin mode - Org B', $html);
        $this->assertStringContainsString('platformOrgSelect', $html);
        foreach ([$this->orgA, $this->orgB, $this->sellerOrg] as $org) {
            $this->assertStringContainsString("(#{$org->id})", $html);
        }

        $this->flushSession();
        $none = $this->actingAs($this->ua)->get('user-dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('no organization selected', $none);
    }

    public function test_b_check_role_admits_a_listed_user_to_admin_routes_but_not_to_a_non_listed_one(): void
    {
        $this->listed($this->ua);

        $this->assertNotSame(302, $this->actingAs($this->ua)->get('admin/rbac')->getStatusCode(), 'listed: admitted past checkRole:admin');
        $this->actingAs($this->est)->get('admin/rbac')->assertRedirect();
        $this->actingAs($this->other)->get('admin/rbac')->assertRedirect();
        $this->actingAs($this->ua)->get('projects')->assertOk();
    }

    public function test_b_check_role_admission_is_only_for_the_admin_role_check(): void
    {
        $adminListed = User::factory()->create(['role' => 'admin']);
        $this->listed($adminListed);

        $this->assertTrue(UniversalAdmin::is($adminListed));
        $this->actingAs($adminListed)->get('projects')->assertRedirect();
        $this->actingAs($adminListed)->get('org-admin')->assertRedirect();
        $this->assertNotSame(302, $this->actingAs($adminListed)->get('admin/rbac')->getStatusCode(), 'a real admin account that is also listed keeps /admin');
    }

    /** A listed user whose session org is gone is judged like CurrentOrg says (own first membership), by every consumer. */
    public function test_b_listed_user_with_a_nonexistent_session_org_falls_back_to_the_validated_org_everywhere(): void
    {
        $this->listed($this->ua);
        $a = $this->orgA->id;
        $b = $this->orgB->id;
        $this->assignRole($this->ua, $this->orgB, 'viewer_read_only');
        $this->mkUser($this->orgB, 'organization_owner');
        $inB = $this->mkOrder($b, $this->outsider);
        $this->mkOrder($a, $this->est);
        $this->stockPallet($this->ua);
        $ids = fn ($c) => $c->pluck('id')->map(fn ($i) => (int) $i)->all();

        $this->assertSame($b, CurrentOrg::id($this->ua->id));
        $this->flushSession();
        $this->withSession($this->orgSession(99999))->actingAs($this->ua);
        $this->assertSame($b, CurrentOrg::sessionOrg($this->ua->id));

        $approvals = $this->actingAs($this->ua)->withSession($this->orgSession(99999))->get('order-approvals')->assertOk()->viewData('pendingOrders');
        $this->assertSame([$inB], $ids($approvals));

        $orders = $this->actingAs($this->ua)->withSession($this->orgSession(99999))->get('view-orders')->assertOk()->viewData('orders');
        $this->assertSame([$inB], $ids($orders), 'platform admin sees the whole org B, validated org');

        DB::table('orders')->delete();
        $this->checkout($this->ua, 99999)->assertRedirect();
        $order = DB::table('orders')->first();
        $this->assertNotNull($order);
        $this->assertSame($b, (int) $order->org_id);
        $this->assertSame('pending_approval', $order->status);
    }

    public function test_b_the_audit_pages_label_platform_admin_rows_instead_of_would_block(): void
    {
        $this->listed($this->ua);
        app(PermissionService::class)->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'F');
        $this->assertCount(1, $this->universalRows());

        $orgPage = $this->actingAs($this->ownerA)->withSession($this->orgSession($this->orgA->id))->get('org-admin/audit-log?tab=enforcement')->assertOk()->getContent();
        $this->assertStringContainsString('Platform admin', $orgPage);

        $adminPage = $this->actingAs($this->ua)->get('admin/rbac/audit-logs?outcome=allowed_universal_admin')->assertOk()->getContent();
        $this->assertStringContainsString('Allowed (platform admin)', $adminPage);
        $this->assertStringContainsString('Platform admin</span>', $adminPage);

        $this->assertStringNotContainsString('Platform admin</span>', $this->actingAs($this->ua)->get('admin/rbac/audit-logs?outcome=would_block')->assertOk()->getContent());
    }

    // =================================================================================================
    // Quote creation by a listed user where the rules of the project still apply
    // =================================================================================================

    public function test_b_a_quote_with_a_null_project_stays_hidden_from_a_listed_user_and_a_deleted_project_is_gone(): void
    {
        $this->listed($this->ua);
        $a = $this->orgA->id;
        $trashed = $this->mkProject($this->orgA, $this->owner, 'Trashed', now()->toDateTimeString());
        $qTrashed = $this->mkQuote($this->owner, $trashed, ['name' => 'in trashed']);

        $ids = Quote::visibleTo($this->ua->id, $a)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $this->assertNotContains($this->q0, $ids);
        $this->assertNotContains($qTrashed, $ids);
        $this->assertContains($this->q1, $ids);
        $this->assertNotContains($this->q4, $ids, 'a quote of org B');
        $this->assertContains($this->p1, Project::visibleTo($this->ua->id, $a)->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertNotContains($trashed, Project::visibleTo($this->ua->id, $a)->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame(404, $this->req($this->ua, 'GET', "projects/$trashed", [], $a)->getStatusCode());
    }

    public function test_b_visible_to_for_a_listed_user_stays_inside_the_requested_org(): void
    {
        $this->listed($this->ua);

        $this->assertSame([$this->p3], Project::visibleTo($this->ua->id, $this->orgB->id)->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([], Project::visibleTo($this->ua->id, $this->sellerOrg->id)->pluck('id')->all());
        $this->assertSame([], Project::visibleTo($this->ua->id, 99999)->pluck('id')->all());
    }

    // ---- F1: a failing audit sink must never change or break the permission answer ---------------

    private function errorLogPath(): string
    {
        $path = sys_get_temp_dir().'/ua-err-'.uniqid().'.log';
        config(['logging.channels.ua_default' => ['driver' => 'single', 'path' => $path, 'level' => 'debug'], 'logging.default' => 'ua_default']);
        Log::forgetChannel('ua_default');

        return $path;
    }

    public function test_f1_a_failing_audit_row_write_still_allows_writes_the_log_file_and_logs_an_error(): void
    {
        $this->listed($this->ua);
        $errPath = $this->errorLogPath();
        Schema::drop('rbac_audit_logs');

        $allowed = app(PermissionService::class)->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'F');

        $this->assertTrue($allowed);
        $this->assertStringContainsString('acted_as_platform_admin', $this->logContents(), 'the file log is still written');
        $err = (string) @file_get_contents($errPath);
        $this->assertStringContainsString('universal_admin audit row write failed', $err);
        $this->assertStringNotContainsString('log channel write failed', $err);
        @unlink($errPath);
    }

    public function test_f1_a_failing_log_channel_still_writes_the_row_and_allows_and_logs_an_error(): void
    {
        $this->listed($this->ua);
        $errPath = $this->errorLogPath();
        config(['logging.channels.universal_admin' => ['driver' => 'single', 'path' => '/nonexistent-ua-dir/'.uniqid().'/x.log', 'level' => 'info']]);
        Log::forgetChannel('universal_admin');

        $allowed = app(PermissionService::class)->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'F');

        $this->assertTrue($allowed);
        $rows = $this->universalRows();
        $this->assertCount(1, $rows);
        $this->assertSame([$this->ua->id, $this->orgA->id], [(int) $rows[0]->user_id, (int) $rows[0]->org_id]);
        $err = (string) @file_get_contents($errPath);
        $this->assertStringContainsString('universal_admin log channel write failed', $err);
        $this->assertStringNotContainsString('audit row write failed', $err);
        @unlink($errPath);
    }

    public function test_f1_both_sinks_failing_still_allows_without_an_exception(): void
    {
        $this->listed($this->ua);
        $errPath = $this->errorLogPath();
        config(['logging.channels.universal_admin' => ['driver' => 'single', 'path' => '/nonexistent-ua-dir/'.uniqid().'/x.log', 'level' => 'info']]);
        Log::forgetChannel('universal_admin');
        Schema::drop('rbac_audit_logs');

        $this->assertTrue(app(PermissionService::class)->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'F', $this->p2));
        $err = (string) @file_get_contents($errPath);
        $this->assertStringContainsString('audit row write failed', $err);
        $this->assertStringContainsString('log channel write failed', $err);
        @unlink($errPath);
    }

    public function test_f1_non_listed_users_are_unaffected_when_the_sinks_are_broken(): void
    {
        $this->listed($this->other);
        $errPath = $this->errorLogPath();
        config(['logging.channels.universal_admin' => ['driver' => 'single', 'path' => '/nonexistent-ua-dir/'.uniqid().'/x.log', 'level' => 'info']]);
        Log::forgetChannel('universal_admin');
        Schema::drop('rbac_audit_logs');
        $perm = app(PermissionService::class);

        $this->assertFalse($perm->checkPermission($this->ua->id, $this->orgA->id, 'user_management', 'R'), 'unlisted, no role');
        $this->assertFalse($perm->checkPermission($this->viewer->id, $this->orgA->id, 'user_management', 'F'), 'role too weak');
        $this->assertFalse($perm->checkPermission($this->ownerA->id, $this->orgB->id, 'user_management', 'R'), 'wrong org');
        $this->assertTrue($perm->checkPermission($this->ownerA->id, $this->orgA->id, 'user_management', 'R'), 'allowed path unchanged');
        $this->assertSame('', $this->logContents());
        $this->assertStringNotContainsString('failed', (string) @file_get_contents($errPath), 'the sinks are never touched for normal users');
        @unlink($errPath);
    }
}

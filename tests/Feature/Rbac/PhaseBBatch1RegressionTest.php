<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\OrgRelationship;
use App\Models\Rbac\Role;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

/**
 * Client audit 2, Phase B batch 1 (N01, S13, D21, O05, B06, L06): behaviour that worked before and must keep working.
 * Only pins whose equivalent is not already in SodSoloOrgTest, JsonErrorBodyTest, CrossOrgNotFoundTest,
 * ConnectionsLookupTest, DelegationAuditTest, RegistrationTest or PhaseARegressionTest.
 */
class PhaseBBatch1RegressionTest extends ProjectTestCase
{
    private User $ownerA;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->admin = User::factory()->create(['role' => 'admin']);

        config(['rbac.universal_admin_user_ids' => [], 'app.debug' => false]);
        $this->setMode('audit');

        Route::get('_reg/boom', fn () => throw new \RuntimeException('reg-secret in /var/www/app/Secret.php'));
        Route::get('_reg/expired', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
        Route::get('_reg/forbidden', fn () => abort(403, 'Not for you.'));
        Route::get('_reg/throttled', fn () => 'ok')->middleware('throttle:1,1');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    public static function debugModes(): array
    {
        return ['debug on' => [true], 'debug off' => [false]];
    }

    private function inOrg(User $u, ?int $orgId = null)
    {
        return $this->actingAs($u)->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id]);
    }

    private function roleId(string $slug): int
    {
        return (int) Role::where('slug', $slug)->value('id');
    }

    private function holds(User $u, Organization $org, string $slug): bool
    {
        return DB::table('user_org_roles')
            ->where('user_id', $u->id)->where('org_id', $org->id)->where('role_id', $this->roleId($slug))->where('is_active', true)
            ->exists();
    }

    private function adminAssign(User $target, Organization $org, string $slug)
    {
        return $this->actingAs($this->admin)->post(route('admin.rbac.roles.assign', $org), [
            'user_id' => $target->id, 'role_id' => $this->roleId($slug),
        ]);
    }

    // ---- N01: the platform-admin screen goes through the same SoD service ---------------------------------------------

    #[DataProvider('modes')]
    public function test_the_platform_admin_screen_still_refuses_both_directions_of_every_active_rule_in_a_multi_member_org(string $mode): void
    {
        $this->setMode($mode);
        $rules = DB::table('sod_conflict_rules')->where('is_active', true)->get();
        $this->assertGreaterThanOrEqual(8, $rules->count());

        foreach ($rules as $rule) {
            $a = Role::findOrFail($rule->role_id_a);
            $b = Role::findOrFail($rule->role_id_b);

            foreach ([[$a, $b], [$b, $a]] as [$held, $incoming]) {
                $member = $this->mkUser($this->orgA, $held->slug);

                $this->adminAssign($member, $this->orgA, $incoming->slug)
                    ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'SoD conflict'));

                $this->assertFalse($this->holds($member, $this->orgA, $incoming->slug), "$held->slug then $incoming->slug");
                $this->assertTrue($this->holds($member, $this->orgA, $held->slug));
            }
        }
    }

    #[DataProvider('modes')]
    public function test_the_platform_admin_screen_exempts_only_the_sole_member_and_enforces_again_when_a_second_one_joins(string $mode): void
    {
        $this->setMode($mode);
        $solo = $this->mkOrg('Solo Co');
        $owner = $this->mkUser($solo, 'organization_owner');

        $this->adminAssign($owner, $solo, 'estimator')->assertSessionHas('success', 'Role assigned.');
        $this->adminAssign($owner, $solo, 'executive_approver')->assertSessionHas('success', 'Role assigned.');
        $this->assertTrue($this->holds($owner, $solo, 'estimator'));
        $this->assertTrue($this->holds($owner, $solo, 'executive_approver'));

        $this->mkUser($solo, 'viewer_read_only');

        $this->adminAssign($owner, $solo, 'requisitioner')->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'SoD conflict'));
        $this->assertFalse($this->holds($owner, $solo, 'requisitioner'));
        $this->assertTrue($this->holds($owner, $solo, 'estimator'), 'nothing is revoked when a second member joins');
        $this->assertTrue($this->holds($owner, $solo, 'executive_approver'));
    }

    // ---- S13: error bodies and pages other than the ones the handler now reshapes -----------------------------------

    #[DataProvider('debugModes')]
    public function test_a_json_405_keeps_the_framework_message_and_the_allow_header(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $response = $this->inOrg($this->ownerA)->getJson('org-admin/invite')->assertStatus(405);

        $this->assertSame(['message' => 'The GET method is not supported for route org-admin/invite. Supported methods: POST.'], $response->json());
        $this->assertSame('POST', $response->headers->get('Allow'));
    }

    #[DataProvider('debugModes')]
    public function test_a_json_419_keeps_the_csrf_message_and_an_html_419_is_still_a_419(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $this->getJson('_reg/expired')->assertStatus(419)->assertExactJson(['message' => 'CSRF token mismatch.']);
        $this->get('_reg/expired')->assertStatus(419);
    }

    #[DataProvider('debugModes')]
    public function test_a_json_429_keeps_the_throttle_message_and_the_retry_after_header(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $this->getJson('_reg/throttled')->assertOk();
        $response = $this->getJson('_reg/throttled')->assertStatus(429);

        $this->assertSame(['message' => 'Too Many Attempts.'], $response->json());
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    #[DataProvider('debugModes')]
    public function test_a_json_403_for_a_guest_keeps_its_own_message_without_the_rbac_error_flag(bool $debug): void
    {
        config(['app.debug' => $debug]);

        $this->getJson('_reg/forbidden')->assertForbidden()->assertExactJson(['message' => 'Not for you.']);
    }

    public function test_an_html_404_and_an_html_500_still_render_the_framework_pages_without_the_exception_text(): void
    {
        $missing = $this->inOrg($this->est)->get('no/such/page')->assertNotFound();
        $this->assertStringContainsString('Not Found', (string) $missing->getContent());
        $this->assertStringNotContainsString('"message"', (string) $missing->getContent());

        $customer = $this->inOrg($this->est)->get('customers/987654')->assertNotFound();
        $this->assertStringContainsString('Not Found', (string) $customer->getContent());

        $boom = $this->inOrg($this->est)->get('_reg/boom')->assertStatus(500);
        $this->assertStringContainsString('Server Error', (string) $boom->getContent());
        $this->assertStringNotContainsString('reg-secret', (string) $boom->getContent());
        $this->assertStringNotContainsString('"message"', (string) $boom->getContent());
    }

    public function test_a_server_error_is_still_reported_to_the_log_with_its_real_message(): void
    {
        $logger = \Mockery::spy(LoggerInterface::class);
        $this->app->instance(LoggerInterface::class, $logger);

        $this->inOrg($this->est)->getJson('_reg/boom')->assertStatus(500)->assertExactJson(['message' => 'Server error.']);

        $logger->shouldHaveReceived('error')
            ->withArgs(fn ($message) => str_contains((string) $message, 'reg-secret'))
            ->atLeast()->once();
    }

    public function test_the_example_environment_file_ships_production_with_debug_off(): void
    {
        $lines = preg_split('/\R/', (string) file_get_contents(base_path('.env.example')));

        $this->assertContains('APP_ENV=production', $lines);
        $this->assertContains('APP_DEBUG=false', $lines);
        $this->assertNotContains('APP_DEBUG=true', $lines);
    }

    // ---- O05 / L06: the allowed path stays quiet and unchanged ------------------------------------------------------

    #[DataProvider('modes')]
    public function test_ordinary_allowed_requests_still_succeed_and_write_no_audit_row(string $mode): void
    {
        $this->setMode($mode);

        $this->inOrg($this->est)->get("projects/{$this->p1}")->assertOk();
        $this->inOrg($this->est)->getJson("quotes/{$this->q1}/details")->assertOk();
        $this->inOrg($this->est)->getJson('quotes/list')->assertOk();
        foreach (['org-admin', 'org-admin/connections', 'org-admin/delegations', 'org-admin/audit-log', 'org-admin/projects'] as $uri) {
            $this->inOrg($this->ownerA)->get($uri)->assertOk();
        }

        $this->assertSame(0, AuditLog::count(), 'allowed requests must not write rows: '.json_encode(AuditLog::pluck('outcome', 'route_uri')));
    }

    #[DataProvider('modes')]
    public function test_an_owner_still_removes_a_role_a_connection_and_a_project_member_of_the_own_org_with_the_old_answers(string $mode): void
    {
        $this->setMode($mode);
        $row = (int) DB::table('user_org_roles')->where('user_id', $this->viewer->id)->value('id');
        $conn = OrgRelationship::create(['from_org_id' => $this->orgA->id, 'to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller', 'is_active' => true])->id;
        $pm = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->super->id)->value('id');
        $lead = $this->mkUser($this->orgA, 'project_manager');

        $this->inOrg($this->ownerA)->from('org-admin')->delete("org-admin/roles/$row")->assertRedirect('org-admin')->assertSessionHas('success');
        $this->inOrg($this->ownerA)->from('org-admin/connections')->delete("org-admin/connections/$conn")->assertRedirect('org-admin/connections')->assertSessionHas('success', 'Connection deactivated.');
        $this->member($this->p1, $lead, $this->orgA);
        $this->inOrg($lead)->from('org-admin/projects')->delete("org-admin/projects/members/$pm")->assertRedirect('org-admin/projects')->assertSessionHas('success');

        $this->assertSame(0, (int) DB::table('user_org_roles')->where('id', $row)->value('is_active'));
        $this->assertFalse((bool) OrgRelationship::find($conn)->is_active);
        $this->assertSame(0, (int) DB::table('project_members')->where('id', $pm)->value('is_active'));
    }

    #[DataProvider('modes')]
    public function test_a_user_without_an_organization_still_gets_403_not_404_when_deleting_a_connection_or_a_project_member(string $mode): void
    {
        $this->setMode($mode);
        $orgless = User::factory()->create(['role' => 'user']);
        $conn = OrgRelationship::create(['from_org_id' => $this->orgA->id, 'to_org_id' => $this->orgB->id, 'relationship_type' => 'buyer_seller', 'is_active' => true])->id;
        $pm = (int) DB::table('project_members')->where('project_id', $this->p1)->where('user_id', $this->super->id)->value('id');

        $this->actingAs($orgless)->deleteJson("org-admin/connections/$conn")->assertForbidden();
        $this->actingAs($orgless)->deleteJson("org-admin/projects/members/$pm")->assertForbidden();

        $this->assertTrue((bool) OrgRelationship::find($conn)->is_active);
        $this->assertSame(1, (int) DB::table('project_members')->where('id', $pm)->value('is_active'));
    }

    // ---- D21: names that real organizations have ---------------------------------------------------------------------

    public static function realisticNames(): array
    {
        return [
            'apostrophe, ampersand and brackets' => ["O'Brien & Sons (Pty) Ltd", "O'Brien & Sons (Pty) Ltd"],
            'accented letters' => ['Müller Bau GmbH', 'Müller Bau GmbH'],
            'surrounding spaces typed by the user' => ['Plain Co', '  Plain Co  '],
            'a name with a percent sign' => ['100% Concrete', '100% Concrete'],
        ];
    }

    #[DataProvider('realisticNames')]
    public function test_the_exact_name_of_a_real_organization_still_creates_the_connection(string $stored, string $typed): void
    {
        $partner = $this->mkOrg($stored);

        $this->inOrg($this->ownerA)->from('org-admin/connections')
            ->post('org-admin/connections', ['partner_name' => $typed, 'relationship_type' => 'buyer_seller'])
            ->assertRedirect('org-admin/connections')->assertSessionHas('success', 'Connection created.');

        $rel = OrgRelationship::firstOrFail();
        $this->assertSame([$this->orgA->id, $partner->id], [(int) $rel->from_org_id, (int) $rel->to_org_id]);
    }

    public function test_a_typed_name_with_markup_is_escaped_when_the_form_is_shown_again(): void
    {
        $name = '"><script>alert(1)</script>';

        $page = $this->inOrg($this->ownerA)->followingRedirects()->from('org-admin/connections')
            ->post('org-admin/connections', ['partner_name' => $name, 'relationship_type' => 'buyer_seller'])->assertOk();

        $html = (string) $page->getContent();
        $this->assertStringNotContainsString($name, $html);
        $this->assertStringContainsString(e($name), $html);
        $this->assertSame(0, OrgRelationship::count());
    }

    #[DataProvider('modes')]
    public function test_a_hidden_to_org_id_next_to_the_name_is_ignored(string $mode): void
    {
        $this->setMode($mode);
        $partner = $this->mkOrg('Partner C');
        $post = fn (array $data) => $this->inOrg($this->ownerA)->from('org-admin/connections')
            ->post('org-admin/connections', $data + ['relationship_type' => 'buyer_seller']);

        $post(['partner_name' => 'Nowhere Ltd', 'to_org_id' => $this->orgB->id])
            ->assertRedirect('org-admin/connections')
            ->assertSessionHas('error', 'No organization matches that name. Check the exact spelling with your partner.');
        $this->assertSame(0, OrgRelationship::count());

        $post(['partner_name' => 'Partner C', 'to_org_id' => $this->orgB->id])->assertSessionHas('success', 'Connection created.');

        $rel = OrgRelationship::firstOrFail();
        $this->assertSame(1, OrgRelationship::count());
        $this->assertSame([$this->orgA->id, $partner->id], [(int) $rel->from_org_id, (int) $rel->to_org_id]);
    }

    // ---- L06: the delegation screens answer exactly as before ---------------------------------------------------------

    #[DataProvider('modes')]
    public function test_granting_and_revoking_a_delegation_keeps_the_old_flash_messages_on_both_screens(string $mode): void
    {
        $this->setMode($mode);
        $delegate = $this->mkUser($this->orgA, 'viewer_read_only');

        $granted = $this->inOrg($this->ownerA)->from('org-admin/delegations')->post('org-admin/delegations', [
            'to_user_id' => $delegate->id,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect('org-admin/delegations');
        $granted->assertSessionHas('success', 'Delegation granted successfully.')->assertSessionHas('peel_off_suggestion');

        $id = (int) DB::table('delegations')->where('to_user_id', $delegate->id)->value('id');
        $this->inOrg($this->ownerA)->from('org-admin/delegations')->delete("org-admin/delegations/$id")
            ->assertRedirect('org-admin/delegations')->assertSessionHas('success', 'Delegation revoked.');
        $this->assertSame(0, (int) DB::table('delegations')->where('id', $id)->value('is_active'));

        $second = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->inOrg($this->ownerA)->post('org-admin/delegations', [
            'to_user_id' => $second->id,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDay()->toDateTimeString(),
        ]);
        $adminId = (int) DB::table('delegations')->where('to_user_id', $second->id)->value('id');
        $this->actingAs($this->admin)->from('admin/rbac/delegations-overview')->delete(route('admin.rbac.delegations.revoke', $adminId))
            ->assertRedirect('admin/rbac/delegations-overview')->assertSessionHas('success', 'Delegation revoked.');
        $this->assertSame(0, (int) DB::table('delegations')->where('id', $adminId)->value('is_active'));
    }

    #[DataProvider('modes')]
    public function test_a_listed_universal_admin_who_is_also_a_delegate_keeps_the_bypass_rows_and_gets_at_most_one_delegated_action_row(string $mode): void
    {
        $this->setMode($mode);
        $ua = User::factory()->create(['role' => 'user']);
        config(['rbac.universal_admin_user_ids' => [$ua->id]]);
        $principal = $this->mkUser($this->orgA, 'superintendent');
        $target = $this->mkUser($this->orgA, 'viewer_read_only');
        DB::table('delegations')->insert([
            'from_user_id' => $principal->id, 'to_user_id' => $ua->id, 'org_id' => $this->orgA->id, 'granted_by' => $principal->id,
            'starts_at' => now()->subHour(), 'expires_at' => now()->addDay(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->inOrg($ua)->postJson('org-admin/roles', ['user_id' => $target->id, 'role_id' => $this->roleId('foreman')])->assertOk();

        $this->assertTrue($this->holds($target, $this->orgA, 'foreman'));
        $this->assertGreaterThanOrEqual(1, AuditLog::where('outcome', 'allowed_universal_admin')->where('user_id', $ua->id)->count());
        $this->assertLessThanOrEqual(1, AuditLog::where('outcome', 'delegated_action')->count());
    }

    #[DataProvider('modes')]
    public function test_very_long_names_never_break_a_delegation_audit_row_or_the_request_that_writes_it(string $mode): void
    {
        $this->setMode($mode);
        $longOwner = User::factory()->create(['role' => 'user', 'name' => str_repeat('P', 300)]);
        $this->assignRole($longOwner, $this->orgA, 'organization_owner');
        $longDelegate = User::factory()->create(['role' => 'user', 'name' => str_repeat('D', 300)]);
        $this->assignRole($longDelegate, $this->orgA, 'viewer_read_only');

        $this->inOrg($longOwner)->from('org-admin/delegations')->post('org-admin/delegations', [
            'to_user_id' => $longDelegate->id,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect('org-admin/delegations')->assertSessionHas('success', 'Delegation granted successfully.');
        $this->inOrg($longDelegate)->get('org-admin/delegations')->assertOk();

        $granted = AuditLog::where('outcome', 'delegation_granted')->get();
        $acted = AuditLog::where('outcome', 'delegated_action')->get();
        $this->assertCount(1, $granted);
        $this->assertCount(1, $acted);
        foreach ([$granted[0], $acted[0]] as $row) {
            $this->assertLessThanOrEqual(255, mb_strlen((string) $row->reason));
            $this->assertSame($this->orgA->id, (int) $row->org_id);
        }
        $this->assertSame($longDelegate->id, (int) $acted[0]->user_id);
    }
}

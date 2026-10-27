<?php

namespace Tests\Feature\Rbac;

use App\Http\Middleware\RbacAudit;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\User;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Client audit 2, L06: delegation grants, revocations and the requests a delegate makes as the principal reach the audit
 * log (rbac_audit_logs outcomes delegation_granted, delegation_revoked, delegated_action).
 */
class DelegationAuditTest extends ProjectTestCase
{
    private User $ownerA;
    private User $delegate;
    private User $ownerB;
    private User $ua;
    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = $this->mkUser($this->orgA, 'organization_owner');
        $this->delegate = $this->mkUser($this->orgA, 'viewer_read_only');
        $this->ownerB = $this->mkUser($this->orgB, 'organization_owner');
        $this->ua = User::factory()->create(['role' => 'user']);
        $this->platformAdmin = User::factory()->create(['role' => 'admin']);

        config(['rbac.universal_admin_user_ids' => []]);
        $this->setMode('audit');
    }

    public static function modes(): array
    {
        return ['audit' => ['audit'], 'enforce' => ['enforce']];
    }

    private function as(User $u, string $method, string $uri, array $data = [], ?int $orgId = null)
    {
        return $this->actingAs($u)
            ->withSession([config('rbac.current_org_session_key') => $orgId ?? $this->orgA->id])
            ->json($method, $uri, $data);
    }

    private function grantVia(User $actor, User $to, ?int $orgId = null)
    {
        return $this->as($actor, 'POST', 'org-admin/delegations', [
            'to_user_id' => $to->id,
            'starts_at' => now()->subHour()->toDateTimeString(),
            'expires_at' => now()->addDay()->toDateTimeString(),
        ], $orgId);
    }

    private function delegation(User $from, User $to, $org, ?string $starts = null, ?string $expires = null, bool $active = true): Delegation
    {
        return Delegation::create([
            'from_user_id' => $from->id, 'to_user_id' => $to->id, 'org_id' => $org->id, 'granted_by' => $from->id,
            'starts_at' => $starts ?? now()->subHour(), 'expires_at' => $expires ?? now()->addDay(), 'is_active' => $active,
        ]);
    }

    private function rows(string $outcome)
    {
        return AuditLog::where('outcome', $outcome)->orderBy('id')->get();
    }

    private function failAuditWrites(): void
    {
        AuditLog::creating(fn () => throw new \RuntimeException('audit table is down'));
    }

    // ---- grant and revoke -------------------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_grant_writes_one_row_for_the_right_org_naming_both_users_and_the_window(string $mode): void
    {
        $this->setMode($mode);

        $this->grantVia($this->ownerA, $this->delegate)->assertRedirect();

        $rows = $this->rows('delegation_granted');
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame([$this->ownerA->id, $this->orgA->id], [(int) $row->user_id, (int) $row->org_id]);
        $this->assertSame(['delegation_and_impersonation', 'O', 'POST', 'org-admin/delegations'], [$row->permission_group, $row->required_level, $row->method, $row->route_uri]);
        $d = Delegation::firstOrFail();
        $this->assertSame(
            "delegate {$this->delegate->name} (#{$this->delegate->id}) for {$this->ownerA->name} (#{$this->ownerA->id}), {$d->starts_at->toDateTimeString()} to {$d->expires_at->toDateTimeString()}",
            $row->reason
        );
        $this->assertSame(0, $this->rows('delegation_revoked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_refused_grant_writes_nothing(string $mode): void
    {
        $this->setMode($mode);
        $outsider = $this->mkUser($this->orgB, 'estimator');

        $this->grantVia($this->ownerA, $outsider)->assertForbidden();
        $this->grantVia($this->delegate, $this->ownerA)->assertForbidden();

        $this->assertSame(0, Delegation::count());
        $this->assertSame(0, $this->rows('delegation_granted')->count());
    }

    #[DataProvider('modes')]
    public function test_a_revoke_by_the_owner_writes_one_row_for_the_delegation_org(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();

        $this->assertFalse((bool) $d->fresh()->is_active);
        $rows = $this->rows('delegation_revoked');
        $this->assertCount(1, $rows);
        $this->assertSame([$this->ownerA->id, $this->orgA->id, 'delegation_and_impersonation', 'O', 'DELETE'], [
            (int) $rows[0]->user_id, (int) $rows[0]->org_id, $rows[0]->permission_group, $rows[0]->required_level, $rows[0]->method,
        ]);
        $this->assertStringContainsString("delegate {$this->delegate->name} (#{$this->delegate->id}) for {$this->ownerA->name} (#{$this->ownerA->id})", $rows[0]->reason);
        $this->assertSame(0, $this->rows('delegation_granted')->count());
    }

    #[DataProvider('modes')]
    public function test_a_refused_revoke_writes_nothing(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->as($this->ownerB, 'DELETE', "org-admin/delegations/{$d->id}", [], $this->orgB->id)->assertForbidden();
        $this->as($this->delegate, 'DELETE', "org-admin/delegations/{$d->id}")->assertForbidden();

        $this->assertTrue((bool) $d->fresh()->is_active);
        $this->assertSame(0, $this->rows('delegation_revoked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_universal_admin_override_revoke_writes_the_revoke_row_next_to_the_bypass_row(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);
        $this->ua->forceFill(['email_verified_at' => now()])->save();
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->as($this->ua, 'DELETE', "org-admin/delegations/{$d->id}", [], $this->orgA->id)->assertRedirect();

        $this->assertFalse((bool) $d->fresh()->is_active);
        $rows = $this->rows('delegation_revoked');
        $this->assertCount(1, $rows);
        $this->assertSame([$this->ua->id, $this->orgA->id], [(int) $rows[0]->user_id, (int) $rows[0]->org_id]);
        $this->assertStringContainsString("for {$this->ownerA->name} (#{$this->ownerA->id})", $rows[0]->reason);
        $this->assertSame(1, AuditLog::where('outcome', 'allowed_universal_admin')->where('reason', 'universal_delegation_revoke')->count());
    }

    #[DataProvider('modes')]
    public function test_the_platform_admin_screen_revoke_writes_one_row_for_the_delegation_org(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA);
        $other = $this->delegation($this->ownerB, $this->mkUser($this->orgB, 'estimator'), $this->orgB);

        $this->actingAs($this->platformAdmin)->delete(route('admin.rbac.delegations.revoke', $d))->assertRedirect();

        $this->assertFalse((bool) $d->fresh()->is_active);
        $this->assertTrue((bool) $other->fresh()->is_active);
        $rows = $this->rows('delegation_revoked');
        $this->assertCount(1, $rows);
        $this->assertSame(
            [$this->platformAdmin->id, $this->orgA->id, 'delegation_and_impersonation', 'F', 'DELETE'],
            [(int) $rows[0]->user_id, (int) $rows[0]->org_id, $rows[0]->permission_group, $rows[0]->required_level, $rows[0]->method]
        );
        $this->assertStringContainsString("delegate {$this->delegate->name} (#{$this->delegate->id}) for {$this->ownerA->name} (#{$this->ownerA->id})", $rows[0]->reason);
    }

    #[DataProvider('modes')]
    public function test_revoking_an_already_revoked_delegation_on_the_org_screen_writes_no_second_row(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();
        $this->assertSame(1, $this->rows('delegation_revoked')->count());

        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();

        $this->assertSame(1, $this->rows('delegation_revoked')->count());
        $this->assertSame($this->ownerA->id, (int) $this->rows('delegation_revoked')[0]->user_id);
    }

    #[DataProvider('modes')]
    public function test_revoking_an_already_revoked_delegation_on_the_admin_screen_writes_no_second_row(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->actingAs($this->platformAdmin)->delete(route('admin.rbac.delegations.revoke', $d))->assertRedirect();
        $this->assertSame(1, $this->rows('delegation_revoked')->count());

        $this->actingAs($this->platformAdmin)->delete(route('admin.rbac.delegations.revoke', $d))->assertRedirect();

        $this->assertSame(1, $this->rows('delegation_revoked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_later_revoke_by_another_actor_is_not_attributed_to_them(string $mode): void
    {
        $this->setMode($mode);
        config(['rbac.universal_admin_user_ids' => [$this->ua->id]]);
        $this->ua->forceFill(['email_verified_at' => now()])->save();
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA);
        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();

        $this->as($this->ua, 'DELETE', "org-admin/delegations/{$d->id}", [], $this->orgA->id)->assertRedirect();

        $rows = $this->rows('delegation_revoked');
        $this->assertCount(1, $rows);
        $this->assertSame($this->ownerA->id, (int) $rows[0]->user_id);
        $this->assertSame(1, AuditLog::where('outcome', 'allowed_universal_admin')->where('reason', 'universal_delegation_revoke')->count(), 'the bypass row is still written');
    }

    #[DataProvider('modes')]
    public function test_revoking_a_delegation_that_was_created_inactive_writes_no_row(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA, null, null, false);

        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect()->assertSessionHas('success', 'Delegation revoked.');
        $this->actingAs($this->platformAdmin)->delete(route('admin.rbac.delegations.revoke', $d))->assertRedirect()->assertSessionHas('success', 'Delegation revoked.');

        $this->assertSame(0, $this->rows('delegation_revoked')->count());
    }

    #[DataProvider('modes')]
    public function test_revoking_an_expired_but_still_active_delegation_counts_as_a_revoke(string $mode): void
    {
        $this->setMode($mode);
        $d = $this->delegation($this->ownerA, $this->delegate, $this->orgA, now()->subDays(3)->toDateTimeString(), now()->subDay()->toDateTimeString());

        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();

        $this->assertSame(1, $this->rows('delegation_revoked')->count());
    }

    #[DataProvider('modes')]
    public function test_a_failing_audit_write_never_blocks_a_grant_or_a_revoke(string $mode): void
    {
        $this->setMode($mode);
        Log::spy();
        $this->failAuditWrites();

        $this->grantVia($this->ownerA, $this->delegate)->assertRedirect();
        $d = Delegation::firstOrFail();
        $this->assertTrue((bool) $d->is_active);

        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();
        $this->assertFalse((bool) $d->fresh()->is_active);

        $d2 = $this->delegation($this->ownerA, $this->delegate, $this->orgA);
        $this->actingAs($this->platformAdmin)->delete(route('admin.rbac.delegations.revoke', $d2))->assertRedirect();
        $this->assertFalse((bool) $d2->fresh()->is_active);

        $this->assertSame(0, AuditLog::count());
        Log::shouldHaveReceived('error')->times(3);
    }

    // ---- delegated actions ------------------------------------------------------------------------------------------

    #[DataProvider('modes')]
    public function test_a_delegate_request_on_a_mapped_route_writes_one_delegated_action_row_naming_the_principal(string $mode): void
    {
        $this->setMode($mode);
        $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->as($this->delegate, 'GET', 'org-admin/delegations')->assertOk();

        $rows = $this->rows('delegated_action');
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame([$this->delegate->id, $this->orgA->id], [(int) $row->user_id, (int) $row->org_id]);
        $this->assertSame("acting for {$this->ownerA->name} (#{$this->ownerA->id})", $row->reason);
        $this->assertSame(
            ['GET', 'org-admin/delegations', 'GET org-admin/delegations', 'delegation_and_impersonation', 'R', 'read'],
            [$row->method, $row->route_uri, $row->matched_pattern, $row->permission_group, $row->required_level, $row->batch]
        );
        $this->assertSame(0, AuditLog::where('outcome', '!=', 'delegated_action')->count());
    }

    #[DataProvider('modes')]
    public function test_the_row_carries_the_project_and_quote_of_a_scoped_route(string $mode): void
    {
        $this->setMode($mode);
        config(['route_permission_map' => config('route_permission_map') + [
            'GET probe/q/{id}' => ['estimate_management', 'R', 'batch' => 'read', 'quote_param' => 'id'],
        ]]);
        Route::middleware([StartSession::class, RbacAudit::class])->get('probe/q/{id}', fn () => 'ran');
        $this->delegation($this->est, $this->stranger, $this->orgA);

        $this->as($this->stranger, 'GET', "probe/q/{$this->q1}")->assertOk();

        $row = $this->rows('delegated_action')->first();
        $this->assertNotNull($row);
        $this->assertSame([$this->p1, $this->q1, $this->stranger->id], [(int) $row->project_id, (int) $row->quote_id, (int) $row->user_id]);
        $this->assertSame("acting for {$this->est->name} (#{$this->est->id})", $row->reason);
    }

    #[DataProvider('modes')]
    public function test_each_request_writes_exactly_one_row(string $mode): void
    {
        $this->setMode($mode);
        $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->as($this->delegate, 'GET', 'org-admin/delegations')->assertOk();
        $this->as($this->delegate, 'GET', 'org-admin/delegations')->assertOk();

        $this->assertSame(2, $this->rows('delegated_action')->count());
    }

    public static function inertDelegations(): array
    {
        return [
            'expired' => ['-3 days', '-1 day', true],
            'not started yet' => ['+1 day', '+3 days', true],
            'revoked' => ['-1 hour', '+1 day', false],
        ];
    }

    #[DataProvider('inertDelegations')]
    public function test_an_expired_future_or_revoked_delegation_writes_no_row(string $starts, string $expires, bool $active): void
    {
        $this->setMode('audit');
        $secondOwner = $this->mkUser($this->orgA, 'organization_owner');
        $this->delegation($this->ownerA, $secondOwner, $this->orgA, now()->modify($starts)->toDateTimeString(), now()->modify($expires)->toDateTimeString(), $active);

        $this->as($secondOwner, 'GET', 'org-admin/delegations')->assertOk();

        $this->assertSame(0, $this->rows('delegated_action')->count());
    }

    #[DataProvider('modes')]
    public function test_a_non_delegate_and_the_principal_themselves_write_no_row(string $mode): void
    {
        $this->setMode($mode);
        $this->delegation($this->ownerA, $this->delegate, $this->orgA);

        $this->as($this->ownerA, 'GET', 'org-admin/delegations')->assertOk();
        $this->as($this->ownerB, 'GET', 'org-admin/delegations', [], $this->orgB->id)->assertOk();
        $this->as($this->est, 'GET', 'quotes/list')->assertOk();

        $this->assertSame(0, $this->rows('delegated_action')->count());
    }

    public function test_a_delegation_in_another_org_does_not_make_the_users_requests_delegated_actions(): void
    {
        $this->setMode('audit');
        $this->delegation($this->ownerB, $this->multi, $this->orgB);
        $this->assignRole($this->multi, $this->orgB, 'viewer_read_only');

        $this->as($this->multi, 'GET', "quotes/{$this->q1}/details", [], $this->orgA->id)->assertOk();

        $this->assertSame(0, $this->rows('delegated_action')->count());
    }

    public function test_a_denied_delegate_request_writes_no_delegated_action_row(): void
    {
        $this->setMode('enforce');
        $this->delegation($this->est, $this->stranger, $this->orgA);

        $this->as($this->stranger, 'PUT', "quotes/{$this->q3}", ['notes' => 'x'])->assertStatus(403);

        $this->assertSame(0, $this->rows('delegated_action')->count());
        $this->assertSame(1, $this->rows('blocked')->count());
    }

    public function test_an_unmapped_route_is_not_logged(): void
    {
        $this->setMode('audit');
        $this->delegation($this->ownerA, $this->delegate, $this->orgA);
        Route::middleware([StartSession::class, RbacAudit::class])->get('probe/unmapped', fn () => 'ok');

        $this->as($this->delegate, 'GET', 'probe/unmapped')->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    #[DataProvider('modes')]
    public function test_a_failing_audit_write_never_blocks_a_delegate_request(string $mode): void
    {
        $this->setMode($mode);
        $this->delegation($this->ownerA, $this->delegate, $this->orgA);
        Log::spy();
        $this->failAuditWrites();

        $this->as($this->delegate, 'GET', 'org-admin/delegations')->assertOk();

        $this->assertSame(0, AuditLog::count());
        Log::shouldHaveReceived('error')->once();
    }

    // ---- where the rows show up -------------------------------------------------------------------------------------

    public function test_the_org_audit_page_shows_the_rows_with_their_labels_and_details_only_to_their_own_org(): void
    {
        $this->setMode('audit');
        $this->grantVia($this->ownerA, $this->delegate)->assertRedirect();
        $this->as($this->delegate, 'GET', 'org-admin/delegations')->assertOk();
        $d = Delegation::firstOrFail();
        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();

        $page = $this->actingAs($this->ownerA)
            ->withSession([config('rbac.current_org_session_key') => $this->orgA->id])
            ->get('org-admin/audit-log?tab=enforcement')->assertOk();
        $page->assertSee('Delegation granted')->assertSee('Delegation revoked')->assertSee('Delegated action');
        $page->assertSee("acting for {$this->ownerA->name} (#{$this->ownerA->id})");

        $other = $this->actingAs($this->ownerB)
            ->withSession([config('rbac.current_org_session_key') => $this->orgB->id])
            ->get('org-admin/audit-log?tab=enforcement')->assertOk();
        $other->assertDontSee('Delegation granted')->assertDontSee('Delegation revoked')->assertDontSee('Delegated action');
        $other->assertDontSee("acting for {$this->ownerA->name}");
    }

    public function test_the_platform_admin_audit_page_labels_the_new_outcomes(): void
    {
        $this->setMode('audit');
        $this->grantVia($this->ownerA, $this->delegate)->assertRedirect();
        $this->as($this->delegate, 'GET', 'org-admin/delegations')->assertOk();
        $d = Delegation::firstOrFail();
        $this->as($this->ownerA, 'DELETE', "org-admin/delegations/{$d->id}")->assertRedirect();

        $page = $this->actingAs($this->platformAdmin)->get(route('admin.rbac.audit-logs'))->assertOk();

        $page->assertSee('Delegation granted')->assertSee('Delegation revoked')->assertSee('Delegated action');
        $this->assertDoesNotMatchRegularExpression('/<span class="badge[^"]*">Would Block<\/span>/', $page->getContent());
    }
}

<?php

namespace Tests\Feature\Rbac;

use App\Http\Middleware\RbacAudit;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\RbacSetting;
use App\Models\Rbac\Role;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * RBAC audit-mode middleware (plan §5.2, Stage B/C).
 */
class AuditMiddlewareTest extends RbacTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A single mapped, protected route used by every test here.
        config(['route_permission_map' => [
            'GET rbac-probe' => ['procurement', 'S', 'batch' => 'read'],
        ]]);

        Route::middleware(RbacAudit::class)->get('rbac-probe', fn () => 'ok');
    }

    public function test_audit_mode_logs_would_block_but_lets_request_through(): void
    {
        RbacSetting::set('rbac_mode', 'audit');
        $user = User::factory()->create(); // no org → cannot satisfy anything

        $this->actingAs($user)->get('rbac-probe')->assertOk()->assertSee('ok');

        $this->assertSame(1, AuditLog::count());
        $log = AuditLog::first();
        $this->assertSame('would_block', $log->outcome);
        $this->assertSame('no_org', $log->reason);
        $this->assertSame('procurement', $log->permission_group);
    }

    public function test_permitted_request_is_not_logged(): void
    {
        RbacSetting::set('rbac_mode', 'audit');
        $user = $this->userWithRole('requisitioner'); // Requisitioner has S on procurement

        $this->actingAs($user)->get('rbac-probe')->assertOk();

        $this->assertSame(0, AuditLog::count(), 'allowed requests are not recorded');
    }

    public function test_unmapped_route_is_ignored(): void
    {
        Route::middleware(RbacAudit::class)->get('rbac-unmapped', fn () => 'ok');
        $user = User::factory()->create();

        $this->actingAs($user)->get('rbac-unmapped')->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_enforce_mode_blocks_and_records_blocked(): void
    {
        RbacSetting::set('rbac_mode', 'enforce');
        config(['rbac.enforce_batches' => []]); // enforce everything
        $user = User::factory()->create(); // no org

        $this->actingAs($user)->get('rbac-probe')->assertForbidden();

        $this->assertSame('blocked', AuditLog::first()->outcome);
    }

    public function test_enforce_only_applies_to_enabled_batches(): void
    {
        // Enforcing the 'write' batch only; our route is 'read' → still audit (pass-through).
        RbacSetting::set('rbac_mode', 'enforce');
        config(['rbac.enforce_batches' => ['write']]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('rbac-probe')->assertOk();
        $this->assertSame('would_block', AuditLog::first()->outcome);
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Acme', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        UserOrgRole::create([
            'user_id' => $user->id, 'org_id' => $org->id, 'role_id' => $role->id,
            'assigned_at' => now(), 'is_active' => true,
        ]);

        return $user;
    }
}

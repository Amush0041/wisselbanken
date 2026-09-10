<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Organization;
use App\Models\Rbac\Role;
use App\Models\Rbac\RoleAssignmentLog;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\RoleAssignmentService;

class RoleAssignmentLogTest extends RbacTestCase
{
    private RoleAssignmentService $service;
    private Organization $org;
    private User $admin;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RoleAssignmentService::class);
        $this->org     = Organization::create(['name' => 'Test Org', 'org_type' => 'subcontractor', 'team_size' => 'solo']);
        $this->admin   = User::factory()->create();
        $this->role    = Role::where('slug', 'viewer_read_only')->firstOrFail();
    }

    public function test_assign_writes_assigned_log_entry(): void
    {
        $user = User::factory()->create();

        $this->service->assign($this->admin->id, $user->id, $this->org->id, $this->role->id);

        $this->assertSame(1, RoleAssignmentLog::count());
        $log = RoleAssignmentLog::first();
        $this->assertSame('assigned', $log->action);
        $this->assertSame($user->id, $log->target_user_id);
        $this->assertSame($this->role->id, $log->role_id);
        $this->assertSame($this->admin->id, $log->performed_by);
        $this->assertSame($this->org->id, $log->org_id);
    }

    public function test_reassigning_already_active_role_does_not_duplicate_log(): void
    {
        $user = User::factory()->create();

        $this->service->assign($this->admin->id, $user->id, $this->org->id, $this->role->id);
        $this->service->assign($this->admin->id, $user->id, $this->org->id, $this->role->id);

        $this->assertSame(1, RoleAssignmentLog::count());
    }

    public function test_deactivate_writes_removed_log_entry(): void
    {
        $user = User::factory()->create();
        $result = $this->service->assign($this->admin->id, $user->id, $this->org->id, $this->role->id);

        $this->service->deactivate($result['assignment']->id, $this->admin->id);

        $logs = RoleAssignmentLog::orderBy('id')->get();
        $this->assertSame(2, $logs->count());
        $this->assertSame('assigned', $logs->first()->action);
        $this->assertSame('removed', $logs->last()->action);
        $this->assertSame($this->admin->id, $logs->last()->performed_by);
    }

    public function test_peel_off_writes_removed_log_entry(): void
    {
        $user = User::factory()->create();
        UserOrgRole::create([
            'user_id'     => $user->id,
            'org_id'      => $this->org->id,
            'role_id'     => $this->role->id,
            'assigned_at' => now(),
            'is_active'   => true,
        ]);

        $this->service->peelOff($user->id, $this->org->id, $this->role->id);

        $log = RoleAssignmentLog::first();
        $this->assertSame('removed', $log->action);
        $this->assertSame($user->id, $log->performed_by);
    }

    public function test_reactivating_removed_role_writes_new_assigned_entry(): void
    {
        $user   = User::factory()->create();
        $result = $this->service->assign($this->admin->id, $user->id, $this->org->id, $this->role->id);
        $this->service->deactivate($result['assignment']->id, $this->admin->id);

        $this->service->assign($this->admin->id, $user->id, $this->org->id, $this->role->id);

        $this->assertSame(3, RoleAssignmentLog::count());
        $actions = RoleAssignmentLog::orderBy('id')->pluck('action')->all();
        $this->assertSame(['assigned', 'removed', 'assigned'], $actions);
    }
}

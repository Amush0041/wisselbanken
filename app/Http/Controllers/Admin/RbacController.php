<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Rbac\ApiToken;
use App\Models\Rbac\AuditLog;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\OrgRelationship;
use App\Models\Rbac\ProjectMember;
use App\Models\Rbac\ProjectMemberLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\RoleAssignmentLog;
use App\Models\Rbac\PermissionGroup;
use App\Models\Rbac\Role;
use App\Models\Rbac\UserOrgRole;
use App\Exceptions\Rbac\SodConflictException;
use App\Models\Rbac\RbacSetting;
use App\Models\Rbac\SodConflictRule;
use App\Models\Rbac\RolePermission;
use App\Models\User;
use App\Services\Rbac\PermissionMatrix;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Platform-admin RBAC management (admin-only). Unlike the org-owner screen at /org-admin,
 * this gives a platform administrator a cross-organization view: the role/permission
 * matrix, every organization and its members, the audit-mode log, and enforcement status.
 */
class RbacController extends Controller
{
    public function __construct(private readonly RoleAssignmentService $assignments)
    {
    }

    /** RBAC overview dashboard with headline counts. */
    public function index()
    {
        $stats = [
            'organizations' => Organization::count(),
            'roles' => Role::count(),
            'permission_groups' => PermissionGroup::count(),
            'active_assignments' => UserOrgRole::where('is_active', true)->count(),
            'audit_would_block' => AuditLog::where('outcome', 'would_block')->count(),
            'audit_blocked' => AuditLog::where('outcome', 'blocked')->count(),
        ];

        $mode = RbacSetting::get('rbac_mode', 'audit');
        $enforceBatches = config('rbac.enforce_batches', []);
        $byBatch = AuditLog::select('batch', 'outcome', DB::raw('count(*) as total'))
            ->groupBy('batch', 'outcome')
            ->get()
            ->groupBy('batch');

        return view('admin.rbac.index', compact('stats', 'mode', 'enforceBatches', 'byBatch'));
    }

/** Read-only role × permission-group matrix. */
    public function roles(Request $request)
    {
        // Only the 15 permission groups actively wired in route_permission_map.php.
        // Future groups (Finance & Commercial, Supply Chain, Compliance, etc.) are
        // commented out in the view's $permSections and excluded here.
        $activeGroupSlugs = [
            'procurement', 'approval_authority', 'estimate_management',
            'project_management', 'quote_rfq_management', 'product_management',
            'user_management', 'delegation_and_impersonation', 'organization_management',
            'pricing_management', 'order_fulfillment', 'manufacturer_controls',
            'catalog_taxonomy_and_data_quality', 'audit_and_logging', 'system_administration',
        ];
        $groups = PermissionGroup::whereIn('slug', $activeGroupSlugs)->get()
            ->sortBy(fn ($g) => array_search($g->slug, $activeGroupSlugs, true))
            ->values();

        $roleQuery = Role::query()->orderBy('category')->orderBy('name');
        if ($phase = $request->query('phase')) {
            $roleQuery->where('phase', $phase);
        }
        // Put Cross-Functional category last regardless of alphabetical ordering.
        $roles = $roleQuery->get()
            ->sortBy(fn ($r) => ($r->category === 'Cross-Functional' ? 'zzz_' : '') . $r->category . '_' . $r->name)
            ->values();

        // role_id => [group_id => level]
        $matrix = [];
        foreach (DB::table('role_permissions')->get() as $rp) {
            $matrix[$rp->role_id][$rp->permission_group_id] = $rp->access_level;
        }

        return view('admin.rbac.roles', compact('groups', 'roles', 'matrix'));
    }

    /** All organizations with member + role counts. */
    public function organizations()
    {
        $organizations = Organization::withCount([
            'userOrgRoles as active_assignments_count' => fn ($q) => $q->where('is_active', true),
        ])->orderBy('name')->paginate(25);

        return view('admin.rbac.organizations', compact('organizations'));
    }

    /** A single org: its members, their roles, and the assignment form. */
    public function showOrganization(Organization $organization)
    {
        $members = User::whereIn('id', function ($q) use ($organization) {
            $q->select('user_id')->from('user_org_roles')
                ->where('org_id', $organization->id)->where('is_active', true);
        })->orderBy('name')->get()->map(function (User $user) use ($organization) {
            $user->setAttribute('org_roles', UserOrgRole::with('role')
                ->where('user_id', $user->id)
                ->where('org_id', $organization->id)
                ->where('is_active', true)->get());

            return $user;
        });

        $roles = Role::orderBy('category')->orderBy('name')->get();

        return view('admin.rbac.organization-show', compact('organization', 'members', 'roles'));
    }

    /** Assign a role to a user in an org (platform-admin; no peel-off prompt). */
    public function assignRole(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        try {
            $this->assignments->assign(
                $request->user()->id,
                (int) $data['user_id'],
                $organization->id,
                (int) $data['role_id'],
            );
        } catch (SodConflictException $e) {
            return back()->with('error', 'SoD conflict: ' . implode(', ', $e->conflictingRoles) . ' cannot coexist with this role.');
        }

        return back()->with('success', 'Role assigned.');
    }

    /** Soft-remove a single role assignment. */
    public function removeRole(UserOrgRole $userOrgRole)
    {
        $this->assignments->deactivate($userOrgRole->id, request()->user()->id);

        return back()->with('success', 'Role removed.');
    }

    /** Search users by name/email for the org-admin assignment picker (Select2 AJAX). */
    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) ($request->query('q') ?? $request->query('term') ?? ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'results' => $users->map(fn ($u) => [
                'id' => $u->id,
                'text' => "{$u->name} ({$u->email})",
            ]),
        ]);
    }

    /**
     * Set (or clear) a role's access level for one permission group. Super-admin edit of
     * the permission matrix — applies immediately and flushes the engine's cached matrix.
     */
    public function updatePermission(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permission_group_id' => ['required', 'integer', Rule::exists('permission_groups', 'id')],
            // Empty string = clear the cell (role has no access to that group).
            'access_level' => ['nullable', Rule::in(['F', 'A', 'O', 'S', 'R'])],
        ]);

        if (empty($data['access_level'])) {
            RolePermission::where('role_id', $role->id)
                ->where('permission_group_id', $data['permission_group_id'])
                ->delete();
            $level = null;
        } else {
            RolePermission::updateOrCreate(
                ['role_id' => $role->id, 'permission_group_id' => $data['permission_group_id']],
                ['access_level' => $data['access_level']],
            );
            $level = $data['access_level'];
        }

        // The engine serves the matrix from cache — refresh it so the change is live now.
        app(PermissionMatrix::class)->flush();

        return response()->json(['success' => true, 'access_level' => $level]);
    }

    /** Audit-mode log review, filterable. */
    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user')->latest('id');

        if ($outcome = $request->query('outcome')) {
            $query->where('outcome', $outcome);
        }
        if ($group = $request->query('permission_group')) {
            $query->where('permission_group', $group);
        }

        $logs = $query->paginate(50)->withQueryString();
        $groups = PermissionGroup::orderBy('name')->pluck('name', 'slug');

        return view('admin.rbac.audit-logs', compact('logs', 'groups'));
    }

    /** Current enforcement mode + per-batch would-block counts. */
    public function enforcement()
    {
        $mode = RbacSetting::get('rbac_mode', 'audit');
        $enforceBatches = config('rbac.enforce_batches', []);

        $byBatch = AuditLog::select('batch', 'outcome', DB::raw('count(*) as total'))
            ->groupBy('batch', 'outcome')
            ->get()
            ->groupBy('batch');

        $recentActivity = AuditLog::with('user')->latest('id')->limit(5)->get();

        return view('admin.rbac.enforcement', compact('mode', 'enforceBatches', 'byBatch', 'recentActivity'));
    }

    /** Toggle RBAC enforcement mode between audit and enforce. */
    public function toggleMode(Request $request): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['audit', 'enforce'])]]);

        RbacSetting::set('rbac_mode', $data['mode']);

        return back()->with('success', 'Enforcement mode updated to ' . strtoupper($data['mode']) . '. Takes effect within 60 seconds across all servers.');
    }

    /** Platform-admin: all users with their org memberships and roles. */
    public function users(Request $request)
    {
        $search = $request->query('q', '');

        $usersQuery = User::query()->orderBy('name');
        if ($search) {
            $usersQuery->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        $users = $usersQuery->paginate(20)->withQueryString();

        $users->each(function (User $user) {
            $user->setAttribute('org_roles_grouped', UserOrgRole::with(['organization', 'role'])
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->get()
                ->groupBy('org_id'));

            $user->setAttribute('active_delegations', Delegation::with(['fromUser', 'organization'])
                ->where('to_user_id', $user->id)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->get());

            $user->setAttribute('api_tokens', ApiToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->get());
        });

        $stats = [
            'total_users' => User::count(),
            'total_orgs'  => Organization::count(),
            'active_assignments' => UserOrgRole::where('is_active', true)->count(),
        ];

        return view('admin.rbac.users', compact('users', 'search', 'stats'));
    }

    /** Create a new custom role (platform-admin). */
    public function storeRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:roles,name'],
            'slug'        => ['required', 'string', 'max:100', 'unique:roles,slug', 'regex:/^[a-z_]+$/'],
            'category'    => ['required', 'string', 'max:100'],
            'phase'       => ['required', Rule::in(['P1', 'P2', 'P3'])],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Role::create($data);
        app(PermissionMatrix::class)->flush();

        return back()->with('success', "Role \"{$data['name']}\" created successfully.");
    }

    /** Platform-admin: all delegations across all orgs. */
    public function adminDelegations(Request $request)
    {
        $query = Delegation::with(['fromUser', 'toUser', 'organization'])->latest('id');

        if ($orgId = $request->query('org_id')) {
            $query->where('org_id', $orgId);
        }
        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->where('is_active', true)->where('expires_at', '>', now());
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<=', now());
            } elseif ($status === 'revoked') {
                $query->where('is_active', false);
            }
        }

        $delegations = $query->paginate(25)->withQueryString();
        $organizations = Organization::orderBy('name')->get(['id', 'name']);

        $stats = [
            'active'         => Delegation::where('is_active', true)->where('expires_at', '>', now())->count(),
            'expiring_today' => Delegation::where('is_active', true)->whereDate('expires_at', today())->count(),
            'expired_week'   => Delegation::where('expires_at', '>=', now()->subWeek())->where('expires_at', '<=', now())->count(),
        ];

        return view('admin.rbac.delegations', compact('delegations', 'organizations', 'stats'));
    }

    /** Revoke a delegation (platform-admin). */
    public function revokeAdminDelegation(Delegation $delegation): RedirectResponse
    {
        $delegation->update(['is_active' => false]);

        return back()->with('success', 'Delegation revoked.');
    }

    /** Platform-admin: all API tokens (service accounts) across all orgs. */
    public function serviceAccounts(Request $request)
    {
        $query = ApiToken::with('user')->latest('id');

        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            } elseif ($status === 'expiring') {
                $query->where('is_active', true)->where('expires_at', '<=', now()->addDays(7))->where('expires_at', '>', now());
            } elseif ($status === 'revoked') {
                $query->where('is_active', false);
            }
        }

        $tokens = $query->paginate(25)->withQueryString();

        $stats = [
            'active'         => ApiToken::where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'expiring_7days' => ApiToken::where('is_active', true)->where('expires_at', '<=', now()->addDays(7))->where('expires_at', '>', now())->count(),
            'revoked_30days' => ApiToken::where('is_active', false)->where('updated_at', '>=', now()->subDays(30))->count(),
            'stale'          => ApiToken::where('is_active', true)->where(fn ($q) => $q->whereNull('last_used_at')->orWhere('last_used_at', '<=', now()->subDays(90)))->count(),
        ];

        return view('admin.rbac.service-accounts', compact('tokens', 'stats'));
    }

    /** Revoke a service account token (platform-admin). */
    public function revokeAdminToken(ApiToken $apiToken): RedirectResponse
    {
        $apiToken->update(['is_active' => false]);

        return back()->with('success', "Token \"{$apiToken->name}\" revoked.");
    }

    /** SoD conflict rules — list all pairs. */
    public function sod()
    {
        $rules = SodConflictRule::with('roleA', 'roleB')->orderBy('id')->get();
        $roles = Role::orderBy('category')->orderBy('name')->get(['id', 'name', 'slug', 'category']);

        $stats = [
            'total'    => $rules->count(),
            'active'   => $rules->where('is_active', true)->count(),
            'inactive' => $rules->where('is_active', false)->count(),
        ];

        return view('admin.rbac.sod', compact('rules', 'roles', 'stats'));
    }

    /** Create a new SoD conflict rule. */
    public function storeSod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id_a' => ['required', 'integer', Rule::exists('roles', 'id')],
            'role_id_b' => ['required', 'integer', Rule::exists('roles', 'id'), 'different:role_id_a'],
            'reason'    => ['nullable', 'string', 'max:300'],
        ]);

        // Normalise order so the unique constraint always fires for both (A,B) and (B,A).
        [$a, $b] = [(int) $data['role_id_a'], (int) $data['role_id_b']];
        if ($a > $b) {
            [$a, $b] = [$b, $a];
        }

        $existing = SodConflictRule::where(fn ($q) => $q->where('role_id_a', $a)->where('role_id_b', $b))
            ->orWhere(fn ($q) => $q->where('role_id_a', $b)->where('role_id_b', $a))
            ->first();

        if ($existing) {
            return back()->with('error', 'A conflict rule for this pair already exists.');
        }

        SodConflictRule::create([
            'role_id_a'  => $a,
            'role_id_b'  => $b,
            'reason'     => $data['reason'] ?? null,
            'is_active'  => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'SoD conflict rule created.');
    }

    /** Toggle a SoD conflict rule active/inactive without deleting it. */
    public function toggleSod(SodConflictRule $sodConflictRule): RedirectResponse
    {
        $sodConflictRule->update(['is_active' => ! $sodConflictRule->is_active]);

        $state = $sodConflictRule->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "SoD rule {$state}.");
    }

    /** Delete a SoD conflict rule. */
    public function destroySod(SodConflictRule $sodConflictRule): RedirectResponse
    {
        $sodConflictRule->delete();

        return back()->with('success', 'SoD conflict rule removed.');
    }

    public function destroyOrganization(Organization $organization): RedirectResponse
    {
        if (Project::withTrashed()->where('org_id', $organization->id)->exists()) {
            return back()->with('error', 'This organization still has projects and cannot be deleted.');
        }

        $name = $organization->name;

        DB::transaction(function () use ($organization) {
            AuditLog::where('org_id', $organization->id)->delete();
            RoleAssignmentLog::where('org_id', $organization->id)->delete();
            Delegation::where('org_id', $organization->id)->delete();
            OrgRelationship::where('from_org_id', $organization->id)
                           ->orWhere('to_org_id', $organization->id)->delete();
            $organization->userOrgRoles()->delete();
            $organization->delete();
        });

        return redirect()->route('admin.rbac.organizations')
            ->with('success', "Organization \"{$name}\" has been permanently deleted.");
    }

    /**
     * Permanently delete a user and all their RBAC data.
     * Refuses to delete platform admins or the currently authenticated admin.
     */
    public function destroyUser(User $user): RedirectResponse
    {
        if ($user->role === 'admin') {
            return back()->with('error', 'Platform administrators cannot be deleted through this interface.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $soleOrgIds = UserOrgRole::where('user_id', $user->id)->pluck('org_id')->unique()
            ->reject(fn ($orgId) => UserOrgRole::where('org_id', $orgId)
                ->where('user_id', '!=', $user->id)
                ->where('is_active', true)
                ->exists());

        if (Project::withTrashed()->whereIn('org_id', $soleOrgIds)->exists()) {
            return back()->with('error', 'This user solely owns an organization that still has projects and cannot be deleted.');
        }

        if (Project::withTrashed()->where('created_by', $user->id)->exists()) {
            return back()->with('error', 'This user created projects and cannot be deleted. Reassign or delete those projects first.');
        }

        if (Quote::withTrashed()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'This user owns quotes and cannot be deleted. Reassign or delete those quotes first.');
        }

        $name = $user->name;

        DB::transaction(function () use ($user) {
            ProjectMember::where('user_id', $user->id)->where('is_active', true)->whereNotNull('project_id')->get()
                ->each(fn ($m) => ProjectMemberLog::record((int) $m->org_id, (int) $m->project_id, (int) $user->id, 'removed', (int) auth()->id()));
            ProjectMember::where('user_id', $user->id)->delete();

            // Remove all RBAC data for the user
            $userOrgIds = UserOrgRole::where('user_id', $user->id)->pluck('org_id')->unique();

            AuditLog::where('user_id', $user->id)->delete();
            RoleAssignmentLog::where('target_user_id', $user->id)
                             ->orWhere('performed_by', $user->id)->delete();
            Delegation::where('from_user_id', $user->id)
                      ->orWhere('to_user_id', $user->id)
                      ->orWhere('granted_by', $user->id)->delete();
            UserOrgRole::where('user_id', $user->id)->delete();

            // Delete any orgs this user solely owned (no other active members)
            foreach ($userOrgIds as $orgId) {
                $otherMembers = UserOrgRole::where('org_id', $orgId)
                    ->where('user_id', '!=', $user->id)
                    ->where('is_active', true)
                    ->exists();

                if (! $otherMembers) {
                    AuditLog::where('org_id', $orgId)->delete();
                    RoleAssignmentLog::where('org_id', $orgId)->delete();
                    Delegation::where('org_id', $orgId)->delete();
                    OrgRelationship::where('from_org_id', $orgId)->orWhere('to_org_id', $orgId)->delete();
                    Organization::where('id', $orgId)->delete();
                }
            }

            $user->delete();
        });

        return redirect()->route('admin.rbac.users')
            ->with('success', "User \"{$name}\" and their associated data have been permanently deleted.");
    }
}

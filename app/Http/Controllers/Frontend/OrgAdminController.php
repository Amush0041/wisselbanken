<?php

namespace App\Http\Controllers\Frontend;

use App\Exceptions\Rbac\SodConflictException;
use App\Http\Controllers\Controller;
use App\Mail\OrgInviteMail;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Rbac\ApiToken;
use App\Models\Rbac\Delegation;
use App\Models\Rbac\OrgInvite;
use App\Models\Rbac\OrgRelationship;
use App\Models\Rbac\ProjectMember;
use App\Models\Rbac\AuditLog as RbacAuditLog;
use App\Models\Rbac\RoleAssignmentLog;
use App\Models\Rbac\Organization;
use App\Models\Rbac\PermissionGroup;
use App\Models\Rbac\Role;
use App\Models\Rbac\RolePermission;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Organization admin screen (plan §6.3). Lets an organization owner/admin manage who holds
 * which roles inside their current organization, including the peel-off prompt that offers
 * to remove a role from the owner's own account when it is handed to a new hire.
 *
 * Authorization for the actions here is also declared in config/route_permission_map.php so
 * the RBAC middleware audits/enforces them centrally.
 */
class OrgAdminController extends Controller
{
    public function __construct(private readonly RoleAssignmentService $assignments)
    {
    }

    public function index(): mixed
    {
        $org = $this->currentOrg();

        if (! $org) {
            return redirect()->route('user.dashboard')
                ->with('error', 'You are not a member of any organization yet.');
        }

        // Users in this org with their active roles.
        $members = User::whereIn('id', function ($q) use ($org) {
            $q->select('user_id')->from('user_org_roles')
                ->where('org_id', $org->id)->where('is_active', true);
        })->get()->map(function (User $user) use ($org) {
            $user->setAttribute('org_roles', UserOrgRole::with('role')
                ->where('user_id', $user->id)
                ->where('org_id', $org->id)
                ->where('is_active', true)
                ->get());

            return $user;
        });

        // Release 1 roles available to assign (Phase 1 + Phase 2).
        $roles = Role::whereIn('phase', ['P1', 'P2'])->orderBy('category')->orderBy('name')->get();

        return view('user.org-admin.index', compact('org', 'members', 'roles'));
    }

    /**
     * Assign a role to a user in the current org. Returns peel_off info so the UI can prompt.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        try {
            $result = $this->assignments->assign(
                Auth::id(),
                (int) $data['user_id'],
                $org->id,
                (int) $data['role_id'],
            );
        } catch (SodConflictException $e) {
            return response()->json([
                'success' => false,
                'sod_conflict' => true,
                'message' => 'SoD conflict: this user already holds ' . implode(', ', $e->conflictingRoles) . '. These roles cannot coexist in the same organization.',
            ], 422);
        }

        $role = Role::find($data['role_id']);

        return response()->json([
            'success' => true,
            'message' => "Assigned {$role->name}.",
            // When true, the assigner holds this role too — the UI should ask whether to
            // remove it from the assigner's own account (the peel-off prompt).
            'peel_off_candidate' => $result['peel_off_candidate'],
            'role_id' => (int) $data['role_id'],
            'role_name' => $role->name,
        ]);
    }

    /**
     * Peel a role off the current user's own account (owner confirms the prompt).
     */
    public function peelOff(Request $request): JsonResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');

        $data = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        $removed = $this->assignments->peelOff(Auth::id(), $org->id, (int) $data['role_id']);

        return response()->json([
            'success' => $removed,
            'message' => $removed ? 'Role removed from your account.' : 'You no longer hold that role.',
        ]);
    }

    /**
     * Remove a role from a member (soft-removal of a single assignment).
     */
    public function removeRole(UserOrgRole $userOrgRole): RedirectResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org || $userOrgRole->org_id !== $org->id, 403, 'Not in your organization.');

        $this->assignments->deactivate($userOrgRole->id, Auth::id());

        return back()->with('success', 'Role removed.');
    }

    /**
     * Roles & Permissions matrix — shows all P1/P2 roles with their permission levels per module.
     * Org owners (user_management:F) can edit levels inline via AJAX.
     */
    public function rolesList(): mixed
    {
        $org = $this->currentOrg();
        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        $mfrOrgTypes = config('rbac.onboarding.manufacturer_org_types', ['manufacturer']);
        $rolesQuery  = Role::whereIn('phase', ['P1', 'P2'])
            ->where('category', '!=', 'Platform');   // Platform roles are internal; org owners never assign them
        if (! in_array($org->org_type, $mfrOrgTypes, true)) {
            $rolesQuery->where('category', '!=', 'Manufacturer/Seller');
        }
        $roles = $rolesQuery->orderBy('category')->orderBy('name')->get()
            ->sortBy(fn ($r) => ($r->category === 'Cross-Functional' ? 'zzz_' : '') . $r->category . '_' . $r->name)
            ->values();

        $assignedRoleSlugs = UserOrgRole::where('org_id', $org->id)->where('is_active', true)
            ->with('role')->get()->pluck('role.slug')->unique()->values();

        // Org-admin sees only Core Operations — platform-admin groups (pricing,
        // fulfillment, manufacturer controls, catalog, audit, system) are
        // configured by platform admins on the admin RBAC page, not by org owners.
        $groupSlugs = [
            'procurement',
            'approval_authority',
            'estimate_management',
            'project_management',
            'quote_rfq_management',
            'product_management',
            'user_management',
            'delegation_and_impersonation',
            'organization_management',
        ];
        $permissionGroups = PermissionGroup::whereIn('slug', $groupSlugs)->get()
            ->sortBy(fn ($g) => array_search($g->slug, $groupSlugs, true))
            ->values();

        // Build matrix: role_id → group_id → access_level
        $roleIds   = $roles->pluck('id');
        $permMatrix = RolePermission::whereIn('role_id', $roleIds)
            ->get()
            ->groupBy('role_id')
            ->map(fn ($perms) => $perms->keyBy('permission_group_id')
                ->map(fn ($p) => $p->access_level));

        return view('user.org-admin.roles', compact(
            'org', 'roles', 'assignedRoleSlugs', 'permissionGroups', 'permMatrix'
        ));
    }

    /**
     * Update (or remove) a single role → permission-group level. Requires user_management:F.
     * Also gated in route_permission_map.php so RBAC middleware enforces it centrally.
     */
    public function updateRolePermission(Request $request): JsonResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');

        $data = $request->validate([
            'role_id'             => ['required', 'integer', 'exists:roles,id'],
            'permission_group_id' => ['required', 'integer', 'exists:permission_groups,id'],
            'access_level'        => ['nullable', 'string', Rule::in(['F', 'A', 'O', 'S', 'R'])],
        ]);

        if (empty($data['access_level'])) {
            RolePermission::where('role_id', $data['role_id'])
                ->where('permission_group_id', $data['permission_group_id'])
                ->delete();
        } else {
            RolePermission::updateOrCreate(
                [
                    'role_id'             => $data['role_id'],
                    'permission_group_id' => $data['permission_group_id'],
                ],
                ['access_level' => $data['access_level']]
            );
        }

        $role  = Role::find($data['role_id']);
        $group = PermissionGroup::find($data['permission_group_id']);

        return response()->json([
            'success' => true,
            'message' => $role->name . ' → ' . $group->name . ' updated to ' . ($data['access_level'] ?: 'None') . '.',
        ]);
    }

    /**
     * Create a new role (org owners only). Roles are global — applies across all orgs.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'category'    => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'phase'       => ['required', Rule::in(['P1', 'P2'])],
        ]);

        $slug = \Illuminate\Support\Str::slug($data['name']);
        $base = $slug;
        $n    = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        $role = Role::create([
            'name'        => $data['name'],
            'slug'        => $slug,
            'category'    => $data['category'],
            'description' => $data['description'] ?? null,
            'phase'       => $data['phase'],
        ]);

        return response()->json(['success' => true, 'role_id' => $role->id, 'message' => 'Role created.']);
    }

    /** Org-Admin overview dashboard. */
    public function overview(): mixed
    {
        $org = $this->currentOrg();
        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        $memberIds = UserOrgRole::where('org_id', $org->id)->where('is_active', true)->pluck('user_id')->unique();

        $stats = [
            'members'     => $memberIds->count(),
            'active_roles'=> UserOrgRole::where('org_id', $org->id)->where('is_active', true)->select('role_id')->distinct()->count(),
            'delegations' => Delegation::where('org_id', $org->id)->where('is_active', true)->where('expires_at', '>', now())->count(),
            'api_tokens'  => ApiToken::whereIn('user_id', $memberIds)->where('is_active', true)->count(),
        ];

        // Role coverage: all P1+P2 roles with assignment status in this org.
        $assignedRoleIds = UserOrgRole::where('org_id', $org->id)->where('is_active', true)
            ->pluck('role_id')->unique();
        $allRoles = Role::whereIn('phase', ['P1', 'P2'])->orderBy('name')->get()->map(function ($role) use ($assignedRoleIds, $org) {
            $assignees = UserOrgRole::with('user')
                ->where('org_id', $org->id)
                ->where('role_id', $role->id)
                ->where('is_active', true)
                ->get();
            $role->setAttribute('filled', $assignedRoleIds->contains($role->id));
            $role->setAttribute('assignees', $assignees);
            return $role;
        })->take(8);

        // Recent activity: last 8 role assignment changes in this org.
        $recentActivity = UserOrgRole::with(['user', 'role', 'assignedBy'])
            ->where('org_id', $org->id)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $mfrOrgTypes  = config('rbac.onboarding.manufacturer_org_types', ['manufacturer']);
        $inviteRolesQ = Role::whereIn('phase', ['P1', 'P2']);
        if (! in_array($org->org_type, $mfrOrgTypes, true)) {
            $inviteRolesQ->where('category', '!=', 'Manufacturer/Seller');
        }
        $roles = $inviteRolesQ->orderBy('category')->orderBy('name')->get();

        return view('user.org-admin.overview', compact('org', 'stats', 'allRoles', 'recentActivity', 'roles'));
    }

    /** My Roles — current user's roles in this org and cross-org. */
    public function myRoles(): mixed
    {
        $user = Auth::user();
        $currentOrg = $this->currentOrg();
        if (! $currentOrg) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        // Group assignments by org.
        $orgGroups = UserOrgRole::with(['organization', 'role', 'assignedBy'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->groupBy('org_id');

        // Active delegation received by this user.
        $activeDelegation = Delegation::with('fromUser')
            ->where('to_user_id', $user->id)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->first();

        return view('user.org-admin.my-roles', compact('user', 'currentOrg', 'orgGroups', 'activeDelegation'));
    }

    /** Role Assignment History (audit log scoped to this org). */
    public function auditLog(Request $request): mixed
    {
        $org = $this->currentOrg();
        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        $query = RoleAssignmentLog::with(['targetUser', 'role', 'performedBy'])
            ->where('org_id', $org->id);

        if ($memberId = $request->query('member_id')) {
            $query->where('target_user_id', $memberId);
        }
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $history = $query->latest('created_at')->paginate(20)->withQueryString();
        $members = User::whereIn('id',
            RoleAssignmentLog::where('org_id', $org->id)->pluck('target_user_id')->unique()
        )->orderBy('name')->get(['id', 'name']);

        $enforcementLog = RbacAuditLog::with('user')
            ->where('org_id', $org->id)
            ->latest('created_at')
            ->paginate(25, ['*'], 'epage')
            ->withQueryString();

        return view('user.org-admin.audit-log', compact('org', 'history', 'members', 'enforcementLog'));
    }

    public function generateInvite(Request $request): JsonResponse
    {
        $org = $this->currentOrg();
        if (! $org) {
            return response()->json(['error' => 'No active organization.'], 403);
        }

        $data = $request->validate([
            'email'      => ['required', 'email', 'max:255'],
            'name'       => ['required', 'string', 'max:255'],
            'role_id'    => ['required', 'integer', 'exists:roles,id'],
            'confirmed'  => ['sometimes', 'boolean'],
        ]);

        // Existing account path — require explicit confirmation before assigning
        $existing = User::where('email', $data['email'])->first();
        if ($existing) {
            $role = Role::find($data['role_id']);

            if (empty($data['confirmed'])) {
                // First hit: tell the front-end who we found so it can ask the user to confirm
                return response()->json([
                    'type'      => 'confirm_existing',
                    'user_name' => $existing->name,
                    'role_name' => $role?->name,
                ]);
            }

            // Second hit (confirmed: true): actually assign
            try {
                $this->assignments->assign(
                    Auth::id(),
                    $existing->id,
                    $org->id,
                    (int) $data['role_id'],
                );
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return response()->json([
                'type'    => 'existing',
                'message' => $existing->name . ' has been added to your org as ' . $role?->name . '.',
            ]);
        }

        // Revoke any prior unused invite for the same email + org
        OrgInvite::where('email', $data['email'])->where('org_id', $org->id)->whereNull('used_at')->delete();

        $invite = OrgInvite::create([
            'token'      => Str::random(48),
            'email'      => $data['email'],
            'name'       => $data['name'],
            'org_id'     => $org->id,
            'role_id'    => (int) $data['role_id'],
            'invited_by' => Auth::id(),
            'expires_at' => now()->addDays(7),
        ]);

        $invite->load(['organization', 'role', 'invitedBy']);

        $emailSent = false;
        if ($request->boolean('send_email', true)) {
            try {
                Mail::to($invite->email, $invite->name)->send(new OrgInviteMail($invite));
                $emailSent = true;
            } catch (\Exception) {
                // Mail failure is non-fatal — caller still gets the link to share manually.
            }
        }

        return response()->json([
            'type'       => 'invite',
            'email_sent' => $emailSent,
            'link'       => url('/register/invite/' . $invite->token),
        ]);
    }

    // =========================================================================
    // Org Connections (plan §4.5)
    // =========================================================================

    public function connections(): mixed
    {
        $org = $this->currentOrg();
        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        $outgoing = OrgRelationship::with('toOrganization')
            ->where('from_org_id', $org->id)
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $incoming = OrgRelationship::with('fromOrganization')
            ->where('to_org_id', $org->id)
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // All other orgs to populate the partner picker (exclude self).
        $allOrgs = Organization::where('id', '!=', $org->id)->orderBy('name')->get(['id', 'name', 'org_type']);

        $relationshipTypes = [
            'buyer_seller'             => 'Buyer → Seller (Trading Partnership)',
            'gc_subcontractor'         => 'General Contractor → Subcontractor',
            'distributor_manufacturer' => 'Distributor → Manufacturer',
            'manufacturer_rep_agency'  => 'Manufacturer → Rep Agency',
            'gpo_member'               => 'GPO → Member Organization',
            'delegation'               => 'Delegation',
        ];

        $priorities = ['Critical', 'High', 'Medium'];

        return view('user.org-admin.connections', compact(
            'org', 'outgoing', 'incoming', 'allOrgs', 'relationshipTypes', 'priorities'
        ));
    }

    public function storeConnection(Request $request): RedirectResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');

        $data = $request->validate([
            'to_org_id'         => ['required', 'integer', 'exists:organizations,id', Rule::notIn([$org->id])],
            'relationship_type' => ['required', 'string', 'in:buyer_seller,gc_subcontractor,distributor_manufacturer,manufacturer_rep_agency,gpo_member,delegation'],
            'priority'          => ['nullable', 'string', 'in:Critical,High,Medium'],
        ]);

        $exists = OrgRelationship::where('from_org_id', $org->id)
            ->where('to_org_id', $data['to_org_id'])
            ->where('relationship_type', $data['relationship_type'])
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return back()->with('error', 'This relationship already exists and is active.');
        }

        OrgRelationship::create([
            'from_org_id'       => $org->id,
            'to_org_id'         => (int) $data['to_org_id'],
            'relationship_type' => $data['relationship_type'],
            'priority'          => $data['priority'] ?? null,
            'is_active'         => true,
        ]);

        return back()->with('success', 'Connection created.');
    }

    public function destroyConnection(OrgRelationship $orgRelationship): RedirectResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org || $orgRelationship->from_org_id !== $org->id, 403, 'Not your connection.');

        $orgRelationship->update(['is_active' => false]);

        return back()->with('success', 'Connection deactivated.');
    }

    // =========================================================================
    // Project Members (plan §3.5)
    // =========================================================================

    public function projects(): mixed
    {
        $org = $this->currentOrg();
        if (! $org) {
            return redirect()->route('user.dashboard')->with('error', 'No organization context.');
        }

        // All org member user ids.
        $memberIds = UserOrgRole::where('org_id', $org->id)->where('is_active', true)->pluck('user_id')->unique();

        // Quotes owned by any org member, or belonging to one of this org's live projects.
        $quotes = Quote::where(function ($q) use ($memberIds, $org) {
                $q->whereIn('user_id', $memberIds)
                    ->orWhereIn('project_id', Project::where('org_id', $org->id)->select('projects.id'));
            })
            ->with(['user'])
            ->withCount(['items'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Quote $quote) use ($org) {
                $members = ProjectMember::with('user')
                    ->where('org_id', $org->id)
                    ->where('is_active', true);

                if ($quote->project_id !== null) {
                    $members->where('project_id', $quote->project_id);
                } else {
                    $members->where('quote_id', $quote->id);
                }

                $quote->setAttribute('project_members_list', $members->get());

                return $quote;
            });

        // Org members for the "add member" dropdown.
        $members = User::whereIn('id', $memberIds)->orderBy('name')->get(['id', 'name', 'email']);

        return view('user.org-admin.projects', compact('org', 'quotes', 'members'));
    }

    public function addProjectMember(Request $request): RedirectResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org, 403, 'No organization context.');

        $data = $request->validate([
            'quote_id' => ['required', 'integer', 'exists:quotes,id'],
            'user_id'  => ['required', 'integer', 'exists:users,id'],
        ]);

        // Confirm the quote belongs to an org member.
        $memberIds = UserOrgRole::where('org_id', $org->id)->where('is_active', true)->pluck('user_id');
        $quoteOwnedByMember = Quote::where('id', $data['quote_id'])->whereIn('user_id', $memberIds)->exists();
        abort_if(! $quoteOwnedByMember, 403, 'Quote does not belong to your organization.');

        // Confirm the target user is an org member.
        abort_if(! $memberIds->contains($data['user_id']), 403, 'User is not a member of your organization.');

        $exists = ProjectMember::where('quote_id', $data['quote_id'])
            ->where('user_id', $data['user_id'])
            ->where('org_id', $org->id)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            ProjectMember::create([
                'quote_id'   => (int) $data['quote_id'],
                'user_id'    => (int) $data['user_id'],
                'org_id'     => $org->id,
                'granted_by' => Auth::id(),
                'granted_at' => now(),
                'is_active'  => true,
            ]);
        }

        return back()->with('success', 'Member added to project.');
    }

    public function removeProjectMember(ProjectMember $projectMember): RedirectResponse
    {
        $org = $this->currentOrg();
        abort_if(! $org || $projectMember->org_id !== $org->id, 403, 'Not in your organization.');

        $projectMember->update(['is_active' => false]);

        return back()->with('success', 'Member removed from project.');
    }

    private function currentOrg(): ?Organization
    {
        $sessionKey = config('rbac.current_org_session_key');
        $orgId = session($sessionKey);

        $query = UserOrgRole::where('user_id', Auth::id())->where('is_active', true);

        if ($orgId && (clone $query)->where('org_id', $orgId)->exists()) {
            return Organization::find($orgId);
        }

        $firstOrgId = $query->value('org_id');

        return $firstOrgId ? Organization::find($firstOrgId) : null;
    }
}

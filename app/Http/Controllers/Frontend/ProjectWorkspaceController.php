<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PlanCrosswalk;
use App\Models\Quote;
use App\Models\Rbac\ProjectMember;
use App\Models\Rbac\UserOrgRole;
use App\Models\User;
use App\Services\Rbac\OrgRelationshipService;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Project Workspace (document §6.5).
 *
 * A per-project surface that filters all data (estimate items, members, crosswalk)
 * to the selected project (quote_id). Every action is gated by
 * checkPermission(userId, orgId, group, level, projectId) so only project members
 * with the correct role can access the workspace.
 */
class ProjectWorkspaceController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly OrgRelationshipService $orgRelationships,
    ) {}

    public function show(Quote $quote)
    {
        $orgId = $this->currentOrgId();

        // Gate: user must have estimate_management:R AND be an active project member.
        $userId = Auth::id();

        $allowed = $quote->project_id !== null && $this->permissions->checkPermission(
            userId: $userId,
            orgId: $orgId,
            permissionGroup: 'estimate_management',
            requiredLevel: 'R',
            projectId: (int) $quote->project_id,
        );

        if (! $allowed) {
            return redirect()->route('org-admin.projects.index')
                ->with('error', 'You are not a member of this project or do not have the required permission.');
        }

        // §4.5 GC→Subcontractor: when the accessing org differs from the project creator's org,
        // verify an active gc_subcontractor relationship exists between the two orgs.
        $creatorOrgId = DB::table('user_org_roles')
            ->where('user_id', $quote->user_id)
            ->where('is_active', true)
            ->value('org_id');

        if ($creatorOrgId && (int) $creatorOrgId !== $orgId) {
            abort_unless(
                $this->orgRelationships->hasActiveEither($creatorOrgId, $orgId, 'gc_subcontractor'),
                403,
                'No active GC-Subcontractor relationship with the project owner organization.'
            );
        }

        // Reload quote with items.
        $quote->load(['items', 'user']);

        // Project members for this quote.
        $projectMembers = ProjectMember::with('user')
            ->where('quote_id', $quote->id)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->get();

        // Crosswalk entries scoped to this project.
        $crosswalkEntries = PlanCrosswalk::with(['product', 'creator'])
            ->where('org_id', $orgId)
            ->where('quote_id', $quote->id)
            ->orderBy('plan_line_code')
            ->get();

        // All org members (for adding to project from workspace, only if user can manage).
        $canManageMembers = $this->permissions->checkPermission(
            userId: $userId,
            orgId: $orgId,
            permissionGroup: 'project_management',
            requiredLevel: 'F',
        );

        $orgMembers = $canManageMembers
            ? User::whereIn('id', function ($q) use ($orgId) {
                $q->select('user_id')->from('user_org_roles')
                    ->where('org_id', $orgId)->where('is_active', true);
            })->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        // Pass the project ID as a JS var so blade directives work project-scoped.
        $projectId = $quote->id;

        return view('user.project-workspace.show', compact(
            'quote', 'projectMembers', 'crosswalkEntries',
            'orgMembers', 'canManageMembers', 'projectId', 'orgId',
        ));
    }

    private function currentOrgId(): int
    {
        return CurrentOrg::id((int) Auth::id()) ?? 0;
    }
}

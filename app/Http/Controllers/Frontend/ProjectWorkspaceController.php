<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PlanCrosswalk;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Rbac\ProjectMember;
use App\Models\User;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Support\Facades\Auth;

/**
 * Project page (document §6.5). Everything shown is filtered to one projects.id; access
 * needs an active project_members row plus the permission group level for each panel.
 */
class ProjectWorkspaceController extends Controller
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function show(Project $project)
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        abort_if($orgId === null, 404);

        $project = Project::visibleTo($userId, $orgId)->whereKey($project->id)->firstOrFail();

        $can = fn (string $group, string $level) => $this->permissions->checkPermission($userId, $orgId, $group, $level, $project->id);

        $canReadEstimates = $can('estimate_management', 'R');
        $canCreateEstimates = $can('estimate_management', 'S');
        $canManageCrosswalk = $can('estimate_management', 'F');
        $canManageMembers = $can('project_management', 'F');
        $canUpdateProject = $can('project_management', 'O');
        $canDeleteProject = $can('project_management', 'F');

        $quotes = $canReadEstimates
            ? Quote::where('project_id', $project->id)->with(['user'])->withCount('items')->orderBy('created_at', 'desc')->get()
            : collect();

        $projectMembers = ProjectMember::with('user')
            ->where('project_id', $project->id)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->get();

        $crosswalkEntries = $canReadEstimates
            ? PlanCrosswalk::with(['product', 'creator'])
                ->where('org_id', $orgId)
                ->where('project_id', $project->id)
                ->orderBy('plan_line_code')
                ->get()
            : collect();

        $orgMembers = $canManageMembers
            ? User::whereIn('id', function ($q) use ($orgId) {
                $q->select('user_id')->from('user_org_roles')
                    ->where('org_id', $orgId)->where('is_active', true);
            })->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        $customers = $canCreateEstimates
            ? Customer::where('user_id', $userId)->where('is_active', true)
                ->get()
                ->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'name' => $c->company_name ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')),
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
            : collect();

        return view('user.project-workspace.show', compact(
            'project', 'quotes', 'customers', 'projectMembers', 'crosswalkEntries', 'orgMembers',
            'canReadEstimates', 'canCreateEstimates', 'canManageCrosswalk', 'canManageMembers',
            'canUpdateProject', 'canDeleteProject', 'orgId',
        ));
    }

    public function legacyRedirect(Quote $quote)
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        abort_if($orgId === null, 404);

        $visible = Project::visibleTo($userId, $orgId)->whereKey($quote->project_id)->exists();
        abort_unless($visible, 404);

        return redirect()->route('projects.show', $quote->project_id, 301);
    }
}

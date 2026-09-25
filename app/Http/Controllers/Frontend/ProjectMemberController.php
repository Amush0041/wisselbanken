<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rbac\ProjectMember;
use App\Models\Rbac\UserOrgRole;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectMemberController extends Controller
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function store(Request $request, Project $project): mixed
    {
        $project = $this->manageableProject($project);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $isOrgMember = UserOrgRole::where('org_id', $project->org_id)
            ->where('user_id', $data['user_id'])
            ->where('is_active', true)
            ->exists();
        abort_unless($isOrgMember, 403, 'User is not a member of the project organization.');

        $member = ProjectMember::enrol($project, (int) $data['user_id'], (int) $project->org_id, (int) Auth::id());

        $message = match (true) {
            $member->wasRecentlyCreated => 'Member added to project.',
            $member->wasChanged('is_active') => 'Member re-activated on project.',
            default => 'User is already a member of this project.',
        };

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'member_id' => $member->id]);
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, Project $project, ProjectMember $projectMember): mixed
    {
        $project = $this->manageableProject($project);
        abort_unless((int) $projectMember->project_id === (int) $project->id, 404);

        $projectMember->deactivate((int) Auth::id());

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Member removed from project.');
    }

    private function manageableProject(Project $project): Project
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        abort_if($orgId === null, 404);

        $project = Project::visibleTo($userId, $orgId)->whereKey($project->id)->firstOrFail();

        abort_unless(
            $this->permissions->checkPermission($userId, $orgId, 'project_management', 'F', $project->id),
            403,
            'You need full project management access to change project members.'
        );

        return $project;
    }
}

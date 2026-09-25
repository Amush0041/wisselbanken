<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rbac\ProjectMember;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function index(): mixed
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);

        $projects = $orgId === null
            ? collect()
            : Project::visibleTo($userId, $orgId)
                ->withCount(['quotes', 'members' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('created_at', 'desc')
                ->get();

        $statuses = Project::STATUSES;

        return view('user.projects.index', compact('projects', 'statuses', 'orgId'));
    }

    public function list(): JsonResponse
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);

        $projects = $orgId === null
            ? []
            : Project::visibleTo($userId, $orgId)->orderBy('name')->get(['projects.id', 'projects.name', 'projects.status']);

        return response()->json(['projects' => $projects]);
    }

    public function store(Request $request): mixed
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        abort_if($orgId === null, 403, 'No organization context.');
        abort_unless(
            $this->permissions->checkPermission($userId, $orgId, 'project_management', 'S'),
            403,
            'You do not have permission to create projects.'
        );

        $data = $this->validated($request);

        $project = DB::transaction(function () use ($data, $orgId, $userId) {
            $project = Project::create($data + ['org_id' => $orgId, 'created_by' => $userId]);
            ProjectMember::enrol($project, $userId, $orgId, $userId);

            return $project;
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'project_id' => $project->id], 201);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    public function update(Request $request, Project $project): mixed
    {
        $project = $this->visibleProject($project, 'O');

        $project->update($this->validated($request));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'project_id' => $project->id]);
        }

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): mixed
    {
        $project = $this->visibleProject($project, 'F');

        DB::transaction(function () use ($project) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            abort_if($locked->quotes()->exists(), 422, 'Delete or move the estimates in this project first.');

            $locked->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(Project::STATUSES)],
            'address' => ['nullable', 'string', 'max:65535'],
            'bid_due_at' => ['nullable', 'date'],
        ]);
    }

    private function visibleProject(Project $project, string $level): Project
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        abort_if($orgId === null, 404);

        $project = Project::visibleTo($userId, $orgId)->whereKey($project->id)->firstOrFail();

        abort_unless(
            $this->permissions->checkPermission($userId, $orgId, 'project_management', $level, $project->id),
            403,
            'You do not have permission to change this project.'
        );

        return $project;
    }
}

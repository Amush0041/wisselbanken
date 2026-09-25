<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PlanCrosswalk;
use App\Models\Product;
use App\Models\Project;
use App\Models\Rbac\UserOrgRole;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Plan Crosswalk management (document §4.6).
 *
 * Maps buyer plan line item codes → WisselBanken SKUs → manufacturer part numbers,
 * scoped to a project (project_id). Access levels per the document:
 *   Estimator / Project Manager       → F (full CRUD)
 *   Procurement Manager / Requisitioner → R (read only)
 *   Executive Approver                 → R (read only)
 *
 * Permission enforced via route_permission_map (estimate_management) with project scoping.
 */
class PlanCrosswalkController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);

        $selectedProjectId = $request->integer('project_id') ?: null;

        if ($orgId === null) {
            $projects = collect();
            $rows = collect();
            $selectedProjectId = null;
        } else {
            $members = app(PermissionService::class)->checkPermission($userId, $orgId, 'estimate_management', 'R');

            $projects = Project::visibleTo($userId, $orgId)->orderBy('projects.name')->get(['projects.id', 'projects.name']);

            if ($selectedProjectId !== null && ! Project::visibleTo($userId, $orgId)->whereKey($selectedProjectId)->exists()) {
                $selectedProjectId = null;
            }

            $rows = PlanCrosswalk::with(['product', 'project', 'creator'])
                ->where('org_id', $orgId)
                ->when(
                    $members,
                    fn ($q) => $q->whereIn('project_id', Project::visibleTo($userId, $orgId)->select('projects.id')),
                    fn ($q) => $q->whereRaw('0 = 1'),
                )
                ->when($selectedProjectId, fn ($q) => $q->where('project_id', $selectedProjectId))
                ->orderBy('project_id')
                ->orderBy('plan_line_code')
                ->get();
        }

        return view('user.plan-crosswalk.index', compact('rows', 'projects', 'selectedProjectId'));
    }

    public function store(Request $request, Project $project)
    {
        $project = $this->writableProject($project->id);

        $data = $request->validate([
            'plan_line_code'           => [
                'required', 'string', 'max:100',
                Rule::unique('plan_crosswalk', 'plan_line_code')->where('project_id', $project->id),
            ],
            'product_id'               => ['nullable', 'integer', 'exists:products,id'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:100'],
            'description'              => ['nullable', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string'],
        ], [
            'plan_line_code.unique' => 'This line code already exists in this project.',
        ]);

        PlanCrosswalk::create($data + [
            'org_id'     => $project->org_id,
            'project_id' => $project->id,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Crosswalk entry added.');
    }

    public function update(Request $request, PlanCrosswalk $planCrosswalk)
    {
        $this->writableRow($planCrosswalk);

        $data = $request->validate([
            'plan_line_code'           => [
                'required', 'string', 'max:100',
                Rule::unique('plan_crosswalk', 'plan_line_code')
                    ->where('project_id', $planCrosswalk->project_id)
                    ->ignore($planCrosswalk->id),
            ],
            'product_id'               => ['nullable', 'integer', 'exists:products,id'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:100'],
            'description'              => ['nullable', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string'],
        ], [
            'plan_line_code.unique' => 'This line code already exists in this project.',
        ]);

        $planCrosswalk->update(array_merge($data, ['updated_by' => Auth::id()]));

        return back()->with('success', 'Crosswalk entry updated.');
    }

    public function destroy(PlanCrosswalk $planCrosswalk)
    {
        $this->writableRow($planCrosswalk);

        $planCrosswalk->delete();

        return back()->with('success', 'Crosswalk entry removed.');
    }

    private function writableProject(int $projectId): Project
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        abort_if($orgId === null, 404);

        $project = Project::visibleTo($userId, $orgId)->whereKey($projectId)->firstOrFail();

        abort_unless(
            app(PermissionService::class)->checkPermission($userId, $orgId, 'estimate_management', 'F', $project->id),
            403,
            'You need full estimate access to change the crosswalk.'
        );

        return $project;
    }

    private function writableRow(PlanCrosswalk $row): void
    {
        $project = $this->writableProject((int) $row->project_id);

        abort_unless((int) $row->org_id === (int) $project->org_id, 404);
    }
}

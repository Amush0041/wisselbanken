<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PlanCrosswalk;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Rbac\UserOrgRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Plan Crosswalk management (document §4.6).
 *
 * Maps buyer plan line item codes → WisselBanken SKUs → manufacturer part numbers,
 * scoped to a project (quote_id). Access levels per the document:
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
        $orgId = $this->currentOrgId();

        // Load projects (quotes) this org owns so the user can filter by project.
        $projects = Quote::where('org_id', $orgId)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'created_at']);

        $selectedQuoteId = $request->integer('project_id') ?: null;

        $rows = PlanCrosswalk::with(['product', 'quote', 'creator'])
            ->where('org_id', $orgId)
            ->when($selectedQuoteId, fn ($q) => $q->where('quote_id', $selectedQuoteId))
            ->orderBy('quote_id')
            ->orderBy('plan_line_code')
            ->get();

        return view('user.plan-crosswalk.index', compact('rows', 'projects', 'selectedQuoteId'));
    }

    public function store(Request $request)
    {
        $orgId = $this->currentOrgId();

        $data = $request->validate([
            'quote_id'                 => ['required', 'integer', 'exists:quotes,id'],
            'plan_line_code'           => ['required', 'string', 'max:100'],
            'product_id'               => ['nullable', 'integer', 'exists:products,id'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:100'],
            'description'              => ['nullable', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string'],
        ]);

        // Verify the quote belongs to the current org.
        abort_unless(
            Quote::where('id', $data['quote_id'])->where('org_id', $orgId)->exists(),
            403,
            'Project not found in your organisation.'
        );

        // Prevent duplicate plan_line_code per project.
        $exists = PlanCrosswalk::where('org_id', $orgId)
            ->where('quote_id', $data['quote_id'])
            ->where('plan_line_code', $data['plan_line_code'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', "Line code '{$data['plan_line_code']}' already exists in this project.");
        }

        PlanCrosswalk::create(array_merge($data, [
            'org_id'     => $orgId,
            'created_by' => Auth::id(),
        ]));

        return back()->with('success', 'Crosswalk entry added.');
    }

    public function update(Request $request, PlanCrosswalk $planCrosswalk)
    {
        $orgId = $this->currentOrgId();
        abort_if($planCrosswalk->org_id !== $orgId, 403);

        $data = $request->validate([
            'plan_line_code'           => ['required', 'string', 'max:100'],
            'product_id'               => ['nullable', 'integer', 'exists:products,id'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:100'],
            'description'              => ['nullable', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string'],
        ]);

        $planCrosswalk->update(array_merge($data, ['updated_by' => Auth::id()]));

        return back()->with('success', 'Crosswalk entry updated.');
    }

    public function destroy(PlanCrosswalk $planCrosswalk)
    {
        $orgId = $this->currentOrgId();
        abort_if($planCrosswalk->org_id !== $orgId, 403);

        $planCrosswalk->delete();

        return back()->with('success', 'Crosswalk entry removed.');
    }

    private function currentOrgId(): int
    {
        return (int) session(config('rbac.current_org_session_key'));
    }
}

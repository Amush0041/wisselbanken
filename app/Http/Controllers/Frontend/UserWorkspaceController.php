<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RfqRequest;
use App\Models\Rbac\Organization;
use App\Models\Rbac\UserOrgRole;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * User Workspace (document §6.5).
 *
 * The primary entry screen for an authenticated user: shows all projects
 * the user is a project member of (filtered by project_members table),
 * pending actions scoped to their role, and role-specific quick links.
 * Every data query is org-scoped and gated through checkPermission().
 */
class UserWorkspaceController extends Controller
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function index()
    {
        $userId = Auth::id();
        $orgId  = $this->currentOrgId();

        // My projects: quotes where this user has an active project_members entry.
        $myProjectIds = $orgId
            ? Quote::whereIn('project_id', Project::visibleTo($userId, $orgId)->select('projects.id'))->pluck('id')
            : collect();

        $myProjects = collect();
        if ($orgId && $myProjectIds->isNotEmpty()) {
            $canReadEstimates = $this->permissions->checkPermission($userId, $orgId, 'estimate_management', 'R');
            if ($canReadEstimates) {
                $myProjects = Quote::withCount('items')
                    ->whereIn('id', $myProjectIds)
                    ->orderByDesc('updated_at')
                    ->get();
            }
        }

        // Pending RFQ actions (role-gated).
        $pendingRfqs = collect();
        if ($orgId && $this->permissions->checkPermission($userId, $orgId, 'quote_rfq_management', 'S')) {
            $pendingRfqs = RfqRequest::where('org_id', $orgId)
                ->whereIn('status', ['sent', 'closed'])
                ->where(function ($q) use ($userId, $orgId) {
                    $q->whereNull('project_id')
                        ->orWhereIn('project_id', Project::visibleTo($userId, $orgId)->select('projects.id'));
                })
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        }

        // Incoming seller RFQs (for seller orgs).
        $incomingRfqs = collect();
        if ($orgId && $this->permissions->checkPermission($userId, $orgId, 'quote_rfq_management', 'S')) {
            $incomingRfqs = DB::table('rfq_recipients')
                ->join('rfq_requests', 'rfq_requests.id', '=', 'rfq_recipients.rfq_request_id')
                ->where('rfq_recipients.seller_org_id', $orgId)
                ->where('rfq_recipients.status', 'pending')
                ->orderByDesc('rfq_requests.created_at')
                ->limit(10)
                ->select('rfq_requests.id', 'rfq_requests.title', 'rfq_requests.deadline', 'rfq_recipients.status')
                ->get();
        }

        // Quotes pending approval (approval_authority role check).
        $pendingApprovals = collect();
        if ($orgId && $this->permissions->checkPermission($userId, $orgId, 'approval_authority', 'A')) {
            $pendingApprovals = Quote::where('status', 'pending_approval')
                ->whereIn('id', $myProjectIds->isNotEmpty() ? $myProjectIds : [0])
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get();
        }

        // Current org + active roles for the role-awareness panel.
        $currentOrg = $orgId ? Organization::find($orgId) : null;
        $myRoles = $orgId
            ? UserOrgRole::with('role')
                ->where('user_id', $userId)
                ->where('org_id', $orgId)
                ->where('is_active', true)
                ->get()
                ->pluck('role')
                ->filter()
            : collect();

        $canReadQuotes = $orgId && $this->permissions->checkPermission($userId, $orgId, 'estimate_management', 'R');
        $visibleQuotes = fn () => Quote::visibleTo($userId, $orgId, (bool) $canReadQuotes);
        $quotesCount = (int) $visibleQuotes()->count();
        $quoteStatusCounts = $visibleQuotes()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $recentQuotes = $visibleQuotes()->orderByDesc('quotes.created_at')->orderByDesc('quotes.id')->limit(5)->get();

        $canManageProjects = $orgId && $this->permissions->checkPermission($userId, $orgId, 'project_management', 'F');

        return view('user.dashboard', compact(
            'myProjects',
            'pendingRfqs',
            'incomingRfqs',
            'pendingApprovals',
            'currentOrg',
            'myRoles',
            'canManageProjects',
            'orgId',
            'quotesCount',
            'quoteStatusCounts',
            'recentQuotes',
        ));
    }

    private function currentOrgId(): ?int
    {
        return CurrentOrg::id((int) Auth::id());
    }
}

<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Project;
use App\Models\RfqRecipient;
use App\Models\RfqRequest;
use App\Models\RfqResponse;
use App\Models\Rbac\Organization;
use App\Services\Rbac\ApprovalRoutingService;
use App\Services\Rbac\OrgRelationshipService;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Buyer-side RFQ workflow (doc §6.6).
 *
 * Permission levels enforced via RbacAudit middleware (route_permission_map.php):
 *  index / show    → quote_rfq_management : R
 *  create / store  → quote_rfq_management : S
 *  selectResponse  → quote_rfq_management : O
 *  convertToOrder  → quote_rfq_management : F   +   procurement : S
 */
class RfqController extends Controller
{
    public function __construct(
        private readonly OrgRelationshipService $orgRelationships,
        private readonly PermissionService $permissions,
    ) {}

    // ── Step 1: list buyer's RFQs ────────────────────────────────────────────

    public function index()
    {
        $orgId = $this->currentOrgId();
        $this->requireOrgLevel($orgId, 'quote_rfq_management', 'R');

        $rfqs = RfqRequest::with(['recipients.sellerOrganization', 'responses', 'project'])
            ->where('org_id', $orgId)
            ->where(function ($q) use ($orgId) {
                $q->whereNull('project_id')
                    ->orWhereIn('project_id', Project::visibleTo((int) Auth::id(), $orgId)->select('projects.id'));
            })
            ->latest()
            ->get();

        return view('user.rfq.index', compact('rfqs'));
    }

    // ── Step 1: create form ──────────────────────────────────────────────────

    public function create()
    {
        $orgId = $this->currentOrgId();
        $this->requireOrgLevel($orgId, 'quote_rfq_management', 'S');

        $projects = Project::visibleTo((int) Auth::id(), $orgId)->orderBy('name')->get(['projects.id', 'projects.name']);
        $selectedProjectId = (int) request('project_id');

        // Only show seller orgs this buyer has an active buyer_seller relationship with (§4.5).
        $partnerIds = $this->orgRelationships->partnerIds($orgId, 'buyer_seller');

        $sellerOrgs = Organization::whereIn('id', $partnerIds)->orderBy('name')->get();

        return view('user.rfq.create', compact('sellerOrgs', 'projects', 'selectedProjectId'));
    }

    // ── Step 1: send RFQ ─────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $orgId = $this->currentOrgId();
        $this->requireOrgLevel($orgId, 'quote_rfq_management', 'S');

        $partnerIds = $this->orgRelationships->partnerIds($orgId, 'buyer_seller');
        $userId = (int) Auth::id();

        $request->validate([
            'project_id'       => ['required', 'integer', function ($attr, $value, $fail) use ($userId, $orgId) {
                if (! Project::visibleTo($userId, $orgId)->whereKey($value)->exists()) {
                    $fail('Select a project you have access to.');
                }
            }],
            'title'            => 'required|string|max:255',
            'notes'            => 'nullable|string',
            'deadline'         => 'nullable|date|after_or_equal:today',
            'seller_org_ids'   => 'required|array|min:1',
            'seller_org_ids.*' => ['integer', 'exists:organizations,id', function ($attr, $value, $fail) use ($partnerIds) {
                if (! in_array((int) $value, $partnerIds, true)) {
                    $fail('You can only send RFQs to organisations you have an active trading partnership with. Add them in Connections first.');
                }
            }],
        ]);

        $projectId = (int) $request->project_id;
        abort_unless(
            $this->permissions->checkPermission($userId, $orgId, 'quote_rfq_management', 'S', $projectId),
            403,
            'You do not have permission to send RFQs for this project.'
        );

        DB::transaction(function () use ($request, $orgId, $projectId) {
            $rfq = RfqRequest::create([
                'org_id'     => $orgId,
                'project_id' => $projectId,
                'created_by' => Auth::id(),
                'title'      => $request->title,
                'notes'      => $request->notes,
                'deadline'   => $request->deadline,
                'status'     => 'sent',
            ]);

            foreach ($request->seller_org_ids as $sellerOrgId) {
                RfqRecipient::create([
                    'rfq_request_id' => $rfq->id,
                    'seller_org_id'  => (int) $sellerOrgId,
                    'status'         => 'pending',
                ]);
            }
        });

        return redirect()->route('rfq.index')
            ->with('success', 'RFQ sent to ' . count($request->seller_org_ids) . ' supplier(s).');
    }

    // ── Step 2: view RFQ + seller responses ─────────────────────────────────

    public function show(RfqRequest $rfq)
    {
        $this->authorizeRfq($rfq, 'R');

        $rfq->load(['project', 'recipients.sellerOrganization', 'responses.sellerOrganization', 'responses.creator']);

        return view('user.rfq.show', compact('rfq'));
    }

    // ── Step 2: select a seller's response ──────────────────────────────────

    public function selectResponse(Request $request, RfqRequest $rfq, RfqResponse $response)
    {
        $this->authorizeRfq($rfq, 'O');

        abort_if($response->rfq_request_id !== $rfq->id, 403);

        DB::transaction(function () use ($rfq, $response) {
            $locked = RfqRequest::whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'converted', 422, 'This RFQ has already been converted to an order.');

            // Mark all other responses as rejected.
            RfqResponse::where('rfq_request_id', $rfq->id)
                ->where('id', '!=', $response->id)
                ->update(['status' => 'rejected']);

            $response->update(['status' => 'selected']);
            $rfq->update(['status' => 'closed']);
        });

        return redirect()->route('rfq.show', $rfq)
            ->with('success', 'Response selected. You can now convert this RFQ to a purchase order.');
    }

    // ── Step 3: convert selected response to an order ───────────────────────

    public function convertToOrder(RfqRequest $rfq)
    {
        $orgId = $this->authorizeRfq($rfq, 'F');

        abort_unless(
            $this->permissions->checkPermission((int) Auth::id(), $orgId, 'procurement', 'S', $rfq->project_id ? (int) $rfq->project_id : null),
            403,
            'You do not have permission to convert RFQs to purchase orders.'
        );

        $order = DB::transaction(function () use ($rfq) {
            $locked = RfqRequest::whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            abort_if($locked->status === 'converted', 422, 'This RFQ has already been converted to an order.');
            abort_if($locked->status !== 'closed', 422, 'No response has been selected for this RFQ.');

            $selectedResponse = $locked->responses()->where('status', 'selected')->first();
            abort_if($selectedResponse === null, 422, 'No response has been selected for this RFQ.');

            $routing = app(ApprovalRoutingService::class)->route((int) $locked->org_id, Auth::id());
            $initialStatus = $routing['auto_approve'] ? 'pending' : 'pending_approval';

            $order = Order::create([
                'user_id'        => Auth::id(),
                'org_id'         => $locked->org_id,
                'project_title'  => $locked->title,
                'name'           => $locked->title,
                'email'          => Auth::user()->email,
                'phone'          => '',
                'address1'       => '',
                'city'           => '',
                'state'          => '',
                'postcode'       => '',
                'notes'          => 'Converted from RFQ #' . $locked->id . '. ' . ($selectedResponse->notes ?? ''),
                'payment_method' => 'rfq',
                'status'         => $initialStatus,
                'subtotal'       => $selectedResponse->total_price,
                'tax'            => 0,
                'tax_rate'       => 0,
                'total'          => $selectedResponse->total_price,
            ]);

            $locked->update(['status' => 'converted']);

            return $order;
        });

        $initialStatus = $order->status;
        $successMsg = $initialStatus === 'pending_approval'
            ? 'RFQ converted to order #' . $order->order_number . ' and submitted for approval by your organization.'
            : 'RFQ converted to order #' . $order->order_number . '.';

        return redirect()->route('order.details', $order)
            ->with('success', $successMsg);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function currentOrgId(): int
    {
        return (int) CurrentOrg::id((int) Auth::id());
    }

    private function requireOrgLevel(int $orgId, string $group, string $level): void
    {
        abort_unless(
            $orgId > 0 && $this->permissions->checkPermission((int) Auth::id(), $orgId, $group, $level),
            403,
            'You do not have permission to perform this action.'
        );
    }

    private function authorizeRfq(RfqRequest $rfq, string $level): int
    {
        $userId = (int) Auth::id();
        $orgId = $this->currentOrgId();

        abort_if($orgId === 0 || (int) $rfq->org_id !== $orgId, 403);

        if ($rfq->project_id !== null) {
            abort_unless(
                Project::visibleTo($userId, $orgId)->whereKey($rfq->project_id)->exists(),
                404
            );
        }

        abort_unless(
            $this->permissions->checkPermission($userId, $orgId, 'quote_rfq_management', $level, $rfq->project_id ? (int) $rfq->project_id : null),
            403,
            'You do not have permission to perform this action.'
        );

        return $orgId;
    }
}

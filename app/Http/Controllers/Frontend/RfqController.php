<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RfqRecipient;
use App\Models\RfqRequest;
use App\Models\RfqResponse;
use App\Models\Rbac\Organization;
use App\Services\Rbac\OrgRelationshipService;
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
    public function __construct(private readonly OrgRelationshipService $orgRelationships) {}

    // ── Step 1: list buyer's RFQs ────────────────────────────────────────────

    public function index()
    {
        $orgId = $this->currentOrgId();

        $rfqs = RfqRequest::with(['recipients.sellerOrganization', 'responses'])
            ->where('org_id', $orgId)
            ->latest()
            ->get();

        return view('user.rfq.index', compact('rfqs'));
    }

    // ── Step 1: create form ──────────────────────────────────────────────────

    public function create()
    {
        $orgId = $this->currentOrgId();

        // Only show seller orgs this buyer has an active buyer_seller relationship with (§4.5).
        $partnerIds = $this->orgRelationships->partnerIds($orgId, 'buyer_seller');

        $sellerOrgs = Organization::whereIn('id', $partnerIds)->orderBy('name')->get();

        return view('user.rfq.create', compact('sellerOrgs'));
    }

    // ── Step 1: send RFQ ─────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $orgId = $this->currentOrgId();

        $partnerIds = $this->orgRelationships->partnerIds($orgId, 'buyer_seller');

        $request->validate([
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

        DB::transaction(function () use ($request, $orgId) {
            $rfq = RfqRequest::create([
                'org_id'     => $orgId,
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
        $this->authorizeOwnership($rfq);

        $rfq->load(['recipients.sellerOrganization', 'responses.sellerOrganization', 'responses.creator']);

        return view('user.rfq.show', compact('rfq'));
    }

    // ── Step 2: select a seller's response ──────────────────────────────────

    public function selectResponse(Request $request, RfqRequest $rfq, RfqResponse $response)
    {
        $this->authorizeOwnership($rfq);

        abort_if($response->rfq_request_id !== $rfq->id, 403);

        DB::transaction(function () use ($rfq, $response) {
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
        $this->authorizeOwnership($rfq);

        $selectedResponse = $rfq->responses()->where('status', 'selected')->first();

        abort_if($selectedResponse === null, 422, 'No response has been selected for this RFQ.');

        $order = DB::transaction(function () use ($rfq, $selectedResponse) {
            $order = Order::create([
                'user_id'        => Auth::id(),
                'org_id'         => $rfq->org_id,
                'project_title'  => $rfq->title,
                'name'           => $rfq->title,
                'email'          => Auth::user()->email,
                'phone'          => '',
                'address1'       => '',
                'city'           => '',
                'state'          => '',
                'postcode'       => '',
                'notes'          => 'Converted from RFQ #' . $rfq->id . '. ' . ($selectedResponse->notes ?? ''),
                'payment_method' => 'rfq',
                'status'         => 'pending',
                'subtotal'       => $selectedResponse->total_price,
                'tax'            => 0,
                'tax_rate'       => 0,
                'total'          => $selectedResponse->total_price,
            ]);

            $rfq->update(['status' => 'converted']);

            return $order;
        });

        return redirect()->route('order.details', $order)
            ->with('success', 'RFQ converted to order #' . $order->order_number . '.');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function currentOrgId(): int
    {
        return (int) session(config('rbac.current_org_session_key'));
    }

    private function authorizeOwnership(RfqRequest $rfq): void
    {
        abort_if((int) $rfq->org_id !== $this->currentOrgId(), 403);
    }
}

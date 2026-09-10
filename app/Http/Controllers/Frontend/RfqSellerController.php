<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\RfqRecipient;
use App\Models\RfqRequest;
use App\Models\RfqResponse;
use App\Models\Rbac\Organization;
use App\Services\Rbac\OrgRelationshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Seller-side RFQ actions (doc §6.6).
 *
 * Sellers see RFQs sent to their org, can respond with a quoted price,
 * or decline. Permission enforced via route_permission_map (quote_rfq_management:S).
 */
class RfqSellerController extends Controller
{
    public function __construct(private readonly OrgRelationshipService $orgRelationships) {}

    public function incoming()
    {
        $orgId = $this->currentOrgId();

        $recipients = RfqRecipient::with(['rfqRequest.creator', 'rfqRequest.organization'])
            ->where('seller_org_id', $orgId)
            ->latest()
            ->get();

        return view('user.rfq.seller-incoming', compact('recipients'));
    }

    public function respond(Request $request, RfqRequest $rfq)
    {
        $orgId = $this->currentOrgId();

        $recipient = RfqRecipient::where('rfq_request_id', $rfq->id)
            ->where('seller_org_id', $orgId)
            ->firstOrFail();

        // §4.5: verify an active buyer_seller relationship exists between buyer and this seller.
        abort_unless(
            $this->orgRelationships->hasActive($rfq->org_id, $orgId, 'buyer_seller'),
            403,
            'No active trading partnership with this buyer.'
        );

        // §4.5: verify distributor / rep_agency seller has an active principal relationship.
        $sellerOrg = Organization::find($orgId);
        if ($sellerOrg) {
            $authError = $this->orgRelationships->sellerAuthorizationError($orgId, (string) $sellerOrg->org_type);
            abort_if($authError !== null, 403, $authError);
        }

        $request->validate([
            'total_price' => 'required|numeric|min:0',
            'valid_until' => 'nullable|date|after_or_equal:today',
            'notes'       => 'nullable|string|max:2000',
        ]);

        RfqResponse::create([
            'rfq_request_id' => $rfq->id,
            'seller_org_id'  => $orgId,
            'created_by'     => Auth::id(),
            'total_price'    => $request->total_price,
            'valid_until'    => $request->valid_until,
            'notes'          => $request->notes,
            'status'         => 'pending_review',
        ]);

        $recipient->update(['status' => 'responded']);

        return redirect()->route('rfq.seller.incoming')
            ->with('success', 'Your quote has been submitted to the buyer.');
    }

    public function decline(RfqRequest $rfq)
    {
        $orgId = $this->currentOrgId();

        $recipient = RfqRecipient::where('rfq_request_id', $rfq->id)
            ->where('seller_org_id', $orgId)
            ->firstOrFail();

        $recipient->update(['status' => 'declined']);

        return redirect()->route('rfq.seller.incoming')
            ->with('success', 'You have declined this RFQ.');
    }

    private function currentOrgId(): int
    {
        return (int) session(config('rbac.current_org_session_key'));
    }
}

<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pallet;
use App\Models\ProductVariationColor;
use App\Models\StateTax;
use App\Services\Rbac\ApprovalRoutingService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;

class CheckoutController extends Controller
{
    public function checkout()
    {
        $this->requireOrgLevel('procurement', 'R');
        $pallet = [];
        $palletItems = [];
        $palletAddress = null;
        $fromPallet = request()->get('from_pallet', false);

        if (Auth::check()) {
            // User is logged in - check if there's session data to migrate
            $sessionPallet = session()->get('pallet', []);  
            if (!empty($sessionPallet)) {
                // Migrate session data to database
                $this->migrateSessionToDatabase($sessionPallet);
                // Clear session after migration
                session()->forget('pallet');
            }

            // Get data from database
            $palletItems = \App\Models\ProductVariationColor::with([
                'productVariation.product.division',
                'productVariation.product.specification',
                'productVariation.product.manufacturer',
                'productVariation.size',
                'productVariation.thickness',
                'productVariation.finish',
                'productVariation.paint_type',
                'productVariation.color_effect',
                'color'
            ])->whereHas('pallets', function($query) {
                $query->where('user_id', Auth::id());
            })->get();
 
            if ($palletItems->isNotEmpty()) {
                // Get pallet data with quantities
                $palletData = \App\Models\Pallet::where('user_id', Auth::id())
                    ->with('palletAddress.state')
                    ->get()
                    ->keyBy('product_variation_color_id');

                // Format pallet data for view
                foreach ($palletData as $item) {
                    $pallet[$item->product_variation_color_id] = [
                        'quantity' => $item->quantity,
                        'product_variation_color_id' => $item->product_variation_color_id
                    ];
                }

                // Get address from PalletAddress table
                $palletAddress = \App\Models\PalletAddress::where('user_id', Auth::id())->with('state')->first();
            }
        } else {
            // User is not logged in - get data from session
            $pallet = session()->get('pallet', []); 
            // Debug: Log session data
            \Log::info('Session pallet data:', $pallet);
            \Log::info('Session pallet_address data:', session()->get('pallet_address'));
            
            if (!empty($pallet)) {
                $palletItems = \App\Models\ProductVariationColor::with([
                    'productVariation.product.division',
                    'productVariation.product.specification',
                    'productVariation.product.manufacturer',
                    'productVariation.size',
                    'productVariation.thickness',
                    'productVariation.finish',
                    'productVariation.paint_type',
                    'productVariation.color_effect',
                    'color'
                ])->whereIn('id', array_keys($pallet))->get();

                // Get address from session if available
                $palletAddress = session()->get('pallet_address');
                if ($palletAddress) {
                        $palletAddress = (object) [
                        'name' => $palletAddress['name'] ?? '',
                        'project_name' => $palletAddress['project_name'] ?? '',
                        'address1' => $palletAddress['address1'],
                        'city' => $palletAddress['city'],
                        'state' => (object) ['state' => \App\Models\StateTax::find($palletAddress['state_id'])->state ?? ''],
                        'postcode' => $palletAddress['postcode']
                        ];
                }
            }
        }

        // If no pallet data found, redirect back
        if (empty($pallet) && empty($palletItems)) {
            return redirect()->back()->with('error', 'No items found in pallet.');
        }

        $states = StateTax::orderBy('state')->get(); // Get all states with IDs

        return view('frontend.products.checkout', compact('pallet', 'palletItems', 'palletAddress', 'states', 'fromPallet'));
    }

    public function processCheckout(Request $request)
    {
        $this->requireOrgLevel('procurement', 'S');

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'project_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'address1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'postcode' => 'required',
        ], [
            'name.required' => 'Jobsite general contractor is required.',
            'project_name.required' => 'Project name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'Phone number is required.',
            'address1.required' => 'Address is required.',
    
            'city.required' => 'City is required.',
            'state.required' => 'State is required.',
            'postcode.required' => 'ZIP code is required.',
        ]);
        if ($validator->fails()) {
            // For debugging - you can dd() here to see the validation errors
            // dd($validator->errors());
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $pallet = [];
        $palletItems = [];
        
        if (Auth::check()) {
            // User is logged in - get data from database
            $palletItems = ProductVariationColor::with('productVariation')
                ->whereHas('pallets', function($query) {
                    $query->where('user_id', Auth::id());
                })->get();

            if ($palletItems->isNotEmpty()) {
                $palletData = Pallet::where('user_id', Auth::id())->get()->keyBy('product_variation_color_id');
                
                foreach ($palletData as $item) {
                    $pallet[$item->product_variation_color_id] = [
                        'quantity' => $item->quantity,
                        'product_variation_color_id' => $item->product_variation_color_id
                    ];
                }
            }
        } else {
            // User is not logged in - get data from session
            $pallet = session()->get('pallet', []);
            
            if (!empty($pallet)) {
                $palletItems = \App\Models\ProductVariationColor::with('productVariation')
                    ->whereIn('id', array_keys($pallet))->get();
            }
        }
        if (empty($pallet) && empty($palletItems)) {
            return redirect()->back()->with('error', 'Your pallet is empty.');
        }

        DB::beginTransaction();
        
        try {
            // Calculate subtotal
            $subtotal = 0;

            foreach ($palletItems as $item) {
                $quantity = $pallet[$item->id]['quantity'];
                $price = $item->productVariation->pricing;
                $itemTotal = $quantity * $price;
                $subtotal += $itemTotal;
            }

            // Calculate tax based on state
            $stateTax = StateTax::where('state', $request->state)->first();
            $taxRate = $stateTax ? $stateTax->combined_tax_rate : 0;
            $tax = $subtotal * ($taxRate / 100);
            $total = $subtotal + $tax;

            // Determine order status via approval routing (plan §6.2).
            // Solo operators auto-approve; multi-approver orgs route to the pool.
            $initialStatus = 'pending';
            if (Auth::check()) {
                $orgId = CurrentOrg::sessionOrg((int) Auth::id());
                if ($orgId) {
                    $routing = app(ApprovalRoutingService::class)->route((int) $orgId, Auth::id());
                    if (! $routing['auto_approve'] && empty($routing['approver_ids'])) {
                        DB::rollBack();
                        $noApprover = 'Your organization has no one who can approve orders. Ask an organization owner to assign an Executive Approver (or another role with approval authority), then try again.';

                        return $request->expectsJson()
                            ? response()->json(['message' => $noApprover], 422)
                            : redirect()->back()->withInput()->with('error', $noApprover);
                    }
                    $initialStatus = $routing['auto_approve'] ? 'pending' : 'pending_approval';
                }
            }

            // Create order with calculated values
            $order = Order::create([
                'user_id' => Auth::id() ?? null,
                'org_id' => Auth::check() ? (CurrentOrg::sessionOrg((int) Auth::id()) ?: null) : null,
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'name' => $request->name,
                'project_title' => $request->project_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'address1' => $request->address1,

                'city' => $request->city,
                'state' => $request->state,
                'postcode' => $request->postcode,
                'notes' => $request->notes,
                'payment_method' => $request->payment_method ?? 'cod',
                'status' => $initialStatus,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'tax_rate' => $taxRate,
                'total' => $total,
            ]);

            // Add order items
            foreach ($palletItems as $item) {
                $quantity = $pallet[$item->id]['quantity'];
                $price = $item->productVariation->pricing;
                $itemTotal = $quantity * $price;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variation_color_id' => $item->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $itemTotal,
                ]);
            }

            DB::commit();

            // Clear pallet data based on user status
            if (Auth::check()) {
                // Clear database pallet data for logged-in user
                \App\Models\Pallet::where('user_id', Auth::id())->delete();
            } else {
                // Clear session pallet data for guest user
                session()->forget('pallet');
                session()->forget('pallet_address');
            }

            // Redirect to thank you page
            $successMsg = $initialStatus === 'pending_approval'
                ? 'Your order has been submitted and is awaiting approval from your organization.'
                : 'Your order has been placed successfully!';

            return redirect()->route('checkout.success', ['order' => $order->order_number])
                ->with('success', $successMsg)
                ->with('order_status', $initialStatus);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->back()->withInput()->with('error', 'There was an error processing your order. Please try again.');
        }
    }

    /**
     * Migrate session pallet data to database for logged-in users
     */
    private function migrateSessionToDatabase($sessionPallet)
    {
        $userId = Auth::id();
        
        foreach ($sessionPallet as $productVariationColorId => $item) {
            // Check if item already exists in database
            $existingPallet = Pallet::where('user_id', $userId)
                ->where('product_variation_color_id', $productVariationColorId)
                ->first();
            
            if ($existingPallet) {
                // Update quantity if item exists
                $existingPallet->update([
                    'quantity' => $existingPallet->quantity + $item['quantity']
                ]);
            } else {
                // Create new pallet item
                Pallet::create([
                    'user_id' => $userId,
                    'product_variation_color_id' => $productVariationColorId,
                    'quantity' => $item['quantity']
                ]);
            }
        }
        
        // Handle address data separately
        $palletAddress = session()->get('pallet_address');
        \Log::info('Migrating pallet_address from session:', $palletAddress);
        
        if ($palletAddress) {
            $this->savePalletAddress($userId, $palletAddress);
            // Clear the pallet_address session after migration
            session()->forget('pallet_address');
            \Log::info('Pallet address migrated and session cleared');
        }
    }
    
    /**
     * Save pallet address to database
     */
    private function savePalletAddress($userId, $addressData)
    {
        \Log::info('Saving pallet address for user:', ['user_id' => $userId, 'address_data' => $addressData]);
        
        // Check if address already exists for this user
        $existingAddress = \App\Models\PalletAddress::where('user_id', $userId)->first();
        
        $addressFields = [
            'user_id' => $userId,
            'name' => $addressData['name'] ?? '',
            'project_name' => $addressData['project_name'] ?? '',
            'address1' => $addressData['address1'] ?? '',
            'city' => $addressData['city'] ?? '',
            'state_id' => $addressData['state_id'] ?? null,
            'postcode' => $addressData['postcode'] ?? ''
        ];
        
        \Log::info('Address fields to save:', $addressFields);
        
        if ($existingAddress) {
            $existingAddress->update($addressFields);
            \Log::info('Updated existing pallet address:', ['id' => $existingAddress->id]);
        } else {
            $palletAddress = \App\Models\PalletAddress::create($addressFields);
            \Log::info('Created new pallet address:', ['id' => $palletAddress->id]);
        }
    }

    public function checkoutSuccess($order)
    {
        $this->requireOrgLevel('procurement', 'R');
        return view('frontend.products.checkout-success', compact('order'));
    }

    private function requireOrgLevel(string $group, string $level): void
    {
        $userId = (int) Auth::id();
        $orgId = (int) CurrentOrg::id($userId);

        abort_unless(
            $orgId > 0 && app(PermissionService::class)->checkPermission($userId, $orgId, $group, $level),
            403,
            'You do not have permission to perform this action.'
        );
    }
}

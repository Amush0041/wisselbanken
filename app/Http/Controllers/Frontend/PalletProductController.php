<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Pallet;
use App\Models\ProductVariation;
use App\Models\ProductVariationColor;
use App\Models\SavedList;
use App\Models\StateTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;

class PalletProductController extends Controller
{
    public function addToPallet(Request $request)
    {
        $productColorVariationId = $request->productColorVariationId;
        $quantity = (int) $request->quantity;
        $action = $request->action;

        if (!$productColorVariationId) {
            return response()->json(['message' => "Invalid request data. Please try again."], 400);
        }

        $pallet = session()->get('pallet', []);

        if ($action == "add") {
            // Add product to the pallet
            if (isset($pallet[$productColorVariationId])) {
                $pallet[$productColorVariationId]['quantity'] += $quantity;
            } else {
                $pallet[$productColorVariationId] = [
                    'product_variation_color_id' => $productColorVariationId,
                    'quantity' => $quantity,
                ];
            }

            session()->put('pallet', $pallet);

            return response()->json([
                'message' => "Product added to pallet successfully!",
            ]);
        } else {
            // Remove product from the pallet
            if (isset($pallet[$productColorVariationId])) {
                unset($pallet[$productColorVariationId]);
                session()->put('pallet', $pallet);
            }

            return response()->json([
                'message' => "Product removed from pallet.",
            ]);
        }
    }

    public function miniPallet(Request $request)
    {
        if (Auth::check()) {
            // User is logged in - get from database
            $palletItems = Pallet::with(['variationColor.productVariation.product'])
                ->where('user_id', Auth::user()->id)
                ->get();

            $palletCount = $palletItems->count();
            $totalQuantity = $palletItems->sum('quantity');
            
            // Get pallet data for view
            $palletData = [];
            foreach ($palletItems as $palletItem) {
                if ($palletItem->variationColor && $palletItem->variationColor->id) {
                    $palletData[$palletItem->variationColor->id] = [
                        'quantity' => $palletItem->quantity,
                        'pallet_address_id' => $palletItem->pallet_address_id
                    ];
                }
            }
            
            $palletItemsForView = $palletItems->map(function($palletItem) {
                return $palletItem->variationColor;
            })->filter(); // Remove null values
            
            // Create empty pallet array for database case (not used but needed for view)
            $pallet = [];
        } else {
            // User is not logged in - get from session
            $pallet = session()->get('pallet', []);
            $palletCount = count($pallet);
            $totalQuantity = array_sum(array_column($pallet, 'quantity'));
            
            $palletItemsForView = ProductVariationColor::with([
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
            
            // Create empty palletData array for session case (not used but needed for view)
            $palletData = [];
        }

        $html = view('frontend.products.pallet.navPallet', compact('palletItemsForView', 'pallet', 'palletData'))->render();

        return response()->json([
            'total' => (int) $palletCount,
            'html' => $html
        ]);
    }

    public function updateProductDetailSidebarPallet(Request $request)
    {
        $productId = $request->input('product_id');
        
        \Log::info('updateProductDetailSidebarPallet called for product_id: ' . $productId);
        \Log::info('User authenticated: ' . (Auth::check() ? 'Yes' : 'No'));

        if (Auth::check()) {
            // User is logged in - get from database
            $palletItems = ProductVariationColor::with([
                'productVariation.product.division',
                'productVariation.product.specification',
                'productVariation.product.manufacturer',
                'productVariation.size',
                'productVariation.thickness',
                'productVariation.finish',
                'productVariation.paint_type',
                'productVariation.color_effect',
                'color'
            ])
                ->whereHas('productVariation.product', function ($query) use ($productId) {
                    $query->where('id', $productId);
                })
                ->whereHas('pallets', function ($query) {
                    $query->where('user_id', Auth::user()->id);
                })
                ->get();

            \Log::info('Database pallet items found: ' . $palletItems->count());

            // Get pallet data for view
            $pallet = [];
            foreach ($palletItems as $item) {
                $palletItem = $item->pallets()->where('user_id', Auth::user()->id)->first();
                if ($palletItem) {
                    $pallet[$item->id] = [
                        'quantity' => $palletItem->quantity,
                        'pallet_address_id' => $palletItem->pallet_address_id
                    ];
                }
            }
            
            \Log::info('Pallet data for view: ' . json_encode($pallet));
        } else {
            // User is not logged in - get from session
            $pallet = session()->get('pallet', []);
            
            \Log::info('Session pallet data: ' . json_encode($pallet));
            
            $palletItems = ProductVariationColor::with([
                'productVariation.product.division',
                'productVariation.product.specification',
                'productVariation.product.manufacturer',
                'productVariation.size',
                'productVariation.thickness',
                'productVariation.finish',
                'productVariation.paint_type',
                'productVariation.color_effect',
                'color'
            ])
                ->whereHas('productVariation.product', function ($query) use ($productId) {
                    $query->where('id', $productId);
                })
                ->whereIn('id', array_column($pallet, 'product_variation_color_id'))
                ->get();
                
            \Log::info('Session pallet items found: ' . $palletItems->count());
        }

        \Log::info('Final pallet items count: ' . $palletItems->count());

        return response()->json([
            'html' => view('frontend.products.pallet.productdetailsidebar', compact('palletItems', 'pallet'))->render()
        ]);
    }

    public function addMultipleToPallet(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.variationId' => 'required',
            'items.*.colorId' => 'required',
            'items.*.quantity' => 'required',
            'items.*.productColorVariationId' => 'required',
            'name' => 'nullable|string|max:255',
            'project_name' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|integer|exists:state_taxes,id',
            'postcode' => 'nullable|string|max:20',
        ]);

        // Check if user is logged in
        if (Auth::check()) {
            // User is logged in - store in database
            return $this->addToDatabasePallet($request);
        } else {
            // User is not logged in - store in session
            return $this->addToSessionPallet($request);
        }
    }

    private function addToDatabasePallet(Request $request)
    {
        $pallet = Pallet::where('user_id', Auth::user()->id)->get();
        
        // Check if we already have address information
        $hasAddressInfo = $pallet->isNotEmpty() && $pallet->first()->pallet_address_id;
        $existingPalletAddressId = $hasAddressInfo ? $pallet->first()->pallet_address_id : null;

        // Create or update pallet address if address info is provided
        $palletAddressId = null;
        if ($request->address1) {
            // Check if user already has a pallet address
            $existingPalletAddress = \App\Models\PalletAddress::where('user_id', Auth::user()->id)->first();
            
            if ($existingPalletAddress) {
                // Update existing address
                $existingPalletAddress->update([
                    'name' => $request->name,
                    'project_name' => $request->project_name,
                    'address1' => $request->address1,
                    'city' => $request->city,
                    'state_id' => $request->state,
                    'postcode' => $request->postcode,
                ]);
                $palletAddressId = $existingPalletAddress->id;
            } else {
                // Create new address
                $palletAddress = \App\Models\PalletAddress::create([
                    'user_id' => Auth::user()->id,
                    'name' => $request->name,
                    'project_name' => $request->project_name,
                    'address1' => $request->address1,
                    'city' => $request->city,
                    'state_id' => $request->state,
                    'postcode' => $request->postcode,
                ]);
                $palletAddressId = $palletAddress->id;
            }
        } elseif ($hasAddressInfo) {
            $palletAddressId = $existingPalletAddressId;
        }

        foreach ($request->items as $item) {
            $productColorVariationId = is_numeric($item['productColorVariationId'])
                ? $item['productColorVariationId']
                : decrypt($item['productColorVariationId']);

            $quantity = (int) $item['quantity'];

            // Check if item already exists in database
            $existingPallet = Pallet::where('user_id', Auth::user()->id)
                ->where('product_variation_color_id', $productColorVariationId)
                ->first();

            if ($existingPallet) {
                // Update existing item
                $existingPallet->update([
                    'quantity' => $existingPallet->quantity + $quantity,
                    'pallet_address_id' => $palletAddressId ?: $existingPallet->pallet_address_id
                ]);
            } else {
                // Create new item
                Pallet::create([
                    'user_id' => Auth::user()->id,
                    'product_variation_color_id' => $productColorVariationId,
                    'quantity' => $quantity,
                    'pallet_address_id' => $palletAddressId
                ]);
            }
        }

        return response()->json([
            'message' => "Items added to pallet",
            'status' => true
        ]);
    }

    private function addToSessionPallet(Request $request)
    {
        $pallet = session()->get('pallet', []);
        $palletAddress = session()->get('pallet_address', null);

        // Check if we already have address information
        $hasAddressInfo = !empty($palletAddress);

        // If new address data is provided, update or create session address
        if ($request->address1) {
            // Store or update address data separately in session
            $palletAddress = [
                'name' => $request->name,
                'project_name' => $request->project_name,
                'address1' => $request->address1,
                'city' => $request->city,
                'state_id' => $request->state,
                'postcode' => $request->postcode,
                ];
            
            session()->put('pallet_address', $palletAddress);
            \Log::info('Updated pallet address in session:', $palletAddress);
        }

        // Clean up old session structure (remove address_data from pallet items)
        foreach ($pallet as $key => $item) {
            if (isset($item['address_data'])) {
                unset($pallet[$key]['address_data']);
            }
        }

        foreach ($request->items as $item) {
            $productColorVariationId = is_numeric($item['productColorVariationId'])
                ? $item['productColorVariationId']
                : decrypt($item['productColorVariationId']);

            $quantity = (int) $item['quantity'];

            if (isset($pallet[$productColorVariationId])) {
                $pallet[$productColorVariationId]['quantity'] += $quantity;
            } else {
                $palletItem = [
                    'product_variation_color_id' => $productColorVariationId,
                    'quantity' => $quantity,
                ];
                
                $pallet[$productColorVariationId] = $palletItem;
            }
        }

        session()->put('pallet', $pallet);

        return response()->json([
            'message' => "Items added to pallet",
            'status' => true
        ]);
    }

    public function addListToPallet($listId)
    {
        $user = Auth::user();
        
        $list = SavedList::with('items')
            ->where('id', $listId)
            ->where('user_id', $user->id)
            ->first();

        if (!$list) {
            return redirect()->back()->with('error', 'List not found');
        }

        // Log the list data for debugging
        \Log::info('Adding list to pallet:', [
            'list_id' => $listId,
            'list_name' => $list->name,
            'list_address1' => $list->address1,
            'list_address2' => $list->address2,
            'list_city' => $list->city,
            'list_state_id' => $list->state_id,
            'list_postcode' => $list->postcode,
        ]);

        if (Auth::check()) {
            // User is logged in - add to database
            return $this->addListToDatabasePallet($list);
        } else {
            // User is not logged in - add to session
            return $this->addListToSessionPallet($list);
        }
    }

    private function addListToDatabasePallet($list)
    {
        // Check if we already have address information
        $existingPallet = Pallet::where('user_id', Auth::user()->id)->first();
        $hasAddressInfo = $existingPallet && $existingPallet->pallet_address_id;
        $existingPalletAddressId = $hasAddressInfo ? $existingPallet->pallet_address_id : null;
        
        // Also check if we have a pallet address record
        $existingPalletAddress = \App\Models\PalletAddress::where('user_id', Auth::user()->id)->first();
        if ($existingPalletAddress) {
            $hasAddressInfo = true;
            $existingPalletAddressId = $existingPalletAddress->id;
        }
        
        // Log the address info check
        \Log::info('Address info check:', [
            'hasAddressInfo' => $hasAddressInfo,
            'existingPalletAddressId' => $existingPalletAddressId,
            'existingPalletAddress' => $existingPalletAddress ? $existingPalletAddress->toArray() : null,
        ]);

        // Create pallet address from list information if we don't have address info
        $palletAddressId = null;
        if (!$hasAddressInfo && $list->address1) {
            $palletAddress = \App\Models\PalletAddress::create([
                'user_id' => Auth::user()->id,
                'name' => $list->address1, // Jobsite General Contractor
                'project_name' => $list->name, // Project name from list
                'address1' => $list->address2, // Address
                'city' => $list->city,
                'state_id' => $list->state_id,
                'postcode' => $list->postcode,
            ]);
            $palletAddressId = $palletAddress->id;
            
            // Log the created pallet address
            \Log::info('Created pallet address from list:', [
                'pallet_address_id' => $palletAddressId,
                'name' => $list->address1,
                'project_name' => $list->name,
                'address1' => $list->address2,
                'city' => $list->city,
                'state_id' => $list->state_id,
                'postcode' => $list->postcode,
            ]);
        } elseif ($hasAddressInfo) {
            $palletAddressId = $existingPalletAddressId;
        }

        // Add list items to database pallet
        foreach ($list->items as $item) {
            $existingPallet = Pallet::where('user_id', Auth::user()->id)
                ->where('product_variation_color_id', $item->product_color_variation_id)
                ->first();

            if ($existingPallet) {
                // Update existing item
                $existingPallet->update([
                    'quantity' => $existingPallet->quantity + $item->quantity,
                    'pallet_address_id' => $palletAddressId ?: $existingPallet->pallet_address_id
                ]);
            } else {
                // Create new item
                Pallet::create([
                    'user_id' => Auth::user()->id,
                    'product_variation_color_id' => $item->product_color_variation_id,
                    'quantity' => $item->quantity,
                    'pallet_address_id' => $palletAddressId
                ]);
            }
        }

        return redirect()->route('pallet.view')->with('success', 'List added to pallet successfully');
    }

    private function addListToSessionPallet($list)
    {
        $pallet = session()->get('pallet', []);
        $palletAddress = session()->get('pallet_address', null);

        // Check if we already have address information
        $hasAddressInfo = !empty($palletAddress);
        
        // Log the session address check
        \Log::info('Session address check:', [
            'hasAddressInfo' => $hasAddressInfo,
            'palletAddress' => $palletAddress,
        ]);

        // Add address data from list if we don't have address info
        if (!$hasAddressInfo && $list->address1) {
            $palletAddress = [
                'name' => $list->address1, // Jobsite General Contractor
                'project_name' => $list->name, // Project name from list
                'address1' => $list->address2, // Address
                'city' => $list->city,
                'state_id' => $list->state_id,
                'postcode' => $list->postcode,
            ];
            
            session()->put('pallet_address', $palletAddress);
            
            // Log the session pallet address
            \Log::info('Created session pallet address from list:', $palletAddress);
        }

        // Add list items to session pallet
        foreach ($list->items as $item) {
            if (isset($pallet[$item->product_color_variation_id])) {
                $pallet[$item->product_color_variation_id]['quantity'] += $item->quantity;
            } else {
                $palletItem = [
                    'product_variation_color_id' => $item->product_color_variation_id,
                    'saved_list_id' => $list->id,
                    'quantity' => $item->quantity,
                ];
                
                $pallet[$item->product_color_variation_id] = $palletItem;
            }
        }

        session()->put('pallet', $pallet);

        return redirect()->route('pallet.view')->with('success', 'List added to pallet successfully');
    }

    public function viewPallet()
    {
        if (Auth::check()) {
            // Check if user has session data that needs to be migrated
            $sessionPallet = session()->get('pallet', []);
            $sessionAddress = session()->get('pallet_address');
            
            if (!empty($sessionPallet) && Auth::user()->email_verified_at) {
                // Auto-migrate session data to database
                $this->autoMigrateSessionToDatabase();
                
                // Set a flash message to inform user about the migration
                session()->flash('pallet_migrated', 'Your pallet items have been automatically saved to your account!');
            }
            
            // User is logged in - get data from database
            return $this->getDatabasePallet();
        } else {
            // User is not logged in - get data from session
            return $this->getSessionPallet();
        }
    }

    private function getDatabasePallet()
    {
        $palletItems = ProductVariationColor::with([
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
            $query->where('user_id', Auth::user()->id);
        })->get();

        // Get pallet data from database
        $palletData = [];
        $palletAddress = null;
        $subtotal = 0;

        foreach ($palletItems as $item) {
            $palletRecord = Pallet::where('user_id', Auth::user()->id)
                ->where('product_variation_color_id', $item->id)
                ->first();

            if ($palletRecord) {
                $palletData[$item->id] = [
                    'quantity' => $palletRecord->quantity,
                    'pallet_address_id' => $palletRecord->pallet_address_id
                ];

                $price = $item->productVariation->pricing ?? 0;
                $subtotal += $palletRecord->quantity * $price;

                // Get address info from the first item
                if (!$palletAddress && $palletRecord->pallet_address_id) {
                    $palletAddress = \App\Models\PalletAddress::with('state')->find($palletRecord->pallet_address_id);
                }
            }
        }

        // Get state tax rates for JavaScript
        $stateTaxRates = StateTax::pluck('combined_tax_rate', 'state');
        
        return view('frontend.products.pallet.viewPallet', compact('palletItems', 'palletData', 'palletAddress', 'subtotal', 'stateTaxRates'));
    }

    private function getSessionPallet()
    {
        $pallet = session()->get('pallet', []);

        $palletItems = ProductVariationColor::with([
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

        // Get pallet address information if available
        $palletAddress = null;
        $sessionAddress = session()->get('pallet_address');
        if ($sessionAddress) {
                // Create a temporary address object for session data
                $palletAddress = (object) [
                'name' => $sessionAddress['name'] ?? '',
                'project_name' => $sessionAddress['project_name'] ?? '',
                'address1' => $sessionAddress['address1'],
                'city' => $sessionAddress['city'],
                'state' => (object) ['state' => \App\Models\StateTax::find($sessionAddress['state_id'])->state ?? ''],
                'postcode' => $sessionAddress['postcode']
                ];
        }

        $subtotal = 0;
        foreach ($palletItems as $item) {
            $palletItem = $pallet[$item->id] ?? null;
            if ($palletItem) {
                $price = $item->productVariation->pricing ?? 0;
                $subtotal += $palletItem['quantity'] * $price;
            }
        }

        // Get state tax rates for JavaScript
        $stateTaxRates = StateTax::pluck('combined_tax_rate', 'state');
        
        return view('frontend.products.pallet.viewPallet', compact('palletItems', 'pallet', 'palletAddress', 'subtotal', 'stateTaxRates'));
    }

    public function updatePalletItem(Request $request)
    {
        try {
            $productVariationId = is_numeric($request->variationId)
                ? $request->variationId
                : decrypt($request->variationId);

            $colorId = is_numeric($request->colorId)
                ? $request->colorId
                : decrypt($request->colorId);

            $productColorVariationId = is_numeric($request->productColorVariationId)
                ? $request->productColorVariationId
                : decrypt($request->productColorVariationId);

            $quantity = (int) $request->quantity;
            $action = $request->action;

            if (!$productVariationId || !$colorId || !$productColorVariationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data. Please try again'
                ], 400);
            }

            $productVariation = ProductVariation::find($productVariationId);
            if (!$productVariation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid product variation. Please try again.'
                ], 400);
            }

            if (Auth::check()) {
                // User is logged in - update database
                $pallet = Pallet::where('user_id', Auth::user()->id)
                    ->where('product_variation_color_id', $productColorVariationId)
                    ->first();

                if ($pallet) {
                    $pallet->update(['quantity' => $quantity]);
                }
            } else {
                // User is not logged in - update session
                $pallet = session()->get('pallet', []);
                if (isset($pallet[$productColorVariationId])) {
                    $pallet[$productColorVariationId]['quantity'] = $quantity;
                    session()->put('pallet', $pallet);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Quantity updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item: ' . $e->getMessage()
            ], 500);
        }
    }

    public function palletItemRemove(Request $request)
    {
        try {
            $id = is_numeric($request->item_id)
                ? $request->item_id
                : decrypt($request->item_id);
                
            \Log::info('palletItemRemove called for item_id: ' . $request->item_id . ' (decrypted: ' . $id . ')');
            \Log::info('User authenticated: ' . (Auth::check() ? 'Yes' : 'No'));

            if (Auth::check()) {
                // User is logged in - remove from database
                $pallet = Pallet::where('user_id', Auth::user()->id)
                    ->where('product_variation_color_id', $id)
                    ->first();

                \Log::info('Database pallet item found: ' . ($pallet ? 'Yes' : 'No'));

                if ($pallet) {
                    $pallet->delete();
                    \Log::info('Database pallet item deleted successfully');
                }
            } else {
                // User is not logged in - remove from session
                $pallet = session()->get('pallet', []);
                \Log::info('Session pallet before removal: ' . json_encode($pallet));
                
                if (isset($pallet[$id])) {
                    unset($pallet[$id]);
                    session()->put('pallet', $pallet);
                    \Log::info('Session pallet item removed successfully');
                } else {
                    \Log::info('Session pallet item not found for id: ' . $id);
                }
                
                \Log::info('Session pallet after removal: ' . json_encode($pallet));
            }

            return response()->json([
                'success' => true,
                'message' => 'Item removed successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in palletItemRemove: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item: ' . $e->getMessage()
            ], 500);
        }
    }

    public function clearPallet()
    {
        if (Auth::check()) {
            // User is logged in - clear from database
            Pallet::where('user_id', Auth::user()->id)->delete();
        } else {
            // User is not logged in - clear from session
            session()->forget('pallet');
            session()->forget('pallet_address');
        }

        return response()->json([
            'success' => true,
            'message' => 'Pallet cleared successfully',
        ]);
    }

    /**
     * Get user's saved lists
     */
    public function getSavedLists()
    {
        $this->requireOrgLevel('project_management', 'R');
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $lists = auth()->user()->savedLists()
            ->withCount('items')
            ->latest()
            ->get();

        return response()->json($lists);
    }

    /**
     * Save or update shopping list
     */
    public function saveShoppingList(Request $request)
    {
        $this->requireOrgLevel('project_management', 'S');
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.variationId' => 'required',
            'items.*.colorId' => 'required',
            'items.*.quantity' => 'required',
            'items.*.productColorVariationId' => 'required',
            'list_id' => 'nullable|exists:saved_lists,id,user_id,' . auth()->id(),
            'name' => 'nullable|string|min:3',
            'project_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|integer|exists:state_taxes,id',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
        ]);

        if ($request->list_id) {
            // Update existing list
            $list = SavedList::find($request->list_id);
            
            // Only update address if provided (for new lists)
            $updateData = ['name' => $request->name ?? $list->name];
            if ($request->first_name) {
                $updateData = array_merge($updateData, [
                    'project_name' => $request->project_name,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'company' => $request->company,
                    'address1' => $request->address1,
                    'address2' => $request->address2,
                    'city' => $request->city,
                    'state_id' => $request->state,
                    'postcode' => $request->postcode,
                    'country' => $request->country,
                ]);
            }
            
            $list->update($updateData);
            $message = 'List updated successfully';
        } else {
            // Create new list with address information - use project_name as list name
            $listData = [
                'name' => $request->project_name, // Use project_name as list name
                'project_name' => $request->project_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'address1' => $request->address1,
                'address2' => $request->address2,
                'city' => $request->city,
                'state_id' => $request->state,
                'postcode' => $request->postcode,
                'country' => $request->country,
            ];
            
            $list = auth()->user()->savedLists()->create($listData);
            $message = 'List saved successfully';
        }

        // Add items to list
        foreach ($request->items as $item) {
            // Check if item already exists in the list
            $existingItem = $list->items()
                ->where('product_color_variation_id', decrypt($item['productColorVariationId']))
                ->where('color_id', decrypt($item['colorId']))
                ->first();

            if ($existingItem) {
                // Update existing item quantity
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $item['quantity']
                ]);
            } else {
                // Create new item
            $list->items()->create([
                'product_variation_id' => decrypt($item['variationId']),
                'product_color_variation_id' => decrypt($item['productColorVariationId']),
                'color_id' => decrypt($item['colorId']),
                'quantity' => $item['quantity']
            ]);
            }
        }

        return response()->json([
            'message' => $message,
            'list' => $list->load('items')
        ]);
    }

    
    public function saveShoppingListProductDetail(Request $request)
    {
        $this->requireOrgLevel('project_management', 'S');
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.variationId' => 'required',
            'items.*.colorId' => 'required',
            'items.*.quantity' => 'required',
            'items.*.productColorVariationId' => 'required',
            'list_id' => 'nullable|exists:saved_lists,id,user_id,' . auth()->id(),
            'project_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|integer|exists:state_taxes,id',
            'postcode' => 'nullable|string|max:20',
        ]);

        if ($request->list_id) {
            // Update existing list
            $list = SavedList::find($request->list_id);
            $list->update(['name' => $request->name ?? $list->name]);

            $message = 'List updated successfully';
        } else {
            // Create new list with address information
            $listData = [
                'name' => $request->project_name, // Use project_name as list name
                'project_name' => $request->project_name,
                'address1' => $request->address1,
                'address2' => $request->address2,
                'city' => $request->city,
                'state_id' => $request->state,
                'postcode' => $request->postcode,
            ];
            
            $list = auth()->user()->savedLists()->create($listData);
            $message = 'List saved successfully';
        }

        // Add items to list
        foreach ($request->items as $item) {
            // Check if item already exists in the list
            $existingItem = $list->items()
                ->where('product_color_variation_id', $item['productColorVariationId'])
                ->where('color_id', $item['colorId'])
                ->first();

            if ($existingItem) {
                // Update existing item quantity
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $item['quantity']
                ]);
            } else {
                // Create new item
            $list->items()->create([
                'product_variation_id' => $item['variationId'],
                'product_color_variation_id' => $item['productColorVariationId'],
                'color_id' => $item['colorId'],
                'quantity' => $item['quantity']
            ]);
            }
        }

        return response()->json([
            'message' => $message,
            'list' => $list->load('items')
        ]);
    }

    /**
     * Get list count for navbar
     */
    public function getListCount()
    {
        if (!Auth::check()) {
            return response()->json(['count' => 0]);
        }

        $count = SavedList::where('user_id', Auth::user()->id)->count();
        
        return response()->json(['count' => $count]);
    }

    public function checkPalletAddress()
    {
        if (Auth::check()) {
            // User is logged in - check database
            $hasAddress = Pallet::where('user_id', Auth::user()->id)
                ->whereNotNull('pallet_address_id')
                ->exists();
             
        } else {
            // User is not logged in - check session
            $palletAddress = session()->get('pallet_address');
            
            // Check if session has address data
            $hasAddress = !empty($palletAddress) && 
                         !empty($palletAddress['address1']) && 
                         !empty($palletAddress['city']) && 
                         !empty($palletAddress['state_id']) && 
                         !empty($palletAddress['postcode']);
        }

        return response()->json(['hasAddress' => $hasAddress]);
    }

    /**
     * Migrate session pallet data to database when user logs in
     * This method should be called after successful login
     */
    public function migrateSessionToDatabase()
    {
        $this->requireOrgLevel('procurement', 'S');
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $sessionPallet = session()->get('pallet', []);
        
        if (empty($sessionPallet)) {
            return response()->json([
                'success' => false,
                'message' => 'No session pallet data to migrate'
            ]);
        }

        $palletAddressId = null;
        $addressData = null;

        // Check if session has address data
        $sessionAddress = session()->get('pallet_address');
        if ($sessionAddress) {
            // Check if user already has a pallet address
            $existingPalletAddress = \App\Models\PalletAddress::where('user_id', Auth::user()->id)->first();
            
            if ($existingPalletAddress) {
                // Update existing address
                $existingPalletAddress->update([
                    'name' => $sessionAddress['name'] ?? null,
                    'project_name' => $sessionAddress['project_name'] ?? null,
                    'address1' => $sessionAddress['address1'],
                    'city' => $sessionAddress['city'],
                    'state_id' => $sessionAddress['state_id'],
                    'postcode' => $sessionAddress['postcode'],
                ]);
                $palletAddressId = $existingPalletAddress->id;
            } else {
                // Create new pallet address record
                $palletAddress = \App\Models\PalletAddress::create([
                    'user_id' => Auth::user()->id,
                    'name' => $sessionAddress['name'] ?? null,
                    'project_name' => $sessionAddress['project_name'] ?? null,
                    'address1' => $sessionAddress['address1'],
                    'city' => $sessionAddress['city'],
                    'state_id' => $sessionAddress['state_id'],
                    'postcode' => $sessionAddress['postcode'],
                ]);
                $palletAddressId = $palletAddress->id;
            }
        }

        // Migrate each session item to database
        foreach ($sessionPallet as $productColorVariationId => $sessionItem) {
            // Check if item already exists in database
            $existingPallet = Pallet::where('user_id', Auth::user()->id)
                ->where('product_variation_color_id', $productColorVariationId)
                ->first();

            if ($existingPallet) {
                // Update existing item
                $existingPallet->update([
                    'quantity' => $existingPallet->quantity + $sessionItem['quantity'],
                    'pallet_address_id' => $palletAddressId ?: $existingPallet->pallet_address_id
                ]);
            } else {
                // Create new item
                Pallet::create([
                    'user_id' => Auth::user()->id,
                    'product_variation_color_id' => $productColorVariationId,
                    'quantity' => $sessionItem['quantity'],
                    'pallet_address_id' => $palletAddressId
                ]);
            }
        }

        // Clear session data after successful migration
        session()->forget('pallet');
        session()->forget('pallet_address');

        return response()->json([
            'success' => true,
            'message' => 'Session pallet data migrated successfully',
            'migrated_items' => count($sessionPallet)
        ]);
    }

    /**
     * Auto-migrate session pallet data to database for verified users
     */
    private function autoMigrateSessionToDatabase()
    {
        $sessionPallet = session()->get('pallet', []);
        $sessionAddress = session()->get('pallet_address');
        
        if (empty($sessionPallet)) {
            return;
        }
        
        \Log::info('Auto-migrating session pallet data for verified user: ' . Auth::user()->id);
        
        $palletAddressId = null;
        
        // Handle address data if available
        if ($sessionAddress) {
            // Check if user already has a pallet address
            $existingPalletAddress = \App\Models\PalletAddress::where('user_id', Auth::user()->id)->first();
            
            if ($existingPalletAddress) {
                // Update existing address
                $existingPalletAddress->update([
                    'name' => $sessionAddress['name'] ?? null,
                    'project_name' => $sessionAddress['project_name'] ?? null,
                    'address1' => $sessionAddress['address1'],
                    'city' => $sessionAddress['city'],
                    'state_id' => $sessionAddress['state_id'],
                    'postcode' => $sessionAddress['postcode'],
                ]);
                $palletAddressId = $existingPalletAddress->id;
            } else {
                // Create new pallet address record
                $palletAddress = \App\Models\PalletAddress::create([
                    'user_id' => Auth::user()->id,
                    'name' => $sessionAddress['name'] ?? null,
                    'project_name' => $sessionAddress['project_name'] ?? null,
                    'address1' => $sessionAddress['address1'],
                    'city' => $sessionAddress['city'],
                    'state_id' => $sessionAddress['state_id'],
                    'postcode' => $sessionAddress['postcode'],
                ]);
                $palletAddressId = $palletAddress->id;
            }
        }
        
        // Migrate each session item to database
        foreach ($sessionPallet as $productColorVariationId => $sessionItem) {
            // Check if item already exists in database
            $existingPallet = Pallet::where('user_id', Auth::user()->id)
                ->where('product_variation_color_id', $productColorVariationId)
                ->first();
            
            if ($existingPallet) {
                // Update existing item
                $existingPallet->update([
                    'quantity' => $existingPallet->quantity + $sessionItem['quantity'],
                    'pallet_address_id' => $palletAddressId ?: $existingPallet->pallet_address_id
                ]);
            } else {
                // Create new item
                Pallet::create([
                    'user_id' => Auth::user()->id,
                    'product_variation_color_id' => $productColorVariationId,
                    'quantity' => $sessionItem['quantity'],
                    'pallet_address_id' => $palletAddressId
                ]);
            }
        }
        
        // Clear session data after successful migration
        session()->forget('pallet');
        session()->forget('pallet_address');
        
        \Log::info('Successfully auto-migrated session pallet data for user: ' . Auth::user()->id);
    }

    /**
     * Get pallet data for checkout
     */
    public function getPalletDataForCheckout()
    {
        $this->requireOrgLevel('procurement', 'R');
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get pallet items and address
        $palletItems = Pallet::with([
            'variationColor.productVariation.product',
            'variationColor.productVariation.size',
            'variationColor.productVariation.thickness',
            'variationColor.productVariation.finish',
            'variationColor.productVariation.paint_type',
            'variationColor.productVariation.color_effect',
            'variationColor.color',
            'palletAddress.state'
        ])->where('user_id', Auth::user()->id)->get();

        if ($palletItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No pallet items found'
            ]);
        }

        // Get address from first item
        $address = $palletItems->first()->palletAddress;

        // Format items for checkout
        $checkoutItems = [];
        foreach ($palletItems as $item) {
            $checkoutItems[] = [
                'product_variation_color_id' => $item->product_variation_color_id,
                'quantity' => $item->quantity,
                'product_name' => $item->variationColor->productVariation->product->name,
                'price' => $item->variationColor->productVariation->pricing ?? 0,
                'size' => $item->variationColor->productVariation->size->name ?? '',
                'thickness' => $item->variationColor->productVariation->thickness->name ?? '',
                'finish' => $item->variationColor->productVariation->finish->name ?? '',
                'paint_type' => $item->variationColor->productVariation->paint_type->name ?? '',
                'color' => $item->variationColor->color->name ?? '',
                'color_effect' => $item->variationColor->productVariation->color_effect->name ?? '',
            ];
        }

        return response()->json([
            'success' => true,
            'items' => $checkoutItems,
            'address' => $address ? [
                'name' => $address->name,
                'project_name' => $address->project_name,
                'address1' => $address->address1,
                'city' => $address->city,
                'state_id' => $address->state_id,
                'state_name' => $address->state->state ?? '',
                'postcode' => $address->postcode,
            ] : null,
            'total_items' => $palletItems->count(),
            'subtotal' => $palletItems->sum(function($item) {
                return $item->quantity * ($item->variationColor->productVariation->pricing ?? 0);
            })
        ]);
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

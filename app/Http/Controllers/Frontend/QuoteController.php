<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SavedList;
use App\Models\SavedListItem;
use App\Models\UserProductPrice;
use App\Models\UserProduct;
use App\Models\UserService;
use App\Models\ProductVariationColor;
use App\Models\Customer;
use App\Services\PersistUserCatalogFromQuoteItemPayload;
use App\Services\Rbac\PermissionService;
use App\Support\QuotePdfPresenter;
use App\Support\Rbac\CurrentOrg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class QuoteController extends Controller
{
    /**
     * Display the quotes workspace (split-pane view)
     */
    public function index()
    {
        return view('user.quotes.index');
    }

    /**
     * Get estimates list for left panel (AJAX)
     */
    public function getEstimatesList(Request $request)
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'quotes' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 25,
                    'total' => 0,
                ],
                'total_amount' => '0.00',
                'error' => 'User not authenticated'
            ], 401);
        }
        
        $orgId = CurrentOrg::id($userId);
        $members = $this->canReadTeamQuotes($userId, $orgId);

        $query = Quote::visibleTo($userId, $orgId, $members)
            ->with(['savedList', 'customer', 'items'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('project_id') && $orgId !== null) {
            $query->whereIn('quotes.project_id', Project::visibleTo($userId, $orgId)->whereKey($request->input('project_id'))->select('projects.id'));
        }

        // Apply filters
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer') && $request->customer !== 'all') {
            $query->whereHas('savedList', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->customer . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $quotes = $query->paginate(25);

        $quotesArray = [];
        
        // Get items from pagination
        foreach ($quotes->items() as $quote) {
            try {
                // Ensure relationships are loaded
                if (!$quote->relationLoaded('savedList')) {
                    $quote->load('savedList');
                }
                if (!$quote->relationLoaded('items')) {
                    $quote->load('items');
                }
                
                $savedList = $quote->savedList;
                $customerName = optional($quote->customer)->company_name
                    ?? optional($savedList)->name
                    ?? 'N/A';
                
                // Calculate total safely
                $total = 0;
                if ($quote->items && $quote->items->count() > 0) {
                    $total = $quote->items->sum('subtotal');
                }
                
                $quotesArray[] = [
                    'id' => $quote->id,
                    'customer_name' => $customerName,
                    'project_name' => $quote->project_name ?? $quote->name ?? 'Untitled',
                    'total_amount' => number_format($total, 2),
                    'quote_number' => $quote->quote_number,
                    'date' => optional($quote->estimate_date)->format('M d, Y') ?? $quote->created_at->format('M d, Y'),
                    'status' => $quote->status,
                ];
            } catch (\Exception $e) {
                // Skip broken rows so the list still loads
            }
        }

        $totalAmount = Quote::visibleTo($userId, $orgId, $members)->get()->sum(function($q) {
            return $q->calculateTotal();
        });

        return response()->json([
            'quotes' => $quotesArray,
            'pagination' => [
                'current_page' => $quotes->currentPage(),
                'last_page' => $quotes->lastPage(),
                'per_page' => $quotes->perPage(),
                'total' => $quotes->total(),
            ],
            'total_amount' => number_format($totalAmount, 2),
        ]);
    }

    private function visibleProject(Project $project): Project
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        abort_if($orgId === null, 404);

        return Project::visibleTo($userId, $orgId)->whereKey($project->id)->firstOrFail();
    }

    private function creatableProject(Project $project): Project
    {
        $project = $this->visibleProject($project);
        $userId = (int) Auth::id();

        abort_unless(
            app(PermissionService::class)->checkPermission($userId, (int) $project->org_id, 'estimate_management', 'S', $project->id),
            403,
            'You do not have permission to create estimates in this project.'
        );

        return $project;
    }

    private function writableQuote($id, string $level, array $with = []): Quote
    {
        $userId = (int) Auth::id();
        $orgId = CurrentOrg::id($userId);
        $permissions = app(PermissionService::class);
        $members = $orgId !== null && $permissions->checkPermission($userId, $orgId, 'estimate_management', $level);

        $quote = Quote::with($with)->visibleTo($userId, $orgId, $members)->where('quotes.id', $id)->firstOrFail();

        abort_unless(
            $orgId !== null
                && $permissions->checkPermission($userId, $orgId, 'estimate_management', $level, $quote->project_id !== null ? (int) $quote->project_id : null),
            403,
            'You do not have permission to change this estimate.'
        );

        return $quote;
    }

    private function canReadTeamQuotes(int $userId, ?int $orgId): bool
    {
        return $orgId !== null
            && app(PermissionService::class)->checkPermission($userId, $orgId, 'estimate_management', 'R');
    }

    /**
     * Get estimate details for right panel (AJAX)
     */
    public function getEstimateDetails($id)
    {
        $orgId = CurrentOrg::id((int) Auth::id());
        $quote = Quote::with(['items.productVariationColor.productVariation.product', 'savedList', 'customer'])
            ->visibleTo(Auth::id(), $orgId, $this->canReadTeamQuotes(Auth::id(), $orgId))
            ->where('id', $id)
            ->firstOrFail();

        $items = $quote->items->map(function($item) {
            $productVariationColor = $item->productVariationColor ?? null;
            // Use stored description if set; otherwise build from product relation (backward compat)
            $description = $item->description;
            if (($description === null || $description === '') && $productVariationColor) {
                $description = $this->getProductDescriptionLine($productVariationColor);
            }
            return [
                'id' => $item->id,
                'sr_no' => $item->id,
                'type' => in_array((string) $item->item_type, ['product', 'service'], true) ? (string) $item->item_type : 'product',
                'description' => $description ?? 'N/A',
                'product_variation_color_id' => $item->product_variation_color_id,
                'quantity' => $item->quantity,
                'rate' => number_format($item->unit_price, 2),
                'amount' => number_format($item->subtotal, 2),
                'item_notes' => $item->item_notes ?? '',
            ];
        });

        $attachmentsForClient = [];
        foreach ($quote->attachments ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $path = isset($row['path']) ? (string) $row['path'] : '';
            if ($path === '') {
                continue;
            }
            $attachmentsForClient[] = [
                'name' => (isset($row['name']) && (string) $row['name'] !== '') ? (string) $row['name'] : basename($path),
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ];
        }

        return response()->json([
            'quote' => [
                'id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'quote_number' => $quote->quote_number,
                'customer_name' => optional($quote->customer)->company_name ?? optional($quote->savedList)->name ?? 'N/A',
                'estimate_label' => $quote->name ?? '',
                'project_name' => $quote->project_name ?? '',
                'total' => number_format($quote->calculateTotal(), 2),
                'date' => optional($quote->estimate_date)->format('M d, Y') ?? $quote->created_at->format('M d, Y'),
                'status' => $quote->status,
                'notes' => $quote->notes,
                'terms_and_conditions' => $quote->terms_and_conditions,
                'staff_notes' => (int) $quote->user_id === (int) Auth::id() ? $quote->staff_notes : null,
                'currency' => $quote->currency ?? 'USD',
                'shipping_cost' => $quote->shipping_cost !== null ? (string) $quote->shipping_cost : '0',
                'order_discount_raw' => $quote->order_discount_raw,
                'attachments' => $attachmentsForClient,
                'estimate_date' => optional($quote->estimate_date)->format('Y-m-d') ?? optional($quote->created_at)->format('Y-m-d'),
                'customer_address' => $quote->customer_address ?? '',
                'project_address' => $quote->project_address ?? '',
                'shipping_method' => $quote->shipping_method ?? '',
            ],
            'items' => $items,
            'subtotal' => number_format($quote->calculateTotal(), 2),
            'total' => number_format($quote->calculateTotal(), 2),
        ]);
    }

    /**
     * Create quote from saved list
     */
    public function createFromList(Project $project, $listId)
    {
        $project = $this->creatableProject($project);

        $list = SavedList::with('items')
            ->where('id', $listId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Create quote
        $quote = Quote::create([
            'user_id' => Auth::id(),
            'project_id' => $project->id,
            'saved_list_id' => $listId,
            'name' => $list->name,
            'project_name' => $list->project_name ?? $list->name,
            'quote_number' => Quote::generateQuoteNumber(),
            'status' => 'draft',
        ]);

        // Create quote items from list items (description stored so quote can be edited independently)
        foreach ($list->items as $listItem) {
            $productVariationColor = ProductVariationColor::with(['productVariation.product', 'productVariation.size', 'productVariation.thickness', 'productVariation.finish', 'productVariation.paint_type', 'color'])
                ->find($listItem->product_color_variation_id);

            if ($productVariationColor) {
                // Get price from user's saved prices or use default (used only for this quote; not written back)
                $unitPrice = UserProductPrice::getPriceForUser(
                    Auth::id(),
                    $productVariationColor->id
                ) ?? $productVariationColor->productVariation->pricing ?? 0;

                $description = $this->getProductDescriptionLine($productVariationColor);

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_variation_color_id' => $productVariationColor->id,
                    'description' => $description,
                    'item_type' => 'product',
                    'quantity' => $listItem->quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $listItem->quantity * $unitPrice,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Quote created successfully',
            'quote_id' => $quote->id,
        ]);
    }

    /**
     * Store a new quote
     */
    public function store(Request $request, Project $project)
    {
        $project = $this->creatableProject($project);

        $request->validate([
            'saved_list_id' => 'nullable|exists:saved_lists,id',
            'customer_id' => 'required|exists:customers,id',
            'estimate_label' => 'nullable|string|max:255',
            'project_name' => 'nullable|string|max:255',
            'project_address' => 'nullable|string|max:65535',
            'customer_address' => 'nullable|string|max:65535',
            'currency' => 'nullable|string|max:10',
            'estimate_date' => 'nullable|date',
            'shipping_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'staff_notes' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'order_discount_raw' => 'nullable|string|max:64',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:15360',
            'items' => 'nullable|array',
            'items.*.type' => 'nullable|in:product,service',
            'items.*.product_variation_color_id' => 'nullable|exists:product_variation_color,id',
            'items.*.description' => 'nullable|string|max:65535',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:65535',
        ]);

        $customer = Customer::where('id', $request->customer_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $list = null;
        if ($request->filled('saved_list_id')) {
            $list = SavedList::where('id', $request->saved_list_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
        }

        $estimateLabel = $request->estimate_label
            ?? $request->project_name
            ?? $list?->project_name
            ?? $list?->name
            ?? 'Estimate';

        $subtitle = trim((string) $request->input('project_name', ''));
        $projectName = $subtitle !== '' ? $subtitle : $estimateLabel;

        $quote = Quote::create([
            'user_id' => Auth::id(),
            'project_id' => $project->id,
            'saved_list_id' => $request->saved_list_id,
            'customer_id' => $customer->id,
            'name' => $estimateLabel,
            'project_name' => $projectName,
            'project_address' => $request->input('project_address', ''),
            'customer_address' => $request->input('customer_address', ''),
            'quote_number' => Quote::generateQuoteNumber(),
            'currency' => $request->currency ?: 'USD',
            'estimate_date' => $request->estimate_date ?: now()->toDateString(),
            'shipping_method' => $request->shipping_method,
            'notes' => $request->notes,
            'terms_and_conditions' => $request->terms_and_conditions,
            'staff_notes' => $request->staff_notes,
            'shipping_cost' => $request->input('shipping_cost', 0),
            'order_discount_raw' => $request->order_discount_raw,
            'attachments' => [],
            'status' => 'draft',
        ]);

        if ($request->hasFile('attachments')) {
            $uploaded = [];
            foreach ($request->file('attachments', []) as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                $uploaded[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store('quotes/' . $quote->id, 'public'),
                ];
            }
            if ($uploaded !== []) {
                $quote->forceFill(['attachments' => $uploaded])->save();
            }
        }

        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as $item) {
                $productVariationColorId = isset($item['product_variation_color_id']) && $item['product_variation_color_id'] !== ''
                    ? (int)$item['product_variation_color_id']
                    : null;
                $description = trim((string)($item['description'] ?? ''));
                $itemType = (($item['type'] ?? 'product') === 'service') ? 'service' : 'product';
                $quantity = (int)($item['quantity'] ?? 1);
                $rate = (float)($item['rate'] ?? 0);
                $notes = $item['notes'] ?? null;

                if ($description === '' && $quantity <= 0 && $rate <= 0) {
                    continue;
                }

                $quantity = max(1, $quantity);
                $subtotal = $quantity * $rate;

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_variation_color_id' => $productVariationColorId,
                    'description' => $description !== '' ? $description : 'Product',
                    'item_type' => $itemType,
                    'quantity' => $quantity,
                    'unit_price' => $rate,
                    'subtotal' => $subtotal,
                    'item_notes' => $notes,
                ]);
            }
        }

        app(PersistUserCatalogFromQuoteItemPayload::class)->sync((int) Auth::id(), is_array($items) ? $items : []);

        return response()->json([
            'success' => true,
            'message' => 'Quote created successfully',
            'quote_id' => $quote->id,
        ]);
    }

    /**
     * Update quote
     */
    public function update(Request $request, $id)
    {
        $quote = $this->writableQuote($id, 'O');

        $request->validate([
            'project_name' => 'nullable|string|max:255',
            'project_address' => 'nullable|string|max:65535',
            'customer_address' => 'nullable|string',
            'currency' => 'nullable|string|max:10',
            'estimate_date' => 'nullable|date',
            'shipping_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'staff_notes' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'order_discount_raw' => 'nullable|string|max:64',
            'status' => 'nullable|in:draft,completed,sent',
        ]);

        $quote->update($request->only([
            'project_name',
            'project_address',
            'customer_address',
            'currency',
            'estimate_date',
            'shipping_method',
            'notes',
            'terms_and_conditions',
            'staff_notes',
            'shipping_cost',
            'order_discount_raw',
            'status',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Quote updated successfully',
        ]);
    }

    /**
     * Update quote item
     */
    public function updateItem(Request $request, $quoteId, $itemId)
    {
        $quote = $this->writableQuote($quoteId, 'O');

        $item = QuoteItem::where('id', $itemId)
            ->where('quote_id', $quote->id)
            ->firstOrFail();

        $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:65535',
            'item_notes' => 'nullable|string|max:65535',
        ]);

        if ($request->has('quantity')) {
            $item->quantity = (int) $request->quantity;
        }

        if ($request->has('unit_price')) {
            $item->unit_price = $request->unit_price;
            // Do not save to UserProductPrice – each quote is independent
        }

        if ($request->has('description')) {
            $item->description = $request->description;
        }

        if ($request->has('item_notes')) {
            $item->item_notes = $request->item_notes;
        }

        $item->calculateSubtotal();
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'subtotal' => number_format($item->subtotal, 2),
            'total' => number_format($quote->fresh()->calculateTotal(), 2),
        ]);
    }

    /**
     * Delete quote item
     */
    public function destroyItem($quoteId, $itemId)
    {
        try {
            $quote = $this->writableQuote($quoteId, 'F');

            $item = QuoteItem::where('id', $itemId)
                ->where('quote_id', $quoteId)
                ->firstOrFail();

            $item->delete();

            $totalItems = QuoteItem::where('quote_id', $quoteId)->count();
            $total = $quote->fresh()->calculateTotal();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from quote successfully',
                'total_items' => $totalItems,
                'total' => number_format($total, 2),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Quote item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete quote
     */
    public function destroy($id)
    {
        $quote = $this->writableQuote($id, 'F');

        // Delete PDF if exists
        if ($quote->pdf_path && Storage::disk('public')->exists($quote->pdf_path)) {
            Storage::disk('public')->delete($quote->pdf_path);
        }

        $quote->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quote deleted successfully',
        ]);
    }

    /**
     * Duplicate quote
     */
    public function duplicate($id)
    {
        $originalQuote = $this->writableQuote($id, 'O', ['items']);
        abort_if($originalQuote->project_id === null, 422, 'This estimate does not belong to a project yet.');

        $newQuote = Quote::create([
            'user_id' => Auth::id(),
            'project_id' => $originalQuote->project_id,
            'saved_list_id' => $originalQuote->saved_list_id,
            'customer_id' => $originalQuote->customer_id,
            'name' => $originalQuote->name . ' (Copy)',
            'project_name' => $originalQuote->project_name,
            'project_address' => $originalQuote->project_address,
            'customer_address' => $originalQuote->customer_address,
            'quote_number' => Quote::generateQuoteNumber(),
            'currency' => $originalQuote->currency ?? 'USD',
            'estimate_date' => $originalQuote->estimate_date,
            'shipping_method' => $originalQuote->shipping_method,
            'status' => 'draft',
            'notes' => $originalQuote->notes,
            'terms_and_conditions' => $originalQuote->terms_and_conditions,
            'staff_notes' => $originalQuote->staff_notes,
            'shipping_cost' => $originalQuote->shipping_cost ?? 0,
            'order_discount_raw' => $originalQuote->order_discount_raw,
            'attachments' => [],
        ]);

        foreach ($originalQuote->items as $item) {
            QuoteItem::create([
                'quote_id' => $newQuote->id,
                'product_variation_color_id' => $item->product_variation_color_id,
                'description' => $item->description,
                'item_type' => in_array((string) $item->item_type, ['product', 'service'], true) ? (string) $item->item_type : 'product',
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'item_notes' => $item->item_notes,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Quote duplicated successfully',
            'quote_id' => $newQuote->id,
        ]);
    }

    public function saveEditor(Request $request, $id)
    {
        $quote = $this->writableQuote($id, 'O');

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'estimate_label' => 'nullable|string|max:255',
            'project_name' => 'nullable|string|max:255',
            'project_address' => 'nullable|string|max:65535',
            'customer_address' => 'nullable|string|max:65535',
            'currency' => 'nullable|string|max:10',
            'estimate_date' => 'nullable|date',
            'shipping_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'staff_notes' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'order_discount_raw' => 'nullable|string|max:64',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:15360',
            'items' => 'nullable|array',
            'items.*.type' => 'nullable|in:product,service',
            'items.*.product_variation_color_id' => 'nullable|exists:product_variation_color,id',
            'items.*.description' => 'nullable|string|max:65535',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:65535',
        ]);

        $customer = Customer::where('id', $request->customer_id)
            ->where(function ($q) use ($quote) {
                $q->where('user_id', Auth::id());
                if ($quote->customer_id !== null) {
                    $q->orWhere('id', $quote->customer_id);
                }
            })
            ->firstOrFail();

        $estimateLabel = $request->estimate_label
            ?? $request->project_name
            ?? $quote->project_name
            ?? 'Estimate';

        $quote->update([
            'customer_id' => $customer->id,
            'name' => $estimateLabel,
            'project_name' => $request->project_name ?: $estimateLabel,
            'project_address' => $request->project_address,
            'customer_address' => $request->customer_address,
            'currency' => $request->currency ?: 'USD',
            'estimate_date' => $request->estimate_date ?: now()->toDateString(),
            'shipping_method' => $request->shipping_method,
            'notes' => $request->notes,
            'terms_and_conditions' => $request->terms_and_conditions,
            'staff_notes' => $request->staff_notes,
            'shipping_cost' => $request->input('shipping_cost', 0),
            'order_discount_raw' => $request->order_discount_raw,
        ]);

        if ($request->hasFile('attachments')) {
            $quote->refresh();
            $attachmentList = is_array($quote->attachments) ? array_values($quote->attachments) : [];
            foreach ($request->file('attachments', []) as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                $attachmentList[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store('quotes/' . $quote->id, 'public'),
                ];
            }
            $quote->forceFill(['attachments' => $attachmentList])->save();
        }

        QuoteItem::where('quote_id', $quote->id)->delete();
        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as $item) {
                $productVariationColorId = isset($item['product_variation_color_id']) && $item['product_variation_color_id'] !== ''
                    ? (int)$item['product_variation_color_id']
                    : null;
                $description = trim((string)($item['description'] ?? ''));
                $itemType = (($item['type'] ?? 'product') === 'service') ? 'service' : 'product';
                $quantity = (int)($item['quantity'] ?? 1);
                $rate = (float)($item['rate'] ?? 0);
                $notes = $item['notes'] ?? null;
                if ($description === '' && $quantity <= 0 && $rate <= 0) {
                    continue;
                }
                $quantity = max(1, $quantity);
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_variation_color_id' => $productVariationColorId,
                    'description' => $description !== '' ? $description : 'Product',
                    'item_type' => $itemType,
                    'quantity' => $quantity,
                    'unit_price' => $rate,
                    'subtotal' => $quantity * $rate,
                    'item_notes' => $notes,
                ]);
            }
        }

        app(PersistUserCatalogFromQuoteItemPayload::class)->sync((int) Auth::id(), is_array($items) ? $items : []);

        return response()->json([
            'success' => true,
            'message' => 'Estimate updated successfully',
            'quote_id' => $quote->id,
        ]);
    }

    public function getCustomersForEstimate()
    {
        $customers = Customer::query()
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderByRaw('COALESCE(NULLIF(company_name, \'\'), NULLIF(last_name, \'\'), NULLIF(first_name, \'\'), \'\') asc')
            ->get()
            ->map(function (Customer $customer) {
                $billingAddress = trim(implode("\n", array_filter([
                    $customer->billing_street_1,
                    $customer->billing_street_2,
                    trim(implode(', ', array_filter([$customer->billing_city, $customer->billing_state]))),
                    trim(implode(' ', array_filter([$customer->billing_zip, $customer->billing_country]))),
                ])));

                $shippingAddress = trim(implode("\n", array_filter([
                    $customer->shipping_street_1,
                    $customer->shipping_street_2,
                    trim(implode(', ', array_filter([$customer->shipping_city, $customer->shipping_state]))),
                    trim(implode(' ', array_filter([$customer->shipping_zip, $customer->shipping_country]))),
                ])));

                $addresses = [];
                if ($billingAddress !== '') {
                    $addresses[] = [
                        'type' => 'billing',
                        'label' => 'Main Address',
                        'value' => $billingAddress,
                    ];
                }
                if (!$customer->shipping_same_as_billing && $shippingAddress !== '') {
                    $addresses[] = [
                        'type' => 'shipping',
                        'label' => 'Shipping Address',
                        'value' => $shippingAddress,
                    ];
                }

                return [
                    'id' => $customer->id,
                    'name' => $customer->company_name ?: trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                    'addresses' => $addresses,
                    'billing_street' => (string) ($customer->billing_street_1 ?? ''),
                    'billing_city' => (string) ($customer->billing_city ?? ''),
                    'billing_state' => (string) ($customer->billing_state ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'customers' => $customers,
        ]);
    }

    public function getProductVariationsForEstimate(Request $request)
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['items' => []]);
        }

        $search = trim((string) $request->get('q', ''));

        $query = UserProduct::query()
            ->where('user_id', $userId)
            ->where('is_archived', false)
            ->with([
                'productVariationColor.productVariation.product',
                'productVariationColor.productVariation.size',
                'productVariationColor.productVariation.thickness',
                'productVariationColor.productVariation.finish',
                'productVariationColor.productVariation.paint_type',
                'productVariationColor.color',
            ]);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('productVariationColor.productVariation.product', function ($qq) use ($like) {
                        $qq->where('name', 'like', $like);
                    })
                    ->orWhereHas('productVariationColor.color', function ($qq) use ($like) {
                        $qq->where('name', 'like', $like);
                    });
            });
        }

        $rows = $query->orderByDesc('updated_at')
            ->limit(150)
            ->get()
            ->map(function (UserProduct $product) {
                $pvc = $product->productVariationColor;
                $desc = trim((string) ($product->description ?? ''));
                if ($desc === '' && $pvc) {
                    $desc = $this->getProductDescriptionLine($pvc);
                }
                if ($desc === '') {
                    $desc = trim((string) ($product->name ?? 'Product'));
                }

                $rate = (float) ($product->sell_price ?? $product->default_unit_price ?? 0);
                if ($rate <= 0 && $pvc) {
                    $rate = (float) ($pvc->productVariation->pricing ?? 0);
                }

                return [
                    'id' => $product->product_variation_color_id ? (int) $product->product_variation_color_id : null,
                    'text' => $desc,
                    'description' => $desc,
                    'rate' => number_format($rate, 2, '.', ''),
                ];
            })
            ->values();

        return response()->json([
            'items' => $rows,
        ]);
    }

    public function getServicesForEstimate(Request $request)
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['items' => []]);
        }

        $search = trim((string) $request->get('q', ''));

        $query = UserService::query()
            ->forUser($userId)
            ->orderByDesc('updated_at');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('service_name', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('variant_size', 'like', $like);
            });
        }

        $rows = $query->limit(100)->get()->map(function (UserService $service) {
            $title = trim((string) ($service->service_name ?: $service->title ?: 'Service'));
            $desc = trim((string) ($service->description ?? ''));
            $full = $desc !== '' ? ($title.' – '.$desc) : $title;
            return [
                'id' => $service->id,
                'title' => $title,
                'description' => $desc,
                'text' => $full,
                'rate' => number_format((float) ($service->default_unit_price ?? 0), 2, '.', ''),
                'unit_type' => (string) ($service->unit_type ?? ''),
            ];
        })->values();

        return response()->json([
            'items' => $rows,
        ]);
    }

    /**
     * Generate PDF
     */
    public function generatePDF($id)
    {
        $orgId = CurrentOrg::id((int) Auth::id());
        $quote = Quote::with(['items.productVariationColor.productVariation.product', 'customer'])
            ->visibleTo(Auth::id(), $orgId, $this->canReadTeamQuotes(Auth::id(), $orgId))
            ->where('id', $id)
            ->firstOrFail();

        $pdf = PDF::loadView('frontend.quotes.pdf-template', QuotePdfPresenter::present($quote));
        
        if ((int) $quote->user_id === (int) Auth::id()) {
            $pdfPath = 'quotes/' . $quote->quote_number . '.pdf';
            Storage::disk('public')->put($pdfPath, $pdf->output());

            $quote->update(['pdf_path' => $pdfPath]);
        }

        return $pdf->download($quote->quote_number . '.pdf');
    }

    /**
     * Preview PDF inline in browser/modal iframe.
     */
    public function previewPDF($id)
    {
        $orgId = CurrentOrg::id((int) Auth::id());
        $quote = Quote::with(['items.productVariationColor.productVariation.product', 'customer'])
            ->visibleTo(Auth::id(), $orgId, $this->canReadTeamQuotes(Auth::id(), $orgId))
            ->where('id', $id)
            ->firstOrFail();

        $pdf = PDF::loadView('frontend.quotes.pdf-template', QuotePdfPresenter::present($quote));

        return $pdf->stream($quote->quote_number . '.pdf');
    }

    /**
     * Get product details as array (for backward compatibility)
     */
    private function getProductDetails($productVariationColor)
    {
        $details = [];
        $variation = $productVariationColor->productVariation ?? null;
        if ($variation) {
            if ($variation->size) $details[] = 'Size: ' . $variation->size->name;
            if ($variation->thickness) $details[] = 'Thickness: ' . $variation->thickness->name;
            if ($variation->finish) $details[] = 'Finish: ' . $variation->finish->name;
            if ($variation->paint_type) $details[] = 'Paint Type: ' . $variation->paint_type->name;
        }
        if ($productVariationColor->color) {
            $details[] = 'Color: ' . $productVariationColor->color->name;
        }
        return $details;
    }

    /**
     * Build a single description line (product name + specs) for storing on quote item
     */
    private function getProductDescriptionLine($productVariationColor)
    {
        $parts = [];
        $product = $productVariationColor->productVariation->product ?? null;
        if ($product) {
            $parts[] = $product->name;
        }
        $details = $this->getProductDetails($productVariationColor);
        if (!empty($details)) {
            $parts[] = implode(', ', $details);
        }
        return implode(' – ', $parts) ?: 'N/A';
    }
}

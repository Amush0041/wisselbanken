<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\UserProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class UserProductController extends Controller
{
    private const PRODUCT_RULES = [
        'name' => ['required', 'string', 'max:512'],
        'sku' => ['nullable', 'string', 'max:255'],
        'variant_size' => ['nullable', 'string', 'max:255'],
        'category' => ['nullable', 'string', 'max:255'],
        'quantity' => ['nullable', 'numeric', 'min:0'],
        'unit_type' => ['nullable', 'string', 'max:100'],
        'buy_price' => ['nullable', 'numeric', 'min:0'],
        'buy_price_tax' => ['nullable', 'numeric', 'min:0'],
        'sell_price' => ['nullable', 'numeric', 'min:0'],
        'sell_price_tax' => ['nullable', 'numeric', 'min:0'],
        'currency' => ['nullable', 'string', 'max:10'],
        'stock' => ['nullable', 'numeric', 'min:0'],
        'inventory_enabled' => ['nullable', 'boolean'],
        'on_hand_stock' => ['nullable', 'numeric', 'min:0'],
        'committed_stock' => ['nullable', 'numeric', 'min:0'],
        'available_for_sale' => ['nullable', 'numeric', 'min:0'],
        'to_be_invoiced' => ['nullable', 'numeric', 'min:0'],
        'to_be_billed' => ['nullable', 'numeric', 'min:0'],
        'image_url' => ['nullable', 'string', 'max:1024'],
        'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        'description' => ['nullable', 'string'],
        'item_notes' => ['nullable', 'string'],
        'parent_product_id' => ['nullable', 'integer'],
    ];

    public function index()
    {
        return view('user.user-products.index');
    }

    public function list(Request $request)
    {
        $userId = (int) Auth::id();

        $q = UserProduct::query()
            ->forUser($userId)
            ->where('is_archived', false)
            ->with(['productVariationColor.productVariation.product', 'productVariationColor.color']);

        if ($request->filled('search')) {
            $s = $request->get('search');
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', '%'.$s.'%')
                    ->orWhere('description', 'like', '%'.$s.'%');
            });
        }

        $q->orderByDesc('updated_at');

        $page = $q->paginate(25);

        $items = collect($page->items())->map(function (UserProduct $p) {
            $pvc = $p->productVariationColor;
            $catalogHint = '';
            if ($pvc) {
                $prod = optional(optional($pvc->productVariation)->product);
                $color = optional($pvc->color)->name ?? '';
                $catalogHint = trim(($prod->name ?? 'Product').($color !== '' ? ' — '.$color : ''));
            }

            return [
                'id' => $p->id,
                'name' => $p->name ?? '',
                'sell_price' => (string) ($p->sell_price ?? $p->default_unit_price ?? 0),
                'category' => (string) ($p->category ?? ''),
                'variant_size' => (string) ($p->variant_size ?? ''),
                'catalog_hint' => $catalogHint,
                'currency' => (string) ($p->currency ?? ''),
                'item_notes' => (string) ($p->item_notes ?? ''),
                'image_url' => (string) ($p->image_url ?? ''),
                'updated_at' => optional($p->updated_at)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'products' => $items,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $userId = (int) Auth::id();
        $p = UserProduct::query()
            ->forUser($userId)
            ->with(['productVariationColor.productVariation.product', 'productVariationColor.color'])
            ->whereKey($id)
            ->firstOrFail();

        $pvc = $p->productVariationColor;

        return response()->json([
            'product' => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'variant_size' => $p->variant_size,
                'parent_product_id' => $p->parent_product_id,
                'category' => $p->category,
                'quantity' => (string) ($p->quantity ?? 1),
                'unit_type' => $p->unit_type,
                'description' => $p->description,
                'default_unit_price' => (string) $p->default_unit_price,
                'buy_price' => (string) ($p->buy_price ?? 0),
                'buy_price_tax' => (string) ($p->buy_price_tax ?? 0),
                'sell_price' => (string) ($p->sell_price ?? $p->default_unit_price ?? 0),
                'sell_price_tax' => (string) ($p->sell_price_tax ?? 0),
                'currency' => (string) ($p->currency ?? 'PKR'),
                'stock' => (string) ($p->stock ?? 0),
                'inventory_enabled' => (bool) $p->inventory_enabled,
                'on_hand_stock' => (string) ($p->on_hand_stock ?? 0),
                'committed_stock' => (string) ($p->committed_stock ?? 0),
                'available_for_sale' => (string) ($p->available_for_sale ?? 0),
                'to_be_invoiced' => (string) ($p->to_be_invoiced ?? 0),
                'to_be_billed' => (string) ($p->to_be_billed ?? 0),
                'image_url' => $p->image_url,
                'item_notes' => $p->item_notes,
                'product_variation_color_id' => $p->product_variation_color_id,
                'catalog_label' => $pvc ? $this->catalogLabel($pvc) : '',
                'is_archived' => (bool) $p->is_archived,
                'updated_at' => optional($p->updated_at)->toDateTimeString(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $userId = (int) Auth::id();
        $this->prepareProductRequest($request);
        $validated = $request->validate(self::PRODUCT_RULES);
        $payload = $this->normalizePayload($validated);
        $payload['parent_product_id'] = $this->resolveParentProductId($userId, $payload['parent_product_id'] ?? null);
        $uploadedImageUrl = $this->handleUploadedImage($request, null);
        if ($uploadedImageUrl !== null) {
            $payload['image_url'] = $uploadedImageUrl;
        }
        $payload['user_id'] = $userId;

        $product = UserProduct::query()->create($payload);

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
        ]);
    }

    public function update(Request $request, $id)
    {
        $userId = (int) Auth::id();
        $product = UserProduct::query()->forUser($userId)->whereKey($id)->firstOrFail();
        $this->prepareProductRequest($request);
        $validated = $request->validate(self::PRODUCT_RULES);
        $payload = $this->normalizePayload($validated);
        $payload['parent_product_id'] = $this->resolveParentProductId($userId, $payload['parent_product_id'] ?? null);
        $uploadedImageUrl = $this->handleUploadedImage($request, $product);
        if ($uploadedImageUrl !== null) {
            $payload['image_url'] = $uploadedImageUrl;
        }
        $product->fill($payload)->save();

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy($id)
    {
        $userId = (int) Auth::id();
        $deleted = UserProduct::query()->forUser($userId)->whereKey($id)->delete();

        return response()->json([
            'success' => (bool) $deleted,
        ]);
    }

    public function duplicate($id)
    {
        $userId = (int) Auth::id();
        $product = UserProduct::query()->forUser($userId)->whereKey($id)->firstOrFail();

        $duplicate = $product->replicate();
        $duplicate->name = trim((string) $product->name) !== '' ? ($product->name.' (Copy)') : 'Product (Copy)';
        $duplicate->is_archived = false;
        $duplicate->archived_at = null;
        $duplicate->push();

        return response()->json([
            'success' => true,
            'product_id' => $duplicate->id,
        ]);
    }

    public function archive($id)
    {
        $userId = (int) Auth::id();
        $product = UserProduct::query()->forUser($userId)->whereKey($id)->firstOrFail();
        $product->forceFill([
            'is_archived' => true,
            'archived_at' => Carbon::now(),
        ])->save();

        return response()->json([
            'success' => true,
        ]);
    }

    public function addVariation(Request $request, $id)
    {
        $userId = (int) Auth::id();
        $product = UserProduct::query()->forUser($userId)->whereKey($id)->firstOrFail();
        $validated = $request->validate([
            'variant_size' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_type' => ['nullable', 'string', 'max:100'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'inventory_enabled' => ['nullable', 'boolean'],
        ]);

        $variation = $product->replicate();
        $variation->parent_product_id = $product->id;
        $variation->variant_size = trim((string) $validated['variant_size']);
        $variation->sku = isset($validated['sku']) ? trim((string) $validated['sku']) : $variation->sku;
        if (array_key_exists('quantity', $validated)) {
            $variation->quantity = (float) $validated['quantity'];
        }
        if (array_key_exists('unit_type', $validated)) {
            $variation->unit_type = trim((string) $validated['unit_type']);
        }
        if (array_key_exists('sell_price', $validated)) {
            $sellPrice = (float) $validated['sell_price'];
            $variation->sell_price = $sellPrice;
            $variation->default_unit_price = $sellPrice;
        }
        if (array_key_exists('currency', $validated)) {
            $currencyRaw = trim((string) $validated['currency']);
            if ($currencyRaw !== '') {
                $parts = preg_split('/[\s-]+/', $currencyRaw);
                $first = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($parts[0] ?? '')));
                $variation->currency = substr($first !== '' ? $first : strtoupper($currencyRaw), 0, 10);
            } else {
                $variation->currency = null;
            }
        }
        if (array_key_exists('inventory_enabled', $validated)) {
            $variation->inventory_enabled = (bool) $validated['inventory_enabled'];
        }
        $variation->name = trim((string) $product->name) !== '' ? $product->name : 'Product';
        $variation->is_archived = false;
        $variation->archived_at = null;
        $variation->push();

        return response()->json([
            'success' => true,
            'product_id' => $variation->id,
        ]);
    }

    private function catalogLabel($pvc): string
    {
        $prod = optional(optional($pvc->productVariation)->product);
        $color = optional($pvc->color)->name ?? '';

        return trim(($prod->name ?? 'Product').($color !== '' ? ' — '.$color : ''));
    }

    private function prepareProductRequest(Request $request): void
    {
        if ($request->has('parent_product_id') && $request->input('parent_product_id') === '') {
            $request->merge(['parent_product_id' => null]);
        }

        if ($request->has('inventory_enabled')) {
            $raw = $request->input('inventory_enabled');
            $request->merge([
                'inventory_enabled' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    private function normalizePayload(array $validated): array
    {
        $toStringOrNull = static fn ($v): ?string => isset($v) ? trim((string) $v) : null;
        $toDecimal = static fn ($v, $fallback = 0): float => is_numeric($v) ? (float) $v : (float) $fallback;

        $sellPrice = $toDecimal($validated['sell_price'] ?? null, $validated['default_unit_price'] ?? 0);

        $currencyRaw = $toStringOrNull($validated['currency'] ?? null);
        $currency = null;
        if ($currencyRaw !== null && $currencyRaw !== '') {
            $parts = preg_split('/[\s-]+/', $currencyRaw);
            $first = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($parts[0] ?? '')));
            $currency = substr($first !== '' ? $first : strtoupper($currencyRaw), 0, 10);
        }

        return [
            'name' => $toStringOrNull($validated['name']) ?? 'Product',
            'sku' => $toStringOrNull($validated['sku'] ?? null),
            'variant_size' => $toStringOrNull($validated['variant_size'] ?? null),
            'parent_product_id' => isset($validated['parent_product_id']) ? (int) $validated['parent_product_id'] : null,
            'category' => $toStringOrNull($validated['category'] ?? null),
            'quantity' => $toDecimal($validated['quantity'] ?? null, 1),
            'unit_type' => $toStringOrNull($validated['unit_type'] ?? null),
            'description' => $toStringOrNull($validated['description'] ?? null),
            'default_unit_price' => $sellPrice,
            'buy_price' => $toDecimal($validated['buy_price'] ?? null, 0),
            'buy_price_tax' => $toDecimal($validated['buy_price_tax'] ?? null, 0),
            'sell_price' => $sellPrice,
            'sell_price_tax' => $toDecimal($validated['sell_price_tax'] ?? null, 0),
            'currency' => $currency,
            'stock' => $toDecimal($validated['stock'] ?? null, 0),
            'inventory_enabled' => isset($validated['inventory_enabled']) ? (bool) $validated['inventory_enabled'] : true,
            'on_hand_stock' => $toDecimal($validated['on_hand_stock'] ?? null, 0),
            'committed_stock' => $toDecimal($validated['committed_stock'] ?? null, 0),
            'available_for_sale' => $toDecimal($validated['available_for_sale'] ?? null, 0),
            'to_be_invoiced' => $toDecimal($validated['to_be_invoiced'] ?? null, 0),
            'to_be_billed' => $toDecimal($validated['to_be_billed'] ?? null, 0),
            'image_url' => $toStringOrNull($validated['image_url'] ?? null),
            'item_notes' => $toStringOrNull($validated['item_notes'] ?? null),
        ];
    }

    private function handleUploadedImage(Request $request, ?UserProduct $existing): ?string
    {
        $file = $request->file('image_file');
        if (! $file) {
            return null;
        }

        $path = $file->store('user-products', 'public');
        $publicUrl = asset('storage/'.$path);

        if ($existing && $existing->image_url) {
            $parsed = parse_url((string) $existing->image_url, PHP_URL_PATH);
            $prefix = '/storage/';
            if (is_string($parsed) && Str::startsWith($parsed, $prefix)) {
                $oldStoragePath = ltrim(Str::after($parsed, $prefix), '/');
                if ($oldStoragePath !== '') {
                    Storage::disk('public')->delete($oldStoragePath);
                }
            }
        }

        return $publicUrl;
    }

    private function resolveParentProductId(int $userId, $rawParentId): ?int
    {
        $parentId = is_numeric($rawParentId) ? (int) $rawParentId : null;
        if (! $parentId) {
            return null;
        }

        $exists = UserProduct::query()
            ->forUser($userId)
            ->whereKey($parentId)
            ->exists();

        return $exists ? $parentId : null;
    }
}

<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;

class UserSavedServiceController extends Controller
{
    private const SERVICE_RULES = [
        'service_name' => ['required', 'string', 'max:512'],
        'sku' => ['nullable', 'string', 'max:255'],
        'variant_size' => ['nullable', 'string', 'max:255'],
        'quantity' => ['nullable', 'numeric', 'min:0'],
        'unit_type' => ['nullable', 'string', 'max:100'],
        'default_unit_price' => ['nullable', 'numeric', 'min:0'],
        'tax_label' => ['nullable', 'string', 'max:255'],
        'inventory_enabled' => ['nullable', 'boolean'],
        'description' => ['nullable', 'string'],
        'item_notes' => ['nullable', 'string'],
    ];

    public function index()
    {
        $this->requireOrgLevel('product_management', 'R');
        return view('user.user-services.index');
    }

    public function list(Request $request)
    {
        $this->requireOrgLevel('product_management', 'R');
        $userId = (int) Auth::id();

        $q = UserService::query()->forUser($userId);

        if ($request->filled('search')) {
            $s = $request->get('search');
            $q->where(function ($qq) use ($s) {
                $qq->where('title', 'like', '%'.$s.'%')
                    ->orWhere('description', 'like', '%'.$s.'%');
            });
        }

        $q->orderByDesc('updated_at');

        $page = $q->paginate(25);

        $items = collect($page->items())->map(function (UserService $s) {
            return [
                'id' => $s->id,
                'title' => $s->service_name ?: $s->title,
                'default_unit_price' => (string) $s->default_unit_price,
                'updated_at' => optional($s->updated_at)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'services' => $items,
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
        $this->requireOrgLevel('product_management', 'R');
        $userId = (int) Auth::id();
        $s = UserService::query()->forUser($userId)->whereKey($id)->firstOrFail();

        return response()->json([
            'service' => [
                'id' => $s->id,
                'title' => $s->title,
                'service_name' => $s->service_name ?: $s->title,
                'sku' => $s->sku,
                'variant_size' => $s->variant_size,
                'quantity' => (string) ($s->quantity ?? 1),
                'unit_type' => $s->unit_type,
                'tax_label' => $s->tax_label,
                'inventory_enabled' => (bool) $s->inventory_enabled,
                'description' => $s->description,
                'default_unit_price' => (string) $s->default_unit_price,
                'item_notes' => $s->item_notes,
                'updated_at' => optional($s->updated_at)->toDateTimeString(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->requireOrgLevel('product_management', 'S');
        $userId = (int) Auth::id();
        $payload = $this->normalizePayload($request->validate(self::SERVICE_RULES));
        $payload['content_hash'] = $this->buildUniqueContentHash($userId, $payload, null);
        $payload['user_id'] = $userId;

        $service = UserService::query()->create($payload);

        return response()->json([
            'success' => true,
            'service_id' => $service->id,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->requireOrgLevel('product_management', 'O');
        $userId = (int) Auth::id();
        $service = UserService::query()->forUser($userId)->whereKey($id)->firstOrFail();
        $payload = $this->normalizePayload($request->validate(self::SERVICE_RULES));
        $payload['content_hash'] = $this->buildUniqueContentHash($userId, $payload, (int) $service->id);
        $service->fill($payload)->save();

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy($id)
    {
        $this->requireOrgLevel('product_management', 'F');
        $userId = (int) Auth::id();
        $deleted = UserService::query()->forUser($userId)->whereKey($id)->delete();

        return response()->json([
            'success' => (bool) $deleted,
        ]);
    }

    private function normalizePayload(array $validated): array
    {
        $serviceName = trim((string) ($validated['service_name'] ?? ''));
        $rate = is_numeric($validated['default_unit_price'] ?? null) ? (float) $validated['default_unit_price'] : 0.0;

        return [
            'title' => $serviceName !== '' ? $serviceName : 'Service',
            'service_name' => $serviceName !== '' ? $serviceName : 'Service',
            'sku' => isset($validated['sku']) ? trim((string) $validated['sku']) : null,
            'variant_size' => isset($validated['variant_size']) ? trim((string) $validated['variant_size']) : null,
            'quantity' => is_numeric($validated['quantity'] ?? null) ? (float) $validated['quantity'] : 1.0,
            'unit_type' => isset($validated['unit_type']) ? trim((string) $validated['unit_type']) : null,
            'tax_label' => isset($validated['tax_label']) ? trim((string) $validated['tax_label']) : null,
            'inventory_enabled' => isset($validated['inventory_enabled']) ? (bool) $validated['inventory_enabled'] : true,
            'default_unit_price' => $rate,
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'item_notes' => isset($validated['item_notes']) ? trim((string) $validated['item_notes']) : null,
        ];
    }

    private function buildUniqueContentHash(int $userId, array $payload, ?int $ignoreId): string
    {
        $serviceName = trim((string) ($payload['service_name'] ?? $payload['title'] ?? 'Service'));
        $description = trim((string) ($payload['description'] ?? ''));
        $sku = trim((string) ($payload['sku'] ?? ''));
        $variantSize = trim((string) ($payload['variant_size'] ?? ''));
        $base = hash('sha256', $userId."\n".$serviceName."\n".$description."\n".$sku."\n".$variantSize);
        $hash = $base;
        $i = 0;

        while (true) {
            $exists = UserService::query()
                ->forUser($userId)
                ->where('content_hash', $hash)
                ->when($ignoreId, function ($q) use ($ignoreId) {
                    $q->where('id', '!=', $ignoreId);
                })
                ->exists();

            if (! $exists) {
                return $hash;
            }

            $i++;
            $hash = hash('sha256', $base.'#'.$i);
        }
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

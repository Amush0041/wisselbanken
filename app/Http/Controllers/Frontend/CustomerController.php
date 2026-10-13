<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Rbac\PermissionService;
use App\Support\Rbac\CurrentOrg;

class CustomerController extends Controller
{
    public function index()
    {
        $this->requireOrgLevel('quote_rfq_management', 'R');
        return view('user.customers.index');
    }

    public function list(Request $request)
    {
        $orgId = $this->requireOrgLevel('quote_rfq_management', 'R');

        $q = Customer::query()
            ->forOrg($orgId);

        if ($request->filled('search')) {
            $s = $request->get('search');
            $q->where(function ($qq) use ($s) {
                $qq->where('company_name', 'like', "%{$s}%")
                    ->orWhere('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->get('status') !== 'all') {
            $status = $request->get('status');
            if ($status === 'active') {
                $q->where('is_active', true);
            } elseif ($status === 'inactive') {
                $q->where('is_active', false);
            }
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'created_at') {
            $q->orderBy('created_at', $sortDir);
        } else {
            // Name sort: company_name then contact last/first as fallback
            $q->orderByRaw('COALESCE(NULLIF(company_name, \'\'), NULLIF(last_name, \'\'), NULLIF(first_name, \'\'), \'\') ' . $sortDir);
            $q->orderBy('id', 'desc');
        }

        $customers = $q->paginate(25);

        $items = collect($customers->items())->map(function (Customer $c) {
            $name = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
            return [
                'id' => $c->id,
                'company_name' => $c->company_name ?? '',
                'contact_name' => $name,
                'is_active' => (bool) $c->is_active,
                'created_at' => optional($c->created_at)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'customers' => $items,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $orgId = $this->requireOrgLevel('quote_rfq_management', 'R');
        $customer = $this->resolveOrgCustomer($id, $orgId);

        $logs = CustomerActivityLog::with('user')
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (CustomerActivityLog $log) {
                $userName = optional($log->user)->name ?: optional($log->user)->email ?: 'System';
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $userName,
                    'meta' => $log->meta ?? [],
                    'created_at' => optional($log->created_at)->toDateTimeString(),
                ];
            })
            ->values();

        return response()->json([
            'customer' => $customer,
            'activities' => $logs,
        ]);
    }

    public function store(Request $request)
    {
        $orgId = $this->requireOrgLevel('user_management', 'S');
        $data = $this->validated($request);
        $data['user_id'] = Auth::id();
        $data['org_id'] = $orgId;

        $customer = Customer::create($data);

        CustomerActivityLog::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'action' => 'created',
            'meta' => [
                'company_name' => $customer->company_name,
            ],
        ]);

        return response()->json([
            'success' => true,
            'customer_id' => $customer->id,
        ]);
    }

    public function update(Request $request, $id)
    {
        $orgId = $this->requireOrgLevel('user_management', 'O');
        $customer = $this->resolveOrgCustomer($id, $orgId);

        $data = $this->validated($request);
        $customer->update($data);

        CustomerActivityLog::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'action' => 'updated',
            'meta' => [
                'company_name' => $customer->company_name,
            ],
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy($id)
    {
        $orgId = $this->requireOrgLevel('user_management', 'F');
        $customer = $this->resolveOrgCustomer($id, $orgId);

        CustomerActivityLog::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'meta' => [
                'company_name' => $customer->company_name,
            ],
        ]);

        $customer->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'vat' => 'nullable|string|max:255',
            'fax' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',

            'billing_street_1' => 'nullable|string|max:255',
            'billing_street_2' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:255',
            'billing_state' => 'nullable|string|max:255',
            'billing_zip' => 'nullable|string|max:50',
            'billing_country' => 'nullable|string|max:255',

            'shipping_same_as_billing' => 'nullable|boolean',
            'shipping_street_1' => 'nullable|string|max:255',
            'shipping_street_2' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:255',
            'shipping_state' => 'nullable|string|max:255',
            'shipping_zip' => 'nullable|string|max:50',
            'shipping_country' => 'nullable|string|max:255',

            'notes' => 'nullable|string',
        ]);
    }

    private function resolveOrgCustomer($id, int $orgId): Customer
    {
        return Customer::forOrg($orgId)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function requireOrgLevel(string $group, string $level): int
    {
        $userId = (int) Auth::id();
        $orgId = (int) CurrentOrg::id($userId);

        abort_unless(
            $orgId > 0 && app(PermissionService::class)->checkPermission($userId, $orgId, $group, $level),
            403,
            'You do not have permission to perform this action.'
        );

        return $orgId;
    }
}

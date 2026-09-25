@extends('user.layouts.app')
@section('seo')
<title>Dashboard | {{ env('APP_NAME','Wisselbanken') }}</title>
<meta name="description" content="" />
@endsection
@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-dashboard">
    @php
        use App\Services\Rbac\PermissionService;
        use App\Models\Rbac\UserOrgRole;
        use App\Models\Rbac\Organization;

        $userId = Auth::id();
        $orders = \App\Models\Order::where('user_id', $userId);
        $quotes = \App\Models\Quote::where('user_id', $userId);
        $lists = \App\Models\SavedList::where('user_id', $userId);

        // Prefer controller-injected vars; fall back to inline computation for Route::view() compatibility.
        $_dashOrgId = $orgId ?? session(config('rbac.current_org_session_key'));
        $_dashUser  = auth()->user();
        $_dashAdmin = $_dashUser && $_dashUser->role === 'admin';
        $perm = app(PermissionService::class);
        $_perm = fn(string $g, string $l) => $_dashAdmin || ($_dashOrgId && $perm->checkPermission($userId, (int) $_dashOrgId, $g, $l));
        $dashCanEstimate = (bool) $_perm('estimate_management', 'S');
        $dashCanProcure  = (bool) $_perm('procurement', 'S');
        $dashCanManageCustomers = (bool) $_perm('user_management', 'S');

        $dashCurrentOrg = $currentOrg ?? ($_dashOrgId ? Organization::find($_dashOrgId) : null);
        $dashMyRoles    = $myRoles ?? ($_dashOrgId
            ? UserOrgRole::with('role')
                ->where('user_id', $userId)
                ->where('org_id', $_dashOrgId)
                ->where('is_active', true)
                ->get()
                ->pluck('role')
                ->filter()
            : collect());

        $ordersCount = (int) $orders->count();
        $ordersTotal = (float) $orders->sum('total');
        $listsCount = (int) $lists->count();
        $quotesCount = (int) $quotes->count();
        $listItemsCount = (int) \App\Models\SavedListItem::whereHas('savedList', fn($q) => $q->where('user_id', $userId))->count();

        $quoteStatusCounts = \App\Models\Quote::where('user_id', $userId)
            ->selectRaw("status, COUNT(*) as total")->groupBy('status')->pluck('total', 'status');

        $ordersByMonth = \App\Models\Order::where('user_id', $userId)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $monthLabels = [];
        $monthData = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i)->format('Y-m');
            $monthLabels[] = now()->subMonths($i)->format('M Y');
            $monthData[] = $ordersByMonth[$m] ?? 0;
        }

        $recentOrders = \App\Models\Order::where('user_id', $userId)->orderByDesc('created_at')->limit(5)->get();
        $recentLists = \App\Models\SavedList::where('user_id', $userId)->withCount('items')->orderByDesc('created_at')->limit(5)->get();
        $recentQuotes = \App\Models\Quote::where('user_id', $userId)->orderByDesc('created_at')->limit(5)->get();

        $chartQuoteStatus = [
            'series' => [
                (int) ($quoteStatusCounts['draft'] ?? 0),
                (int) ($quoteStatusCounts['sent'] ?? 0),
                (int) ($quoteStatusCounts['completed'] ?? 0),
            ],
            'labels' => ['Draft', 'Sent', 'Completed'],
        ];
    @endphp

    {{-- §6.5 User Workspace: My Projects (permission-filtered by project_members) --}}
    @if (!empty($myProjects) && $myProjects->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="ti ti-folders me-1 text-muted"></i>My Projects</h6>
            @if (!empty($canManageProjects) && $canManageProjects)
            <a href="{{ route('org-admin.projects.index') }}" class="btn btn-xs btn-outline-secondary">Manage</a>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Project</th>
                            <th class="text-end">Items</th>
                            <th class="text-end">Status</th>
                            <th class="text-end">Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($myProjects as $mp)
                        <tr>
                            <td class="fw-medium small">{{ $mp->name ?: ('Estimate #' . $mp->id) }}</td>
                            <td class="text-end small text-muted">{{ $mp->items_count }}</td>
                            <td class="text-end"><span class="badge bg-label-secondary" style="font-size:.65rem">{{ ucfirst($mp->status ?? 'draft') }}</span></td>
                            <td class="text-end small text-muted">{{ $mp->updated_at?->format('M d') }}</td>
                            <td class="text-end">
                                <a href="{{ route('project.workspace', $mp->id) }}" class="btn btn-xs btn-label-primary">Open</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- §6.5 Pending Actions (role-gated) --}}
    @if ((!empty($pendingRfqs) && $pendingRfqs->isNotEmpty()) || (!empty($incomingRfqs) && $incomingRfqs->isNotEmpty()) || (!empty($pendingApprovals) && $pendingApprovals->isNotEmpty()))
    <div class="row g-3 mb-3">
        @if (!empty($pendingRfqs) && $pendingRfqs->isNotEmpty())
        <div class="col-md-4">
            <div class="card border-start border-primary border-3 shadow-sm h-100">
                <div class="card-body py-3">
                    <p class="text-muted small text-uppercase fw-semibold mb-2" style="font-size:.7rem">
                        <i class="ti ti-send me-1"></i>Open Outgoing RFQs
                    </p>
                    @foreach ($pendingRfqs as $rq)
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <a href="{{ route('rfq.show', $rq->id) }}" class="small text-body fw-medium text-truncate" style="max-width:160px">{{ $rq->title }}</a>
                        @if ($rq->deadline)<small class="text-muted">{{ \Carbon\Carbon::parse($rq->deadline)->format('M d') }}</small>@endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        @if (!empty($incomingRfqs) && $incomingRfqs->isNotEmpty())
        <div class="col-md-4">
            <div class="card border-start border-warning border-3 shadow-sm h-100">
                <div class="card-body py-3">
                    <p class="text-muted small text-uppercase fw-semibold mb-2" style="font-size:.7rem">
                        <i class="ti ti-inbox me-1"></i>Incoming RFQs (Pending Response)
                    </p>
                    @foreach ($incomingRfqs as $rq)
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <a href="{{ route('rfq.seller.incoming') }}" class="small text-body fw-medium text-truncate" style="max-width:160px">{{ $rq->title }}</a>
                        @if ($rq->deadline)<small class="text-muted">{{ \Carbon\Carbon::parse($rq->deadline)->format('M d') }}</small>@endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        @if (!empty($pendingApprovals) && $pendingApprovals->isNotEmpty())
        <div class="col-md-4">
            <div class="card border-start border-danger border-3 shadow-sm h-100">
                <div class="card-body py-3">
                    <p class="text-muted small text-uppercase fw-semibold mb-2" style="font-size:.7rem">
                        <i class="ti ti-check me-1"></i>Pending Approvals
                    </p>
                    @foreach ($pendingApprovals as $ap)
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <a href="{{ route('project.workspace', $ap->id) }}" class="small text-body fw-medium text-truncate" style="max-width:160px">{{ $ap->name ?: ('Estimate #' . $ap->id) }}</a>
                        <span class="badge bg-label-danger" style="font-size:.65rem">Awaiting</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Role banner + quick actions (plan §6.5) --}}
    @if ($dashCurrentOrg)
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 px-4">
                    <p class="text-muted small text-uppercase fw-semibold mb-2" style="font-size:.7rem;letter-spacing:.04em">
                        <i class="ti ti-building me-1"></i>Active Organization
                    </p>
                    <div class="fw-semibold mb-2">{{ $dashCurrentOrg->name }}</div>
                    @if ($dashMyRoles->isNotEmpty())
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($dashMyRoles as $role)
                                <span class="badge" style="background:rgba(107,28,28,.12);color:#6b1c1c;font-size:.72rem">{{ $role->name }}</span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-muted small">No roles assigned yet</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 px-4">
                    <p class="text-muted small text-uppercase fw-semibold mb-2" style="font-size:.7rem;letter-spacing:.04em">
                        <i class="ti ti-bolt me-1"></i>Quick Actions
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($dashCanEstimate)
                            <a href="{{ url('quotes') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-file-invoice me-1"></i>New Estimate
                            </a>
                        @endif
                        @if ($dashCanProcure)
                            <a href="{{ url('checkout') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-shopping-cart me-1"></i>Checkout
                            </a>
                        @endif
                        @if ($dashCanManageCustomers)
                            <a href="{{ url('customers') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-users me-1"></i>Customers
                            </a>
                        @endif
                        @if (! $dashCanEstimate && ! $dashCanProcure && ! $dashCanManageCustomers)
                            <span class="text-muted small">Your role has read-only access. Contact your organization owner to request additional permissions.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Dense stats bar --}}
    <div class="row g-2 mb-3">
        <div class="col">
            <div class="card border-0 shadow-none bg-label-primary py-2 px-3">
                <div class="d-flex align-items-center">
                    <i class="ti ti-shopping-cart ti-sm me-2"></i>
                    <div>
                        <span class="fw-semibold">{{ $ordersCount }}</span> Orders
                        <span class="text-muted ms-1">· ${{ number_format($ordersTotal, 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-none bg-label-info py-2 px-3">
                <div class="d-flex align-items-center">
                    <i class="ti ti-layout-kanban ti-sm me-2"></i>
                    <div><span class="fw-semibold">{{ $listsCount }}</span> Lists <span class="text-muted">· {{ $listItemsCount }} items</span></div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-none bg-label-success py-2 px-3">
                <div class="d-flex align-items-center">
                    <i class="ti ti-file-invoice ti-sm me-2"></i>
                    <div><span class="fw-semibold">{{ $quotesCount }}</span> Quotes</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts row --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h6 class="mb-0">Quote Status</h6>
                </div>
                <div class="card-body pt-0">
                    <div id="chartQuoteStatus" style="min-height: 200px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h6 class="mb-0">Activity</h6>
                </div>
                <div class="card-body pt-0">
                    <div id="chartActivity" style="min-height: 200px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h6 class="mb-0">Orders (6 months)</h6>
                </div>
                <div class="card-body pt-0">
                    <div id="chartOrdersMonth" style="min-height: 200px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dense tables --}}
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Recent Orders</h6>
                    <a href="{{ url('view-orders') }}" class="btn btn-xs btn-outline-primary">All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                                @forelse($recentOrders as $order)
                                <tr>
                                    <td class="py-2"><a href="{{ route('order.details', $order->id) }}" class="text-body">{{ $order->order_number }}</a></td>
                                    <td class="py-2 text-end">${{ number_format($order->total ?? 0, 2) }}</td>
                                    <td class="py-2 text-end"><span class="badge bg-label-secondary">{{ ucfirst($order->status ?? 'pending') }}</span></td>
                                </tr>
                                @empty
                                <tr><td class="py-2 text-muted" colspan="3">No orders</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Recent Lists</h6>
                    <a href="{{ url('view-lists') }}" class="btn btn-xs btn-outline-primary">All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                                @forelse($recentLists as $list)
                                <tr>
                                    <td class="py-2"><a href="{{ url('list-view/'.$list->id.'/'.$list->name) }}" class="text-body">{{ $list->name ?? 'Untitled' }}</a></td>
                                    <td class="py-2 text-end">{{ $list->items_count ?? 0 }} items</td>
                                    <td class="py-2 text-end"><a href="{{ url('list-view/'.$list->id.'/'.$list->name) }}" class="btn btn-xs btn-label-primary">Open</a></td>
                                </tr>
                                @empty
                                <tr><td class="py-2 text-muted" colspan="3">No lists</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Recent Quotes</h6>
                    <a href="{{ url('quotes') }}" class="btn btn-xs btn-outline-primary">All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                                @forelse($recentQuotes as $quote)
                                <tr>
                                    <td class="py-2">{{ $quote->quote_number ?? 'Quote' }}</td>
                                    <td class="py-2 text-end">{{ $quote->created_at?->format('M d') }}</td>
                                    <td class="py-2 text-end"><span class="badge bg-label-primary">{{ ucfirst($quote->status ?? 'draft') }}</span></td>
                                </tr>
                                @empty
                                <tr><td class="py-2 text-muted" colspan="3">No quotes</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    if (typeof ApexCharts === 'undefined') return;

    var chartQuoteOpts = {
        series: @json($chartQuoteStatus['series']),
        chart: { type: 'donut', height: 200, fontFamily: 'inherit' },
        labels: @json($chartQuoteStatus['labels']),
        colors: ['#4a171e', '#e3b143', '#8a6f73'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: true },
        plotOptions: { pie: { donut: { size: '65%' } } }
    };
    if (document.getElementById('chartQuoteStatus')) {
        new ApexCharts(document.querySelector('#chartQuoteStatus'), chartQuoteOpts).render();
    }

    var chartActivityOpts = {
        series: [{ name: 'Count', data: [{{ $ordersCount }}, {{ $listsCount }}, {{ $quotesCount }}] }],
        chart: { type: 'bar', height: 200, fontFamily: 'inherit', toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, barHeight: '60%', borderRadius: 4 } },
        xaxis: { categories: ['Orders', 'Lists', 'Quotes'] },
        colors: ['#4a171e'],
        dataLabels: { enabled: true }
    };
    if (document.getElementById('chartActivity')) {
        new ApexCharts(document.querySelector('#chartActivity'), chartActivityOpts).render();
    }

    var chartOrdersOpts = {
        series: [{ name: 'Orders', data: @json($monthData) }],
        chart: { type: 'bar', height: 200, fontFamily: 'inherit', toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        xaxis: { categories: @json($monthLabels) },
        colors: ['#e3b143'],
        dataLabels: { enabled: true }
    };
    if (document.getElementById('chartOrdersMonth')) {
        new ApexCharts(document.querySelector('#chartOrdersMonth'), chartOrdersOpts).render();
    }
})();
</script>
@endpush
@endsection

@extends('user.layouts.app')

@section('seo')
<title>Orders - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page user-orders">
    <div class="mb-3">
        <h4 class="page-title mb-1">{{ $isOrgAdmin ? 'All Organisation Orders' : 'My Orders' }}</h4>
        <p class="page-description mb-0">Track status, totals, and approval details of orders.</p>
    </div>
    <div class="card">
        <div class="card-header py-3">
            <div class="section-header">
                <h5 class="mb-0">Orders</h5>
                <div class="search-tabs">
                    <input type="text" placeholder="Search" class="search-box" id="searchInput">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="ordersTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            @if ($isOrgAdmin)
                            <th>Submitted By</th>
                            @endif
                            <th>Project</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Approval</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        <tr>
                            <td data-label="Order No">{{ $order->order_number }}</td>
                            @if ($isOrgAdmin)
                            <td data-label="Submitted By">{{ optional($order->user)->name ?? '—' }}</td>
                            @endif
                            <td data-label="Project">{{ $order->project_title ?: $order->name }}</td>
                            <td data-label="Date">{{ $order->created_at->format('M d, Y') }}</td>
                            <td data-label="Status">
                                @php
                                    $badgeClass = match($order->status) {
                                        'processing'      => 'bg-label-info',
                                        'completed'       => 'bg-label-success',
                                        'cancelled'       => 'bg-label-danger',
                                        'pending_approval'=> 'bg-label-warning',
                                        default           => 'bg-label-secondary',
                                    };
                                    $statusLabel = match($order->status) {
                                        'pending_approval' => 'Pending Approval',
                                        default            => ucfirst($order->status),
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}" style="font-size:.75rem">{{ $statusLabel }}</span>
                            </td>
                            <td data-label="Items">{{ $order->items->count() }}</td>
                            <td data-label="Total">${{ number_format($order->total, 2) }}</td>
                            <td data-label="Approval">
                                @if ($order->approved_by && $order->approvedBy)
                                    <div class="small">
                                        <span class="text-success fw-semibold"><i class="ti ti-circle-check me-1"></i>Approved</span><br>
                                        <span class="text-muted">by {{ $order->approvedBy->name }}</span><br>
                                        <span class="text-muted" style="font-size:.72rem">{{ $order->updated_at->format('M d, Y') }}</span>
                                        @if ($order->approval_note)
                                            <br><span class="text-muted fst-italic" style="font-size:.72rem" title="{{ $order->approval_note }}">{{ Str::limit($order->approval_note, 40) }}</span>
                                        @endif
                                    </div>
                                @elseif ($order->rejected_by && $order->rejectedBy)
                                    <div class="small">
                                        <span class="text-danger fw-semibold"><i class="ti ti-circle-x me-1"></i>Rejected</span><br>
                                        <span class="text-muted">by {{ $order->rejectedBy->name }}</span><br>
                                        <span class="text-muted" style="font-size:.72rem">{{ $order->updated_at->format('M d, Y') }}</span>
                                        @if ($order->approval_note)
                                            <br><span class="text-muted fst-italic" style="font-size:.72rem" title="{{ $order->approval_note }}">{{ Str::limit($order->approval_note, 40) }}</span>
                                        @endif
                                    </div>
                                @elseif ($order->status === 'pending_approval')
                                    <span class="text-warning small"><i class="ti ti-clock me-1"></i>Awaiting approval</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="action-btns">
                                    <a href="{{ route('order.details', $order->id) }}" class="btn-view">
                                        <i class="ti ti-eye btn-icon"></i>
                                        View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script>
    $(document).ready(function() {
        var table = $('#ordersTable').DataTable({
            responsive: true,
            paging: true,
            searching: true,
            info: false,
            lengthChange: false,
            dom: '<"top"f>rt<"bottom"p>',
            language: {
                search: "",
                searchPlaceholder: "Search orders..."
            },
            initComplete: function() {
                $('.dataTables_filter').hide();
            },
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -2 },
                { responsivePriority: 3, targets: 4 }
            ]
        });

        $('#searchInput').keyup(function() {
            table.search($(this).val()).draw();
        });
    });
</script>
@endpush

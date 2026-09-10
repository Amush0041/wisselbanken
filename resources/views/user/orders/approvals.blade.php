@extends('user.layouts.app')

@section('seo')
<title>Pending Approvals - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page user-orders">

    <div class="mb-3">
        <h4 class="page-title mb-1">Pending Order Approvals</h4>
        <p class="page-description mb-0">Review and approve or reject orders submitted by your organisation members.</p>
    </div>

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card">
        <div class="card-header py-3">
            <div class="section-header">
                <h5 class="mb-0">Orders Awaiting Approval</h5>
                <div class="search-tabs">
                    <input type="text" placeholder="Search" class="search-box" id="searchInput">
                </div>
            </div>
        </div>
        <div class="card-body">
            @if ($pendingOrders->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-circle-check" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">No orders are currently awaiting approval.</p>
                </div>
            @else
            <div class="table-responsive">
                <table id="approvalsTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Submitted By</th>
                            <th>Project</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingOrders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->user?->name ?? '—' }}</td>
                            <td>{{ $order->project_title ?? $order->name ?? '—' }}</td>
                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                            <td>{{ $order->items->count() }}</td>
                            <td>${{ number_format($order->total, 2) }}</td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#approveModal{{ $order->id }}">
                                        <i class="ti ti-check me-1"></i>Approve
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rejectModal{{ $order->id }}">
                                        <i class="ti ti-x me-1"></i>Reject
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- Approve modal --}}
                        <div class="modal fade" id="approveModal{{ $order->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('orders.approve', $order) }}">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title">Approve Order {{ $order->order_number }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-3">
                                                This will move the order to <strong>Processing</strong> and notify the requester.
                                            </p>
                                            <div class="mb-3">
                                                <label class="form-label">Note (optional)</label>
                                                <textarea name="note" class="form-control" rows="3" placeholder="Add an approval note…"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="ti ti-check me-1"></i>Confirm Approve
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Reject modal --}}
                        <div class="modal fade" id="rejectModal{{ $order->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('orders.reject', $order) }}">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Order {{ $order->order_number }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-3">
                                                This will <strong>cancel</strong> the order. The requester will be notified.
                                            </p>
                                            <div class="mb-3">
                                                <label class="form-label">Reason (optional)</label>
                                                <textarea name="note" class="form-control" rows="3" placeholder="Explain why this order is being rejected…"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="ti ti-x me-1"></i>Confirm Reject
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#approvalsTable').DataTable({
        responsive: true,
        paging: true,
        searching: true,
        info: false,
        lengthChange: false,
        dom: '<"top"f>rt<"bottom"p>',
        language: { search: '', searchPlaceholder: 'Search orders…' },
        initComplete: function() { $('.dataTables_filter').hide(); },
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: 5 },
        ]
    });

    $('#searchInput').keyup(function() { table.search($(this).val()).draw(); });
});
</script>
@endpush

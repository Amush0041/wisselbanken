@extends('user.layouts.app')

@section('seo')
<title>RFQs - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="page-title mb-1">Requests for Quote</h4>
            <p class="page-description mb-0">Send RFQs to suppliers, review their responses, and convert to orders.</p>
        </div>
        @canDo('quote_rfq_management', 'S')
        <a href="{{ route('rfq.create') }}" class="btn btn-primary btn-sm">
            <i class="ti ti-plus me-1"></i>New RFQ
        </a>
        @endCanDo
    </div>

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card">
        <div class="card-header py-3">
            <div class="section-header">
                <h5 class="mb-0">My RFQs</h5>
                <div class="search-tabs">
                    <input type="text" placeholder="Search" class="search-box" id="searchInput">
                </div>
            </div>
        </div>
        <div class="card-body">
            @if ($rfqs->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-mail-forward" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">No RFQs yet.@canDo('quote_rfq_management', 'S') <a href="{{ route('rfq.create') }}">Send your first one.</a>@endCanDo</p>
                </div>
            @else
            <div class="table-responsive">
                <table id="rfqTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Project</th>
                            <th>Suppliers</th>
                            <th>Responses</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rfqs as $rfq)
                        <tr>
                            <td>{{ $rfq->id }}</td>
                            <td>{{ $rfq->title }}</td>
                            <td>@if ($rfq->project)<a href="{{ route('projects.show', $rfq->project->id) }}">{{ $rfq->project->name }}</a>@else — @endif</td>
                            <td>{{ $rfq->recipients->count() }}</td>
                            <td>{{ $rfq->responses->count() }}</td>
                            <td>{{ $rfq->deadline?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <span class="badge bg-label-{{ match($rfq->status) {
                                    'draft'     => 'secondary',
                                    'sent'      => 'primary',
                                    'closed'    => 'warning',
                                    'converted' => 'success',
                                    'cancelled' => 'danger',
                                    default     => 'secondary',
                                } }}">{{ ucfirst($rfq->status) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('rfq.show', $rfq) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
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
    var table = $('#rfqTable').DataTable({
        responsive: true, paging: true, searching: true, info: false,
        lengthChange: false,
        dom: '<"top"f>rt<"bottom"p>',
        language: { search: '', searchPlaceholder: 'Search RFQs…' },
        initComplete: function() { $('.dataTables_filter').hide(); },
    });
    $('#searchInput').keyup(function() { table.search($(this).val()).draw(); });
});
</script>
@endpush

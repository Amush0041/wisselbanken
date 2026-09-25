@extends('user.layouts.app')

@section('seo')
<title>RFQ #{{ $rfq->id }} - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="page-title mb-1">{{ $rfq->title }}</h4>
            <p class="page-description mb-0">
                @if ($rfq->project)Project: <a href="{{ route('projects.show', $rfq->project->id) }}">{{ $rfq->project->name }}</a> &middot; @endif
                RFQ #{{ $rfq->id }} &middot; Sent {{ $rfq->created_at->format('M d, Y') }}
                @if ($rfq->deadline) &middot; Deadline: {{ $rfq->deadline->format('M d, Y') }} @endif
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-label-{{ match($rfq->status) {
                'draft'     => 'secondary',
                'sent'      => 'primary',
                'closed'    => 'warning',
                'converted' => 'success',
                'cancelled' => 'danger',
                default     => 'secondary',
            } }} fs-6">{{ ucfirst($rfq->status) }}</span>
            <a href="{{ route('rfq.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- RFQ notes --}}
    @if ($rfq->notes)
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-semibold mb-1">Notes / Specifications</h6>
            <p class="mb-0" style="white-space: pre-line">{{ $rfq->notes }}</p>
        </div>
    </div>
    @endif

    <div class="row g-3">

        {{-- Supplier status --}}
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header py-3"><h6 class="mb-0">Suppliers</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ($rfq->recipients as $recipient)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $recipient->sellerOrganization?->name ?? '—' }}</span>
                            <span class="badge bg-label-{{ match($recipient->status) {
                                'pending'   => 'secondary',
                                'responded' => 'success',
                                'declined'  => 'danger',
                                default     => 'secondary',
                            } }}">{{ ucfirst($recipient->status) }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- Responses --}}
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header py-3"><h6 class="mb-0">Quotes Received</h6></div>
                <div class="card-body">
                    @if ($rfq->responses->isEmpty())
                        <p class="text-muted mb-0">No quotes received yet.</p>
                    @else
                    @foreach ($rfq->responses as $response)
                    <div class="border rounded p-3 mb-3 @if($response->status === 'selected') border-success @endif">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong>{{ $response->sellerOrganization?->name ?? '—' }}</strong>
                                <br>
                                <span class="text-muted small">Submitted {{ $response->created_at->format('M d, Y') }}</span>
                                @if ($response->valid_until)
                                &middot; <span class="text-muted small">Valid until {{ $response->valid_until->format('M d, Y') }}</span>
                                @endif
                            </div>
                            <div class="text-end">
                                <span class="fs-5 fw-bold">${{ number_format($response->total_price, 2) }}</span>
                                <br>
                                <span class="badge bg-label-{{ match($response->status) {
                                    'pending_review' => 'primary',
                                    'selected'       => 'success',
                                    'rejected'       => 'danger',
                                    default          => 'secondary',
                                } }}">{{ ucwords(str_replace('_', ' ', $response->status)) }}</span>
                            </div>
                        </div>
                        @if ($response->notes)
                        <p class="mb-2 small" style="white-space:pre-line">{{ $response->notes }}</p>
                        @endif

                        @if ($rfq->status === 'sent' && $response->status === 'pending_review')
                        @canDo('quote_rfq_management', 'O')
                        <form method="POST" action="{{ route('rfq.response.select', [$rfq, $response]) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="ti ti-check me-1"></i>Select this quote
                            </button>
                        </form>
                        @endCanDo
                        @endif

                        @if ($response->status === 'selected' && $rfq->status === 'closed')
                        @canDo('quote_rfq_management', 'F')
                        <form method="POST" action="{{ route('rfq.convert', $rfq) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-shopping-cart me-1"></i>Convert to Purchase Order
                            </button>
                        </form>
                        @endCanDo
                        @endif
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

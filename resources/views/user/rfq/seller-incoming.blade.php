@extends('user.layouts.app')

@section('seo')
<title>Incoming RFQs - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page">

    <div class="mb-3">
        <h4 class="page-title mb-1">Incoming RFQs</h4>
        <p class="page-description mb-0">Review requests for quote sent to your organisation and submit your prices.</p>
    </div>

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if ($recipients->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="ti ti-inbox" style="font-size:2rem"></i>
                <p class="mt-2 mb-0">No RFQs have been sent to your organisation yet.</p>
            </div>
        </div>
    @else
    @foreach ($recipients as $recipient)
    @php $rfq = $recipient->rfqRequest; @endphp
    <div class="card mb-3">
        <div class="card-header py-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-0">{{ $rfq->title }}</h6>
                    <small class="text-muted">
                        From <strong>{{ $rfq->organization?->name ?? '—' }}</strong>
                        &middot; Sent {{ $rfq->created_at->format('M d, Y') }}
                        @if ($rfq->deadline) &middot; Deadline {{ $rfq->deadline->format('M d, Y') }} @endif
                    </small>
                </div>
                <span class="badge bg-label-{{ match($recipient->status) {
                    'pending'   => 'primary',
                    'responded' => 'success',
                    'declined'  => 'secondary',
                    default     => 'secondary',
                } }}">{{ ucfirst($recipient->status) }}</span>
            </div>
        </div>

        @if ($rfq->notes)
        <div class="card-body border-bottom py-2">
            <p class="mb-0 small" style="white-space:pre-line">{{ $rfq->notes }}</p>
        </div>
        @endif

        @if ($recipient->status === 'pending')
        @canDo('quote_rfq_management', 'S')
        <div class="card-body">
            <h6 class="fw-semibold mb-3">Submit Your Quote</h6>
            <form method="POST" action="{{ route('rfq.seller.respond', $rfq) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Total Price (USD) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="total_price" class="form-control @error('total_price') is-invalid @enderror"
                                step="0.01" min="0" value="{{ old('total_price') }}" placeholder="0.00">
                            @error('total_price')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quote Valid Until</label>
                        <input type="date" name="valid_until" class="form-control"
                            value="{{ old('valid_until') }}" min="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"
                            placeholder="Delivery schedule, terms, exclusions…">{{ old('notes') }}</textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ti ti-send me-1"></i>Submit Quote
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#declineModal{{ $rfq->id }}">
                            Decline
                        </button>
                    </div>
                </div>
            </form>
        </div>
        @endCanDo
        @cannotDo('quote_rfq_management', 'S')
        <div class="card-body">
            <div class="alert alert-warning mb-0">
                <i class="ti ti-lock me-1"></i>
                You don't have permission to submit quotes. Contact your organization administrator.
            </div>
        </div>
        @endCannotDo
        @endif

        {{-- Decline modal (only rendered when user has submit permission) --}}
        @canDo('quote_rfq_management', 'S')
        <div class="modal fade" id="declineModal{{ $rfq->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('rfq.seller.decline', $rfq) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Decline RFQ</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to decline this RFQ from <strong>{{ $rfq->organization?->name }}</strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger btn-sm">Confirm Decline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endCanDo

    </div>
    @endforeach
    @endif
</div>
@endsection

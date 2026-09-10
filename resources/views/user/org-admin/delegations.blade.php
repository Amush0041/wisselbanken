@extends('user.layouts.app')

@section('seo')
<title>Delegations | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .delegation-card { border-left:4px solid var(--wb-maroon); }
    .delegation-card.expired { border-left-color:#dee2e6;opacity:.7; }
    .delegation-card.active-now { border-left-color:#198754; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Delegations</h4>
            <p class="text-muted small mb-0">Grant time-bound access to act on your behalf within {{ $org->name }}</p>
        </div>
        @canDo('delegation_and_impersonation', 'O')
        <button class="btn btn-sm text-white" style="background:var(--wb-maroon)" data-bs-toggle="modal" data-bs-target="#grantModal">
            <i class="ti ti-plus me-1"></i> Grant Delegation
        </button>
        @endCanDo
    </div>

    @include('user.org-admin._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="ti ti-check me-1"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('peel_off_suggestion'))
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="ti ti-transfer me-1"></i>
            <strong>Peel-off tip:</strong> {{ session('peel_off_suggestion') }}
            <a href="{{ route('org-admin.index') }}" class="alert-link ms-1">Go to Team &rarr;</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="ti ti-alert-triangle me-1"></i> {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        {{-- Delegations I've granted --}}
        <div class="col-lg-6">
            <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:.74rem;letter-spacing:.05em">
                <i class="ti ti-arrow-up-right me-1"></i> Granted by me ({{ $granted->count() }})
            </h6>

            @forelse ($granted as $d)
                @php
                    $now = now();
                    $isActive = $d->is_active && $now->between($d->starts_at, $d->expires_at);
                    $isExpired = $now->isAfter($d->expires_at);
                @endphp
                <div class="card border-0 shadow-sm mb-3 delegation-card {{ $isActive ? 'active-now' : ($isExpired || !$d->is_active ? 'expired' : '') }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="fw-medium mb-0">
                                    <i class="ti ti-user me-1 text-muted"></i>
                                    {{ $d->toUser->name ?? 'Unknown' }}
                                    <small class="text-muted">{{ $d->toUser->email ?? '' }}</small>
                                </p>
                                <small class="text-muted">
                                    {{ $d->starts_at->format('M d, Y H:i') }} → {{ $d->expires_at->format('M d, Y H:i') }}
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @if (!$d->is_active)
                                    <span class="badge bg-label-secondary">Revoked</span>
                                @elseif ($isExpired)
                                    <span class="badge bg-label-secondary">Expired</span>
                                @elseif ($isActive)
                                    <span class="badge bg-label-success">Active</span>
                                @else
                                    <span class="badge bg-label-warning text-dark">Pending</span>
                                @endif

                                @canDo('delegation_and_impersonation', 'O')
                                @if ($d->is_active && !$isExpired)
                                    <form action="{{ route('org-admin.delegations.destroy', $d->id) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-icon btn-text-danger" title="Revoke"
                                                onclick="return confirm('Revoke this delegation?')">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @endif
                                @endCanDo
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-dashed border-0 text-center py-4 text-muted">
                    <i class="ti ti-arrows-exchange fs-2 d-block mb-2"></i>
                    <p class="mb-0 small">No delegations granted yet</p>
                    <small>Use "Grant Delegation" to let a colleague act on your behalf.</small>
                </div>
            @endforelse
        </div>

        {{-- Delegations I've received --}}
        <div class="col-lg-6">
            <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:.74rem;letter-spacing:.05em">
                <i class="ti ti-arrow-down-left me-1"></i> Received by me ({{ $received->count() }})
            </h6>

            @forelse ($received as $d)
                @php
                    $now = now();
                    $isActive = $d->is_active && $now->between($d->starts_at, $d->expires_at);
                    $isExpired = $now->isAfter($d->expires_at);
                @endphp
                <div class="card border-0 shadow-sm mb-3 delegation-card {{ $isActive ? 'active-now' : ($isExpired || !$d->is_active ? 'expired' : '') }}">
                    <div class="card-body">
                        <p class="fw-medium mb-0">
                            <i class="ti ti-user me-1 text-muted"></i>
                            Acting as <strong>{{ $d->fromUser->name ?? 'Unknown' }}</strong>
                        </p>
                        <small class="text-muted">
                            {{ $d->starts_at->format('M d, Y H:i') }} → {{ $d->expires_at->format('M d, Y H:i') }}
                        </small>
                        <div class="mt-1">
                            @if (!$d->is_active)
                                <span class="badge bg-label-secondary">Revoked</span>
                            @elseif ($isExpired)
                                <span class="badge bg-label-secondary">Expired</span>
                            @elseif ($isActive)
                                <span class="badge bg-label-success">Active — you have their permissions</span>
                            @else
                                <span class="badge bg-label-warning text-dark">Starts {{ $d->starts_at->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border-0 text-center py-4 text-muted">
                    <i class="ti ti-user-check fs-2 d-block mb-2"></i>
                    <p class="mb-0 small">No delegations received</p>
                    <small>When a colleague delegates to you, it appears here.</small>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Grant delegation modal --}}
<div class="modal fade" id="grantModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-semibold">Grant Delegation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('org-admin.delegations.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">
                        Choose a colleague to act on your behalf in <strong>{{ $org->name }}</strong> during the specified window.
                        They will inherit your roles for permission checks during that period.
                    </p>

                    <div class="mb-3">
                        <label class="form-label">Delegate to</label>
                        <select name="to_user_id" class="form-select" required>
                            <option value="">Select a team member…</option>
                            @foreach ($orgMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Starts at</label>
                            <input type="datetime-local" name="starts_at" class="form-control" required
                                   value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Expires at</label>
                            <input type="datetime-local" name="expires_at" class="form-control" required
                                   value="{{ now()->addDays(7)->format('Y-m-d\TH:i') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--wb-maroon)">Grant Delegation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

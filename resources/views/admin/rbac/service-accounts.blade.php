@extends('admin.layouts.app')

@section('seo')
<title>Service Accounts | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; }
    .stat-card { border: 0; border-radius: .75rem; box-shadow: 0 .125rem .5rem rgba(0,0,0,.08); }
    .stale-label { font-size: .65rem; font-weight: 700; color: #e65100; letter-spacing: .03em; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Service Accounts Overview</h4>
            <p class="text-muted small mb-0">All API tokens across all organizations</p>
        </div>
    </div>

    @include('admin.rbac._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3">
            <i class="ti ti-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stale warning --}}
    @if ($stats['stale'] > 0)
        <div class="alert mb-4" style="background:#fff8f2;border:1px solid #f5d5b5;border-radius:.75rem">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex gap-2">
                    <i class="ti ti-alert-triangle text-warning fs-5 flex-shrink-0"></i>
                    <div>
                        <strong class="text-warning">{{ $stats['stale'] }} {{ Str::plural('token', $stats['stale']) }} may be stale — review recommended</strong><br>
                        <small class="text-muted">These service accounts haven't been used in over 90 days and exceed security threshold.</small>
                    </div>
                </div>
                <a href="{{ route('admin.rbac.service-accounts') }}?status=active" class="btn btn-sm text-white flex-shrink-0" style="background:#e65100">
                    Review Stale Tokens
                </a>
            </div>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-3">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0" style="color:var(--wb-maroon)">{{ number_format($stats['active']) }}</div>
                <small class="text-muted">Active Tokens</small>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0 text-warning">{{ number_format($stats['expiring_7days']) }}</div>
                <small class="text-muted">Expiring in 7 Days</small>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0 text-secondary">{{ number_format($stats['revoked_30days']) }}</div>
                <small class="text-muted">Revoked (30d)</small>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card stat-card py-3 text-center text-white" style="background:var(--wb-maroon)">
                <div class="fw-bold fs-5 mb-0">{{ $stats['stale'] === 0 ? 'Optimal' : 'Review Needed' }}</div>
                <small style="opacity:.8">Security Pulse</small>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.rbac.service-accounts') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <div class="d-flex align-items-center gap-1">
                    <label class="text-muted small fw-medium">Status:</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:140px">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="expiring" @selected(request('status') === 'expiring')>Expiring (7 days)</option>
                        <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm text-white" style="background:var(--wb-maroon)">Apply</button>
                @if (request('status'))
                    <a href="{{ route('admin.rbac.service-accounts') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
                <span class="ms-auto text-muted small">Showing {{ $tokens->firstItem() }}–{{ $tokens->lastItem() }} of {{ number_format($tokens->total()) }} results</span>
            </form>
        </div>
    </div>

    {{-- Tokens table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Token Name</th>
                            <th class="py-3">Owner</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Last Used</th>
                            <th class="py-3">Expires</th>
                            <th class="py-3 pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            @php
                                $expired  = $token->expires_at && now()->isAfter($token->expires_at);
                                $active   = $token->is_active && !$expired;
                                $expiring = $active && $token->expires_at && $token->expires_at->lte(now()->addDays(7));
                                $stale    = $active && ($token->last_used_at === null || $token->last_used_at->lte(now()->subDays(90)));
                            @endphp
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="width:32px;height:32px;background:#fde8e8">
                                            <i class="ti ti-robot" style="color:var(--wb-maroon);font-size:.85rem"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium small">{{ $token->name }}</div>
                                            @if ($stale)
                                                <span class="stale-label">STALE TOKEN</span>
                                            @else
                                                <small class="text-muted font-monospace" style="font-size:.68rem">
                                                    ID: TK-{{ str_pad($token->id, 3, '0', STR_PAD_LEFT) }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <small class="fw-medium">{{ optional($token->user)->name ?? '—' }}</small><br>
                                    <small class="text-muted">{{ optional($token->user)->email ?? '' }}</small>
                                </td>
                                <td class="py-3">
                                    @if (!$token->is_active)
                                        <span class="badge bg-label-secondary">REVOKED</span>
                                    @elseif ($expired)
                                        <span class="badge bg-label-secondary">EXPIRED</span>
                                    @elseif ($expiring)
                                        <span class="badge bg-label-warning text-dark">EXPIRING</span>
                                    @else
                                        <span class="badge bg-label-success">ACTIVE</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">
                                        {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}
                                    </small>
                                </td>
                                <td class="py-3">
                                    <small class="{{ ($expiring) ? 'text-danger fw-medium' : 'text-muted' }}">
                                        {{ $token->expires_at ? $token->expires_at->format('M d, Y') : 'Never' }}
                                    </small>
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    @if ($token->is_active)
                                        <form action="{{ route('admin.rbac.service-accounts.revoke', $token->id) }}" method="POST" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-text-danger" style="font-size:.8rem;font-weight:600;color:#dc3545"
                                                    onclick="return confirm('Revoke token {{ $token->name }}?')">
                                                Revoke
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="ti ti-api fs-2 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-0">No API tokens found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($tokens->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4">
                {{ $tokens->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

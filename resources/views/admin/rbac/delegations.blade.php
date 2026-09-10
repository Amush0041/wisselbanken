@extends('admin.layouts.app')

@section('seo')
<title>Delegations Overview | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; }
    .stat-card { border: 0; border-radius: .75rem; box-shadow: 0 .125rem .5rem rgba(0,0,0,.08); }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Delegations Control</h4>
            <p class="text-muted small mb-0">Global monitoring dashboard — all active delegations across organizations</p>
        </div>
    </div>

    @include('admin.rbac._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3">
            <i class="ti ti-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0" style="color:var(--wb-maroon)">{{ number_format($stats['active']) }}</div>
                <small class="text-muted">Total Active Delegations</small>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0 text-warning">{{ number_format($stats['expiring_today']) }}</div>
                <small class="text-muted">Expiring Today</small>
                @if ($stats['expiring_today'] > 0)
                    <div><span class="badge bg-label-warning" style="font-size:.65rem">Critical</span></div>
                @endif
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0 text-danger">{{ number_format($stats['expired_week']) }}</div>
                <small class="text-muted">Expired This Week</small>
                @if ($stats['expired_week'] > 0)
                    <div><span class="badge bg-label-danger" style="font-size:.65rem">Action Required</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.rbac.delegations') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <div class="d-flex align-items-center gap-1">
                    <label class="text-muted small fw-medium">Filter by Org:</label>
                    <select name="org_id" class="form-select form-select-sm" style="min-width:160px">
                        <option value="">All Organizations</option>
                        @foreach ($organizations as $org)
                            <option value="{{ $org->id }}" @selected(request('org_id') == $org->id)>{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label class="text-muted small fw-medium">Status:</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:130px">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="expiring" @selected(request('status') === 'expiring')>Expiring Soon</option>
                        <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                        <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm text-white" style="background:var(--wb-maroon)">Apply</button>
                @if (request('org_id') || request('status'))
                    <a href="{{ route('admin.rbac.delegations') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Delegations table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">From User</th>
                            <th class="py-3">To User (Delegate)</th>
                            <th class="py-3">Organization</th>
                            <th class="py-3">Time Window</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($delegations as $d)
                            @php
                                $now      = now();
                                $isActive = $d->is_active && $now->between($d->starts_at, $d->expires_at);
                                $isExpiring = $isActive && $d->expires_at->isToday();
                                $isExpired= $now->isAfter($d->expires_at);
                                $isRevoked= !$d->is_active;
                            @endphp
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar avatar-xs rounded-circle text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                             style="background:var(--wb-maroon);width:30px;height:30px;font-size:.65rem">
                                            {{ strtoupper(substr(optional($d->fromUser)->name ?? '?', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-medium small">{{ optional($d->fromUser)->name ?? 'Unknown' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar avatar-xs rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                             style="background:#dee2e6;color:#495057;width:30px;height:30px;font-size:.65rem">
                                            {{ strtoupper(substr(optional($d->toUser)->name ?? '?', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-medium small">{{ optional($d->toUser)->name ?? 'Unknown' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <small class="fw-medium">{{ optional($d->organization)->name ?? '—' }}</small>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">{{ $d->starts_at->format('M d') }} → {{ $d->expires_at->format('M d, Y') }}</small>
                                    @if ($isActive && !$isExpiring)
                                        <br><small class="text-success">{{ $d->expires_at->diffForHumans() }}</small>
                                    @elseif ($isExpiring)
                                        <br><small class="text-warning fw-medium">Expires today</small>
                                    @elseif ($isExpired)
                                        <br><small class="text-muted">Past period</small>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @if ($isRevoked)
                                        <span class="badge bg-label-secondary">REVOKED</span>
                                    @elseif ($isExpired)
                                        <span class="badge bg-label-secondary">EXPIRED</span>
                                    @elseif ($isExpiring)
                                        <span class="badge bg-label-warning text-dark">EXPIRING</span>
                                    @elseif ($isActive)
                                        <span class="badge bg-label-success">ACTIVE</span>
                                    @else
                                        <span class="badge bg-label-info">PENDING</span>
                                    @endif
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    @if ($d->is_active && !$isExpired)
                                        <form action="{{ route('admin.rbac.delegations.revoke', $d->id) }}" method="POST" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-text-danger" style="font-size:.8rem;font-weight:600;color:#dc3545"
                                                    onclick="return confirm('Revoke this delegation?')">
                                                Revoke
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">Archived</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="ti ti-arrows-exchange fs-2 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-0">No delegations found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($delegations->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4">
                {{ $delegations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

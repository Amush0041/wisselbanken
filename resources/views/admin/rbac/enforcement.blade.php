@extends('admin.layouts.app')

@section('seo')
<title>Enforcement Mode | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    .mode-card {
        border: 2px solid #dee2e6; border-radius: .75rem;
        padding: 1.5rem; cursor: pointer; transition: border-color .15s, box-shadow .15s;
        height: 100%;
    }
    .mode-card.active-mode {
        border-color: #6b1c1c; box-shadow: 0 0 0 3px rgba(107,28,28,.12);
    }
    .mode-card .mode-icon {
        width: 48px; height: 48px; border-radius: .6rem;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; margin-bottom: 1rem;
    }
    .batch-toggle { width: 44px; height: 24px; position: relative; display: inline-block; }
    .batch-toggle input { opacity: 0; width: 0; height: 0; }
    .batch-toggle .slider {
        position: absolute; inset: 0; background: #dee2e6;
        border-radius: 24px; cursor: pointer; transition: .2s;
    }
    .batch-toggle input:checked + .slider { background: #6b1c1c; }
    .batch-toggle .slider::before {
        content: ""; position: absolute; height: 18px; width: 18px;
        left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s;
    }
    .batch-toggle input:checked + .slider::before { transform: translateX(20px); }
    .activity-item { border-left: 3px solid #6b1c1c; padding: .6rem 1rem; margin-bottom: .75rem; background: #fff; border-radius: 0 .5rem .5rem 0; }
    .activity-item.warn { border-left-color: #e67e22; }
    .activity-item.info { border-left-color: #2196f3; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Enforcement Mode</h4>
            <p class="text-muted small mb-0">Control how RBAC policies are applied across the platform</p>
        </div>
        <span class="badge bg-label-secondary">PLATFORM ADMIN</span>
    </div>

    @include('admin.rbac._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3">
            <i class="ti ti-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Security warning banner --}}
    <div class="alert mb-4" style="background:#fff3f3;border:1px solid #f5c6c6;border-radius:.75rem">
        <div class="d-flex gap-2">
            <i class="ti ti-alert-triangle text-danger fs-5 mt-1 flex-shrink-0"></i>
            <div>
                <strong class="text-danger">Security Escalation Warning</strong><br>
                <small class="text-muted">Switching to Enforce Mode will immediately block non-compliant users. Audit Mode passively logs violations without blocking access. Both modes write to the Audit Trail.</small>
            </div>
        </div>
    </div>

    {{-- Mode toggle cards --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="mode-card {{ $mode === 'audit' ? 'active-mode' : '' }}">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="mode-icon" style="background:#fff8e1">
                        <i class="ti ti-eye" style="color:#e65100"></i>
                    </div>
                    @if ($mode === 'audit')
                        <span class="badge bg-label-warning">ACTIVE</span>
                    @else
                        <span class="badge bg-label-secondary">PASSIVE</span>
                    @endif
                </div>
                <h5 class="fw-bold mb-1">Audit Mode</h5>
                <p class="text-muted small mb-3">Passively monitor access requests without blocking. Generates violation reports for review. Recommended for new permission rollout phases.</p>
                <form action="{{ route('admin.rbac.enforcement.toggle-mode') }}" method="POST">
                    @csrf
                    <input type="hidden" name="mode" value="audit">
                    @if ($mode === 'audit')
                        <button type="button" class="btn btn-outline-secondary w-100" disabled>Currently in Audit Mode</button>
                    @else
                        <button type="submit" class="btn btn-outline-secondary w-100"
                                onclick="return confirm('Switch to Audit Mode? Non-compliant requests will be logged but not blocked.')">
                            Select Audit Mode
                        </button>
                    @endif
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="mode-card {{ $mode === 'enforce' ? 'active-mode' : '' }}" style="{{ $mode === 'enforce' ? 'background:#fdf6f6' : '' }}">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="mode-icon" style="background:#fde8e8">
                        <i class="ti ti-shield-lock" style="color:#6b1c1c"></i>
                    </div>
                    @if ($mode === 'enforce')
                        <span class="badge text-white" style="background:#6b1c1c">● ACTIVE</span>
                    @else
                        <span class="badge bg-label-secondary">PASSIVE</span>
                    @endif
                </div>
                <h5 class="fw-bold mb-1">Enforce Mode</h5>
                <p class="text-muted small mb-3">Strictly enforce all RBAC policies. Non-compliant attempts are blocked instantly. Real-time session termination enabled for policy drift.</p>
                <form action="{{ route('admin.rbac.enforcement.toggle-mode') }}" method="POST">
                    @csrf
                    <input type="hidden" name="mode" value="enforce">
                    @if ($mode === 'enforce')
                        <button type="button" class="btn w-100 text-white" style="background:#6b1c1c" disabled>Stay in Enforce Mode</button>
                    @else
                        <button type="submit" class="btn w-100 text-white" style="background:#6b1c1c"
                                onclick="return confirm('Switch to Enforce Mode? Non-compliant requests will be blocked immediately.')">
                            Switch to Enforce Mode
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>

    {{-- Enforcement Batches table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-semibold mb-0">Enforcement Batches</h6>
                    <small class="text-muted">Manage granular enforcement per organization tier</small>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Batch Name</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Would-Block</th>
                            <th class="py-3">Blocked</th>
                            <th class="py-3 pe-4 text-end">Enforced</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byBatch as $batch => $rows)
                            @php
                                $would   = optional($rows->firstWhere('outcome','would_block'))->total ?? 0;
                                $blocked = optional($rows->firstWhere('outcome','blocked'))->total ?? 0;
                                $isEnforced = $mode === 'enforce' && (count($enforceBatches) === 0 || in_array($batch, $enforceBatches, true));
                            @endphp
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ti ti-stack-2 text-muted"></i>
                                        <strong>{{ $batch ?? 'Unbatched' }}</strong>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge {{ $isEnforced ? 'bg-label-danger' : 'bg-label-warning' }}">
                                        {{ $isEnforced ? 'ENFORCED' : 'MONITORING' }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="text-warning fw-medium">{{ number_format($would) }}</span>
                                </td>
                                <td class="py-3">
                                    <span class="text-danger fw-medium">{{ number_format($blocked) }}</span>
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    <label class="batch-toggle">
                                        <input type="checkbox" {{ $isEnforced ? 'checked' : '' }} disabled title="Toggle via .env RBAC_ENFORCE_BATCHES">
                                        <span class="slider"></span>
                                    </label>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="ti ti-stack-2 fs-2 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-0">No audit activity recorded yet.</p>
                                    <small class="text-muted">Activity will appear here once RBAC middleware is active.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Recent Enforcement Activity --}}
    <div class="row">
        <div class="col-12">
            <h6 class="fw-semibold mb-3">Recent Enforcement Activity</h6>
            @forelse ($recentActivity as $log)
                @php
                    $isBlocked = $log->outcome === 'blocked';
                    $isWouldBlock = $log->outcome === 'would_block';
                @endphp
                <div class="activity-item {{ $isBlocked ? '' : ($isWouldBlock ? 'warn' : 'info') }}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="fw-medium mb-0 small">
                                @if ($isBlocked)
                                    <span class="badge bg-label-danger me-1">Blocked</span>
                                @elseif ($isWouldBlock)
                                    <span class="badge bg-label-warning me-1">Would Block</span>
                                @elseif ($log->outcome === 'allowed_universal_admin')
                                    <span class="badge bg-label-info me-1">Platform admin</span>
                                @else
                                    <span class="badge bg-label-secondary me-1">Logged</span>
                                @endif
                                {{ $log->permission_group ?? 'Unknown' }}
                                @if ($log->required_level)
                                    — requires <strong>{{ $log->required_level }}</strong>
                                @endif
                            </p>
                            <small class="text-muted">
                                {{ optional($log->user)->name ?? 'Unknown user' }}
                                @if ($log->route) · {{ $log->route }} @endif
                                @if ($log->batch) · Batch {{ $log->batch }} @endif
                            </small>
                        </div>
                        <small class="text-muted flex-shrink-0">{{ $log->created_at?->diffForHumans() ?? '—' }}</small>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm text-center py-5 text-muted">
                    <i class="ti ti-shield-check fs-2 d-block mb-2"></i>
                    <p class="mb-0">No recent enforcement activity</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

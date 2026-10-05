@extends('admin.layouts.app')

@section('seo')
<title>Audit Trail | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Audit Trail</h4>
            <p class="text-muted small mb-0">System events and access logs</p>
        </div>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-download me-1"></i> Export CSV
        </a>
    </div>

    @include('admin.rbac._nav')

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-medium mb-1">Event Type / Outcome</label>
                    <select name="outcome" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Events</option>
                        <option value="would_block" @selected(request('outcome')==='would_block')>Would Block (audit mode)</option>
                        <option value="blocked" @selected(request('outcome')==='blocked')>Blocked (enforce mode)</option>
                        <option value="allowed_universal_admin" @selected(request('outcome')==='allowed_universal_admin')>Allowed (platform admin)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium mb-1">Permission Group</label>
                    <select name="permission_group" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Permission Groups</option>
                        @foreach ($groups as $slug => $name)
                            <option value="{{ $slug }}" @selected(request('permission_group')===$slug)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @if(request()->hasAny(['outcome','permission_group']))
                    <div class="col-md-2">
                        <a href="{{ route('admin.rbac.audit-logs') }}" class="btn btn-sm btn-outline-secondary w-100">Clear filters</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    {{-- Audit table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:.83rem">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Timestamp</th>
                            <th class="py-3">User</th>
                            <th class="py-3">Action / Route</th>
                            <th class="py-3">Permission Group</th>
                            <th class="py-3">Required</th>
                            <th class="py-3">Batch</th>
                            <th class="py-3">Reason</th>
                            <th class="py-3 pe-4 text-end">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="ps-4 py-3">
                                    <span class="text-muted" style="font-variant-numeric:tabular-nums">
                                        {{ $log->created_at->format('Y-m-d') }}<br>
                                        <small>{{ $log->created_at->format('H:i:s') }}</small>
                                    </span>
                                </td>
                                <td class="py-3">
                                    @if ($log->user)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-xs rounded-circle bg-label-primary flex-shrink-0">
                                                {{ strtoupper(substr($log->user->name, 0, 2)) }}
                                            </span>
                                            <div>
                                                <div class="fw-medium">{{ $log->user->name }}</div>
                                                <small class="text-muted">{{ $log->user->email }}</small>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-label-secondary me-1">{{ $log->method }}</span>
                                    <code class="small">{{ $log->matched_pattern ?? $log->route_uri }}</code>
                                </td>
                                <td class="py-3">
                                    <span class="text-body-secondary small">{{ $log->permission_group }}</span>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge bg-label-dark fw-bold" style="min-width:28px">{{ $log->required_level }}</span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-label-info small">{{ $log->batch ?? '—' }}</span>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">{{ str_replace('_', ' ', $log->reason) }}</small>
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    @if ($log->outcome === 'blocked')
                                        <span class="badge bg-danger">Denied</span>
                                    @elseif ($log->outcome === 'allowed_universal_admin')
                                        <span class="badge bg-info">Platform admin</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Would Block</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="ti ti-shield-check fs-2 text-success d-block mb-2"></i>
                                    <p class="text-muted mb-0">No audit entries match your filters.</p>
                                    <small class="text-muted">Entries appear when a user attempts an action their role doesn't permit.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="card-footer border-top-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ number_format($logs->total()) }} entries
                    </small>
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Footer note --}}
    <div class="mt-3 d-flex align-items-center gap-2 text-muted small">
        <i class="ti ti-lock"></i>
        <span>Audit logs are immutable once written. <code>would_block</code> and <code>blocked</code> outcomes are recorded, as are universal-admin bypasses (<code>allowed_universal_admin</code>); other permitted requests are not logged.</span>
    </div>

</div>
@endsection

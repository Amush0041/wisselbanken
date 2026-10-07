@extends('admin.layouts.app')

@section('seo')
<title>RBAC Overview | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>
            <h4 class="mb-0 fw-bold">RBAC Management</h4>
            <p class="text-muted small mb-0">Role-based access control for the Wisselbanken platform</p>
        </div>
        {{-- Mode badge — hidden for now, enable in Phase 3
        <span class="badge px-3 py-2 {{ $mode === 'enforce' ? 'bg-label-danger' : 'bg-label-warning' }}">
            <i class="ti {{ $mode === 'enforce' ? 'ti-shield-lock' : 'ti-shield' }} me-1"></i>
            {{ strtoupper($mode) }} MODE
        </span>
        --}}
    </div>

    @include('admin.rbac._nav')

    @if ($enforcedOrgs->isNotEmpty())
        <div class="alert alert-warning py-2 mb-3" style="font-size:.82rem">
            <i class="ti ti-shield-lock me-1"></i>
            <strong>Enforced organizations:</strong> {{ $enforcedOrgs->implode(', ') }}
        </div>
    @endif

    {{-- Compact stats row --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row gy-3">
                <div class="col-6 col-md-3">
                    <a href="{{ route('admin.rbac.organizations') }}" class="d-flex align-items-center gap-3 text-decoration-none">
                        <div class="badge rounded bg-label-primary p-2"><i class="ti ti-building ti-lg"></i></div>
                        <div><h5 class="mb-0">{{ number_format($stats['organizations']) }}</h5><small class="text-muted">Organizations</small></div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('admin.rbac.roles') }}" class="d-flex align-items-center gap-3 text-decoration-none">
                        <div class="badge rounded bg-label-info p-2"><i class="ti ti-id-badge-2 ti-lg"></i></div>
                        <div><h5 class="mb-0">{{ number_format($stats['roles']) }}</h5><small class="text-muted">Roles</small></div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('admin.rbac.users') }}" class="d-flex align-items-center gap-3 text-decoration-none">
                        <div class="badge rounded bg-label-success p-2"><i class="ti ti-users ti-lg"></i></div>
                        <div><h5 class="mb-0">{{ number_format($stats['active_assignments']) }}</h5><small class="text-muted">Active Assignments</small></div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('admin.rbac.roles') }}" class="d-flex align-items-center gap-3 text-decoration-none">
                        <div class="badge rounded bg-label-secondary p-2"><i class="ti ti-key ti-lg"></i></div>
                        <div><h5 class="mb-0">{{ number_format($stats['permission_groups']) }}</h5><small class="text-muted">Permission Groups</small></div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body py-3">
            <p class="text-muted small fw-semibold text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">Quick Actions</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.rbac.roles') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-table me-1"></i> Role Designer
                </a>
                <a href="{{ route('admin.rbac.organizations') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-building me-1"></i> Organizations
                </a>
                <a href="{{ route('admin.rbac.users') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-users me-1"></i> Users
                </a>
                <a href="{{ route('admin.rbac.delegations') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-arrows-exchange me-1"></i> Delegations
                </a>
                <a href="{{ route('admin.rbac.sod') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-git-compare me-1"></i> SoD Rules
                </a>
            </div>
        </div>
    </div>

</div>
@endsection

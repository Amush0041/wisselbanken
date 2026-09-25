@extends('user.layouts.app')

@section('seo')
<title>Projects | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
@php
    $statusLabel = fn (string $s) => ucfirst(str_replace('_', ' ', $s));
    $statusClass = [
        'active' => 'bg-label-success',
        'on_hold' => 'bg-label-warning',
        'awarded' => 'bg-label-primary',
        'lost' => 'bg-label-danger',
        'archived' => 'bg-label-secondary',
    ];
@endphp
<div class="container-fluid flex-grow-1 container-p-y user-page">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="page-title mb-1">Projects</h4>
            <p class="page-description mb-0">Projects you are a member of. Estimates and the plan crosswalk live inside a project.</p>
        </div>
        @canDo('project_management', 'S')
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createProjectModal">
            <i class="ti ti-plus me-1"></i>New Project
        </button>
        @endCanDo
    </div>

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-3">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if ($errors->any())
    <div class="alert alert-danger mb-3">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3">
                <label class="form-label mb-0 fw-semibold text-nowrap" for="projectStatusFilter">Filter by Status</label>
                <select id="projectStatusFilter" class="form-select form-select-sm" style="max-width:220px">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}">{{ $statusLabel($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-3">
            <h6 class="mb-0">
                All Projects
                <span class="badge bg-label-secondary ms-1">{{ $projects->count() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            @if ($projects->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-folders" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">No projects yet.</p>
                    @canDo('project_management', 'S')
                    <small>Use <strong>New Project</strong> to create one.</small>
                    @endCanDo
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Address</th>
                            <th>Bid Due</th>
                            <th class="text-end">Estimates</th>
                            <th class="text-end">Members</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                        <tr data-status="{{ $project->status }}">
                            <td>
                                <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-body">{{ $project->name }}</a>
                            </td>
                            <td>
                                <span class="badge {{ $statusClass[$project->status] ?? 'bg-label-secondary' }}" style="font-size:.7rem">{{ $statusLabel($project->status) }}</span>
                            </td>
                            <td class="small text-muted">{{ $project->address ? Str::limit($project->address, 50) : '—' }}</td>
                            <td class="small text-muted">{{ $project->bid_due_at ? $project->bid_due_at->format('M d, Y H:i') : '—' }}</td>
                            <td class="text-end small">{{ $project->quotes_count }}</td>
                            <td class="text-end small">{{ $project->members_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('projects.show', $project) }}" class="btn btn-xs btn-label-primary">Open</a>
                            </td>
                        </tr>
                        @endforeach
                        <tr id="projectFilterEmpty" style="display:none">
                            <td colspan="7" class="text-center text-muted py-4">No projects with this status.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

@canDo('project_management', 'S')
<div class="modal fade" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('projects.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" @selected(old('status', 'active') === $s)>{{ $statusLabel($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Site address">{{ old('address') }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Bid Due</label>
                        <input type="datetime-local" name="bid_due_at" class="form-control" value="{{ old('bid_due_at') }}">
                        <div class="form-text">Time zone: {{ config('app.timezone') }}.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i>Create Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endCanDo
@endsection

@push('scripts')
<script>
    (function () {
        var filter = document.getElementById('projectStatusFilter');
        if (filter) {
            filter.addEventListener('change', function () {
                var visible = 0;
                document.querySelectorAll('tr[data-status]').forEach(function (row) {
                    var show = !filter.value || row.dataset.status === filter.value;
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                var empty = document.getElementById('projectFilterEmpty');
                if (empty) empty.style.display = visible ? 'none' : '';
            });
        }
        @if ($errors->any())
        var modalEl = document.getElementById('createProjectModal');
        if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        @endif
    })();
</script>
@endpush

@extends('user.layouts.app')

@section('seo')
<title>{{ $project->name }} – Project | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
@php
    $statusLabel = fn (string $s) => ucfirst(str_replace('_', ' ', $s));
@endphp
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .ws-header { background: linear-gradient(135deg, var(--wb-maroon) 0%, #3d0f0f 100%); border-radius: .75rem; padding: 1.25rem 1.5rem; color: #fff; margin-bottom: 1.25rem; }
    .ws-header h4 { color: #fff; margin-bottom: .15rem; }
    .ws-header .meta { font-size: .82rem; opacity: .8; }
    .ws-badge { background: rgba(255,255,255,.15); border-radius: 2rem; padding: .15rem .6rem; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
</style>

<div class="container-fluid flex-grow-1 container-p-y user-page">

    <div class="ws-header">
        <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="{{ route('projects.index') }}" class="text-white opacity-75" style="text-decoration:none;font-size:.82rem">
                        <i class="ti ti-arrow-left me-1"></i>Projects
                    </a>
                </div>
                <h4 class="fw-bold mb-0">{{ $project->name }}</h4>
                <div class="meta mt-1">
                    <span class="ws-badge">{{ $statusLabel($project->status) }}</span>
                    @if ($project->address)
                        &nbsp;·&nbsp; {{ Str::limit($project->address, 80) }}
                    @endif
                    @if ($project->bid_due_at)
                        &nbsp;·&nbsp; Bid due {{ $project->bid_due_at->format('M d, Y H:i') }} ({{ config('app.timezone') }})
                    @endif
                    &nbsp;·&nbsp; Created {{ $project->created_at->format('M d, Y') }}
                </div>
            </div>
            <div class="d-flex gap-2">
                @if ($canUpdateProject)
                <button class="btn btn-sm btn-light" style="color:var(--wb-maroon)" data-bs-toggle="modal" data-bs-target="#editProjectModal">
                    <i class="ti ti-pencil me-1"></i>Edit
                </button>
                @endif
                @if ($canDeleteProject)
                <button class="btn btn-sm btn-light text-danger" data-bs-toggle="modal" data-bs-target="#deleteProjectModal">
                    <i class="ti ti-trash me-1"></i>Delete
                </button>
                @endif
            </div>
        </div>
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

    <div class="row g-3">

        <div class="col-lg-4">

            <div class="card mb-3">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Project Members</h6>
                    @if ($canManageMembers)
                    <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                        <i class="ti ti-plus me-1"></i>Add
                    </button>
                    @endif
                </div>
                <div class="card-body">
                    @forelse ($projectMembers as $pm)
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar avatar-xs">
                                    <span class="avatar-initial rounded-circle" style="background:var(--wb-maroon-light);color:var(--wb-maroon);font-size:.65rem">
                                        {{ strtoupper(substr($pm->user?->name ?? '?', 0, 1)) }}
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-medium small">{{ $pm->user?->name ?? 'Unknown' }}</div>
                                    <div class="text-muted" style="font-size:.7rem">{{ $pm->user?->email ?? '' }}</div>
                                </div>
                            </div>
                            @if ($canManageMembers)
                            <form action="{{ route('projects.members.destroy', [$project, $pm]) }}" method="POST"
                                  onsubmit="return confirm('Remove from project?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remove">
                                    <i class="ti ti-x"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No members assigned to this project yet.</p>
                    @endforelse
                </div>
            </div>

            @if ($canReadEstimates)
            <div class="card">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Plan Crosswalk
                        <span class="badge bg-label-secondary ms-1">{{ $crosswalkEntries->count() }}</span>
                    </h6>
                    @if ($canManageCrosswalk)
                    <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#addCrosswalkModal">
                        <i class="ti ti-plus me-1"></i>Add
                    </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if ($crosswalkEntries->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-map-2" style="font-size:1.5rem"></i>
                            <p class="small mt-1 mb-0">No crosswalk entries for this project.</p>
                        </div>
                    @else
                    <ul class="list-group list-group-flush">
                        @foreach ($crosswalkEntries->take(8) as $cw)
                        <li class="list-group-item py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <code class="small text-primary">{{ $cw->plan_line_code }}</code>
                                @if ($cw->manufacturer_part_number)
                                    <small class="text-muted">{{ $cw->manufacturer_part_number }}</small>
                                @endif
                            </div>
                            @if ($cw->description)
                                <div class="text-muted" style="font-size:.72rem">{{ $cw->description }}</div>
                            @endif
                        </li>
                        @endforeach
                        @if ($crosswalkEntries->count() > 8)
                        <li class="list-group-item py-2 px-3 text-center">
                            <a href="{{ route('plan-crosswalk.index', ['project_id' => $project->id]) }}" class="small">
                                View all {{ $crosswalkEntries->count() }} entries →
                            </a>
                        </li>
                        @endif
                    </ul>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-8">
            @if ($canReadEstimates)
            <div class="card">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Estimates
                        <span class="badge bg-label-secondary ms-1">{{ $quotes->count() }}</span>
                    </h6>
                    @if ($canCreateEstimates)
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEstimateModal">
                        <i class="ti ti-plus me-1"></i>Add estimate
                    </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if ($quotes->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="ti ti-clipboard-list" style="font-size:2rem"></i>
                            <p class="mt-2 mb-0">No estimates in this project yet.</p>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Estimate</th>
                                    <th>Owner</th>
                                    <th>Status</th>
                                    <th class="text-end">Items</th>
                                    <th class="text-end">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotes as $quote)
                                <tr>
                                    <td class="small">
                                        <a href="{{ route('quotes.index') }}" class="fw-medium text-body">{{ $quote->name ?: ('Estimate #' . $quote->id) }}</a>
                                    </td>
                                    <td class="small text-muted">{{ $quote->user?->name ?? 'Unknown' }}</td>
                                    <td><span class="badge bg-label-secondary" style="font-size:.65rem">{{ ucfirst($quote->status ?? 'draft') }}</span></td>
                                    <td class="text-end small">{{ $quote->items_count }}</td>
                                    <td class="text-end small text-muted">{{ $quote->created_at->format('M d, Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
            @else
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="ti ti-lock" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">You do not have access to the estimates in this project.</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@if ($canUpdateProject)
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('projects.update', $project) }}" method="POST">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $project->name) }}" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            @foreach (\App\Models\Project::STATUSES as $s)
                                <option value="{{ $s }}" @selected(old('status', $project->status) === $s)>{{ $statusLabel($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $project->address) }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Bid Due</label>
                        <input type="datetime-local" name="bid_due_at" class="form-control"
                               value="{{ old('bid_due_at', $project->bid_due_at?->format('Y-m-d\TH:i')) }}">
                        <div class="form-text">Time zone: {{ config('app.timezone') }}.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canDeleteProject)
<div class="modal fade" id="deleteProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('projects.destroy', $project) }}" method="POST">
                @csrf @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title">Delete Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Delete <strong>{{ $project->name }}</strong>?</p>
                    <div class="alert alert-warning py-2 mb-0 small">
                        <i class="ti ti-alert-triangle me-1"></i>
                        A project cannot be deleted while it still has estimates. Delete its estimates first.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm" @disabled($project->quotes()->exists())>Delete Project</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canManageMembers)
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('projects.members.store', $project) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Project Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Team member</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">{{ $orgMembers->isEmpty() ? 'All organization members are already in this project' : 'Select a member…' }}</option>
                        @foreach ($orgMembers as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($orgMembers->isEmpty())>Add to Project</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canCreateEstimates)
<div class="modal fade" id="addEstimateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addEstimateForm" action="{{ route('projects.quotes.store', $project) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Estimate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 small d-none" id="addEstimateError"></div>
                    <div class="mb-3">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Select customer</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                        @if ($customers->isEmpty())
                            <div class="form-text">You have no customers yet. Add one under Customers first.</div>
                        @endif
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Estimate label</label>
                        <input type="text" name="estimate_label" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="addEstimateSubmit">Create Estimate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canManageCrosswalk)
<div class="modal fade" id="addCrosswalkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('projects.crosswalk.store', $project) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Crosswalk Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Plan Line Code <span class="text-danger">*</span></label>
                        <input type="text" name="plan_line_code" class="form-control font-monospace"
                            placeholder="e.g. 06-1000, A-102" required maxlength="100">
                        <div class="form-text">Your plan's own line item identifier.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WisselBanken Product ID (SKU)</label>
                        <input type="number" name="product_id" class="form-control" min="1" placeholder="Leave blank if not yet mapped">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manufacturer Part Number</label>
                        <input type="text" name="manufacturer_part_number" class="form-control" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" maxlength="255">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i>Add Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if ($canCreateEstimates)
@push('scripts')
<script>
document.getElementById('addEstimateForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btn = document.getElementById('addEstimateSubmit');
    const err = document.getElementById('addEstimateError');
    err.classList.add('d-none');
    btn.disabled = true;

    fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: new FormData(form)
    }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) { return { ok: res.ok, data: data }; });
    }).then(function (r) {
        if (r.ok && r.data.success) {
            window.location.reload();
            return;
        }
        const errors = r.data.errors ? Object.values(r.data.errors).flat().join(' ') : '';
        err.textContent = errors || r.data.message || 'Failed to create estimate.';
        err.classList.remove('d-none');
        btn.disabled = false;
    }).catch(function () {
        err.textContent = 'Failed to create estimate.';
        err.classList.remove('d-none');
        btn.disabled = false;
    });
});
</script>
@endpush
@endif
@endsection

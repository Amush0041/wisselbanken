@extends('user.layouts.app')

@section('seo')
<title>Plan Crosswalk - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="page-title mb-1">Plan Crosswalk</h4>
            <p class="page-description mb-0">Map your plan line item codes to WisselBanken SKUs and manufacturer part numbers, scoped by project.</p>
        </div>
        @canDo('estimate_management', 'F')
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal">
            <i class="ti ti-plus me-1"></i>Add Entry
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

    {{-- Project filter --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('plan-crosswalk.index') }}" class="d-flex align-items-center gap-3">
                <label class="form-label mb-0 fw-semibold text-nowrap">Filter by Project</label>
                <select name="project_id" class="form-select form-select-sm" style="max-width:320px" onchange="this.form.submit()">
                    <option value="">All projects</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedQuoteId == $project->id)>
                            {{ $project->title ?? 'Quote #' . $project->id }}
                            ({{ $project->created_at->format('M Y') }})
                        </option>
                    @endforeach
                </select>
                @if ($selectedQuoteId)
                    <a href="{{ route('plan-crosswalk.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Crosswalk table --}}
    <div class="card">
        <div class="card-header py-3">
            <h6 class="mb-0">
                Crosswalk Entries
                <span class="badge bg-label-secondary ms-1">{{ $rows->count() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            @if ($rows->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-map-2" style="font-size:2rem"></i>
                    <p class="mt-2 mb-0">No crosswalk entries yet.</p>
                    @canDo('estimate_management', 'F')
                    <small>Use <strong>Add Entry</strong> to map a plan line item code to a SKU.</small>
                    @endCanDo
                    @cannotDo('estimate_management', 'F')
                    <small>Contact your Estimator or Project Manager to add entries.</small>
                    @endCannotDo
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Project</th>
                            <th>Plan Line Code</th>
                            <th>WisselBanken SKU</th>
                            <th>Mfr Part #</th>
                            <th>Description</th>
                            <th>Added by</th>
                            @canDo('estimate_management', 'F')
                            <th></th>
                            @endCanDo
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                        <tr>
                            <td>
                                <small class="text-muted">{{ $row->quote?->title ?? 'Quote #' . $row->quote_id }}</small>
                            </td>
                            <td>
                                <span class="badge bg-label-primary font-monospace">{{ $row->plan_line_code }}</span>
                            </td>
                            <td>
                                @if ($row->product)
                                    <a href="#" class="text-body">{{ $row->product->name ?? 'SKU #' . $row->product_id }}</a>
                                    <small class="text-muted d-block">ID: {{ $row->product_id }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($row->manufacturer_part_number)
                                    <code class="small">{{ $row->manufacturer_part_number }}</code>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted small">{{ $row->description ?? '—' }}</span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $row->creator?->name ?? '—' }}</small>
                            </td>
                            @canDo('estimate_management', 'F')
                            <td class="text-end">
                                <button class="btn btn-sm btn-icon btn-text-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal{{ $row->id }}"
                                    title="Edit">
                                    <i class="ti ti-pencil"></i>
                                </button>
                                <form action="{{ route('plan-crosswalk.destroy', $row) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Remove this crosswalk entry?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remove">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </td>
                            @endCanDo
                        </tr>

                        {{-- Edit modal per row --}}
                        @canDo('estimate_management', 'F')
                        <div class="modal fade" id="editModal{{ $row->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="{{ route('plan-crosswalk.update', $row) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Crosswalk Entry</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Plan Line Code <span class="text-danger">*</span></label>
                                                <input type="text" name="plan_line_code" class="form-control font-monospace"
                                                    value="{{ $row->plan_line_code }}" required maxlength="100">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">WisselBanken Product ID (SKU)</label>
                                                <input type="number" name="product_id" class="form-control"
                                                    value="{{ $row->product_id }}" min="1" placeholder="Leave blank if unknown">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Manufacturer Part Number</label>
                                                <input type="text" name="manufacturer_part_number" class="form-control"
                                                    value="{{ $row->manufacturer_part_number }}" maxlength="100">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <input type="text" name="description" class="form-control"
                                                    value="{{ $row->description }}" maxlength="255">
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Notes</label>
                                                <textarea name="notes" class="form-control" rows="2">{{ $row->notes }}</textarea>
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
                        @endCanDo

                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Add entry modal --}}
@canDo('estimate_management', 'F')
<div class="modal fade" id="addEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('plan-crosswalk.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Crosswalk Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Project <span class="text-danger">*</span></label>
                        <select name="quote_id" class="form-select" required>
                            <option value="">Select a project…</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected($selectedQuoteId == $project->id)>
                                    {{ $project->title ?? 'Quote #' . $project->id }}
                                </option>
                            @endforeach
                        </select>
                        @if ($projects->isEmpty())
                            <div class="form-text text-warning">No projects found. Create a quote/estimate first.</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Plan Line Code <span class="text-danger">*</span></label>
                        <input type="text" name="plan_line_code" class="form-control font-monospace"
                            placeholder="e.g. 06-1000, A-102" required maxlength="100">
                        <div class="form-text">Your plan's own line item identifier.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WisselBanken Product ID (SKU)</label>
                        <input type="number" name="product_id" class="form-control"
                            min="1" placeholder="Leave blank if not yet mapped">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manufacturer Part Number</label>
                        <input type="text" name="manufacturer_part_number" class="form-control"
                            placeholder="e.g. MFR-XYZ-001" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control"
                            placeholder="Brief description of the item" maxlength="255">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                            placeholder="Any additional mapping notes…"></textarea>
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
@endCanDo
@endsection

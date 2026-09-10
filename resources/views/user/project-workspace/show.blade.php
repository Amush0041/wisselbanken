@extends('user.layouts.app')

@section('seo')
<title>{{ $quote->name ?: 'Project #' . $quote->id }} – Workspace | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .ws-header { background: linear-gradient(135deg, var(--wb-maroon) 0%, #3d0f0f 100%); border-radius: .75rem; padding: 1.25rem 1.5rem; color: #fff; margin-bottom: 1.25rem; }
    .ws-header h4 { color: #fff; margin-bottom: .15rem; }
    .ws-header .meta { font-size: .82rem; opacity: .8; }
    .ws-badge { background: rgba(255,255,255,.15); border-radius: 2rem; padding: .15rem .6rem; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .section-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #8a8d93; margin-bottom: .6rem; }
    .member-chip { display: inline-flex; align-items: center; gap: .3rem; background: var(--wb-maroon-light); border-radius: 2rem; padding: .2rem .65rem; font-size: .78rem; font-weight: 500; color: var(--wb-maroon); }
</style>

<div class="container-fluid flex-grow-1 container-p-y user-page">

    {{-- Project header --}}
    <div class="ws-header">
        <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <a href="{{ route('org-admin.projects.index') }}" class="text-white opacity-75" style="text-decoration:none;font-size:.82rem">
                        <i class="ti ti-arrow-left me-1"></i>Projects
                    </a>
                </div>
                <h4 class="fw-bold mb-0">{{ $quote->name ?: 'Quote #' . $quote->id }}</h4>
                <div class="meta mt-1">
                    Quote # {{ $quote->quote_number ?? $quote->id }}
                    &nbsp;·&nbsp; Created {{ $quote->created_at->format('M d, Y') }}
                    @if ($quote->status)
                        &nbsp;·&nbsp; <span class="ws-badge">{{ ucfirst($quote->status) }}</span>
                    @endif
                </div>
            </div>
            @canDo('estimate_management', 'O')
            <a href="{{ route('quotes.details', $quote->id) }}" class="btn btn-sm btn-light" style="color:var(--wb-maroon)">
                <i class="ti ti-external-link me-1"></i>Open Full Estimate
            </a>
            @endCanDo
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

    <div class="row g-3">

        {{-- Left column: members + crosswalk --}}
        <div class="col-lg-4">

            {{-- Project Members --}}
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
                            <form action="{{ route('org-admin.projects.members.destroy', $pm->id) }}" method="POST"
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

            {{-- Plan Crosswalk (project-scoped) --}}
            <div class="card">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Plan Crosswalk</h6>
                    @canDo('estimate_management', 'F')
                    <a href="{{ route('plan-crosswalk.index', ['project_id' => $quote->id]) }}" class="btn btn-sm btn-outline-secondary py-0">
                        <i class="ti ti-external-link me-1"></i>Manage
                    </a>
                    @endCanDo
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
                            <a href="{{ route('plan-crosswalk.index', ['project_id' => $quote->id]) }}" class="small">
                                View all {{ $crosswalkEntries->count() }} entries →
                            </a>
                        </li>
                        @endif
                    </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right column: estimate line items --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="mb-0">Estimate Line Items
                            <span class="badge bg-label-secondary ms-1">{{ $quote->items->count() }}</span>
                        </h6>
                        @if ($quote->notes)
                        <button class="btn btn-sm btn-outline-secondary py-0" type="button"
                                data-bs-toggle="collapse" data-bs-target="#projectNotes">
                            <i class="ti ti-notes me-1"></i>Notes
                        </button>
                        @endif
                    </div>
                    @if ($quote->notes)
                    <div class="collapse mt-2" id="projectNotes">
                        <div class="alert alert-light mb-0 py-2">
                            <small style="white-space:pre-line">{{ $quote->notes }}</small>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if ($quote->items->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="ti ti-clipboard-list" style="font-size:2rem"></i>
                            <p class="mt-2 mb-0">No line items on this estimate yet.</p>
                            @canDo('estimate_management', 'S')
                            <a href="{{ route('quotes.details', $quote->id) }}" class="btn btn-sm btn-primary mt-2">
                                Open Estimate Editor
                            </a>
                            @endCanDo
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quote->items as $i => $item)
                                <tr>
                                    <td class="text-muted small">{{ $i + 1 }}</td>
                                    <td>
                                        <div class="fw-medium small">{{ ucfirst($item->item_type ?? 'Item') }}</div>
                                        @if (!empty($item->description))
                                            <div class="text-muted" style="font-size:.71rem">{{ Str::limit($item->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end small">{{ $item->quantity ?? '—' }}</td>
                                    <td class="text-end small">${{ number_format($item->unit_price ?? 0, 2) }}</td>
                                    <td class="text-end small fw-medium">${{ number_format($item->subtotal ?? 0, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-semibold small">Total</td>
                                    <td class="text-end fw-bold">${{ number_format($quote->calculateTotal(), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                </div>
                @canDo('estimate_management', 'O')
                <div class="card-footer py-2 text-end">
                    <a href="{{ route('quotes.details', $quote->id) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-pencil me-1"></i>Edit in Estimate Editor
                    </a>
                    @canDo('quote_rfq_management', 'S')
                    <a href="{{ route('rfq.create') }}" class="btn btn-sm btn-primary ms-2">
                        <i class="ti ti-mail-forward me-1"></i>Send RFQ for this Project
                    </a>
                    @endCanDo
                </div>
                @endCanDo
            </div>
        </div>
    </div>
</div>

{{-- Add member modal --}}
@if ($canManageMembers && $orgMembers->isNotEmpty())
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('org-admin.projects.members.store') }}" method="POST">
                @csrf
                <input type="hidden" name="quote_id" value="{{ $quote->id }}">
                <div class="modal-header">
                    <h5 class="modal-title">Add Project Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Team member</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Select a member…</option>
                        @foreach ($orgMembers as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add to Project</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

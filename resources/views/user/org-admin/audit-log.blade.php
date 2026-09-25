@extends('user.layouts.app')

@section('seo')
<title>Audit Log | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .role-pill { display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:.35rem;background:var(--wb-maroon);color:#fff;font-size:.72rem;font-weight:600; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Audit Log</h4>
            <p class="text-muted small mb-0">Role changes and permission enforcement activity for <strong>{{ $org->name }}</strong></p>
        </div>
    </div>

    @include('user.org-admin._nav')

    {{-- Tab switcher --}}
    @php $activeTab = request('tab', 'roles'); @endphp
    <ul class="nav nav-pills mb-4 gap-1">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'roles' ? 'active' : '' }}"
               href="{{ route('org-admin.audit-log') }}?tab=roles"
               style="{{ $activeTab === 'roles' ? 'background:var(--wb-maroon)' : '' }}">
                <i class="ti ti-user-check me-1"></i> Role Changes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'enforcement' ? 'active' : '' }}"
               href="{{ route('org-admin.audit-log') }}?tab=enforcement"
               style="{{ $activeTab === 'enforcement' ? 'background:var(--wb-maroon)' : '' }}">
                <i class="ti ti-shield-lock me-1"></i> Enforcement Log
                @if ($enforcementLog->total() > 0)
                    <span class="badge bg-danger ms-1" style="font-size:.62rem">{{ number_format($enforcementLog->total()) }}</span>
                @endif
            </a>
        </li>
    </ul>

    @if ($activeTab === 'roles')

    {{-- Role changes filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('org-admin.audit-log') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="roles">
                <div class="col-sm-3">
                    <label class="form-label form-label-sm">From date</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                </div>
                <div class="col-sm-3">
                    <label class="form-label form-label-sm">To date</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div class="col-sm-3">
                    <label class="form-label form-label-sm">Member</label>
                    <select name="member_id" class="form-select form-select-sm">
                        <option value="">All Members</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected(request('member_id') == $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label form-label-sm">Action type</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <option value="assigned" @selected(request('action') === 'assigned')>Assigned</option>
                        <option value="removed" @selected(request('action') === 'removed')>Removed</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--wb-maroon)">Apply Filters</button>
                    @if (request('from') || request('to') || request('member_id') || request('action'))
                        <a href="{{ route('org-admin.audit-log') }}?tab=roles" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Role history table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3 small">Date/Time</th>
                            <th class="py-3 small">Member</th>
                            <th class="py-3 small">Action</th>
                            <th class="py-3 small">Role</th>
                            <th class="py-3 small">Assigned By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $entry)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-medium small">{{ $entry->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $entry->created_at->format('H:i') }} · {{ $entry->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                             style="width:28px;height:28px;background:var(--wb-maroon);font-size:.6rem">
                                            {{ strtoupper(substr(optional($entry->targetUser)->name ?? '?', 0, 2)) }}
                                        </div>
                                        <span class="fw-medium small">{{ optional($entry->targetUser)->name ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if ($entry->action === 'assigned')
                                        <span class="badge bg-label-success">Assigned</span>
                                    @else
                                        <span class="badge bg-label-danger">Removed</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <span class="role-pill">{{ optional($entry->role)->name ?? '—' }}</span>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">{{ optional($entry->performedBy)->name ?? 'System' }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="ti ti-clipboard-list fs-2 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-0">No role changes recorded</p>
                                    @if (request()->anyFilled(['from','to','member_id','action']))
                                        <small class="text-muted">Try removing some filters.</small>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($history->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                <small class="text-muted">Showing {{ $history->firstItem() }}–{{ $history->lastItem() }} of {{ number_format($history->total()) }} results</small>
                {{ $history->withQueryString()->links() }}
            </div>
        @else
            <div class="card-footer bg-white border-top py-2 px-4">
                <small class="text-muted">{{ $history->count() }} {{ Str::plural('result', $history->count()) }}</small>
            </div>
        @endif
    </div>

    {{-- Project membership changes --}}
    <h6 class="fw-semibold mt-4 mb-2">Project membership changes</h6>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3 small">Date/Time</th>
                            <th class="py-3 small">Project</th>
                            <th class="py-3 small">Member</th>
                            <th class="py-3 small">Action</th>
                            <th class="py-3 small">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($projectMemberLog as $pml)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-medium small">{{ $pml->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $pml->created_at->format('H:i') }} · {{ $pml->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="py-3 small">{{ optional($pml->project)->name ?? 'Deleted project' }}</td>
                                <td class="py-3 small">{{ optional($pml->targetUser)->name ?? 'Unknown' }}</td>
                                <td class="py-3">
                                    @if ($pml->action === 'removed')
                                        <span class="badge bg-label-danger">Removed</span>
                                    @elseif ($pml->action === 'reactivated')
                                        <span class="badge bg-label-info">Re-activated</span>
                                    @else
                                        <span class="badge bg-label-success">Added</span>
                                    @endif
                                </td>
                                <td class="py-3"><small class="text-muted">{{ optional($pml->performedBy)->name ?? 'System' }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">No project membership changes recorded</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($projectMemberLog->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4">
                {{ $projectMemberLog->withQueryString()->links() }}
            </div>
        @endif
    </div>

    @else

    {{-- Enforcement log --}}
    <div class="alert alert-info py-2 mb-3" style="font-size:.82rem">
        <i class="ti ti-info-circle me-1"></i>
        <strong>Audit mode:</strong> The system is recording every access attempt that <em>would have been blocked</em> if enforcement were active. No users are currently blocked — this log shows what enforcement would look like.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3 small">Date/Time</th>
                            <th class="py-3 small">User</th>
                            <th class="py-3 small">Route Accessed</th>
                            <th class="py-3 small">Permission Required</th>
                            <th class="py-3 small">Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($enforcementLog as $entry)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-medium small">{{ $entry->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $entry->created_at->format('H:i') }}</small>
                                </td>
                                <td class="py-3">
                                    <span class="small">{{ optional($entry->user)->name ?? 'User #'.$entry->user_id }}</span>
                                </td>
                                <td class="py-3">
                                    <code class="small" style="font-size:.72rem">{{ $entry->method }} {{ $entry->route_uri }}</code>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-label-warning" style="font-size:.65rem">{{ $entry->permission_group }}</span>
                                    <span class="badge bg-label-secondary ms-1" style="font-size:.65rem">Level: {{ $entry->required_level }}</span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-label-danger" style="font-size:.65rem">Would Block</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="ti ti-shield-check fs-2 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-0">No enforcement events recorded for this organisation</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($enforcementLog->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                <small class="text-muted">Showing {{ $enforcementLog->firstItem() }}–{{ $enforcementLog->lastItem() }} of {{ number_format($enforcementLog->total()) }} results</small>
                {{ $enforcementLog->withQueryString()->links() }}
            </div>
        @else
            <div class="card-footer bg-white border-top py-2 px-4">
                <small class="text-muted">{{ $enforcementLog->count() }} {{ Str::plural('event', $enforcementLog->count()) }}</small>
            </div>
        @endif
    </div>

    @endif
</div>
@endsection

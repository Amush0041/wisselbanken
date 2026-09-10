@extends('admin.layouts.app')

@section('seo')
<title>SoD Rules | RBAC | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .conflict-badge { display:inline-flex;align-items:center;gap:.35rem;background:var(--wb-maroon-light);color:var(--wb-maroon);padding:.2rem .6rem;border-radius:.35rem;font-size:.78rem;font-weight:600; }
    .conflict-arrow { color:#adb5bd;font-size:.85rem; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Page header --}}
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Separation of Duties</h4>
            <p class="text-muted small mb-0">Role pairs that cannot coexist in the same organization.</p>
        </div>
        <button class="btn btn-sm text-white" style="background:var(--wb-maroon)"
                data-bs-toggle="modal" data-bs-target="#addRuleModal">
            <i class="ti ti-plus me-1"></i> Add Rule
        </button>
    </div>

    @include('admin.rbac._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="ti ti-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="ti ti-alert-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats row --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fw-bold fs-4" style="color:var(--wb-maroon)">{{ $stats['total'] }}</div>
                <div class="text-muted small">Total Rules</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fw-bold fs-4 text-success">{{ $stats['active'] }}</div>
                <div class="text-muted small">Active</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fw-bold fs-4 text-secondary">{{ $stats['inactive'] }}</div>
                <div class="text-muted small">Inactive</div>
            </div>
        </div>
    </div>

    {{-- Explanation banner --}}
    <div class="alert mb-4 d-flex gap-3" style="background:var(--wb-maroon-light);border:1px solid rgba(107,28,28,.15);border-radius:.75rem;">
        <i class="ti ti-info-circle mt-1 flex-shrink-0" style="color:var(--wb-maroon);font-size:1.1rem;"></i>
        <div style="font-size:.85rem;color:#3d0c0c;">
            <strong>How SoD enforcement works:</strong>
            When an admin or org-admin tries to assign a role to a user who already holds a conflicting role in the same organization, the assignment is <strong>blocked</strong> with a clear error message. Both directions are enforced — adding Role A blocks if the user has Role B, and vice versa.
        </div>
    </div>

    {{-- Rules table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3" style="width:38%">Role A</th>
                            <th class="py-3" style="width:4%;"></th>
                            <th class="py-3" style="width:38%">Role B</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr class="{{ $rule->is_active ? '' : 'opacity-50' }}">
                                <td class="ps-4 py-3">
                                    <span class="conflict-badge">
                                        <i class="ti ti-id-badge-2" style="font-size:.8rem;"></i>
                                        {{ optional($rule->roleA)->name ?? '—' }}
                                    </span>
                                    @if (optional($rule->roleA)->category)
                                        <div class="text-muted mt-1" style="font-size:.7rem;">{{ $rule->roleA->category }}</div>
                                    @endif
                                </td>
                                <td class="py-3 text-center conflict-arrow">
                                    <i class="ti ti-arrows-exchange"></i>
                                </td>
                                <td class="py-3">
                                    <span class="conflict-badge">
                                        <i class="ti ti-id-badge-2" style="font-size:.8rem;"></i>
                                        {{ optional($rule->roleB)->name ?? '—' }}
                                    </span>
                                    @if (optional($rule->roleB)->category)
                                        <div class="text-muted mt-1" style="font-size:.7rem;">{{ $rule->roleB->category }}</div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @if ($rule->is_active)
                                        <span class="badge bg-label-success">Active</span>
                                    @else
                                        <span class="badge bg-label-secondary">Inactive</span>
                                    @endif
                                    @if ($rule->reason)
                                        <div class="text-muted mt-1" style="font-size:.72rem;">{{ $rule->reason }}</div>
                                    @endif
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    <form action="{{ route('admin.rbac.sod.toggle', $rule->id) }}" method="POST" class="d-inline me-1">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm btn-icon {{ $rule->is_active ? 'btn-text-warning' : 'btn-text-success' }}"
                                                title="{{ $rule->is_active ? 'Deactivate (pause without deleting)' : 'Reactivate' }}">
                                            <i class="ti {{ $rule->is_active ? 'ti-player-pause' : 'ti-player-play' }}"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.rbac.sod.destroy', $rule->id) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-icon btn-text-danger" title="Remove rule permanently"
                                                onclick="return confirm('Permanently remove this SoD rule? Users will be allowed to hold both roles again.')">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="ti ti-git-compare fs-2 d-block mb-2"></i>
                                    <p class="mb-1">No SoD rules defined yet.</p>
                                    <small>Add a rule to prevent incompatible roles from coexisting in the same organization.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- Add Rule Modal --}}
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-semibold">Add SoD Conflict Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.rbac.sod.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Users in the same organization will be prevented from holding both of these roles simultaneously.
                        The restriction is bidirectional.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Role A</label>
                        <select name="role_id_a" class="form-select @error('role_id_a') is-invalid @enderror" required>
                            <option value="">Select first role…</option>
                            @foreach ($roles->groupBy('category') as $category => $catRoles)
                                <optgroup label="{{ $category }}">
                                    @foreach ($catRoles as $role)
                                        <option value="{{ $role->id }}" @selected(old('role_id_a') == $role->id)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('role_id_a')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex align-items-center justify-content-center my-2 text-muted">
                        <i class="ti ti-arrows-exchange fs-4"></i>
                        <span class="ms-2 small">conflicts with</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Role B</label>
                        <select name="role_id_b" class="form-select @error('role_id_b') is-invalid @enderror" required>
                            <option value="">Select second role…</option>
                            @foreach ($roles->groupBy('category') as $category => $catRoles)
                                <optgroup label="{{ $category }}">
                                    @foreach ($catRoles as $role)
                                        <option value="{{ $role->id }}" @selected(old('role_id_b') == $role->id)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('role_id_b')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Reason <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="reason" class="form-control @error('reason') is-invalid @enderror"
                               placeholder="e.g. Cannot approve their own purchase orders"
                               value="{{ old('reason') }}" maxlength="300">
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--wb-maroon)">
                        <i class="ti ti-plus me-1"></i> Add Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

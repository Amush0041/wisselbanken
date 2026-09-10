@extends('admin.layouts.app')

@section('seo')
<title>{{ $organization->name }} — RBAC | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('backend/assets/vendor/libs/select2/select2.css') }}" />
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="mb-3">RBAC Management</h4>
    @include('admin.rbac._nav')

    @if (session('success'))<div class="alert alert-success alert-dismissible fade show"><i class="ti ti-check me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if (session('error'))<div class="alert alert-danger alert-dismissible fade show"><i class="ti ti-shield-x me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h5 class="mb-0 text-break">{{ $organization->name }}
            <small class="text-muted d-block d-sm-inline">{{ $organization->org_type ? ucwords(str_replace('_',' ',$organization->org_type)) : '' }} · {{ $organization->team_size }}</small>
        </h5>
        <div class="d-flex gap-2 flex-shrink-0">
            <a href="{{ route('admin.rbac.organizations') }}" class="btn btn-sm btn-outline-secondary">&larr; All organizations</a>
            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteOrgModal">
                <i class="ti ti-trash me-1"></i> Delete Org
            </button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">Assign a role</h6></div>
        <div class="card-body">
            <form action="{{ route('admin.rbac.roles.assign', $organization->id) }}" method="POST" class="row g-3 align-items-start">
                @csrf
                <div class="col-12 col-md-5">
                    <label class="form-label" for="user_id">User</label>
                    <select id="user_id" name="user_id" class="form-select" required style="width:100%">
                        <option value="">Search by name or email…</option>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="role_id">Role</label>
                    <select id="role_id" name="role_id" class="form-select" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->category }} — {{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button class="btn btn-primary">Assign</button>
                </div>
                <div class="col-12">
                    <small class="text-muted">Pick any user to give them a role in this organization.</small>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">Members &amp; roles</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Member</th><th>Roles</th></tr></thead>
                    <tbody>
                        @forelse ($members as $member)
                            <tr>
                                <td><strong>{{ $member->name }}</strong><br><small class="text-muted">{{ $member->email }}</small></td>
                                <td>
                                    @forelse ($member->org_roles as $assignment)
                                        <span class="badge bg-label-info mb-1">
                                            {{ $assignment->role->name }}
                                            <form action="{{ route('admin.rbac.roles.remove', $assignment->id) }}" method="POST" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-text-danger btn-sm p-0 ms-1" title="Remove"
                                                    onclick="return confirm('Remove {{ $assignment->role->name }} from {{ $member->name }}?')">&times;</button>
                                            </form>
                                        </span>
                                    @empty
                                        <span class="text-muted">No active roles</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">No active members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Delete confirmation modal --}}
<div class="modal fade" id="deleteOrgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger fw-bold"><i class="ti ti-alert-triangle me-2"></i>Delete Organization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">You are about to permanently delete <strong>{{ $organization->name }}</strong>.</p>
                <p class="text-muted small mb-0">This will remove all role assignments, delegations, audit logs, and org relationships. <strong>This cannot be undone.</strong></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.rbac.organizations.destroy', $organization->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="ti ti-trash me-1"></i> Yes, Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('backend/assets/vendor/libs/select2/select2.js') }}"></script>
<script>
    $(function () {
        $('#user_id').select2({
            placeholder: 'Search by name or email…',
            allowClear: true,
            ajax: {
                url: "{{ route('admin.rbac.users.search') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data.results }),
                cache: true,
            },
            minimumInputLength: 1,
            width: '100%',
        });
    });
</script>
@endpush
@endsection

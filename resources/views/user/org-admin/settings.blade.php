@extends('user.layouts.app')

@section('seo')
<title>Org Settings | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .settings-nav a { display:block;padding:.5rem .75rem;border-radius:.4rem;font-size:.85rem;color:#495057;text-decoration:none; }
    .settings-nav a:hover { background:#f8f9fa; }
    .settings-nav a.active { background:var(--wb-maroon-light);color:var(--wb-maroon);font-weight:500; }
    .danger-zone { border:1px solid #f5c2c7;border-radius:.75rem;padding:1.5rem; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-0 fw-bold">{{ $org->name }}</h4>
            <p class="text-muted small mb-0">Organization Settings</p>
        </div>
    </div>

    @include('user.org-admin._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="ti ti-check me-1"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        {{-- Left settings nav --}}
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <nav class="settings-nav">
                        <p class="text-muted small text-uppercase fw-semibold px-2 mt-2 mb-1" style="font-size:.7rem;letter-spacing:.04em">Configuration</p>
                        <a href="#general" class="active">General</a>
                        @if ($canManageOrg)
                        <a href="#danger">Danger zone</a>
                        @endif
                    </nav>
                </div>
            </div>
        </div>

        {{-- Settings form --}}
        <div class="col-md-9">
            {{-- General --}}
            <div class="card border-0 shadow-sm mb-4" id="general">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-semibold mb-0">General Information</h6>
                    <small class="text-muted">Update your organization's core identification details.</small>
                </div>
                <div class="card-body">
                    <form action="{{ route('org-admin.settings.update') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="org_name" class="form-label">Organization Name</label>
                            <small class="d-block text-muted mb-1">This is your public display name within the platform.</small>
                            <input type="text" id="org_name" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $org->name) }}" required @disabled(! $canManageOrg)>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="org_type" class="form-label">Organization Type</label>
                            <small class="d-block text-muted mb-1">Select the category that best describes your business entity.</small>
                            <select id="org_type" name="org_type" class="form-select @error('org_type') is-invalid @enderror" required @disabled(! $canManageOrg)>
                                @foreach ($orgTypes as [$slug, $label, $purpose])
                                    <option value="{{ $slug }}" @selected(old('org_type', $org->org_type) === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('org_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="team_size" class="form-label">Team Size</label>
                            <select id="team_size" name="team_size" class="form-select @error('team_size') is-invalid @enderror" required @disabled(! $canManageOrg)>
                                @foreach ($teamSizes as $size)
                                    <option value="{{ $size }}" @selected(old('team_size', $org->team_size) === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                            @error('team_size')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex gap-2">
                            @if ($canManageOrg)
                            <button type="submit" class="btn text-white" style="background:var(--wb-maroon)">Save Changes</button>
                            <a href="{{ route('org-admin.settings') }}" class="btn btn-outline-secondary">Discard changes</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            @if ($canManageOrg)
            {{-- Danger zone --}}
            <div class="danger-zone" id="danger">
                <h6 class="fw-semibold text-danger mb-3"><i class="ti ti-alert-triangle me-1"></i> Danger Zone</h6>

                @php $isOwner = \App\Models\Rbac\UserOrgRole::where('org_id', $org->id)->where('user_id', Auth::id())->where('is_active', true)->whereHas('role', fn($q) => $q->where('slug', 'organization_owner'))->exists(); @endphp

                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div>
                        <p class="fw-medium mb-0">Transfer Ownership</p>
                        <small class="text-muted">Hand over ownership to another member. You will lose the Owner role.</small>
                    </div>
                    @if ($isOwner)
                        <button class="btn btn-sm btn-outline-secondary ms-3" data-bs-toggle="modal" data-bs-target="#transferModal">
                            Transfer Ownership
                        </button>
                    @else
                        <button class="btn btn-sm btn-outline-secondary ms-3" disabled title="Only the organization owner can transfer ownership">
                            Transfer Ownership
                        </button>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3">
                    <div>
                        <p class="fw-medium text-danger mb-0">Delete Organization</p>
                        <small class="text-muted">Permanently delete this organization, including all roles, permissions, and audit logs. Non-recoverable.</small>
                    </div>
                    @if ($isOwner)
                        <button class="btn btn-sm btn-danger ms-3" data-bs-toggle="modal" data-bs-target="#deleteOrgModal">
                            Delete Organization
                        </button>
                    @else
                        <button class="btn btn-sm btn-danger ms-3" disabled title="Only the organization owner can delete this organization">
                            Delete Organization
                        </button>
                    @endif
                </div>
            </div>

            {{-- Transfer Ownership Modal --}}
            <div class="modal fade" id="transferModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold">Transfer Ownership</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="{{ route('org-admin.settings.transfer') }}" method="POST">
                            @csrf
                            <div class="modal-body">
                                <p class="text-muted small mb-3">Enter the email address of the user you want to become the new owner of <strong>{{ $org->name }}</strong>. They must already be a member of this organization.</p>
                                <label class="form-label fw-medium">New Owner Email</label>
                                <input type="email" name="new_owner_email" class="form-control @error('new_owner_email') is-invalid @enderror"
                                       placeholder="user@example.com" required value="{{ old('new_owner_email') }}">
                                @error('new_owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-danger d-block mt-2"><i class="ti ti-alert-triangle me-1"></i>You will lose the Owner role after this action.</small>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning btn-sm">Transfer Ownership</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Delete Org Modal --}}
            <div class="modal fade" id="deleteOrgModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title text-danger fw-bold"><i class="ti ti-alert-triangle me-2"></i>Delete Organization</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-1">You are about to permanently delete <strong>{{ $org->name }}</strong>.</p>
                            <p class="text-muted small mb-0">This will remove all role assignments, delegations, audit logs, and relationships. <strong>This cannot be undone.</strong></p>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <form action="{{ route('org-admin.settings.destroy') }}" method="POST" class="d-inline">
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
            @endif
        </div>
    </div>
</div>

@push('scripts')
@if ($canManageOrg && $errors->has('new_owner_email'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('transferModal')).show();
    });
</script>
@endif
@endpush

@endsection

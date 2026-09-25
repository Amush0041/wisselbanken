@extends('user.layouts.app')

@section('seo')
<title>New RFQ - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page">

    <div class="mb-3">
        <h4 class="page-title mb-1">Send a Request for Quote</h4>
        <p class="page-description mb-0">Fill in the details and select which suppliers to send this RFQ to.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('rfq.store') }}">
                @csrf

                <div class="row g-3">
                    {{-- Project --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Project <span class="text-danger">*</span></label>
                        @if ($projects->isEmpty())
                            <div class="alert alert-warning mb-0">
                                <strong>No projects available.</strong>
                                An RFQ must belong to a project.
                                <a href="{{ route('projects.index') }}" class="alert-link ms-1">Create a project &rarr;</a>
                            </div>
                        @else
                            <select name="project_id" class="form-select @error('project_id') is-invalid @enderror">
                                <option value="">Select a project…</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((int) old('project_id', $selectedProjectId) === $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                            @error('project_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    {{-- Title --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="title"
                            class="form-control @error('title') is-invalid @enderror"
                            value="{{ old('title') }}"
                            placeholder="e.g. Timber supply for Project Maple">
                        @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Notes --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes / Specifications</label>
                        <textarea
                            name="notes"
                            class="form-control"
                            rows="4"
                            placeholder="Describe quantities, specifications, delivery requirements…">{{ old('notes') }}</textarea>
                    </div>

                    {{-- Deadline --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Response Deadline</label>
                        <input
                            type="date"
                            name="deadline"
                            class="form-control @error('deadline') is-invalid @enderror"
                            value="{{ old('deadline') }}"
                            min="{{ now()->toDateString() }}">
                        @error('deadline')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Supplier selection --}}
                    <div class="col-12 mt-2">
                        <label class="form-label fw-semibold">Send to Suppliers <span class="text-danger">*</span></label>
                        @error('seller_org_ids')
                        <div class="text-danger small mb-1">{{ $message }}</div>
                        @enderror
                        @if ($sellerOrgs->isEmpty())
                            <div class="alert alert-warning mb-0">
                                <i class="ti ti-link me-1"></i>
                                <strong>No trading partners yet.</strong>
                                You can only send RFQs to supplier organisations you have an active <strong>Buyer → Seller</strong> connection with.
                                <a href="{{ route('org-admin.connections.index') }}" class="alert-link ms-1">Add a supplier in Connections &rarr;</a>
                            </div>
                        @else
                        <div class="row g-2">
                            @foreach ($sellerOrgs as $org)
                            <div class="col-md-4 col-6">
                                <div class="form-check border rounded p-3">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="seller_org_ids[]"
                                        value="{{ $org->id }}"
                                        id="org_{{ $org->id }}"
                                        {{ in_array($org->id, (array) old('seller_org_ids', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="org_{{ $org->id }}">
                                        <strong>{{ $org->name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $org->org_type ?? '')) }}</small>
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="col-12 d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-send me-1"></i>Send RFQ
                        </button>
                        <a href="{{ route('rfq.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@extends('user.layouts.app')

@section('seo')
<title>Connections | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .conn-card { background:#fff;border:1px solid #eee;border-radius:.6rem;padding:.75rem 1rem;margin-bottom:.5rem; }
    .conn-card.inactive { opacity:.55; }
    .badge-type { background:var(--wb-maroon);color:#fff;padding:.2rem .55rem;border-radius:.35rem;font-size:.7rem;font-weight:700; }
    .badge-prio-critical { background:#c0392b;color:#fff;padding:.15rem .45rem;border-radius:.3rem;font-size:.7rem; }
    .badge-prio-high { background:#e67e22;color:#fff;padding:.15rem .45rem;border-radius:.3rem;font-size:.7rem; }
    .badge-prio-medium { background:#2980b9;color:#fff;padding:.15rem .45rem;border-radius:.3rem;font-size:.7rem; }
    .section-header { font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;margin-bottom:.75rem;margin-top:1.25rem; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="mb-1">
        <h4 class="mb-0 fw-bold">Org Connections</h4>
        <p class="text-muted small mb-0">Trading partnerships and organizational relationships for <strong>{{ $org->name }}</strong></p>
    </div>

    @include('user.org-admin._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        {{-- Left: existing connections --}}
        <div class="col-lg-8">

            {{-- Outgoing --}}
            <div class="section-header"><i class="ti ti-arrow-up-right me-1"></i> Connections you initiated</div>

            @if ($outgoing->isEmpty())
                <div class="text-muted small mb-3">No outgoing connections yet.</div>
            @else
                @foreach ($outgoing as $rel)
                    @php
                        $label = $relationshipTypes[$rel->relationship_type] ?? $rel->relationship_type;
                    @endphp
                    <div class="conn-card d-flex align-items-center justify-content-between gap-3 {{ $rel->is_active ? '' : 'inactive' }}">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold small">{{ optional($rel->toOrganization)->name ?? 'Unknown Org' }}</span>
                                @if (optional($rel->toOrganization)->org_type)
                                    <span class="badge bg-label-secondary" style="font-size:.62rem">{{ strtoupper($rel->toOrganization->org_type) }}</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                <span class="badge-type">{{ $label }}</span>
                                @if ($rel->priority)
                                    <span class="badge-prio-{{ strtolower($rel->priority) }}">{{ $rel->priority }}</span>
                                @endif
                                @if (! $rel->is_active)
                                    <span class="badge bg-label-secondary" style="font-size:.62rem">Inactive</span>
                                @endif
                            </div>
                        </div>
                        @if ($rel->is_active && $canManageConnections)
                            <form action="{{ route('org-admin.connections.destroy', $rel->id) }}" method="POST"
                                  onsubmit="return confirm('Deactivate this connection?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="ti ti-plug-x"></i> Deactivate
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            @endif

            {{-- Incoming --}}
            <div class="section-header mt-4"><i class="ti ti-arrow-down-left me-1"></i> Connections others made to you</div>

            @if ($incoming->isEmpty())
                <div class="text-muted small">No other organizations have connected to you yet.</div>
            @else
                @foreach ($incoming as $rel)
                    @php $label = $relationshipTypes[$rel->relationship_type] ?? $rel->relationship_type; @endphp
                    <div class="conn-card {{ $rel->is_active ? '' : 'inactive' }}">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold small">{{ optional($rel->fromOrganization)->name ?? 'Unknown Org' }}</span>
                            @if (optional($rel->fromOrganization)->org_type)
                                <span class="badge bg-label-secondary" style="font-size:.62rem">{{ strtoupper($rel->fromOrganization->org_type) }}</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                            <span class="badge-type">{{ $label }}</span>
                            @if ($rel->priority)
                                <span class="badge-prio-{{ strtolower($rel->priority) }}">{{ $rel->priority }}</span>
                            @endif
                            @if (! $rel->is_active)
                                <span class="badge bg-label-secondary" style="font-size:.62rem">Inactive</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

        </div>

        {{-- Right: add new connection --}}
        <div class="col-lg-4">
            @if ($canManageConnections)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h6 class="mb-0 fw-semibold"><i class="ti ti-plug me-1" style="color:var(--wb-maroon)"></i> Add New Connection</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('org-admin.connections.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Partner Organization</label>
                            <select name="to_org_id" class="form-select form-select-sm @error('to_org_id') is-invalid @enderror" required>
                                <option value="">— Select organization —</option>
                                @foreach ($allOrgs as $o)
                                    <option value="{{ $o->id }}" {{ old('to_org_id') == $o->id ? 'selected' : '' }}>
                                        {{ $o->name }}{{ $o->org_type ? ' (' . strtoupper($o->org_type) . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('to_org_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Relationship Type</label>
                            <select name="relationship_type" class="form-select form-select-sm @error('relationship_type') is-invalid @enderror" required>
                                <option value="">— Select type —</option>
                                @foreach ($relationshipTypes as $key => $display)
                                    <option value="{{ $key }}" {{ old('relationship_type') === $key ? 'selected' : '' }}>{{ $display }}</option>
                                @endforeach
                            </select>
                            @error('relationship_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Priority <span class="text-muted fw-normal">(optional)</span></label>
                            <select name="priority" class="form-select form-select-sm">
                                <option value="">— None —</option>
                                @foreach ($priorities as $p)
                                    <option value="{{ $p }}" {{ old('priority') === $p ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-sm w-100" style="background:var(--wb-maroon);color:#fff">
                            <i class="ti ti-plug-connected me-1"></i> Create Connection
                        </button>
                    </form>
                </div>
            </div>
            @endif

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body py-3">
                    <p class="small text-muted mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        Connections describe your org's trading relationships on the platform — e.g., who you buy from or sell to. They are directional: <strong>you</strong> initiate the connection toward the partner.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

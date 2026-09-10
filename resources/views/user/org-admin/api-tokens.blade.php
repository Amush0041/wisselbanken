@extends('user.layouts.app')

@section('seo')
<title>API Tokens | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .token-revealed {
        font-family:monospace; font-size:.82rem; background:#0d1117; color:#a5f3a5;
        padding:.75rem 1rem; border-radius:.5rem; word-break:break-all; letter-spacing:.04em;
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-0 fw-bold">API Tokens</h4>
            <p class="text-muted small mb-0">Service account bearer tokens for non-human callers</p>
        </div>
        <button class="btn btn-sm text-white" style="background:var(--wb-maroon)" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="ti ti-plus me-1"></i> Create Token
        </button>
    </div>

    <nav class="oa-nav d-flex gap-1 mt-3 flex-wrap mb-4">
        <a href="{{ route('org-admin.overview') }}"><i class="ti ti-layout-dashboard"></i> Overview</a>
        <a href="{{ route('org-admin.index') }}"><i class="ti ti-users"></i> Team</a>
        <a href="{{ route('org-admin.my-roles') }}"><i class="ti ti-id-badge-2"></i> Roles</a>
        <a href="{{ route('org-admin.delegations.index') }}"><i class="ti ti-arrows-exchange"></i> Delegations</a>
        <a href="{{ route('org-admin.api-tokens.index') }}" class="active"><i class="ti ti-api"></i> API Tokens</a>
        <a href="{{ route('org-admin.audit-log') }}"><i class="ti ti-clipboard-list"></i> Audit Log</a>
        <a href="{{ route('org-admin.settings') }}"><i class="ti ti-settings"></i> Org Settings</a>
    </nav>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="ti ti-check me-1"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- One-time token reveal --}}
    @if ($newToken)
        <div class="alert alert-warning mb-4">
            <h6 class="fw-bold mb-2"><i class="ti ti-alert-triangle me-1"></i> Copy your token now — it will not be shown again</h6>
            <div class="token-revealed mb-2">{{ $newToken }}</div>
            <button class="btn btn-sm btn-outline-secondary" onclick="copyToken('{{ $newToken }}')">
                <i class="ti ti-copy me-1"></i> Copy to clipboard
            </button>
        </div>
    @endif

    {{-- Token list --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Name</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Last Used</th>
                            <th class="py-3">Expires</th>
                            <th class="py-3 pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            @php
                                $expired = $token->expires_at && now()->isAfter($token->expires_at);
                                $active  = $token->is_active && !$expired;
                            @endphp
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-xs bg-label-secondary rounded">
                                            <i class="ti ti-robot" style="font-size:.75rem"></i>
                                        </span>
                                        <div>
                                            <div class="fw-medium">{{ $token->name }}</div>
                                            <small class="text-muted font-monospace">{{ substr(hash('sha256', $token->token), 0, 8) }}…</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if (!$token->is_active)
                                        <span class="badge bg-label-danger">Revoked</span>
                                    @elseif ($expired)
                                        <span class="badge bg-label-secondary">Expired</span>
                                    @else
                                        <span class="badge bg-label-success">Active</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">
                                        {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never' }}
                                    </small>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">
                                        {{ $token->expires_at ? $token->expires_at->format('M d, Y') : 'Never' }}
                                    </small>
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    @if ($token->is_active)
                                        <form action="{{ route('org-admin.api-tokens.destroy', $token->id) }}" method="POST" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-icon btn-text-danger" title="Revoke token"
                                                    onclick="return confirm('Revoke token {{ $token->name }}? This cannot be undone.')">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="ti ti-api fs-2 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-1">No API tokens yet</p>
                                    <small class="text-muted">Create a token for automated integrations, AI services, or scripts.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex align-items-center gap-2 text-muted small">
        <i class="ti ti-lock"></i>
        <span>Tokens are stored as SHA-256 hashes. The plaintext is shown only once at creation and is never retrievable.</span>
    </div>
</div>

{{-- Create token modal --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-semibold">Create API Token</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('org-admin.api-tokens.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Token name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. AI Import Service" required maxlength="100">
                        <small class="text-muted">A label to identify this token (visible to you only).</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry date <span class="text-muted">(optional)</span></label>
                        <input type="date" name="expires_at" class="form-control" min="{{ now()->addDay()->format('Y-m-d') }}">
                        <small class="text-muted">Leave blank for a non-expiring token.</small>
                    </div>
                    <div class="alert alert-info py-2 mb-0">
                        <small><i class="ti ti-info-circle me-1"></i> The token will be shown <strong>once</strong> after creation. Store it securely.</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--wb-maroon)">Create token</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyToken(token) {
    navigator.clipboard.writeText(token).then(() => {
        alert('Token copied to clipboard.');
    });
}
</script>
@endsection

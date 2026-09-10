@extends('admin.layouts.app')

@section('seo')
<title>User Management | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; }
    .user-drawer {
        position: fixed; top: 0; right: -440px; width: 440px; height: 100vh;
        background: #fff; z-index: 1055; box-shadow: -4px 0 24px rgba(0,0,0,.12);
        transition: right .25s ease; display: flex; flex-direction: column; overflow: hidden;
    }
    .user-drawer.open { right: 0; }
    .drawer-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,.35);
        z-index: 1054; display: none;
    }
    .drawer-backdrop.show { display: block; }
    .drawer-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #eee; flex-shrink: 0; }
    .drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; }
    .org-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .6rem; border-radius: .35rem; background: rgba(107,28,28,.1); color: var(--wb-maroon); font-size: .75rem; font-weight: 600; }
    .role-tag { display: inline-flex; align-items: center; padding: .2rem .55rem; border-radius: .35rem; background: var(--wb-maroon); color: #fff; font-size: .72rem; font-weight: 600; margin: .15rem; }
    .token-row { display: flex; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid #f0f0f0; }
    .token-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>
            <h4 class="mb-0 fw-bold">User Management</h4>
            <p class="text-muted small mb-0">All platform users with their organizations, roles, and access</p>
        </div>
        <span class="badge bg-label-secondary">GLOBAL ADMIN</span>
    </div>

    @include('admin.rbac._nav')

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fw-bold fs-4 mb-0" style="color:var(--wb-maroon)">{{ number_format($stats['total_users']) }}</div>
                <small class="text-muted">Total Active Users</small>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fw-bold fs-4 mb-0" style="color:var(--wb-maroon)">{{ number_format($stats['total_orgs']) }}</div>
                <small class="text-muted">Organizations</small>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fw-bold fs-4 mb-0" style="color:var(--wb-maroon)">{{ number_format($stats['active_assignments']) }}</div>
                <small class="text-muted">Active Role Assignments</small>
            </div>
        </div>
    </div>

    {{-- Search --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.rbac.users') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Search by name or email…" value="{{ $search }}">
                </div>
                <button type="submit" class="btn text-white flex-shrink-0" style="background:var(--wb-maroon)">Search</button>
                @if ($search)
                    <a href="{{ route('admin.rbac.users') }}" class="btn btn-outline-secondary flex-shrink-0">Clear</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Users table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">User Identity</th>
                            <th class="py-3">Organizations</th>
                            <th class="py-3">Roles</th>
                            <th class="py-3">Joined</th>
                            <th class="py-3 pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $orgCount  = $user->org_roles_grouped->count();
                                $roleCount = $user->org_roles_grouped->flatten()->count();
                                $firstOrgs = $user->org_roles_grouped->take(2);
                            @endphp
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar avatar-sm rounded-circle text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="background:var(--wb-maroon);width:36px;height:36px;font-size:.75rem">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-medium">{{ $user->name }}</div>
                                            <small class="text-muted">{{ $user->email }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if ($orgCount > 0)
                                        <span class="badge bg-label-secondary me-1">{{ $orgCount }} {{ Str::plural('Org', $orgCount) }}</span>
                                        @foreach ($firstOrgs as $orgId => $orgRoles)
                                            <span class="org-chip">{{ optional($orgRoles->first()->organization)->name ?? '—' }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-muted small">No orgs</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <span class="fw-medium">{{ $roleCount }}</span>
                                    <small class="text-muted"> {{ Str::plural('role', $roleCount) }}</small>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">{{ $user->created_at?->format('M d, Y') ?? '—' }}</small>
                                </td>
                                <td class="py-3 pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-secondary"
                                            onclick="openUserDrawer({{ $user->id }})">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="ti ti-users fs-2 text-muted d-block mb-2"></i>
                                    <p class="text-muted mb-0">No users found</p>
                                    @if ($search)
                                        <small class="text-muted">Try a different search term.</small>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Drawer backdrop --}}
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeUserDrawer()"></div>

{{-- User detail drawer --}}
<div class="user-drawer" id="userDrawer">
    <div class="drawer-header">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" id="drawerName">Loading…</h6>
            <button class="btn btn-sm btn-icon btn-text-secondary" onclick="closeUserDrawer()">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <small class="text-muted" id="drawerEmail"></small><br>
        <small class="text-muted"><i class="ti ti-calendar-event me-1"></i>Joined <span id="drawerJoined"></span></small>
    </div>
    <div class="drawer-body" id="drawerBody">
        <p class="text-muted text-center mt-4">Select a user to view details.</p>
    </div>
    <div class="drawer-footer border-top p-3" id="drawerFooter" style="display:none;flex-shrink:0">
        <button class="btn btn-sm btn-danger w-100" id="drawerDeleteBtn" onclick="confirmDeleteUser()">
            <i class="ti ti-trash me-1"></i> Delete User
        </button>
    </div>
</div>

{{-- Delete user confirmation modal --}}
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger fw-bold"><i class="ti ti-alert-triangle me-1"></i>Delete User</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-1">Permanently delete <strong id="deleteUserName"></strong>?</p>
                <p class="text-muted small mb-0">All role assignments, delegations, and audit logs for this user will be removed. Sole-owner organizations will also be deleted. <strong>This cannot be undone.</strong></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteUserForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="ti ti-trash me-1"></i> Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Pre-serialized user data for drawer --}}
@php
$usersJson = $users->keyBy('id')->map(function ($u) {
    return [
        'id'         => $u->id,
        'name'       => $u->name,
        'email'      => $u->email,
        'joined'     => optional($u->created_at)->format('M d, Y'),
        'org_groups' => $u->org_roles_grouped->map(function ($roles) {
            return [
                'org_name' => optional($roles->first()->organization)->name ?? '—',
                'org_type' => optional($roles->first()->organization)->org_type ?? '',
                'roles'    => $roles->pluck('role')->filter()->map(fn ($r) => ['name' => $r->name, 'phase' => $r->phase])->values(),
            ];
        })->values(),
        'delegations' => $u->active_delegations->map(fn ($d) => [
            'from'    => optional($d->fromUser)->name ?? 'Unknown',
            'expires' => optional($d->expires_at)->format('M d, Y H:i'),
        ])->values(),
        'tokens' => $u->api_tokens->map(fn ($t) => [
            'name'      => $t->name,
            'last_used' => $t->last_used_at ? $t->last_used_at->diffForHumans() : 'Never',
            'active'    => $t->is_active,
        ])->values(),
    ];
});
@endphp
<script>
const usersData = @json($usersJson);

let _activeDrawerUserId = null;

function openUserDrawer(userId) {
    const u = usersData[userId];
    if (!u) return;

    _activeDrawerUserId = userId;
    document.getElementById('drawerName').textContent    = u.name;
    document.getElementById('drawerEmail').textContent   = u.email;
    document.getElementById('drawerJoined').textContent  = u.joined || '—';
    document.getElementById('drawerFooter').style.display = 'block';

    let html = '';

    // Orgs & Roles
    html += `<h6 class="fw-semibold text-uppercase mb-2" style="font-size:.72rem;letter-spacing:.05em;color:#8a8d93">Organizations &amp; Roles</h6>`;
    if (u.org_groups.length) {
        u.org_groups.forEach(og => {
            html += `<div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small">${og.org_name}</span>
                        ${og.org_type ? `<span class="badge bg-label-secondary" style="font-size:.65rem">${og.org_type}</span>` : ''}
                    </div>
                    <div class="d-flex flex-wrap">${og.roles.map(r => `<span class="role-tag">${r.name}</span>`).join('')}</div>
                </div>
            </div>`;
        });
    } else {
        html += `<p class="text-muted small">No organization memberships.</p>`;
    }

    // Active delegations
    html += `<h6 class="fw-semibold text-uppercase mt-3 mb-2" style="font-size:.72rem;letter-spacing:.05em;color:#8a8d93">Active Delegations</h6>`;
    if (u.delegations.length) {
        u.delegations.forEach(d => {
            html += `<div class="card border-0 shadow-sm mb-2">
                <div class="card-body py-2 px-3">
                    <small class="fw-medium">Acting for ${d.from}</small><br>
                    <small class="text-muted">Expires ${d.expires}</small>
                </div>
            </div>`;
        });
    } else {
        html += `<p class="text-muted small">No active delegations.</p>`;
    }

    // API Tokens & View Audit Log — hidden for now, enable in Phase 3
    /*
    html += `<h6 class="fw-semibold text-uppercase mt-3 mb-2" style="font-size:.72rem;letter-spacing:.05em;color:#8a8d93">API Tokens</h6>`;
    if (u.tokens.length) {
        u.tokens.forEach(t => {
            html += `<div class="token-row">
                <span class="token-dot" style="background:${t.active ? '#198754' : '#adb5bd'}"></span>
                <div class="flex-grow-1">
                    <div class="fw-medium small">${t.name}</div>
                    <small class="text-muted">Last used: ${t.last_used}</small>
                </div>
            </div>`;
        });
    } else {
        html += `<p class="text-muted small">No API tokens.</p>`;
    }

    html += `<div class="mt-4 pt-3 border-top d-flex gap-2">
        <a href="{{ route('admin.rbac.audit-logs') }}?user_id=${u.id}" class="btn btn-sm btn-outline-secondary flex-grow-1">
            <i class="ti ti-clipboard-list me-1"></i> View Audit Log
        </a>
    </div>`;
    */

    document.getElementById('drawerBody').innerHTML = html;
    document.getElementById('userDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('show');
}

function closeUserDrawer() {
    _activeDrawerUserId = null;
    document.getElementById('userDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('show');
    document.getElementById('drawerFooter').style.display = 'none';
}

const DELETE_USER_BASE = '{{ url("admin/rbac/users") }}';

function confirmDeleteUser() {
    if (!_activeDrawerUserId) return;
    const u = usersData[_activeDrawerUserId];
    if (!u) return;
    document.getElementById('deleteUserName').textContent = u.name;
    document.getElementById('deleteUserForm').action = DELETE_USER_BASE + '/' + u.id;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>
@endsection

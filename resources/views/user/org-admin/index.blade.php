@extends('user.layouts.app')

@section('seo')
<title>Team Management | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    /* Brand color */
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }

    /* Org-admin sidebar nav */
    .oa-nav { display:flex; gap:.25rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .oa-nav a {
        display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .9rem;
        border-radius:.5rem; font-size:.84rem; font-weight:500; text-decoration:none;
        color:#495057; transition:background .12s,color .12s;
    }
    .oa-nav a:hover { background: var(--wb-maroon-light); color: var(--wb-maroon); }
    .oa-nav a.active { background: var(--wb-maroon); color:#fff; }

    /* Member card table */
    .member-row { cursor:pointer; transition:background .1s; }
    .member-row:hover { background:#f8f9fa; }
    .member-row.selected { background: var(--wb-maroon-light); }

    .role-tag {
        display:inline-block; padding:.2rem .55rem; font-size:.72rem; font-weight:600;
        border-radius:.35rem; background:#e9ecef; color:#495057; margin:.1rem;
    }

    /* Slide-over member details */
    .member-drawer {
        position:fixed; top:0; right:0; height:100vh; width:400px; max-width:100vw;
        background:#fff; box-shadow:-4px 0 24px rgba(0,0,0,.12);
        z-index:1045; display:flex; flex-direction:column;
        transform:translateX(100%); transition:transform .25s ease;
    }
    .member-drawer.open { transform:translateX(0); }
    .drawer-overlay {
        position:fixed; inset:0; background:rgba(0,0,0,.25);
        z-index:1044; display:none;
    }
    .drawer-overlay.open { display:block; }

    .drawer-header { padding:1.25rem 1.5rem; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
    .drawer-body { flex:1; overflow-y:auto; padding:1.25rem 1.5rem; }
    .drawer-footer { padding:1rem 1.5rem; border-top:1px solid #f0f0f0; }

    /* Invite modal */
    .capability-preview { background:#f8f9fa; border-radius:.5rem; padding:.75rem 1rem; font-size:.82rem; }
    .capability-preview li { padding:.15rem 0; }

    /* Peel-off modal icon */
    .peel-icon { width:56px; height:56px; border-radius:12px; background:#fff9e6; display:flex; align-items:center; justify-content:center; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-0 fw-bold">{{ $org->name }}</h4>
            <p class="text-muted small mb-0">
                {{ ucwords(str_replace('_',' ', $org->org_type ?? 'Organization')) }}
                @if($org->team_size) · {{ $org->team_size }} team @endif
            </p>
        </div>
        @canDo('user_management', 'F')
        <button class="btn btn-sm" style="background:var(--wb-maroon);color:#fff" onclick="openInviteModal()">
            <i class="ti ti-user-plus me-1"></i> Invite Member
        </button>
        @endCanDo
    </div>

    {{-- Org-admin nav --}}
    @include('user.org-admin._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Tab switcher --}}
    <ul class="nav nav-tabs mb-0" id="teamTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabMembers">
                <i class="ti ti-users me-1"></i> Team Members <span class="badge bg-label-secondary ms-1">{{ $members->count() }}</span>
            </button>
        </li>
        {{-- <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabInvitations">
                <i class="ti ti-mail me-1"></i> Pending Invitations
                <span class="badge bg-label-warning ms-1">0</span>
            </button>
        </li> --}}
    </ul>

    <div class="tab-content">

    {{-- Members table --}}
    <div class="tab-pane fade show active" id="tabMembers">
    <div class="card border-0 shadow-sm" style="border-radius:0 .75rem .75rem .75rem">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <select id="filterRole" class="form-select form-select-sm" style="width:180px" onchange="filterMembers()">
                        <option value="">Filter by role</option>
                        @foreach ($roles as $role)
                            <option value="{{ strtolower($role->name) }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <span class="text-muted small">{{ $members->count() }} Total Members</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table mb-0" id="memberTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3">Member</th>
                            <th class="py-3">Roles Assigned</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Joined</th>
                            <th class="py-3 pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            @php $memberRoles = $member->org_roles; @endphp
                            <tr class="member-row" data-member-id="{{ $member->id }}"
                                data-roles="{{ $memberRoles->pluck('role.name')->map(fn($n) => strtolower($n))->implode(',') }}"
                                onclick="openMemberDrawer({{ $member->id }})">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm rounded-circle flex-shrink-0"
                                              style="background:var(--wb-maroon);color:#fff;font-weight:700;font-size:.8rem">
                                            {{ strtoupper(substr($member->name,0,2)) }}
                                        </span>
                                        <div>
                                            <div class="fw-medium">{{ $member->name }}</div>
                                            <small class="text-muted">{{ $member->email }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @forelse ($memberRoles as $assignment)
                                        @php
                                            $coHolders = \Illuminate\Support\Str::contains($assignment->role->slug, ['owner', 'admin'])
                                                ? $members->where('id', '!=', $member->id)->filter(fn ($m) => $m->org_roles->contains('role_id', $assignment->role_id))->pluck('name')
                                                : collect();
                                        @endphp
                                        <span class="role-tag">{{ $assignment->role->name }}</span>
                                        @if ($coHolders->isNotEmpty())
                                            <small class="text-warning" title="Also held by {{ $coHolders->implode(', ') }}"><i class="ti ti-users"></i> also held by {{ $coHolders->first() }}{{ $coHolders->count() > 1 ? ' +' . ($coHolders->count() - 1) : '' }}</small>
                                        @endif
                                    @empty
                                        <span class="text-muted small">No roles</span>
                                    @endforelse
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-label-success">Active</span>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">
                                        {{ $member->created_at ? $member->created_at->format('M d, Y') : '—' }}
                                    </small>
                                </td>
                                <td class="py-3 pe-4 text-end" onclick="event.stopPropagation()">
                                    <button class="btn btn-sm btn-icon btn-text-secondary"
                                            title="Edit" onclick="openMemberDrawer({{ $member->id }})">
                                        <i class="ti ti-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="border border-dashed rounded-3 py-5 mx-4 text-muted">
                                        <i class="ti ti-user-plus fs-2 d-block mb-2"></i>
                                        <p class="mb-1">No members yet</p>
                                        <small>Use "Invite Member" to add team members to your organization.</small>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center" style="background:#fafafa">
                <small class="text-muted d-flex align-items-center gap-2">
                    <i class="ti ti-shield-check text-success"></i> Audit logs synced
                </small>
                <small class="text-muted">{{ $members->count() }} of {{ $members->count() }} members</small>
            </div>
        </div>
    </div>
    </div> {{-- /tab-pane tabMembers --}}

    {{-- Pending Invitations tab — commented out for now (Phase 2 feature)
    <div class="tab-pane fade" id="tabInvitations">
        <div class="card border-0 shadow-sm" style="border-radius:0 .75rem .75rem .75rem">
            <div class="card-body text-center py-5">
                <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center"
                     style="width:64px;height:64px;background:var(--wb-maroon-light)">
                    <i class="ti ti-mail" style="color:var(--wb-maroon);font-size:1.75rem"></i>
                </div>
                <h5 class="fw-semibold mb-2">Email Invitations — Coming in Release 2</h5>
                <p class="text-muted mb-3" style="max-width:420px;margin:0 auto">
                    Send email invitations to team members and track their acceptance status here.
                    Pending, accepted, and expired invitations will be listed in this tab.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <span class="badge bg-label-secondary py-2 px-3"><i class="ti ti-mail-forward me-1"></i> Send invite by email</span>
                    <span class="badge bg-label-secondary py-2 px-3"><i class="ti ti-clock me-1"></i> Track invitation status</span>
                    <span class="badge bg-label-secondary py-2 px-3"><i class="ti ti-refresh me-1"></i> Resend or revoke</span>
                </div>
                <div class="alert alert-info mt-4 text-start" style="max-width:420px;margin:1rem auto 0">
                    <small>
                        <i class="ti ti-info-circle me-1"></i>
                        For now, share the registration link with team members:
                        <code class="d-block mt-1 text-wrap">{{ route('register') }}</code>
                    </small>
                </div>
            </div>
        </div>
    </div>
    --}}

    </div> {{-- /tab-content --}}

</div> {{-- /container --}}

{{-- ── Drawer overlay ── --}}
<div class="drawer-overlay" id="drawerOverlay" onclick="closeMemberDrawer()"></div>

{{-- ── Member detail drawer ── --}}
<div class="member-drawer" id="memberDrawer">
    <div class="drawer-header">
        <h6 class="mb-0 fw-semibold">Member Details</h6>
        <button class="btn btn-sm btn-icon btn-text-secondary" onclick="closeMemberDrawer()">
            <i class="ti ti-x"></i>
        </button>
    </div>
    <div class="drawer-body" id="drawerBody">
        <div class="text-center text-muted py-4">Loading…</div>
    </div>
</div>

{{-- ── Invite member modal ── --}}
<div class="modal fade" id="inviteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold">Invite team member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                {{-- Step 1: form --}}
                <div id="inviteFormStep">
                    <div id="inviteFormMessage" class="alert alert-danger py-2 small" role="alert" style="display:none"></div>
                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input type="email" id="inviteEmail" class="form-control" placeholder="e.g. coworker@company.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" id="inviteName" class="form-control" placeholder="e.g. Alex Rivera">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign role</label>
                        <select id="inviteRole" class="form-select">
                            <option value="">Select a role…</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" data-category="{{ $role->category }}">
                                    {{ $role->category }} — {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="capability-preview" id="capabilityPreview">
                        <small class="text-muted">Select a role to see what this user will be able to do.</small>
                    </div>
                </div>
                {{-- Step 2: link result --}}
                <div id="inviteLinkStep" style="display:none">
                    <div id="inviteLinkResult"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 flex-column align-items-stretch gap-2" id="inviteModalFooter">
                <button type="button" class="btn text-white w-100" style="background:var(--wb-maroon)" id="inviteSubmitBtn" onclick="sendInvite(true)">
                    <i class="ti ti-mail-forward me-1"></i>
                    <span id="inviteBtnText">Send invite email</span>
                    <span id="inviteBtnSpinner" class="spinner-border spinner-border-sm ms-1" style="display:none"></span>
                </button>
                <button type="button" class="btn btn-outline-secondary w-100" id="inviteCopyBtn" onclick="sendInvite(false)">
                    <i class="ti ti-link me-1"></i>
                    <span id="inviteCopyBtnText">Copy link instead</span>
                    <span id="inviteCopyBtnSpinner" class="spinner-border spinner-border-sm ms-1" style="display:none"></span>
                </button>
                <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Peel-off modal ── --}}
<div class="modal fade" id="peelOffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow text-center p-4">
            <div class="peel-icon mx-auto mb-3">
                <i class="ti ti-alert-triangle text-warning" style="font-size:1.75rem"></i>
            </div>
            <h5 class="fw-bold mb-2">You already hold this role</h5>
            <p class="text-muted small mb-3" id="peelOffMessage">
                You currently have the <strong id="peelOffRoleName"></strong> role assigned to your account.
                Would you like to remove it from your account?
            </p>
            <div class="alert alert-danger text-start py-2 mb-3" id="peelOffLastRoleWarning" role="alert" style="display:none;font-size:.8rem"></div>
            <div class="bg-light rounded p-2 mb-4 text-start" style="font-size:.8rem">
                <i class="ti ti-info-circle me-1 text-muted"></i>
                Keeping both is fine — removing transfers sole use to the new assignee.
            </div>
            <div class="alert alert-danger text-start py-2 mb-3" id="peelOffError" role="alert" style="display:none;font-size:.8rem"></div>
            <button type="button" class="btn text-white w-100 mb-2" style="background:var(--wb-maroon)" id="peelOffConfirmBtn">
                Yes, remove from mine
            </button>
            <button type="button" class="btn btn-link text-muted w-100" data-bs-dismiss="modal">Keep on my account</button>
        </div>
    </div>
</div>

{{-- ── Last-role modal ── --}}
<div class="modal fade" id="lastRoleModal" tabindex="-1" aria-labelledby="lastRoleTitle">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow text-center p-4">
            <div class="peel-icon mx-auto mb-3" style="background:#fdecea">
                <i class="ti ti-alert-triangle text-danger" style="font-size:1.75rem"></i>
            </div>
            <h5 class="fw-bold mb-2" id="lastRoleTitle"></h5>
            <p class="text-muted small mb-2" id="lastRoleBody"></p>
            <p class="text-danger small fw-semibold mb-3" id="lastRoleOwnerNote" style="display:none"></p>
            <div class="d-flex gap-2 justify-content-center mt-2">
                <button type="button" class="btn btn-outline-secondary" id="lastRoleCancelBtn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="lastRoleConfirmBtn">Remove from organization</button>
            </div>
        </div>
    </div>
</div>

@php
    $ownerRowCount = $members->sum(fn ($m) => $m->org_roles->filter(fn ($a) => $a->role->slug === 'organization_owner')->count());
@endphp
<script>
(function () {
    const RBAC = {
        assignUrl: "{{ route('org-admin.roles.assign') }}",
        peelOffUrl: "{{ route('org-admin.peel-off') }}",
        csrf: "{{ csrf_token() }}",
        userId: {{ (int) auth()->id() }},
        orgName: @json($org->name),
        canManageRoles: {{ (auth()->user() && (auth()->user()->role === 'admin' || (\App\Support\Rbac\CurrentOrg::sessionOrg((int) auth()->id()) && app(\App\Services\Rbac\PermissionService::class)->checkPermission(auth()->id(), (int) \App\Support\Rbac\CurrentOrg::sessionOrg((int) auth()->id()), 'user_management', 'F')))) ? 'true' : 'false' }},
    };

    // ── Member data (pre-built from Blade) ──────────────────────────────────
    const membersData = {
        @foreach ($members as $member)
        {{ $member->id }}: {
            id: {{ $member->id }},
            name: @json($member->name),
            email: @json($member->email),
            joined: @json($member->created_at ? $member->created_at->format('M d, Y') : '—'),
            roles: [
                @foreach ($member->org_roles as $assignment)
                { id: {{ $assignment->id }}, roleId: {{ $assignment->role_id }}, roleName: @json($assignment->role->name), soleOwner: {{ $assignment->role->slug === 'organization_owner' && $ownerRowCount === 1 ? 'true' : 'false' }} },
                @endforeach
            ],
        },
        @endforeach
    };

    const rolesData = [
        @foreach ($roles as $role)
        { id: {{ $role->id }}, name: @json($role->name), category: @json($role->category) },
        @endforeach
    ];

    // ── Drawer ───────────────────────────────────────────────────────────────
    window.openMemberDrawer = function(memberId) {
        const m = membersData[memberId];
        if (!m) return;
        document.querySelectorAll('.member-row').forEach(r => r.classList.remove('selected'));
        const row = document.querySelector(`.member-row[data-member-id="${memberId}"]`);
        if (row) row.classList.add('selected');

        renderDrawer(m);
        document.getElementById('memberDrawer').classList.add('open');
        document.getElementById('drawerOverlay').classList.add('open');
    };

    window.closeMemberDrawer = function() {
        document.getElementById('memberDrawer').classList.remove('open');
        document.getElementById('drawerOverlay').classList.remove('open');
        document.querySelectorAll('.member-row').forEach(r => r.classList.remove('selected'));
    };

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    let drawerMemberId = null;

    document.getElementById('drawerBody').addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        if (btn.dataset.action === 'remove-role') window.removeRole(Number(btn.dataset.assignmentId), drawerMemberId);
        else if (btn.dataset.action === 'show-add-role') window.showAddRoleForm(drawerMemberId);
        else if (btn.dataset.action === 'assign-role') window.assignRoleFromDrawer(drawerMemberId);
        else if (btn.dataset.action === 'hide-add-role') window.hideAddRoleForm();
    });

    function renderDrawer(m) {
        drawerMemberId = m.id;
        const initials = escapeHtml(m.name.substring(0,2).toUpperCase());
        const rolesList = m.roles.map(r =>
            `<span class="role-tag" style="display:inline-flex;align-items:center;gap:.3rem">
                ${escapeHtml(r.roleName)}
                ${RBAC.canManageRoles ? `<button type="button" data-action="remove-role" data-assignment-id="${r.id}" class="btn p-0 border-0 bg-transparent text-muted" style="line-height:1;font-size:.8rem" title="Remove">×</button>` : ''}
            </span>`
        ).join('') || '<span class="text-muted small">No roles assigned</span>';

        const roleOptions = rolesData.map(r =>
            `<option value="${r.id}">${escapeHtml(r.category)} — ${escapeHtml(r.name)}</option>`
        ).join('');

        document.getElementById('drawerBody').innerHTML = `
            <div class="text-center mb-4">
                <span class="avatar avatar-lg rounded-circle d-inline-flex align-items-center justify-content-center"
                      style="background:var(--wb-maroon);color:#fff;font-size:1.2rem;font-weight:700;width:72px;height:72px">
                    ${initials}
                </span>
                <h6 class="fw-bold mt-3 mb-0">${escapeHtml(m.name)}</h6>
                <small class="text-muted">${escapeHtml(m.email)}</small>
                <br><small class="text-muted">Joined ${escapeHtml(m.joined)}</small>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-semibold mb-0">Current Roles</label>
                    ${RBAC.canManageRoles ? `<button type="button" class="btn btn-xs btn-outline-secondary btn-sm" data-action="show-add-role"><i class="ti ti-plus"></i> Add role</button>` : ''}
                </div>
                <div id="drawerRoles">${rolesList}</div>
                ${RBAC.canManageRoles ? `
                <div id="addRoleForm" class="mt-2" style="display:none">
                    <select id="addRoleSelect" class="form-select form-select-sm mb-2">
                        <option value="">Select role…</option>
                        ${roleOptions}
                    </select>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm text-white flex-grow-1" style="background:var(--wb-maroon)"
                                data-action="assign-role">Assign</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="hide-add-role">Cancel</button>
                    </div>
                </div>` : ''}
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="border rounded-3 p-3 text-center">
                        <div class="text-muted small text-uppercase fw-medium mb-1" style="font-size:.65rem">Status</div>
                        <span class="badge bg-label-success">Active</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded-3 p-3 text-center">
                        <div class="text-muted small text-uppercase fw-medium mb-1" style="font-size:.65rem">Roles held</div>
                        <strong>${m.roles.length}</strong>
                    </div>
                </div>
            </div>
        `;
    }

    window.showAddRoleForm = function(memberId) {
        document.getElementById('addRoleForm').style.display = 'block';
    };
    window.hideAddRoleForm = function() {
        document.getElementById('addRoleForm').style.display = 'none';
    };

    window.assignRoleFromDrawer = async function(memberId) {
        const roleId = document.getElementById('addRoleSelect').value;
        if (!roleId) return;

        const res = await fetch(RBAC.assignUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': RBAC.csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ user_id: memberId, role_id: roleId }),
        });
        const data = await res.json();
        if (!data.success) { alert(data.message || 'Could not assign role.'); return; }

        if (data.peel_off_candidate) {
            showPeelOff(data.role_name, data.role_id, memberId, !!data.peel_off_last_role);
        } else {
            location.reload();
        }
    };

    function submitRoleRemoval(assignmentId, confirmLastRole) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/org-admin/roles/${assignmentId}`;
        const fields = { _token: RBAC.csrf, _method: 'DELETE' };
        if (confirmLastRole) fields.confirm_last_role = '1';
        Object.keys(fields).forEach(function (key) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    window.removeRole = function(assignmentId, memberId) {
        const m = membersData[memberId];
        const role = m ? m.roles.find(r => r.id === assignmentId) : null;
        if (m && role && m.roles.length === 1) {
            showLastRoleModal(m, role);
            return;
        }
        if (!confirm('Remove this role from the member?')) return;
        submitRoleRemoval(assignmentId, false);
    };

    // ── Last role of a member ────────────────────────────────────────────────
    function showLastRoleModal(m, role) {
        const isSelf = m.id === RBAC.userId;
        const org = RBAC.orgName;

        document.getElementById('lastRoleTitle').textContent = isSelf
            ? 'Remove your last role?'
            : 'Remove ' + m.name + "'s last role?";
        document.getElementById('lastRoleBody').textContent = isSelf
            ? 'This is your only role in ' + org + '. Removing it removes you from the organization and you lose access immediately; you can only come back by being invited again.'
            : role.roleName + ' is the only role ' + m.name + ' has in ' + org + '. Removing it removes ' + m.name + ' from the organization: they lose access to its projects, estimates, orders and approvals, and can only come back by being invited again.';

        const ownerNote = document.getElementById('lastRoleOwnerNote');
        if (role.soleOwner) {
            ownerNote.textContent = (isSelf ? 'You are' : m.name + ' is') + ' also the only Owner of ' + org + '. After this the organization has no Owner.';
            ownerNote.style.display = '';
        } else {
            ownerNote.textContent = '';
            ownerNote.style.display = 'none';
        }

        const confirmBtn = document.getElementById('lastRoleConfirmBtn');
        confirmBtn.disabled = false;
        confirmBtn.onclick = function () {
            confirmBtn.disabled = true;
            submitRoleRemoval(role.id, true);
        };
        bootstrap.Modal.getOrCreateInstance(document.getElementById('lastRoleModal')).show();
    }

    document.getElementById('lastRoleModal').addEventListener('shown.bs.modal', function () {
        document.getElementById('lastRoleCancelBtn').focus();
    });

    // ── Peel-off ─────────────────────────────────────────────────────────────
    function showPeelOff(roleName, roleId, memberId, lastRole) {
        document.getElementById('peelOffRoleName').textContent = roleName;

        const warning = document.getElementById('peelOffLastRoleWarning');
        if (lastRole) {
            warning.textContent = 'This is your only role in ' + RBAC.orgName + '. Removing it removes you from the organization and you lose access immediately; you can only come back by being invited again.';
            warning.style.display = '';
        } else {
            warning.textContent = '';
            warning.style.display = 'none';
        }

        const error = document.getElementById('peelOffError');
        error.textContent = '';
        error.style.display = 'none';

        document.getElementById('peelOffConfirmBtn').onclick = async function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('peelOffModal'));
            const payload = { role_id: roleId };
            if (lastRole) payload.confirm_last_role = true;
            let failure = null;
            try {
                const res = await fetch(RBAC.peelOffUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': RBAC.csrf, 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    failure = (body && typeof body.message === 'string' && body.message) ? body.message : 'Something went wrong. Please try again.';
                }
            } catch (e) {
                failure = 'Something went wrong. Please try again.';
            }
            if (failure) {
                error.textContent = failure;
                error.style.display = '';
                return;
            }
            modal.hide();
            location.reload();
        };
        new bootstrap.Modal(document.getElementById('peelOffModal')).show();
    }

    // ── Invite modal ─────────────────────────────────────────────────────────
    const INVITE_GENERIC_ERROR = 'Something went wrong. Please try again.';

    function inviteEl(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text != null) node.textContent = text;
        return node;
    }

    function hideInviteFormMessage() {
        const box = document.getElementById('inviteFormMessage');
        box.textContent = '';
        box.style.display = 'none';
    }

    function showInviteFormMessage(text, field) {
        const box = document.getElementById('inviteFormMessage');
        box.textContent = text;
        box.style.display = '';
        field.focus();
    }

    function showInviteForm() {
        document.getElementById('inviteLinkStep').style.display    = 'none';
        document.getElementById('inviteFormStep').style.display    = '';
        document.getElementById('inviteModalFooter').style.display = '';
    }

    function finishInviteRequest() {
        document.getElementById('inviteSubmitBtn').disabled = false;
        document.getElementById('inviteCopyBtn').disabled   = false;
        document.getElementById('inviteBtnText').textContent     = 'Send invite email';
        document.getElementById('inviteCopyBtnText').textContent = 'Copy link instead';
        document.getElementById('inviteBtnSpinner').style.display     = 'none';
        document.getElementById('inviteCopyBtnSpinner').style.display = 'none';
    }

    function resetInviteModal() {
        showInviteForm();
        hideInviteFormMessage();
        finishInviteRequest();
        document.getElementById('inviteLinkResult').textContent = '';
        document.getElementById('inviteEmail').value = '';
        document.getElementById('inviteName').value  = '';
        document.getElementById('inviteRole').value  = '';
    }

    document.getElementById('inviteModal').addEventListener('show.bs.modal', resetInviteModal);
    ['input', 'change'].forEach(function (evt) {
        document.getElementById('inviteFormStep').addEventListener(evt, hideInviteFormMessage);
    });

    window.openInviteModal = function() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('inviteModal')).show();
    };

    window.sendInvite = function(sendEmail) {
        const emailInput = document.getElementById('inviteEmail');
        const nameInput  = document.getElementById('inviteName');
        const roleSelect = document.getElementById('inviteRole');
        const email  = emailInput.value.trim();
        const name   = nameInput.value.trim();
        const roleId = roleSelect.value;
        if (!email)  { showInviteFormMessage('Email address is required.', emailInput); return; }
        if (!name)   { showInviteFormMessage('Full name is required.', nameInput); return; }
        if (!roleId) { showInviteFormMessage('Please select a role.', roleSelect); return; }
        hideInviteFormMessage();

        // Disable both buttons and show spinner on the one clicked
        const activeText = document.getElementById(sendEmail ? 'inviteBtnText' : 'inviteCopyBtnText');
        const spinner    = document.getElementById(sendEmail ? 'inviteBtnSpinner' : 'inviteCopyBtnSpinner');
        document.getElementById('inviteSubmitBtn').disabled = true;
        document.getElementById('inviteCopyBtn').disabled   = true;
        activeText.textContent = sendEmail ? 'Sending…' : 'Generating…';
        spinner.style.display = 'inline-block';

        doInviteFetch({ email, name, role_id: roleId, send_email: sendEmail }, email, name, sendEmail);
    };

    function inviteErrorMessage(body) {
        if (body && typeof body === 'object') {
            if (body.errors && typeof body.errors === 'object') {
                const first = Object.values(body.errors)[0];
                const text = Array.isArray(first) ? first[0] : first;
                if (typeof text === 'string' && text !== '') return text;
            }
            if (typeof body.message === 'string' && body.message !== '') return body.message;
            if (typeof body.error === 'string' && body.error !== '') return body.error;
        }
        return INVITE_GENERIC_ERROR;
    }

    function showInviteResult(nodes) {
        const box = document.getElementById('inviteLinkResult');
        box.textContent = '';
        nodes.forEach(function (node) { box.appendChild(node); });
        document.getElementById('inviteFormStep').style.display    = 'none';
        document.getElementById('inviteModalFooter').style.display = 'none';
        document.getElementById('inviteLinkStep').style.display    = '';
    }

    function showInviteError(message) {
        const box = inviteEl('div', 'alert alert-danger');
        box.appendChild(inviteEl('i', 'ti ti-alert-circle me-2'));
        box.appendChild(document.createTextNode(message));

        const retry = inviteEl('button', 'btn btn-sm text-white', 'Try again');
        retry.type = 'button';
        retry.style.background = 'var(--wb-maroon)';
        retry.addEventListener('click', showInviteForm);

        const close = inviteEl('button', 'btn btn-sm btn-outline-secondary', 'Close');
        close.type = 'button';
        close.setAttribute('data-bs-dismiss', 'modal');

        const actions = inviteEl('div', 'd-flex gap-2 mt-2');
        actions.appendChild(retry);
        actions.appendChild(close);
        showInviteResult([box, actions]);
    }

    function showInviteSuccess(body, email, name, sendEmail) {
        const nodes = [];

        if (sendEmail) {
            const status = inviteEl('div', (body.email_sent ? 'alert alert-success' : 'alert alert-warning') + ' py-2 mb-3');
            if (body.email_sent) {
                status.appendChild(inviteEl('i', 'ti ti-mail-check me-1'));
                status.appendChild(document.createTextNode('Invite email sent to '));
                status.appendChild(inviteEl('strong', '', email));
            } else {
                status.appendChild(inviteEl('i', 'ti ti-alert-triangle me-1'));
                status.appendChild(document.createTextNode('Email could not be delivered — copy the link below and send it manually.'));
            }
            nodes.push(status);
        }

        const intro = inviteEl('p', 'text-muted small mb-2');
        intro.appendChild(document.createTextNode('Link for '));
        intro.appendChild(inviteEl('strong', '', name));
        intro.appendChild(document.createTextNode(' — expires in '));
        intro.appendChild(inviteEl('strong', '', '7 days'));
        intro.appendChild(document.createTextNode(', single use.'));
        nodes.push(intro);

        const input = inviteEl('input', 'form-control form-control-sm font-monospace');
        input.type = 'text';
        input.id = 'inviteLinkInput';
        input.value = body.link;
        input.readOnly = true;

        const copyLabel = inviteEl('span', '', 'Copy');
        const copyBtn = inviteEl('button', 'btn btn-outline-secondary btn-sm');
        copyBtn.type = 'button';
        copyBtn.appendChild(inviteEl('i', 'ti ti-copy me-1'));
        copyBtn.appendChild(copyLabel);
        let copyTimer = null;
        copyBtn.addEventListener('click', function () {
            input.select();
            let copied = false;
            try { copied = document.execCommand('copy'); } catch (e) { copied = false; }
            copyLabel.textContent = copied ? 'Copied' : 'Copy failed';
            clearTimeout(copyTimer);
            copyTimer = setTimeout(function () { copyLabel.textContent = 'Copy'; }, 2000);
        });

        const group = inviteEl('div', 'input-group mb-0');
        group.appendChild(input);
        group.appendChild(copyBtn);
        nodes.push(group);

        const hint = inviteEl('p', 'text-muted mt-2 mb-0');
        hint.style.fontSize = '.75rem';
        hint.appendChild(inviteEl('i', 'ti ti-brand-whatsapp me-1'));
        hint.appendChild(document.createTextNode('Paste this link in WhatsApp, Slack, or any channel.'));
        nodes.push(hint);

        const done = inviteEl('button', 'btn btn-sm text-white flex-grow-1', 'Done');
        done.type = 'button';
        done.style.background = 'var(--wb-maroon)';
        done.setAttribute('data-bs-dismiss', 'modal');

        const another = inviteEl('button', 'btn btn-sm btn-outline-secondary', 'Invite another person');
        another.type = 'button';
        another.addEventListener('click', resetInviteModal);

        const actions = inviteEl('div', 'd-flex gap-2 mt-3');
        actions.appendChild(done);
        actions.appendChild(another);
        nodes.push(actions);

        showInviteResult(nodes);
    }

    function doInviteFetch(payload, email, name, sendEmail) {
        fetch('{{ route("org-admin.invite") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.json().catch(() => null).then(body => ({ ok: r.ok, body: body })))
        .then(function (result) {
            finishInviteRequest();
            const body = result.body;
            if (!result.ok || !body || body.error || typeof body.link !== 'string' || body.link === '') {
                showInviteError(inviteErrorMessage(body));
                return;
            }
            showInviteSuccess(body, email, name, sendEmail);
        })
        .catch(function () {
            finishInviteRequest();
            showInviteError(INVITE_GENERIC_ERROR);
        });
    }

    // ── Role filter ─────────────────────────────────────────────────────────
    window.filterMembers = function() {
        const q = document.getElementById('filterRole').value.toLowerCase();
        document.querySelectorAll('.member-row').forEach(row => {
            const roles = row.dataset.roles || '';
            row.style.display = (!q || roles.includes(q)) ? '' : 'none';
        });
    };
})();
</script>
@endsection

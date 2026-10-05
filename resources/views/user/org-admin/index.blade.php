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
            <div class="bg-light rounded p-2 mb-4 text-start" style="font-size:.8rem">
                <i class="ti ti-info-circle me-1 text-muted"></i>
                Keeping both is fine — removing transfers sole use to the new assignee.
            </div>
            <button type="button" class="btn text-white w-100 mb-2" style="background:var(--wb-maroon)" id="peelOffConfirmBtn">
                Yes, remove from mine
            </button>
            <button type="button" class="btn btn-link text-muted w-100" data-bs-dismiss="modal">Keep on my account</button>
        </div>
    </div>
</div>

<script>
(function () {
    const RBAC = {
        assignUrl: "{{ route('org-admin.roles.assign') }}",
        peelOffUrl: "{{ route('org-admin.peel-off') }}",
        csrf: "{{ csrf_token() }}",
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
                { id: {{ $assignment->id }}, roleId: {{ $assignment->role_id }}, roleName: @json($assignment->role->name) },
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

    function renderDrawer(m) {
        const initials = m.name.substring(0,2).toUpperCase();
        const rolesList = m.roles.map(r =>
            `<span class="role-tag" style="display:inline-flex;align-items:center;gap:.3rem">
                ${r.roleName}
                ${RBAC.canManageRoles ? `<button onclick="removeRole(${r.id}, ${m.id})" class="btn p-0 border-0 bg-transparent text-muted" style="line-height:1;font-size:.8rem" title="Remove">×</button>` : ''}
            </span>`
        ).join('') || '<span class="text-muted small">No roles assigned</span>';

        const roleOptions = rolesData.map(r =>
            `<option value="${r.id}">${r.category} — ${r.name}</option>`
        ).join('');

        document.getElementById('drawerBody').innerHTML = `
            <div class="text-center mb-4">
                <span class="avatar avatar-lg rounded-circle d-inline-flex align-items-center justify-content-center"
                      style="background:var(--wb-maroon);color:#fff;font-size:1.2rem;font-weight:700;width:72px;height:72px">
                    ${initials}
                </span>
                <h6 class="fw-bold mt-3 mb-0">${m.name}</h6>
                <small class="text-muted">${m.email}</small>
                <br><small class="text-muted">Joined ${m.joined}</small>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-semibold mb-0">Current Roles</label>
                    ${RBAC.canManageRoles ? `<button class="btn btn-xs btn-outline-secondary btn-sm" onclick="showAddRoleForm(${m.id})"><i class="ti ti-plus"></i> Add role</button>` : ''}
                </div>
                <div id="drawerRoles">${rolesList}</div>
                ${RBAC.canManageRoles ? `
                <div id="addRoleForm" class="mt-2" style="display:none">
                    <select id="addRoleSelect" class="form-select form-select-sm mb-2">
                        <option value="">Select role…</option>
                        ${roleOptions}
                    </select>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm text-white flex-grow-1" style="background:var(--wb-maroon)"
                                onclick="assignRoleFromDrawer(${m.id})">Assign</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="hideAddRoleForm()">Cancel</button>
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
            showPeelOff(data.role_name, data.role_id, memberId);
        } else {
            location.reload();
        }
    };

    window.removeRole = async function(assignmentId, memberId) {
        if (!confirm('Remove this role from the member?')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/org-admin/roles/${assignmentId}`;
        form.innerHTML = `<input name="_token" value="${RBAC.csrf}"><input name="_method" value="DELETE">`;
        document.body.appendChild(form);
        form.submit();
    };

    // ── Peel-off ─────────────────────────────────────────────────────────────
    function showPeelOff(roleName, roleId, memberId) {
        document.getElementById('peelOffRoleName').textContent = roleName;
        document.getElementById('peelOffConfirmBtn').onclick = async function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('peelOffModal'));
            await fetch(RBAC.peelOffUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': RBAC.csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ role_id: roleId }),
            });
            modal.hide();
            location.reload();
        };
        new bootstrap.Modal(document.getElementById('peelOffModal')).show();
    }

    // ── Invite modal ─────────────────────────────────────────────────────────
    window.openInviteModal = function() {
        // Reset to form step
        document.getElementById('inviteFormStep').style.display   = '';
        document.getElementById('inviteLinkStep').style.display    = 'none';
        document.getElementById('inviteModalFooter').style.display = '';
        document.getElementById('inviteBtnText').textContent       = 'Send invite email';
        document.getElementById('inviteCopyBtnText').textContent   = 'Copy link instead';
        document.getElementById('inviteEmail').value = '';
        document.getElementById('inviteName').value  = '';
        document.getElementById('inviteRole').value  = '';
        new bootstrap.Modal(document.getElementById('inviteModal')).show();
    };

    window.sendInvite = function(sendEmail) {
        const email  = document.getElementById('inviteEmail').value.trim();
        const name   = document.getElementById('inviteName').value.trim();
        const roleId = document.getElementById('inviteRole').value;
        if (!email) { alert('Email address is required.'); return; }
        if (!name)  { alert('Full name is required.'); return; }
        if (!roleId){ alert('Please select a role.'); return; }

        // Disable both buttons and show spinner on the one clicked
        const emailBtn   = document.getElementById('inviteSubmitBtn');
        const copyBtn    = document.getElementById('inviteCopyBtn');
        const activeText = document.getElementById(sendEmail ? 'inviteBtnText' : 'inviteCopyBtnText');
        const spinner    = document.getElementById(sendEmail ? 'inviteBtnSpinner' : 'inviteCopyBtnSpinner');
        emailBtn.disabled = true;
        copyBtn.disabled  = true;
        activeText.textContent = sendEmail ? 'Sending…' : 'Generating…';
        spinner.style.display = 'inline-block';

        doInviteFetch({ email, name, role_id: roleId, send_email: sendEmail }, emailBtn, copyBtn, email, name, sendEmail);
    };

    function doInviteFetch(payload, emailBtn, copyBtn, email, name, sendEmail) {
        fetch('{{ route("org-admin.invite") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (emailBtn) { emailBtn.disabled = false; copyBtn.disabled = false; }
            document.getElementById('inviteBtnText').textContent     = 'Send invite email';
            document.getElementById('inviteCopyBtnText').textContent = 'Copy link instead';
            document.getElementById('inviteBtnSpinner').style.display     = 'none';
            document.getElementById('inviteCopyBtnSpinner').style.display = 'none';

            document.getElementById('inviteFormStep').style.display   = 'none';
            document.getElementById('inviteModalFooter').style.display = 'none';
            document.getElementById('inviteLinkStep').style.display   = '';

            if (data.error) {
                document.getElementById('inviteLinkResult').innerHTML =
                    `<div class="alert alert-danger"><i class="ti ti-alert-circle me-2"></i>${data.error}</div>
                     <button class="btn btn-sm btn-outline-secondary mt-2" onclick="resetInviteModal()">Try again</button>`;
                return;
            }

            if (data.type === 'confirm_existing') {
                document.getElementById('inviteLinkResult').innerHTML =
                    `<div class="alert alert-warning py-2 mb-3">
                         <i class="ti ti-user-check me-1"></i>
                         <strong>${data.user_name}</strong> already has a Wisselbanken account.
                         Adding them will assign the <strong>${data.role_name}</strong> role to their account immediately.
                     </div>
                     <p class="text-muted small mb-3">No invite email will be sent — they already have access. Continue?</p>
                     <div class="d-flex gap-2">
                         <button class="btn btn-sm text-white flex-grow-1" style="background:#6b1c1c"
                             onclick="confirmExistingAssign('${email}', '${name}', ${JSON.stringify(payload.role_id)})">
                             Yes, add them
                         </button>
                         <button class="btn btn-sm btn-outline-secondary" onclick="resetInviteModal()">Cancel</button>
                     </div>`;
                return;
            }

            if (data.type === 'existing') {
                document.getElementById('inviteLinkResult').innerHTML =
                    `<div class="alert alert-success"><i class="ti ti-check me-2"></i>${data.message}</div>
                     <p class="text-muted small mb-0">They can now log in and switch to this organization.</p>`;
            } else {
                const link = data.link;

                let statusHtml = '';
                if (sendEmail) {
                    statusHtml = data.email_sent
                        ? `<div class="alert alert-success py-2 mb-3">
                               <i class="ti ti-mail-check me-1"></i>
                               Invite email sent to <strong>${email}</strong>
                           </div>`
                        : `<div class="alert alert-warning py-2 mb-3">
                               <i class="ti ti-alert-triangle me-1"></i>
                               Email could not be delivered — copy the link below and send it manually.
                           </div>`;
                }

                document.getElementById('inviteLinkResult').innerHTML =
                    `${statusHtml}
                     <p class="text-muted small mb-2">
                         Link for <strong>${name}</strong> — expires in <strong>7 days</strong>, single use.
                     </p>
                     <div class="input-group mb-0">
                         <input type="text" id="inviteLinkInput" class="form-control form-control-sm font-monospace" value="${link}" readonly>
                         <button class="btn btn-outline-secondary btn-sm" onclick="copyInviteLink()">
                             <i class="ti ti-copy"></i> Copy
                         </button>
                     </div>
                     <p class="text-muted mt-2 mb-0" style="font-size:.75rem">
                         <i class="ti ti-brand-whatsapp me-1"></i>Paste this link in WhatsApp, Slack, or any channel.
                     </p>`;
            }
        })
        .catch(() => {
            if (emailBtn) { emailBtn.disabled = false; copyBtn.disabled = false; }
            document.getElementById('inviteBtnText').textContent     = 'Send invite email';
            document.getElementById('inviteCopyBtnText').textContent = 'Copy link instead';
            document.getElementById('inviteBtnSpinner').style.display     = 'none';
            document.getElementById('inviteCopyBtnSpinner').style.display = 'none';
            alert('Something went wrong. Please try again.');
        });
    }

    window.confirmExistingAssign = function(email, name, roleId) {
        doInviteFetch({ email, name, role_id: roleId, confirmed: true }, null, null, email, name, false);
    };

    window.copyInviteLink = function() {
        const input = document.getElementById('inviteLinkInput');
        input.select();
        document.execCommand('copy');
        alert('Link copied to clipboard!');
    };

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

@extends('user.layouts.app')

@section('seo')
<title>Organization Overview | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .stat-card { border:0;border-radius:.75rem;box-shadow:0 .125rem .5rem rgba(0,0,0,.08); }
    .badge-P1 { background:#6b1c1c;color:#fff; }
    .badge-P2 { background:#c0392b;color:#fff;opacity:.8; }
    .activity-avatar { width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:#fff;flex-shrink:0; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-label-secondary" style="font-size:.7rem">{{ strtoupper($org->name) }}</span>
                @if ($org->org_type)
                    <span class="text-muted small">· {{ $org->org_type }}</span>
                @endif
            </div>
            <h4 class="mb-0 fw-bold">Organization Dashboard</h4>
            <p class="text-muted small mb-0">Overview of your team's access, roles, and recent activity</p>
        </div>
        <div class="d-flex gap-2">
            @canDo('user_management', 'F')
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#delegateModal">
                <i class="ti ti-shield-check me-1"></i> Grant Delegation
            </button>
            <button class="btn btn-sm text-white" style="background:var(--wb-maroon)" data-bs-toggle="modal" data-bs-target="#inviteModal">
                <i class="ti ti-user-plus me-1"></i> Invite Member
            </button>
            @endCanDo
        </div>
    </div>

    @include('user.org-admin._nav')

    @canDo('user_management', 'R')
    {{-- Stat cards — admin/manager only --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0" style="color:var(--wb-maroon)">{{ $stats['members'] }}</div>
                <small class="text-muted">Team Members</small>
                <div><small class="text-success" style="font-size:.72rem">Active</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0" style="color:var(--wb-maroon)">{{ $stats['active_roles'] }}</div>
                <small class="text-muted">Active Roles</small>
                <div><small class="text-muted" style="font-size:.72rem">Defined</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0" style="color:var(--wb-maroon)">{{ $stats['delegations'] }}</div>
                <small class="text-muted">Delegations Active</small>
                <div><small class="text-warning" style="font-size:.72rem">Temporary</small></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center py-3">
                <div class="fw-bold fs-3 mb-0" style="color:var(--wb-maroon)">{{ $stats['api_tokens'] }}</div>
                <small class="text-muted">API Tokens</small>
                <div><small class="text-muted" style="font-size:.72rem">Connected</small></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Role Coverage --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-semibold mb-0">Role Coverage</h6>
                        <small class="text-muted">Which P1/P2 roles are filled in your org</small>
                    </div>
                    <a href="{{ route('org-admin.roles.list') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-2 small">Role Name</th>
                                <th class="py-2 small">Phase</th>
                                <th class="py-2 small">Assigned To</th>
                                <th class="py-2 small pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allRoles as $role)
                                <tr>
                                    <td class="ps-4 py-2">
                                        <span class="fw-medium small">{{ $role->name }}</span>
                                    </td>
                                    <td class="py-2">
                                    </td>
                                    <td class="py-2">
                                        @if ($role->filled)
                                            <div class="d-flex align-items-center">
                                                @foreach ($role->assignees->take(3) as $assignment)
                                                    <div class="activity-avatar me-1" style="background:var(--wb-maroon);font-size:.58rem"
                                                         title="{{ optional($assignment->user)->name }}">
                                                        {{ strtoupper(substr(optional($assignment->user)->name ?? '?', 0, 2)) }}
                                                    </div>
                                                @endforeach
                                                @if ($role->assignees->count() > 3)
                                                    <span class="badge bg-label-secondary" style="font-size:.65rem">+{{ $role->assignees->count() - 3 }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted small fst-italic">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4">
                                        <span class="badge {{ $role->filled ? 'bg-label-success' : 'bg-label-warning' }}" style="font-size:.65rem">
                                            {{ $role->filled ? 'Filled' : 'Empty' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-semibold mb-0">Recent Activity</h6>
                </div>
                <div class="card-body p-0">
                    @forelse ($recentActivity as $activity)
                        <div class="d-flex align-items-start gap-3 px-4 py-3 border-bottom">
                            <div class="activity-avatar mt-1" style="background:{{ $activity->is_active ? '#6b1c1c' : '#adb5bd' }}">
                                {{ strtoupper(substr(optional($activity->user)->name ?? '?', 0, 2)) }}
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-0 small">
                                    <strong>{{ optional($activity->user)->name ?? 'Unknown' }}</strong>
                                    {{ $activity->is_active ? 'was assigned' : 'was removed from' }}
                                    <span class="fw-semibold" style="color:var(--wb-maroon)">{{ optional($activity->role)->name ?? '—' }}</span>
                                </p>
                                <small class="text-muted">
                                    by {{ optional($activity->assignedBy)->name ?? 'System' }}
                                </small>
                            </div>
                            <small class="text-muted flex-shrink-0">{{ $activity->updated_at->diffForHumans() }}</small>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="ti ti-activity fs-2 d-block mb-2"></i>
                            <p class="mb-0 small">No recent activity</p>
                        </div>
                    @endforelse
                </div>
                @if ($recentActivity->count() >= 8)
                    <div class="card-footer bg-white border-top text-center py-2">
                        <a href="{{ route('org-admin.audit-log') }}" class="text-muted small">View full audit log →</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endCanDo

    @cannotDo('user_management', 'R')
    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center" style="min-height:260px">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(107,28,28,.08);display:flex;align-items:center;justify-content:center;margin-bottom:1rem">
            <i class="ti ti-shield-check" style="font-size:1.8rem;color:#6b1c1c"></i>
        </div>
        <h5 class="fw-semibold mb-1">Organization Overview</h5>
        <p class="text-muted mb-4" style="max-width:360px;font-size:.875rem">
            Your role doesn't include organization management access.<br>
            You can review the roles you have been assigned below.
        </p>
        <a href="{{ route('org-admin.my-roles') }}" class="btn text-white px-4" style="background:#6b1c1c;border-radius:.5rem">
            <i class="ti ti-id-badge-2 me-1"></i> View My Roles
        </a>
    </div>
    @endCannotDo

</div>

{{-- Invite Member Modal --}}
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
                                <option value="{{ $role->id }}">{{ $role->category }} — {{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                {{-- Step 2: result --}}
                <div id="inviteLinkStep" style="display:none">
                    <div id="inviteLinkResult"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 flex-column align-items-stretch gap-2" id="inviteModalFooter">
                <button type="button" class="btn text-white w-100" style="background:#6b1c1c" id="inviteSubmitBtn" onclick="sendInvite(true)">
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

<script>
(function () {
    window.openInviteModal = function() {
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
        if (!email)  { alert('Email address is required.'); return; }
        if (!name)   { alert('Full name is required.'); return; }
        if (!roleId) { alert('Please select a role.'); return; }

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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
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
            document.getElementById('inviteLinkStep').style.display    = '';

            if (data.error) {
                document.getElementById('inviteLinkResult').innerHTML =
                    `<div class="alert alert-danger"><i class="ti ti-alert-circle me-2"></i>${data.error}</div>
                     <button class="btn btn-sm btn-outline-secondary mt-2" onclick="openInviteModal()">Try again</button>`;
                return;
            }

            if (data.type === 'confirm_existing') {
                document.getElementById('inviteLinkResult').innerHTML =
                    `<div class="alert alert-warning py-2 mb-3">
                         <i class="ti ti-user-check me-1"></i>
                         <strong>${data.user_name}</strong> already has a Wisselbanken account.
                         Adding them will assign the <strong>${data.role_name}</strong> role immediately.
                     </div>
                     <p class="text-muted small mb-3">No invite email will be sent — they already have access. Continue?</p>
                     <div class="d-flex gap-2">
                         <button class="btn btn-sm text-white flex-grow-1" style="background:#6b1c1c"
                             onclick="confirmExistingAssign('${email}', '${name}', ${JSON.stringify(payload.role_id)})">
                             Yes, add them
                         </button>
                         <button class="btn btn-sm btn-outline-secondary" onclick="openInviteModal()">Cancel</button>
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
                        ? `<div class="alert alert-success py-2 mb-3"><i class="ti ti-mail-check me-1"></i>Invite email sent to <strong>${email}</strong></div>`
                        : `<div class="alert alert-warning py-2 mb-3"><i class="ti ti-alert-triangle me-1"></i>Email could not be delivered — copy the link below and send it manually.</div>`;
                }
                document.getElementById('inviteLinkResult').innerHTML =
                    `${statusHtml}
                     <p class="text-muted small mb-2">Link for <strong>${name}</strong> — expires in <strong>7 days</strong>, single use.</p>
                     <div class="input-group mb-0">
                         <input type="text" id="inviteLinkInput" class="form-control form-control-sm font-monospace" value="${link}" readonly>
                         <button class="btn btn-outline-secondary btn-sm" onclick="copyInviteLink()">
                             <i class="ti ti-copy"></i> Copy
                         </button>
                     </div>
                     <p class="text-muted mt-2 mb-0" style="font-size:.75rem"><i class="ti ti-brand-whatsapp me-1"></i>Paste this link in WhatsApp, Slack, or any channel.</p>`;
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
        input.blur();
    };
})();
</script>

{{-- Grant Delegation Modal --}}
<div class="modal fade" id="delegateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-semibold">Grant Delegation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">To grant a delegation, go to the Delegations page.</p>
            </div>
            <div class="modal-footer border-0">
                <a href="{{ route('org-admin.delegations.index') }}" class="btn text-white" style="background:var(--wb-maroon)">Go to Delegations</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('admin.layouts.app')

@php
$permIcon = fn(string $slug): string => match($slug) {
    'procurement'                        => 'ti-shopping-cart',
    'approval_authority'                 => 'ti-circle-check',
    'estimate_management'                => 'ti-file-invoice',
    'project_management'                 => 'ti-layout-kanban',
    'quote_rfq_management'               => 'ti-mail-forward',
    'product_management'                 => 'ti-package',
    'user_management'                    => 'ti-users',
    'delegation_and_impersonation'       => 'ti-arrows-exchange',
    'organization_management'            => 'ti-building',
    'budget_and_cost_control'            => 'ti-chart-bar',
    'financial_access'                   => 'ti-coin',
    'invoice_and_payment_processing'     => 'ti-receipt',
    'pricing_management'                 => 'ti-tags',
    'seller_payout_and_settlement'       => 'ti-cash',
    'inventory_and_availability'         => 'ti-box',
    'order_fulfillment'                  => 'ti-truck',
    'returns_and_rma'                    => 'ti-arrow-back-up',
    'compliance_and_certification'       => 'ti-certificate',
    'contract_and_agreement_management'  => 'ti-file-text',
    'reporting_and_analytics'            => 'ti-chart-line',
    'audit_and_logging'                  => 'ti-clipboard-list',
    'manufacturer_controls'              => 'ti-factory',
    'catalog_taxonomy_and_data_quality'  => 'ti-category',
    'system_administration'              => 'ti-settings',
    'ai_extraction_review'               => 'ti-brain',
    default                              => 'ti-lock',
};

$permShort = fn(string $slug): string => match($slug) {
    'procurement'                        => 'Orders',
    'approval_authority'                 => 'Approvals',
    'estimate_management'                => 'Estimates',
    'project_management'                 => 'Lists',
    'quote_rfq_management'               => 'RFQs',
    'product_management'                 => 'Products',
    'user_management'                    => 'Team',
    'delegation_and_impersonation'       => 'Delegations',
    'organization_management'            => 'Org Mgmt',
    'budget_and_cost_control'            => 'Budget',
    'financial_access'                   => 'Finance',
    'invoice_and_payment_processing'     => 'Invoices',
    'pricing_management'                 => 'Pricing',
    'seller_payout_and_settlement'       => 'Payouts',
    'inventory_and_availability'         => 'Inventory',
    'order_fulfillment'                  => 'Fulfillment',
    'returns_and_rma'                    => 'Returns',
    'compliance_and_certification'       => 'Compliance',
    'contract_and_agreement_management'  => 'Contracts',
    'reporting_and_analytics'            => 'Reports',
    'audit_and_logging'                  => 'Audit Log',
    'manufacturer_controls'              => 'Mfr Admin',
    'catalog_taxonomy_and_data_quality'  => 'Catalog',
    'system_administration'              => 'System',
    'ai_extraction_review'               => 'AI Review',
    default                              => $slug,
};

$permDesc = fn(string $slug): string => match($slug) {
    'procurement'                        => 'Checkout cart, place orders, view order history',
    'approval_authority'                 => 'Approve or reject orders submitted by others',
    'estimate_management'                => 'Create, edit, and share cost estimates',
    'project_management'                 => 'Save and manage product lists',
    'quote_rfq_management'               => 'Send RFQs to suppliers and respond to incoming ones',
    'product_management'                 => "Manage the org's own product & service catalogue",
    'user_management'                    => 'Invite members, assign roles, manage customers',
    'delegation_and_impersonation'       => 'Grant or receive temporary permission delegations',
    'organization_management'            => 'Edit org profile, settings, and configuration',
    'budget_and_cost_control'            => 'Set and track project budgets and cost limits',
    'financial_access'                   => 'View financial data, P&L, and sensitive reports',
    'invoice_and_payment_processing'     => 'Create, send, and process invoices and payments',
    'pricing_management'                 => 'Set and manage product and service pricing',
    'seller_payout_and_settlement'       => 'Manage seller payouts and settlement reports',
    'inventory_and_availability'         => 'View and manage stock levels and availability',
    'order_fulfillment'                  => 'Process and fulfil orders placed by buyers',
    'returns_and_rma'                    => 'Handle product returns and RMA requests',
    'compliance_and_certification'       => 'Manage compliance documents and certifications',
    'contract_and_agreement_management'  => 'Create and manage contracts and agreements',
    'reporting_and_analytics'            => 'Access dashboards, reports, and analytics',
    'audit_and_logging'                  => 'View system audit logs and enforcement history',
    'manufacturer_controls'              => 'Admin-level manufacturer catalogue management',
    'catalog_taxonomy_and_data_quality'  => 'Admin-level product taxonomy and data quality',
    'system_administration'              => 'Platform-level admin: RBAC, users, system config',
    'ai_extraction_review'               => 'Review and approve AI-extracted document data',
    default                              => '',
};

// Active permission groups only (wired in route_permission_map.php).
// Future groups are commented out below — uncomment when the module is live.
$permSections = [
    // ── Core Operations (9) ────────────────────────────────────────────────
    'procurement'                        => 'Core Operations',
    'approval_authority'                 => 'Core Operations',
    'estimate_management'                => 'Core Operations',
    'project_management'                 => 'Core Operations',
    'quote_rfq_management'               => 'Core Operations',
    'product_management'                 => 'Core Operations',
    'user_management'                    => 'Core Operations',
    'delegation_and_impersonation'       => 'Core Operations',
    'organization_management'            => 'Core Operations',
    // ── Platform Admin (6) ────────────────────────────────────────────────
    'pricing_management'                 => 'Platform Admin',
    'order_fulfillment'                  => 'Platform Admin',
    'manufacturer_controls'              => 'Platform Admin',
    'catalog_taxonomy_and_data_quality'  => 'Platform Admin',
    'audit_and_logging'                  => 'Platform Admin',
    'system_administration'              => 'Platform Admin',
    // ── Future / not yet active — uncomment when module ships ─────────────
    // 'budget_and_cost_control'            => 'Finance & Commercial',
    // 'financial_access'                   => 'Finance & Commercial',
    // 'invoice_and_payment_processing'     => 'Finance & Commercial',
    // 'seller_payout_and_settlement'       => 'Finance & Commercial',
    // 'inventory_and_availability'         => 'Supply Chain',
    // 'returns_and_rma'                    => 'Supply Chain',
    // 'compliance_and_certification'       => 'Compliance',
    // 'contract_and_agreement_management'  => 'Compliance',
    // 'reporting_and_analytics'            => 'Analytics',
    // 'ai_extraction_review'               => 'AI / Emerging',
];

$sectionColors = [
    'Core Operations' => '#6b1c1c',
    'Platform Admin'  => '#92400e',
    // Future sections — uncomment alongside $permSections entries above:
    // 'Finance & Commercial' => '#065f46',
    // 'Supply Chain'         => '#1d4ed8',
    // 'Compliance'           => '#6b21a8',
    // 'Analytics'            => '#0369a1',
    // 'AI / Emerging'        => '#374151',
];
@endphp

@section('seo')
<title>Role Designer | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }

    /* Matrix table — overflow:auto so both sticky axes work inside the box */
    .perm-table-wrap { overflow:auto; max-height:calc(100vh - 265px); border-radius:.75rem; border:1px solid #e9ecef; box-shadow:0 .125rem .5rem rgba(0,0,0,.06); }
    .perm-table { width:100%; border-collapse:separate; border-spacing:0; min-width:820px; }

    /* Sticky role-name column */
    .perm-table th.col-role,
    .perm-table td.col-role {
        position:sticky; left:0; z-index:2;
        background:#fff; border-right:2px solid #e9ecef;
        min-width:210px; max-width:230px;
    }

    /* Sticky section-banner row (first thead row) */
    .perm-table thead tr:first-child th { position:sticky; top:0; z-index:4; }
    .perm-table thead tr:first-child th.col-role { z-index:6; background:#f8f9fa; }

    /* Sticky column-name row (second thead row) */
    .perm-table thead tr:last-child th  { position:sticky; top:28px; z-index:4; }
    .perm-table thead tr:last-child th.col-role { z-index:6; background:#f8f9fa; }

    /* Header row base styles */
    .perm-table thead th {
        background:#f8f9fa; padding:.65rem .6rem; font-size:.7rem;
        font-weight:700; text-transform:uppercase; letter-spacing:.04em;
        color:#6c757d; border-bottom:2px solid #dee2e6; white-space:nowrap;
        text-align:center; width:90px;
    }
    .perm-table thead th.col-role { text-align:left; padding-left:1rem; width:auto; }

    /* Body rows */
    .perm-table tbody tr { transition:background .1s; }
    .perm-table tbody tr:hover td { background:#fafafa; }
    .perm-table tbody tr:hover td.col-role { background:#fafafa; }
    .perm-table tbody td {
        padding:.55rem .4rem; text-align:center;
        border-bottom:1px solid #f0f0f0; vertical-align:middle;
    }
    .perm-table tbody td.col-role {
        padding:.7rem 1rem; text-align:left; vertical-align:middle;
    }

    /* Category separator rows */
    .perm-table tr.cat-row td {
        background:#f1f3f5; padding:.38rem 1rem; font-size:.69rem;
        font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#868e96;
        border-bottom:1px solid #dee2e6;
    }
    .perm-table tr.cat-row td.col-role { background:#f1f3f5; }

    /* Level badges */
    .lvl-badge {
        display:inline-flex; align-items:center; justify-content:center;
        width:32px; height:24px; border-radius:5px; font-size:.71rem; font-weight:700;
        border:0; letter-spacing:.02em;
        transition:transform .1s, box-shadow .1s;
    }
    .lvl-badge.editable { cursor:pointer; }
    .lvl-badge.editable:hover { transform:scale(1.15); box-shadow:0 3px 10px rgba(0,0,0,.2); }
    .lvl-F { background:#6b1c1c; color:#fff; }
    .lvl-A { background:#92400e; color:#fff; }
    .lvl-O { background:#1d4ed8; color:#fff; }
    .lvl-S { background:#065f46; color:#fff; }
    .lvl-R { background:#6b7280; color:#fff; }
    .lvl-none { background:#f0f0f0; color:#adb5bd; border:1px dashed #ced4da; }

    /* Level picker */
    .lvl-picker {
        display:none; position:fixed; z-index:9200;
        background:#fff; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.2);
        padding:1rem 1rem .75rem; width:230px; border:1px solid #e9ecef;
    }
    .lvl-picker.show { display:block; }
    .lvl-picker-title { font-size:.7rem; font-weight:700; color:#6c757d; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.65rem; line-height:1.3; }
    .lvl-opt {
        display:flex; align-items:flex-start; gap:.5rem; width:100%;
        border:0; background:none; padding:.4rem .45rem; border-radius:6px;
        font-size:.79rem; text-align:left; cursor:pointer; transition:background .1s; line-height:1.25;
    }
    .lvl-opt:hover { background:#f8f9fa; }
    .lvl-opt.is-current { background:var(--wb-maroon-light); }
    .lvl-dot {
        width:28px; height:20px; border-radius:4px; font-size:.68rem; font-weight:700;
        display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px;
    }
    .lvl-opt-label { font-weight:600; display:block; }
    .lvl-opt-desc { font-size:.69rem; color:#6c757d; display:block; margin-top:1px; }
    .lvl-picker-footer { margin-top:.6rem; padding-top:.5rem; border-top:1px solid #f0f0f0; }

    /* Saving state */
    .cell-saving { opacity:.45; pointer-events:none; }

    /* Toast */
    #perm-toast {
        position:fixed; bottom:24px; right:24px; z-index:9999;
        background:#1e293b; color:#fff; border-radius:10px; padding:.6rem 1rem;
        font-size:.81rem; display:flex; align-items:center; gap:.5rem;
        box-shadow:0 8px 28px rgba(0,0,0,.22); opacity:0;
        transition:opacity .2s; pointer-events:none;
    }
    #perm-toast.show { opacity:1; }

    /* Legend badges */
    .lg-badge { display:inline-flex;align-items:center;justify-content:center;width:22px;height:17px;border-radius:4px;font-size:.65rem;font-weight:700;color:#fff; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-1">
        <div>
            <h4 class="mb-0 fw-bold">Role Designer</h4>
            <p class="text-muted small mb-0">
                Configure which permissions each role has across all system modules.
                <span class="text-success ms-1" style="font-size:.78rem"><i class="ti ti-pencil me-1"></i>Click any cell to change a permission level.</span>
            </p>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="openAddRole">
            <i class="ti ti-plus me-1"></i> Add Role
        </button>
    </div>

    @include('admin.rbac._nav')

    {{-- Legend — hidden for now, uncomment to restore
    <div class="d-flex flex-wrap gap-3 mb-3 align-items-center" style="font-size:.77rem;color:#444">
        <span class="fw-semibold text-muted me-1">Permission levels:</span>
        <span class="d-flex align-items-center gap-1"><span class="lg-badge" style="background:#6b1c1c">F</span> Full</span>
        <span class="d-flex align-items-center gap-1"><span class="lg-badge" style="background:#92400e">A</span> Approve</span>
        <span class="d-flex align-items-center gap-1"><span class="lg-badge" style="background:#1d4ed8">O</span> Own</span>
        <span class="d-flex align-items-center gap-1"><span class="lg-badge" style="background:#065f46">S</span> Submit</span>
        <span class="d-flex align-items-center gap-1"><span class="lg-badge" style="background:#6b7280">R</span> Read</span>
        <span class="d-flex align-items-center gap-1"><span class="lg-badge" style="background:#e9ecef;color:#adb5bd;border:1px dashed #ced4da">—</span> No access</span>
    </div>
    --}}

    {{-- Matrix --}}
    <div class="perm-table-wrap">
        <table class="perm-table" id="permMatrix">
            @php
                $sectionSpans = [];
                foreach ($groups as $g) {
                    $sec   = $permSections[$g->slug] ?? 'Other';
                    $color = $sectionColors[$sec] ?? '#6c757d';
                    if (!$sectionSpans || end($sectionSpans)['label'] !== $sec) {
                        $sectionSpans[] = ['label' => $sec, 'color' => $color, 'span' => 1];
                    } else {
                        $sectionSpans[array_key_last($sectionSpans)]['span']++;
                    }
                }
            @endphp
            <thead>
                {{-- Section banner row --}}
                <tr>
                    <th class="col-role" style="border-bottom:0;padding:.25rem 1rem;font-size:.6rem;color:#adb5bd;text-transform:uppercase;letter-spacing:.05em">Modules →</th>
                    @foreach ($sectionSpans as $span)
                    <th colspan="{{ $span['span'] }}" style="background:{{ $span['color'] }};color:#fff;text-align:center;font-size:.62rem;font-weight:700;letter-spacing:.07em;padding:.3rem .4rem;border:0;text-transform:uppercase;white-space:nowrap">
                        {{ $span['label'] }}
                    </th>
                    @endforeach
                </tr>
                {{-- Column name row --}}
                <tr>
                    <th class="col-role">Role</th>
                    @foreach ($groups as $group)
                    @php $sec = $permSections[$group->slug] ?? 'Other'; $sc = $sectionColors[$sec] ?? '#6c757d'; @endphp
                    <th style="border-top:3px solid {{ $sc }}">
                        <div class="d-flex flex-column align-items-center gap-1" title="{{ $permDesc($group->slug) }}">
                            <i class="ti {{ $permIcon($group->slug) }}" style="font-size:.9rem;color:{{ $sc }}"></i>
                            <span>{{ $permShort($group->slug) }}</span>
                        </div>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $lastCat = null; @endphp
                @foreach ($roles as $role)
                    @if ($role->category !== $lastCat)
                        @php $lastCat = $role->category; @endphp
                        <tr class="cat-row">
                            <td colspan="{{ $groups->count() + 1 }}" class="col-role">{{ $role->category }}</td>
                        </tr>
                    @endif
                    <tr data-role-id="{{ $role->id }}">
                        <td class="col-role">
                            <div class="d-flex align-items-center gap-2">
                                <div class="flex-grow-1" style="min-width:0">
                                    <div class="fw-semibold" style="font-size:.84rem">{{ $role->name }}</div>
                                    @if ($role->description)
                                    <div class="text-muted text-truncate" style="font-size:.71rem;max-width:180px" title="{{ $role->description }}">{{ $role->description }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        @foreach ($groups as $group)
                            @php $level = $matrix[$role->id][$group->id] ?? null; @endphp
                            <td class="perm-cell"
                                data-role-id="{{ $role->id }}"
                                data-group-id="{{ $group->id }}"
                                data-current="{{ $level ?? '' }}"
                                data-role-name="{{ $role->name }}"
                                data-group-name="{{ $group->name }}">
                                <button class="lvl-badge editable lvl-{{ $level ?? 'none' }}" onclick="openPicker(this)" title="Click to edit">
                                    {{ $level ?? '—' }}
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="ti ti-info-circle me-1"></i>
        Levels apply globally — a role's permissions are the same across all organisations that use it.
        Changes take effect immediately for all members holding this role.
    </p>

</div>

{{-- ── Level picker ── --}}
<div id="lvlPicker" class="lvl-picker" role="dialog" aria-label="Select permission level">
    <div class="lvl-picker-title" id="lvlPickerTitle">Set level</div>
    <div id="lvlPickerBtns">
        @foreach ([
            'F' => ['#6b1c1c', 'Full',    'Create, read, update, delete & approve everything'],
            'A' => ['#92400e', 'Approve',  'Approve or act on others\' submissions'],
            'O' => ['#1d4ed8', 'Own',      'Act on own records only'],
            'S' => ['#065f46', 'Submit',   'Create or request — cannot approve'],
            'R' => ['#6b7280', 'Read',     'View-only access'],
        ] as $lvl => [$bg, $label, $desc])
        <button class="lvl-opt" data-lvl="{{ $lvl }}" onclick="selectLevel('{{ $lvl }}')">
            <span class="lvl-dot" style="background:{{ $bg }};color:#fff">{{ $lvl }}</span>
            <span>
                <span class="lvl-opt-label">{{ $label }}</span>
                <span class="lvl-opt-desc">{{ $desc }}</span>
            </span>
        </button>
        @endforeach
        <button class="lvl-opt" data-lvl="" onclick="selectLevel('')">
            <span class="lvl-dot" style="background:#f0f0f0;color:#adb5bd;border:1px dashed #ced4da">—</span>
            <span>
                <span class="lvl-opt-label">No access</span>
                <span class="lvl-opt-desc">Remove permission entirely</span>
            </span>
        </button>
    </div>
    <div class="lvl-picker-footer">
        <button class="btn btn-sm btn-outline-secondary w-100" onclick="closePicker()">Cancel</button>
    </div>
</div>

{{-- ── Add Role modal ── --}}
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.rbac.roles.create') }}" method="POST">
                @csrf
                <div class="modal-body py-3">
                    @if (session('success'))
                    <div class="alert alert-success py-2 mb-3"><i class="ti ti-check me-1"></i>{{ session('success') }}</div>
                    @endif
                    @if ($errors->any())
                    <div class="alert alert-danger py-2 mb-3">{{ $errors->first() }}</div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Site Supervisor" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control font-monospace" placeholder="e.g. site_supervisor" required maxlength="100" pattern="[a-z_]+" title="Lowercase letters and underscores only">
                        <small class="text-muted">Lowercase letters and underscores only.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Buyer/Contractor" required maxlength="100" list="catSuggestions">
                        <datalist id="catSuggestions">
                            @foreach ($roles->pluck('category')->unique()->sort() as $cat)
                            <option value="{{ $cat }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Phase <span class="text-danger">*</span></label>
                            <select name="phase" class="form-select" required>
                                <option value="P1">P1 — Core</option>
                                <option value="P2" selected>P2 — Extended</option>
                                <option value="P3">P3 — Future</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="2" maxlength="500" placeholder="What does this role do?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3 gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--wb-maroon)">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Toast --}}
<div id="perm-toast">
    <i id="perm-toast-icon" class="ti ti-check" style="color:#4ade80"></i>
    <span id="perm-toast-msg"></span>
</div>

<script>
(function () {

const BASE_URL = "{{ url('admin/rbac/roles') }}";
const CSRF     = "{{ csrf_token() }}";

const LVL_CLS = { F:'lvl-F', A:'lvl-A', O:'lvl-O', S:'lvl-S', R:'lvl-R', '':'lvl-none' };

// ── Picker state ─────────────────────────────────────────────────────────────
let activeCell = null;

window.openPicker = function (badge) {
    const cell    = badge.closest('.perm-cell');
    activeCell    = cell;
    const current = cell.dataset.current || '';

    document.getElementById('lvlPickerTitle').textContent =
        cell.dataset.roleName + ' → ' + cell.dataset.groupName;

    document.querySelectorAll('#lvlPickerBtns .lvl-opt').forEach(btn => {
        btn.classList.toggle('is-current', btn.dataset.lvl === current);
    });

    const picker = document.getElementById('lvlPicker');
    picker.classList.add('show');
    positionPicker(badge, picker);

    setTimeout(() => document.addEventListener('click', outsideClick), 0);
};

function positionPicker(trigger, picker) {
    const r  = trigger.getBoundingClientRect();
    const pw = 230;
    let left = r.left + r.width / 2 - pw / 2;
    let top  = r.bottom + 6;
    if (left + pw > window.innerWidth - 10) left = window.innerWidth - pw - 10;
    if (left < 8) left = 8;
    if (top + 320 > window.innerHeight) top = r.top - 320 - 4;
    picker.style.left = left + 'px';
    picker.style.top  = top + 'px';
}

function outsideClick(e) {
    if (!document.getElementById('lvlPicker').contains(e.target)) closePicker();
}

window.closePicker = function () {
    document.getElementById('lvlPicker').classList.remove('show');
    document.removeEventListener('click', outsideClick);
    activeCell = null;
};

// ── Save level ───────────────────────────────────────────────────────────────
window.selectLevel = async function (lvl) {
    if (!activeCell) return;
    const cell    = activeCell;
    const badge   = cell.querySelector('.lvl-badge');
    const roleId  = cell.dataset.roleId;
    const groupId = cell.dataset.groupId;
    closePicker();
    cell.classList.add('cell-saving');

    try {
        const res  = await fetch(`${BASE_URL}/${roleId}/permissions`, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
            body: JSON.stringify({
                permission_group_id: parseInt(groupId),
                access_level:        lvl || null,
            }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Save failed.');

        badge.textContent = lvl || '—';
        badge.className   = 'lvl-badge editable ' + (LVL_CLS[lvl] ?? LVL_CLS['']);
        cell.dataset.current = lvl;
        showToast(lvl ? `Set to ${lvl} — saved.` : 'Access removed.');
    } catch (err) {
        showToast('Error: ' + err.message, true);
    } finally {
        cell.classList.remove('cell-saving');
    }
};

// ── Toast ────────────────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg, isError) {
    const t    = document.getElementById('perm-toast');
    const icon = document.getElementById('perm-toast-icon');
    document.getElementById('perm-toast-msg').textContent = msg;
    icon.className   = isError ? 'ti ti-alert-circle' : 'ti ti-check';
    icon.style.color = isError ? '#f87171' : '#4ade80';
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Add Role button ───────────────────────────────────────────────────────────
document.getElementById('openAddRole').addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('addRoleModal')).show();
});

})();
</script>
@endsection

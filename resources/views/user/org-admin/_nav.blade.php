@php
use App\Services\Rbac\PermissionService;
$_navUser  = auth()->user();
$_navAdmin = $_navUser && $_navUser->role === 'admin';
$_navOrgId = session(config('rbac.current_org_session_key'));
$_navCan   = fn(string $g, string $l) => $_navUser && ($_navAdmin || ($_navOrgId && app(PermissionService::class)->checkPermission($_navUser->id, (int) $_navOrgId, $g, $l)));
@endphp

<nav class="oa-nav d-flex gap-1 mt-3 flex-wrap mb-4">

    {{-- Overview — any org member --}}
    <a href="{{ route('org-admin.overview') }}" {{ Request::is('org-admin/overview') ? 'class=active' : '' }}>
        <i class="ti ti-layout-dashboard"></i> Overview
    </a>

    {{-- Team — user_management:R --}}
    @if ($_navCan('user_management', 'R'))
    <a href="{{ route('org-admin.index') }}" {{ Request::routeIs('org-admin.index') ? 'class=active' : '' }}>
        <i class="ti ti-users"></i> Team
    </a>
    @endif

    {{-- My Roles — any org member --}}
    <a href="{{ route('org-admin.my-roles') }}" {{ Request::is('org-admin/my-roles') ? 'class=active' : '' }}>
        <i class="ti ti-id-badge-2"></i> My Roles
    </a>

    {{-- Roles — user_management:R --}}
    @if ($_navCan('user_management', 'R'))
    <a href="{{ route('org-admin.roles.list') }}" {{ Request::is('org-admin/roles-list') ? 'class=active' : '' }}>
        <i class="ti ti-shield-check"></i> Roles
    </a>
    @endif

    {{-- Delegations — delegation_and_impersonation:R --}}
    @if ($_navCan('delegation_and_impersonation', 'R'))
    <a href="{{ route('org-admin.delegations.index') }}" {{ Request::is('org-admin/delegations*') ? 'class=active' : '' }}>
        <i class="ti ti-arrows-exchange"></i> Delegations
    </a>
    @endif

    {{-- Connections — org-to-org trading partnerships (buyer→seller, GC→subcontractor, etc.) --}}
    @if ($_navCan('user_management', 'F'))
    <a href="{{ route('org-admin.connections.index') }}" {{ Request::is('org-admin/connections*') ? 'class=active' : '' }}>
        <i class="ti ti-network"></i> Connections
    </a>
    @endif

    {{-- Projects — user_management:F (owners/admins only) --}}
    @if ($_navCan('user_management', 'F'))
    <a href="{{ route('org-admin.projects.index') }}" {{ Request::is('org-admin/projects*') ? 'class=active' : '' }}>
        <i class="ti ti-briefcase"></i> Projects
    </a>
    @endif

    {{-- Audit Log — user_management:F (owners/admins only) --}}
    @if ($_navCan('user_management', 'F'))
    <a href="{{ route('org-admin.audit-log') }}" {{ Request::is('org-admin/audit-log') ? 'class=active' : '' }}>
        <i class="ti ti-clipboard-list"></i> Audit Log
    </a>
    @endif

    {{-- Org Settings — user_management:F (owners/admins only) --}}
    @if ($_navCan('user_management', 'F'))
    <a href="{{ route('org-admin.settings') }}" {{ Request::is('org-admin/settings') ? 'class=active' : '' }}>
        <i class="ti ti-settings"></i> Org Settings
    </a>
    @endif

</nav>

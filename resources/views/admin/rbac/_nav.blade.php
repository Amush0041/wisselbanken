{{-- RBAC admin sub-navigation --}}
<ul class="nav nav-pills flex-column flex-md-row mb-4">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.index') ? 'active' : '' }}" href="{{ route('admin.rbac.index') }}">
            <i class="ti ti-layout-dashboard me-1"></i> Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.roles') ? 'active' : '' }}" href="{{ route('admin.rbac.roles') }}">
            <i class="ti ti-table me-1"></i> Role Designer
        </a>
    </li>
<li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.organizations*') ? 'active' : '' }}" href="{{ route('admin.rbac.organizations') }}">
            <i class="ti ti-building me-1"></i> Organizations
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.users') ? 'active' : '' }}" href="{{ route('admin.rbac.users') }}">
            <i class="ti ti-users me-1"></i> Users
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.delegations') ? 'active' : '' }}" href="{{ route('admin.rbac.delegations') }}">
            <i class="ti ti-arrows-exchange me-1"></i> Delegations
        </a>
    </li>
    {{-- Service Accounts — hidden for now, enable in a future release
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.service-accounts') ? 'active' : '' }}" href="{{ route('admin.rbac.service-accounts') }}">
            <i class="ti ti-api me-1"></i> Service Accounts
        </a>
    </li>
    --}}
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.sod*') ? 'active' : '' }}" href="{{ route('admin.rbac.sod') }}">
            <i class="ti ti-git-compare me-1"></i> SoD Rules
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.audit-logs') ? 'active' : '' }}" href="{{ route('admin.rbac.audit-logs') }}">
            <i class="ti ti-clipboard-list me-1"></i> Audit Trail
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.rbac.enforcement') ? 'active' : '' }}" href="{{ route('admin.rbac.enforcement') }}">
            <i class="ti ti-shield-lock me-1"></i> Enforcement
        </a>
    </li>
</ul>

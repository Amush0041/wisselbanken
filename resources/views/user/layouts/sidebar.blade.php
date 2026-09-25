@php
use App\Services\Rbac\PermissionService;
$_sUser    = auth()->user();
$_sAdmin   = $_sUser && $_sUser->role === 'admin';
$_sOrgId   = session(config('rbac.current_org_session_key'));
$_sc       = fn(string $g, string $l) => $_sUser && ($_sAdmin || ($_sOrgId && app(PermissionService::class)->checkPermission($_sUser->id, (int) $_sOrgId, $g, $l)));
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{url('/')}}">
            <img src="{{asset('logo.png')}}" style="width:200px;">
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="ti menu-toggle-icon d-none d-xl-block align-middle"></i>
            <i class="ti ti-x d-block d-xl-none ti-md align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">

        {{-- Dashboard — always visible --}}
        <li class="menu-item {{ Request::is('user-dashboard') ? 'active' : '' }}">
            <a href="{{ url('user-dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-smart-home"></i>
                <div data-i18n="Dashboard">Dashboard</div>
            </a>
        </li>

        {{-- Product Filter — always visible (browsing catalogue) --}}
        <li class="menu-item {{ Request::is('product-filter') ? 'active' : '' }}">
            <a href="{{ url('product-filter') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-filter"></i>
                <div data-i18n="Product Filter">Product Filter</div>
            </a>
        </li>

        {{-- Projects — project_management:R --}}
        @if ($_sc('project_management', 'R'))
        <li class="menu-item {{ Request::is('projects') || Request::is('projects/*') ? 'active' : '' }}">
            <a href="{{ route('projects.index') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-folders"></i>
                <div data-i18n="Projects">Projects</div>
            </a>
        </li>
        @endif

        {{-- Lists — project_management:R --}}
        @if ($_sc('project_management', 'R'))
        <li class="menu-item {{ Request::is('view-lists') || Request::is('list-view/*') ? 'active' : '' }}">
            <a href="{{ url('view-lists') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-layout-kanban"></i>
                <div data-i18n="Lists">Lists</div>
            </a>
        </li>
        @endif

        {{-- Customers — quote_rfq_management:R (interim) --}}
        @if ($_sc('quote_rfq_management', 'R'))
        <li class="menu-item {{ Request::is('customers') || Request::is('customers/*') ? 'active' : '' }}">
            <a href="{{ url('customers') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-users"></i>
                <div data-i18n="Customers">Customers</div>
            </a>
        </li>
        @endif

        {{-- My Products — product_management:R --}}
        @if ($_sc('product_management', 'R'))
        <li class="menu-item {{ Request::is('user-products') || Request::is('user-products/*') ? 'active' : '' }}">
            <a href="{{ url('user-products') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-package"></i>
                <div data-i18n="My Products">My Products</div>
            </a>
        </li>

        {{-- My Services — product_management:R (same group) --}}
        <li class="menu-item {{ Request::is('user-services') || Request::is('user-services/*') ? 'active' : '' }}">
            <a href="{{ url('user-services') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-tool"></i>
                <div data-i18n="My Services">My Services</div>
            </a>
        </li>
        @endif

        {{-- Estimates — estimate_management:R --}}
        @if ($_sc('estimate_management', 'R'))
        <li class="menu-item {{ Request::is('quotes') || Request::is('quotes/*') ? 'active' : '' }}">
            <a href="{{ url('quotes') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-file-invoice"></i>
                <div data-i18n="Estimate">Estimate</div>
            </a>
        </li>
        @endif

        {{-- Orders — procurement:R --}}
        @if ($_sc('procurement', 'R'))
        <li class="menu-item {{ Request::is('view-orders') || Request::is('order-detail/*') ? 'active' : '' }}">
            <a href="{{ url('view-orders') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-shopping-cart"></i>
                <div data-i18n="Orders">Orders</div>
            </a>
        </li>
        @endif

        {{-- Approvals — approval_authority:A (only approvers see this) --}}
        @if ($_sc('approval_authority', 'A'))
        <li class="menu-item {{ Request::is('order-approvals') ? 'active' : '' }}">
            <a href="{{ url('order-approvals') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-circle-check"></i>
                <div data-i18n="Approvals">Approvals</div>
            </a>
        </li>
        @endif

        {{-- RFQs (buyer) — quote_rfq_management:R --}}
        @if ($_sc('quote_rfq_management', 'R'))
        <li class="menu-item {{ Request::is('rfq') || Request::is('rfq/*') ? 'active' : '' }}">
            <a href="{{ url('rfq') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-mail-forward"></i>
                <div data-i18n="RFQs">RFQs</div>
            </a>
        </li>

        {{-- Incoming RFQs (seller) — same permission group --}}
        <li class="menu-item {{ Request::is('rfq-incoming') ? 'active' : '' }}">
            <a href="{{ url('rfq-incoming') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-mail-opened"></i>
                <div data-i18n="Incoming RFQs">Incoming RFQs</div>
            </a>
        </li>
        @endif

        {{-- Plan Crosswalk — estimate_management:R --}}
        @if ($_sc('estimate_management', 'R'))
        <li class="menu-item {{ Request::is('plan-crosswalk') || Request::is('plan-crosswalk/*') ? 'active' : '' }}">
            <a href="{{ url('plan-crosswalk') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-map-2"></i>
                <div data-i18n="Plan Crosswalk">Plan Crosswalk</div>
            </a>
        </li>
        @endif

        {{-- Profile — always visible --}}
        <li class="menu-item {{ Request::is('profile') ? 'active' : '' }}">
            <a href="{{ url('profile') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-user"></i>
                <div data-i18n="Profile">Profile</div>
            </a>
        </li>

        {{-- Organization section — only shown when user belongs to an org --}}
        @if ($_sOrgId)
        <li class="menu-item {{ Request::is('org-admin*') ? 'active open' : '' }} has-sub">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons ti ti-building"></i>
                <div>Organization</div>
            </a>
            <ul class="menu-sub">

                {{-- Overview — user_management:R (shows org-wide team data) --}}
                @if ($_sc('user_management', 'R'))
                <li class="menu-item {{ Request::is('org-admin/overview') ? 'active' : '' }}">
                    <a href="{{ route('org-admin.overview') }}" class="menu-link">
                        <div>Overview</div>
                    </a>
                </li>
                @endif

                {{-- My Roles — any org member --}}
                <li class="menu-item {{ Request::is('org-admin/my-roles') ? 'active' : '' }}">
                    <a href="{{ route('org-admin.my-roles') }}" class="menu-link">
                        <div>My Roles</div>
                    </a>
                </li>

                {{-- Delegations — delegation_and_impersonation:R --}}
                @if ($_sc('delegation_and_impersonation', 'R'))
                <li class="menu-item {{ Request::is('org-admin/delegations*') ? 'active' : '' }}">
                    <a href="{{ route('org-admin.delegations.index') }}" class="menu-link">
                        <div>Delegations</div>
                    </a>
                </li>
                @endif

                {{-- Team — user_management:R (read) to see, actions inside are further gated --}}
                @if ($_sc('user_management', 'R'))
                <li class="menu-item {{ Request::routeIs('org-admin.index') ? 'active' : '' }}">
                    <a href="{{ route('org-admin.index') }}" class="menu-link">
                        <div>Team</div>
                    </a>
                </li>
                @endif

                {{-- Audit Log — user_management:F (owners/admins only) --}}
                @if ($_sc('user_management', 'F'))
                <li class="menu-item {{ Request::is('org-admin/audit-log') ? 'active' : '' }}">
                    <a href="{{ route('org-admin.audit-log') }}" class="menu-link">
                        <div>Audit Log</div>
                    </a>
                </li>
                @endif

                {{-- Settings — user_management:F (owners/admins only) --}}
                @if ($_sc('user_management', 'F'))
                <li class="menu-item {{ Request::is('org-admin/settings') ? 'active' : '' }}">
                    <a href="{{ route('org-admin.settings') }}" class="menu-link">
                        <div>Settings</div>
                    </a>
                </li>
                @endif

            </ul>
        </li>
        @endif

    </ul>
</aside>

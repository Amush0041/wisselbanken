<nav class="layout-navbar navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
    id="layout-navbar" style="min-height:56px">

    {{-- Mobile sidebar toggle --}}
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0" href="javascript:void(0)">
            <i class="ti ti-menu-2 ti-md"></i>
        </a>
    </div>

    {{-- Left: greeting --}}
    <div class="d-none d-xl-flex align-items-center gap-2">
        <span class="text-muted small">Welcome back,</span>
        <span class="fw-semibold small">{{ Auth::user()->name }}</span>
    </div>

    {{-- Right: org switcher + user dropdown --}}
    <ul class="navbar-nav flex-row align-items-center ms-auto gap-2">

        {{-- Org Switcher --}}
        @if (!empty($navUserOrgs) && $navUserOrgs->count() > 1)
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 px-2 py-1 rounded"
               href="javascript:void(0);" data-bs-toggle="dropdown"
               style="background:rgba(107,28,28,.07);max-width:220px">
                <span class="d-flex align-items-center justify-content-center rounded flex-shrink-0"
                      style="width:24px;height:24px;background:#6b1c1c">
                    <i class="ti ti-building" style="font-size:.75rem;color:#fff"></i>
                </span>
                <span class="text-truncate fw-medium" style="font-size:.82rem;color:#1a1a2e;max-width:130px">
                    {{ $navCurrentOrg?->name ?? 'Select Org' }}
                </span>
                <i class="ti ti-chevron-down flex-shrink-0" style="font-size:.75rem;color:#6b1c1c"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-1" style="min-width:240px">
                <li>
                    <p class="dropdown-header text-uppercase fw-semibold mb-0" style="font-size:.68rem;letter-spacing:.05em;color:#8a8d93">
                        Your Organizations
                    </p>
                </li>
                @foreach ($navUserOrgs as $org)
                    <li>
                        @if ($org->id === ($navCurrentOrg?->id))
                            <span class="dropdown-item d-flex align-items-center gap-2 py-2" style="cursor:default">
                                <span class="d-flex align-items-center justify-content-center rounded flex-shrink-0"
                                      style="width:28px;height:28px;background:#6b1c1c">
                                    <i class="ti ti-building" style="font-size:.78rem;color:#fff"></i>
                                </span>
                                <div class="flex-grow-1" style="min-width:0">
                                    <div class="fw-semibold text-truncate" style="font-size:.82rem">{{ $org->name }}</div>
                                    <div style="font-size:.7rem;color:#8a8d93">{{ str_replace('_',' ', ucwords($org->org_type,'_')) }}</div>
                                </div>
                                <i class="ti ti-check text-success ms-1 flex-shrink-0" style="font-size:.85rem"></i>
                            </span>
                        @else
                            <form action="{{ route('org.switch') }}" method="POST" class="d-block">
                                @csrf
                                <input type="hidden" name="org_id" value="{{ $org->id }}">
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2 border-0 bg-transparent w-100 text-start">
                                    <span class="d-flex align-items-center justify-content-center rounded flex-shrink-0"
                                          style="width:28px;height:28px;background:rgba(107,28,28,.1)">
                                        <i class="ti ti-building" style="font-size:.78rem;color:#6b1c1c"></i>
                                    </span>
                                    <div class="flex-grow-1" style="min-width:0">
                                        <div class="fw-medium text-truncate" style="font-size:.82rem">{{ $org->name }}</div>
                                        <div style="font-size:.7rem;color:#8a8d93">{{ str_replace('_',' ', ucwords($org->org_type,'_')) }}</div>
                                    </div>
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('org-admin.settings') }}">
                        <i class="ti ti-settings" style="font-size:.9rem;color:#6b1c1c"></i>
                        <span style="font-size:.82rem">Org Settings</span>
                    </a>
                </li>
            </ul>
        </li>
        @endif

        {{-- User dropdown --}}
        <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center gap-2 p-0"
               href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="d-none d-md-flex flex-column text-end me-1">
                    <span class="fw-semibold small lh-1">{{ Auth::user()->name }}</span>
                    <small class="text-muted" style="font-size:.7rem">{{ ucfirst(Auth::user()->role) }}</small>
                </div>
                <div class="avatar avatar-sm avatar-online">
                    <span class="avatar-initial rounded-circle"
                          style="background:#6b1c1c;color:#fff;font-size:.75rem;font-weight:700">
                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                    </span>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end mt-1 shadow-sm" style="min-width:200px">
                <li>
                    <div class="dropdown-item pe-none py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                  style="background:#6b1c1c;color:#fff;font-weight:700;font-size:.75rem;width:36px;height:36px">
                                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                            </span>
                            <div>
                                <div class="fw-semibold small">{{ Auth::user()->name }}</div>
                                <small class="text-muted">{{ Auth::user()->email }}</small>
                            </div>
                        </div>
                    </div>
                </li>
                <li><div class="dropdown-divider my-1"></div></li>
                <li>
                    <a class="dropdown-item" href="{{ url('user-dashboard') }}">
                        <i class="ti ti-layout-dashboard me-2 ti-sm"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('org-admin.index') }}">
                        <i class="ti ti-building me-2 ti-sm"></i> My Organization
                    </a>
                </li>
                <li><div class="dropdown-divider my-1"></div></li>
                <li>
                    <div class="px-2 pb-1">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="ti ti-logout ti-sm"></i> Logout
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </li>

    </ul>
</nav>

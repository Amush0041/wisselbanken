<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
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

    {{-- Right: user dropdown --}}
    <ul class="navbar-nav flex-row align-items-center ms-auto">
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
                            <span class="avatar avatar-sm rounded-circle d-inline-flex align-items-center justify-content-center"
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
                    <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                        <i class="ti ti-layout-dashboard me-2 ti-sm"></i> Dashboard
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

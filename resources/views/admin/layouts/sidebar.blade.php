<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{url('/')}}">
            <img src="{{asset('logo.png')}}" style="width:200px;">
        </a>

       
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <!-- Dashboards -->
        <li class="menu-item {{Request::is('admin/dashboard') ? 'active' : ''}}">
            <a href="{{url('admin/dashboard')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-smart-home"></i>
                <div data-i18n="Dashboards">Dashboards</div>
            </a>
        </li>

        <!-- RBAC -->
        <li class="menu-item {{Request::is('admin/rbac') || Request::is('admin/rbac/*') ? 'active open' : ''}}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons ti ti-shield-lock"></i>
                <div data-i18n="RBAC">RBAC</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{Request::is('admin/rbac') ? 'active' : ''}}">
                    <a href="{{route('admin.rbac.index')}}" class="menu-link"><div>Overview</div></a>
                </li>
                <li class="menu-item {{Request::is('admin/rbac/roles') ? 'active' : ''}}">
                    <a href="{{route('admin.rbac.roles')}}" class="menu-link"><div>Role Designer</div></a>
                </li>
                <li class="menu-item {{Request::is('admin/rbac/organizations*') ? 'active' : ''}}">
                    <a href="{{route('admin.rbac.organizations')}}" class="menu-link"><div>Organizations</div></a>
                </li>
                <li class="menu-item {{Request::is('admin/rbac/audit-logs') ? 'active' : ''}}">
                    <a href="{{route('admin.rbac.audit-logs')}}" class="menu-link"><div>Audit Logs</div></a>
                </li>
                <li class="menu-item {{Request::is('admin/rbac/enforcement') ? 'active' : ''}}">
                    <a href="{{route('admin.rbac.enforcement')}}" class="menu-link"><div>Enforcement</div></a>
                </li>
            </ul>
        </li>

        <!-- Division -->
        <li class="menu-item {{Request::is('admin/divisions') ? 'active' : ''}}">
            <a href="{{url('admin/divisions')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-components"></i>
                <div data-i18n="Division">Division</div>
            </a>
        </li>

        <!-- Specifications -->
        <li class="menu-item {{Request::is('admin/specifications') ? 'active' : ''}}">
            <a href="{{url('admin/specifications')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-category-plus"></i>
                <div data-i18n="Specifications">Specifications</div>
            </a>
        </li>
 
        <!-- Manufacturer -->
        <li class="menu-item {{Request::is('admin/manufacturers') ? 'active' : ''}}">
            <a href="{{url('admin/manufacturers')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-components"></i>
                <div data-i18n="Manufacturers">Manufacturers</div>
            </a>
        </li>
        <!-- Products -->
        <li class="menu-item {{Request::is('admin/products') || Request::is('admin/products/create') || Request::is('admin/product-files/*') || Request::is('admin/products/*/edit') ? 'active' : ''}}">
            <a href="{{url('admin/products')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-shopping-cart"></i>
                <div data-i18n="Products">Products</div>
            </a>
        </li>
        <li class="menu-item {{Request::is('admin/orders') || Request::is('admin/orders/*') ? 'active' : ''}}">
            <a href="{{ route('admin.orders.index') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-shopping-cart"></i>
                <div data-i18n="Orders">Orders</div>
            </a>
        </li>
        <!-- Blog -->
        <li class="menu-item {{Request::is('admin/blogs') || Request::is('admin/blogs/*') ? 'active' : ''}}">
            <a href="{{ route('admin.blogs.index') }}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-news"></i>
                <div data-i18n="Blog">Blog</div>
            </a>
        </li> 
        <!-- Products -->
        <!-- <li class="menu-item {{Request::is('admin/product-pricing') ? 'active' : ''}}">
            <a href="{{url('admin/product-pricing')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-currency-dollar"></i>
                <div data-i18n="Product Pricing">Product Pricing</div>
            </a>
        </li> -->
        <li class="menu-header small">
            <span class="menu-header-text" data-i18n="Product Meta">Product Meta</span>
        </li>
        
        <!-- File Import -->
        <li class="menu-item">
            <a href="javascript:void(0)" class="menu-link" id="importSizeBtn" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light float-end" data-bs-toggle="offcanvas" data-bs-target="#offcanvasImportSize">
                <i class="menu-icon tf-icons ti ti-file-import"></i>
                <div data-i18n="File Import">File Import</div>
            </a>
        </li>
        <!-- Size -->
        <li class="menu-item {{Request::is('admin/sizes') ? 'active' : ''}}">
            <a href="{{url('admin/sizes')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-layout-kanban"></i>
                <div data-i18n="Sizes">Sizes</div>
            </a>
        </li>
        <!-- Thickness -->
        <li class="menu-item {{Request::is('admin/thicknesses') ? 'active' : ''}}">
            <a href="{{url('admin/thicknesses')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-spaces"></i>
                <div data-i18n="Thicknesses">Thicknesses</div>
            </a>
        </li>

        <!-- Finish -->
        <li class="menu-item {{Request::is('admin/finishes') ? 'active' : ''}}">
            <a href="{{url('admin/finishes')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-paint-off"></i>
                <div data-i18n="Finishes">Finishes</div>
            </a>
        </li>
        <!-- PaintType -->
        <li class="menu-item {{Request::is('admin/paint-types') ? 'active' : ''}}">
            <a href="{{url('admin/paint-types')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-paint"></i>
                <div data-i18n="Paint Types">Paint Type</div>
            </a>
        </li>
            
        <!-- Colors -->
        <li class="menu-item {{Request::is('admin/colors') ? 'active' : ''}}">
            <a href="{{url('admin/colors')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-brand-drupal"></i>
                <div data-i18n="Colors">Colors</div>
            </a>
        </li>
        <!-- Color Effects -->
        <li class="menu-item {{Request::is('admin/color-effects') ? 'active' : ''}}">
            <a href="{{url('admin/color-effects')}}" class="menu-link">
                <i class="menu-icon tf-icons ti ti-color-filter"></i>
                <div data-i18n="Color Effects">Color Effects</div>
            </a>
        </li>
        
    </ul>
</aside>
 
<header class="header-area header-style-4 header-height-2">
    <div class="header-top header-top-ptb-1 d-none d-lg-block">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-xl-6 col-lg-6">
                    <div class="header-info">
                        <ul>
                            <li><i class="fi-rs-smartphone"></i> <a href="tel:6513929405"> 651-392-9405</a></li>
                            <li><i class="fi-rs-envelope"></i><a href="mailto:mitch@wisselbanken.com">Mitch@wisselbanken.com</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-6">
                    <div class="header-info header-info-right">
                        <ul>
                            @if (Route::has('login'))
                            @auth
                            <li><i class="fi-rs-user"></i><a href="{{ Auth::user()->role === 'user' ? url('user-dashboard') : url('admin/dashboard') }}">Dashboard</a></li>
                            @else
                            <li>
                                <i class="fi-rs-user"></i>
                                <a href="{{url('login')}}">Log In
                                    @if (Route::has('register')) / Sign Up
                                    @endif
                                </a>
                            </li>

                            @endauth
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="header-middle header-middle-ptb-1 d-none d-lg-block">
        <div class="container-fluid  px-4">
            <div class="header-wrap">
                <div class="logo logo-width-1">
                    <a href="{{url('/')}}"><img src="{{asset('logo.png')}}" alt="{{env('APP_NAME','Wisselbanken')}}"></a>
                </div>
                <div class="header-right">
                    <div class="search-style-2">
                        <form action="#">
                            <select class="select-active">
                                <option>Browse Division</option>
                                @foreach(\App\Helper\Helper::getDivisions() as $division)
                                <option value="{{ $division->slug}}">{{$division->code}} - {{$division->name}}</option>
                                @endforeach
                            </select>
                            <input type="text" placeholder="Search for items...">
                        </form>
                    </div>
                    <div class="header-action-right">
                        <div class="header-action-2">
                            <div class="header-action-icon-2">
                                <a href="{{url('view-lists')}}" title="View lists">
                                    <img class="svgInject" alt="View lists" src="{{asset('frontend/icon-list.png')}}">
                                    <span class="pro-count blue lists-count">{{App\Helper\Helper::getListsCount() ?? 0}}</span>
                                </a>
                            </div>
                            <div class="header-action-icon-2">
                                <a class="mini-cart-icon" href="{{url('pallet')}}" title="View pallet">
                                    <img alt="View Pallet" src="{{asset('frontend/icon-pallet.png')}}">
                                    <span class="pro-count blue custom-pallet">{{App\Helper\Helper::getPalletCount() ?: 0}}</span>
                                </a>
                                <div class="cart-dropdown-wrap cart-dropdown-hm2 update_pallet_preview">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="header-bottom header-bottom-bg-color sticky-bar">
        <div class="container-fluid  px-4">
            <div class="header-wrap header-space-between position-relative">
                <div class="logo logo-width-1 d-block d-lg-none">
                    <a href="{{url('/')}}"><img src="{{asset('logo.png')}}" alt="logo"></a>
                </div>
                <div class="header-nav d-none d-lg-flex">
                    <div class="main-categori-wrap d-none d-lg-block">
                        <a class="categori-button-active" disabled="{{ Request::is('/') ? true : false }}" href="#">
                            <span class="fi-rs-apps"></span> Browse Divisions
                        </a>
                        <div class="categori-dropdown-wrap categori-dropdown-active-large">
                            <ul>
                                @foreach (\App\Helper\Helper::getDivisions() as $division)
                                <li class="nav-item dropdown position-relative">
                                    <h3>
                                        <a href="{{ route('product-division', $division->slug) }}"
                                            class="nav-link text-dark d-flex align-items-center division-link parent-link">
                                            {{$division->code}} - {{$division->name}}
                                        </a>
                                    </h3>
                                    @if(count($division->specifications) > 0)
                                    <ul class="dropdown-menu position-absolute start-100 top-0 mt-0 shadow"
                                        style="min-width: 250px;">
                                        @foreach($division->specifications as $specification)
                                        <li>
                                            <a class="dropdown-item child-link" href="{{ url('product-manufacturer?filter='.$division->code. '-' . $specification->specification_number) }}">
                                                {{ $specification->specification_number }} - {{ $specification->material_type }}
                                            </a>
                                        </li>
                                        @endforeach
                                    </ul>
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="main-menu main-menu-padding-1 main-menu-lh-2 d-none d-lg-block">
                        <nav>
                            <ul>
                                <li><a class="{{Request::is('/') ? 'active' : ''}}" href="{{url('/')}}">Home </a>
                                </li>
                                <li><a href="javascript:void(0)" class="{{ Request::is('product-manufacturer/*') ? 'active' : ''}}">Manufacturers <i class="fi-rs-angle-down"></i></a>
                                    <ul class="sub-menu">

                                        @foreach(\App\Helper\Helper::getManufacturer() as $manufacturer)
                                        <li><a class="{{Request::is('product-manufacturer/*') ? 'active' : ''}}" href="{{url('product-manufacturer',$manufacturer->slug)}}">{{ucfirst($manufacturer->name)}}</a></li>
                                        @endforeach
                                    </ul>
                                </li>
                                <li><a href="javascript:void(0)" class="{{  Request::is('service/submital-builder-service') ||  Request::is('service/turnkey-construction-service') ||  Request::is('service/shop-drawing-service') || Request::is('service/material-quote-service') || Request::is('service/take-off-estimating-services') ? 'active' : ''}}">Services <i class="fi-rs-angle-down"></i></a>
                                    <ul class="sub-menu">
                                        <li><a class="{{Request::is('service/take-off-estimating-services') ? 'active' : ''}}" href="{{route('estimating-service')}}">Take off & Estimating Services</a></li>
                                        <li><a class="{{Request::is('service/material-quote-service') ? 'active' : ''}}" href="{{route('material-quote-service')}}">Material Quotes</a></li>
                                        <li><a class="{{Request::is('service/turnkey-construction-service') ? 'active' : ''}}" href="{{route('turnkey-construction-service')}}">Turnkey Construction Partnerships</a></li>
                                        <li><a class="{{Request::is('service/shop-drawing-service') ? 'active' : ''}}" href="{{route('shop-drawing-service')}}">Shop Drawings / Plan Review / Design</a></li>
                                        <li><a class="{{Request::is('service/submital-builder-service') ? 'active' : ''}}" href="{{route('submital-builder-service')}}">Submittal Builder</a></li>
                                    </ul>
                                </li>
                                <li>
                                    <a class="{{Request::is('product-filter') || Request::is('product-detail/*') ? 'active' : ''}}" href="{{url('product-filter')}}">Request a quote</a>
                                </li>
                                <li>
                                    <a class="{{Request::is('blogs*') ? 'active' : ''}}" href="{{route('blogs.index')}}">Blogs</a>
                                </li>
                                <li>
                                    <a class="{{Request::is('contacts') ? 'active' : ''}}" href="{{url('contacts')}}">Contact</a>
                                </li>
                                @if (Route::has('login'))
                                @auth
                                @else

                                <li>
                                    <a class="{{Request::is('login') ? 'active' : ''}}" href="{{url('login')}}">Login</a>
                                </li>
                                @if (Route::has('register'))

                                <li>
                                    <a class="{{Request::is('register') ? 'active' : ''}}" href="{{url('register')}}">Register</a>
                                </li>
                                @endif
                                @endauth
                                @endif

                                @if(Auth::check())
                                @if(Auth::user()->email_verified_at)
                                <li>
                                    <a class="{{ (Auth::check() && Auth::user()->role === 'user') ? (Request::is('user-dashboard') ? 'active' : '') : (Request::is('admin/dashboard') ? 'active' : '') }}"
                                        href="{{ Auth::check() && Auth::user()->role === 'user' ? url('user-dashboard') : url('admin/dashboard') }}">
                                        Dashboard
                                    </a>
                                </li>
                                @else
                                <li>
                                    <a href="{{ route('logout') }}"
                                        onclick="event.preventDefault(); document.getElementById('logout-form-navbar').submit();">
                                        Logout
                                    </a>
                                    <form id="logout-form-navbar" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                                @endif
                                @endif
                            </ul>
                        </nav>
                    </div>
                </div>
                @if(Auth::check() && Auth::user()->role == 'user')
                <div class="d-none d-lg-block">
                    <a class="d-flex align-items-center text-dark" href="{{ route('workspace') }}">
                        <img src="{{ Auth::user()->profile_image ? asset('storage/' . Auth::user()->profile_image) : asset('default-avatar.jpg') }}" alt="Profile" class="rounded-circle" width="40" height="40">
                        <span class="ms-2">{{ Auth::user()->name }}</span>
                        <span class="ms-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 5.646a.5.5 0 0 1 .708 0L8 11.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg></span>
                    </a>
                </div>
                @endif

                <div class="header-action-right d-block d-lg-none">
                    <div class="header-action-2">
                        <div class="header-action-icon-2">
                            <a href="shop-wishlist.html">
                                <img href="{{url('view-lists')}}" title="View lists" src="{{asset('frontend/icon-list.png')}}">
                                                                    <span class="pro-count white lists-count">{{App\Helper\Helper::getListsCount() ?? 0}}</span>
                            </a>
                        </div>
                        <div class="header-action-icon-2">
                            <a class="mini-cart-icon" href="{{url('pallet')}}">
                                <img alt="View pallet" src="{{asset('frontend/icon-pallet.png')}}">
                                                                    <span class="pro-count white custom-pallet">{{App\Helper\Helper::getPalletCount() ?? 0}}</span>
                            </a>
                            <div class="cart-dropdown-wrap cart-dropdown-hm2 update_pallet_preview">

                            </div>
                        </div>
                        <div class="header-action-icon-2 d-block d-lg-none">
                            <div class="burger-icon burger-icon-white">
                                <span class="burger-icon-top"></span>
                                <span class="burger-icon-mid"></span>
                                <span class="burger-icon-bottom"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="mobile-header-active mobile-header-wrapper-style">
    <div class="mobile-header-wrapper-inner">
        <div class="mobile-header-top">
            <div class="mobile-header-logo">
                <a href="{{url('/')}}"><img src="{{asset('logo.png')}}" alt="{{env('APP_NAME','Wisselbanken')}}"></a>
            </div>
            <div class="mobile-menu-close close-style-wrap close-style-position-inherit">
                <button class="close-style search-close">
                    <i class="icon-top"></i>
                    <i class="icon-bottom"></i>
                </button>
            </div>
        </div>
        <div class="mobile-header-content-area">
            @if(Auth::check())
            <a href="{{ Auth::user()->role == 'user' ? route('workspace') : url('admin/dashboard') }}" class="mobile-user-profile d-flex align-items-center mb-3 text-dark" style="gap: 10px; text-decoration: none;">
                <img src="{{ Auth::user()->profile_image ? asset('storage/' . Auth::user()->profile_image) : asset('default-avatar.jpg') }}" alt="Profile" class="rounded-circle" width="40" height="40">
                <span class="fw-bold">{{ Auth::user()->name }}</span>
            </a>
            @endif
            <div class="mobile-search search-style-3 mobile-header-border">
                <form action="#">
                    <input type="text" placeholder="Search for items…">
                    <button type="submit"><i class="fi-rs-search"></i></button>
                </form>
            </div>
            <div class="mobile-menu-wrap mobile-header-border">
                <div class="main-categori-wrap mobile-header-border">
                    <a class="categori-button-active-2" href="#">
                        <span class="fi-rs-apps"></span> Browse Divisions
                    </a>
                    <div class="categori-dropdown-wrap categori-dropdown-active-small">
                        @foreach (\App\Helper\Helper::getDivisions() as $division)
                        <li>
                            <a href="{{ route('product-division', $division->slug) }}">
                                {{$division->code}} - {{$division->name}}
                            </a>
                        </li>
                        @endforeach
                    </div>
                </div>
                <!-- mobile menu start -->
                <nav>
                    <ul class="mobile-menu">
                        <li class="menu-item"><a class="{{Request::is('/') ? 'active' : ''}}" href="{{url('/')}}">Home</a>
                        <li class="menu-item-has-children"><span class="menu-expand"></span><a href="javascript:void(0)" class="{{ Request::is('product-manufacturer/*') ? 'active' : ''}}">Manufacturers</a>
                            <ul class="dropdown">
                                @foreach(\App\Helper\Helper::getManufacturer() as $manufacturer)
                                <li><a class="{{Request::is('product-manufacturer/*') ? 'active' : ''}}" href="{{url('product-manufacturer',$manufacturer->slug)}}">{{$manufacturer->name}}</a></li>
                                @endforeach
                            </ul>
                        </li>
                        <li class="menu-item-has-children"><span class="menu-expand"></span><a href="javascript:void(0)" class="{{Request::is('service/estimating-service') || Request::is('service/material-quote-service') || Request::is('turnkey-construction-service') || Request::is('service/shop-drawing-service') || Request::is('service/submital-builder-service') ? 'active' : ''}}">Services</a>
                            <ul class="dropdown">
                                <li><a class="{{Request::is('service/estimating-service') ? 'active' : ''}}" href="{{route('estimating-service')}}">Take off & Estimating Services</a></li>
                                <li><a class="{{Request::is('service/material-quote-service') ? 'active' : ''}}" href="{{route('material-quote-service')}}">Material Quotes</a></li>
                                <li><a class="{{Request::is('service/turnkey-construction-service') ? 'active' : ''}}" href="{{route('turnkey-construction-service')}}">Turnkey Construction Partnerships</a></li>
                                <li><a class="{{Request::is('service/shop-drawing-service') ? 'active' : ''}}" href="{{route('shop-drawing-service')}}">Shop Drawings / Plan Review / Design</a></li>
                                <li><a class="{{Request::is('service/submital-builder-service') ? 'active' : ''}}" href="{{route('submital-builder-service')}}">Submittal Builder</a></li>
                            </ul>
                        </li>
                        <li class="menu-item">
                            <a class="{{Request::is('product-filter') ? 'active' : ''}}" href="{{url('product-filter')}}">Request a quote</a>
                        </li>
                        <li class="menu-item">
                            <a class="{{Request::is('blogs*') ? 'active' : ''}}" href="{{route('blogs.index')}}">Blogs</a>
                        </li>
                        <li class="menu-item">
                            <a class="{{Request::is('contacts') ? 'active' : ''}}" href="{{url('contacts')}}">Contact</a>
                        </li>
                        @if(Auth::check())
                        @if(Auth::user()->role == 'user')
                        <li class="menu-item"><a href="{{ route('workspace') }}" class="{{ Request::is('workspace') ? 'active' : '' }}">{{ Auth::user()->name }}</a></li>
                        @else
                        <li class="menu-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                        @endif
                        @else
                        <li class="menu-item"><a href="{{url('login')}}">Login</a></li>
                        @if (Route::has('register'))
                        <li class="menu-item"><a href="{{url('register')}}">Register</a></li>
                        @endif
                        @endif
                    </ul>
                </nav>
                <!-- mobile menu end -->
            </div>
            <div class="mobile-header-info-wrap mobile-header-border">
                <div class="single-mobile-header-info">
                    @if (Route::has('login'))
                    @auth
                    @if(Auth::user()->email_verified_at)
                    @if(Auth::user()->role == 'user')
                    <a href="{{ route('workspace') }}">My Workspace</a>
                    @endif
                    <a href="{{ Auth::check() && Auth::user()->role === 'user' ? url('user-dashboard') : url('admin/dashboard') }}">
                        Dashboard
                    </a>
                    @else
                    <a class="{{Request::is('logout') ? 'active' : ''}}" href="{{ route('logout') }}"
                        onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">Logout</a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                    @endif
                    @else
                    <a href="{{url('login')}}">Log In
                        @if (Route::has('register')) / Sign Up
                        @endif</a>

                    @endauth
                    @endif
                </div>
                <div class="single-mobile-header-info">
                    <a href="tel:6513929405">651-392-9405 </a>
                </div>

            </div>
            <div class="mobile-social-icon">
                <h5 class="mb-15 text-grey-4">Follow Us</h5>
                <a href="https://www.facebook.com/wisselbanken/"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-facebook.svg')}}" alt=""></a>
                <a href="https://www.instagram.com/wisselbanken/"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-instagram.svg')}}" alt=""></a>
            </div>
        </div>
    </div>
</div>

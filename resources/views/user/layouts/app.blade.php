<!doctype html>

<html
    lang="en"
    class="light-style layout-navbar-fixed layout-menu-fixed layout-compact"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="{{asset('backend/assets/')}}/"
    data-template="vertical-menu-template"
    data-style="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    @yield('seo')

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{asset('fav.png')}}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/fonts/fontawesome.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/fonts/tabler-icons.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/fonts/flag-icons.css')}}" />

    <!-- Core CSS -->

    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/rtl/core.css')}}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/rtl/theme-default.css')}}" class="template-customizer-theme-css" />

    <link rel="stylesheet" href="{{asset('backend/assets/css/demo.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/css/custom.css')}}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/node-waves/node-waves.css')}}" />

    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/typeahead-js/typeahead.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/apex-charts/apex-charts.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/swiper/swiper.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/pages/cards-advance.css')}}" />

    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/animate-css/animate.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />


    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/select2/select2.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/tagify/tagify.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/bootstrap-select/bootstrap-select.css')}}" />


    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/quill/typography.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/quill/katex.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/quill/editor.css')}}" />
    <!-- <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/dropzone/dropzone.css')}}" /> -->
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/flatpickr/flatpickr.css')}}" />

    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/toastr/toastr.css')}}" /> 
    @stack('css')
    <!-- Helpers -->
    <script src="{{asset('backend/assets/vendor/js/helpers.js')}}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->

    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <!-- <script src="{{asset('backend/assets/vendor/js/template-customizer.js')}}"></script> -->

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{asset('backend/assets/js/config.js')}}"></script>

</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            @include('user.layouts.sidebar')
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                @include('user.layouts.navbar')

                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    @yield('content')
                    <!-- / Content -->

                    <!-- Footer -->
                    @include('user.layouts.footer')
                    <!-- / Footer -->
                    <!-- Offcanvas Form for Import Size -->
                    
                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>

        <!-- Drag Target Area To SlideIn Menu On Small Screens -->
        <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="{{asset('backend/assets/vendor/libs/jquery/jquery.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/popper/popper.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/js/bootstrap.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/node-waves/node-waves.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/hammer/hammer.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/i18n/i18n.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/typeahead-js/typeahead.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/js/menu.js')}}"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{asset('backend/assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/swiper/swiper.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>

    <script src="{{asset('backend/assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>

    <script src="{{asset('backend/assets/js/extended-ui-sweetalert2.js')}}"></script>
    <!-- Main JS -->
    <script src="{{asset('backend/assets/js/main.js')}}"></script>


    <!-- Vendors JS -->
    <script src="{{asset('backend/assets/vendor/libs/select2/select2.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/tagify/tagify.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/bootstrap-select/bootstrap-select.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/bloodhound/bloodhound.js')}}"></script>


    <!-- Page JS -->
    <script src="{{asset('backend/assets/js/dashboards-analytics.js')}}"></script>
     

    <!-- Vendors JS -->
    <script src="{{asset('backend/assets/vendor/libs/quill/katex.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/quill/quill.js')}}"></script> 
    <!-- <script src="{{asset('backend/assets/vendor/libs/dropzone/dropzone.js')}}"></script> -->
    <script src="{{asset('backend/assets/vendor/libs/jquery-repeater/jquery-repeater.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/flatpickr/flatpickr.js')}}"></script> 
   
  
    <!-- Vendors JS -->
    <script src="{{asset('backend/assets/vendor/libs/toastr/toastr.js')}}"></script>
 

    @stack('scripts')

<script>
(function () {
    // ── RBAC denial popup ────────────────────────────────────────────────────
    // Server-side flash (page navigation blocked, redirect back with flash).
    @if (session('rbac_denied'))
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Access Restricted',
                text: @json(session('rbac_denied')),
                confirmButtonText: 'OK',
                confirmButtonColor: '#6b1c1c',
                customClass: { popup: 'rbac-denied-popup' }
            });
        }
    });
    @endif

    // AJAX requests blocked (middleware returns JSON with rbac_error: true).
    if (typeof $ !== 'undefined') {
        $(document).ajaxError(function (event, xhr) {
            if (xhr.status !== 403) return;
            try {
                var data = JSON.parse(xhr.responseText);
                if (!data.rbac_error) return;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Access Restricted',
                        text: data.message || "You don’t have permission to perform this action.",
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#6b1c1c',
                        customClass: { popup: 'rbac-denied-popup' }
                    });
                }
            } catch (e) {}
        });
    }
})();
</script>

@auth
@if (!Auth::user()->hasVerifiedEmail())
<div class="modal fade" id="verifyEmailModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content border-0" style="border-radius:14px;overflow:hidden">
            <div style="height:4px;background:#6b1c1c"></div>
            <div class="modal-body p-4 p-md-5 text-center">
                <div style="width:64px;height:64px;border-radius:50%;background:rgba(107,28,28,.09);display:flex;align-items:center;justify-content:center;margin:0 auto 18px">
                    <i class="ti ti-mail-opened" style="font-size:1.7rem;color:#6b1c1c"></i>
                </div>
                <h5 class="fw-bold mb-1" style="color:#1a1a1a">Verify your email address</h5>
                <p class="text-muted mb-4" style="font-size:.875rem;line-height:1.6">
                    We sent a verification link to <strong>{{ Auth::user()->email }}</strong>.<br>
                    Click it to activate your account.
                </p>
                @if (session('resent'))
                <div class="alert alert-success py-2 mb-3" style="font-size:.84rem">New link sent — check your inbox.</div>
                @endif
                <form method="POST" action="{{ route('verification.resend') }}">
                    @csrf
                    <button type="submit" class="btn w-100 fw-semibold text-white mb-3" style="background:#6b1c1c;border-radius:8px;padding:11px">
                        Resend verification email
                    </button>
                </form>
                <div class="d-flex justify-content-center gap-3" style="font-size:.84rem">
                    <a href="{{ url('/') }}" style="color:#6b1c1c;text-decoration:none"><i class="ti ti-home me-1"></i>Homepage</a>
                    <span class="text-muted">·</span>
                    <a href="{{ route('login') }}" style="color:#6b1c1c;text-decoration:none"><i class="ti ti-login me-1"></i>Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('verifyEmailModal');
        if (el && typeof bootstrap !== 'undefined') new bootstrap.Modal(el).show();
    });
</script>
@endif
@endauth

</body>

</html>
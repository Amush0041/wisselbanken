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
    <link rel="icon" type="image/png" href="{{ asset('fav.png') }}" />
    <link rel="shortcut icon" type="image/png" href="{{ asset('fav.png') }}" />

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
            @include('admin.layouts.sidebar')
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                @include('admin.layouts.navbar')

                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    @yield('content')
                    <!-- / Content -->

                    <!-- Footer -->
                    @include('admin.layouts.footer')
                    <!-- / Footer -->
                    <!-- Offcanvas Form for Import Size -->
                    <div class="offcanvas offcanvas-end" id="offcanvasImportSize">
                        <div class="offcanvas-header py-6">
                            <h5 class="offcanvas-title">Import Sizes</h5>
                            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
                        </div>
                        <div class="offcanvas-body border-top">
                            @if(session('error'))
                            <div class="alert alert-danger mb-3" role="alert">
                                <div class="alert-body">
                                    <strong>{{ session('error') }}</strong>
                                    @if(session('error_details'))
                                    <ul class="mt-2 mb-0" style="padding-left: 20px;">
                                        @foreach(session('error_details') as $error)
                                        <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    @endif
                                </div>
                            </div>
                            @endif

                            @if(session('success'))
                            <div class="alert alert-success mb-3" role="alert">
                                <div class="alert-body">
                                    {{ session('success') }}
                                </div>
                            </div>
                            @endif

                            <form action="{{ route('files.import') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label for="file" class="form-label">Choose File ( <a href="{{asset('files/sample_file.csv')}}" class="text-primary">click to see sample file</a>)</label>
                                    <input type="file" name="file" accept=".xlsx, .xls, .csv" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Import Size</button>
                            </form>
                        </div>
                    </div>
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
</body>

</html>
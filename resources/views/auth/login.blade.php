<!doctype html>
<html
  lang="en"
  class="light-style layout-wide customizer-hide"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="{{asset('backend/assets/')}}/"
  data-template="vertical-menu-template"
  data-style="light">

<head>
  <meta charset="utf-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Login | {{env('APP_NAME','Wisselbanken')}}</title>

  <meta name="description" content="" />

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

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/node-waves/node-waves.css')}}" />

  <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
  <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/typeahead-js/typeahead.css')}}" />
  <!-- Vendor -->
  <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/@form-validation/form-validation.css')}}" />

  <!-- Page CSS -->
  <!-- Page -->
  <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/pages/page-auth.css')}}" />

  <!-- Helpers -->
  <script src="{{asset('backend/assets/vendor/js/helpers.js')}}"></script>
  <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->

  <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
  <script src="{{asset('backend/assets/vendor/js/template-customizer.js')}}"></script>

  <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
  <script src="{{asset('backend/assets/js/config.js')}}"></script>

  <style>
    .app-brand img {
      max-width: 100%;
      height: auto;
      max-height: 60px;
      display: block;
      margin: 0 auto;
    }
    @media (max-width: 576px) {
      .app-brand img {
        max-height: 40px;
      }
    }
  </style>
</head>

<body>
  <!-- Content -->

  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner py-6">
        <!-- Login -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center mb-6">
              <a href="{{url('/')}}" class="app-brand-link">
                <img src="{{asset('logo.png')}}" alt="{{env('APP_NAME','Wisselbanken')}}" style="height: 50px;width:290px">
              </a>
            </div>
            <!-- /Logo -->

            @if (session('status'))
              <div class="alert alert-success mb-4" role="alert">
                <i class="ti ti-circle-check me-1"></i>{{ session('status') }}
              </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mb-4">
              @csrf
              <div class="mb-6">
                <label for="email" class="form-label">Email</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Enter your email or username" required autocomplete="email" autofocus />
                @error('email')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="mb-6 form-password-toggle">
                <label class="form-label" for="password">Password</label>
                <div class="input-group input-group-merge">
                  <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password"
                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                    aria-describedby="password">
                  <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
                </div>
                @error('password')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="my-8">
                <div class="d-flex justify-content-between">
                  <div class="form-check mb-0 ms-2">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }} />
                    <label class="form-check-label" for="remember"> Remember Me </label>
                  </div>

                  @if (Route::has('password.request'))
                  <a href="{{ route('password.request') }}">

                    <p class="mb-0">Forgot Password?</p>
                  </a>
                  @endif

                </div>
              </div>
              <div class="mb-6">
                <button class="btn btn-primary d-grid w-100" type="submit">Login</button>
              </div>
            </form>

            <p class="text-center">
              <span>New on our platform?</span>
              <a href="{{url('register')}}">
                <span>Create an account</span>
              </a>
            </p>

            
          </div>
        </div>
        <!-- /Register -->
      </div>
    </div>
  </div>

  <!-- / Content -->

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
  <script src="{{asset('backend/assets/vendor/libs/@form-validation/popular.js')}}"></script>
  <script src="{{asset('backend/assets/vendor/libs/@form-validation/bootstrap5.js')}}"></script>
  <script src="{{asset('backend/assets/vendor/libs/@form-validation/auto-focus.js')}}"></script>

  <!-- Main JS -->
  <script src="{{asset('backend/assets/js/main.js')}}"></script>

  <!-- Page JS -->
  <script src="{{asset('backend/assets/js/pages-auth.js')}}"></script>
</body>

</html>
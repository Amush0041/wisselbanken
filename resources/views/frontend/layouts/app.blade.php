<!DOCTYPE html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    @stack('seo')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:title" content="">
    <meta property="og:type" content="">
    <meta property="og:url" content="">
    <meta property="og:image" content="">
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="{{asset('fav.png')}}">
    <!-- Template CSS -->
    <link rel="stylesheet" href="{{asset('frontend/assets/css/main.css?v=3.4')}}">
    <style>
        .logo.logo-width-1 a img {
            width: 210px;
            min-width: 210px;
        }

        .logo.logo-width-1 {
            margin-right: 40px;
        }

        .bg-dark {
            background-color: #e3b143 !important;
            color: #4A171E !important;
        }

        .header-style-4 .select2-container {
            max-width: unset;
            min-width: 160px;
        }

        @media only screen and (max-width: 768px) {
            .custom .banner-img {
                height: 194px;
                width: 325px;
                background: #4a171e;
            }

            .logo.logo-width-1 a img {
                min-width: 170px;
            }


        }

        /* .custom-cart {
            font-size: 16px;
            margin-left: 8px;
            margin-top: 5px;
            font-weight: 800;

        } */

        /* .header-action-2 .header-action-icon-2>a {
            font-size: 30px;
            color: #333;
            line-height: 1;
            display: inline-block;
            position: relative;
            width: 112px;
            right: 0px;

        } */

        .bg-primary {
            background-color: #4A171E !important;
            color: white !important;
        }

        .text-primary {

            color: #4A171E !important;
        }

        .btn-check:active+.btn-primary,
        .btn-check:checked+.btn-primary,
        .btn-primary.active,
        .btn-primary:active,
        .show>.btn-primary.dropdown-toggle {

            background-color: #4A171E !important;
            color: white !important;
        }

        .text-default {
            color: #2b2929 !important;
        }

        .custom-check {
            width: 14px;
            height: 14px;
            margin-right: 5px;
            cursor: pointer;
        }

        .cart-dropdown-wrap.cart-dropdown-hm2 {
            right: 6px;
        }

        /* cart css  */
        .cart-dropdown-wrap {
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            z-index: 99;
            width: 350px;
            background-color: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease-in-out;
            border-radius: 6px;
            border: 1px solid #eef0ee;
        }

        .cart-dropdown-wrap ul {
            padding: 0;
            margin: 0;
            list-style: none;
            max-height: 300px;
            /* Limit height to enable scrolling */
            overflow-y: auto;
            /* Enable vertical scrolling only for items */
        }

        .cart-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-img img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
        }

        .cart-item-content {
            flex: 1;
        }

        .cart-item-title {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 5px;
        }

        .cart-item-details {
            font-size: 12px;
            color: #777;
        }

        .cart-item-price {
            font-size: 14px;
            font-weight: 700;
            color: #333;
        }

        .cart-item-delete {
            position: absolute;
            right: 10px;
            top: 10px;
        }

        .cart-item-delete a {
            color: #d9534f;
            font-size: 16px;
            cursor: pointer;
        }

        .cart-item-delete a:hover {
            color: #c9302c;
        }

        .shopping-cart-footer {
            text-align: center;
            background: #fff;
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            padding-bottom: 10px;
        }

        .shopping-cart-total {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .shopping-cart-buttons a {
            display: inline-block;
            padding: 8px 15px;
            font-size: 14px;
            text-decoration: none;
            border-radius: 4px;
            transition: 0.3s;
        }

        .btn-outline {
            color: #4a171e;
            border: 1px solid #4a171e;
        }

        .btn-outline:hover {
            background-color: #4a171e;
            color: #4a171e;
        }

        .btn-cart-primary {
            background: #4a171e;
            color: #fff;
            border: none;
        }

        .btn-cart-primary:hover {
            background: #e2b143;
            color: #fff;
        }

        .shadow-sm {
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px !important;
        }

        .page-header.breadcrumb-wrap {
            padding: 20px;
            background-color: #ffffff;
        }


        .filter-note {
            background-color: #fff4e5;
            border-left: 5px solid #ff9800;
            color: #d35400;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 5px;
            position: relative;
        }

        .filter-note button {
            position: absolute;
            top: 5px;
            right: 10px;
            font-size: 18px;
            border: none;
            background: none;
            cursor: pointer;
            color: #d35400;
        }

        #toast-container {
            position: fixed;
            top: 70px;
            /* Below navbar */
            right: 20px;
            z-index: 999999;
        }

        .toast {
            position: relative;
            overflow: hidden;
            margin: 0 0 10px;
            padding: 15px 35px 15px 15px;
            width: 300px;
            border-radius: 4px;
            color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            opacity: 0.9;
            display: flex;
            align-items: center;
        }

        .toast-close-button {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            color: #fff;
            background: transparent;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 0 5px;
            line-height: 1;
        }

        .toast-icon {
            margin-right: 10px;
            font-size: 20px;
            min-width: 20px;
            text-align: center;
        }

        .toast-message {
            flex: 1;
        }

        .toast-success {
            background-color: #51a351;
        }

        .toast-warning {
            background-color: #f89406;
        }

        .toast-error {
            background-color: #bd362f;
        }

        .toast-info {
            background-color: #2f96b4;
        }

        .dropdown-toggle::after {
            display: none !important;
        }

        @media only screen and (max-width: 768px) {
            .mobile-header-wrapper-style .mobile-header-wrapper-inner .mobile-header-top .mobile-header-logo a img {
                width: 290px;
            }
        }
    </style>
    @stack('css')
<style>
/* ── RBAC denial modal ──────────────────────────────────────────────────── */
#rbac-denied-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 99998;
    animation: rbacFadeIn .18s ease;
}
#rbac-denied-modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 99999;
    width: 92%;
    max-width: 420px;
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.28);
    animation: rbacSlideIn .22s ease;
}
#rbac-denied-modal .rbac-header {
    background: #4A171E;
    padding: 22px 24px 18px;
    text-align: center;
}
#rbac-denied-modal .rbac-header .rbac-icon {
    width: 52px;
    height: 52px;
    background: rgba(255,255,255,.12);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
#rbac-denied-modal .rbac-header .rbac-icon svg {
    width: 26px;
    height: 26px;
    fill: #e3b143;
}
#rbac-denied-modal .rbac-header h6 {
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    letter-spacing: .3px;
}
#rbac-denied-modal .rbac-body {
    padding: 20px 24px 24px;
    text-align: center;
}
#rbac-denied-modal .rbac-body p {
    color: #444;
    font-size: .875rem;
    line-height: 1.65;
    margin: 0 0 20px;
}
#rbac-denied-modal .rbac-body button {
    background: #4A171E;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 36px;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
#rbac-denied-modal .rbac-body button:hover { background: #6b1c1c; }
@keyframes rbacFadeIn  { from { opacity: 0 } to { opacity: 1 } }
@keyframes rbacSlideIn { from { opacity: 0; transform: translate(-50%,-46%) } to { opacity: 1; transform: translate(-50%,-50%) } }
</style>

</head>

<body>
    <!-- Quick view -->

    {{-- RBAC denial modal (shown instead of a toast when access is blocked) --}}
    <div id="rbac-denied-backdrop"></div>
    <div id="rbac-denied-modal" role="dialog" aria-modal="true" aria-labelledby="rbac-modal-title">
        <div class="rbac-header">
            <div class="rbac-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 1a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2h1V6a5 5 0 0 1 5-5zm0 11a1.5 1.5 0 0 0-1 2.6V16a1 1 0 0 0 2 0v-1.4A1.5 1.5 0 0 0 12 12zm0-9a3 3 0 0 0-3 3v2h6V6a3 3 0 0 0-3-3z"/>
                </svg>
            </div>
            <h6 id="rbac-modal-title">Access Restricted</h6>
        </div>
        <div class="rbac-body">
            <p id="rbac-modal-message"></p>
            <button onclick="closeRbacModal()">OK, Got it</button>
        </div>
    </div>

    @include('frontend/layouts.navbar')

    @yield('content')

    @include('frontend/layouts.footer')
    <!-- Preloader Start -->
    <div id="preloader-active">
        <div class="preloader d-flex align-items-center justify-content-center">
            <div class="preloader-inner position-relative">
                <div class="text-center">
                    <h5 class="mb-5">Now Loading</h5>
                    <div class="loader">
                        <div class="bar bar1"></div>
                        <div class="bar bar2"></div>
                        <div class="bar bar3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Vendor JS-->
    <script src="{{asset('frontend/assets/js/vendor/modernizr-3.6.0.min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/vendor/jquery-3.6.0.min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/vendor/jquery-migrate-3.3.0.min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/vendor/bootstrap.bundle.min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/slick.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/jquery.syotimer.min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/wow.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/jquery-ui.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/perfect-scrollbar.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/magnific-popup.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/select2.min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/waypoints.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/counterup.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/jquery.countdown.min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/images-loaded.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/isotope.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/scrollup.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/jquery.vticker-min.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/jquery.theia.sticky.js')}}"></script>
    <script src="{{asset('frontend/assets/js/plugins/jquery.elevatezoom.js')}}"></script>
    <!-- Template  JS -->
    <script src="{{asset('frontend/assets/js/main.js?v=3.4')}}"></script>
    <!-- <script src="{{asset('frontend/assets/js/shop.js?v=3.4')}}"></script> -->

    @stack('scripts')

<script>
(function () {
    // ── RBAC denial modal ────────────────────────────────────────────────────
    window.closeRbacModal = function () {
        document.getElementById('rbac-denied-modal').style.display = 'none';
        document.getElementById('rbac-denied-backdrop').style.display = 'none';
    };

    function showRbacDenied(msg) {
        var text = msg || "You don't have permission to perform this action.";
        document.getElementById('rbac-modal-message').textContent = text;
        document.getElementById('rbac-denied-modal').style.display = 'block';
        document.getElementById('rbac-denied-backdrop').style.display = 'block';
    }

    // Close when clicking outside the modal.
    document.getElementById('rbac-denied-backdrop').addEventListener('click', closeRbacModal);

    // Server-side flash (page navigation blocked).
    @if (session('rbac_denied'))
    document.addEventListener('DOMContentLoaded', function () {
        showRbacDenied(@json(session('rbac_denied')));
    });
    @endif

    // AJAX requests blocked.
    $(document).ajaxError(function (event, xhr) {
        if (xhr.status !== 403) return;
        try {
            var data = JSON.parse(xhr.responseText);
            if (data.rbac_error) showRbacDenied(data.message);
        } catch (e) {}
    });
})();
</script>

    <script>
        //Get cart data on page view
        $(document).ready(function() {
            updateMiniPallet();
            updateListCount(); // Call updateListCount on page load
        });

        function updateMiniPallet() {
            $.ajax({
                url: "{{ url('mini-pallet') }}",
                type: "POST",
                global: false, // prevent global ajaxError handler from firing
                headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
                success: function(response) {
                    $('.custom-pallet').html(response.total || 0);
                    $('.update_pallet_preview').html(response.html);
                },
                error: function() {
                    $('.custom-pallet').html('0');
                }
            });
        }

        function updateListCount() {
            $.ajax({
                url: "{{ route('get-list-count') }}",
                type: "GET",
                global: false, // prevent global ajaxError handler from firing
                success: function(response) {
                    $('.lists-count').html(response.count || 0);
                },
                error: function() {
                    $('.lists-count').html('0');
                }
            });
        }
        $(document).on('click', '.remove-pallet-item', function() {
            let itemId = $(this).data('id');

            $.ajax({
                url: "{{ route('pallet-item-remove') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        updateMiniPallet(); // Update pallet total 
                        showToast(response.message, "success");

                        if (window.location.href.includes('/product-detail/')) {
                            updateSideBar(); // Call updateSideBar() if on product-detail page
                        }
                    } else {
                        showToast('Failed to remove item.', "error");

                    }
                },
                error: function() {
                    // toastr.error('Something went wrong. Please try again.', 'Error');
                }
            });
        });

        // Improved Toaster Function with consistent icons
        function showToast(message, type) {
            // Remove existing toasts
            $('#toast-container').empty();

            // Determine icon based on type
            let icon;
            switch (type) {
                case 'success':
                    icon = '✓';
                    break;
                case 'warning':
                    icon = '⚠';
                    break;
                case 'error':
                    icon = '✗';
                    break;
                case 'info':
                default:
                    icon = 'ℹ';
            }

            // Create toast element with consistent structure
            const toast = $(`
            <div class="toast toast-${type}"> 
                <div class="toast-message">${message}</div>
                <button class="toast-close-button" title="Dismiss">&times;</button>
            </div>
        `);

            // Add to container
            $('#toast-container').append(toast);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                toast.fadeOut(500, () => toast.remove());
            }, 5000);

            // Close button functionality
            toast.find('.toast-close-button').click(function() {
                toast.fadeOut(500, () => toast.remove());
            });
        }
    </script>

@auth
@if (!Auth::user()->hasVerifiedEmail())
{{-- ── Email Verification Modal ── --}}
<div class="modal fade" id="verifyEmailModal" tabindex="-1" aria-labelledby="verifyEmailModalLabel" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content border-0" style="border-radius:14px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.15)">
            <div style="height:4px;background:#6b1c1c"></div>
            <div class="modal-body p-4 p-md-5 text-center">

                <div style="width:64px;height:64px;border-radius:50%;background:rgba(107,28,28,.09);display:flex;align-items:center;justify-content:center;margin:0 auto 18px">
                    <i class="fi-rs-envelope-open" style="font-size:1.7rem;color:#6b1c1c"></i>
                </div>

                <h5 class="fw-bold mb-1" id="verifyEmailModalLabel" style="color:#1a1a1a">Verify your email address</h5>
                <p class="text-muted mb-4" style="font-size:.875rem;line-height:1.6">
                    We sent a verification link to <strong>{{ Auth::user()->email }}</strong>.<br>
                    Click it to activate your account.
                </p>

                <div class="text-start mb-4" style="font-size:.83rem">
                    @foreach(['Open the email from <strong>Wisselbanken</strong> (check spam if needed).', 'Click <strong>"Verify Email Address"</strong> inside the email.', 'You\'ll be taken straight into your account.'] as $i => $step)
                    <div class="d-flex align-items-start gap-2 p-2 mb-2 rounded" style="border:1px solid #eee;background:#fafafa;color:#555">
                        <div style="min-width:20px;height:20px;border-radius:50%;background:#6b1c1c;color:#fff;font-size:.65rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px">{{ $i + 1 }}</div>
                        <div>{!! $step !!}</div>
                    </div>
                    @endforeach
                </div>

                <hr class="my-3">

                @if (session('resent'))
                <div class="alert alert-success py-2 mb-3" style="font-size:.84rem;border-radius:8px">
                    <i class="fi-rs-check me-1"></i> New verification link sent — check your inbox.
                </div>
                @endif

                <p class="text-muted mb-2" style="font-size:.84rem">Didn't receive it?</p>
                <form method="POST" action="{{ route('verification.resend') }}">
                    @csrf
                    <button type="submit" class="btn w-100 fw-semibold text-white mb-3" style="background:#6b1c1c;border-radius:8px;padding:11px;font-size:.92rem">
                        Resend verification email
                    </button>
                </form>

                <div class="d-flex justify-content-center align-items-center gap-3" style="font-size:.84rem">
                    <a href="{{ url('/') }}" style="color:#6b1c1c;text-decoration:none">
                        <i class="fi-rs-home me-1"></i> Homepage
                    </a>
                    <span class="text-muted">·</span>
                    <a href="{{ route('login') }}" style="color:#6b1c1c;text-decoration:none">
                        <i class="fi-rs-sign-in me-1"></i> Back to login
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var showNow = @json(session('show_verify_modal', false));
        if (showNow) {
            var el = document.getElementById('verifyEmailModal');
            if (el && typeof bootstrap !== 'undefined') new bootstrap.Modal(el).show();
        }
    });
</script>
@endif
@endauth

</body>

</html>
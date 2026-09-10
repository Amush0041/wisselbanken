<!doctype html>
<html lang="en" class="light-style layout-wide customizer-hide" dir="ltr"
      data-theme="theme-default" data-assets-path="{{asset('backend/assets/')}}/"
      data-template="vertical-menu-template" data-style="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Workspace Ready | {{ env('APP_NAME','Wisselbanken') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('fav.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/fonts/tabler-icons.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/rtl/core.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/rtl/theme-default.css')}}" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Public Sans', sans-serif;
            background: linear-gradient(135deg, #fff5f5 0%, #fff 50%, #f5f0ff 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 2rem 1rem;
        }
        .page-wrap { max-width: 640px; width: 100%; text-align: center; }
        .check-icon {
            width: 72px; height: 72px; border-radius: 18px;
            background: rgba(107,28,28,.1);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .check-icon i { font-size: 2rem; color: #6b1c1c; }
        h1 { font-size: 2rem; font-weight: 700; color: #1a1a2e; margin-bottom: .75rem; }
        .subtitle { color: #6c757d; font-size: 1rem; margin-bottom: 2rem; }
        .subtitle strong { color: #1a1a2e; }
        .roles-grid { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-bottom: 2.5rem; }
        .role-card {
            background: #fff; border: 1px solid #eee; border-radius: .75rem;
            padding: 1.25rem 1rem; text-align: left; width: 180px; position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .role-card .card-icon {
            width: 36px; height: 36px; border-radius: .5rem;
            background: rgba(107,28,28,.08); display: flex; align-items: center; justify-content: center;
            margin-bottom: .75rem;
        }
        .role-card .card-icon i { color: #6b1c1c; font-size: 1rem; }
.role-card h6 { font-size: .85rem; font-weight: 600; color: #1a1a2e; margin-bottom: .35rem; }
        .role-card p { font-size: .76rem; color: #8a8d93; margin-bottom: .5rem; line-height: 1.4; }
        .role-card .access-level { display: flex; align-items: center; gap: .35rem; font-size: .72rem; color: #495057; }
        .access-dot { width: 6px; height: 6px; border-radius: 50%; background: #6b1c1c; }
        .btns { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .btn-primary-wb {
            display: inline-flex; align-items: center; gap: .5rem;
            background: #6b1c1c; color: #fff; border: none;
            padding: .7rem 1.5rem; border-radius: .5rem; font-weight: 600;
            font-size: .9rem; text-decoration: none; cursor: pointer;
        }
        .btn-primary-wb:hover { background: #5a1818; color: #fff; }
        .btn-outline-wb {
            display: inline-flex; align-items: center; gap: .5rem;
            background: transparent; color: #6b1c1c; border: 1.5px solid #6b1c1c;
            padding: .7rem 1.5rem; border-radius: .5rem; font-weight: 600;
            font-size: .9rem; text-decoration: none;
        }
        .btn-outline-wb:hover { background: rgba(107,28,28,.06); color: #6b1c1c; }
        .help-links { color: #8a8d93; font-size: .85rem; }
        .help-links a { color: #6b1c1c; text-decoration: none; }
        .help-links a:hover { text-decoration: underline; }
        .footer-brand { margin-top: 2rem; font-size: .72rem; letter-spacing: .1em; color: #c0c0c0; text-transform: uppercase; }

        @media (max-width: 480px) {
            .role-card { width: 145px; }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="check-icon">
            <i class="ti ti-circle-check"></i>
        </div>

        <h1>Your workspace is ready!</h1>
        <p class="subtitle">
            We've set up <strong>{{ optional($org)->name ?? 'your organization' }}</strong> with the following roles based on your organization type.
            These can be adjusted anytime in settings.
        </p>

        {{-- Role cards --}}
        @if ($roles->isNotEmpty())
            <div class="roles-grid">
                @foreach ($roles as $role)
                    @php
                        $icons = [
                            'organization_owner' => 'ti-building',
                            'procurement_manager' => 'ti-file-invoice',
                            'estimator' => 'ti-calculator',
                            'executive_approver' => 'ti-shield-check',
                            'manufacturer_admin' => 'ti-tools',
                            'product_manager' => 'ti-package',
                            'order_fulfillment_csr' => 'ti-truck',
                        ];
                        $accessLabels = [
                            'F' => 'Full Control', 'A' => 'Approval Power',
                            'O' => 'Own Records', 'S' => 'Draft Access', 'R' => 'Read Only',
                        ];
                        $icon = $icons[$role->slug] ?? 'ti-id-badge-2';
                        $firstLevel = 'R';
                    @endphp
                    <div class="role-card">
                        <div class="card-icon">
                            <i class="ti {{ $icon }}"></i>
                        </div>
                        <h6>{{ $role->name }}</h6>
                        <p>{{ Str::limit($role->description ?? 'Core platform role.', 55) }}</p>
                        <div class="access-level">
                            <span class="access-dot"></span>
                            {{ $accessLabels[$firstLevel] ?? 'Access granted' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div style="background:#fff;border:1px solid #eee;border-radius:.75rem;padding:1.5rem;margin-bottom:2rem;color:#8a8d93">
                <i class="ti ti-id-badge-2" style="font-size:1.5rem;display:block;margin-bottom:.5rem;color:#6b1c1c"></i>
                Your roles are being configured. You can manage them from the Org Admin panel.
            </div>
        @endif

        <div class="btns">
            @if (auth()->user()?->hasVerifiedEmail())
                <a href="{{ route('org-admin.overview') }}" class="btn-primary-wb">
                    <i class="ti ti-arrow-right"></i> Go to Dashboard
                </a>
            @else
                <a href="{{ route('verification.notice') }}" class="btn-primary-wb">
                    <i class="ti ti-mail-check"></i> Verify Email &amp; Continue
                </a>
            @endif
            <a href="{{ route('org-admin.index') }}" class="btn-outline-wb">
                <i class="ti ti-user-plus"></i> Invite Team Members
            </a>
        </div>

        <p class="help-links">
            <a href="#"><i class="ti ti-help-circle me-1"></i>Help Center</a>
            &nbsp;·&nbsp;
            <a href="#"><i class="ti ti-player-play me-1"></i>Quick Setup Guide</a>
        </p>

        <p class="footer-brand">Wisselbanken Enterprise Resource Module</p>
    </div>

    <script src="{{asset('backend/assets/vendor/libs/jquery/jquery.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/js/bootstrap.js')}}"></script>
</body>
</html>

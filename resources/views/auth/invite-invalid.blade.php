<!doctype html>
<html lang="en" class="light-style" dir="ltr" data-theme="theme-default"
      data-assets-path="{{ asset('backend/assets/') }}/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invalid Invite | {{ env('APP_NAME','Wisselbanken') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('fav.png') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/css/demo.css') }}" />
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f4f5fb">
<div class="text-center" style="max-width:420px;padding:2rem">
    <i class="ti ti-link-off" style="font-size:3.5rem;color:#6b1c1c;display:block;margin-bottom:1rem"></i>
    <h4 class="fw-bold mb-2">Invite link invalid or expired</h4>
    <p class="text-muted mb-4">This invite link has already been used, has expired, or does not exist. Please ask your organization owner to send a new one.</p>
    <a href="{{ route('login') }}" class="btn text-white" style="background:#6b1c1c">Go to login</a>
</div>
</body>
</html>

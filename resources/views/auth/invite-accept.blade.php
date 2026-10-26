<!doctype html>
<html lang="en" class="light-style" dir="ltr" data-theme="theme-default"
      data-assets-path="{{ asset('backend/assets/') }}/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Accept Invitation | {{ env('APP_NAME','Wisselbanken') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('fav.png') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/css/demo.css') }}" />
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f4f5fb">
<div class="text-center" style="max-width:460px;padding:2rem">
    <i class="ti ti-mail-forward" style="font-size:3.5rem;color:#6b1c1c;display:block;margin-bottom:1rem"></i>
    <h4 class="fw-bold mb-2">You've been invited to {{ $invite->organization->name }}</h4>
    <p class="text-muted mb-4">
        {{ $invite->invitedBy?->name ?? 'A team member' }} invited you to join
        <strong>{{ $invite->organization->name }}</strong> as <strong>{{ $invite->role->name }}</strong>.
        Accepting adds this role to your existing account.
    </p>

    @if (session('error'))
        <div class="alert alert-danger text-start">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('invite.accept', $token) }}">
        @csrf
        <button type="submit" class="btn text-white me-2" style="background:#6b1c1c">Accept invitation</button>
        <a href="{{ route('user.dashboard') }}" class="btn btn-outline-secondary">Not now</a>
    </form>
</div>
</body>
</html>

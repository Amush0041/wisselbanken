<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email — {{ config('app.name', 'Wisselbanken') }}</title>
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/fonts/tabler-icons.css') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        :root { --wb: #6b1c1c; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f5f5f6;
            font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #333;
        }

        /* ── Header ── */
        .auth-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 18px 32px;
            display: flex;
            align-items: center;
        }
        .auth-header img { height: 36px; object-fit: contain; }

        /* ── Main ── */
        .auth-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 16px;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .auth-card-accent { height: 4px; background: var(--wb); }
        .auth-card-body { padding: 40px 36px 36px; }

        .icon-circle {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: rgba(107,28,28,.08);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .icon-circle i { font-size: 1.8rem; color: var(--wb); }

        h2 { margin: 0 0 6px; font-size: 1.25rem; font-weight: 700; text-align: center; color: #1a1a1a; }
        .subtitle { margin: 0 0 28px; font-size: .875rem; color: #777; text-align: center; line-height: 1.5; }

        .step {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 11px 14px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: .84rem;
            color: #555;
            background: #fafafa;
        }
        .step-num {
            flex-shrink: 0;
            width: 20px; height: 20px;
            border-radius: 50%;
            background: var(--wb);
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }

        hr { border: none; border-top: 1px solid #eee; margin: 24px 0; }

        .resend-label { text-align: center; font-size: .84rem; color: #888; margin-bottom: 14px; }

        .btn-primary {
            display: block; width: 100%;
            background: var(--wb); color: #fff;
            border: none; border-radius: 8px;
            padding: 12px;
            font-size: .92rem; font-weight: 600;
            cursor: pointer; text-align: center;
            transition: opacity .15s;
        }
        .btn-primary:hover { opacity: .88; }

        .alert-success {
            background: #edf7ed; border: 1px solid #b7dfb8; border-radius: 8px;
            padding: 10px 14px; font-size: .84rem; color: #2e7d32; margin-bottom: 20px;
        }

        /* ── Footer ── */
        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 18px;
            font-size: .84rem;
        }
        .nav-links a {
            color: var(--wb);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .nav-links a:hover { text-decoration: underline; }
        .nav-links .divider { color: #ccc; }

        .auth-footer {
            text-align: center;
            padding: 20px 16px;
            font-size: .78rem;
            color: #aaa;
        }
        .auth-footer a { color: #aaa; text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    {{-- Header --}}
    <header class="auth-header">
        <a href="{{ url('/') }}">
            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}">
        </a>
    </header>

    {{-- Main --}}
    <main class="auth-main">
        <div class="auth-card">
            <div class="auth-card-accent"></div>
            <div class="auth-card-body">

                <div class="icon-circle">
                    <i class="ti ti-mail-opened"></i>
                </div>

                <h2>Check your inbox</h2>
                <p class="subtitle">
                    We sent a verification link to your email.<br>
                    Click it to activate your account.
                </p>

                @if (session('resent'))
                    <div class="alert-success">
                        <i class="ti ti-check" style="margin-right:6px"></i>
                        New verification link sent — check your inbox.
                    </div>
                @endif

                <div class="step">
                    <div class="step-num">1</div>
                    <div>Open the email from <strong>Wisselbanken</strong> (check spam if needed).</div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div>Click <strong>"Verify Email Address"</strong> inside the email.</div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div>You'll be taken to the login page to sign in and access your account.</div>
                </div>

                <hr>

                <p class="resend-label">Didn't receive it?</p>
                <form method="POST" action="{{ route('verification.resend') }}">
                    @csrf
                    <button type="submit" class="btn-primary">
                        Resend verification email
                    </button>
                </form>

                <div class="nav-links">
                    <a href="{{ url('/') }}">
                        <i class="ti ti-home"></i> Go to homepage
                    </a>
                    <span class="divider">·</span>
                    <a href="{{ route('login') }}">
                        <i class="ti ti-login"></i> Back to login
                    </a>
                </div>

            </div>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="auth-footer">
        &copy; {{ date('Y') }} Wisselbanken &nbsp;·&nbsp;
        <a href="{{ url('/') }}">Home</a> &nbsp;·&nbsp;
        <a href="#">Privacy Policy</a>
    </footer>

</body>
</html>

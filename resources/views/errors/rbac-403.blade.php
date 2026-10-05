<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Restricted | {{ env('APP_NAME', 'Wisselbanken') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('fav.png') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/fonts/tabler-icons.css') }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .page {
            background: #fff;
            border-radius: 1rem;
            padding: 3rem 2.5rem;
            text-align: center;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 .5rem 2rem rgba(0,0,0,.08);
            position: relative;
        }
        /* Grid dot background */
        .page::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 1rem;
            background-image: radial-gradient(circle, #dee2e6 1px, transparent 1px);
            background-size: 28px 28px;
            opacity: .35;
            pointer-events: none;
        }
        .lock-icon {
            width: 72px; height: 72px; border-radius: 16px;
            background: #fff; box-shadow: 0 .25rem 1rem rgba(0,0,0,.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; margin: 0 auto 1.5rem; position: relative; z-index: 1;
        }
        h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: .75rem; position: relative; z-index: 1; }
        p { color: #6c757d; font-size: .9rem; line-height: 1.6; margin-bottom: 1.5rem; position: relative; z-index: 1; }
        code {
            background: #f1f3f5; padding: .15rem .4rem; border-radius: .3rem;
            font-size: .85rem; color: #6b1c1c; font-weight: 600;
        }
        .notif {
            display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem;
            background: #f8f9fa; border-radius: .5rem; margin-bottom: 1.5rem;
            font-size: .83rem; color: #495057; position: relative; z-index: 1;
        }
        .notif-avatar {
            width: 36px; height: 36px; border-radius: 50%; background: #6b1c1c;
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; flex-shrink: 0;
        }
        .btn-primary-wb {
            display: block; width: 100%; padding: .75rem;
            background: #6b1c1c; color: #fff; border: none;
            border-radius: .5rem; font-size: .9rem; font-weight: 600;
            text-decoration: none; cursor: pointer; margin-bottom: .75rem;
            position: relative; z-index: 1;
        }
        .btn-primary-wb:hover { background: #5a1717; color: #fff; }
        .btn-secondary-wb {
            display: block; width: 100%; padding: .75rem;
            background: #fff; color: #495057; border: 1px solid #dee2e6;
            border-radius: .5rem; font-size: .9rem; font-weight: 500;
            text-decoration: none; cursor: pointer; position: relative; z-index: 1;
        }
        .btn-secondary-wb:hover { background: #f8f9fa; }
        .footer-badge {
            display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .75rem;
            background: #dc3545; color: #fff; border-radius: .5rem; font-size: .72rem;
            font-weight: 600; letter-spacing: .03em; margin-top: 1.5rem; position: relative; z-index: 1;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="lock-icon">
            <i class="ti ti-lock" style="color:#6b1c1c"></i>
        </div>

        <h2>You don't have access to this</h2>

        @if (!empty($permissionGroup) && !empty($requiredLevel))
            <p>
                This section requires the <code>{{ $permissionGroup }}</code> permission at level <code>{{ $requiredLevel }}</code>.
                Your current credentials do not grant access.
            </p>
        @elseif (!empty($message))
            <p>{{ $message }}</p>
        @else
            <p>
                This section is restricted. Your current credentials do not grant visibility into this area.
            </p>
        @endif

        <p>Contact your organization administrator if you need access.</p>

        @if (!empty($adminName))
            <div class="notif">
                <div class="notif-avatar">{{ strtoupper(substr($adminName, 0, 2)) }}</div>
                <div>
                    <strong>System Administrator</strong><br>
                    Notifying <strong>{{ $adminName }}</strong>
                </div>
            </div>
        @endif

        <a href="javascript:history.back()" class="btn-secondary-wb">Go back</a>
        <a href="{{ url('/user-dashboard') }}" class="btn-primary-wb" style="margin-top:.75rem">Back to my workspace</a>

        <span class="footer-badge">
            <i class="ti ti-shield-lock"></i> ACCESS RESTRICTED
        </span>
    </div>
</body>
</html>

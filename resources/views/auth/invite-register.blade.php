<!doctype html>
<html lang="en" class="light-style layout-wide customizer-hide" dir="ltr"
      data-theme="theme-default" data-assets-path="{{asset('backend/assets/')}}/"
      data-template="vertical-menu-template" data-style="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Accept Invitation | {{ env('APP_NAME','Wisselbanken') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('fav.png') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend/assets/vendor/css/pages/page-auth.css') }}" />
    <script src="{{ asset('backend/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('backend/assets/vendor/js/template-customizer.js') }}"></script>
    <script src="{{ asset('backend/assets/js/config.js') }}"></script>
    <style>
        .invalid-feedback { display: block; }
        .auth-split { display: flex; min-height: 100vh; }
        .auth-brand {
            flex: 0 0 44%; max-width: 44%;
            background: linear-gradient(150deg, #0f172a 0%, #1e293b 55%, #312e81 100%);
            color: #fff; padding: 3.5rem; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
        }
        .auth-brand .brand-name { font-family: 'Playfair Display', serif; font-size: 2rem; letter-spacing: .04em; color: #fff; margin-bottom: 2rem; }
        .auth-brand .brand-name span { color: #d4af37; }
        .auth-form { flex: 1 1 56%; display: flex; align-items: center; justify-content: center; padding: 2.5rem 1.25rem; background: #f4f5fb; }
        .auth-card { width: 100%; max-width: 520px; background: #fff; border-radius: 1.1rem; box-shadow: 0 .3rem 1.6rem rgba(20,23,40,.07); padding: 2.75rem; }
        .invite-badge { display: inline-flex; align-items: center; gap: .5rem; background: rgba(107,28,28,.08); color: #6b1c1c; border-radius: .5rem; padding: .4rem .85rem; font-size: .82rem; font-weight: 600; margin-bottom: 1.5rem; }
        .role-pill { display: inline-block; background: #6b1c1c; color: #fff; border-radius: .4rem; padding: .2rem .65rem; font-size: .78rem; font-weight: 600; }
        @media (max-width: 768px) {
            .auth-brand { display: none; }
            .auth-form { padding: 1.5rem 1rem; }
        }
    </style>
</head>
<body>
<div class="auth-split">
    {{-- Brand panel --}}
    <div class="auth-brand">
        <div class="brand-name">Wissel<span>banken</span></div>
        <h1 style="color:#fff;font-size:2rem;font-weight:700;margin-bottom:1rem">You've been invited</h1>
        <p style="color:rgba(255,255,255,.72);font-size:1rem;margin-bottom:2rem">
            Complete your registration to join <strong style="color:#d4af37">{{ $invite->organization->name }}</strong>
            and start collaborating with your team.
        </p>
        <div style="display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;background:rgba(255,255,255,.07);border-radius:.75rem">
            <i class="ti ti-shield-check" style="font-size:1.5rem;color:#d4af37"></i>
            <div>
                <div style="font-weight:600;color:#fff">{{ $invite->role->name }}</div>
                <div style="font-size:.8rem;color:rgba(255,255,255,.6)">Your assigned role in this organization</div>
            </div>
        </div>
    </div>

    {{-- Form panel --}}
    <div class="auth-form">
        <div class="auth-card">
            <div class="invite-badge">
                <i class="ti ti-mail-forward"></i>
                Invitation from {{ $invite->organization->name }}
            </div>

            <h4 class="mb-1">Create your account</h4>
            <p class="text-muted mb-4" style="font-size:.9rem">
                You're joining as <span class="role-pill">{{ $invite->role->name }}</span>
            </p>

            @if ($errors->any())
                <div class="alert alert-danger mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('invite.register', $token) }}">
                @csrf

                {{-- Email — pre-filled, read-only --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email address</label>
                    <input type="email" class="form-control bg-light" value="{{ $invite->email }}" readonly disabled>
                    <small class="text-muted">This invite is tied to this email address.</small>
                </div>

                {{-- Name --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="name">Full name</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $invite->name) }}" placeholder="Your full name" required autofocus>
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                {{-- Password --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="password">Password</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               placeholder="Min. 8 characters" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('password', this)">
                            <i class="ti ti-eye"></i>
                        </button>
                    </div>
                    @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                {{-- Confirm password --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="password_confirmation">Confirm password</label>
                    <div class="input-group">
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control" placeholder="Repeat password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('password_confirmation', this)">
                            <i class="ti ti-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn w-100 text-white fw-semibold" style="background:#6b1c1c;padding:.7rem">
                    <i class="ti ti-check me-1"></i> Accept invitation &amp; create account
                </button>
            </form>

            <p class="text-center text-muted mt-3 mb-0" style="font-size:.82rem">
                Already have an account? <a href="{{ route('login') }}">Sign in</a> — your role will be assigned automatically.
            </p>
        </div>
    </div>
</div>

<script src="{{ asset('backend/assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('backend/assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('backend/assets/vendor/js/bootstrap.js') }}"></script>
<script>
function togglePwd(fieldId, btn) {
    const f = document.getElementById(fieldId);
    const isText = f.type === 'text';
    f.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'ti ti-eye' : 'ti ti-eye-off';
}
</script>
</body>
</html>

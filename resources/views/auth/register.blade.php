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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Create account | {{env('APP_NAME','Wisselbanken')}}</title>
    <meta name="description" content="Create your Wisselbanken account" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('fav.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/fonts/fontawesome.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/fonts/tabler-icons.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/fonts/flag-icons.css')}}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/rtl/core.css')}}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/rtl/theme-default.css')}}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{asset('backend/assets/css/demo.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/node-waves/node-waves.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
    <link rel="stylesheet" href="{{asset('backend/assets/vendor/css/pages/page-auth.css')}}" />

    <!-- Helpers -->
    <script src="{{asset('backend/assets/vendor/js/helpers.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/js/template-customizer.js')}}"></script>
    <script src="{{asset('backend/assets/js/config.js')}}"></script>

    <style>
        .invalid-feedback { display: block; }

        .auth-split { display: flex; min-height: 100vh; }

        /* Left brand panel */
        .auth-brand {
            flex: 0 0 44%; max-width: 44%;
            background: linear-gradient(150deg, #0f172a 0%, #1e293b 55%, #312e81 100%);
            color: #fff; padding: 3.5rem; position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
        }
        .auth-brand::after {
            content: ""; position: absolute; right: -120px; bottom: -120px;
            width: 360px; height: 360px; border-radius: 50%;
            background: radial-gradient(circle, rgba(212,175,55,.22), transparent 70%);
        }
        .auth-brand .brand-name {
            font-family: 'Playfair Display', serif; font-size: 2rem; letter-spacing: .04em;
            color: #fff; margin-bottom: 2.5rem;
        }
        .auth-brand .brand-name span { color: #d4af37; }
        .auth-brand h1 { color: #fff !important; font-size: 2.1rem; font-weight: 700; line-height: 1.25; margin-bottom: 1rem; }
        .auth-brand p.lead-sub { color: rgba(255,255,255,.72); font-size: 1.02rem; margin-bottom: 2.25rem; }
        .auth-brand .feat { display: flex; align-items: flex-start; gap: .85rem; margin-bottom: 1.25rem; }
        .auth-brand .feat i { color: #d4af37; font-size: 1.35rem; line-height: 1.4; }
        .auth-brand .feat div b { display: block; font-weight: 600; color: #fff; }
        .auth-brand .feat div small { color: rgba(255,255,255,.6); }

        /* Right form panel */
        .auth-form { flex: 1 1 56%; display: flex; align-items: center; justify-content: center;
            padding: 2.5rem 1.25rem; background: #f4f5fb; }
        .auth-card { width: 100%; max-width: 600px; background: #fff; border-radius: 1.1rem;
            box-shadow: 0 .3rem 1.6rem rgba(20,23,40,.07); padding: 2.75rem; }
        .auth-card .mobile-logo { display: none; }
        .auth-card h4 { font-weight: 700; margin-bottom: .25rem; }
        .auth-card .subtitle { color: #8a8d93; margin-bottom: 1.75rem; }

        /* Wizard steps */
        .wizard-steps { display:flex; justify-content:space-between; margin-bottom:2rem; position:relative; }
        .wizard-steps::before {
            content:''; position:absolute; top:14px; left:0; right:0; height:2px;
            background:#e9ecef; z-index:0;
        }
        .step-item { display:flex; flex-direction:column; align-items:center; gap:.35rem; z-index:1; flex:1; }
        .step-circle {
            width:28px; height:28px; border-radius:50%; background:#e9ecef; color:#8a8d93;
            display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:700;
            transition:background .2s, color .2s;
        }
        .step-circle.done { background:#198754; color:#fff; }
        .step-circle.active { background:#6b1c1c; color:#fff; }
        .step-label { font-size:.68rem; color:#8a8d93; text-align:center; font-weight:500; }
        .step-label.active { color:#6b1c1c; font-weight:600; }

        /* Org type cards (Step 1) */
        .org-type-grid { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; max-height:260px; overflow-y:auto; padding-right:.25rem; }
        .org-type-card {
            border:2px solid #dee2e6; border-radius:.6rem; padding:.7rem .8rem;
            cursor:pointer; transition:border-color .15s, background .15s; position:relative;
        }
        .org-type-card:hover { border-color:#6b1c1c; background:rgba(107,28,28,.04); }
        .org-type-card.selected { border-color:#6b1c1c; background:rgba(107,28,28,.06); }
        .org-type-card input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
        .org-type-card .ot-name { font-size:.8rem; font-weight:600; color:#333; }
        .org-type-card .ot-purpose { font-size:.7rem; color:#8a8d93; margin-top:.15rem; line-height:1.3; }
        .org-type-card .ot-check {
            position:absolute; top:.5rem; right:.5rem; width:16px; height:16px;
            border-radius:50%; background:#6b1c1c; color:#fff; display:none;
            align-items:center; justify-content:center; font-size:.6rem;
        }
        .org-type-card.selected .ot-check { display:flex; }

        /* Step panels */
        .step-panel { display:none; }
        .step-panel.active { display:block; }

        /* Responsive */
        @media (max-width: 991.98px) {
            .auth-brand { display: none; }
            .auth-form { flex: 1 1 100%; }
        }
        @media (max-width: 575.98px) {
            .auth-form { padding: 1.25rem .75rem; background: #fff; }
            .auth-card { padding: 1.25rem; box-shadow: none; border-radius: 0; max-width: 480px; }
            .auth-card .mobile-logo { display: block; }
        }
    </style>
</head>

<body>
    <div class="auth-split">

        <!-- Brand panel -->
        <aside class="auth-brand">
            <div class="brand-name">WISSELBANKEN<span>.</span></div>
            <h1>Create your account</h1>
            <p class="lead-sub">Set up your organization once — we’ll put the right roles and access in place for your team automatically.</p>

            <div class="feat">
                <i class="ti ti-rosette-discount-check"></i>
                <div><b>Smart onboarding</b><small>Tell us your org type &amp; size — starter roles are assigned for you.</small></div>
            </div>
            <div class="feat">
                <i class="ti ti-users-group"></i>
                <div><b>Built for your whole team</b><small>Buyers, sellers, manufacturers &amp; contractors.</small></div>
            </div>
            <div class="feat">
                <i class="ti ti-shield-lock"></i>
                <div><b>Secure by design</b><small>Granular, role-based access from day one.</small></div>
            </div>
        </aside>

        <!-- Form panel -->
        <main class="auth-form">
            <div class="auth-card">
                <div class="mobile-logo text-center mb-4">
                    <a href="{{ url('/') }}"><img src="{{ asset('logo.png') }}" alt="{{ env('APP_NAME','Wisselbanken') }}" style="max-height:46px;max-width:100%"></a>
                </div>

                <h4>Get started</h4>
                <p class="subtitle">Create your organization account in under a minute.</p>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 mb-3">{{ $errors->first() }}</div>
                @endif

                {{-- Step progress --}}
                <div class="wizard-steps mb-4">
                    <div class="step-item">
                        <div class="step-circle active" id="sc1">1</div>
                        <span class="step-label active" id="sl1">Org Type</span>
                    </div>
                    <div class="step-item">
                        <div class="step-circle" id="sc2">2</div>
                        <span class="step-label" id="sl2">Your Details</span>
                    </div>
                    <div class="step-item">
                        <div class="step-circle" id="sc3">3</div>
                        <span class="step-label" id="sl3">Role Preview</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('register') }}" id="regForm">
                    @csrf

                    {{-- ── STEP 1: Org Type Selection ── --}}
                    <div class="step-panel active" id="step1">
                        <h6 class="fw-semibold mb-1">What best describes your organization?</h6>
                        <p class="text-muted small mb-3">This determines which starter roles are assigned to your team.</p>

                        <div class="org-type-grid mb-3" id="orgTypeGrid">
                            @foreach (\App\Support\Rbac\OrganizationType::all() as [$slug, $label, $purpose])
                                <label class="org-type-card {{ old('org_type') === $slug ? 'selected' : '' }}" onclick="selectOrgType(this, '{{ $slug }}')">
                                    <input type="radio" name="org_type" value="{{ $slug }}" {{ old('org_type') === $slug ? 'checked' : '' }}>
                                    <span class="ot-check"><i class="ti ti-check" style="font-size:.5rem"></i></span>
                                    <div class="ot-name">{{ $label }}</div>
                                    <div class="ot-purpose">{{ $purpose }}</div>
                                </label>
                            @endforeach
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Team size <span class="text-danger">*</span></label>
                            <select id="team_size" name="team_size" class="form-select @error('team_size') is-invalid @enderror" required>
                                <option value="" disabled {{ old('team_size') ? '' : 'selected' }}>How many people?</option>
                                @foreach (config('rbac.team_sizes') as $size)
                                    <option value="{{ $size }}" @selected(old('team_size') === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                            @error('team_size')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>

                        <button type="button" class="btn btn-primary w-100" onclick="goStep(2)">
                            Continue <i class="ti ti-arrow-right ms-1"></i>
                        </button>
                    </div>

                    {{-- ── STEP 2: Personal + Org Details ── --}}
                    <div class="step-panel" id="step2">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label for="name" class="form-label">Your name <span class="text-danger">*</span></label>
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                                       name="name" value="{{ old('name') }}" placeholder="Jane Doe" required autofocus />
                                @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="email" class="form-label">Work email <span class="text-danger">*</span></label>
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                       name="email" value="{{ old('email') }}" placeholder="you@company.com" required autocomplete="email" />
                                @error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-12">
                                <label for="company_name" class="form-label">Company / Organization name</label>
                                <input id="company_name" type="text" class="form-control @error('company_name') is-invalid @enderror"
                                       name="company_name" value="{{ old('company_name') }}" placeholder="Acme Builders" />
                                @error('company_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-12 col-sm-6 form-password-toggle">
                                <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                                <div class="input-group input-group-merge">
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                                           name="password" required autocomplete="new-password" placeholder="At least 8 characters" />
                                    <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
                                </div>
                                @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-12 col-sm-6 form-password-toggle">
                                <label class="form-label" for="password-confirm">Confirm password <span class="text-danger">*</span></label>
                                <div class="input-group input-group-merge">
                                    <input id="password-confirm" type="password" class="form-control"
                                           name="password_confirmation" required autocomplete="new-password" placeholder="Re-enter password" />
                                    <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="goStep(1)">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary flex-grow-1" onclick="goStep(3)">
                                Continue <i class="ti ti-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ── STEP 3: Role Preview ── --}}
                    <div class="step-panel" id="step3">
                        <div class="rounded-3 border p-3 mb-3" style="background:#f8f9fa">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <i class="ti ti-shield-check text-success mt-1"></i>
                                <div>
                                    <p class="mb-1 small fw-semibold">Roles auto-assigned to your organization</p>
                                    <p class="mb-0 small text-muted">Based on your org type and team size — you can customize these after setup.</p>
                                </div>
                            </div>
                            <div id="rolePreviewTags" class="d-flex flex-wrap gap-2 mt-2"></div>
                            <p class="text-muted small mt-2 mb-0" id="noRoleMsg" style="display:none">
                                <i class="ti ti-info-circle me-1"></i> Please go back and select an org type and team size.
                            </p>
                        </div>

                        <div class="rounded-3 border p-3 mb-3 small text-muted">
                            <i class="ti ti-user me-1"></i>
                            <span id="summaryName">—</span> ·
                            <i class="ti ti-mail ms-2 me-1"></i>
                            <span id="summaryEmail">—</span> ·
                            <i class="ti ti-building ms-2 me-1"></i>
                            <span id="summaryOrg">—</span>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary" onclick="goStep(2)">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </button>
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="ti ti-rocket me-1"></i> Create account
                            </button>
                        </div>
                    </div>
                </form>

                <p class="text-center mt-4 mb-0">
                    <span class="text-muted">Already have an account?</span>
                    <a href="{{ route('login') }}" class="fw-medium">Sign in</a>
                </p>
            </div>
        </main>
    </div>

    <!-- Core JS -->
    <script src="{{asset('backend/assets/vendor/libs/jquery/jquery.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/popper/popper.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/js/bootstrap.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/node-waves/node-waves.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
    <script src="{{asset('backend/assets/vendor/js/menu.js')}}"></script>
    <script src="{{asset('backend/assets/js/main.js')}}"></script>
    <script src="{{asset('backend/assets/js/pages-auth.js')}}"></script>
    <script>
    (function() {
        const buyerTypes  = @json(config('rbac.onboarding.buyer_org_types', []));
        const mfrTypes    = @json(config('rbac.onboarding.manufacturer_org_types', []));
        const smallSizes  = @json(config('rbac.small_team_sizes', []));
        const bundles     = @json(config('rbac.onboarding.bundles', []));

        const bundleLabels = {
            'organization_owner':   'Organization Owner',
            'procurement_manager':  'Procurement Manager',
            'estimator':            'Estimator',
            'executive_approver':   'Executive Approver',
            'manufacturer_admin':   'Manufacturer Admin',
            'product_manager':      'Product Manager',
            'order_fulfillment_csr':'Order Fulfillment',
        };

        let currentStep = 1;
        let selectedOrgType = '{{ old("org_type", "") }}';

        window.selectOrgType = function(card, slug) {
            document.querySelectorAll('.org-type-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            card.querySelector('input').checked = true;
            selectedOrgType = slug;
        };

        window.goStep = function(n) {
            // Validate before advancing
            if (n === 2 && currentStep === 1) {
                if (!document.querySelector('input[name=org_type]:checked')) {
                    alert('Please select an organization type before continuing.');
                    return;
                }
                if (!document.getElementById('team_size').value) {
                    alert('Please select a team size before continuing.');
                    return;
                }
            }
            if (n === 3 && currentStep === 2) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const pwd = document.getElementById('password').value;
                const pwdC = document.getElementById('password-confirm').value;
                if (!name || !email || !pwd || !pwdC) {
                    alert('Please fill in all required fields.');
                    return;
                }
                if (pwd !== pwdC) {
                    alert('Passwords do not match.');
                    return;
                }
                // Update summary panel
                document.getElementById('summaryName').textContent  = name;
                document.getElementById('summaryEmail').textContent = email;
                document.getElementById('summaryOrg').textContent   = document.getElementById('company_name').value || name + "'s Org";
                buildRolePreview();
            }

            // Hide all panels
            document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('step' + n).classList.add('active');

            // Update step circles
            for (let i = 1; i <= 3; i++) {
                const circ = document.getElementById('sc' + i);
                const lbl  = document.getElementById('sl' + i);
                circ.classList.remove('active', 'done');
                lbl.classList.remove('active');
                if (i < n) { circ.classList.add('done'); circ.innerHTML = '<i class="ti ti-check" style="font-size:.6rem"></i>'; }
                else if (i === n) { circ.classList.add('active'); circ.textContent = i; lbl.classList.add('active'); }
                else { circ.textContent = i; }
            }

            currentStep = n;
        };

        function buildRolePreview() {
            const orgType  = selectedOrgType;
            const teamSize = document.getElementById('team_size').value;
            const tags     = document.getElementById('rolePreviewTags');
            const noMsg    = document.getElementById('noRoleMsg');

            if (!orgType || !teamSize) {
                tags.innerHTML = '';
                noMsg.style.display = 'block';
                return;
            }
            noMsg.style.display = 'none';

            let slugs = [];
            const isSmall = smallSizes.includes(teamSize);
            if (buyerTypes.includes(orgType) && isSmall)      slugs = bundles.buyer_small || [];
            else if (mfrTypes.includes(orgType) && isSmall)   slugs = bundles.manufacturer_small || [];
            else                                                slugs = bundles.owner_only || [];

            tags.innerHTML = slugs.map(slug => {
                const label = bundleLabels[slug] || slug.replace(/_/g,' ');
                return `<span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill border bg-white" style="font-size:.78rem">
                    <span style="width:7px;height:7px;background:#6b1c1c;border-radius:50%;display:inline-block"></span>
                    ${label}
                </span>`;
            }).join('');
        }

        // Restore step if there were validation errors from server
        @if ($errors->any())
            goStep(2);
        @elseif (old('org_type'))
            goStep({{ old('name') ? 2 : 1 }});
        @endif
    })();
    </script>
</body>

</html>

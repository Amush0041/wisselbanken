@extends('user.layouts.app')

@section('seo')
<title>My Roles | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .delegation-banner { background:var(--wb-maroon);color:#fff;border-radius:.75rem;padding:1rem 1.5rem; }
    .role-card { background:#fff;border:1px solid #eee;border-radius:.6rem;padding:.75rem 1rem; }
    .role-tag-admin { background:var(--wb-maroon);color:#fff;padding:.2rem .55rem;border-radius:.35rem;font-size:.72rem;font-weight:700; }
    .role-tag-editor { background:#0d47a1;color:#fff;padding:.2rem .55rem;border-radius:.35rem;font-size:.72rem;font-weight:700; }
    .role-tag-generic { background:#495057;color:#fff;padding:.2rem .55rem;border-radius:.35rem;font-size:.72rem;font-weight:700; }
    .org-card { border:0;border-radius:.75rem;box-shadow:0 .125rem .5rem rgba(0,0,0,.08);margin-bottom:1rem; }
    .badge-P1 { background:#6b1c1c;color:#fff; }
    .badge-P2 { background:#c0392b;color:#fff;opacity:.8; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="mb-1">
        <h4 class="mb-0 fw-bold">My Access &amp; Roles</h4>
        <p class="text-muted small mb-0">Manage your organizational identity and role delegations</p>
    </div>

    @include('user.org-admin._nav')

    {{-- Active delegation banner --}}
    @if ($activeDelegation)
        <div class="delegation-banner mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-bold"
                     style="width:44px;height:44px;background:rgba(255,255,255,.2);font-size:.8rem">
                    {{ strtoupper(substr(optional($activeDelegation->fromUser)->name ?? '?', 0, 2)) }}
                </div>
                <div>
                    <div class="fw-semibold">Acting as {{ optional($activeDelegation->fromUser)->name ?? 'Unknown' }}</div>
                    <small style="opacity:.8">You are currently using elevated privileges via delegation.</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-center">
                    <div class="fw-bold" id="delegationTimer">—</div>
                    <small style="opacity:.7;font-size:.7rem">Remaining</small>
                </div>
                <form action="{{ route('org-admin.delegations.destroy', $activeDelegation->id) }}" method="POST"
                      onsubmit="return confirm('End this delegation session? You will return to your own permissions.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm"
                            style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4)">
                        <i class="ti ti-logout me-1"></i> Terminate Session
                    </button>
                </form>
            </div>
        </div>
        <script>
        (function() {
            const expires = new Date("{{ $activeDelegation->expires_at->toISOString() }}");
            function tick() {
                const diff = Math.max(0, expires - new Date());
                const h = String(Math.floor(diff/3600000)).padStart(2,'0');
                const m = String(Math.floor(diff%3600000/60000)).padStart(2,'0');
                const s = String(Math.floor(diff%60000/1000)).padStart(2,'0');
                document.getElementById('delegationTimer').textContent = `${h}:${m}:${s}`;
                if (diff > 0) setTimeout(tick, 1000);
            }
            tick();
        })();
        </script>
    @endif

    {{-- My roles grouped by org --}}
    @if ($orgGroups->isEmpty())
        <div class="card border-0 shadow-sm text-center py-5 text-muted">
            <i class="ti ti-id-badge-2 fs-2 d-block mb-2"></i>
            <p class="mb-1">You haven't been assigned any roles yet.</p>
            <small>Contact your organization admin to get started.</small>
        </div>
    @else
        <div class="row g-4">
            @foreach ($orgGroups as $orgId => $assignments)
                @php
                    $orgEntity = optional($assignments->first()->organization);
                    $isCurrent = ($orgId == $currentOrg->id);
                @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="org-card card {{ $isCurrent ? 'border-start border-3' : '' }}" style="{{ $isCurrent ? 'border-color:var(--wb-maroon)!important' : '' }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2 mb-3">
                                <div class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:36px;height:36px;background:var(--wb-maroon-light)">
                                    <i class="ti ti-building" style="color:var(--wb-maroon)"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold small text-truncate">{{ $orgEntity->name ?? 'Unknown Org' }}</div>
                                    @if ($orgEntity->org_type)
                                        <span class="badge bg-label-secondary" style="font-size:.62rem">{{ strtoupper($orgEntity->org_type) }}</span>
                                    @endif
                                    @if ($isCurrent)
                                        <span class="badge bg-label-success ms-1" style="font-size:.62rem">Current</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row g-2">
                                @foreach ($assignments as $assignment)
                                    @php $role = $assignment->role; @endphp
                                    @if ($role)
                                        <div class="col-12">
                                            <div class="role-card">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        @php
                                            $catBadge = match($role->category ?? '') {
                                                'Cross-Functional'       => 'XFN',
                                                'Design & Specification' => 'DSG',
                                                'Field Operations'       => 'FLD',
                                                'Manufacturer/Seller'    => 'MFR',
                                                'Organization'           => 'ORG',
                                                'Platform'               => 'PLT',
                                                'Procurement'            => 'PRC',
                                                'Project Operations'     => 'PRJ',
                                                default                  => strtoupper(substr($role->category ?? 'GEN', 0, 3)),
                                            };
                                        @endphp
                                        <span class="role-tag-generic">{{ $catBadge }}</span>
                                                        <div class="fw-semibold small mt-1">{{ $role->name }}</div>
                                                    </div>
                                                </div>
                                                <small class="text-muted" style="font-size:.72rem">
                                                    Assigned by {{ optional($assignment->assignedBy)->name ?? 'System' }}
                                                    @if ($assignment->assigned_at)
                                                        on {{ $assignment->assigned_at->format('M d, Y') }}
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="text-muted small mt-4">
        <i class="ti ti-info-circle me-1"></i>
        Showing all roles across all organizations you belong to. Roles in the current org (<strong>{{ $currentOrg->name }}</strong>) are highlighted.
    </p>
</div>
@endsection

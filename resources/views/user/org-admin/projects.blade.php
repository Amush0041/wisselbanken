@extends('user.layouts.app')

@section('seo')
<title>Projects | {{ env('APP_NAME','Wisselbanken') }}</title>
@endsection

@section('content')
<style>
    :root { --wb-maroon: #6b1c1c; --wb-maroon-light: rgba(107,28,28,.08); }
    .oa-nav a { display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:.5rem;font-size:.84rem;font-weight:500;text-decoration:none;color:#495057;transition:background .12s,color .12s; }
    .oa-nav a:hover { background:var(--wb-maroon-light);color:var(--wb-maroon); }
    .oa-nav a.active { background:var(--wb-maroon);color:#fff; }
    .project-card { border:1px solid #eee;border-radius:.75rem;padding:1rem;background:#fff;margin-bottom:.75rem; }
    .member-chip { display:inline-flex;align-items:center;gap:.35rem;background:var(--wb-maroon-light);border-radius:2rem;padding:.2rem .6rem .2rem .35rem;font-size:.76rem;font-weight:500; }
    .member-chip form { display:inline; }
    .member-chip button { background:none;border:none;padding:0;line-height:1;color:#6b1c1c;cursor:pointer;font-size:.7rem; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="mb-1">
        <h4 class="mb-0 fw-bold">Project Access</h4>
        <p class="text-muted small mb-0">Control which team members can access each project in <strong>{{ $org->name }}</strong></p>
    </div>

    @include('user.org-admin._nav')

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($projects->isEmpty())
        <div class="card border-0 shadow-sm text-center py-5 text-muted">
            <i class="ti ti-folders fs-2 d-block mb-2"></i>
            <p class="mb-1">No projects found in this organization.</p>
            <small>Projects created by org members will appear here.</small>
        </div>
    @else
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <p class="small text-muted mb-0">
                    <i class="ti ti-info-circle me-1"></i>
                    Project membership controls project-level access. Only listed members can open a project, in addition to holding the required role permission.
                </p>
            </div>
        </div>

        @foreach ($projects as $project)
            <div class="project-card">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold">{{ $project->name }}</span>
                            <span class="badge bg-label-secondary" style="font-size:.65rem">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                        </div>
                        <div class="small text-muted mt-1">
                            {{ $project->quotes_count }} estimate(s)
                            &nbsp;·&nbsp; Created {{ $project->created_at->format('M d, Y') }}
                        </div>

                        <div class="mt-2 d-flex align-items-center gap-1 flex-wrap">
                            @forelse ($project->project_members_list as $pm)
                                <span class="member-chip">
                                    <i class="ti ti-user" style="font-size:.7rem;color:var(--wb-maroon)"></i>
                                    {{ optional($pm->user)->name ?? 'Unknown' }}
                                    @if ($canManageProjects)
                                    <form action="{{ route('org-admin.projects.members.destroy', $pm->id) }}" method="POST"
                                          onsubmit="return confirm('Remove this member from the project?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Remove"><i class="ti ti-x"></i></button>
                                    </form>
                                    @endif
                                </span>
                            @empty
                                <span class="small text-muted fst-italic">No project members assigned</span>
                            @endforelse
                        </div>
                    </div>

                    @if ($canManageProjects)
                    <div class="flex-shrink-0" style="min-width:220px">
                        <form action="{{ route('org-admin.projects.members.store') }}" method="POST"
                              class="d-flex gap-1 align-items-center">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $project->id }}">
                            <select name="user_id" class="form-select form-select-sm" required style="font-size:.78rem">
                                <option value="">Add member…</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm" style="background:var(--wb-maroon);color:#fff;white-space:nowrap">
                                <i class="ti ti-plus"></i>
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection

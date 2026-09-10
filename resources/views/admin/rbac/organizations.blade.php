@extends('admin.layouts.app')

@section('seo')
<title>RBAC Organizations | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="mb-3">RBAC Management</h4>
    @include('admin.rbac._nav')

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Organizations</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Type</th><th>Team size</th><th>Active assignments</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($organizations as $org)
                            <tr>
                                <td>{{ $org->id }}</td>
                                <td><strong>{{ $org->name }}</strong></td>
                                <td>{{ $org->org_type ? ucwords(str_replace('_',' ',$org->org_type)) : '—' }}</td>
                                <td>{{ $org->team_size ?? '—' }}</td>
                                <td><span class="badge bg-label-info">{{ $org->active_assignments_count }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('admin.rbac.organizations.show', $org->id) }}" class="btn btn-sm btn-outline-primary">Manage</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No organizations yet. They are created when users register.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $organizations->links() }}
        </div>
    </div>
</div>
@endsection

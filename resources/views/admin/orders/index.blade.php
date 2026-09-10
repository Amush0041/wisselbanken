@extends('admin.layouts.app') 
@section('content')
<div class="container-fluid py-4">
    <h3 class="mb-4">Orders</h3>
    <div class="card">
        <div class="card-body table-responsive">
            {!! $dataTable->table(['class' => 'table table-bordered table-hover w-100', 'id' => 'orders-table']) !!}
        </div>
    </div>
</div>
@endsection
@push('scripts') 
{!! $dataTable->scripts() !!}
@endpush 
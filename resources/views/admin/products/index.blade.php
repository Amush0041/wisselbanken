@extends('admin.layouts.app')
@section('seo')
<title> Products | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Product">
        <!-- Product List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Products
                    <!-- Add Button -->
                    <a style="float: right;" href="javascript:void(0)"  data-bs-toggle="offcanvas" data-bs-target="#offcanvasProductImport" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light">
                        <span><i class="ti ti-file-import ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Import Product</span>
                        </span>
                    </a>
                    <!-- Add Button -->
                    <a style="float: right;" href="{{route('products.create')}}" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Product</span>
                        </span>
                    </a>

                </h4>

            </div>
            <div class="card-body">
                @if(Session::has("status"))
                @if(session('status') == 'success')
                <div class="alert alert-success" role="alert">
                    <div class="alert-body">
                        {{ session()->get('message') }}
                    </div>
                </div>
                @endif
                @if(session('status') == 'failure')
                <div class="alert alert-danger" role="alert">
                    <div class="alert-body">
                        {{ session()->get('message') }}
                    </div>
                </div>
                @endif
                @endif

                @if(session('error'))
                <div class="alert alert-danger" role="alert">
                    <div class="alert-body">
                        <strong>{{ session('error') }}</strong>
                        @if(session('error_details'))
                        <ul class="mt-2 mb-0" style="padding-left: 20px;">
                            @foreach(session('error_details') as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        @endif
                    </div>
                </div>
                @endif

                @if(session('success'))
                <div class="alert alert-success" role="alert">
                    <div class="alert-body">
                        {{ session('success') }}
                    </div>
                </div>
                @endif

                <div class="card-datatable table-responsive">

                    {{ $dataTable->table() }}
                </div>
                <!-- Offcanvas Form for Import Size -->
                <div class="offcanvas offcanvas-end" id="offcanvasProductImport">
                    <div class="offcanvas-header py-6">
                        <h5 class="offcanvas-title">Import Products</h5>
                        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
                    </div>
                    <div class="offcanvas-body border-top">
                        <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="file" class="form-label">Choose File ( <a href="{{asset('files/product_sample_file.csv')}}" class="text-primary">click to see sample file</a>)</label>
                                <input type="file" name="file" accept=".xlsx, .xls, .csv" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Import Products</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{ $dataTable->scripts() }}

<script>
    $(document).ready(function() {
        let table = $('#product-table').DataTable();
 
        // Pass Header Token
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': "{{csrf_token()}}"
            }
        });


        // Delete Product with SweetAlert
        $('body').on('click', '.deleteProduct', function() {
            let id = $(this).data('id');

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                customClass: {
                    confirmButton: 'btn btn-primary me-3 waves-effect waves-light',
                    cancelButton: 'btn btn-label-secondary waves-effect waves-light'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    $.ajax({
                        type: "DELETE",
                        url: "{{ route('products.destroy', ':id') }}".replace(':id', id),
                        success: function(response) {
                            table.ajax.reload();
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: response.message,
                                customClass: {
                                    confirmButton: 'btn btn-success waves-effect waves-light'
                                }
                            });
                        }
                    });

                }
            });
        });
    });
</script>
@endpush
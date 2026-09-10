@extends('admin.layouts.app')
@section('seo')
<title> Manufactureres | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Manufacturer">
        <!-- Manufacturer List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Manufacturers 
                </h4>
            </div>
            <div class="card-body">
                <div class="card-datatable table-responsive">
                    <!-- Add Button -->
                    <button id="addManufacturerButton" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddManufacturer">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Manufacturer</span>
                        </span>
                    </button>
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>

        <!-- Offcanvas Form for Add Manufacturer -->
        <div class="offcanvas offcanvas-end" id="offcanvasAddManufacturer">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleAdd">Add Manufacturer</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="ManufacturerFormAdd">
                    @csrf
                    <input type="hidden" id="Manufacturer_id_add" name="id">

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="name_add">
                        <span class="text-danger error-text name_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" id="status_add">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <span class="text-danger error-text status_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" id="image_add" accept="image/*">
                        <span class="text-danger error-text image_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <button id="saveBtnAdd" type="submit" class="btn btn-primary">Add</button>
                        <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">Discard</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Offcanvas Form for Edit Manufacturer -->
        <div class="offcanvas offcanvas-end" id="offcanvasEditManufacturer">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleEdit">Edit Manufacturer</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="ManufacturerFormEdit">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="Manufacturer_id_edit" name="id">
                    

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="name_edit">
                        <span class="text-danger error-text name_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" id="status_edit">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <span class="text-danger error-text status_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" id="image_edit" accept="image/*">
                        <span class="text-danger error-text image_error_edit"></span>
                        <div id="current_image_edit" class="mt-2"></div>
                    </div>

                    <div class="mb-3">
                        <button id="saveBtnEdit" type="submit" class="btn btn-primary">Update</button>
                        <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">Discard</button>
                    </div>
                </form>
            </div>
        </div>

       
    </div>
</div>
@endsection

@push('scripts')
{{ $dataTable->scripts() }}

<script>
$(document).ready(function () {
    let table = $('#manufacturer-table').DataTable();

    // Move Add Button Near Search Bar
    setTimeout(() => {
        $("#addManufacturerButton").appendTo(".dataTables_filter");
    }, 500);

    // Pass Header Token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': "{{csrf_token()}}"
        }
    });

    // Submit Add Form
    $('#ManufacturerFormAdd').on('submit', function (e) {
        e.preventDefault();
        $('#saveBtnAdd').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('manufacturers.store') }}";

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#saveBtnAdd').prop('disabled', false).html('Add');
                $('#ManufacturerFormAdd')[0].reset();
                $('#offcanvasAddManufacturer').offcanvas('hide');
                table.ajax.reload();
                Swal.fire({
                    title: 'Success',
                    text: response.message,
                    icon: 'success',
                    customClass: {
                    confirmButton: 'btn btn-primary waves-effect waves-light'
                    },
                    buttonsStyling: false
                });
            },
            error: function (xhr) {
                $('#saveBtnAdd').prop('disabled', false).html('Add');
                let errors = xhr.responseJSON.errors;
                $('.error-text').text('');
                $.each(errors, function (key, value) {
                    $('.' + key + '_error_add').text(value[0]);
                });
            }
        });
    });

    // Edit Manufacturer
    $('body').on('click', '.editManufacturer', function () {
        let id = $(this).data('id');
        $.get("{{ route('manufacturers.edit', ':id') }}".replace(':id', id), function (data) {
            $('#Manufacturer_id_edit').val(data.id);
            $('#name_edit').val(data.name);
            $('#status_edit').val(data.status);
            if(data.image) {
                $('#current_image_edit').html('<img src="/storage/' + data.image + '" alt="Image" style="max-width:100px;">');
            } else {
                $('#current_image_edit').html('');
            }
            $('#offcanvasTitleEdit').text('Edit Manufacturer');
            $('#offcanvasEditManufacturer').offcanvas('show');
        });
    });

    // Submit Edit Form
    $('#ManufacturerFormEdit').on('submit', function (e) {
        e.preventDefault();

        $('#saveBtnEdit').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        formData.append('_token', "{{ csrf_token() }}");
        formData.append('_method', "PUT");
        let actionUrl = "{{ route('manufacturers.update', ':id') }}".replace(':id', $('#Manufacturer_id_edit').val());

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#saveBtnEdit').prop('disabled', false).html('Update');
                $('#ManufacturerFormEdit')[0].reset();
                $('#offcanvasEditManufacturer').offcanvas('hide');
                table.ajax.reload();
                Swal.fire({
                    title: 'Success',
                    text: response.message,
                    icon: 'success',
                    customClass: {
                    confirmButton: 'btn btn-primary waves-effect waves-light'
                    },
                    buttonsStyling: false
                });
            },
            error: function (xhr) {
                $('#saveBtnEdit').prop('disabled', false).html('Update');
                let errors = xhr.responseJSON.errors;
                $('.error-text').text('');
                $.each(errors, function (key, value) {
                    $('.' + key + '_error_edit').text(value[0]);
                });
            }
        });
    });

    // Delete Manufacturer with SweetAlert
    $('body').on('click', '.deleteManufacturer', function () {
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
        }).then(function (result) {
            if (result.value) {
                $.ajax({
                    type: "DELETE",
                    url: "{{ route('manufacturers.destroy', ':id') }}".replace(':id', id),
                    success: function (response) {
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

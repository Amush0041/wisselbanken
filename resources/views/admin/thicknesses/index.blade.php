@extends('admin.layouts.app')

@section('seo')
<title> Thickness | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Thickness">
        <!-- Thickness List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Thicknesses 
                </h4>
            </div>
            <div class="card-body">
                <div class="card-datatable table-responsive">
                    <!-- Add Button -->
                    <button id="addThicknessButton" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddThickness">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Thickness</span>
                        </span>
                    </button>
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>

        <!-- Offcanvas Form for Add Thickness -->
        <div class="offcanvas offcanvas-end" id="offcanvasAddThickness">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleAdd">Add Thickness</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="thicknessFormAdd">
                    @csrf
                    <input type="hidden" id="thickness_id_add" name="id">

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="name_add">
                        <span class="text-danger error-text name_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control" id="slug_add">
                        <span class="text-danger error-text slug_error_add"></span>
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
                        <button id="saveBtnAdd" type="submit" class="btn btn-primary">Add</button>
                        <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">Discard</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Offcanvas Form for Edit Thickness -->
        <div class="offcanvas offcanvas-end" id="offcanvasEditThickness">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleEdit">Edit Thickness</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="thicknessFormEdit">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="thickness_id_edit" name="id">
                    

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="name_edit">
                        <span class="text-danger error-text name_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control" id="slug_edit">
                        <span class="text-danger error-text slug_error_edit"></span>
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
    let table = $('#thickness-table').DataTable();

    // Move Add Button Near Search Bar
    setTimeout(() => {
        $("#addThicknessButton").appendTo(".dataTables_filter");
    }, 500);

    // Generate Slug from Name
    $('#name_add').on('keyup', function () {
        let name = $(this).val();
        let slug = name.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
        $('#slug_add').val(slug);
    });

    // Pass Header Token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': "{{csrf_token()}}"
        }
    });

    // Submit Add Form
    $('#thicknessFormAdd').on('submit', function (e) {
        e.preventDefault();
        $('#saveBtnAdd').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('thicknesses.store') }}";

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#saveBtnAdd').prop('disabled', false).html('Add');
                $('#thicknessFormAdd')[0].reset();
                $('#offcanvasAddThickness').offcanvas('hide');
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

    // Edit Thickness
    $('body').on('click', '.editThickness', function () {
        let id = $(this).data('id');
        $.get("{{ route('thicknesses.edit', ':id') }}".replace(':id', id), function (data) {
            $('#thickness_id_edit').val(data.id);
            $('#name_edit').val(data.name);
            $('#slug_edit').val(data.slug); 
            $('#status_edit').val(data.status);
            $('#offcanvasTitleEdit').text('Edit Thickness');
            $('#offcanvasEditThickness').offcanvas('show');
        });
    });
    // Generate Slug from Name
    $('#name_edit').on('keyup', function () {
            let name = $(this).val();
            let slug = name.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
            $('#slug_edit').val(slug);
        });

    // Submit Edit Form
    $('#thicknessFormEdit').on('submit', function (e) {
        e.preventDefault();

        $('#saveBtnEdit').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('thicknesses.update', ':id') }}".replace(':id', $('#thickness_id_edit').val());

        $.ajax({
            type: "PUT",
            url: actionUrl,
            data: {
                    _token: "{{ csrf_token() }}",
                    _method: "PUT",
                    id: $('#thicknesses_id_edit').val(), 
                    name: $('#name_edit').val(),
                    slug: $('#slug_edit').val(),
                    status: $('#status_edit').val()
                },
            success: function (response) {
                $('#saveBtnEdit').prop('disabled', false).html('Update');
                $('#thicknessFormEdit')[0].reset();
                $('#offcanvasEditThickness').offcanvas('hide');
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

    // Delete Thickness with SweetAlert
    $('body').on('click', '.deleteThickness', function () {
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
                    url: "{{ route('thicknesses.destroy', ':id') }}".replace(':id', id),
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

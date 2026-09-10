@extends('admin.layouts.app')
@section('seo')
<title> Colors | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Colors">
        <!-- Paint Type List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Colors
                </h4>
            </div>
            <div class="card-body">
                <div class="card-datatable table-responsive">
                    <!-- Add Button -->
                    <button id="addColorButton" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddColor">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Color</span>
                        </span>
                    </button>
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>

        <!-- Offcanvas Form for Add Color -->
        <div class="offcanvas offcanvas-end" id="offcanvasAddColor">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleAdd">Add Color</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="colorFormAdd">
                    @csrf
                    <input type="hidden" id="color_id_add" name="id">
                    <div class="mb-3">
                        <label class="form-label">Paint Type</label>
                        <select name="paint_type_id" class="form-control" id="paint_types">
                            <option value="" selected disabled>Select Paint Type</option>
                            @foreach($paint_types as $paint_type)
                            <option value="{{$paint_type->id}}">{{$paint_type->name}}</option>
                            @endforeach
                        </select>
                        <span class="text-danger error-text status_error_add"></span>
                    </div>
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

        <!-- Offcanvas Form for Edit Color -->
        <div class="offcanvas offcanvas-end" id="offcanvasEditColor">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleEdit">Edit Color</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="ColorFormEdit">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="color_id_edit" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Paint Type</label>
                        <select name="paint_type_id" class="form-control" id="paint_type_edit">
                            <option value=""  disabled>Select Paint Type</option>
                            @foreach($paint_types as $paint_type)
                            <option value="{{$paint_type->id}}">{{$paint_type->name}}</option>
                            @endforeach
                        </select>
                        <span class="text-danger error-text status_error_add"></span>
                    </div>
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
    let table = $('#color-table').DataTable();

    // Move Add Button Near Search Bar
    setTimeout(() => {
        $("#addColorButton").appendTo(".dataTables_filter");
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
    $('#colorFormAdd').on('submit', function (e) {
        e.preventDefault();
        $('#saveBtnAdd').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('colors.store') }}";

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#saveBtnAdd').prop('disabled', false).html('Add');
                $('#colorFormAdd')[0].reset();
                $('#offcanvasAddColor').offcanvas('hide');
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

    // Edit Color
    $('body').on('click', '.editColor', function () {
        let id = $(this).data('id');
        $.get("{{ route('colors.edit', ':id') }}".replace(':id', id), function (data) {
            $('#color_id_edit').val(data.id);
            $('#name_edit').val(data.name);
            $('#paint_type_edit').val(data.paint_type_id);
            $('#slug_edit').val(data.slug); 
            $('#status_edit').val(data.status);
            $('#offcanvasTitleEdit').text('Edit Color');
            $('#offcanvasEditColor').offcanvas('show');
        });
    });
    // Generate Slug from Name
    $('#name_edit').on('keyup', function () {
            let name = $(this).val();
            let slug = name.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
            $('#slug_edit').val(slug);
        });

    // Submit Edit Form
    $('#ColorFormEdit').on('submit', function (e) {
        e.preventDefault();

        $('#saveBtnEdit').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('colors.update', ':id') }}".replace(':id', $('#color_id_edit').val());

        $.ajax({
            type: "PUT",
            url: actionUrl,
            data: {
                    _token: "{{ csrf_token() }}",
                    _method: "PUT",
                    id: $('#colors_id_edit').val(), 
                    paint_type_id: $('#paint_type_edit').val(),
                    name: $('#name_edit').val(),
                    slug: $('#slug_edit').val(),
                    status: $('#status_edit').val()
                },
            success: function (response) {
                $('#saveBtnEdit').prop('disabled', false).html('Update');
                $('#ColorFormEdit')[0].reset();
                $('#offcanvasEditColor').offcanvas('hide');
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

    // Delete Color with SweetAlert
    $('body').on('click', '.deleteColor', function () {
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
                    url: "{{ route('colors.destroy', ':id') }}".replace(':id', id),
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

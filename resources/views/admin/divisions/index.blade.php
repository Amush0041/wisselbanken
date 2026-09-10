@extends('admin.layouts.app')

@section('seo')
<title> Divisions | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Division">
        <!-- Division List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Divisions
                    <!-- Import Button -->
                    <button id="importDivisionBtn" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light float-end" data-bs-toggle="offcanvas" data-bs-target="#offcanvasImportDivision">
                        <span><i class="ti ti-file-import ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Import Division</span>
                        </span>
                    </button>
                </h4>
            </div>
            <div class="card-body">
                <div class="card-datatable table-responsive">
                    <!-- Add Button -->
                    <button id="addDivisionButton" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddDivision">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Division</span>
                        </span>
                    </button>
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>

        <!-- Offcanvas Form for Add Division -->
        <div class="offcanvas offcanvas-end" id="offcanvasAddDivision">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleAdd">Add Division</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="divisionFormAdd">
                    @csrf
                    <input type="hidden" id="division_id_add" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="number" name="code" class="form-control" id="code_add">
                        <span class="text-danger error-text code_error_add"></span>
                    </div>

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
                        <button id="saveBtnAdd" type="submit" class="btn btn-primary">Add</button>
                        <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">Discard</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Offcanvas Form for Edit Division -->
        <div class="offcanvas offcanvas-end" id="offcanvasEditDivision">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleEdit">Edit Division</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="divisionFormEdit">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="division_id_edit" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="number" name="code" class="form-control" id="code_edit">
                        <span class="text-danger error-text code_error_edit"></span>
                    </div>

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
                        <button id="saveBtnEdit" type="submit" class="btn btn-primary">Update</button>
                        <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">Discard</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Offcanvas Form for Import Division -->
        <div class="offcanvas offcanvas-end" id="offcanvasImportDivision">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title">Import Divisions</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form action="{{ route('divisions.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="file" class="form-label">Choose File ( <a href="{{asset('files/sample_division.xlsx')}}" class="text-primary">click to see sample file</a>)</label>
                        <input type="file" name="file" accept=".xlsx, .xls, .csv" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import Divisions</button>
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
    let table = $('#division-table').DataTable();

    // Move Add Button Near Search Bar
    setTimeout(() => {
        $("#addDivisionButton").appendTo(".dataTables_filter");
    }, 500);

    // Pass Header Token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': "{{csrf_token()}}"
        }
    });

    // Submit Add Form
    $('#divisionFormAdd').on('submit', function (e) {
        e.preventDefault();
        $('#saveBtnAdd').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('divisions.store') }}";

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#saveBtnAdd').prop('disabled', false).html('Add');
                $('#divisionFormAdd')[0].reset();
                $('#offcanvasAddDivision').offcanvas('hide');
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

    // Edit Division
    $('body').on('click', '.editDivision', function () {
        let id = $(this).data('id');
        $.get("{{ route('divisions.edit', ':id') }}".replace(':id', id), function (data) {
            $('#division_id_edit').val(data.id);
            $('#name_edit').val(data.name);
            $('#code_edit').val(data.code);
            $('#status_edit').val(data.status);
            $('#offcanvasTitleEdit').text('Edit Division');
            $('#offcanvasEditDivision').offcanvas('show');
        });
    });

    // Submit Edit Form
    $('#divisionFormEdit').on('submit', function (e) {
        e.preventDefault();

        $('#saveBtnEdit').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('divisions.update', ':id') }}".replace(':id', $('#division_id_edit').val());

        $.ajax({
            type: "PUT",
            url: actionUrl,
            data: {
                    _token: "{{ csrf_token() }}",
                    _method: "PUT",
                    id: $('#division_id_edit').val(),
                    code: $('#code_edit').val(),
                    name: $('#name_edit').val(),
                    status: $('#status_edit').val()
                },
            success: function (response) {
                $('#saveBtnEdit').prop('disabled', false).html('Update');
                $('#divisionFormEdit')[0].reset();
                $('#offcanvasEditDivision').offcanvas('hide');
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

    // Delete Division with SweetAlert
    $('body').on('click', '.deleteDivision', function () {
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
                    url: "{{ route('divisions.destroy', ':id') }}".replace(':id', id),
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

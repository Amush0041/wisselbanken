@extends('admin.layouts.app')
@section('seo')
<title>Specification | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Specification">
        <!-- Specification List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Specification</h4>
            </div>
            <div class="card-body">
                <div class="card-datatable table-responsive">
                    <!-- Add Button -->
                    <button id="addSpecificationButton" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddSpecification">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Specification</span>
                        </span>
                    </button>
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>

        <!-- Offcanvas Form for Add Specification -->
        <div class="offcanvas offcanvas-end" id="offcanvasAddSpecification">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleAdd">Add Specification</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="SpecificationFormAdd">
                    @csrf
                    <input type="hidden" id="Specification_id_add" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Division</label>
                        <select name="division_id" class="form-control select2" id="division_id_add"> 
                            @foreach($divisions as $division)
                            @if($division->code == 07) 
                            <option value="{{$division->id}}" selected>{{$division->code}} - {{$division->name}}</option>
                            @else
                            <option value="{{$division->id}}">{{$division->code}} - {{$division->name}}</option>
                            @endif
                            @endforeach
                        </select>
                        <span class="text-danger error-text division_id_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Specification Number</label>
                        <input type="text" name="specification_number" class="form-control" id="specification_number_add">
                        <span class="text-danger error-text specification_number_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Material Type</label>
                        <input type="text" name="material_type" class="form-control" id="material_type_add">
                        <span class="text-danger error-text material_type_error_add"></span>
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

        <!-- Offcanvas Form for Edit Specification -->
        <div class="offcanvas offcanvas-end" id="offcanvasEditSpecification">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleEdit">Edit Specification</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="SpecificationFormEdit">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="Specification_id_edit" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Division</label>
                        <select name="division_id" class="form-control select2" id="division_id_edit"> 
                            @foreach($divisions as $division)
                            <option value="{{$division->id}}">{{$division->code}} - {{$division->name}}</option>
                            @endforeach
                        </select>
                        <span class="text-danger error-text division_id_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Specification Number</label>
                        <input type="text" name="specification_number" class="form-control" id="specification_number_edit">
                        <span class="text-danger error-text specification_number_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Material Type</label>
                        <input type="text" name="material_type" class="form-control" id="material_type_edit">
                        <span class="text-danger error-text material_type_error_edit"></span>
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
   
    let table = $('#specification-table').DataTable();

    // Move Add Button Near Search Bar
    setTimeout(() => {
        $("#addSpecificationButton").appendTo(".dataTables_filter");
    }, 500);

    // Generate Slug from Name
     // Initialize select2 on show
     $('#offcanvasAddSpecification').on('shown.bs.offcanvas', function () {
       
        var select2 = $('#division_id_add');
        if (select2.length) {
            select2.each(function () {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>').select2({
                    placeholder: 'Select Division',
                    dropdownParent: $this.parent()
                });
            });
        }

    });

    $('#offcanvasEditSpecification').on('shown.bs.offcanvas', function () {
        var select2 = $('#division_id_edit');
        if (select2.length) {
            select2.each(function () {
            var $this = $(this);
            $this.wrap('<div class="position-relative"></div>').select2({
                    placeholder: 'Select Division',
                    dropdownParent: $this.parent()
                });
            });
        }
        
    });

    // Pass Header Token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': "{{csrf_token()}}"
        }
    });

    // Submit Add Form
    $('#SpecificationFormAdd').on('submit', function (e) {
        e.preventDefault();
        $('#saveBtnAdd').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('specifications.store') }}";

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#saveBtnAdd').prop('disabled', false).html('Add');
                $('#SpecificationFormAdd')[0].reset();
                $('#offcanvasAddSpecification').offcanvas('hide');
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

    // Edit Specification
    $('body').on('click', '.editSpecification', function () {
        let id = $(this).data('id');
        
        $.get("{{ route('specifications.edit', ':id') }}".replace(':id', id), function (data) {
            $('#Specification_id_edit').val(data.id);
            $('#specification_number_edit').val(data.specification_number);
            $('#material_type_edit').val(data.material_type);
            $('#division_id_edit').val(data.division_id);
            $('#status_edit').val(data.status);
            $('#offcanvasTitleEdit').text('Edit Specification');
            $('#offcanvasEditSpecification').offcanvas('show');
        });
    });
 

    // Submit Edit Form
    $('#SpecificationFormEdit').on('submit', function (e) {
        e.preventDefault();

        $('#saveBtnEdit').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('specifications.update', ':id') }}".replace(':id', $('#Specification_id_edit').val());

        $.ajax({
            type: "PUT",
            url: actionUrl,
            data: {
                    _token: "{{ csrf_token() }}",
                    _method: "PUT",
                    id: $('#Specification_id_edit').val(),
                    division_id: $('#division_id_edit').val(),
                    specification_number: $('#specification_number_edit').val(),
                    material_type: $('#material_type_edit').val(),
                    status: $('#status_edit').val()
                }, 
            success: function (response) {
                $('#saveBtnEdit').prop('disabled', false).html('Update');
                $('#SpecificationFormEdit')[0].reset();
                $('#offcanvasEditSpecification').offcanvas('hide');  
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

    // Delete Specification with SweetAlert
    $('body').on('click', '.deleteSpecification', function () {
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
                    url: "{{ route('specifications.destroy', ':id') }}".replace(':id', id),
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

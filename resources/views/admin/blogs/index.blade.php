@extends('admin.layouts.app')
@section('seo')
<title>Blog | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Blog">
        <!-- Blog List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Blog</h4>
            </div>
            <div class="card-body">
                <div class="card-datatable table-responsive">
                    <!-- Add Button -->
                    <button id="addBlogButton" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddBlog">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Blog</span>
                        </span>
                    </button>
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>

        <!-- Offcanvas Form for Add Blog -->
        <div class="offcanvas offcanvas-end" id="offcanvasAddBlog">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleAdd">Add Blog</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="BlogFormAdd" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="Blog_id_add" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" id="title_add" required>
                        <span class="text-danger error-text title_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" id="content_add" rows="10" required></textarea>
                        <span class="text-danger error-text content_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Featured Image</label>
                        <input type="file" name="featured_image" class="form-control" id="featured_image_add" accept="image/*">
                        <span class="text-danger error-text featured_image_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" class="form-control" id="meta_description_add" rows="3"></textarea>
                        <span class="text-danger error-text meta_description_error_add"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-control" id="meta_keywords_add" placeholder="keyword1, keyword2, keyword3">
                        <span class="text-danger error-text meta_keywords_error_add"></span>
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

        <!-- Offcanvas Form for Edit Blog -->
        <div class="offcanvas offcanvas-end" id="offcanvasEditBlog">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleEdit">Edit Blog</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="BlogFormEdit" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="Blog_id_edit" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" id="title_edit" required>
                        <span class="text-danger error-text title_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" id="content_edit" rows="10" required></textarea>
                        <span class="text-danger error-text content_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Featured Image</label>
                        <input type="file" name="featured_image" class="form-control" id="featured_image_edit" accept="image/*">
                        <div id="current_image_edit" class="mt-2"></div>
                        <span class="text-danger error-text featured_image_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" class="form-control" id="meta_description_edit" rows="3"></textarea>
                        <span class="text-danger error-text meta_description_error_edit"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-control" id="meta_keywords_edit" placeholder="keyword1, keyword2, keyword3">
                        <span class="text-danger error-text meta_keywords_error_edit"></span>
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
   
    let table = $('#blog-table').DataTable();

    // Move Add Button Near Search Bar
    setTimeout(() => {
        $("#addBlogButton").appendTo(".dataTables_filter");
    }, 500);

    // Pass Header Token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': "{{csrf_token()}}"
        }
    });

    // Submit Add Form
    $('#BlogFormAdd').on('submit', function (e) {
        e.preventDefault();
        $('#saveBtnAdd').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('admin.blogs.store') }}";

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                $('#saveBtnAdd').prop('disabled', false).html('Add');
                $('#BlogFormAdd')[0].reset();
                $('#offcanvasAddBlog').offcanvas('hide');
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

    // Edit Blog
    $('body').on('click', '.editBlog', function () {
        let id = $(this).data('id');
        
        $.get("{{ route('admin.blogs.edit', ':id') }}".replace(':id', id), function (data) {
            $('#Blog_id_edit').val(data.id);
            $('#title_edit').val(data.title);
            $('#content_edit').val(data.content);
            $('#meta_description_edit').val(data.meta_description);
            $('#meta_keywords_edit').val(data.meta_keywords);
            $('#status_edit').val(data.status);
            
            // Show current image if exists
            if (data.featured_image) {
                $('#current_image_edit').html('<img src="{{ asset("storage") }}/' + data.featured_image + '" style="max-width: 200px; border-radius: 4px;" class="mb-2"><br><small>Current Image</small>');
            } else {
                $('#current_image_edit').html('');
            }
            
            $('#offcanvasTitleEdit').text('Edit Blog');
            $('#offcanvasEditBlog').offcanvas('show');
        });
    });
 

    // Submit Edit Form
    $('#BlogFormEdit').on('submit', function (e) {
        e.preventDefault();

        $('#saveBtnEdit').prop('disabled', true).html('Sending...');
        let formData = new FormData(this);
        let actionUrl = "{{ route('admin.blogs.update', ':id') }}".replace(':id', $('#Blog_id_edit').val());

        $.ajax({
            type: "POST",
            url: actionUrl,
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-HTTP-Method-Override': 'PUT'
            },
            success: function (response) {
                $('#saveBtnEdit').prop('disabled', false).html('Update');
                $('#BlogFormEdit')[0].reset();
                $('#current_image_edit').html('');
                $('#offcanvasEditBlog').offcanvas('hide');  
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

    // Delete Blog with SweetAlert
    $('body').on('click', '.deleteBlog', function () {
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
                    url: "{{ route('admin.blogs.destroy', ':id') }}".replace(':id', id),
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


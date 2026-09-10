@extends('admin.layouts.app')

@section('seo')
<title>Product Files | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection
@push('css')
<link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.2/dropzone.min.css" rel="stylesheet">
<style>
    .dropzone {
        min-height: 150px;
        border: 2px dotted rgba(0, 0, 0, .3);
        background: #fff;
        border-radius: 3px;
        padding: 20px 20px;
    }

    td a.badge {
        display: inline-block;
        max-width: 100%;
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .bg-primary {
        background-color: #4a171e !important;
    }
</style>
@endpush
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-Product">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
            <h4 class="mb-1">{{ $product->name ?? '' }}</h4>
            <a href="{{ route('products.index') }}" class="btn btn-outline-danger"><i class="fas fa-arrow-left pr-2"></i> Back</a>
        </div>

        <div class="row">
            <div class="col-md-12">
                <form action="" method="POST" enctype="multipart/form-data">
                    @csrf
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
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Upload Product Files</h4>
                                </div>
                                <div class="card-body row">
                                    <input type="hidden" id="product_id" name="product_id" class="form-control" value="{{ $product_id }}" />
                                    <div class="form-group col-md-2">

                                        <div class="form-group">
                                            <label class="form-label" for="type">Select Literature <span class="text-danger">*</span></label>
                                            <select type="select" id="type" name="type" class="form-control">
                                                <option value="brochure" @selected(old('type'))>Brochure</option>
                                                <option value="color_chart" @selected(old('color_chart'))>Color Chart</option>
                                                <option value="data_sheet" @selected(old('type'))>Data sheet</option>
                                                <option value="testing" @selected(old('type'))>Testing</option>
                                                <option value="manuals" @selected(old('type'))>Manuals</option>
                                                <option value="cad_pdf" @selected(old('type'))>CAD (PDF)</option>
                                                <option value="cad_dwg" @selected(old('type'))>CAD (DWG)</option>
                                                <option value="bim" @selected(old('type'))>Bim</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-10">
                                        <label class="form-label" for="product_description">Upload Here <span class="text-danger">*</span></label>
                                        <div class="needsclick dropzone" id="document-dropzone">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </form>

            </div>
        </div>

        <div class="row">
            @if(count($results) > 0)
            @foreach ($results as $type => $products)
            <div class="col-12 col-sm-12 col-lg-12 mb-6" style="margin-top: 20px;">
                <div class="card">
                    <div class="card-header">
                        <h5>{{$type ?? ''}}</h5>
                    </div>
                    <div class="card-body">
                        <ul class="p-0 m-0">
                            @foreach ($products as $product)
                            <li id='item-{{ $product['id'] }}' class="mb-2 mt-2 d-flex justify-content-between align-items-center">
                                <div class="badge bg-label-success rounded">
                                    <a href="{{ asset($product['path']) }}" target="_blank"><i class="fas fa-file text-success"></i> {{ $product['name'] }}</a>
                                </div>
                                <div class="d-flex justify-content-between w-100 flex-wrap">
                                    <h6 class="mb-0 ms-4 mt-1"></h6>
                                    <div class="d-flex">
                                        <p class="mb-0">
                                            <a type="button" class="deleteRecord" data-id="{{ $product['id'] }}"><i class="fas fa-trash text-danger"></i></a>
                                        </p>
                                    </div>
                                </div>
                            </li>
                            <hr style="margin-top: 0px; margin-bottom: 0px" />
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endforeach
            @else
            <div class="col-12 col-sm-12 col-lg-12 mb-12" style="    margin-top: 20px;">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="text-center text-danger">No file uploaded yet</h2>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.2/min/dropzone.min.js"></script>
<script>
    var uploadedDocumentMap = {};
    Dropzone.options.documentDropzone = {
        url: '{{ route("product-files.store") }}',
        maxFilesize: 5, // MB
        addRemoveLinks: true,
        headers: {
            'X-CSRF-TOKEN': "{{ csrf_token() }}"
        },
        init: function() {
            this.on("sending", function(file, xhr, formData) {
                var selectedType = document.querySelector("select[name='type']").value;
                formData.append('type', selectedType);
                formData.append('product_id', $('#product_id').val());
            });

            this.on("success", function(file, response) {
                $('form').append('<input type="hidden" name="document[]" value="' + response.name + '">');
                uploadedDocumentMap[file.name] = response.name;
                if (response.status) {
                    toastr.success(response.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    toastr.error(response.message);
                }

            });

            this.on("removedfile", function(file) {
                file.previewElement.remove();
                var name = '';
                if (typeof file.file_name !== 'undefined') {
                    name = file.file_name;
                } else {
                    name = uploadedDocumentMap[file.name];
                }
                $('form').find('input[name="document[]"][value="' + name + '"]').remove();
            });

            @if(isset($project) && $project->document)
            var files = {
                !!json_encode($project->document) !!
            };
            for (var i in files) {
                var file = files[i];
                this.options.addedfile.call(this, file);
                file.previewElement.classList.add('dz-complete');
                $('form').append('<input type="hidden" name="document[]" value="' + file.file_name + '">');
            }
            @endif
        }
    };
</script>
<script>
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            }
        });
        $('.deleteRecord').click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (confirm("Are you sure you want to delete this record?")) {
                $.ajax({
                    url: '/admin/product-files/' + id,
                    type: 'DELETE',
                    success: function(response) {
                        toastr.success(response.success);
                        $('#item-' + id).remove();
                    },
                    error: function(xhr) {
                        alert('An error occurred while deleting the record.');
                    }
                });
            }
        });
    });
</script>

@endpush
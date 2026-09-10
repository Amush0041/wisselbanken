@extends('frontend.layouts.app')
@push('seo')
<title> {{$product->name ?? ''}} | {{env('APP_NAME','Wisselbanken')}}</title>
@endpush
@push('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    .product-title {
        font-weight: bold;
        color: #343a40;
        text-align: left;
    }

    .text-muted {
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .hm-product-detail table th,
    .hm-product-detail table td {
        border: none;
    }

    .hm-product-detail .table th,
    .hm-product-detail .table td {
        font-size: 14px !important;
        padding: 10px !important;
        border: none;
        vertical-align: top;
        border-bottom: 1px solid #cccccc;
    }

    .basic-details table th,
    .basic-details table td {
        border: none !important;
        padding: 14px !important;
    }

    .basic-details table th {
        width: 25%;
        text-align: left;
        font-weight: bold;
    }

    .product-attributes table th {
        text-align: left;
    }

    .product-attributes table th,
    .product-attributes table td {
        border: none !important;
        border-bottom: 1px solid #cccccc !important;
    }


    .card {
        background-color: #f6f5f5;
        border: none;
        box-shadow: 0px 4px 16px 0px rgba(0, 0, 0, 0.305);
        border-radius: 0;
        padding: 10px;
        margin-bottom: 20px;
    }



    td a.badge {
        display: inline-block;
        max-width: 100%;
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .color-names {
        display: flex;
        flex-wrap: wrap;
        gap: 2px 6px;
        white-space: normal;
        overflow-x: visible !important;
        max-width: 100%;
    }

    .color-names .badge {
        word-break: break-word;
        overflow-wrap: break-word;
        white-space: normal !important;
        max-width: 100px;
        display: inline-block;
    }

    .color-names-toggle {
        display: inline-block;
        color: #007bff;
        cursor: pointer;
        margin-left: 8px;
        font-size: 0.95em;
    }
    
    /* Toast container styles */
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast {
        margin-bottom: 10px;
    }
    .btn-outline-warning{
        color: #582020 !important;
    }
    /* SweetAlert2 custom styles */
    .swal2-popup.custom-swal {
        border-radius: 18px !important;
        padding: 32px 24px 24px 24px !important;
        background: #f8f9fa !important;
        box-shadow: 0 8px 32px rgba(44,88,160,0.12);
    }
    .swal2-confirm.site-primary {
        background: #582020 !important;
        color: #fff !important;
        border-radius: 6px !important;
        font-weight: 600;
        padding: 8px 32px;
        border: none;
    }
    .swal2-cancel.site-cancel {
        background: #D4A020 !important;
        color: #fff !important;
        border-radius: 6px !important;
        font-weight: 600;
        padding: 8px 32px;
        border: none;
        margin-left: 8px;
    }
    .swal2-input, .swal2-select {
        border-radius: 6px !important;
        padding: 10px 12px !important;
        font-size: 15px !important;
        margin-bottom: 12px !important;
        background: #fff !important;
        border: 1px solid #ccc !important;
    }
    .swal2-actions {
        margin-top: 18px !important;
    }
</style>
@endpush
@section('content')
<main class="main" style="background-color: #f5f5f5;">
    <div id="toast-container"></div>
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{url('/')}}" rel="nofollow">Home</a>
                <span></span>
                <a href="{{url('product-filter')}}" rel="nofollow">Products</a>
                <span></span> {{$product->name}}
            </div>
        </div>
    </div>
    <section class="mt-50 mb-50">
        <div class="container-fluid px-4">

            <div class="hm-product-detail ">
                <div class="mt-5">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card basic-details">

                                <div class="row">
                                    <!-- Image Section -->
                                    <div class="col-md-4 mt-2 text-center">
                                        <img src="{{ asset($product->feature_image) }}" onerror="this.onerror=null; this.src='{{ asset('demo.jpg') }}';" alt="{{ $product->name ?? '' }}" class="img-fluid">
                                    </div>
                                    <!-- Details Section -->
                                    <div class="col-md-8">

                                        <h3 class="product-title mt-2">{{$product->name ?? ''}}</h3>
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <th>Divisions:</th>
                                                    <td>{{ $product->division ? $product->division->code : ''}} - {{ $product->division ? $product->division->name : '' }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Specification</th>
                                                    <td>{{$product->specification?->specification_number }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Material Type</th>
                                                    <td>{{$product->specification ? $product->specification->material_type : ''}}</td>
                                                </tr>
                                                <tr>
                                                    <th>Manufacturer</th>
                                                    <td>{{ ucfirst(Str::camel($product->manufacturer?->name )) }}</td>
                                                </tr>

                                                <div id="addtoList"></div>
                                            </tbody>
                                        </table>

                                    </div>

                                    @if(isset($product->product_description))
                                    <div class="col-md-12">

                                        <hr>
                                        {!! $product->product_description !!}
                                    </div>

                                    @endif
                                </div>
                            </div> <!-- Closing div for basic-details -->
                            <h4 class="fw-bold mt-4 mb-4">Product Attributes</h4>

                            <div class="card product-attributes">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" style="width: 40%; vertical-align: top; text-align: start;">TYPE</th>
                                                <th scope="col" style="width: 60%;">DESCRIPTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Size Row -->
                                            <tr>
                                                <th>Size</th>
                                                <td>
                                                    @php
                                                    $sizes = $product->variations->pluck('size.name')->unique()->implode(', ');
                                                    @endphp
                                                    {{ $sizes ?? 'N/A' }}
                                                </td>
                                            </tr>

                                            <!-- Thickness Row -->
                                            <tr>
                                                <th>Thickness</th>
                                                <td>
                                                    @php
                                                    $thicknesses = $product->variations->pluck('thickness.name')->unique()->implode(', ');
                                                    @endphp
                                                    {{ $thicknesses ?? 'N/A' }}
                                                </td>
                                            </tr>

                                            <!-- Paint Type Row -->
                                            <tr>
                                                <th>Paint Type</th>
                                                <td>
                                                    @php
                                                    $paintTypes = $product->variations->pluck('paint_type.name')->unique()->implode(', ');
                                                    @endphp
                                                    {{ $paintTypes ?? 'N/A' }}
                                                </td>
                                            </tr>

                                            <!-- Finish Row -->
                                            <tr>
                                                <th>Finish</th>
                                                <td>
                                                    @php
                                                    $finishes = $product->variations->pluck('finish.name')->unique()->implode(', ');
                                                    @endphp
                                                    {{ $finishes ?? 'N/A' }}
                                                </td>
                                            </tr>

                                            <!-- Color Effect Row -->
                                            <tr>
                                                <th>Color Effect</th>
                                                <td>
                                                    @php
                                                    $colorEffects = $product->variations->pluck('color_effect.name')->unique()->implode(', ');
                                                    @endphp
                                                    {{ $colorEffects ?? 'N/A' }}
                                                </td>
                                            </tr>

                                            <!-- Colors Row -->
                                            <tr>
                                                <th>Colors</th>
                                                <td>
                                                    @php
                                                    // Initialize an empty array to store color names
                                                    $allColorNames = [];
                                                    // Collect all color names from variations and store them in the array
                                                    $product->variations->flatMap(function ($variation) use (&$allColorNames) {
                                                    $allColorNames = array_merge($allColorNames, $variation->colors->pluck('name')->toArray());
                                                    });
                                                    $uniqueColors = array_unique($allColorNames);
                                                    $maxToShow = 5;
                                                    @endphp
                                                    <span class="color-names" id="color-names-list">
                                                        @foreach(array_slice($uniqueColors, 0, $maxToShow) as $color)
                                                        <span class="badge bg-primary me-1 mb-1">{{ $color }}</span>
                                                        @endforeach
                                                        @if(count($uniqueColors) > $maxToShow)
                                                        <span id="more-colors" style="display:none;">
                                                            @foreach(array_slice($uniqueColors, $maxToShow) as $color)
                                                            <span class="badge bg-primary me-1 mb-1">{{ $color }}</span>
                                                            @endforeach
                                                        </span>
                                                        <span class="color-names-toggle" id="show-more-colors" onclick="toggleColors(true)">Show more</span>
                                                        <span class="color-names-toggle" id="show-less-colors" style="display:none;" onclick="toggleColors(false)">Show less</span>
                                                        @endif
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                </div>
                            </div>


                            <h4 class="fw-bold mt-4 mb-4">Product Files</h4>
                            @if(count($product_files) > 0)
                            <div class="card product-attributes">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" style="width: 40%; vertical-align: top; text-align: start;">TYPE</th>
                                                <th scope="col" style="width: 60%;">DESCRIPTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($product_files as $type => $files)
                                            <tr>
                                                <th>{{ $type ?? '' }}</th>
                                                <td>
                                                    @foreach ($files as $file)
                                                    <span class="badge bg-lg bg-primary">
                                                        @php
                                                        $icons = [
                                                        'jpg' => 'fas fa-file-image',
                                                        'jpeg' => 'fas fa-file-image',
                                                        'png' => 'fas fa-file-image',
                                                        'pdf' => 'fas fa-file-pdf',
                                                        'doc' => 'fas fa-file-word',
                                                        'docx' => 'fas fa-file-word',
                                                        'xls' => 'fas fa-file-excel',
                                                        'xlsx' => 'fas fa-file-excel',
                                                        ];
                                                        $icon = $icons[$file['extension']] ?? 'fas fa-file text-secondary';
                                                        @endphp
                                                        <a href="{{ asset($file['path']) }}" class="text-primary" style="color:white !important;" target="_blank">
                                                            <i class="{{ $icon }}"></i> {{ $file['name'] }}
                                                        </a>
                                                    </span>
                                                    @endforeach
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @else
                            <div class="card product-attributes">
                                <div class="text-muted mb-4 p-3">No files available for this product.</div>
                            </div>
                            @endif

                        </div> <!-- Closing div for col-md-7 -->

                        <div class="col-md-5">
                            <div class="card shadow-lg p-4 rounded">
                                <div class="d-flex justify-content-between align-item-center mb-3">
                                    <div class="text-muted">Add item to Pallet</div>
                                </div>
                                <div class="filter-note" id="filter-note">
                                    <p>
                                        <strong>Note:</strong> Please select all filters to view available products before adding to the pallet.

                                    </p>
                                    <button onclick="document.getElementById('filter-note').style.display='none'" style="float: right; background: none; border: none; font-size: 16px; cursor: pointer;">&times;</button>
                                </div>
                                <form id="addToPalletForm" class="row g-3">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" id="selectedVariationId" name="variation_id">
                                    <input type="hidden" id="productvariationId" name="productvariationId">

                                    <!-- Size Dropdown -->
                                    <div class="col-md-4">
                                        <label class="form-label">Size</label>
                                        <select id="sizeDropdown" name="size_id" class="form-control">
                                            <option value="" disabled selected>Select Size</option>
                                            @foreach($product->variations->pluck('size')->unique() as $size)
                                            <option value="{{ $size->id }}">{{ $size->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Thickness Dropdown -->
                                    <div class="col-md-4">
                                        <label class="form-label">Thickness</label>
                                        <select id="thicknessDropdown" name="thickness_id" class="form-control" disabled></select>
                                    </div>

                                    <!-- Finish Dropdown -->
                                    <div class="col-md-4">
                                        <label class="form-label">Finish</label>
                                        <select id="finishDropdown" name="finish_id" class="form-control" disabled></select>
                                    </div>

                                    <!-- Paint Type Dropdown -->
                                    <div class="col-md-4">
                                        <label class="form-label">Paint Type</label>
                                        <select id="paintTypeDropdown" name="paint_type_id" class="form-control" disabled></select>
                                    </div>

                                    <!-- Color Dropdown -->
                                    <div class="col-md-4">
                                        <label class="form-label">Color</label>
                                        <select id="colorDropdown" name="color_id" class="form-control" disabled></select>
                                    </div>

                                    <!-- Color Effect Dropdown -->
                                    <div class="col-md-4">
                                        <label class="form-label">Color Effect</label>
                                        <select id="colorEffectDropdown" name="color_effect_id" class="form-control" disabled></select>
                                    </div>

                                    <!-- Quantity -->
                                    <div class="col-md-4">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" id="quantity" name="quantity" min="0" value="0" class="form-control" disabled>
                                    </div>
                                    <!-- Quantity -->
                                    <div class="col-md-4">
                                        <label class="form-label">Price($)</label>
                                        <input type="number" id="pricing" name="pricing" min="0" class="form-control" disabled>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Sub Total($)</label>
                                        <input type="number" id="subtotal" name="subtotal" min="0" class="form-control" disabled>
                                    </div>
                                    <!-- Add to pallet Button -->
                                    <div class="col-md-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fi-rs-shopping-cart-add"></i> Add to Pallet
                                        </button>
                                        <button type="button" class="btn btn-outline-warning ms-2" id="addToListBtn">
                                            <i class="fa fa-list"></i> Add to List
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="card shadow-lg p-4 rounded">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="text-muted">Product items in pallet</h4>
                                    <!-- <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#productModal">
                                        <i class="fi-rs-shopping-cart-add"></i> Add Items to Pallet
                                    </button> -->
                                </div>

                                <div id="pallet-content">
                                    <div class="list-group" id="selected-variations-body"></div>
                                </div>

                            </div>
                        </div>


                    </div> <!-- Closing div for row -->
                </div> <!-- Closing div for mt-5 -->

            </div> <!-- Closing div for hm-product-detail -->

        </div>
    </section>
</main>
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-primary" id="productModalLabel">Select Product Variations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="filter-note" id="filter-note">
                    <p>
                        <strong>Note:</strong> Please select all filters to view available products before adding to the pallet.

                    </p>
                    <button onclick="document.getElementById('filter-note').style.display='none'" style="float: right; background: none; border: none; font-size: 16px; cursor: pointer;">&times;</button>
                </div>
                                            <form id="addToPalletForm" class="row g-3">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" id="selectedVariationId" name="variation_id">
                    <input type="hidden" id="productvariationId" name="productvariationId">

                    <!-- Size Dropdown -->
                    <div class="col-md-4">
                        <label class="form-label">Size</label>
                        <select id="sizeDropdown" name="size_id" class="form-control">
                            <option value="" disabled selected>Select Size</option>
                            @foreach($product->variations->pluck('size')->unique() as $size)
                            <option value="{{ $size->id }}">{{ $size->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Thickness Dropdown -->
                    <div class="col-md-4">
                        <label class="form-label">Thickness</label>
                        <select id="thicknessDropdown" name="thickness_id" class="form-control" disabled></select>
                    </div>

                    <!-- Finish Dropdown -->
                    <div class="col-md-4">
                        <label class="form-label">Finish</label>
                        <select id="finishDropdown" name="finish_id" class="form-control" disabled></select>
                    </div>

                    <!-- Paint Type Dropdown -->
                    <div class="col-md-4">
                        <label class="form-label">Paint Type</label>
                        <select id="paintTypeDropdown" name="paint_type_id" class="form-control" disabled></select>
                    </div>

                    <!-- Color Dropdown -->
                    <div class="col-md-4">
                        <label class="form-label">Color</label>
                        <select id="colorDropdown" name="color_id" class="form-control" disabled></select>
                    </div>

                    <!-- Color Effect Dropdown -->
                    <div class="col-md-4">
                        <label class="form-label">Color Effect</label>
                        <select id="colorEffectDropdown" name="color_effect_id" class="form-control" disabled></select>
                    </div>

                    <!-- Quantity -->
                    <div class="col-md-4">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="0" value="0" class="form-control" disabled>
                    </div>
                    <!-- Quantity -->
                    <div class="col-md-4">
                        <label class="form-label">Price($)</label>
                        <input type="number" id="pricing" name="pricing" min="0" class="form-control" disabled>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Sub Total($)</label>
                        <input type="number" id="subtotal" name="subtotal" min="0" class="form-control" disabled>
                    </div>
                                                    <!-- Add to pallet Button -->
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fi-rs-shopping-cart-add"></i> Add to Pallet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Toast notification function
    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `);
        
        $('#toast-container').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        // Remove toast after it's hidden
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }

    $(document).ready(function() {
        $('#media').carousel({

        });
    });
</script>
<script>
    $(document).ready(function() {
        updateSideBar();
        $('#sizeDropdown').on('change', function() {
            var formData = $('#addToPalletForm').serialize();
            getSelectData(formData, 'size');
        });

        $('#thicknessDropdown').on('change', function() {
            var formData = $('#addToPalletForm').serialize();
            getSelectData(formData, 'thickness');
        });

        $('#finishDropdown').on('change', function() {
            var formData = $('#addToPalletForm').serialize();
            getSelectData(formData, 'finish');
        });

        $('#paintTypeDropdown').on('change', function() {
            var formData = $('#addToPalletForm').serialize();
            getSelectData(formData, 'paint_type');
        });

        $('#colorDropdown').on('change', function() {
            var formData = $('#addToPalletForm').serialize();
            getSelectData(formData, 'color');
        });
        $('#colorEffectDropdown').on('change', function() {
            var formData = $('#addToPalletForm').serialize();
            getSelectData(formData, 'color_effect');
        });

        function getSelectData(value, field) {
            $.ajax({
                url: "{{ url('get-product-variations') }}",
                type: 'POST',
                data: value,
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                success: function(data) {
                    if (field == 'size' && data.thickness.length > 0) {
                        $('#thicknessDropdown').prop('disabled', false).html('<option value="" disabled selected>Select Thickness</option>');
                        $('#finishDropdown, #paintTypeDropdown, #colorDropdown, #colorEffectDropdown').prop('disabled', true).html('<option value="" disabled selected disabled selected>Select</option>');
                        $('#pricing').val(0);
                        $('#quantity').prop('disabled', true);
                        $.each(data.thickness, function(index, variation) {
                            $('#thicknessDropdown').append('<option value="' + variation.id + '">' + variation.name + '</option>');
                        });
                    }

                    if (field == 'thickness' && data.finish.length > 0) {
                        $('#finishDropdown').prop('disabled', false).html('<option value="" disabled selected>Select Finish</option>');
                        $('#paintTypeDropdown, #colorDropdown, #colorEffectDropdown').prop('disabled', true).html('<option value="" disabled selected>Select</option>');
                        $('#pricing').val(0);
                        $('#quantity').prop('disabled', true);
                        $.each(data.finish, function(index, variation) {
                            $('#finishDropdown').append('<option value="' + variation.id + '">' + variation.name + '</option>');
                        });
                    }

                    if (field == 'finish' && data.paintTypes.length > 0) {
                        $('#paintTypeDropdown').prop('disabled', false).html('<option value="" disabled selected>Select Paint Type</option>');
                        $('#colorDropdown, #colorEffectDropdown').prop('disabled', true).html('<option value="" disabled selected>Select</option>');
                        $('#pricing').val(0);
                        $('#quantity').prop('disabled', true);
                        $.each(data.paintTypes, function(index, variation) {
                            $('#paintTypeDropdown').append('<option value="' + variation.id + '">' + variation.name + '</option>');
                        });
                    }

                    if (field == 'paint_type' && data.colors.length > 0) {
                        $('#colorDropdown').prop('disabled', false).html('<option value="" disabled selected>Select Color</option>');
                        $('#colorEffectDropdown').prop('disabled', true).html('<option value="" disabled selected>Select</option>');
                        $('#pricing').val(0);
                        $('#quantity').prop('disabled', true);
                        $.each(data.colors, function(index, variation) {
                            $('#colorDropdown').append('<option value="' + variation.id + '">' + variation.name + '</option>');
                        });
                    }

                    if (field == 'color' && data.colorEffects.length > 0) {
                        $('#colorEffectDropdown').prop('disabled', false).html('<option value="" disabled selected>Select Color Effect</option>');
                        $('#pricing').val(0);
                        $('#quantity').prop('disabled', false);
                        $.each(data.colorEffects, function(index, variation) {
                            $('#colorEffectDropdown').append('<option value="' + variation.id + '">' + variation.name + '</option>');
                        });
                    }
                    if (
                        $('#sizeDropdown').val() &&
                        $('#thicknessDropdown').val() &&
                        $('#finishDropdown').val() &&
                        $('#paintTypeDropdown').val() &&
                        $('#colorDropdown').val() &&
                        $('#colorEffectDropdown').val()
                    ) {
                        if (!data.selectedVariation || !data.selectedVariation.product_variation) {
                            showToast('This product item is not available. Please select another combination.', "error");
                        } else {
                            $('#selectedVariationId').val(data.selectedVariation.id);
                            $('#productvariationId').val(data.selectedVariation.product_variation.id);
                            $('#pricing').val(data.selectedVariation?.product_variation?.pricing ?? 0);
                        }
                    }

                }
            });
        }
    });

    function updateSubtotal() {
        let price = parseFloat($('#pricing').val()) || 0;
        let quantity = parseInt($('#quantity').val()) || 0;
        let subtotal = price * quantity;
        $('#subtotal').val(subtotal.toFixed(2)); // Ensuring two decimal places
    }

    // Trigger subtotal update when quantity changes
    $('#quantity').on('input', function() {
        updateSubtotal();
    });

    // Also update subtotal when pricing changes
    $(document).on('change', '#pricing', function() {
        updateSubtotal();
    });

    $(document).ready(function() {
        $('#addToPalletForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            let productColorVariationId = $('#selectedVariationId').val();
            let variationId = $('#productvariationId').val();
            let quantity = parseInt($('#quantity').val()) || 0;
            let submitButton = $('#addToPalletForm button[type="submit"]');

            if (!variationId) {
                showToast('Please select all options to proceed.', "error");
                return;
            }

            if (quantity <= 0) {
                showToast('Quantity should be at least 1.', "info");
                return;
            }

            // Check if we need to collect address information
            const isLoggedIn = "{{ Auth::check() ? 'true' : 'false' }}" === 'true';
            
            if (isLoggedIn) {
                // For logged-in users, check if they have any pallet items with address
                $.ajax({
                    url: "{{ route('check-pallet-address') }}",
                    type: 'GET',
                    success: (response) => {
                        if (response.hasAddress) {
                            // Already have address info, just add item
                            addItemToPallet();
                        } else {
                            // Need to collect address info first
                            showPalletAddressForm();
                        }
                    },
                    error: (xhr) => {
                        if (xhr.status === 401) { // Unauthorized
                            Swal.fire({
                                title: 'Session Expired',
                                text: 'Your session has expired. Please login again to continue.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Login',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = "{{ route('login') }}";
                                }
                            });
                        } else {
                            // If check fails, show address form to be safe
                            showPalletAddressForm();
                        }
                    }
                });
            } else {
                // For guests, check if they have any pallet items with address
                $.ajax({
                    url: "{{ route('check-pallet-address') }}",
                    type: 'GET',
                    success: (response) => {
                        if (response.hasAddress) {
                            // Already have address info, just add item
                            addItemToPallet();
                        } else {
                            // Need to collect address info first
                            showPalletAddressForm();
                        }
                    },
                    error: (xhr) => {
                        // If check fails, show address form to be safe
                        showPalletAddressForm();
                    }
                });
            }
        });

        // Ensure modal closes only via the close button
        $('#productModal').modal({
            backdrop: 'static', // Prevents closing by clicking outside
            keyboard: false // Prevents closing by pressing ESC
        });
    });
    
    // Helper functions for pallet functionality
    function showPalletAddressForm() {
        // Get states from the API
        $.get("{{ route('get-states') }}", (states) => {
            let html = `
                <input type="text" class="form-control" id="pallet_project_name" name="project_name" placeholder="Project Name" required style="margin-bottom: 12px;">
                <input type="text" class="form-control" id="pallet_name" name="name" placeholder="Jobsite General Contractor" required style="margin-bottom: 12px;">
                <input type="text" class="form-control" id="pallet_address1" name="address1" placeholder="Address" required style="margin-bottom: 12px;">
                <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                    <input type="text" class="form-control" id="pallet_city" name="city" placeholder="City" required style="flex: 1;">
                    <select class="form-control" id="pallet_state" name="state" required style="flex: 1;">
                        <option value="">State</option>
                        ${states.map(state => `<option value="${state.id}">${state.state}</option>`).join('')}
                    </select>
                </div>
                <input type="text" class="form-control" id="pallet_postcode" name="postcode" placeholder="ZIP Code" required>`;

            Swal.fire({
                title: 'Add to Pallet',
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Add to Pallet',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                preConfirm: () => {
                    const formData = {
                        project_name: $('#pallet_project_name').val(),
                        name: $('#pallet_name').val(),
                        address1: $('#pallet_address1').val(),
                        city: $('#pallet_city').val(),
                        state: $('#pallet_state').val(),
                        postcode: $('#pallet_postcode').val()
                    };

                    // Validate required fields
                    const requiredFields = ['project_name', 'name', 'address1', 'city', 'state', 'postcode'];
                    for (let field of requiredFields) {
                        if (!formData[field]) {
                            Swal.showValidationMessage(`Please fill in ${field.replace('_', ' ')}`);
                            return false;
                        }
                    }

                    return formData;
                },
                customClass: {
                    popup: 'custom-swal',
                    confirmButton: 'site-primary',
                    cancelButton: 'site-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    addItemToPallet(result.value);
                }
            });
        });
    }
    
    function addItemToPallet(addressData = null) {
        let productColorVariationId = $('#selectedVariationId').val();
        let variationId = $('#productvariationId').val();
        let quantity = parseInt($('#quantity').val()) || 0;
        let submitButton = $('#addToPalletForm button[type="submit"]');
        
        // Disable button and change text while processing
        submitButton.prop('disabled', true).html('<i class="fi-rs-shopping-cart-add"></i> Adding to Pallet...');
        
        const requestData = {
            items: [{
                variationId: variationId,
                colorId: $('#colorDropdown').val(),
                quantity: quantity,
                productColorVariationId: productColorVariationId
            }]
        };

        if (addressData) {
            Object.assign(requestData, addressData);
        }

        $.ajax({
            url: "{{ route('add-multiple-to-pallet') }}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            data: requestData,
            success: function(response) {
                showToast(response.message, "success");
                updateSideBar();
                
                // Update navbar pallet count
                if (typeof updateMiniPallet === 'function') {
                    updateMiniPallet();
                }
                
                // Reset form fields
                $('#addToPalletForm')[0].reset();
                $('#sizeDropdown').prop('disabled', false);
                $('#thicknessDropdown, #finishDropdown, #paintTypeDropdown, #colorDropdown, #colorEffectDropdown')
                    .prop('disabled', true)
                    .html('<option value="" disabled selected>Select</option>');

                $('#pricing, #subtotal, #quantity').val('');
            },
            error: function(xhr) {
                if (xhr.status === 419) { // CSRF Token Mismatch
                    Swal.fire({
                        title: 'Session Expired',
                        text: 'Your session has expired. Please try again.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Retry',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            addItemToPallet(addressData);
                        }
                    });
                } else if (xhr.status === 401) { // Unauthorized
                    Swal.fire({
                        title: 'Session Expired',
                        text: 'Your session has expired. Please login again to continue.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Login',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ route('login') }}";
                        }
                    });
                } else {
                    showToast(xhr.responseJSON?.message || "Something went wrong. Please try again.", "error");
                }
            },
            complete: function() {
                // Re-enable button and restore text
                submitButton.prop('disabled', false).html('<i class="fi-rs-shopping-cart-add"></i> Add to Pallet');
            }
        });
    }

    function updateSideBar() {
        console.log('Updating sidebar for product:', "{{$product->id}}");
        $.ajax({
            url: "{{ url('update-product-detail-sidebar-pallet') }}", // Endpoint to fetch mini-pallet HTML
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            data: {
                product_id: "{{$product->id}}",
            },
            success: function(response) {
                console.log('Sidebar update response:', response);
                $('#selected-variations-body').html('');
                $('#selected-variations-body').html(response.html);
                
                // Re-bind remove events after content update
                $(document).off('click', '.remove-pallet-item').on('click', '.remove-pallet-item', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const itemId = $(this).data('id');
                    console.log('Remove button clicked for item:', itemId);
                    
                    Swal.fire({
                        title: 'Remove Item',
                        text: 'Are you sure you want to remove this item from your pallet?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, remove it!',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            console.log('Sending remove request for item:', itemId);
                            $.ajax({
                                url: "{{ route('pallet-item-remove') }}",
                                type: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                                },
                                data: {
                                    item_id: itemId
                                },
                                success: function(response) {
                                    console.log('Remove response:', response);
                                    if (response.success) {
                                        showToast('Item removed from pallet', 'success');
                                        updateSideBar();
                                        
                                        // Update navbar pallet count
                                        if (typeof updateMiniPallet === 'function') {
                                            updateMiniPallet();
                                        }
                                    } else {
                                        showToast(response.message || 'Failed to remove item', 'error');
                                    }
                                },
                                error: function(xhr) {
                                    console.log('Remove error:', xhr);
                                    if (xhr.status === 419) { // CSRF Token Mismatch
                                        Swal.fire({
                                            title: 'Session Expired',
                                            text: 'Your session has expired. Please try again.',
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: 'Retry',
                                            cancelButtonText: 'Cancel'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                // Retry the same operation
                                                $('.remove-pallet-item[data-id="' + itemId + '"]').click();
                                            }
                                        });
                                    } else {
                                        showToast(xhr.responseJSON?.message || 'Error removing item', 'error');
                                    }
                                }
                            });
                        }
                    });
                });
            },
            error: function(xhr) {
                console.error("Failed to update mini-pallet:", xhr.responseText);
            }
        });
    }
    
    // Handle pallet item removal
    $(document).on('click', '.remove-pallet-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const itemId = $(this).data('id');
        
        console.log('Remove button clicked for item:', itemId);
        
        Swal.fire({
            title: 'Remove Item',
            text: 'Are you sure you want to remove this item from your pallet?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                console.log('Sending remove request for item:', itemId);
                $.ajax({
                    url: "{{ route('pallet-item-remove') }}",
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    data: {
                        item_id: itemId
                    },
                    success: function(response) {
                        console.log('Remove response:', response);
                        if (response.success) {
                            showToast('Item removed from pallet', 'success');
                            updateSideBar();
                            
                            // Update navbar pallet count
                            if (typeof updateMiniPallet === 'function') {
                                updateMiniPallet();
                            }
                        } else {
                            showToast(response.message || 'Failed to remove item', 'error');
                        }
                    },
                    error: function(xhr) {
                        console.log('Remove error:', xhr);
                        if (xhr.status === 419) { // CSRF Token Mismatch
                            Swal.fire({
                                title: 'Session Expired',
                                text: 'Your session has expired. Please try again.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Retry',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Retry the same operation
                                    $('.remove-pallet-item[data-id="' + itemId + '"]').click();
                                }
                            });
                        } else {
                            showToast(xhr.responseJSON?.message || 'Error removing item', 'error');
                        }
                    }
                });
            }
        });
    });

    function toggleColors(showAll) {
        var moreColors = document.getElementById('more-colors');
        var showMoreBtn = document.getElementById('show-more-colors');
        var showLessBtn = document.getElementById('show-less-colors');
        if (showAll) {
            moreColors.style.display = 'inline';
            showMoreBtn.style.display = 'none';
            showLessBtn.style.display = 'inline';
        } else {
            moreColors.style.display = 'none';
            showMoreBtn.style.display = 'inline';
            showLessBtn.style.display = 'none';
            // Optionally scroll back to the color list
            document.getElementById('color-names-list').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }

    // Add to List button logic
    $('#addToListBtn').on('click', function(e) {
        e.preventDefault();
        // Validate form fields
        let sizeId = $('#sizeDropdown').val();
        let thicknessId = $('#thicknessDropdown').val();
        let finishId = $('#finishDropdown').val();
        let paintTypeId = $('#paintTypeDropdown').val();
        let colorId = $('#colorDropdown').val();
        let colorEffectId = $('#colorEffectDropdown').val();
        let quantity = parseInt($('#quantity').val()) || 0;
        let price = parseFloat($('#pricing').val()) || 0;
        let subtotal = parseFloat($('#subtotal').val()) || 0;
        let productName = $('.product-title').text().trim();
        let imageUrl = $('img[alt="'+productName+'"]').attr('src') || "{{ asset('demo.jpg') }}";
        let selectedVariationId = $('#selectedVariationId').val();
        let productvariationId = $('#productvariationId').val();
        
        if (!sizeId || !thicknessId || !finishId || !paintTypeId || !colorId || !colorEffectId || !selectedVariationId || !productvariationId) {
            showToast('Please select all options to proceed.', "error");
            return;
        }
        if (quantity <= 0) {
            showToast('Quantity should be at least 1.', "info");
            return;
        }
        
        // Prepare item object
        let item = {
            variationId: productvariationId,
            productColorVariationId: selectedVariationId,
            colorId: colorId,
            productName: productName,
            price: price,
            quantity: quantity,
            size: $('#sizeDropdown option:selected').text(),
            thickness: $('#thicknessDropdown option:selected').text(),
            finish: $('#finishDropdown option:selected').text(),
            paintType: $('#paintTypeDropdown option:selected').text(),
            color: $('#colorDropdown option:selected').text(),
            colorEffect: $('#colorEffectDropdown option:selected').text(),
            imageUrl: imageUrl
        };
        
        // Check if user is logged in by attempting to fetch lists and states
        Promise.all([
            $.get("{{ route('get-saved-lists') }}"),
            $.get("{{ route('get-states') }}")
        ]).then(([response, states]) => {
            // Build the HTML for the dialog - same as filter page
            let html = `<div class="form-group">`;
            
            if (response.length > 0) {
                html += `
                    <div class="mb-3"> 
                        <select id="existingLists" class="form-control">
                            <option value="">-- Select existing List --</option>
                            ${response.map(list => 
                                `<option value="${list.id}">${list.name} (${list.items_count} items)</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="text-center my-3">
                        <strong>OR</strong>
                    </div>`;
            } else {
                html += `
                    <div class="mb-3"> 
                        <select id="existingLists" class="form-control" disabled>
                            <option value="">No saved lists available</option>
                        </select>
                    </div>`;
            }
            
            html += `
                <div class="mb-3"> 
                    <input type="text" id="newListName" class="form-control" placeholder="Enter project name">
                </div>
                <div class="mb-3">
                    <input type="text" class="form-control" id="address1" name="address1" placeholder="Jobsite General Contractor" required>
                </div>
                <div class="mb-3">
                    <input type="text" class="form-control" id="address2" name="address2" placeholder="Address" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <input type="text" class="form-control" id="city" name="city" placeholder="Enter city" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <select class="form-control" id="state" name="state" placeholder="Select state" required>
                            <option value="">Select State</option>
                            ${states.map(state => `<option value="${state.id}">${state.state}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <input type="text" class="form-control" id="postcode" placeholder="ZIP Code" name="postcode" required>
            </div>`;
            
            // Show SweetAlert with the options - same as filter page
            Swal.fire({
                title: 'Save List',
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Save',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                preConfirm: () => {
                    const listId = $('#existingLists').val();
                    const newName = $('#newListName').val().trim();
                    const address1 = $('#address1').val().trim();
                    const address2 = $('#address2').val().trim();
                    const city = $('#city').val().trim();
                    const state = $('#state').val();
                    const postcode = $('#postcode').val().trim();

                    if (!listId && !newName) {
                        Swal.showValidationMessage('Please select a list or enter a project name');
                        return false;
                    }

                    if (newName && newName.length < 3) {
                        Swal.showValidationMessage('Project name must be at least 3 characters');
                        return false;
                    }

                    // Validate address fields for new lists
                    if (!listId) {
                        if (!newName) {
                            Swal.showValidationMessage('Please enter project name');
                            return false;
                        }
                        if (!address1) {
                            Swal.showValidationMessage('Please enter jobsite general contractor');
                            return false;
                        }
                        if (!address2) {
                            Swal.showValidationMessage('Please enter address');
                            return false;
                        }
                        if (!city) {
                            Swal.showValidationMessage('Please enter city');
                            return false;
                        }
                        if (!state) {
                            Swal.showValidationMessage('Please select state');
                            return false;
                        }
                        if (!postcode) {
                            Swal.showValidationMessage('Please enter ZIP code');
                            return false;
                        }
                    }

                    return {
                        listId: listId,
                        newName: newName,
                        project_name: newName,
                        address1: address1,
                        address2: address2,
                        city: city,
                        state: state,
                        postcode: postcode
                    };
                },
                didOpen: () => {
                    // If existing lists exist, set up the toggle behavior
                    if (response.length > 0) {
                        $('#existingLists').change(function() {
                            if ($(this).val()) {
                                $('#newListName').val('').prop('disabled', true);
                                $('#address1').val('').prop('disabled', true);
                                $('#city').val('').prop('disabled', true);
                                $('#state').val('').prop('disabled', true);
                                $('#postcode').val('').prop('disabled', true);
                            } else {
                                $('#newListName').prop('disabled', false).focus();
                                $('#address1').prop('disabled', false);
                                $('#city').prop('disabled', false);
                                $('#state').prop('disabled', false);
                                $('#postcode').prop('disabled', false);
                            }
                        });

                        $('#newListName').on('input', function() {
                            if ($(this).val().trim()) {
                                $('#existingLists').val('');
                                $('#address1').prop('disabled', false);
                                $('#city').prop('disabled', false);
                                $('#state').prop('disabled', false);
                                $('#postcode').prop('disabled', false);
                            }
                        });
                    }
                },
                customClass: {
                    popup: 'custom-swal',
                    confirmButton: 'site-primary',
                    cancelButton: 'site-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send AJAX to save item to list on server
                    let listId = result.value.listId;
                    let newName = result.value.newName;
                    let itemsToSave = [{
                        variationId: item.variationId,
                        colorId: item.colorId,
                        quantity: item.quantity,
                        productName: item.productName,
                        price: item.price,
                        productColorVariationId: item.productColorVariationId
                    }];
                    
                    if (listId) {
                        // Existing list - no need for address
                        saveList(listId, newName, itemsToSave);
                    } else {
                        // New list with address
                        saveListWithAddress(newName, itemsToSave, {
                            project_name: result.value.project_name,
                            address1: result.value.address1,
                            address2: result.value.address2,
                            city: result.value.city,
                            state: result.value.state,
                            postcode: result.value.postcode
                        });
                    }
                }
            });
        }).catch((error) => {
            if (error.status === 419) { // CSRF Token Mismatch
                Swal.fire({
                    title: 'Session Expired',
                    text: 'Your session has expired. Please try again.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Retry',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Retry the same operation
                        $('#addToListBtn').click();
                    }
                });
            } else if (error.status === 401) { // Unauthorized
                Swal.fire({
                    title: 'Login Required',
                    text: 'You need to login to save lists. Would you like to login now?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Login',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "{{ route('login') }}";
                    }
                });
            } else if (error.status === 403 || (error.responseJSON && error.responseJSON.message && error.responseJSON.message.includes('email address is not verified'))) {
                // Email verification required
                Swal.fire({
                    title: 'Email Verification Required',
                    text: 'You need to verify your email address before you can save lists. Please check your email for a verification link.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Resend Verification',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect to email verification page
                        window.location.href = "{{ route('verification.notice') }}";
                    }
                });
            } else {
                showToast('Error loading data. Please try again.', 'error');
            }
        });
    });
    
    // Helper functions for saving lists
    function saveList(listId, newName, itemsToSave) {
        $.ajax({
            url: "{{ route('save-shopping-list-product-detail') }}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            data: {
                list_id: listId,
                name: newName,
                items: itemsToSave
            },
            success: function(response) {
                Swal.fire({
                    title: 'Success',
                    text: response.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                // Update list count in navbar if function exists
                if (typeof updateListCount === 'function') {
                    updateListCount();
                }
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'Failed to save your list. Please try again.',
                    icon: 'error'
                });
            }
        });
    }
    
    function saveListWithAddress(listName, itemsToSave, addressData) {
        $.ajax({
            url: "{{ route('save-shopping-list-product-detail') }}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            data: {
                name: listName,
                items: itemsToSave,
                ...addressData
            },
            success: function(response) {
                Swal.fire({
                    title: 'Success',
                    text: response.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                // Update list count in navbar if function exists
                if (typeof updateListCount === 'function') {
                    updateListCount();
                }
            },
            error: function(xhr) {
                Swal.fire({
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'Failed to save your list. Please try again.',
                    icon: 'error'
                });
            }
        });
    }

</script>
@endpush
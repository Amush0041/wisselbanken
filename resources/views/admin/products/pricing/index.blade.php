@extends('admin.layouts.app')

@section('seo')
<title> Product Pricings | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce-ProductPricing">
        <!-- ProductPricing List Table -->
        <div class="card">
            <div class="card-header">
                <h4>Product Pricings
                </h4>
            </div>
            <div class="card-body">
                <div class="card-datatable table-responsive">
                    <!-- Add Button -->
                    <button id="addProductPricingButton" class="btn btn-secondary btn-primary ms-2 waves-effect waves-light"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasAddProductPricing">
                        <span><i class="ti ti-plus ti-xs me-0 me-sm-2"></i>
                            <span class="d-none d-sm-inline-block">Add Product Pricing</span>
                        </span>
                    </button>
                    {{ $dataTable->table() }}
                </div>
            </div>
        </div>

        <!-- Offcanvas Form for Add ProductPricing -->
        <div class="offcanvas offcanvas-end" id="offcanvasAddProductPricing">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleAdd">Add Product Pricing</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="ProductPricingFormAdd">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="title">Manufacturers <span class="text-danger">*</span></label>
                        <select type="select" id="my-manfacturers" name="manufacturer" class="form-control select2" data-name="Manufacturers">
                            <option value="" disabled selected>--select manufacturer--</option>
                            @foreach($manufacturers as $manufacturer)
                            <option value="{{$manufacturer->id}}">{{$manufacturer->name}}</option>
                            @endforeach
                        </select>

                        <span class="text-danger error-text manufacturer_error_add"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="title">Products <span class="text-danger">*</span></label>
                        <select type="select" id="my-products" name="product" class="form-control select2" data-name="Prodcut"></select>
                        <span class="text-danger error-text product_error_add"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="title">Part Name <span class="text-danger">*</span></label>
                        <input type="text" name="part_name" id="part_name" class="form-control">
                        <span class="text-danger error-text part_name_error_add"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="title">Part Number <span class="text-danger">*</span></label>
                        <input type="text" name="part_number" id="part_number" class="form-control">
                        <span class="text-danger error-text part_number_error_add"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="title">Colors <span class="text-danger">*</span></label>
                        <select type="select" name="color" id="color" class="form-control select2" data-name="Colors">
                            <option value="" disabled selected>--select color--</option>
                            @foreach($colors as $color)
                            <option value="{{$color->id}}">{{$color->name}}</option>
                            @endforeach
                        </select>
                        <span class="text-danger error-text color_error_add"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="title">Price<span class="text-danger">*</span></label>
                        <input type="number" min="0" name="price" id="price" step="any" class="form-control">
                        <span class="text-danger error-text price_error_add"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="unit_price">Unit Price <span class="text-danger">*</span></label>
                        <input type="number" min="0" id="unit_price" name="unit_price" class="form-control" value="{{old('unit_price')}}" />

                        <span class="text-danger error-text unit_price_error_add"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="quantity">Quantity <span class="text-danger">*</span></label>
                        <input type="number" id="quantity" min="0" name="quantity" class="form-control" value="{{old('quantity')}}" />

                        <span class="text-danger error-text quantity_error_add"></span>
                    </div>


                    <div class="form-group mt-3 mb-3">
                        <button id="saveBtnAdd" type="submit" class="btn btn-primary">Add</button>
                        <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">Discard</button>
                    </div>
                </form>

            </div>
        </div>

        <!-- Offcanvas Form for Edit ProductPricing -->
        <div class="offcanvas offcanvas-end" id="offcanvasEditProductPricing">
            <div class="offcanvas-header py-6">
                <h5 class="offcanvas-title" id="offcanvasTitleEdit">Edit ProductPricing</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body border-top">
                <form id="ProductPricingFormEdit">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="ProductPricing_id_edit" name="id">


                    <div class="form-group">
                        <label class="form-label" for="title">Manufacturers <span class="text-danger">*</span></label>
                        <select type="select" id="manufacturer_edit" name="manufacturer" class="form-control select2" data-name="Manufacturers">
                            <option value="">--select manufacturer--</option>
                            @foreach($manufacturers as $manufacturer)
                            <option value="{{$manufacturer->id}}">{{$manufacturer->name}}</option>
                            @endforeach
                        </select>

                        <span class="text-danger error-text manufacturer_error_edit"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="title">Products <span class="text-danger">*</span></label>
                        <select type="select" id="product_edit" name="product" class="form-control select2" data-name="Prodcut"></select>
                        <span class="text-danger error-text product_error_edit"></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="title">Part Name <span class="text-danger">*</span></label>
                        <input type="text" name="part_name" id="part_name_edit" class="form-control">
                        <span class="text-danger error-text part_name_error_edit"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="title">Part Number <span class="text-danger">*</span></label>
                        <input type="text" name="part_number" id="part_number_edit" class="form-control">
                        <span class="text-danger error-text part_number_error_edit"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="title">Colors <span class="text-danger">*</span></label>
                        <select type="select" name="color" id="color_edit" class="form-control select2" data-name="Colors">
                            <option value="">--select color--</option>
                            @foreach($colors as $color)
                            <option value="{{$color->id}}">{{$color->name}}</option>
                            @endforeach
                        </select>
                        <span class="text-danger error-text color_error_edit"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="title">Price<span class="text-danger">*</span></label>
                        <input type="number" min="0" name="price" id="price_edit" step="any" class="form-control">
                        <span class="text-danger error-text price_error_edit"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="unit_price">Unit Price <span class="text-danger">*</span></label>
                        <input type="number" min="0" id="unit_price_edit" name="unit_price" class="form-control" value="{{old('unit_price')}}" />

                        <span class="text-danger error-text unit_price_error_edit"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="quantity">Quantity <span class="text-danger">*</span></label>
                        <input type="number" id="quantity_edit" min="0" name="quantity" class="form-control" value="{{old('quantity')}}" />

                        <span class="text-danger error-text quantity_error_edit"></span>
                    </div>



                    <div class="form-group mt-3 mb-3">
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
    function reloadSelect2(element) {
        $(element).select2('destroy').select2({
            placeholder: `Select ${$(element).data('name')}`,
            dropdownParent: $(element).parent()
        });
    }
    $(document).ready(function() {

        $(document).on('change', '#my-manfacturers', function() {
            get_products();
        });

        function get_products() {
            var manfacture_id = $('#my-manfacturers').val();
            $.ajax({
                url: "{{route('get-manfacturer-products')}}",
                method: "POST",
                data: {
                    id: manfacture_id,
                    _token: "{{csrf_token()}}",
                },
                success: function(response) {
                    if (response.success) {
                        $('#my-products').empty();
                        $('#my-products').append('<option disabled selected value="">-- Select Products --</option>');
                        $.each(response.products, function(index, product) {
                            $('#my-products').append('<option value="' + product.id + '">' + product.name + '</option>');
                        });

                        reloadSelect2('#my-products');
                    }
                }
            });
        }

    });

    $(function() {
        const select2 = $('.select2'),
            selectPicker = $('.selectpicker');

        // Bootstrap select
        if (selectPicker.length) {
            selectPicker.selectpicker();
        }

        // select2
        if (select2.length) {
            select2.each(function() {
                var $this = $(this);
                $this.wrap('<div class="position-relative"></div>');
                $this.select2({
                    placeholder: 'Select ' + $this.data('name'),
                    dropdownParent: $this.parent()
                });
            });
        }
    });
</script>
<script>
    $(document).ready(function() {
        let table = $('#product-pricing-table').DataTable();

        // Move Add Button Near Search Bar
        setTimeout(() => {
            $("#addProductPricingButton").appendTo(".dataTables_filter");
        }, 500);


        // Pass Header Token
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': "{{csrf_token()}}"
            }
        });

        // Submit Add Form
        $('#ProductPricingFormAdd').on('submit', function(e) {
            e.preventDefault();
            $('#saveBtnAdd').prop('disabled', true).html('Sending...');
            let formData = new FormData(this);
            let actionUrl = "{{ route('product-pricing.store') }}";

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#saveBtnAdd').prop('disabled', false).html('Add');
                    $('#ProductPricingFormAdd')[0].reset();
                    $('#offcanvasAddProductPricing').offcanvas('hide');
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
                error: function(xhr) {
                    $('#saveBtnAdd').prop('disabled', false).html('Add');
                    let errors = xhr.responseJSON.errors;
                    $('.error-text').text('');
                    $.each(errors, function(key, value) {
                        $('.' + key + '_error_add').text(value[0]);
                    });
                }
            });
        });

        $(document).on('change', '#manufacturer_edit', function() {
            get_edit_products();
        });

        function get_edit_products(manufacturer_id, selected_product_id = null) {
            var manfacture_id = manufacturer_id ?? $('#manufacturer_edit').val();
            $.ajax({
                url: "{{route('get-manfacturer-products')}}",
                method: "POST",
                data: {
                    id: manfacture_id,
                    _token: "{{csrf_token()}}",
                },
                success: function(response) {
                    if (response.success) {
                        $('#product_edit').empty();
                        $('#product_edit').append('<option disabled selected value="">-- Select Products --</option>');
                        $.each(response.products, function(index, product) {
                            $('#product_edit').append('<option value="' + product.id + '">' + product.name + '</option>');
                        });

                        reloadSelect2('#product_edit');

                        // Set selected product after options are populated
                        if (selected_product_id) {
                            $('#product_edit').val(selected_product_id).trigger('change');
                        }
                    } else {
                        $('#product_edit').addClass('error-border');
                        $('#product_edit').append('<option disabled selected>No Product Found!</option>');
                    }
                }
            });
        }

        // Edit ProductPricing
        $('body').on('click', '.editProductPricing', function() {
            let id = $(this).data('id');
            $.get("{{ route('product-pricing.edit', ':id') }}".replace(':id', id), function(data) {
                $('#ProductPricing_id_edit').val(data.id);

                // Set manufacturer and trigger change event
                $('#manufacturer_edit').val(data.manufacturer_id).trigger('change');

                // Fetch products and set the correct selected product
                get_edit_products(data.manufacturer_id, data.product_id);

                // Set other form values
                $('#part_name_edit').val(data.part_name);
                $('#part_number_edit').val(data.part_number);
                $('#color_edit').val(data.color_id).trigger('change');
                $('#quantity_edit').val(data.quantity);
                $('#price_edit').val(data.price);
                $('#unit_price_edit').val(data.unit_price);

                $('#offcanvasTitleEdit').text('Edit Product Pricing');
                $('#offcanvasEditProductPricing').offcanvas('show');
            });
        });

        // Generate Slug from Name
        $('#name_edit').on('keyup', function() {
            let name = $(this).val();
            let slug = name.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
            $('#slug_edit').val(slug);
        });

        // Submit Edit Form
        $('#ProductPricingFormEdit').on('submit', function(e) {
            e.preventDefault();

            $('#saveBtnEdit').prop('disabled', true).html('Sending...');
            let formData = new FormData(this);
            let actionUrl = "{{ route('product-pricing.update', ':id') }}".replace(':id', $('#ProductPricing_id_edit').val());

            $.ajax({
                type: "PUT",
                url: actionUrl,
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: "PUT",
                    id: $('#ProductPricings_id_edit').val(),
                    manufacturer: $('#manufacturer_edit').val(),
                    product: $('#product_edit').val(),
                    color: $('#color_edit').val(),
                    part_name: $('#part_name_edit').val(),
                    part_number: $('#part_number_edit').val(),
                    price: $('#price_edit').val(),
                    unit_price: $('#unit_price_edit').val(),
                    quantity: $('#quantity_edit').val(), 
                },
                success: function(response) {
                    $('#saveBtnEdit').prop('disabled', false).html('Update');
                    $('#ProductPricingFormEdit')[0].reset();
                    $('#offcanvasEditProductPricing').offcanvas('hide');
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
                error: function(xhr) {
                    $('#saveBtnEdit').prop('disabled', false).html('Update');
                    let errors = xhr.responseJSON.errors;
                    $('.error-text').text('');
                    $.each(errors, function(key, value) {
                        $('.' + key + '_error_edit').text(value[0]);
                    });
                }
            });
        });

        // Delete ProductPricing with SweetAlert
        $('body').on('click', '.deleteProductPricing', function() {
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
                        url: "{{ route('product-pricing.destroy', ':id') }}".replace(':id', id),
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
@extends('admin.layouts.app')
@section('seo')
<title> Edit Product | {{ env("APP_NAME", "Wisselbanken") }}</title>
@endsection
@push('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.9.1/summernote-bs5.min.css" integrity="sha512-rDHV59PgRefDUbMm2lSjvf0ZhXZy3wgROFyao0JxZPGho3oOuWejq/ELx0FOZJpgaE5QovVtRN65Y3rrb7JhdQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    .table,
    .table th,
    .table td {
        border: none !important;
        padding: 0 !important;
    }

    .table th,
    .table td {
        padding-bottom: 10px !important;
    }

    .btn-container {
        display: flex;
        align-items: center;
        gap: 2px;
        /* Adds some space between the buttons */
    }

    /* Remove padding inside cells to make them compact */
    td,
    th {
        padding: 5px;
    }

    .light-style .select2-container--default.select2-container--focus .select2-selection--multiple,
    .light-style .select2-container--default.select2-container--open .select2-selection--multiple,
    .light-style .select2-container--default .select2-selection--multiple {
        height: 10px;
        overflow: scroll;
    }

    .table-responsive {
        max-height: 400px;
        /* Adjust height as needed */
        overflow-y: auto;
        overflow-x: hidden;
        /* Optional: Adds a border around the table */
    }

    .table thead {
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }

    /* Ensure table layout remains stable */
    .table {
        table-layout: fixed;
        width: 100%;
    }

    /* Ensure select elements stay fixed in size */
    .table td select {
        min-width: 120px;
        width: 100%;
        max-width: 150px;
    }

    .select2-container {
        width: 100% !important;
    }

    .select2-selection {
        min-height: 40px !important;
        display: flex;
        align-items: center;
    }
</style>
@endpush
@section('content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="app-ecommerce">
        <!-- Add Product -->


        <form id="product-form" action="{{route('products.update',$product->id)}}" method="Post" enctype="multipart/form-data">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
                <div class="d-flex flex-column justify-content-center">
                    <h4 class="mb-1">Edit Product</h4>
                </div>
                <div class="d-flex align-content-center flex-wrap gap-4">
                    <a class="btn btn-outline-danger"> <i class="fas fa-arrow-left" style="padding-right: 4px;"> </i> Back</a>
                    <!-- Remove this external button or link it to form submission -->
                    <button type="submit" class="btn btn-primary">Update product</button>
                </div>
            </div>
            @csrf
            @method('PUT')
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
                        <div class="card-body row">
                            <!-- Row 1 -->

                            <div class="form-group col-md-12 mb-6">
                                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" value="{{old('name',$product->name)}}" placeholder="Product Name" required />
                            </div>
                            <div class="form-group col-md-6 mb-6">
                                <label class="form-label" for="division_id">Division <span class="text-danger">*</span></label>
                                <select id="division_id" name="division_id" class="form-control" data-name="Division" required>
                                    @foreach($divisions as $division)
                                    @if($division->code == 07)
                                    <option value="{{$division->id}}" @selected(old('division_id', $product->division_id) == $division->id)> {{$division->code}} - {{$division->name}}</option>
                                    @else
                                    <option value="{{$division->id}}" @selected(old('division_id', $product->division_id) == $division->id)> {{$division->code}} - {{$division->name}}</option>
                                    @endif
                                    @endforeach
                                </select>
                                @error('division_id')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-6">
                                <label class="form-label" for="specification_id">Specification <span class="text-danger">*</span></label>
                                <select id="specification_id" name="specification_id" class="form-control select2" data-name="Specification" required>
                                    @foreach($specifications as $specification)
                                    <option value="{{$specification->id}}" @selected(old('specification_id', $product->specification_id) == $specification->id)>{{$specification->specification_number}} ({{$specification->material_type}})</option>
                                    @endforeach
                                </select>
                                @error('specification_id')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-6">
                                <label class="form-label" for="material_type">Material Types <span class="text-danger">*</span></label>
                                <input type="text" id="material_type" name="material_type" class="form-control" value="{{ old('material_type', $material_type->material_type) }}" placeholder="Material Type" readonly />
                                @error('material_type')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-6">
                                <label for="manufacturer">Choose or Add a Manufacturer:<span class="text-danger">*</span></label>
                                <select class="form-control select2" id="manufacturer_id" name="manufacturer_id" class="form-control" value="" placeholder="Manufacture" required>
                                    @foreach($manufacturers as $id => $name)
                                    <option value="{{$id}}" @selected(old('manufacturer_id', $product->manufacturer_id) == $id)>{{$name}}</option>
                                    @endforeach
                                </select>
                                <p id="statusMessage" style="color: red; display: none;"></p>
                            </div>
                            <!-- Row 4 -->
                            <div class="form-group col-md-6 mb-6">
                                <label class="form-label" for="feature_image">Feature Image</label>
                                <input id="logo-id" name="feature_image" class="form-control" type="file">
                                @error('feature_image')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-6">
                                <label class="form-label" for="product_description">Image Preview</label>
                                <div class="main-img-preview">
                                    @if($product->feature_image)
                                    <img class="thumbnail img-preview" src="{{asset($product->feature_image)}}" style="height: 150px;width:auto;">
                                    @else
                                    <img class="thumbnail img-preview" src="{{asset($product->feature_image)}}" style="height: 150px;width:auto;display:none">
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="card mt-10">
                        <div class="card-header">
                            <h5>Product Variations</h5>
                        </div>
                        @php $i = 1 @endphp
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table" width="100%">
                                    <thead>
                                        <tr>
                                            <th width="12%">Size</th>
                                            <th width="12%">Thickness</th>
                                            <th width="12%">Finish</th>
                                            <th width="12%">Paint Type</th>
                                            <th width="20%">Colors</th>
                                            <th width="12%">Color Effect</th>
                                            <th width="10%">Pricing</th>
                                            <th width="10%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="variation-container">
                                        @foreach($product->productVariation as $variation)
                                        <tr class="variation-row" data-id="{{$i}}">
                                            <td>
                                                <select name="size_id[{{$i}}]" class="form-control select2" data-field="size" required>
                                                    <option value="" disabled>Select Size</option>
                                                    @foreach($sizes as $size)
                                                    <option value="{{ $size->id }}" {{ $variation->size_id == $size->id ? 'selected' : '' }}>{{ $size->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="thickness_id[{{$i}}]" class="form-control select2" data-field="thickness" required>
                                                    <option value="" disabled>Select Thickness</option>
                                                    @foreach($thicknesses as $thickness)
                                                    <option value="{{ $thickness->id }}" {{ $variation->thickness_id == $thickness->id ? 'selected' : '' }}>{{ $thickness->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="finish_id[{{$i}}]" class="form-control select2" data-field="finish" required>
                                                    <option value="" disabled>Select Finish</option>
                                                    @foreach($finishes as $finish)
                                                    <option value="{{ $finish->id }}" {{ $variation->finish_id == $finish->id ? 'selected' : '' }}>{{ $finish->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="paint_type_id[{{$i}}]" class="form-control paint-type select2" data-field="paint_type" onchange="getColors(this, {{$i}})" required>
                                                    <option value="" disabled>Select Paint Type</option>
                                                    @foreach($paintTypes as $paintType)
                                                    <option value="{{ $paintType->id }}" {{ $variation->paint_type_id == $paintType->id ? 'selected' : '' }}>{{ $paintType->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="color_id[{{$i}}][]" class="form-control select2 color-select" data-field="color" id="fetchColor-{{$i}}" multiple required>
                                                    @foreach($colors as $color)
                                                    <option value="{{ $color->id }}" {{ in_array($color->id, $variation->colors->pluck('id')->toArray()) ? 'selected' : '' }}>{{ $color->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="color_effect_id[{{$i}}]" class="form-control select2" data-field="color_effect" required>
                                                    <option value="" disabled>Select Color Effect</option>
                                                    @foreach($colorEffects as $colorEffect)
                                                    <option value="{{ $colorEffect->id }}" {{ $variation->color_effect_id == $colorEffect->id ? 'selected' : '' }}>{{ $colorEffect->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="pricing[{{$i}}]" class="form-control pricinginput" value="{{ $variation->pricing }}" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <div class="btn-container">
                                                    <button type="button" class="btn btn-danger btn-sm remove-row" title="Remove"><i class="ti ti-minus"></i></button>
                                                    <button type="button" class="btn btn-info btn-sm copy-row" title="Copy"><i class="ti ti-copy"></i></button>
                                                    <button type="button" class="btn btn-success btn-sm add-row" title="Add"><i class="ti ti-plus"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        @php $i++ @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <div class="card mt-10">
                        <div class="card-body">
                            <!-- Row 5 -->
                            <div class="form-group col-md-12 mb-6" style="margin-top:10px;">
                                <label class="mb-1">Description (Optional)</label>
                                <textarea type="text" name="description" class="form-control summernote">{{$product->product_description ?? ''}}</textarea>
                            </div>
                        </div>
                        <div class="card-footer">

                            <button type="submit" class="btn btn-lg btn-primary" style="float:right;">Update Product</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.9.1/summernote-bs5.min.js" integrity="sha512-qTQLA91yGDLA06GBOdbT7nsrQY8tN6pJqjT16iTuk08RWbfYmUz/pQD3Gly1syoINyCFNsJh7A91LtrLIwODnw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
    $(document).ready(function() {
        function initializeSelect2(container) {
            container.find('.select2').each(function() {
                const $select = $(this);
                const isColorSelect = $select.hasClass('color-select');
                const isMultiple = $select.prop('multiple');
                
                // Enable tags for Size, Thickness, Finish, Paint Type, Colors, and Color Effect
                if ($select.attr('name') && (
                    $select.attr('name').includes('size_id') ||
                    $select.attr('name').includes('thickness_id') ||
                    $select.attr('name').includes('finish_id') ||
                    $select.attr('name').includes('paint_type_id') ||
                    $select.attr('name').includes('color_effect_id') ||
                    isColorSelect
                )) {
                    const select2Config = {
                        width: '100%',
                        allowClear: true,
                        tags: true,
                        createTag: function (params) {
                            const term = $.trim(params.term);
                            if (term === '') {
                                return null;
                            }
                            return {
                                id: 'new_' + term,
                                text: term,
                                newTag: true
                            };
                        }
                    };
                    
                    if (isMultiple) {
                        select2Config.multiple = true;
                    }
                    
                    $select.select2(select2Config);
                } else {
                    $select.select2({
                        width: '100%',
                        allowClear: true
                    });
                }
            });
        }

        initializeSelect2($("#variation-container"));
        let rowCount = "{{$i}}";

        $(document).on("click", ".add-row", function() {
            let newRow = `
                <tr class="variation-row" data-id="${rowCount}">
                    <td>
                        <select name="size_id[${rowCount}]" class="form-control select2" data-field="size" required>
                            <option value=""  disabled selected>Select Size</option>
                            ${generateOptions(@json($sizes))}
                        </select>
                    </td>
                    <td>
                        <select name="thickness_id[${rowCount}]" class="form-control select2" data-field="thickness" required>
                            <option value=""  disabled selected>Select Thickness</option>
                            ${generateOptions(@json($thicknesses))}
                        </select>
                    </td>
                    <td>
                        <select name="finish_id[${rowCount}]" class="form-control select2" data-field="finish" required>
                            <option value=""  disabled selected>Select Finish</option>
                            ${generateOptions(@json($finishes))}
                        </select>
                    </td>
                    <td>
                        <select name="paint_type_id[${rowCount}]" class="form-control paint-type select2" data-field="paint_type" onchange="getColors(this, ${rowCount})" required>
                            <option value=""  disabled selected >Select Paint Type</option>
                            ${generateOptions(@json($paintTypes))}
                        </select>
                    </td>
                    <td>
                        <select name="color_id[${rowCount}][]" class="form-control select2 color-select" data-field="color" id="fetchColor-${rowCount}" multiple required>
                        </select>
                    </td>
                    <td>
                        <select name="color_effect_id[${rowCount}]" class="form-control select2" data-field="color_effect" required>
                            <option value="" disabled selected>Select Color Effect</option>
                            ${generateOptions(@json($colorEffects))}
                        </select>
                    </td>
                    <td>
                        <input type="number" name="pricing[${rowCount}]" class="form-control pricinginput" step="0.01" min="0" required>
                    </td>
                    <td>
                        <div class="btn-container">
                            <button type="button" class="btn btn-danger btn-sm remove-row" title="Remove"><i class="ti ti-minus"></i></button>
                            <button type="button" class="btn btn-info btn-sm copy-row" title="Copy"><i class="ti ti-copy"></i></button>
                            <button type="button" class="btn btn-success btn-sm add-row" title="Add"><i class="ti ti-plus"></i></button>
                        </div>
                    </td>
                </tr>
            `;
            $("#variation-container").append(newRow);
            rowCount++;
            initializeSelect2($("#variation-container"));
        });

        $(document).on("click", ".remove-row", function() {
            $(this).closest("tr").remove();
        });

        $(document).on("click", ".copy-row", function() {
            const $currentRow = $(this).closest("tr");
            let currentRowId;
            const dataId = $currentRow.data('id');
            if (dataId) {
                currentRowId = parseInt(dataId);
            } else {
                const nameMatch = $currentRow.find('select').first().attr('name');
                if (nameMatch) {
                    const match = nameMatch.match(/\[(\d+)\]/);
                    currentRowId = match ? parseInt(match[1]) : 0;
                } else {
                    currentRowId = 0;
                }
            }
            
            // Get all values from current row
            const sizeId = $currentRow.find('select[name*="size_id"]').val();
            const thicknessId = $currentRow.find('select[name*="thickness_id"]').val();
            const finishId = $currentRow.find('select[name*="finish_id"]').val();
            const paintTypeId = $currentRow.find('select[name*="paint_type_id"]').val();
            const colorIds = $currentRow.find('select[name*="color_id"]').val() || [];
            const colorEffectId = $currentRow.find('select[name*="color_effect_id"]').val();
            const pricing = $currentRow.find('input[name*="pricing"]').val();
            
            // Create new row with incremented index
            const newRowId = rowCount;
            rowCount++;
            
            let newRow = `
                <tr class="variation-row" data-id="${newRowId}">
                    <td>
                        <select name="size_id[${newRowId}]" class="form-control select2" data-field="size" required>
                            <option value="" disabled>Select Size</option>
                            ${generateOptions(@json($sizes))}
                        </select>
                    </td>
                    <td>
                        <select name="thickness_id[${newRowId}]" class="form-control select2" data-field="thickness" required>
                            <option value="" disabled>Select Thickness</option>
                            ${generateOptions(@json($thicknesses))}
                        </select>
                    </td>
                    <td>
                        <select name="finish_id[${newRowId}]" class="form-control select2" data-field="finish" required>
                            <option value="" disabled>Select Finish</option>
                            ${generateOptions(@json($finishes))}
                        </select>
                    </td>
                    <td>
                        <select name="paint_type_id[${newRowId}]" class="form-control paint-type select2" data-field="paint_type" onchange="getColors(this, ${newRowId})" required>
                            <option value="" disabled>Select Paint Type</option>
                            ${generateOptions(@json($paintTypes))}
                        </select>
                    </td>
                    <td>
                        <select name="color_id[${newRowId}][]" class="form-control select2 color-select" data-field="color" id="fetchColor-${newRowId}" multiple required>
                        </select>
                    </td>
                    <td>
                        <select name="color_effect_id[${newRowId}]" class="form-control select2" data-field="color_effect" required>
                            <option value="" disabled>Select Color Effect</option>
                            ${generateOptions(@json($colorEffects))}
                        </select>
                    </td>
                    <td>
                        <input type="number" name="pricing[${newRowId}]" class="form-control pricinginput" step="0.01" min="0" value="${pricing || ''}" required>
                    </td>
                    <td>
                        <div class="btn-container">
                            <button type="button" class="btn btn-danger btn-sm remove-row" title="Remove"><i class="ti ti-minus"></i></button>
                            <button type="button" class="btn btn-info btn-sm copy-row" title="Copy"><i class="ti ti-copy"></i></button>
                            <button type="button" class="btn btn-success btn-sm add-row" title="Add"><i class="ti ti-plus"></i></button>
                        </div>
                    </td>
                </tr>
            `;
            
            // Insert new row after current row
            $currentRow.after(newRow);
            
            // Get reference to new row
            const $newRow = $currentRow.next();
            
            // Initialize Select2 for non-color selects first
            $newRow.find('.select2').not('.color-select').each(function() {
                const $select = $(this);
                const select2Config = {
                    width: '100%',
                    allowClear: true,
                    tags: true,
                    createTag: function (params) {
                        const term = $.trim(params.term);
                        if (term === '') {
                            return null;
                        }
                        return {
                            id: 'new_' + term,
                            text: term,
                            newTag: true
                        };
                    }
                };
                $select.select2(select2Config);
            });
            
            // Set values for non-color selects first
            if (sizeId) {
                $newRow.find('select[name*="size_id"]').val(sizeId).trigger('change');
            }
            if (thicknessId) {
                $newRow.find('select[name*="thickness_id"]').val(thicknessId).trigger('change');
            }
            if (finishId) {
                $newRow.find('select[name*="finish_id"]').val(finishId).trigger('change');
            }
            if (colorEffectId) {
                $newRow.find('select[name*="color_effect_id"]').val(colorEffectId).trigger('change');
            }
            
            // Handle paint type and colors
            if (paintTypeId) {
                $newRow.find('select[name*="paint_type_id"]').val(paintTypeId).trigger('change');
                
                // Fetch colors for the paint type, then set selected colors
                setTimeout(() => {
                    const paintTypeSelect = $newRow.find('select[name*="paint_type_id"]')[0];
                    getColors(paintTypeSelect, newRowId);
                    
                    // Wait for colors to load, then set selected colors
                    setTimeout(() => {
                        const $colorSelect = $newRow.find('select[name*="color_id"]');
                        if (colorIds && colorIds.length > 0) {
                            // Make sure Select2 is initialized before setting values
                            if (!$colorSelect.hasClass('select2-hidden-accessible')) {
                                $colorSelect.select2({
                                    width: '100%',
                                    allowClear: true,
                                    tags: true,
                                    multiple: true,
                                    createTag: function (params) {
                                        const term = $.trim(params.term);
                                        if (term === '') {
                                            return null;
                                        }
                                        return {
                                            id: 'new_' + term,
                                            text: term,
                                            newTag: true
                                        };
                                    }
                                });
                            }
                            $colorSelect.val(colorIds).trigger('change');
                        }
                    }, 800);
                }, 200);
            } else {
                // Initialize color select even if no paint type
                $newRow.find('select[name*="color_id"]').select2({
                    width: '100%',
                    allowClear: true,
                    tags: true,
                    multiple: true,
                    createTag: function (params) {
                        const term = $.trim(params.term);
                        if (term === '') {
                            return null;
                        }
                        return {
                            id: 'new_' + term,
                            text: term,
                            newTag: true
                        };
                    }
                });
            }
        });

        function generateOptions(data) {
            let options = "";
            for (const id in data) {
                options += `<option value="${data[id].id}">${data[id].name}</option>`;
            }
            return options;
        }
    });
</script>


<script>
    $(document).ready(function() {
        $('#manufacturer_id').select2({
            tags: true, // Allows adding new entries
            placeholder: "Select or add a manufacturer",
            allowClear: true
        });
    });
    $(document).ready(function() {
        function reloadSelect2(element) {
            $(element).select2('destroy').select2({
                placeholder: `Select ${$(element).data('name')}`,
                dropdownParent: $(element).parent()
            });
        }
        reloadSelect2();
        $('#division_id').on('change', function() {
            var idDivision = $(this).val();

            getDivision(idDivision);
        });

        function getDivision(idDivision) {

            $.ajax({
                url: "{{url('admin/product/fetch-specification')}}",
                type: "POST",
                data: {
                    division_id: idDivision,
                    _token: '{{csrf_token()}}'
                },
                dataType: 'json',
                success: function(result) {
                    $("#specification_id").html(''); // Clear existing options
                    $("#specification_id").append('<option value="" selected disabled> -- Select Specification --</option>');

                    $.each(result.specification, function(key, value) {
                        $("#specification_id").append('<option value="' + value.id + '">' + value.specification_number + ' (' + value.material_type + ')</option>');
                    });


                    // Reinitialize Select2
                    reloadSelect2('#specification_id');
                }
            });
        }


    });
</script>
<script>
    function getColors(element, i) {
        let paintTypeId = $(element).val();
        // Correct way to get the selected value 
        var colorSelect = $('#fetchColor-' + i);
        if (!paintTypeId) {
            colorSelect.html('');
            colorSelect.html('<option value="">Select Color</option>');
            return;
        }

        $.ajax({
            url: "{{ url('admin/product/fetch-colors') }}",
            type: "POST",
            data: {
                paint_type_id: paintTypeId,
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                console.log(response, 'dasi');
                let options = '<option value="">Select Color</option>';
                response.forEach(color => {
                    options += `<option value="${color.id}">${color.name}</option>`;
                });
                colorSelect.html(options);
                
                // Reinitialize Select2 with tags enabled
                colorSelect.select2('destroy').select2({
                    width: '100%',
                    allowClear: true,
                    tags: true,
                    multiple: true,
                    createTag: function (params) {
                        const term = $.trim(params.term);
                        if (term === '') {
                            return null;
                        }
                        return {
                            id: 'new_' + term,
                            text: term,
                            newTag: true
                        };
                    }
                });
            },
            error: function(xhr) {
                console.error('Error fetching colors:', xhr.responseText);
            }
        });
    }
</script>
<script>
    $(document).ready(function() {
        $('.summernote').summernote({
            toolbar: [
                ['table', ['table']],
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['font', ['strikethrough', 'superscript', 'subscript']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'video']],
                ['height', ['height']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
        // Image preview functionality for the file input
        document.getElementById("logo-id").addEventListener("change", function(event) {
            var output = document.querySelector(".thumbnail.img-preview");
            if (event.target.files[0]) {
                var reader = new FileReader();
                reader.onload = function() {
                    output.src = reader.result;
                }
                reader.readAsDataURL(event.target.files[0]);
                $('.img-preview').show();
            }
        });
    });
</script>
@endpush
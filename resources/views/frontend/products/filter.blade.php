@extends('frontend.layouts.app')
@push('seo')
<title> Product {{env('APP_NAME','Wisselbanken')}}</title>
@endpush
@push('css')
<link href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css" rel="stylesheet"><!-- DataTables Responsive CSS -->
<link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css" rel="stylesheet">

<style>
    #example tr {
        cursor: pointer;
    }

    .filter-container {
        display: flex;
        flex-wrap: nowrap;
        justify-content: flex-start;
        overflow-x: auto;
        gap: 1px;
        padding: 5px 0;
    }

    .filter-card {
        flex: 1.5;
        min-width: 200px;
        max-width: 280px;
        font-size: 11px;
        padding: 5px;
        border: 1px solid #cccccc;
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        background: #fff;
        transition: none;
        position: relative;
        z-index: 1;
        box-shadow: 0 4px 12px rgba(88, 32, 32, 0.15);
    }

    .filter-card.expanded {
        flex: 1.5;
        min-width: 200px;
        max-width: 280px;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(88, 32, 32, 0.15);
        border-color: #582020;
    }

    .filter-card.active {
        border-color: #582020;
        background: #fdf5f5;
    }

    .filter-card.completed {
        border-color: #D4A020;
        background: #fffdf5;
        cursor: pointer;
    }

    .filter-card.completed:hover {
        /* Removed hover effects - keep static expanded look */
    }

    .filter-card.completed:hover,
    .filter-card.hover-expanded,
    .filter-card.active:hover {
        /* Removed hover effects - keep static expanded look */
    }

    .filter-card.completed:hover .filterBody,
    .filter-card.active:hover .filterBody,
    .filter-card.hover-expanded .filterBody {
        /* Removed hover effects - keep static expanded look */
    }

    .filter-card.hover-expanded {
        /* Removed hover effects - keep static expanded look */
    }

    .filter-card.hover-expanded .filterBody {
        /* Removed hover effects - keep static expanded look */
    }

    .filterTitle {
        font-weight: 700;
        font-size: 12px;
        padding: 6px;
        text-transform: uppercase;
        border-bottom: 1px solid #cccccc;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
    }

    .filterTitle:hover {
        /* Removed hover effect */
    }

    .filter-card.expanded .filterTitle {
        background: #582020;
        color: white;
        border-bottom-color: #3d1515;
    }

    .filter-card.completed .filterTitle {
        background: #D4A020;
        color: white;
        border-bottom-color: #b88a1a;
    }

    .filter-card.completed .filterTitle::after {
        content: " ✓";
        font-weight: bold;
    }

    .filter-card.completed .filterTitle:hover::after {
        content: " ✓";
    }

    .filter-card.active .filterTitle::after {
        content: " →";
        font-weight: bold;
    }

    .filter-card.disabled {
        opacity: 0.5;
        pointer-events: none;
    }

    .filter-card.disabled .filterTitle {
        cursor: not-allowed;
        background: #f8f9fa;
        color: #6c757d;
    }

    .filter-card:hover:not(.disabled) {
        /* Removed hover effects - keep static look */
    }

    .filterTitle-required::after {
        content: " *";
        color: red;
    }

    .filterBody {
        flex-grow: 1;
        overflow-y: auto;
        padding: 5px;
        overflow-x: auto;
        box-sizing: border-box;
        height: 250px;
        max-height: 50vh;
        transition: none;
    }

    .filter-card.expanded .filterBody {
        height: 250px;
        max-height: 50vh;
    }

    .filter ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .filterBody li {
        display: flex;
        align-items: center;
        white-space: nowrap;
        /* overflow-x: auto; */
    }

    .filterBody label {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        /* overflow-x: auto; */
        max-width: 100%;
        margin-bottom: 0px;
    }

    .filterBody input[type="checkbox"] {
        margin-right: 5px;
        flex-shrink: 0;
    }


    .shop-product-fillter-header {
        border: none;
        margin-bottom: 10px;
        padding: 0px;
        box-shadow: none;
    }

    .shop-product-fillter-header .categor-list li+li {
        border-top: 0px solid #f7f8f9;
        padding-top: 0px;
        margin-top: 0px;
    }

    .shop-product-fillter-header .categor-list li {
        font-size: 10px;
    }

    .custom-check {
        width: 16px;
        height: 16px;
    }

    #scrollUp {
        display: none !important;
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

    /* Ensuring table styling is professional */
    .table-container {
        overflow-x: auto;
    }

    table.dataTable {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 10px;
        text-align: left;
        vertical-align: middle;
    }

    .quantity-input {
        width: 70px;
        text-align: center;
    }

    .quantity-input {
        padding-left: 0px;
    }

    .btn-custom {
        padding: 4px 8px !important;
        font-size: 8px !important;
        text-transform: none !important;
        line-height: 1.8 !important;
        margin-left: 55px;
        position: absolute;
        bottom: 0px;
        margin-bottom: 2px;
    }

    #example tbody td:first-child {
        white-space: nowrap !important;
        overflow: visible !important;
    }

    .sticky-col:nth-child(2) {
        min-width: 200px;
    }

    /* Shopping List Popup Styles */
    .shopping-list-popup {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50%;
        min-width: 50%;
        max-width: 600px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        display: none;
        flex-direction: column;
        overflow: hidden;
        font-family: Arial, sans-serif;
        border: 1px solid #ddd;
    }

    .shopping-list-popup-close {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 32px;
        height: 32px;
        padding: 0;
        border: none;
        border-radius: 50%;
        background: #fff;
        color: #c00;
        font-size: 24px;
        line-height: 1;
        cursor: pointer;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        transition: background 0.2s, color 0.2s;
    }
    .shopping-list-popup-close:hover {
        background: #c00;
        color: #fff;
    }
    .shopping-list-popup-close span {
        line-height: 1;
        margin-top: -2px;
    }

    /* Red X for product detail modal */
    #productDetailModal .btn-close-red {
        filter: invert(27%) sepia(98%) saturate(5000%) hue-rotate(355deg);
        opacity: 1;
    }
    #productDetailModal .btn-close-red:hover {
        opacity: 0.85;
    }

    .shopping-list-body {
        padding: 15px;
        max-height: 60vh;
        overflow-y: auto;
    }

    .shopping-list-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        gap: 15px;
        scrollbar-width: thin;
        scrollbar-color: #582020 #f1f1f1;
    }

    .shopping-list-list::-webkit-scrollbar {
        height: 6px;
    }

    .shopping-list-list::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .shopping-list-list::-webkit-scrollbar-thumb {
        background-color: #582020;
        border-radius: 6px;
    }

    .list-item {
        display: flex;
        width: 255px;
        min-width: 255px;
        max-width: 255px;
        padding: 10px 0px 10px 0px;
        border: 1px solid #eee;
        border-radius: 8px;
        position: relative;
        flex-shrink: 0;
    }

    .list-item-img {
        width: 60px;
        height: 60px;
        margin-right: 5px;
        flex-shrink: 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
    }

    .list-item-img img {
        max-width: 60px;
        max-height: 60px;
        object-fit: contain;
    }

    .list-item-content {
        flex-grow: 1;
        position: relative;
        min-width: 0;
    }

    .list-item-title {
        font-size: 12px;
        margin: 0 0 5px 0;
        /* font-weight: bold; */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .list-item-title a {
        color: #333;
        text-decoration: none;
    }

    .list-item-details {
        font-size: 10px;
        color: #666;
        margin: 0 0 5px 0;
        line-height: 1.4;
    }

    /* .list-item-price {
        font-size: 10px;
        font-weight: bold;
        margin: 10px 0 0 0;
    } */

    .list-item-delete {
        position: absolute;
        cursor: pointer;
        top: -3px;
        right: -3px;
    }

    .list-item-delete a {
        color: #ff0000;
        font-size: 16px;
        background: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .highlight-color {
        color: #582020;
        font-weight: bold;
    }

    .shopping-list-footer {
        padding: 15px;
        background: #f8f9fa;
        border-top: 1px solid #ddd;
    }

    /* .shopping-list-total {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        font-size: 16px;
    } */
    .btn-md {
        padding: 8px 20px !important;
        font-size: 12px !important;
    }

    .shopping-list-buttons {
        display: flex;
        gap: 10px;
    }

    .shopping-list-trigger {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #582020;
        color: white;
        border-radius: 4px;
        padding: 8px 12px;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        z-index: 999;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .shopping-list-count {
        background: #ff5722;
        color: white;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .list-empty {
        padding: 20px;
        text-align: center;
        color: #666;
        width: 100%;
    }

    @media (max-width: 768px) {
        .shopping-list-popup {
            width: 90%;
            min-width: 90%;
            max-width: 90%;
            right: 5%;
        }

        .list-item {
            width: 280px;
            min-width: 280px;
            max-width: 280px;
        }
    }

    .btn-outline-dark {
        background: white !important;
        color: #4A171E !important;
        border-color: #4A171E !important;
    }

    @media (max-width: 767.98px) {
        .table-container {
            overflow-x: auto;
            position: relative;
        }
        #example {
            min-width: 900px;
        }
        .table th, .table td {
            white-space: nowrap;
        }
        /* Scroll hint gradient */
        .table-container::after {
            content: '';
            position: absolute;
            top: 0; right: 0; height: 100%; width: 30px;
            pointer-events: none;
            background: linear-gradient(to left, #fff 60%, rgba(255,255,255,0));
            z-index: 2;
            display: block;
        }
    }
    .swal2-html-container label {
        display: none !important;
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
    /* Remove any forced stacking for table rows/cells */
    @media (max-width: 767.98px) {
        table, thead, tbody, tr, th, td {
            display: revert !important;
        }
    }
</style>

@endpush
@section('content')

<main class="main">

    <div id="toast-container"></div>
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{url('/')}}" rel="nofollow">Home</a>
                <span></span> Products
            </div>
        </div>
    </div>
    <section class="mt-2 mb-4">
        <div class="container-fluid px-4">
            <div class="row">
                <div class="col-lg-12">
                    <a class="shop-filter-toogle active open" href="#">
                        <span class="fi-rs-filter mr-5"></span>
                        Filters
                        <i class="fi-rs-angle-small-down angle-down"></i>
                        <i class="fi-rs-angle-small-up angle-up"></i>
                    </a>

                    <div class="shop-product-fillter-header">
                        <div class="filter-note" id="filter-note">
                            <p><strong>Note:</strong> Please select all filters to view the products.
                            </p>
                            <button onclick="document.getElementById('filter-note').style.display='none'" style="float: right; background: none; border: none; font-size: 16px; cursor: pointer;">&times;</button>
                        </div>

                        <div class="filter-container">

                            <div class="filter-card" id="filter-Divisions">
                                <div class="filterTitle filterTitle-required">Divisions</div>
                                <ul class="categor-list filterBody">
                                    @foreach ($divisions as $item)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="divisionsIds[]" value="{{ $item->id }}" {{ isset($item->code) && $item->code == $division_filter ? 'checked' : '' }}>
                                            {{ isset($item->code) ? $item->code . ' - ' : '' }}{{ isset($item->name) ? ucfirst($item->name) : '' }}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Specifications">
                                <div class="filterTitle filterTitle-required">Specifications</div>
                                <ul class="categor-list filterBody">
                                    @foreach ($specifications as $item)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="specificationsIds[]" value="{{ $item->id }}" {{ isset($item->specification_number) && $item->specification_number == $specification_filter ? 'checked' : '' }}>
                                            {{ isset($item->specification_number) ? $item->specification_number . ' - ' : '' }}{{ isset($item->material_type) ? $item->material_type : '' }}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Manufacturers">
                                <div class="filterTitle filterTitle-required">Manufacturers</div>
                                <ul class="categor-list filterBody">
                                    @foreach ($manufacturers as $item)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="manufacturersIds[]" value="{{ $item->id }}" {{ isset($item->id) && $item->id == $manufacturer_filter ? 'checked' : '' }}>
                                            {{ isset($item->name) ? ucfirst($item->name) : '' }}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Products">
                                <div class="filterTitle filterTitle-required">Products</div>
                                <ul class="categor-list filterBody"></ul>
                            </div>

                            <div class="filter-card" id="filter-Sizes">
                                <div class="filterTitle filterTitle-required">Sizes</div>
                                <ul class="categor-list filterBody"></ul>
                            </div>

                            <div class="filter-card" id="filter-Thickness">
                                <div class="filterTitle filterTitle-required">Thickness</div>
                                <ul class="categor-list filterBody"></ul>
                            </div>

                            <div class="filter-card" id="filter-Finishes">
                                <div class="filterTitle filterTitle-required">Finishes</div>
                                <ul class="categor-list filterBody"></ul>
                            </div>

                            <div class="filter-card" id="filter-PaintTypes">
                                <div class="filterTitle filterTitle-required">Paint Types</div>
                                <ul class="categor-list filterBody"></ul>
                            </div>

                            <div class="filter-card" id="filter-Colors">
                                <div class="filterTitle filterTitle-required">Colors</div>
                                <ul class="categor-list filterBody"></ul>
                            </div>

                            <div class="filter-card" id="filter-ColorsEffect">
                                <div class="filterTitle">Colors Effect</div>
                                <ul class="categor-list filterBody"></ul>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="row product-grid-3">
                        <div class="table-container">
                            <div class="filter-note" id="filter-note">
                                <p>
                                    <strong>Note:</strong> Click on the checkbox in the table below
                                    <i class="fi-rs-checkbox"></i> to add the product to the list or pallet.
                                </p>
                                <button onclick="document.getElementById('filter-note').style.display='none'"
                                    style="float: right; background: none; border: none; font-size: 16px; cursor: pointer;">
                                    &times;
                                </button>
                            </div>
                            
                            <table id="example" class="display table table-bordered table-hover nowrap" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th class="bg-primary sticky-col" style="width:2%"></th>
                                        <th class="bg-primary sticky-col">Product</th>
                                        <th class="bg-primary">Quantity</th>
                                        <th class="bg-primary">Price</th>
                                        <th class="bg-primary">Subtotal</th>
                                        <th class="bg-primary">Division</th>
                                        <th class="bg-primary">Specification</th>
                                        <th class="bg-primary">Manufacturer</th>
                                        <th class="bg-primary">Size</th>
                                        <th class="bg-primary">Thickness</th>
                                        <th class="bg-primary">Finish</th>
                                        <th class="bg-primary">Paint Type</th>
                                        <th class="bg-primary">Color</th>
                                        <th class="bg-primary">Color Effect</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>

                        </div>


                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Shopping List Popup -->
    <div class="shopping-list-popup" id="shoppingListPopup">
        <button type="button" class="shopping-list-popup-close" id="shoppingListPopupClose" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <div class="shopping-list-body">
            <ul class="shopping-list-list" id="shoppingListItems">
                <li class="list-empty">Your list is empty</li>
            </ul>
        </div>
        <div class="shopping-list-footer">
            <!-- <div class="shopping-list-total">
                <h4>Total: <span id="listTotal">$0.00</span></h4>
            </div> -->
            <div class="shopping-list-buttons">
                <!-- <a href="javascript:void(0)" class="btn btn-outline-primary" id="compareProductbtn">Compare Product</a> -->
                <a href="javascript:void(0)" class="btn btn-md btn-warning" id="saveListBtn">Add to List</a>
                <a href="javascript:void(0)" class="btn btn-md btn-primary" id="addToPalletBtn"><i class="fi-rs-shopping-cart-add"></i> Add to Pallet</a>
            </div>
        </div>
    </div>
</main>
<!-- Loader -->
<div id="loader" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        Loading...
    </div>
</div>
<!-- Large Modal -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailModalLabel"></h5>
                <button type="button" class="btn-close btn-close-red" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBodyContent">
                <!-- Product details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script><!-- DataTables Responsive JS -->
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
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
        // CSRF Token Management
        function refreshCsrfToken() {
            $.ajax({
                url: "{{ route('home') }}",
                type: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(data, status, xhr) {
                    const newToken = xhr.getResponseHeader('X-CSRF-TOKEN');
                    if (newToken) {
                        $('meta[name="csrf-token"]').attr('content', newToken);
                        $('input[name="_token"]').val(newToken);
                    }
                }
            });
        }

        // Refresh CSRF token every 30 minutes
        setInterval(refreshCsrfToken, 30 * 60 * 1000);

        // Function to retry DataTable Ajax call
        function retryDataTableAjax() {
            if (typeof table !== 'undefined' && table) {
                refreshCsrfToken();
                setTimeout(() => {
                    table.ajax.reload();
                }, 1000);
            }
        }

        // Shopping List functionality
        const shoppingList = {
            items: [],

            init: function() {
                this.loadFromSession();
                this.bindEvents();
            },

            bindEvents: function() {
                // Close popup (red X)
                $('#shoppingListPopupClose').on('click', () => this.hidePopup());
                // Save list button
                $('#saveListBtn').click(() => this.showSaveListDialog());

                        // Add to pallet button
        $('#addToPalletBtn').click(() => this.addToPallet());

                // Compare button
                // $('#compareBtn').click(() => this.compareItems());

                // Table checkbox changes
                $(document).on('change', '.variation-checkbox', function() {
                    const $row = $(this).closest('tr');
                    const quantity = parseInt($row.find('.quantity-input').val()) || 0;
                    const isChecked = $(this).prop('checked');
                    if (isChecked && quantity === 0) {
                        showToast("Quantity should be at least 1.", "info");
                        $(this).prop('checked', false);
                        return;
                    }

                    if (isChecked && quantity > 0) {
                        shoppingList.addItem($row);
                    } else {
                        shoppingList.removeItem($(this).data('variation-id'));
                    }
                });

                // Quantity changes
                $(document).on('input', '.quantity-input', function() {
                    const $row = $(this).closest('tr');
                    const variationId = $row.find('.variation-checkbox').data('variation-id');
                    const quantity = parseInt($(this).val()) || 0;

                    shoppingList.updateQuantity(variationId, quantity);
                });
            },

            loadFromSession: function() {
                const sessionItems = sessionStorage.getItem('tempShoppingList');
                if (sessionItems) {
                    this.items = JSON.parse(sessionItems);
                    this.updateUI();
                }
            },

            saveToSession: function() {
                sessionStorage.setItem('tempShoppingList', JSON.stringify(this.items));
            },

            clearSession: function() {
                sessionStorage.removeItem('tempShoppingList');
            },

            showPopup: function() {
                $('#shoppingListPopup').show();
            },

            togglePopup: function() {
                $('#shoppingListPopup').toggle();
            },

            hidePopup: function() {
                $('#shoppingListPopup').hide();
            },

            addItem: function($row) {
                const variationId = $row.find('.variation-checkbox').data('variation-id');
                const existingItem = this.items.find(item => item.variationId === variationId);

                if (existingItem) {
                    existingItem.quantity = parseInt($row.find('.quantity-input').val()) || 1;
                } else {
                    this.items.push({
                        variationId: variationId,
                        productColorVariationId: $row.find('.variation-checkbox').data('product-color-variation-id'),
                        colorId: $row.find('.variation-checkbox').data('color-id'),
                        productName: $row.find('td:eq(1)').text(),
                        price: parseFloat($row.find('td:eq(3)').text().replace('$', '')),
                        quantity: parseInt($row.find('.quantity-input').val()) || 1,
                        size: $row.find('td:eq(8)').text(),
                        thickness: $row.find('td:eq(9)').text(),
                        finish: $row.find('td:eq(10)').text(),
                        paintType: $row.find('td:eq(11)').text(),
                        color: $row.find('td:eq(12)').text(),
                        colorEffect: $row.find('td:eq(13)').text(),
                        imageUrl: $row.find('img').attr('src') || "{{ asset('demo.jpg') }}"
                    });
                }

                this.saveToSession();
                this.updateUI();
                this.showPopup();
            },

            removeItem: function(variationId) {
                this.items = this.items.filter(item => item.variationId !== variationId);
                this.saveToSession();
                this.updateUI();
                // Also uncheck the corresponding checkbox in the table
                $(`.variation-checkbox[data-variation-id="${variationId}"]`).prop('checked', false);

                // Reset quantity to 1 for the removed item
                $(`.variation-checkbox[data-variation-id="${variationId}"]`)
                    .closest('tr')
                    .find('.quantity-input')
                    .val('0');
            },

            updateQuantity: function(variationId, quantity) {
                const item = this.items.find(item => item.variationId === variationId);
                if (item) {
                    item.quantity = quantity;
                    this.saveToSession();
                    this.updateUI();
                }
            },

            clearAll: function() {
                this.items = [];
                this.clearSession();
                this.updateUI();
                $('.variation-checkbox').prop('checked', false);
                $('.quantity-input').val('1');
            },

            updateUI: function() {
                // Update count badge
                $('#shoppingListCount').text(this.items.length);

                // Update list items
                const $list = $('#shoppingListItems');

                if (this.items.length === 0) {
                    $list.html('<li class="list-empty">Your list is empty</li>');
                    $('#listTotal').text('$0.00');
                    // $('#compareBtn').prop('disabled', true);
                    return;
                }

                let html = '';
                let total = 0;

                this.items.forEach(item => {
                    const subtotal = item.price * item.quantity;
                    total += subtotal;

                    html += `
                <li class="list-item">
                    <div class="list-item-delete">
                        <a href="javascript:void(0)" class="remove-list-item" data-id="${item.variationId}">
                            <i class="fi-rs-cross-small"></i>
                        </a>
                    </div>
                    <div class="list-item-img">
                        <a href="javascript:void(0)">
                            <img src="${item.imageUrl}" alt="${item.productName}">
                        </a>
                    </div>
                    <div class="list-item-content">
                        <h4 class="list-item-title" title="${item.productName}">
                            <a href="javascript:void(0)">${item.productName}</a>
                        </h4>
                        <p class="list-item-details">
                            <span>Size: ${item.size}</span>,
                            <span>Thickness: ${item.thickness}</span><br>
                            <span>Finish: ${item.finish}</span>,
                            <span>Paint Type: ${item.paintType}</span><br>
                            <span class="highlight-color">Color: ${item.color}</span>,
                            <span>Color Effect: ${item.colorEffect}</span>
                        </p>
                    </div>
                </li>
            `;
                });

                $list.html(html);
                $('#listTotal').text('$' + total.toFixed(2));
                // $('#compareBtn').prop('disabled', false);

                // Bind remove item events

            },

            showSaveListDialog: function() {
                if (this.items.length === 0) {
                    showToast('Your list is empty', 'error');
                    return;
                }

                // First get user's saved lists and states
                Promise.all([
                    $.get("{{ route('get-saved-lists') }}"),
                    $.get("{{ route('get-states') }}")
                ]).then(([response, states]) => {
                    // Build the HTML for the dialog
                    let html = `
                         <div class="form-group">`;

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

                    // Show SweetAlert with the options
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
                        if (result.value.listId) {
                            // Existing list - no need for address
                            this.saveList(result.value.listId, result.value.newName);
                        } else {
                            // New list with address
                            this.saveListWithAddress(result.value.newName, {
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
                    // Refresh CSRF token and retry
                    refreshCsrfToken();
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
                            this.showSaveListDialog();
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
            },

            saveList: function(listId, newName) {
                $('#loader').show();

                const itemsToSave = this.items.map(item => ({
                    variationId: item.variationId,
                    colorId: item.colorId,
                    quantity: item.quantity,
                    productName: item.productName,
                    price: item.price,
                    productColorVariationId: item.productColorVariationId
                }));

                $.ajax({
                    url: "{{ route('save-shopping-list') }}",
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    data: {
                        list_id: listId,
                        name: newName,
                        items: itemsToSave
                    },
                    success: (response) => {
                        Swal.fire({
                            title: 'Success',
                            text: response.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        this.clearAll();
                        this.hidePopup();
                        // Update list count in navbar
                        if (typeof updateListCount === 'function') {
                            updateListCount();
                        }
                    },
                    error: (xhr) => {
                        if (xhr.status === 419) { // CSRF Token Mismatch
                            // Refresh CSRF token and retry
                            refreshCsrfToken();
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
                                    this.saveList(listId, newName);
                                }
                            });
                        } else if (xhr.status === 401) { // Unauthorized
                            Swal.fire({
                                title: 'Login Required',
                                text: xhr.responseJSON?.message || 'Please login to add products to your list.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Login',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = "{{ route('login') }}";
                                }
                            });
                        } else if (xhr.status === 403) {
                            // Check if it's a role issue or email verification issue
                            if (xhr.responseJSON && xhr.responseJSON.message && xhr.responseJSON.message.includes('email address is not verified')) {
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
                                // Role restriction (admin trying to access user feature)
                                Swal.fire({
                                    title: 'Access Restricted',
                                    text: xhr.responseJSON?.message || 'Please login as a user to add products to your list. Admin accounts cannot create lists.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Failed to save your list. Please try again.',
                                icon: 'error'
                            });
                        }
                    },
                    complete: () => {
                        $('#loader').hide();
                    }
                });
            },

            saveListWithAddress: function(listName, addressData) {
                $('#loader').show();

                const itemsToSave = this.items.map(item => ({
                    variationId: item.variationId,
                    colorId: item.colorId,
                    quantity: item.quantity,
                    productName: item.productName,
                    price: item.price,
                    productColorVariationId: item.productColorVariationId
                }));

                $.ajax({
                    url: "{{ route('save-shopping-list') }}",
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    data: {
                        name: listName,
                        items: itemsToSave,
                        ...addressData
                    },
                    success: (response) => {
                        Swal.fire({
                            title: 'Success',
                            text: response.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        this.clearAll();
                        this.hidePopup();
                        // Update list count in navbar
                        if (typeof updateListCount === 'function') {
                            updateListCount();
                        }
                    },
                    error: (xhr) => {
                        if (xhr.status === 419) { // CSRF Token Mismatch
                            // Refresh CSRF token and retry
                            refreshCsrfToken();
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
                                    this.saveListWithAddress(listName, addressData);
                                }
                            });
                        } else if (xhr.status === 401) { // Unauthorized
                            Swal.fire({
                                title: 'Login Required',
                                text: xhr.responseJSON?.message || 'Please login to add products to your list.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Login',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = "{{ route('login') }}";
                                }
                            });
                        } else if (xhr.status === 403) {
                            // Check if it's a role issue or email verification issue
                            if (xhr.responseJSON && xhr.responseJSON.message && xhr.responseJSON.message.includes('email address is not verified')) {
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
                                // Role restriction (admin trying to access user feature)
                                Swal.fire({
                                    title: 'Access Restricted',
                                    text: xhr.responseJSON?.message || 'Please login as a user to add products to your list. Admin accounts cannot create lists.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Failed to save your list. Please try again.',
                                icon: 'error'
                            });
                        }
                    },
                    complete: () => {
                        $('#loader').hide();
                    }
                });
            },

            addToPallet: function() {
                if (this.items.length === 0) {
                    showToast('Your list is empty', 'error');
                    return;
                }

                // Check if we already have address info in the pallet
                // For logged-in users, check database; for guests, check session
                const isLoggedIn = "{{ Auth::check() ? 'true' : 'false' }}" === 'true';
                
                if (isLoggedIn) {
                    // For logged-in users, check if they have any pallet items with address
                    $.ajax({
                        url: "{{ route('check-pallet-address') }}",
                        type: 'GET',
                        success: (response) => {
                            if (response.hasAddress) {
                                // Already have address info, just add items
                                this.addItemsToPallet();
                            } else {
                                // Need to collect address info first
                                this.showPalletAddressForm();
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
                            } else if (xhr.status === 403 || (xhr.responseJSON && xhr.responseJSON.message && xhr.responseJSON.message.includes('email address is not verified'))) {
                                // Email verification required
                                Swal.fire({
                                    title: 'Email Verification Required',
                                    text: 'You need to verify your email address before you can add items to pallet. Please check your email for a verification link.',
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
                                // If check fails, show address form to be safe
                                this.showPalletAddressForm();
                            }
                        }
                    });
                } else {
                    // For guests, check session storage
                    const pallet = sessionStorage.getItem('pallet') ? JSON.parse(sessionStorage.getItem('pallet')) : [];
                    const hasAddressInfo = pallet.length > 0 && (
                        (pallet[0].address1 && pallet[0].city && pallet[0].state && pallet[0].postcode) ||
                        (pallet[0].address_data && pallet[0].address_data.address1 && pallet[0].address_data.city && pallet[0].address_data.state_id && pallet[0].address_data.postcode)
                    );

                    if (hasAddressInfo) {
                        // Already have address info, just add items
                                                        this.addItemsToPallet();
                    } else {
                        // Need to collect address info first
                        this.showPalletAddressForm();
                    }
                }
            },

            showPalletAddressForm: function() {
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
                        this.addItemsToPallet(result.value);
                    }
                });
            });
            },

            addItemsToPallet: function(addressData = null) {
                $('#loader').show();

                const itemsToAdd = this.items.map(item => ({
                    variationId: item.variationId,
                    colorId: item.colorId,
                    quantity: item.quantity,
                    productName: item.productName,
                    price: item.price,
                    productColorVariationId: item.productColorVariationId
                }));

                const requestData = {
                    items: itemsToAdd
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
                    success: (response) => {
                        showToast(`Items added to pallet`, 'success');
                        updateMiniPallet();
                        this.clearAll();
                        this.hidePopup();
                        // Refresh the table to show updated data
                        if (typeof table !== 'undefined' && table) {
                            table.ajax.reload();
                        }
                    },
                    error: (xhr) => {
                        if (xhr.status === 419) { // CSRF Token Mismatch
                            // Refresh CSRF token and retry
                            refreshCsrfToken();
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
                                    this.addItemsToPallet(addressData);
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
                        } else if (xhr.status === 403 || (xhr.responseJSON && xhr.responseJSON.message && xhr.responseJSON.message.includes('email address is not verified'))) {
                            // Email verification required
                            Swal.fire({
                                title: 'Email Verification Required',
                                text: 'You need to verify your email address before you can add items to pallet. Please check your email for a verification link.',
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
                            showToast(xhr.responseJSON?.message || 'Error adding items to pallet', 'error');
                        }
                    },
                    complete: () => {
                        $('#loader').hide();
                    }
                });
            },

        
        };

        // Initialize shopping list
        shoppingList.init();

        // Clear session storage on page refresh
        $(window).on('beforeunload', function() {
            sessionStorage.removeItem('tempShoppingList');
        });

        // Update list count on page load
        if (typeof updateListCount === 'function') {
            updateListCount();
        }

        // Migrate session pallet to database if user is logged in and has session data
        function migrateSessionPallet() {
            const sessionPallet = sessionStorage.getItem('pallet');
            if (sessionPallet && sessionPallet !== '[]' && sessionPallet !== '{}') {
                $.ajax({
                    url: "{{ route('migrate-session-pallet') }}",
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            // Clear session storage after successful migration
                            sessionStorage.removeItem('pallet');
                            showToast('Your pallet items have been saved to your account', 'success');
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 401) {
                            // User not logged in, keep session data
                            return;
                        } else if (xhr.status === 403 || (xhr.responseJSON && xhr.responseJSON.message && xhr.responseJSON.message.includes('email address is not verified'))) {
                            // Email verification required - show alert but don't clear session data
                            Swal.fire({
                                title: 'Email Verification Required',
                                text: 'You need to verify your email address before your pallet items can be saved to your account. Please check your email for a verification link.',
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
                            showToast('Error migrating pallet data', 'error');
                        }
                    }
                });
            }
        }

        // Check if user is logged in and migrate session data
        var isLoggedIn = "{{ Auth::check() ? 'true' : 'false' }}" === 'true';
        if (isLoggedIn) {
            migrateSessionPallet();
        }

        $(document).on('click', '.remove-list-item', (e) => {
            const variationId = $(e.currentTarget).data('id');
            shoppingList.removeItem(variationId);
            $(`.variation-checkbox[data-variation-id="${variationId}"]`).prop('checked', false);
        });
    });
</script>
<script>
    window.onload = function() {
        setTimeout(function() {
            let filterCategories = ["filter-Divisions", "filter-Specifications", "filter-Manufacturers"];

            for (let i = 0; i < filterCategories.length; i++) {
                let filterCard = document.getElementById(filterCategories[i]);

                if (filterCard) {
                    let checkedCheckbox = filterCard.querySelector("input[type='checkbox']:checked");

                    if (checkedCheckbox) {
                        checkedCheckbox.focus(); // Helps trigger UI adjustments

                        filterCard.scrollIntoView({
                            behavior: "smooth",
                            block: "center",
                            inline: "nearest"
                        });

                        break; // Stop scrolling after first found checked filter
                    }
                }
            }
        }, 800); // Ensure everything is loaded properly
    };

    $(document).ready(function() {

        let divisionIds = [],
            specificationIds = [],
            manufactureIds = [],
            sizesIds = [],
            thicknessIds = [],
            finishIds = [],
            painttypesIds = [],
            colorsIds = [],
            colorEffectIds = [];

        let table;

        // Filter expansion management
        const filterManager = {
            currentStep: 0,
            filterSteps: [
                'filter-Divisions',
                'filter-Specifications', 
                'filter-Manufacturers',
                'filter-Products',
                'filter-Sizes',
                'filter-Thickness',
                'filter-Finishes',
                'filter-PaintTypes',
                'filter-Colors',
                'filter-ColorsEffect'
            ],

            init: function() {
                // Check for pre-selected filters from URL parameters
                // Advance currentStep to the next available filter
                this.initializeCurrentStep();
                
                this.setupFilterClicks();
                this.setupFilterHovers();
                this.updateFilterStates();
            },

            initializeCurrentStep: function() {
                // Start from step 0
                this.currentStep = 0;
                
                // Check each filter step in order
                // If a filter has selections, advance to the next step
                for (let i = 0; i < this.filterSteps.length; i++) {
                    const filterId = this.filterSteps[i];
                    if (this.hasFilterSelection(filterId)) {
                        // This filter has selections, so advance to next step
                        this.currentStep = i + 1;
                    } else {
                        // Found first filter without selections, stop here
                        break;
                    }
                }
                
                // Make sure currentStep doesn't exceed filterSteps length
                if (this.currentStep >= this.filterSteps.length) {
                    this.currentStep = this.filterSteps.length - 1;
                }
            },

            setupFilterClicks: function() {
                // Add click handlers to filter titles
                $('.filterTitle').on('click', (e) => {
                    const $card = $(e.currentTarget).closest('.filter-card');
                    const cardId = $card.attr('id');
                    const stepIndex = this.filterSteps.indexOf(cardId);
                    
                    if (stepIndex !== -1) {
                        // Allow clicking on any completed step or current step
                        if (stepIndex <= this.currentStep) {
                            this.currentStep = stepIndex;
                            this.updateFilterStates();
                            this.expandFilter(cardId);
                            
                            // Force update the visual state
                            setTimeout(() => {
                                this.updateFilterStates();
                            }, 100);
                        }
                    }
                });
            },

            setupFilterHovers: function() {
                // Hover effects disabled - all boxes stay in expanded state
                // No hover handlers needed
            },

            expandFilter: function(filterId) {
                // All boxes stay expanded - no collapsing
                // Scroll to the selected filter
                $(`#${filterId}`)[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'nearest'
                });
            },

            collapseAll: function() {
                // All boxes stay expanded - no collapsing
            },

            updateFilterStates: function() {
                // Reset all states
                $('.filter-card').removeClass('active completed disabled');
                
                // Mark completed steps
                for (let i = 0; i < this.currentStep; i++) {
                    const filterId = this.filterSteps[i];
                    const $card = $(`#${filterId}`);
                    const hasSelection = this.hasFilterSelection(filterId);
                    
                    if (hasSelection) {
                        $card.addClass('completed');
                    }
                }
                
                // Mark current step as active
                if (this.currentStep < this.filterSteps.length) {
                    const currentFilterId = this.filterSteps[this.currentStep];
                    const $currentCard = $(`#${currentFilterId}`);
                    $currentCard.addClass('active');
                    
                    // If current step has selections, also mark as completed
                    if (this.hasFilterSelection(currentFilterId)) {
                        $currentCard.addClass('completed');
                    }
                }
                
                // Mark future steps as disabled
                for (let i = this.currentStep + 1; i < this.filterSteps.length; i++) {
                    const filterId = this.filterSteps[i];
                    $(`#${filterId}`).addClass('disabled');
                }
            },


            hasFilterSelection: function(filterId) {
                switch(filterId) {
                    case 'filter-Divisions':
                        return $("input[name='divisionsIds[]']:checked").length > 0;
                    case 'filter-Specifications':
                        return $("input[name='specificationsIds[]']:checked").length > 0;
                    case 'filter-Manufacturers':
                        return $("input[name='manufacturersIds[]']:checked").length > 0;
                    case 'filter-Products':
                        return $("input[name='productIds[]']:checked").length > 0;
                    case 'filter-Sizes':
                        return $("input[name='sizesIds[]']:checked").length > 0;
                    case 'filter-Thickness':
                        return $("input[name='thicknessIds[]']:checked").length > 0;
                    case 'filter-Finishes':
                        return $("input[name='finishIds[]']:checked").length > 0;
                    case 'filter-PaintTypes':
                        return $("input[name='painttypesIds[]']:checked").length > 0;
                    case 'filter-Colors':
                        return $("input[name='colorsIds[]']:checked").length > 0;
                    case 'filter-ColorsEffect':
                        return $("input[name='colorEffectIds[]']:checked").length > 0;
                    default:
                        return false;
                }
            },

            advanceToNextStep: function() {
                // Collapse current filter
                this.collapseAll();
                
                // Move to next step
                this.currentStep++;
                
                // Update states
                this.updateFilterStates();
                
                // Don't auto-expand - let user hover to expand
                // The filter will be marked as active but not expanded
            },

            resetToStep: function(stepIndex) {
                this.currentStep = stepIndex;
                this.collapseAll();
                this.updateFilterStates();
            },


            isAllFiltersCompleted: function() {
                return this.filterSteps.every(filterId => this.hasFilterSelection(filterId));
            },

            showCompletionMessage: function() {
                if (this.isAllFiltersCompleted()) {
                    showToast('All filters completed! Products are now loaded.', 'success');
                }
            },

            // Method to handle when user wants to modify a completed filter
            modifyFilter: function(filterId) {
                const stepIndex = this.filterSteps.indexOf(filterId);
                if (stepIndex !== -1 && stepIndex <= this.currentStep) {
                    this.currentStep = stepIndex;
                    this.updateFilterStates();
                    this.expandFilter(filterId);
                    
                    // Force visual update
                    setTimeout(() => {
                        this.updateFilterStates();
                    }, 100);
                }
            },

            // Method to refresh filter states after any change
            refreshFilterStates: function() {
                this.updateFilterStates();
            },

            // Method to manually trigger hover expansion for testing
            triggerHoverExpansion: function(filterId) {
                // All boxes stay expanded - no hover expansion needed
            },

            // Method to ensure hover events are properly bound
            rebindHoverEvents: function() {
                // Remove existing hover events
                $('.filter-card').off('mouseenter mouseleave');
                $('.filter-card .filterBody').off('mouseenter');
                
                // Rebind hover events
                this.setupFilterHovers();
            }
        };

        $('#example tbody').html('<tr><td colspan="14" class="text-center">Select all required filters to view products</td></tr>');

        function updateFilters() {
            divisionIds = $("input[name='divisionsIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            specificationIds = $("input[name='specificationsIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            manufactureIds = $("input[name='manufacturersIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            productIds = $("input[name='productIds[]']:checked").map(function() {
                return $(this).val();
            }).get();

            sizesIds = $("input[name='sizesIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            thicknessIds = $("input[name='thicknessIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            finishIds = $("input[name='finishIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            painttypesIds = $("input[name='painttypesIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            colorsIds = $("input[name='colorsIds[]']:checked").map(function() {
                return $(this).val();
            }).get();
            colorEffectIds = $("input[name='colorEffectIds[]']:checked").map(function() {
                return $(this).val();
            }).get();

            // Update filter states after each change
            filterManager.updateFilterStates();
            
            // Force refresh visual states
            setTimeout(() => {
                filterManager.refreshFilterStates();
                // Rebind hover events to ensure they work properly
                filterManager.rebindHoverEvents();
            }, 50);

            if (divisionIds.length && specificationIds.length && manufactureIds.length && productIds.length &&
                sizesIds.length && thicknessIds.length && finishIds.length &&
                painttypesIds.length && colorsIds.length) {

                if (!$.fn.DataTable.isDataTable('#example')) {
                    initializeDataTable();
                } else {
                    table.ajax.reload();
                }

                // Show completion message
                filterManager.showCompletionMessage();

            } else {
                // Destroy the DataTable if it exists and show message
                if ($.fn.DataTable.isDataTable('#example')) {
                    table.destroy();
                }
                $('#example tbody').html('<tr><td colspan="14" class="text-center">Select all required filters to view products</td></tr>');
            }
        }

        function initializeDataTable() {
            table = $('#example').DataTable({
                paging: true,
                pageLength: 25,
                info: true,
                dom: 'lBfrtip',
                processing: true,
                serverSide: true,
                scrollX: true,
                scrollCollapse: true,
                fixedColumns: {
                    leftColumns: 2
                },
                ajax: {
                    url: "{{ url('get-products') }}",
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    data: function(d) {
                        d._token = "{{ csrf_token() }}";
                        d.divisionIds = divisionIds;
                        d.specificationIds = specificationIds;
                        d.productIds = productIds;
                        d.manufactureIds = manufactureIds;
                        d.sizesIds = sizesIds;
                        d.thicknessIds = thicknessIds;
                        d.finishIds = finishIds;
                        d.painttypesIds = painttypesIds;
                        d.colorsIds = colorsIds;
                        d.colorEffectIds = colorEffectIds;
                    },
                    dataSrc: function(json) {
                        // Check if response is valid
                        if (!json || typeof json !== 'object') {
                            throw new Error('Invalid server response');
                        }
                        
                        // Check if data property exists
                        if (!json.data) {
                            throw new Error('Missing data in server response');
                        }
                        
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        // Show user-friendly error message
                        showToast('Error loading products. Please try again.', 'error');
                        
                        // Hide the table and show error message with retry button
                        $('#example tbody').html(`
                            <tr>
                                <td colspan="14" class="text-center text-danger">
                                    <div>
                                        <p>Error loading products. Please try again.</p>
                                        <button onclick="retryDataTableAjax()" class="btn btn-sm btn-primary mt-2">Retry</button>
                                    </div>
                                </td>
                            </tr>
                        `);
                    }
                },
                columns: [{
                        data: 'checkbox',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'product_name'
                    },
                    {
                        data: 'quantity',
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'pricing',
                        render: function(data) {
                            return "$" + parseFloat(data).toFixed(2);
                        }
                    },
                    {
                        data: 'subtotal',
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'division'
                    },
                    {
                        data: 'specification'
                    },
                    {
                        data: 'manufacturer'
                    },
                    {
                        data: 'size'
                    },
                    {
                        data: 'thickness'
                    },
                    {
                        data: 'finish'
                    },
                    {
                        data: 'paint_type'
                    },
                    {
                        data: 'color'
                    },
                    {
                        data: 'color_effect'
                    }
                ],
                drawCallback: function(settings) {

                    $('.quantity-input').on('input', function() {
                        let $row = $(this).closest('tr');
                        let price = $(this).data('price');
                        let quantity = parseInt($(this).val()) || 0;
                        let subtotal = price * quantity;

                        $row.find('.subtotal').text("$" + subtotal.toFixed(2));

                        // let checkbox = $row.find('.variation-checkbox');

                        // if (quantity === 0) {
                        //     checkbox.prop('checked', false);
                        //     checkbox.trigger('change'); // Trigger change to remove from pallete
                        // } else { 
                        //     checkbox.prop('checked', true);
                        //     checkbox.trigger('change'); // Trigger change to update pallete
                        // }
                    });

                    // Update filtered and total product counts
                    let api = this.api();
                    let filteredCount = api.page.info().recordsDisplay;
                    let totalCount = api.page.info().recordsTotal;
                    $('#filtered-products').text(filteredCount);
                    $('#total-products').text(totalCount);
                }

            });
        }

        // Event Listeners for Filters
        $(document).on("change", "input[name='divisionsIds[]']", function() {
            fetchData('Division');
            updateFilters();
            // Only advance if we're currently on the Divisions step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Divisions' && $("input[name='divisionsIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        $(document).on("change", "input[name='specificationsIds[]']", function() {
            fetchData('Specification');
            updateFilters();
            // Only advance to next step if we're currently on the Specifications step
            // and we have at least one selection
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Specifications' && $("input[name='specificationsIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });

        $(document).on("change", "input[name='manufacturersIds[]']", function() {
            fetchData('Manufacturers');
            updateFilters();
            // Only advance if we're currently on the Manufacturers step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Manufacturers' && $("input[name='manufacturersIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        $(document).on("change", "input[name='productIds[]']", function() {
            fetchData('Products');
            updateFilters();
            // Refresh Colors filter when products change
            if ($("input[name='painttypesIds[]']:checked").length > 0) {
                fetchData('Colors');
            }
            // Only advance if we're currently on the Products step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Products' && $("input[name='productIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        $(document).on("change", "input[name='sizesIds[]']", function() {
            updateFilters();
            // Refresh Colors filter when sizes change
            if ($("input[name='painttypesIds[]']:checked").length > 0) {
                fetchData('Colors');
            }
            // Only advance if we're currently on the Sizes step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Sizes' && $("input[name='sizesIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        $(document).on("change", "input[name='thicknessIds[]']", function() {
            updateFilters();
            // Refresh Colors filter when thickness changes
            if ($("input[name='painttypesIds[]']:checked").length > 0) {
                fetchData('Colors');
            }
            // Only advance if we're currently on the Thickness step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Thickness' && $("input[name='thicknessIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        $(document).on("change", "input[name='painttypesIds[]']", function() {
            fetchData('Colors');
            updateFilters();
            // Only advance if we're currently on the PaintTypes step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-PaintTypes' && $("input[name='painttypesIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        $(document).on("change", "input[name='colorsIds[]']", function() {
            updateFilters();
            // Only advance if we're currently on the Colors step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Colors' && $("input[name='colorsIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        $(document).on("change", "input[name='colorEffectIds[]']", function() {
            updateFilters();
        });
        $(document).on("change", "input[name='finishIds[]']", function() {
            updateFilters();
            // Refresh Colors filter when finishes change
            if ($("input[name='painttypesIds[]']:checked").length > 0) {
                fetchData('Colors');
            }
            // Only advance if we're currently on the Finishes step
            setTimeout(() => {
                const currentFilterId = filterManager.filterSteps[filterManager.currentStep];
                if (currentFilterId === 'filter-Finishes' && $("input[name='finishIds[]']:checked").length > 0) {
                    filterManager.advanceToNextStep();
                }
            }, 500);
        });
        var manufacturerFilter = "{{ $manufacturer_filter ?? '' }}";

        // Initialize filter manager after a small delay to ensure all checkboxes are rendered
        setTimeout(function() {
            filterManager.init();
            
            // After initialization, check if manufacturer filter needs to trigger change
            // This ensures the filter state is properly set before triggering events
            if ($("input[name='manufacturersIds[]']:checked").length > 0 || manufacturerFilter !== "") {
                $("input[name='manufacturersIds[]']:checked").trigger("change");
            }
        }, 100);

        // Don't auto-expand any filter on page load - let user hover to expand
        // setTimeout(() => {
        //     filterManager.expandFilter('filter-Divisions');
        // }, 1000);

        //Fetch data for filters
        function fetchData(name) {
            var ids;
            if (name == 'Division') {
                ids = $("input[name='divisionsIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
            }
            if (name == 'Specification') {
                ids = $("input[name='specificationsIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
            }
            if (name == 'Manufacturers') {
                ids = $("input[name='manufacturersIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
            }
            if (name == 'Products') {
                ids = $("input[name='productIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
            }
            if (name == 'Colors') {
                ids = $("input[name='painttypesIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
            }
            
            // For Colors, send all selected filter IDs to get only available colors
            var filterData = {
                type: name,
                ids: ids
            };
            
            if (name == 'Colors') {
                filterData.productIds = $("input[name='productIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
                filterData.sizesIds = $("input[name='sizesIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
                filterData.thicknessIds = $("input[name='thicknessIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
                filterData.finishIds = $("input[name='finishIds[]']:checked").map(function() {
                    return $(this).val();
                }).get();
                filterData.painttypesIds = ids; // paint type IDs
            }
            
            $.ajax({
                url: "{{url('get-filter-data')}}",
                type: "GET",
                data: filterData,
                success: function(response) {
                    if (name == 'Division') {
                        $("#filter-Specifications .filterBody").html('');
                        $("#filter-Manufacturers .filterBody").html('');
                        $("#filter-Products .filterBody").html('');
                        $("#filter-Sizes .filterBody").html('');
                        $("#filter-Thickness .filterBody").html('');
                        $("#filter-PaintTypes .filterBody").html('');
                        $("#filter-Finishes .filterBody").html('');
                        $("#filter-Specifications .filterBody").html(response);
                    }
                    if (name == 'Specification') {
                        $("#filter-Manufacturers .filterBody").html('');
                        $("#filter-Products .filterBody").html('');
                        $("#filter-Sizes .filterBody").html('');
                        $("#filter-Thickness .filterBody").html('');
                        $("#filter-PaintTypes .filterBody").html('');
                        $("#filter-Finishes .filterBody").html('');
                        $("#filter-Manufacturers .filterBody").html(response);
                    }
                    if (name == 'Manufacturers') {
                        $("#filter-Products .filterBody").html('');
                        // $('#filter-Products').toggle(!!response);
                        $("#filter-Sizes .filterBody").html('');
                        $("#filter-Thickness .filterBody").html('');
                        $("#filter-PaintTypes .filterBody").html('');
                        $("#filter-Finishes .filterBody").html('');
                        $("#filter-Products .filterBody").html(response);
                    }
                    if (name == 'Products') {
                        $("#filter-Sizes .filterBody").html('');
                        $("#filter-Thickness .filterBody").html('');
                        $("#filter-PaintTypes .filterBody").html('');
                        $("#filter-Finishes .filterBody").html('');
                        $("#filter-Colors .filterBody").html('');
                        // $('#filter-Sizes').toggle(!!response.sizes);
                        // $('#filter-Thickness').toggle(!!response.thicknesses);
                        // $('#filter-PaintTypes').toggle(!!response.paintTypes);
                        // $('#filter-finishes').toggle(!!response.finishes);
                        $("#filter-Sizes .filterBody").html(response.sizes || '');
                        $("#filter-Thickness .filterBody").html(response.thicknesses || '');
                        $("#filter-PaintTypes .filterBody").html(response.paintTypes || '');
                        $("#filter-Finishes .filterBody").html(response.finishes || '');
                    }
                    if (name == 'Colors') {
                        $("#filter-Colors .filterBody").html('');
                        // $('#filter-Colors').toggle(!!response.colors);
                        $("#filter-Colors .filterBody").html(response.colors);

                        $("#filter-ColorsEffect .filterBody").html('');
                        // $('#filter-ColorsEffect').toggle(!!response.coloreffect);
                        $("#filter-ColorsEffect .filterBody").html(response.coloreffect);
                    }
                }
            });
        }
    });
</script>
@endpush
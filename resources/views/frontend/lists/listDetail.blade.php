@php
use App\Services\Rbac\PermissionService;
$_ldOrgId  = session(config('rbac.current_org_session_key'));
$_ldUser   = auth()->user();
$_ldAdmin  = $_ldUser && $_ldUser->role === 'admin';
$_ldCheck  = fn(string $g, string $l) => $_ldUser && ($_ldAdmin || ($_ldOrgId && app(PermissionService::class)->checkPermission($_ldUser->id, (int) $_ldOrgId, $g, $l)));
$ldCanEdit          = (bool) $_ldCheck('project_management', 'O');
$ldCanAddItem       = (bool) $_ldCheck('project_management', 'S');
$ldCanRemoveItem    = (bool) $_ldCheck('project_management', 'O');
$ldCanAddToPallet   = (bool) $_ldCheck('procurement', 'S');
$ldCanCreateEstimate= (bool) $_ldCheck('estimate_management', 'S');
@endphp
<script>
window.RBAC_CAN = window.RBAC_CAN || {};
window.RBAC_CAN.ldCanRemoveListItem = {{ $ldCanRemoveItem ? 'true' : 'false' }};
</script>

@push('css')
<link href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    #example tr {
        cursor: pointer;
    }

    .filter-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 1px;
        padding: 5px 0;
    }

    .filter-card {
        flex: 1;
        min-width: 120px;
        max-width: 150px;
        font-size: 11px;
        padding: 5px;
        border: 1px solid #cccccc;
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        background: #fff;
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
        height: 130px;
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
        scrollbar-color: #2c58a0 #f1f1f1;
    }

    .shopping-list-list::-webkit-scrollbar {
        height: 6px;
    }

    .shopping-list-list::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .shopping-list-list::-webkit-scrollbar-thumb {
        background-color: #2c58a0;
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
        top: 3px;
        right: 3px;
    }

    .list-item-delete a {
        color: #ff0000;
        font-size: 16px;
        /* background: white;
        border-radius: 50%; */
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        /* box-shadow: 0 2px 4px rgba(0,0,0,0.1); */
    }

    .highlight-color {
        color: #2c58a0;
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
        background: #2c58a0;
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

    #example tbody td:last-child {
        text-align: center;
    }

    #example tbody td:last-child a {
        color: #dc3545;
        font-size: 16px;
    }

    #example tbody td:last-child a:hover {
        color: #b02a37;
    }

    /* Export buttons styling */
    .dt-buttons .btn {
        margin-right: 5px;
        margin-bottom: 5px;
    }

    .breadcrumb-wrap .d-flex {
        padding: 10px 0;
    }

    .breadcrumb-wrap .breadcrumb {
        margin-bottom: 0;
    }
    
    /* Custom styles for new features */
    .btn-rounded {
        border-radius: 25px !important;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    
    /* Improved button styling for round buttons */
    .btn-outline-primary.btn-sm,
    .btn-outline-secondary.btn-sm,
    .btn-outline-info.btn-sm,
    .btn-primary.btn-sm {
        border-radius: 20px !important;
        padding: 8px 16px !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        text-transform: none !important;
        border-width: 1.5px !important;
        transition: all 0.3s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        min-width: 80px !important;
        justify-content: center !important; 
    }
    
    /* Specific color overrides for outline buttons */
    .btn-outline-primary.btn-sm {
        color: #007bff !important;
        border-color: #007bff !important;
    }
    
    .btn-outline-primary.btn-sm:hover {
        color: #fff !important;
        background-color: #007bff !important;
        border-color: #007bff !important;
    }
    
    .btn-outline-secondary.btn-sm {
        color: #6c757d !important;
        border-color: #6c757d !important;
    }
    
    .btn-outline-secondary.btn-sm:hover {
        color: #fff !important;
        background-color: #6c757d !important;
        border-color: #6c757d !important;
    }
    
    .btn-outline-info.btn-sm {
        color: #17a2b8 !important;
        border-color: #17a2b8 !important;
    }
    
    .btn-outline-info.btn-sm:hover {
        color: #fff !important;
        background-color: #17a2b8 !important;
        border-color: #17a2b8 !important;
    }
    
    .btn-outline-primary.btn-sm:hover,
    .btn-outline-secondary.btn-sm:hover,
    .btn-outline-info.btn-sm:hover,
    .btn-primary.btn-sm:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    }
    
    .btn-outline-primary.btn-sm:active,
    .btn-outline-secondary.btn-sm:active,
    .btn-outline-info.btn-sm:active,
    .btn-primary.btn-sm:active {
        transform: translateY(0) !important;
    }
    
    /* Icon styling */
    .btn i {
        font-size: 12px !important;
        line-height: 1 !important;
    }
    
    /* Button text styling */
    .btn span {
        font-weight: 500 !important;
    }
    
    /* Ensure proper text color for all button states */
    .btn-outline-primary.btn-sm,
    .btn-outline-secondary.btn-sm,
    .btn-outline-info.btn-sm {
        background-color: transparent !important;
    }
    
    /* Edit button specific styling - square with brown color */
    .btn-edit-project {
        border-radius: 4px !important;
        width: 28px !important;
        height: 28px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-width: 1.5px !important;
        color: #8B4513 !important;
        border-color: #8B4513 !important;
        background-color: transparent !important;
    }
    
    .btn-edit-project:hover {
        color: #fff !important;
        background-color: #8B4513 !important;
        border-color: #8B4513 !important;
    }
    
    /* Additional button improvements */
    .btn {
        text-decoration: none !important;
        outline: none !important;
    }
    
    .btn:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
    }
    
    /* Ensure proper spacing in button groups */
    .d-flex .btn {
        margin-right: 8px !important;
    }
    
    .d-flex .btn:last-child {
        margin-right: 0 !important;
    }
    
    .list-info-bar {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1px solid #dee2e6;
        border-radius: 10px;
    }
    
    .project-header {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .total-subtotal {
        font-size: 1.5rem;
        font-weight: bold;
        color: #007bff;
    }
    
    /* Tax breakdown styling - single line */
    .text-muted {
        font-size: 0.85rem;
    }
    
    .fw-bold {
        font-size: 1rem;
    }
    
    .fs-5 {
        font-size: 1.25rem !important;
    }
    
    /* Mobile responsive styles */
    @media (max-width: 768px) {
        /* Project header responsive */
        .project-title {
            font-size: 1.5rem !important;
            line-height: 1.2 !important;
        }
        
        /* Tax breakdown responsive */
        .d-flex.align-items-center {
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        
        .d-flex.align-items-center > * {
            margin-bottom: 4px;
        }
        
        /* Button responsive */
        .btn-sm {
            padding: 6px 12px !important;
            font-size: 11px !important;
        }
        
        /* Filter cards responsive */
        .filter-card {
            min-width: 100% !important;
            max-width: 100% !important;
            margin-bottom: 10px;
        }
        
        .filter-container {
            flex-direction: column;
            gap: 10px;
        }
        
        /* Table responsive */
        .table-responsive {
            font-size: 12px;
        }
        
        .table-responsive th,
        .table-responsive td {
            padding: 8px 4px;
        }
        
        /* DataTable responsive */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 10px;
        }
        
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 10px;
        }
        
        /* List info bar responsive */
        #list-info-bar .card-body {
            padding: 10px;
        }
        
        #list-info-bar .d-flex {
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        #list-info-bar .d-flex > * {
            margin-bottom: 5px;
        }
        
        /* SweetAlert responsive */
        .swal2-popup {
            width: 95% !important;
            margin: 10px auto !important;
        }
        
        /* Breadcrumb responsive */
        .breadcrumb {
            font-size: 14px;
        }
        
        .breadcrumb a {
            word-break: break-word;
        }
    }
    
    @media (max-width: 576px) {
        /* Extra small devices */
        .project-title {
            font-size: 1.25rem !important;
        }
        
        .btn-sm {
            padding: 4px 8px !important;
            font-size: 10px !important;
        }
        
        .container-fluid {
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
        
        /* Hide button text on very small screens */
        .d-none.d-sm-inline {
            display: none !important;
        }
        
        /* Make buttons icon-only on very small screens */
        .btn i {
            font-size: 14px !important;
        }
        
        /* Shopping list popup responsive */
        .shopping-list-popup {
            width: 95% !important;
            min-width: 95% !important;
            max-width: 95% !important;
            right: 2.5% !important;
        }
        
        .list-item {
            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;
        }
        
        /* DataTable buttons responsive */
        .dt-buttons {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 5px !important;
            margin-bottom: 10px !important;
        }
        
        .dt-buttons .btn {
            font-size: 11px !important;
            padding: 4px 8px !important;
        }
    }
    
    /* Additional responsive improvements */
    @media (max-width: 480px) {
        /* Extra small devices */
        .project-title {
            font-size: 1.1rem !important;
        }
        
        .btn-edit-project {
            width: 24px !important;
            height: 24px !important;
        }
        
        .btn-edit-project i {
            font-size: 10px !important;
        }
        
        /* Stack tax breakdown vertically */
        .d-flex.align-items-center {
            flex-direction: column !important;
            align-items: flex-start !important;
        }
        
        .d-flex.align-items-center > * {
            margin-bottom: 8px !important;
        }
        
        /* Make table more compact */
        .table-responsive {
            font-size: 11px;
        }
        
        .table-responsive th,
        .table-responsive td {
            padding: 6px 2px;
        }
    }
    
    .filter-section {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    #filter-section,
    #list-info-bar {
        transition: all 0.3s ease;
    }
    
    .tooltip-custom {
        position: relative;
        cursor: help;
    }
    
    .tooltip-custom:hover::after {
        content: attr(title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 1000;
    }
    
    /* SweetAlert custom styling */
    .swal-wide {
        border-radius: 15px !important;
    }
    
    .swal-wide .swal2-popup {
        border-radius: 15px !important;
        padding: 20px !important;
    }
    
    .swal-wide .swal2-title {
        font-size: 18px !important;
        font-weight: 600 !important;
        color: #333 !important;
    }
    
    .swal-wide .swal2-html-container {
        margin: 15px 0 !important;
    }
    
    .swal-wide .form-control {
        border-radius: 8px !important;
        border: 1.5px solid #ddd !important;
        padding: 10px 12px !important;
        font-size: 14px !important;
        transition: border-color 0.3s ease !important;
    }
    
    .swal-wide .form-control:focus {
        border-color: #007bff !important;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
    }
    
    .swal-wide .form-label {
        font-weight: 500 !important;
        color: #555 !important;
        margin-bottom: 5px !important;
    }
</style>

@endpush

<main class="main">
    <div id="toast-container"></div>
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
                <div class="breadcrumb">
                    <a href="{{url('/')}}" rel="nofollow">Home</a>
                    <span></span>
                <a href="{{url('view-lists')}}" rel="nofollow">View Lists</a>
                    <span></span> {{$list->name ?? ''}}
                </div>
        </div>
    </div>
    
    <!-- Project Header Section -->
    <div class="container-fluid px-4 mt-3 list-header">
        <div class="row align-items-center px-4">
            <div class="col-12">
                <div class="list-header-row d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="list-title d-flex align-items-center gap-2">
                        <h2 class="mb-0 project-title">{{ $list->name ?? 'Project' }}</h2>
                        @if ($ldCanEdit)
                        <button type="button" class="btn btn-outline-primary btn-edit-project" onclick="editListInfo()" title="Edit Project Information">
                            <i class="fas fa-edit"></i>
                        </button>
                        @endif
                    </div>
                    <div class="list-actions d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleFilters()" title="Toggle Filters">
                            <i class="fas fa-filter"></i> <span class="d-none d-sm-inline">Filters</span>
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="toggleListInfo()" title="List Information">
                            <i class="fas fa-info-circle"></i> <span class="d-none d-sm-inline">List Info</span>
                        </button>
                        @if ($ldCanAddItem)
                        <a href="{{url('product-filter')}}" class="btn btn-outline-primary btn-sm btn-rounded" title="Add Product">
                            <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">Add Product</span>
                        </a>
                        @endif
                        @if ($ldCanAddToPallet)
                    <a href="{{url('add-list-pallet',$list->id)}}" class="btn btn-primary btn-sm btn-rounded">
                        <i class="fi-rs-shopping-cart-add btn-icon-spacing"></i> <span class="d-none d-sm-inline">Add to Pallet</span>
                        </a>
                        @endif
                        @if ($ldCanCreateEstimate)
                        <button type="button" class="btn btn-success btn-sm btn-rounded"
                                onclick="createQuoteFromList(this)" data-list-id="{{ $list->id }}"
                                title="Create Estimate">
                        <i class="fas fa-file-invoice-dollar btn-icon-spacing"></i> <span class="d-none d-sm-inline">Create Estimate</span>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3  px-4">
            <div class="col-12">
                <div class="list-summary d-flex flex-wrap justify-content-md-end gap-3">
                    <div class="summary-item">
                        <div class="summary-label">Subtotal</div>
                        <div class="summary-value" id="total-subtotal">$0.00</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Tax <span id="tax-rate-label"></span></div>
                        <div class="summary-value" id="tax-amount">$0.00</div>
                    </div>
                    <div class="summary-item total">
                        <div class="summary-label">Total</div>
                        <div class="summary-value" id="total-amount">$0.00</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- List Info Bar (Hidden by default) -->
        <div class="row mt-2" id="list-info-bar" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <div class="d-flex flex-wrap align-items-center">
                                <div class="d-flex align-items-center me-3 mb-1">
                                    <span class="badge bg-primary me-2">Project</span>
                                    <span class="fw-bold">{{ $list->name ?? 'N/A' }}</span>
                                </div>
                                <div class="d-flex align-items-center me-3 mb-1">
                                    <span class="text-muted me-2">|</span>
                                    <span title="{{ $list->address1 ?? 'N/A' }}">
                                        <i class="fas fa-user me-1"></i>
                                        {{ Str::limit($list->address1 ?? 'N/A', 25) }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center me-3 mb-1">
                                    <span class="text-muted me-2">|</span>
                                    <span title="{{ $list->address2 ?? 'N/A' }}">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        {{ Str::limit($list->address2 ?? 'N/A', 30) }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="text-muted me-2">|</span>
                                    <span>
                                        <i class="fas fa-city me-1"></i>
                                        {{ $list->city ?? 'N/A' }}, 
                                        @if($list->state_id)
                                            @php
                                                $state = \App\Models\StateTax::find($list->state_id);
                                            @endphp
                                            {{ $state ? $state->state : 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                        {{ $list->postcode ?? '' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="mt-4 mb-4">
        <div class="container-fluid px-4">
            <!-- List Information Display (Hidden by default) -->
            <div class="row mb-4" id="original-list-info" style="display: none;">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">List Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Project Name:</strong><br>
                                    {{ $list->name ?? 'N/A' }}
                                </div>
                                <div class="col-md-3">
                                    <strong>City:</strong><br>
                                    {{ $list->city ?? 'N/A' }}
                                </div>
                                <div class="col-md-3">
                                    <strong>State:</strong><br>
                                    @if($list->state_id)
                                        @php
                                            $state = \App\Models\StateTax::find($list->state_id);
                                        @endphp
                                        {{ $state ? $state->state : 'N/A' }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                                <div class="col-md-3">
                                    <strong>Zip Code:</strong><br>
                                    {{ $list->postcode ?? 'N/A' }}
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <strong>Jobsite General Contractor:</strong><br>
                                    {{ $list->address1 ?? 'N/A' }}
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <strong>Address:</strong><br>
                                    {{ $list->address2 ?? 'N/A' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End List Information Display -->
            
            <div class="row">
                <div class="col-lg-12">
                    <div class="shop-product-fillter-header" id="filter-section" style="display: none;">
                        <!-- <div class="filter-note" id="filter-note">
                            <p><strong>Note:</strong> Please select all filters to view the products.</p>
                            <button onclick="document.getElementById('filter-note').style.display='none'" style="float: right; background: none; border: none; font-size: 16px; cursor: pointer;">&times;</button>
                        </div> -->

                        <div class="filter-container">
                            <div class="filter-card" id="filter-Divisions">
                                <div class="filterTitle filterTitle-required">Divisions</div>
                                <ul class="categor-list filterBody">
                                    @foreach($divisions as $division)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="divisionsIds[]" value="{{$division->id}}">
                                            {{$division->code}} - {{$division->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Specifications">
                                <div class="filterTitle filterTitle-required">Specifications</div>
                                <ul class="categor-list filterBody">
                                    @foreach($specifications as $specification)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="specificationsIds[]" value="{{$specification->id}}">
                                            {{$specification->specification_number}} - {{$specification->material_type}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Manufacturers">
                                <div class="filterTitle filterTitle-required">Manufacturers</div>
                                <ul class="categor-list filterBody">
                                    @foreach($manufacturers as $manufacturer)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="manufacturersIds[]" value="{{$manufacturer->id}}">
                                            {{$manufacturer->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Products">
                                <div class="filterTitle filterTitle-required">Products</div>
                                <ul class="categor-list filterBody">
                                    @foreach($products as $product)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="productIds[]" value="{{$product->id}}">
                                            {{$product->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Sizes">
                                <div class="filterTitle filterTitle-required">Sizes</div>
                                <ul class="categor-list filterBody">
                                    @foreach($sizes as $size)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="sizesIds[]" value="{{$size->id}}">
                                            {{$size->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Thickness">
                                <div class="filterTitle filterTitle-required">Thickness</div>
                                <ul class="categor-list filterBody">
                                    @foreach($thicknesses as $thickness)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="thicknessIds[]" value="{{$thickness->id}}">
                                            {{$thickness->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Finishes">
                                <div class="filterTitle filterTitle-required">Finishes</div>
                                <ul class="categor-list filterBody">
                                    @foreach($finishes as $finish)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="finishIds[]" value="{{$finish->id}}">
                                            {{$finish->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-PaintTypes">
                                <div class="filterTitle filterTitle-required">Paint Types</div>
                                <ul class="categor-list filterBody">
                                    @foreach($paint_types as $paint_type)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="painttypesIds[]" value="{{$paint_type->id}}">
                                            {{$paint_type->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-Colors">
                                <div class="filterTitle filterTitle-required">Colors</div>
                                <ul class="categor-list filterBody">
                                    @foreach($colors as $color)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="colorsIds[]" value="{{$color->id}}">

                                            {{$color->name}} ({{$color->paint_type ? $color->paint_type->name : ''}})
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="filter-card" id="filter-ColorsEffect">
                                <div class="filterTitle">Colors Effect</div>
                                <ul class="categor-list filterBody">
                                    @foreach($color_effects as $color_effect)
                                    <li>
                                        <label>
                                            <input type="checkbox" class="custom-check" name="colorEffectIds[]" value="{{$color_effect->id}}">
                                            {{$color_effect->name}}
                                        </label>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <div class="count-container d-flex flex-column flex-md-row align-items-center justify-content-md-end mt-3 mt-md-0">
                            <div class="text-center text-md-end mb-2 mb-md-0 me-md-3">
                                <span id="filtered-products" class="fw-bold">0</span> Out of <span id="total-products">{{ $list->items()->count() }}</span>
                            </div>
                            <a href="{{url('list-view/'.$list->id.'/'.$list->name)}}" class="btn btn-danger btn-sm btn-rounded">
                                <i class="fi-rs-refresh"></i> <span class="d-none d-sm-inline">Reset Filter</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12">
                    <div class="row product-grid-3">
                        <div class="table-container">
                            <!-- <div class="filter-note" id="filter-note">
                                <p>
                                    <strong>Note:</strong> Click on the checkbox in the table below
                                    <i class="fi-rs-checkbox"></i> to add the product to the pallet.
                                </p>
                                <button onclick="document.getElementById('filter-note').style.display='none'"
                                    style="float: right; background: none; border: none; font-size: 16px; cursor: pointer;">
                                    &times;
                                </button>
                            </div> -->

                        <div class="table-shell">
                            <div class="table-responsive">
                                <table id="example" class="display nowrap" style="width:100%;">
                                    <thead>
                                        <tr>
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
                                            <th class="bg-primary">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Shopping List Popup -->
    <div class="shopping-list-popup" id="shoppingListPopup">
        <div class="shopping-list-body">
            <ul class="shopping-list-list" id="shoppingListItems">
                <li class="list-empty">Your list is empty</li>
            </ul>
        </div>
        <div class="shopping-list-footer">
            <div class="shopping-list-buttons">
                <a href="javascript:void(0)" class="btn btn-md btn-outline-default" id="saveListBtn">Add to List</a>
                <a href="javascript:void(0)" class="btn btn-md btn-primary" id="addToPalletBtn">Add to Pallet</a>
            </div>
        </div>
    </div>
</main>
@push('scripts')
<script id="initial-list" type="application/json">{!! json_encode([
    'id' => $list->id,
    'name' => $list->name,
    'address1' => $list->address1,
    'address2' => $list->address2,
    'city' => $list->city,
    'state_id' => $list->state_id,
    'postcode' => $list->postcode,
]) !!}</script>
<script id="state-options" type="application/json">{!! \App\Models\StateTax::orderBy('state')->get(['id','state','combined_tax_rate'])->toJson() !!}</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Parse backend-provided data from non-executable JSON script tags (avoids linter errors)
    let currentList = {};
    let states = [];
    let stateTaxRates = {};
    try {
        currentList = JSON.parse(document.getElementById('initial-list').textContent || '{}');
        states = JSON.parse(document.getElementById('state-options').textContent || '[]');
        stateTaxRates = states.reduce((acc, s) => {
            acc[s.state] = parseFloat(s.combined_tax_rate || 0);
            return acc;
        }, {});
    } catch (e) {
        console.error('Failed to parse initial JSON data', e);
    }
    $(document).ready(function() {
        let divisionIds = [],
            specificationIds = [],
            manufactureIds = [],
            sizesIds = [],
            thicknessIds = [],
            finishIds = [],
            painttypesIds = [],
            colorsIds = [];

        let table;

        // Initialize with empty table message
        $('#example tbody').html('<tr><td colspan="14" class="text-center">Select all required filters to view products</td></tr>');

        updateFilters();

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

            initializeDataTable();
        }



        // Event Listeners for Filters
        $(document).on("change", "input[name='divisionsIds[]']", function() {
            updateFilters('Division');
        });

        $(document).on("change", "input[name='specificationsIds[]']", function() {
            updateFilters('Specification');
        });

        $(document).on("change", "input[name='manufacturersIds[]']", function() {
            updateFilters('Manufacturers');
        });

        $(document).on("change", "input[name='productIds[]']", function() {
            updateFilters('Products');
        });

        $(document).on("change", "input[name='sizesIds[]']", function() {
            updateFilters('Products');
        });

        $(document).on("change", "input[name='thicknessIds[]']", function() {
            updateFilters('Products');
        });

        $(document).on("change", "input[name='painttypesIds[]']", function() {
            updateFilters('Products');
        });

        $(document).on("change", "input[name='colorsIds[]']", function() {
            updateFilters('Products');
        });

        $(document).on("change", "input[name='colorEffectIds[]']", function() {
            updateFilters('Products');
        });

        $(document).on("change", "input[name='finishIds[]']", function() {
            updateFilters('Products');
        });

        function initializeDataTable() {
            if ($.fn.DataTable.isDataTable('#example')) {
                table.destroy();
            }

            table = $('#example').DataTable({
                dom: 'Blfrtip',
                buttons: [
                    { extend: 'copy', text: '<i class="ti ti-copy"></i>', titleAttr: 'Copy' },
                    { extend: 'csv', text: '<i class="ti ti-file-type-csv"></i>', titleAttr: 'CSV' },
                    { extend: 'excel', text: '<i class="ti ti-file-spreadsheet"></i>', titleAttr: 'Excel' },
                    { extend: 'pdf', text: '<i class="ti ti-file-type-pdf"></i>', titleAttr: 'PDF' },
                    { extend: 'print', text: '<i class="ti ti-printer"></i>', titleAttr: 'Print' }
                ],
                searching: true,
                info: false,
                lengthChange: false,
                processing: true,
                serverSide: true,
                scrollX: true,
                scrollCollapse: true,
                responsive: true,
                fixedColumns: {
                    leftColumns: window.innerWidth < 768 ? 1 : 2
                },
                ajax: {
                    url: "{{ url('list-detail-data', $list->id) }}",
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
                    },
                    dataSrc: function(json) {
                        // Update filtered products count
                        $('#filtered-products').text(json.recordsFiltered);
                        $('#total-products').text(json.totalRecords);
                        return json.data;
                    }
                },
                columns: [{
                        data: 'product_name'
                    },
                    {
                        data: 'quantity',
                        orderable: false,
                        searchable: false
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
                    }, {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return window.RBAC_CAN.ldCanRemoveListItem
                                ? `<a href="javascript:void(0)" class="text-danger remove-from-list-btn" data-id="${data}"><i class="fi-rs-trash"></i></a>`
                                : '';
                        }
                    }
                ],
                drawCallback: function() {
                    // Update subtotal when quantity changes
                    $('.quantity-input').on('input', function() {
                        let $row = $(this).closest('tr');
                        let price = $(this).data('price');
                        let quantity = parseInt($(this).val()) || 0;
                        let subtotal = price * quantity;

                        $row.find('.subtotal').text("$" + subtotal.toFixed(2));

                    });
                    $('.quantity-input').each(function() {
                        $(this).data('prev-value', $(this).val());
                    });
                }
            });
            // Expose globally so other handlers can safely reference
            window.table = table;
        }

        // Function to handle item removal with confirmation
        function removeListItem(itemId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ url('remove-lists-items') }}/" + itemId,
                        type: 'POST',
                        data: {
                            _token: "{{ csrf_token() }}",
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    'Removed!',
                                    'The item has been removed from your list.',
                                    'success'
                                );
                                // Update total count
                                $('#total-products').text(response.total_items);
                                table.ajax.reload();
                            }
                        },
                        error: function(xhr) {
                            Swal.fire(
                                'Error!',
                                xhr.responseJSON?.message || 'Something went wrong.',
                                'error'
                            );
                        }
                    });
                }
            });
        }

        $(document).on('click', '.remove-from-list-btn', function() {
            const itemId = $(this).data('id');
            removeListItem(itemId);
        });
    });

    // Add this event listener after your DataTable initialization:
    $(document).on('change', '.quantity-input', function() {
        const itemId = $(this).data('id');
        const newQuantity = $(this).val();
        const price = $(this).data('price');
        const $row = $(this).closest('tr');

        if (newQuantity < 1) {
            $(this).val(1);
            return;
        }

        // Show loading indicator 
        $.ajax({
            url: "{{ route('update-list-item-quantity') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                item_id: itemId,
                quantity: newQuantity
            },
            success: function(response) {
                if (response.success) {
                    // Update subtotal in the table
                    $row.find('.subtotal').text("$" + (price * newQuantity).toFixed(2));
                    updateMiniPallet();
                    updateTotalSubtotal(); // Update the total subtotal
                    showToast('Quantity updated successfully', 'success');
                } else {
                    showToast(response.message || 'Failed to update quantity', 'error');
                    // Revert to previous value
                    $(this).val($(this).data('prev-value'));
                }
            },
            error: function(xhr) {
                showToast(xhr.responseJSON?.message || 'Error updating quantity', 'error');
                // Revert to previous value
                $(this).val($(this).data('prev-value'));
            }
        });
    });

    // Toggle filters section with smooth animation
    function toggleFilters() {
        const filterSection = document.getElementById('filter-section');
        const filterBtn = document.querySelector('button[onclick="toggleFilters()"]');
        
        if (filterSection.style.display === 'none' || filterSection.style.display === '') {
            filterSection.style.display = 'block';
            filterSection.style.opacity = '0';
            filterSection.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                filterSection.style.transition = 'all 0.3s ease';
                filterSection.style.opacity = '1';
                filterSection.style.transform = 'translateY(0)';
            }, 10);
            
            // Update button text
            filterBtn.innerHTML = '<i class="fas fa-filter"></i> Hide Filters';
        } else {
            filterSection.style.transition = 'all 0.3s ease';
            filterSection.style.opacity = '0';
            filterSection.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                filterSection.style.display = 'none';
            }, 300);
            
            // Update button text
            filterBtn.innerHTML = '<i class="fas fa-filter"></i> Filters';
        }
    }

    // Toggle list info bar with smooth animation
    function toggleListInfo() {
        const listInfoBar = document.getElementById('list-info-bar');
        const listInfoBtn = document.querySelector('button[onclick="toggleListInfo()"]');
        
        if (listInfoBar.style.display === 'none' || listInfoBar.style.display === '') {
            listInfoBar.style.display = 'block';
            listInfoBar.style.opacity = '0';
            listInfoBar.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                listInfoBar.style.transition = 'all 0.3s ease';
                listInfoBar.style.opacity = '1';
                listInfoBar.style.transform = 'translateY(0)';
            }, 10);
            
            // Update button text
            listInfoBtn.innerHTML = '<i class="fas fa-info-circle"></i> Hide Info';
        } else {
            listInfoBar.style.transition = 'all 0.3s ease';
            listInfoBar.style.opacity = '0';
            listInfoBar.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                listInfoBar.style.display = 'none';
            }, 300);
            
            // Update button text
            listInfoBtn.innerHTML = '<i class="fas fa-info-circle"></i> List Info';
        }
    }

    // Edit list information
    function editListInfo() {
        const currentStateId = currentList.state_id || 0;

        const html = `
            <input type="text" class="form-control" id="edit_project_name" name="project_name" 
                   value="${currentList.name ? String(currentList.name) : ''}" placeholder="Project Name" required style="margin-bottom: 12px;">
            <input type="text" class="form-control" id="edit_address1" name="address1" 
                   value="${currentList.address1 ? String(currentList.address1) : ''}" placeholder="Jobsite General Contractor" required style="margin-bottom: 12px;">
            <input type="text" class="form-control" id="edit_address2" name="address2" 
                   value="${currentList.address2 ? String(currentList.address2) : ''}" placeholder="Address" required style="margin-bottom: 12px;">
            <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                <input type="text" class="form-control" id="edit_city" name="city" 
                       value="${currentList.city ? String(currentList.city) : ''}" placeholder="City" required style="flex: 1;">
                <select class="form-control" id="edit_state" name="state" required style="flex: 1;">
                    <option value="">State</option>
                    ${states.map(state => `<option value="${state.id}" ${Number(state.id) === Number(currentStateId) ? 'selected' : ''}>${state.state}</option>`).join('')}
                </select>
            </div>
            <input type="text" class="form-control" id="edit_postcode" name="postcode" 
                   value="${currentList.postcode ? String(currentList.postcode) : ''}" placeholder="ZIP Code" required>`;

        Swal.fire({
            title: 'Edit Project Information',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Update',
            cancelButtonText: 'Cancel',
            focusConfirm: false,
            preConfirm: () => {
                const projectName = document.getElementById('edit_project_name').value.trim();
                const address1 = document.getElementById('edit_address1').value.trim();
                const address2 = document.getElementById('edit_address2').value.trim();
                const city = document.getElementById('edit_city').value.trim();
                const state = document.getElementById('edit_state').value;
                const postcode = document.getElementById('edit_postcode').value.trim();

                if (!projectName || !address1 || !address2 || !city || !state || !postcode) {
                    Swal.showValidationMessage('Please fill in all required fields');
                    return false;
                }

                return {
                    project_name: projectName,
                    address1: address1,
                    address2: address2,
                    city: city,
                    state: state,
                    postcode: postcode
                };
            },
            customClass: {
                popup: 'custom-swal',
                confirmButton: 'site-primary',
                cancelButton: 'site-cancel'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ url('update-list-info', $list->id) }}",
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        ...result.value
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Project information updated successfully',
                                icon: 'success',
                                timer: 1200,
                                showConfirmButton: false
                            }).then(() => {
                                // Reload the page to ensure all Blade-rendered values and future edit defaults are up to date
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error!', response.message || 'Failed to update project information', 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to update project information', 'error');
                    }
                });
            }
        });
    }

    // Update total subtotal and tax when DataTable loads
    function updateTotalSubtotal() {
        let subtotal = 0;
        $('.subtotal').each(function() {
            const subtotalText = $(this).text().replace('$', '').replace(',', '');
            subtotal += parseFloat(subtotalText) || 0;
        });
        
        // Get current state from parsed data and compute tax
        const currentStateObj = states.find(s => Number(s.id) === Number(currentList.state_id || 0));
        const taxRate = currentStateObj ? parseFloat(currentStateObj.combined_tax_rate || 0) : 0;
        const tax = subtotal * (taxRate / 100);
        const total = subtotal + tax;
        
        // Update display
        $('#total-subtotal').text('$' + subtotal.toFixed(2));
        $('#tax-amount').text('$' + tax.toFixed(2));
        $('#total-amount').text('$' + total.toFixed(2));
        $('#tax-rate-label').text(taxRate ? `(${taxRate}%)` : '');
    }

    // Function to update list info on page without reloading
    function updateListInfoOnPage(data) {
        // Update project name in header
        $('h2').text(data.project_name || 'Project');
        
        // Update breadcrumb
        $('.breadcrumb span:last').text(data.project_name || '');
        
        // Update list info bar if visible
        if ($('#list-info-bar').is(':visible')) {
            $('#list-info-bar .fw-bold').text(data.project_name || 'N/A');
            $('#list-info-bar span[title]').each(function() {
                const title = $(this).attr('title');
                if (title.includes('address1')) {
                    $(this).text(data.address1 || 'N/A');
                    $(this).attr('title', data.address1 || 'N/A');
                } else if (title.includes('address2')) {
                    $(this).text(data.address2 || 'N/A');
                    $(this).attr('title', data.address2 || 'N/A');
                }
            });
        }
    }

    // Call updateTotalSubtotal when DataTable is drawn
    $(document).ready(function() {
        // Update total subtotal initially
        setTimeout(updateTotalSubtotal, 1000);

        // Update total subtotal when DataTable is redrawn (guard if table is not ready yet)
        if (typeof table !== 'undefined' && table && typeof table.on === 'function') {
            table.on('draw.dt', function() {
                setTimeout(updateTotalSubtotal, 100);
            });
        }

        // Handle window resize for responsive DataTable
        $(window).on('resize', function() {
            if (typeof table !== 'undefined' && table) {
                table.columns.adjust();
                if (table.fixedColumns) {
                    table.fixedColumns().relayout();
                }
            }
        });
    });

    // Create Estimate from List
    function createQuoteFromList(listIdOrEl) {
        const listId = (typeof listIdOrEl === 'object' && listIdOrEl)
            ? listIdOrEl.getAttribute('data-list-id')
            : listIdOrEl;

        $.getJSON(@json(route('projects.list'))).done(function(res) {
            const projects = Array.isArray(res.projects) ? res.projects : [];
            if (!projects.length) {
                Swal.fire('No projects', 'Create a project first, then create an estimate from this list.', 'info');
                return;
            }
            const inputOptions = {};
            projects.forEach(function(p) { inputOptions[String(p.id)] = p.name || ('Project #' + p.id); });

            Swal.fire({
                title: 'Create Estimate',
                text: 'Select the project for the new estimate.',
                icon: 'question',
                input: 'select',
                inputOptions: inputOptions,
                inputPlaceholder: 'Select project',
                inputValidator: function(value) {
                    if (!value) return 'Please select project';
                },
                showCancelButton: true,
                confirmButtonText: 'Yes, create estimate',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    submitQuoteFromList(listId, result.value);
                }
            });
        }).fail(function() {
            Swal.fire('Error!', 'Failed to load projects', 'error');
        });
    }

    function submitQuoteFromList(listId, projectId) {
        const urlTemplate = @json(route('projects.quotes.create-from-list', ['project' => '__PROJECT__', 'listId' => '__LIST__']));
        $.ajax({
            url: urlTemplate.replace('__PROJECT__', encodeURIComponent(projectId)).replace('__LIST__', encodeURIComponent(listId)),
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonText: 'View Estimate',
                        showCancelButton: true,
                        cancelButtonText: 'Stay Here'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '{{ route("quotes.index") }}';
                        }
                    });
                }
            },
            error: function(xhr) {
                Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to create quote', 'error');
            }
        });
    }
</script>
@endpush
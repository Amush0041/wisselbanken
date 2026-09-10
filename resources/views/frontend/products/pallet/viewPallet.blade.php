@extends('frontend.layouts.app')
@push('seo')
<title> Pallet view | {{env('APP_NAME','Wisselbanken')}}</title>
@endpush
@push('css')
<style>
    .quantity-input {
        width: 70px;
        padding: 4px 6px;
        text-align: center;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .product-cell {
        white-space: nowrap;
    }

    .product-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .product-image {
        width: 50px;
        height: 50px;
        border-radius: 5px;
        object-fit: cover;
    }

    .product-name {
        white-space: normal;
        color: #007bff;
        text-decoration: none;
        font-weight: 500;
    }

    .product-name:hover {
        text-decoration: underline;
    }

    .info-box {
      
    padding: 5px 5px 5px 0px;
    margin: 5px 5px 5px 0px;
        width: 48%;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        height: fit-content;
    }

    .info-box table {
        border-collapse: collapse;
        border-spacing: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    /* Removed equal height helper classes since we're no longer using them */
    /* .equal-h {
        box-sizing: border-box;
    }

    .equal-h>table {
        height: 100%;
    } */

    /* .info-box th {
        width: 28px;
        background-color: #4a171e;            
        text-align: top !important;
        color: white;
        font-weight: bold;
        vertical-align: top;
        padding-top: 2px;
        padding-bottom: 2px;
    } */

    /* Keep the address text pinned to the top so there isn't empty space above it */
    .info-box td {
        font-size: 0.95rem;
        line-height: 1.2;
        padding-top: 2px;
        padding-bottom: 1px;
        vertical-align: top;
    }

    .info-box tr {
        display: flex;
        flex: 1;
    }

    .info-box th {
        flex-shrink: 0;
    }

    .info-box td {
        flex: 1;
        margin: 0;
        border: none;
    }

    /* Ensure no extra spacing in table cells */
    .info-box table td {
        padding:3.5px 3.5px 3.5px 3.5px;
        margin: 0;
    }

    /* Ensure vertical labels are properly sized */
    .vertical-label {
        min-height: auto;
        height: fit-content;
    }

    /* Vertical sidebar label without <br> stacking to avoid forcing tall rows */
    .vertical-label {
        writing-mode: vertical-rl;
        text-align: top !important;
        text-orientation: upright;
        width: 28px;
        letter-spacing: 4px;
        background: #4a171e;
        color: white;
        line-height: 1;
        padding: 2px 3px 0 3px;
        /* reduced padding to fit content */
        font-size: 0.8rem;
    }

    /* Right-side compact contact table styles */
    .compact-info-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #ddd;
        border-radius: 4px;
        line-height: 1.2;
    }

    .compact-info-table th {
        background-color: #f8f9fa;
        color: #333;
        padding: 6px 10px;
        text-align: center;
        font-weight: bold;
        font-size: 11px;
        text-transform: uppercase;
    }

    .compact-info-table td {
        padding: 6px 10px;
        text-align: center;
        background: white;
        border-top: 1px solid #eee;
        vertical-align: top;
        font-size: 0.95rem;
    }

    /* Make phone and email text smaller in the right box */
    .compact-info-table td:nth-child(1),
    .compact-info-table td:nth-child(2) {
        font-size: 0.85rem;
    }

    .order-details .row .col-12>div:first-child,
    .order-details .row .col-6>div:first-child {
        background-color: #f8f9fa;
        color: black;
        border: 1px solid #ddd;
        padding: 8px 12px;
        margin: -3px -12px 4px -12px;
        font-weight: bold;
        font-size: 11px;
        text-transform: uppercase;
    }
 
    .tss {
        line-height: 1.75;
        height: auto;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: stretch;
    }

    .col-lg-8,
    .col-lg-4 {
        display: flex;
        flex-direction: column;
    }

    .col-lg-8 .info-row {
        flex: 1;
    }

    .table th,
    .table td {
        padding: 0.45rem 0.75rem;
        /* reduced from default for about 10% less height */
    }

    .detailRow {
        height: 38px;
        font-size: 0.97rem;
    }

    .detailRow td {
        font-size: 0.93rem;
        padding-top: 0.25rem !important;
        padding-bottom: 0.25rem !important;
    }

    .detailRow td .quantity-input {
        height: 28px;
        font-size: 0.88rem;
        padding: 2px 6px;
        width: 70px;
        min-width: 70px;
        max-width: 100px;
    }

    .detailRow td .btn {
        height: 28px;
        font-size: 0.88rem;
        padding: 2px 10px;
        line-height: 1.1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .detailRow td .btn i {
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
    }

    /* ORDER ACKNOWLEDGEMENT Styles */
    .order-acknowledgement-header {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .shipping-terms-section {
        border: 1px solid #ddd;
    }

    .order-notes-section {
        border: 1px solid #ddd;
    }

    .footer-section {
        border-top: 2px solid #333;
    }

    .signature-section {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Table styling for ORDER ACKNOWLEDGEMENT look */
    #palletTable {
        border: 1px solid #ddd;
    }

    #palletTable thead th {
        background-color: #4a181d !important;
        color: white !important;
        font-weight: bold;
        text-align: center;
        border: 1px solid #555;
    }

    #palletTable tbody td {
        border: 1px solid #ddd;
        vertical-align: middle;
    }

    #palletTable tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    @media (max-width: 767.98px) {
        .info-row {
            flex-direction: column;
        }

        .info-box {
            width: 100%;
            margin: 0 0 10px 0;
        }

        .card {
            margin-top: 20px;
        }

        .table-responsive {
            overflow-x: auto;
            position: relative;
        }

        .table-responsive table {
            min-width: 900px;
        }

        .table th,
        .table td {
            white-space: nowrap;
        }

        /* Remove scroll hint gradient */
        .table-responsive::after {
            display: none !important;
        }

        /* Only for viewPallet page: allow product name to wrap in table */
        .table .product-name {
            white-space: normal !important;
            word-break: break-word;
            max-width: 120px;
            display: inline-block;
        }

        .table .product-info {
            flex-wrap: wrap;
        }
    }

    /* PRINT STYLES - match Order Detail design */
    @media print {
        body * {
            visibility: hidden;
        }

        main,
        main * {
            visibility: visible !important;
        }

        .page-header,
        .page-footer,
        header,
        footer,
        nav,
        aside,
        .breadcrumb-wrap,
        .no-print {
            display: none !important;
        }

        main {
            position: static !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        .container-fluid {
            max-width: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .row {
            display: flex !important;
            flex-wrap: wrap !important;
            margin-right: -15px !important;
            margin-left: -15px !important;
        }

        .col-lg-8 {
            width: 66.666667% !important;
            flex: 0 0 66.666667% !important;
            max-width: 66.666667% !important;
            padding-right: 15px !important;
            padding-left: 15px !important;
        }

        .col-lg-4 {
            width: 33.333333% !important;
            flex: 0 0 33.333333% !important;
            max-width: 33.333333% !important;
            padding-right: 15px !important;
            padding-left: 15px !important;
        }

        .col-lg-12 {
            width: 100% !important;
            flex: 0 0 100% !important;
            max-width: 100% !important;
            padding-right: 15px !important;
            padding-left: 15px !important;
        }

        .order-acknowledgement-header {
            border-bottom: 2px solid #333 !important;
            padding: 20px 0 !important;
            margin-bottom: 20px !important;
        }

        .project-info {
            background: #f8f9fa !important;
            padding: 10px 0 !important;
            margin-bottom: 15px !important;
            border-bottom: 1px solid #ddd !important;
        }

        .project-info p {
            margin: 0 !important;
            font-size: 14px !important;
            font-weight: bold !important;
            color: #333 !important;
        }

        .info-box table {
            font-size: 13px !important;
            width: 100% !important;
        }

        .info-box td {
            padding: 4px 6px !important;
        }

        .info-box strong {
            font-weight: bold !important;
        }

        .shipping-terms-section {
            background: #f8f9fa !important;
            padding: 6px !important;
            margin-bottom: 15px !important;
            font-size: 14px !important;
        }

        #palletTable {
            font-size: 12px !important;
            border-collapse: collapse !important;
        }

        #palletTable thead th {
            background-color: #4a181d !important;
            color: white !important;
            padding: 8px 4px !important;
            font-size: 11px !important;
            border: 1px solid #333 !important;
        }

        #palletTable tbody td {
            padding: 6px 4px !important;
            border: 1px solid #ddd !important;
            font-size: 11px !important;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate,
        .dataTables_wrapper .dataTables_buttons {
            display: none !important;
        }

        .btn,
        .no-print,
        .dataTables_wrapper .dt-buttons {
            display: none !important;
        }

        .dataTables_wrapper .dataTables_scroll {
            overflow: visible !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        .table th,
        .table td {
            white-space: nowrap !important;
        }
    }

    @media (max-width: 767.98px) {

        table,
        thead,
        tbody,
        tr,
        th,
        td {
            display: revert !important;
        }
    }
</style>
<!-- DataTables CSS for export buttons -->
<link href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet">
@endpush
@section('content')
<main class="main">
    <div id="toast-container"></div>

    @if(session('pallet_migrated'))
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin: 20px; border-radius: 8px;">
        <i class="fi-rs-check-circle me-2"></i>
        {{ session('pallet_migrated') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
 

    <!-- Project Information -->
    @if($palletAddress && $palletAddress->project_name)
    <div class="project-info" style="background: #f8f9fa; padding: 10px 0; border-bottom: 1px solid #ddd;">
        <div class="container-fluid px-4">

        <div class="d-flex justify-content-between align-items-center">
            <nav aria-label="breadcrumb" style="margin: 0;">
                <ol class="breadcrumb mb-0" style="background: transparent; padding: 0; margin: 0; font-size: 0.85rem;">
                    <li class="breadcrumb-item">
                        <a href="#" style="color: #6c757d; text-decoration: none; font-weight: 500;">Pallet</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <span style="color: #495057; font-weight: 600;">{{ $palletAddress->project_name }}</span>
                    </li>
                </ol>
            </nav>
            <a href="{{ url('product-filter') }}" class="btn btn-sm btn-outline-primary text-dark d-flex align-items-center custom-back-btn" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin-right: 3px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Product Filters
            </a>
        </div>
        </div>
        
    </div>
    @endif

    <section class="mt-4 mb-5">
        <div class="container-fluid px-4">

            <div class="row">
                <div class="col-lg-7">
                    <div class="info-row">
                        <div class="info-box">
                            <table style="width: 100%;">

                                <tr style="border: 1px solid #dddddd;border-radius: 4px;"">
                                    <th class="vertical-label tss">SOLD TO</th>
                                    <td style="line-height: 1.75;">
                                        {{ env('APP_NAME','Wisselbanken') }}<br>
                                        {{ env('COMPANY_ADDRESS','Abc revenue, xyz.') }}<br>
                                        PH: {{ env('COMPANY_PHONE','651-392-9405') }}<br>
                                        Email: {{ env('COMPANY_EMAIL','Mitch@wisselbanken.com') }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="info-box">
                            <table style="width: 100%;">
                                <tr style="border: 1px solid #dddddd;border-radius: 4px;"">
                                    <th class="vertical-label tss">SHIP TO</th>
                                    <td style="line-height: 1.75;">
                                        @if(count($palletItems) > 0)
                                        @if($palletAddress)

                                        {{ $palletAddress && $palletAddress->project_name ? $palletAddress->project_name : ($palletAddress && $palletAddress->name ? $palletAddress->name : (Auth::user()->name ?? '')) }}<br>
                                        {{ $palletAddress && $palletAddress->name ? $palletAddress->name : '' }}<br>
                                        {{ $palletAddress && $palletAddress->address1 ? Str::limit($palletAddress->address1, 50) : '' }}<br>
                                        {{ $palletAddress && $palletAddress->city ? $palletAddress->city : '' }}, {{ $palletAddress && $palletAddress->state && $palletAddress->state->state ? $palletAddress->state->state : '' }}<br>
                                        {{ $palletAddress && $palletAddress->postcode ? $palletAddress->postcode : '' }}
                                        @else
                                        <span class="text-muted" style="font-size: 0.9em;">
                                            <i class="fi-rs-info"></i> Address information will appear here when you add items to pallet
                                        </span>
                                        @endif
                                        @else
                                        <span class="text-muted" style="font-size: 0.9em;">
                                            <i class="fi-rs-info"></i> No pallet items
                                        </span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                </div>
                <div class="col-lg-5">
                    <div class="info-box" style="width:100%; margin:5px; padding:5px;">
                        <table class="compact-info-table" width="100%">
                            <tr>
                                <th colspan="2" width="100%">Account Manager</th>
                            </tr>
                            <tr>
                                <td style="width:33%;border: 1px solid #dddddd;"> 
                                    <div style="font-size: 11px;"><strong>First Name:</strong> John</div>
                                </td>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Last Name:</strong> Smith</div>
                                </td>

                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Company:</strong> Wisselbanken</div>
                                </td>
                            </tr> 
                            <tr>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Phone:</strong> (555) 123-4567</div>
                                </td>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Email:</strong> info@wisselbanken.com</div>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="2" width="100%">CUSTOMER CONTACT</th>
                            </tr>
                            <tr>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>First Name:</strong> 
                                        {{ Auth::user() ? (Auth::user()->first_name ?? explode(' ', Auth::user()->name)[0] ?? 'Customer') : 'Customer' }}
                                    </div>
                                </td>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Last Name:</strong> 
                                        {{ Auth::user() ? (Auth::user()->last_name ?? (explode(' ', Auth::user()->name)[1] ?? 'Test')) : 'Test' }}
                                    </div>
                                </td>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Project Name:</strong> 
                                        {{ $palletAddress && isset($palletAddress->project_name) && $palletAddress->project_name ? $palletAddress->project_name : (Auth::user() && Auth::user()->project_name ? Auth::user()->project_name : 'N/A') }}
                                    </div>
                                </td>
                            </tr>  
                            <tr>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Phone:</strong> 
                                        {{ $palletAddress && isset($palletAddress->phone) && $palletAddress->phone ? $palletAddress->phone : (Auth::user() && Auth::user()->phone ? Auth::user()->phone : '(555) 987-6543') }}
                                    </div>
                                </td>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Email:</strong> 
                                        {{ $palletAddress && isset($palletAddress->email) && $palletAddress->email ? $palletAddress->email : (Auth::user() && Auth::user()->email ? Auth::user()->email : 'customer@example.com') }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-lg-12">
                    <div class="shipping-terms-section" style="background: #f8f9fa; padding: 6px; border-radius: 5px; border: 1px solid #ddd;">
                        <div class="row">
                            <div class="col-md-2">
                                <p style="margin: 0; font-size: 12px;"><strong>SHIP VIA:</strong> WISSELBANKEN TRUCK</p>
                            </div>
                            <div class="col-md-3">
                                <p style="margin: 0; font-size: 12px;"><strong>NOTE DATE:</strong> 1-2 WEEKS FROM ORDER</p>
                            </div>
                            <div class="col-md-2">
                                <p style="margin: 0; font-size: 12px;"><strong>TERM:</strong> NET 30</p>
                            </div>
                            <div class="col-md-2">
                                <p style="margin: 0; font-size: 12px;"><strong>DATE:</strong> {{ isset($palletAddress->created_at) && $palletAddress->created_at ? date('m/d/Y',strtotime($palletAddress->created_at)) : date('m/d/Y') }}</p>
                            </div>
                            <div class="col-md-3">
                                <p style="margin: 0; font-size: 12px;"><strong>ORDER NUMBER:</strong> PO-{{ date('Y') }}{{ str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="table-responsive">
                        <table id="palletTable" class="table table-border table-hover nowrap w-100 mb-0">
                            <thead>
                                <tr class="bg-primary text-white">
                                    <th>Line</th>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Size</th>
                                    <th>Thickness</th>
                                    <th>Finish</th>
                                    <th>Color</th>
                                    <th>Unit Price</th>
                                    <th>Total Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($palletItems) > 0)
                                @foreach($palletItems as $index => $item)
                                @php
                                $product = $item->productVariation->product;

                                // Handle both database and session data structures
                                if (isset($palletData)) {
                                // Database data structure
                                $palletItem = $palletData[$item->id] ?? null;
                                $quantity = $palletItem ? ($palletItem['quantity'] ?? 0) : 0;
                                } else {
                                // Session data structure
                                $palletItem = $pallet[$item->id] ?? null;
                                $quantity = $palletItem ? ($palletItem['quantity'] ?? 0) : 0;
                                }

                                $price = $item->productVariation->pricing ?? 0;
                                $subtotal = $quantity * $price;
                                @endphp
                                <tr class="detailRow">
                                    <td>{{ $index + 1 }}</td>
                                    <td class="product-cell">
                                        <div class="product-info">
                                            <img src="{{ asset($product->feature_image ?? 'demo.jpg') }}" data-fallback="{{ asset('demo.jpg') }}" class="product-image" alt="{{ $product->name }}" onerror="this.onerror=null; this.src=this.getAttribute('data-fallback');">
                                            <a class="product-name" href="{{ url('product-detail/' . $product->slug) }}">{{ $product->name }}</a>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="quantity-input part-quantity"
                                            data-price="{{ $price }}"
                                            value="{{ $quantity }}" min="0"
                                            variation-id="{{ encrypt($item->product_variation_id) }}"
                                            color-id="{{ encrypt($item->color_id) }}"
                                            product-color-variation-id="{{ encrypt($item->id) }}">
                                    </td>
                                    <td>{{ $item->productVariation->size->name ?? 'N/A' }}</td>
                                    <td>{{ $item->productVariation->thickness->name ?? 'N/A' }}</td>
                                    <td>{{ $item->productVariation->finish->name ?? 'N/A' }}</td>
                                    <td>{{ $item->color->name ?? 'N/A' }}</td>
                                    <td class="cart-unitPrice">${{ number_format($price, 2) }}</td>
                                    <td><span class="cart-extendedPrice">${{ number_format($subtotal, 2) }}</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger trash-item" title="Remove item">
                                            <i class="fi-rs-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="14" class="text-center text-muted">No products found</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order Notes Section -->
                <div class="col-lg-12 mb-4">
                    <div class="order-notes-section" style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                        <h5 style="margin: 0 0 10px 0; color: #333;">ORDER NOTES:</h5>
                        <p style="margin: 0; font-size: 14px; color: #666;">SHIP WITH PALLET ITEMS ON {{ date('m/d/Y') }}. ALL ITEMS ARE SUBJECT TO AVAILABILITY AND MARKET PRICING.</p>
                    </div>
                </div>
                <div class="col-lg-8">
                    <!-- Order Notes and Financial Summary Section -->
                    <div class="order-notes-financial-section" style="background: white; padding: 20px 0; margin-top: 20px;">

                        <div style="background: #f5f5f5; border: 1px solid #333; padding: 10px; margin-bottom: 20px;">
                            <p style="margin: 0; font-weight: bold; text-transform: uppercase; font-size: 12px;">NOTE: A 15% FUEL SURCHARGE WILL BE ASSESSED ON ALL FREIGHT</p>
                        </div>

                        <div style="font-size: 12px; color: #333; line-height: 1.6; margin-bottom: 20px;">
                            <p style="margin-bottom: 10px; font-weight: bold; font-style: italic;">OIL CANNING IS A WAVINESS IN THE FLAT AREAS OF A PANEL. OIL CANNING IS AN INHERENT CHARACTERISTIC OF LIGHT-GAUGE METAL ROOFING AND SIDING, NOT A DEFECT, AND THEREFORE NOT A CAUSE FOR REJECTION. WE STRONGLY RECOMMENDED THE USE OF STRIATIONS, STIFFENERS AND OR EMBOSSING TO MINIMIZE THIS EFFECT.</p>
                        </div>

                        <div style="font-size: 12px; color: #333; line-height: 1.6; margin-bottom: 20px;">
                            <p style="margin-bottom: 5px;">PLEASE SIGN BELOW TO APPROVE ORDER AND ALL CORRESPONDING FABRICATION GUIDES (IF APPLICABLE)</p>
                            <p style="margin-bottom: 5px;">By signing below, the customer is releasing order for immediate shipment.</p>
                            <p style="margin-bottom: 15px;">Any delays in fabrication or delivery caused by customer will result in material being repriced subject to market pricing at time of delivery.</p>
                        </div>

                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div style="flex: 1;">
                                <p style="margin: 0 0 5px 0; font-size: 12px; font-weight: bold;">SIGNATURE:</p>
                                <div style="border-bottom: 1px solid #333; height: 30px;"></div>
                            </div>
                            <div style="flex: 1;">
                                <p style="margin: 0 0 5px 0; font-size: 12px; font-weight: bold;">DATE:</p>
                                <div style="border-bottom: 1px solid #333; height: 30px;"></div>
                            </div>
                        </div>

                        <div style="font-size: 11px; color: #333; line-height: 1.4; margin-bottom: 20px;">
                            <p style="margin-bottom: 5px; font-weight: bold;">PLEASE NOTE:</p>
                            <p style="margin-bottom: 3px;">NO PRODUCTION WILL BEGIN UNTIL SIGNED ACKNOWLEDGMENT IS RETURNED.</p>
                            <p style="margin-bottom: 3px;">LEAD TIMES BEGIN UPON RECEIPT OF SIGNED ORDER ACKNOWLEDGEMENT.</p>
                            <p style="margin-bottom: 3px;">BY SIGNING YOU ARE CONFIRMING THAT ORDER ABOVE IS ACCURATE AND COMPLETE.</p>
                            <p style="margin-bottom: 3px;">YOU ARE ALSO AGREEING TO WISSELBANKEN'S TERMS AND CONDITIONS OF SALE.</p>
                        </div>
                    </div>

                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm" style="top: 20px; margin-bottom:40px;">
                        <div class="card-header bg-light py-3">
                            <h5 class="mb-0">Pallet Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span class="subtotal">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Sales Tax <span id="tax-rate-label"></span></span>
                                <span id="tax-amount">$0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold" id="total-amount">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            <a href="{{url('checkout')}}" class="btn btn-primary w-100 py-2 mb-3">Checkout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>




</main>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
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
        // Removed equal height functionality to allow boxes to fit their content naturally
        // function equalizeHeaderHeights() {
        //     var maxH = 0;
        //     var minDesired = 160; // reduce height to fit content better
        //     $('.equal-h').css('height', 'auto');
        //     $('.equal-h').each(function() {
        //         maxH = Math.max(maxH, $(this).outerHeight());
        //     });
        //     maxH = Math.max(maxH, minDesired);
        //     $('.equal-h').outerHeight(maxH);
        // }
        // equalizeHeaderHeights();
        // $(window).on('load resize', equalizeHeaderHeights);
        // State tax rates from backend
        const parsedStateTaxRates = @json($stateTaxRates);

        // Get the state from pallet address if available
        const palletState = "{{ $palletAddress && $palletAddress->state && $palletAddress->state->state ? $palletAddress->state->state : '' }}";

        function updatePalletTotal() {
            let total = 0;
            $(".detailRow").each(function() {
                let $row = $(this);
                let quantity = parseInt($row.find(".part-quantity").val()) || 1;
                let unitPrice = parseFloat($row.find(".cart-unitPrice").text().replace('$', ''));
                let subtotal = quantity * unitPrice;

                $row.find(".cart-extendedPrice").text(`$${subtotal.toFixed(2)}`);
                total += subtotal;
            });

            // Calculate tax based on state
            const taxRate = palletState && parsedStateTaxRates[palletState] ? parseFloat(parsedStateTaxRates[palletState]) : 0;
            const tax = total * (taxRate / 100);
            const grandTotal = total + tax;

            // Update pallet summary display
            $(".subtotal").text(`$${total.toFixed(2)}`);
            $("#tax-rate-label").text(taxRate ? `(${taxRate}%)` : '');
            $("#tax-amount").text(`$${tax.toFixed(2)}`);
            $("#total-amount").text(`$${grandTotal.toFixed(2)}`);

            // Update financial summary section
            $("#financial-tax-amount").text(`$${tax.toFixed(2)}`);
            $("#financial-total-amount").text(`$${grandTotal.toFixed(2)}`);
        }

        $(document).on("input", ".part-quantity", function() {
            let $row = $(this).closest(".detailRow");
            let quantity = parseInt($(this).val());

            if (quantity < 1) {
                $(this).val(1);
                showToast("Quantity should be at least 1.", "info");
                return;
            }

            let variationId = $(this).attr("variation-id");
            let colorId = $(this).attr("color-id");
            let productColorVariationId = $(this).attr("product-color-variation-id");

            $.ajax({
                url: "{{ route('pallet.update-item') }}",
                type: "POST",
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                data: {
                    variationId: variationId,
                    colorId: colorId,
                    productColorVariationId: productColorVariationId,
                    quantity: quantity,
                    action: "add"
                },
                success: function(response) {
                    updatePalletTotal();
                    updateMiniPallet();
                    showToast(response.message, "success");
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.message || "Error updating pallet.", "Error", "error");
                }
            });

            updatePalletTotal();
        });

        $(document).on("click", ".trash-item", function() {
            let $row = $(this).closest(".detailRow");
            let variationId = $row.find(".part-quantity").attr("product-color-variation-id");

            Swal.fire({
                title: "Are you sure?",
                text: "Do you want to remove this item from your pallet?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, remove it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('pallet-item-remove') }}",
                        type: "POST",
                        headers: {
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        data: {
                            item_id: variationId
                        },
                        success: function(response) {
                            $row.remove();
                            updateMiniPallet();
                            updatePalletTotal();
                            showToast(response.message, "warning");
                        },
                        error: function(xhr) {
                            showToast(xhr.responseJSON?.message || "Error removing item.", "Error", "error");
                        }
                    });
                }
            });
        });

        // Calculate initial tax and update financial summary
        const initialTaxRate = palletState && parsedStateTaxRates[palletState] ? parseFloat(parsedStateTaxRates[palletState]) : 0;
        const initialTax = parseFloat("{{ $subtotal }}") * (initialTaxRate / 100);
        const initialGrandTotal = parseFloat("{{ $subtotal }}") + initialTax;

        $("#financial-tax-amount").text(`$${initialTax.toFixed(2)}`);
        $("#financial-total-amount").text(`$${initialGrandTotal.toFixed(2)}`);

        updatePalletTotal();

        // Only initialize DataTable if there is at least one data row
        var hasData = $('#palletTable tbody tr').length > 0 &&
            !$('#palletTable tbody tr td').hasClass('text-center'); // Exclude 'No products found' row
        if (hasData) {
            $('#palletTable').DataTable({
                dom: 'Blfrtip',
                buttons: [{
                        extend: 'csv',
                        exportOptions: {
                            columns: ':visible',
                            format: {
                                body: function(data, row, column, node) {
                                    // Product column (index 0): extract only the product name
                                    if (column === 0) {
                                        var $a = $(node).find('a.product-name');
                                        if ($a.length) {
                                            return $a.text().trim();
                                        }
                                        return $(node).text().trim();
                                    }
                                    // Quantity column (index 1): get value of input
                                    if (column === 1) {
                                        return $(node).find('input').val() || '';
                                    }
                                    // Other columns: just get the text
                                    return $(node).text().trim();
                                }
                            }
                        }
                    },
                    {
                        extend: 'excel',
                        exportOptions: {
                            columns: ':visible',
                            format: {
                                body: function(data, row, column, node) {
                                    if (column === 0) {
                                        var $a = $(node).find('a.product-name');
                                        if ($a.length) {
                                            return $a.text().trim();
                                        }
                                        return $(node).text().trim();
                                    }
                                    if (column === 1) {
                                        return $(node).find('input').val() || '';
                                    }
                                    return $(node).text().trim();
                                }
                            }
                        }
                    }
                ],
                paging: false,
                searching: false,
                info: false,
                ordering: false,
                responsive: true,
                scrollX: true
            });
        }
    });
</script>
@endpush
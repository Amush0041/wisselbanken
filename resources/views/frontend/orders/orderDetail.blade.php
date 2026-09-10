@extends('frontend.layouts.app')
@push('seo')
<title>Order Detail | {{env('APP_NAME','Wisselbanken')}}</title>
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
        height: auto;
        padding: 7px;
        flex-shrink: 0;
        font-size: 11px;
    }

    .info-box td {
        flex: 1;
        margin: 0;
        border: none;
    }

    /* Ensure no extra spacing in table cells */
    .info-box table td {
        padding: 3.5px 3.5px 3.5px 3.5px;
        margin: 0;
    }

    .info-box strong {
        font-weight: bold;
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

    /* Right-side compact contact table styles to match pallet view */
    .compact-info-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #ddd;
        border-radius: 4px;
        line-height: 1.2;
        height: 100%;
        display: flex;
        flex-direction: column;
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
        padding: 6px 10px !important;
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

    /* Removed equal height helper classes since we're no longer using them */
    /* .equal-h {
        box-sizing: border-box;
    }

    .equal-h>table {
        height: 100%;
    } */

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

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: stretch;
    }

    .col-lg-7,
    .col-lg-5 {
        display: flex;
        flex-direction: column;
    }

    .col-lg-7 .info-row {
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
    #orderTable {
        border: 1px solid #ddd;
    }

    #orderTable thead th {
        background-color: #4a181d !important;
        color: white !important;
        font-weight: bold;
        text-align: center;
        border: 1px solid #555;
    }

    #orderTable tbody td {
        border: 1px solid #ddd;
        vertical-align: middle;
    }

    #orderTable tbody tr:nth-child(even) {
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

        /* Only for orderDetail page: allow product name to wrap in table */
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

    /* PRINT STYLES */
    @media print {

        /* Hide all elements by default */
        body * {
            visibility: hidden;
        }

        /* Show only the main content */
        main,
        main * {
            visibility: visible !important;
        }

        /* Hide navigation and other elements */
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

        /* Reset positioning for print */
        main {
            position: static !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Page setup - A4 portrait to match web view */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        /* Container adjustments */
        .container-fluid {
            max-width: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Ensure proper Bootstrap grid in print */
        .row {
            display: flex !important;
            flex-wrap: wrap !important;
            margin-right: -15px !important;
            margin-left: -15px !important;
        }

        .col-md-9,
        .col-lg-9 {
            width: 70% !important;
            flex: 0 0 70% !important;
            max-width: 70% !important;
            padding-right: 15px !important;
            padding-left: 15px !important;
        }

        .col-md-3,
        .col-lg-3 {
            width: 30% !important;
            flex: 0 0 30% !important;
            max-width: 30% !important;
            padding-right: 15px !important;
            padding-left: 15px !important;
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

        /* Keep desktop layout - don't apply mobile styles */
        .info-row {
            display: flex !important;
            justify-content: space-between !important;
            flex-direction: row !important;
        }

        .info-box {
            width: 48% !important;
            padding: 5px !important;
            margin: 5px !important;
        }

        /* Header adjustments - keep desktop size */
        .order-acknowledgement-header {
            border-bottom: 2px solid #333 !important;
            padding: 20px 0 !important;
            margin-bottom: 20px !important;
        }

        /* Company info adjustments - keep desktop size */
        .company-info img {
            height: 60px !important;
            max-width: 200px !important;
            object-fit: contain !important;
        }

        .company-info p {
            margin: 5px 0 !important;
            font-size: 14px !important;
            color: #666 !important;
        }

        /* Order details box - keep desktop size */
        .order-details {
            border: 1px solid #333 !important;
            border-radius: 4px !important;
            overflow: hidden !important;
            font-size: 12px !important;
            margin-top: 15px !important;
        }

        .order-details .row {
            margin: 0 !important;
        }

        .order-details .col-6,
        .order-details .col-12 {
            padding: 8px 12px !important;
            text-align: center !important;
        }

        .order-details .col-6 {
            border-right: 1px solid #333 !important;
        }

        .order-details .row:not(:first-child) {
            border-top: 1px solid #333 !important;
        }

        /* Project info - keep desktop size */
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

        .tss {
            line-height: 1.75;
            height: auto;
        }

        /*   / SHIP TO tables - keep desktop size */
        .info-box table {
            font-size: 13px !important;
            width: 100% !important;
        }

        /* .info-box th {
            width: 50px !important;
            background-color: #0c0c0c !important;
            text-align: top !important;
            color: white !important;
            font-size: 12px !important;
            padding: 4px !important;
        } */

        .info-box td {
            padding: 4px 6px !important;
        }

        /* Ensure proper text alignment and styling */
        .info-box strong {
            font-weight: bold !important;
        }

        /* Shipping terms - keep desktop size */
        .shipping-terms-section {
            background: #f8f9fa !important;
            padding: 6px !important;
            margin-bottom: 15px !important;
            font-size: 14px !important;
        }

        /* Product table - keep desktop size */
        #orderTable {
            font-size: 12px !important;
            border-collapse: collapse !important;
        }

        #orderTable thead th {
            background-color: #4a181d !important;
            color: white !important;
            padding: 8px 4px !important;
            font-size: 11px !important;
            border: 1px solid #333 !important;
        }

        #orderTable tbody td {
            padding: 6px 4px !important;
            border: 1px solid #ddd !important;
            font-size: 11px !important;
        }

        #orderTable .product-image {
            width: 50px !important;
            height: 50px !important;
        }

        /* Order notes - keep desktop size */
        .order-notes-section {
            background: #f8f9fa !important;
            padding: 15px !important;
            margin-bottom: 15px !important;
            font-size: 14px !important;
        }

        /* Order notes and financial section - ensure proper display */
        .order-notes-financial-section {
            background: white !important;
            padding: 20px 0 !important;
            margin-top: 20px !important;
            page-break-inside: avoid !important;
        }

        /* Ensure flex layout works in print */
        .d-flex {
            display: flex !important;
        }

        .justify-content-between {
            justify-content: space-between !important;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        /* Fix any inline styles that might interfere */
        [style*="top: 20px"] {
            top: auto !important;
            position: static !important;
        }

        /* Footer content - keep desktop size */
        .order-notes-financial-section {
            font-size: 12px !important;
            line-height: 1.6 !important;
        }

        .order-notes-financial-section>div {
            margin-bottom: 20px !important;
        }

        /* Layout fixes for print */
        .row {
            display: flex !important;
            flex-wrap: wrap !important;
        }

        .col-lg-8 {
            width: 66.666667% !important;
            flex: 0 0 66.666667% !important;
            max-width: 66.666667% !important;
        }

        .col-lg-4 {
            width: 33.333333% !important;
            flex: 0 0 33.333333% !important;
            max-width: 33.333333% !important;
        }

        .col-lg-12 {
            width: 100% !important;
            flex: 0 0 100% !important;
            max-width: 100% !important;
        }

        /* Summary card - keep desktop size */
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            position: static !important;
            top: auto !important;
        }

        .card-header {
            background: #f8f9fa !important;
            padding: 12px !important;
            font-size: 14px !important;
        }

        .card-body {
            padding: 12px !important;
            font-size: 13px !important;
        }

        .card-footer {
            background: #f8f9fa !important;
            padding: 12px !important;
            font-size: 12px !important;
        }

        /* Ensure proper spacing */
        .mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .mt-4 {
            margin-top: 1.5rem !important;
        }

        .mb-5 {
            margin-bottom: 3rem !important;
        }

        /* Hide DataTables elements */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate,
        .dataTables_wrapper .dataTables_buttons {
            display: none !important;
        }

        /* Hide all buttons in print */
        .btn,
        .no-print,
        .dataTables_wrapper .dt-buttons {
            display: none !important;
        }

        /* Hide navigation elements */
        .custom-back-btn,
        [onclick*="printInvoice"],
        [onclick*="window.print"] {
            display: none !important;
        }

        /* Ensure table is visible and not responsive */
        .dataTables_wrapper .dataTables_scroll {
            overflow: visible !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        /* Keep desktop table layout */
        .table th,
        .table td {
            white-space: nowrap !important;
        }

        /* Force page breaks */
        .page-break {
            page-break-before: always;
        }

        /* Ensure no page breaks within important elements */
        .info-row,
        .shipping-terms-section,
        .order-notes-section {
            page-break-inside: avoid;
        }

        /* Override any mobile styles */
        @media (max-width: 767.98px) {
            .info-row {
                flex-direction: row !important;
            }

            .info-box {
                width: 48% !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            .table th,
            .table td {
                white-space: nowrap !important;
            }
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

    <!-- Project Information -->
    @if($order->project_title || $order->name)
    <div class="project-info" style="background: #f8f9fa; padding: 10px 0; border-bottom: 1px solid #ddd;">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb" style="margin: 0;">
                    <ol class="breadcrumb mb-0" style="background: transparent; padding: 0; margin: 0; font-size: 0.85rem;">
                        <li class="breadcrumb-item">
                            <a href="#" style="color: #6c757d; text-decoration: none; font-weight: 500;">Order</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <span style="color: #495057; font-weight: 600;">{{ $order->project_title ? $order->project_title : $order->name }}</span>
                        </li>
                    </ol>
                </nav>
                <div class="d-flex gap-2">
                    <a href="{{ url('view-orders') }}" class="btn btn-sm btn-outline-primary text-dark d-flex align-items-center custom-back-btn" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin-right: 3px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Orders
                    </a>
                    <button class="btn btn-sm btn-primary no-print" onclick="printInvoice()" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 12px;">
                        <i class="fi-rs-printer"></i> Print
                    </button>
                </div>
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
                                <tr style="border: 1px solid #dddddd;border-radius: 4px;height: 173px;">
                                    <th class="vertical-label tss">SOLD TO</th>
                                    <td style="line-height: 1.75;">
                                        {{ env('APP_NAME','Wisselbanken') }}<br>
                                        {{ env('COMPANY_ADDRESS','Abc revenue, xyz.') }}<br>
                                        Minneapolis, Minnesota<br>
                                        PH: {{ env('COMPANY_PHONE','651-392-9405') }}<br>
                                        Email: {{ env('COMPANY_EMAIL','Mitch@wisselbanken.com') }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="info-box">
                            <table style="width: 100%;">
                                <tr style="border: 1px solid #dddddd;border-radius: 4px;height: 173px;">
                                    <th class="vertical-label tss">SHIP TO</th>
                                    <td style="line-height: 1.75;">
                                        {{ $order->project_title ? $order->project_title : $order->name }}<br>
                                        {{ Str::limit($order->address1, 50) }}<br>
                                        {{ $order->city }}, {{ $order->state }}<br>
                                        {{ $order->postcode }}<br>
                                        PH: {{ $order->phone }}<br>
                                        Email: {{ $order->email }}
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
                                    <div style="font-size: 11px;"><strong>First Name:</strong> 
                                        {{ $order->user ? (explode(' ', $order->user->name)[0] ?? 'John') : 'John' }}
                                    </div>
                                </td>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Last Name:</strong> 
                                        {{ $order->user ? (explode(' ', $order->user->name)[1] ?? 'Smith') : 'Smith' }}
                                    </div>
                                </td>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Company:</strong> 
                                        {{ $order->user ? $order->user->name : 'Wisselbanken' }}
                                    </div>
                                </td>
                            </tr> 
                            <tr>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Phone:</strong> 
                                        {{ env('COMPANY_PHONE','651-392-9405') }}
                                    </div>
                                </td>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Email:</strong> 
                                        {{ env('COMPANY_EMAIL','Mitch@wisselbanken.com') }}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="2" width="100%">CUSTOMER CONTACT</th>
                            </tr>
                            <tr>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>First Name:</strong> 
                                        {{ $order->name ? (explode(' ', $order->name)[0] ?? 'Customer') : 'Customer' }}
                                    </div>
                                </td>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Last Name:</strong> 
                                        {{ $order->name ? (explode(' ', $order->name)[1] ?? '') : '' }}
                                    </div>
                                </td>
                                <td style="width:33%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Project Name:</strong> 
                                        {{ $order->project_title ? $order->project_title : 'N/A' }}
                                    </div>
                                </td>
                            </tr>  
                            <tr>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Phone:</strong> 
                                        {{ $order->phone ?? '(555) 987-6543' }}
                                    </div>
                                </td>
                                <td style="width:50%;border: 1px solid #dddddd;">
                                    <div style="font-size: 11px;"><strong>Email:</strong> 
                                        {{ $order->email ?? 'customer@example.com' }}
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
                                <p style="margin: 0;font-size: 12px;"><strong>SHIP VIA:</strong> WISSELBANKEN TRUCK</p>
                            </div>
                            <div class="col-md-3">
                                <p style="margin: 0;font-size: 12px;"><strong>NOTE DATE:</strong> 1-2 WEEKS FROM ORDER</p>
                            </div>
                            <div class="col-md-2">
                                <p style="margin: 0;font-size: 12px;"><strong>TERM:</strong> NET 30</p>
                            </div>
                            <div class="col-md-2">
                                <p style="margin: 0;font-size: 12px;"><strong>DATE:</strong> {{ date('m/d/Y',strtotime($order->created_at)) }}</p>
                            </div>
                            <div class="col-md-3">
                                <p style="margin: 0;font-size: 12px;"><strong>ORDER NUMBER:</strong> {{ $order->order_number ?? 'PO-' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="row">
                <div class="col-lg-12">
                    <div class="table-responsive">
                        <table id="orderTable" class="table table-border table-hover nowrap w-100 mb-0">
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
                                </tr>
                            </thead>
                            <tbody>
                                @if(count($orderItems) > 0)
                                @foreach($orderItems as $index => $item)
                                @php
                                $product = $item->productVariation->product;
                                $orderItem = $order->items->where('product_variation_color_id', $item->id)->first();
                                $quantity = $orderItem->quantity ?? 0;
                                $price = $item->productVariation->pricing ?? 0;
                                $subtotal = $quantity * $price;
                                @endphp
                                <tr class="detailRow">
                                    <td>{{ $index + 1 }}</td>
                                    <td class="product-cell">
                                        <div class="product-info">
                                            <img src="{{ asset($product->feature_image ?? 'demo.jpg') }}" class="product-image" alt="{{ $product->name }}" onerror="this.onerror=null; this.src='{{ asset('demo.jpg') }}';">
                                            <a class="product-name" href="{{ url('product-detail/' . $product->slug) }}">{{ $product->name }}</a>
                                        </div>
                                    </td>
                                    <td>{{ $quantity }}</td>
                                    <td>{{ $item->productVariation->size->name ?? 'N/A' }}</td>
                                    <td>{{ $item->productVariation->thickness->name ?? 'N/A' }}</td>
                                    <td>{{ $item->productVariation->finish->name ?? 'N/A' }}</td>
                                    <td>{{ $item->color->name ?? 'N/A' }}</td>
                                    <td>${{ number_format($price, 2) }}</td>
                                    <td>${{ number_format($subtotal, 2) }}</td>
                                </tr>
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No products found</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order Notes Section -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="order-notes-section" style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                        <h5 style="margin: 0 0 10px 0; color: #333;">ORDER NOTES:</h5>
                        <p style="margin: 0; font-size: 14px; color: #666;">SHIP WITH ORDER ITEMS ON {{ \Carbon\Carbon::parse($order->created_at)->format('m/d/Y') }}. ALL ITEMS ARE SUBJECT TO AVAILABILITY AND MARKET PRICING.</p>
                    </div>
                </div>
            </div>

            <div class="row">
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
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span class="subtotal">${{ number_format($order->subtotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Sales Tax @if($order->tax_rate) ({{ $order->tax_rate }}%) @endif</span>
                                <span id="tax-amount">${{ number_format($order->tax, 2) }}</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold" id="total-amount">${{ number_format($order->total, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Payment Method</span>
                                <span>{{ strtoupper($order->payment_method) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Status</span>
                                @php
                                    $badgeClass = match($order->status) {
                                        'processing'       => 'bg-primary',
                                        'completed'        => 'bg-success',
                                        'cancelled'        => 'bg-danger',
                                        'pending_approval' => 'bg-warning text-dark',
                                        default            => 'bg-secondary',
                                    };
                                    $statusLabel = $order->status === 'pending_approval' ? 'Pending Approval' : ucfirst($order->status);
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Order Date</span>
                                <span>{{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y h:i A') }}</span>
                            </div>
                        </div>

                        {{-- Approval / Rejection details --}}
                        @if ($order->approved_by || $order->rejected_by)
                        <div class="card-body border-top pt-3 pb-2">
                            @if ($order->approved_by && $order->approvedBy)
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <i class="ti ti-circle-check text-success mt-1" style="font-size:1.1rem;flex-shrink:0"></i>
                                <div>
                                    <div class="fw-semibold text-success" style="font-size:.83rem">Approved</div>
                                    <div class="text-muted" style="font-size:.8rem">by {{ $order->approvedBy->name }}</div>
                                    <div class="text-muted" style="font-size:.78rem">{{ $order->updated_at->format('M d, Y \a\t h:i A') }}</div>
                                </div>
                            </div>
                            @elseif ($order->rejected_by && $order->rejectedBy)
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <i class="ti ti-circle-x text-danger mt-1" style="font-size:1.1rem;flex-shrink:0"></i>
                                <div>
                                    <div class="fw-semibold text-danger" style="font-size:.83rem">Rejected</div>
                                    <div class="text-muted" style="font-size:.8rem">by {{ $order->rejectedBy->name }}</div>
                                    <div class="text-muted" style="font-size:.78rem">{{ $order->updated_at->format('M d, Y \a\t h:i A') }}</div>
                                </div>
                            </div>
                            @endif
                            @if ($order->approval_note)
                            <div class="bg-light rounded p-2 mt-1" style="font-size:.8rem">
                                <span class="fw-semibold text-muted">Note: </span>{{ $order->approval_note }}
                            </div>
                            @endif
                        </div>
                        @endif

                        <div class="card-footer bg-light no-print">
                            <p class="small text-muted mb-0">
                                <strong>Note:</strong> {{ $order->notes ?? 'No additional notes' }}
                            </p>
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
<script>
    // Print function
    function printInvoice() {
        // Hide DataTables elements before printing
        $('.dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_processing, .dataTables_wrapper .dataTables_paginate, .dataTables_wrapper .dataTables_buttons').hide();

        // Trigger print
        window.print();

        // Show DataTables elements after printing
        setTimeout(function() {
            $('.dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_processing, .dataTables_wrapper .dataTables_paginate, .dataTables_wrapper .dataTables_buttons').show();
        }, 1000);
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
        // Only initialize DataTable if there is at least one data row
        var hasData = $('#orderTable tbody tr').length > 0 &&
            !$('#orderTable tbody tr td').hasClass('text-center'); // Exclude 'No products found' row
        if (hasData) {
            $('#orderTable').DataTable({
                dom: 'Blfrtip',
                buttons: [{
                        extend: 'csv',
                        exportOptions: {
                            columns: ':visible',
                            format: {
                                body: function(data, row, column, node) {
                                    // Product column (index 1): extract only the product name
                                    if (column === 1) {
                                        var $a = $(node).find('a.product-name');
                                        if ($a.length) {
                                            return $a.text().trim();
                                        }
                                        return $(node).text().trim();
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
                                    if (column === 1) {
                                        var $a = $(node).find('a.product-name');
                                        if ($a.length) {
                                            return $a.text().trim();
                                        }
                                        return $(node).text().trim();
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
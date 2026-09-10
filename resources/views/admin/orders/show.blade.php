@extends('admin.layouts.app')
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
        padding: 5px;
        margin: 5px;
        width: 48%;
    }

    .info-box th {
        width: 50px;
        background-color: #0c0c0c;
        text-align: center;
        color: white;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    .table thead th,
    .table thead tr th {
        background: #fff !important;
        color: #222 !important;
        font-weight: 600;
    }

    .table thead tr.bg-primary th {
        background: #4a171e !important;
        color: #fff !important;
    }

    @media (max-width: 991.98px) {
        .info-row {
            flex-direction: column;
        }

        .info-box {
            width: 100%;
            margin-bottom: 10px;
        }
    }

    @media (max-width: 767.98px) {
        .product-info {
            flex-direction: column;
            align-items: flex-start;
        }

        .product-image {
            width: 40px;
            height: 40px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table th,
        .table td {
            white-space: nowrap;
        }
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #print-section,
        #print-section * {
            visibility: visible;
        }

        #print-section {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .no-print,
        .no-print * {
            display: none !important;
        }

        .page-header,
        .page-footer,
        header,
        footer,
        nav,
        aside {
            display: none !important;
        }

        @page {
            size: auto;
            margin: 5mm;
        }

        table {
            width: 100%;
            font-size: 11px;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 4px;
        }
    }
</style>
@endpush
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <section class="mt-2 mb-2" id="print-section">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h3 class="fw-bold">Order #{{ $order->order_number ?? $order->id }}</h3>
                                <p class="text-muted">Date: {{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y h:i A') }}</p>
                            </div>
                            <div class="col-md-6 text-end no-print">
                                <button class="btn btn-primary" onclick="window.print()">
                                    <i class="fi-rs-printer"></i> Print Invoice
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="info-row">
                                    <div class="info-box">
                                        <table>
                                            <tr>
                                                <th>S<br>O<br>L<br>D<br><br>T<br>O</th>
                                                <td>
                                                    {{ env('APP_NAME','Wisselbanken') }}<br>
                                                    {{ env('COMPANY_ADDRESS','Abc revenue, xyz.') }}<br>
                                                    PH: {{ env('COMPANY_PHONE','651-392-9405') }}<br>
                                                    Email: {{ env('COMPANY_EMAIL','Mitch@wisselbanken.com') }}
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="info-box">
                                        <table>
                                            <tr>
                                                <th>S<br>H<br>I<br>P<br><br>T<br>O</th>
                                                <td>
                                                    {{ $order->first_name ?? '-' }} {{ $order->last_name ?? '' }}<br>
                                                    {{ $order->address1 ?? '-' }}<br>
                                                    @if($order->address2)
                                                    {{ $order->address2 }}<br>
                                                    @endif
                                                    {{ $order->city ?? '' }}, {{ $order->state ?? '' }} {{ $order->postcode ?? '' }}<br>
                                                    {{ $order->country ?? '' }}<br>
                                                    PH: {{ $order->phone ?? '-' }}<br>
                                                    Email: {{ $order->email ?? '-' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-border table-hover nowrap w-100">
                                        <thead>
                                            <tr class="bg-primary text-white">
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Subtotal</th>
                                                <th>Division</th>
                                                <th>Specification</th>
                                                <th>Manufacturer</th>
                                                <th>Size</th>
                                                <th>Thickness</th>
                                                <th>Finish</th>
                                                <th>Paint Type</th>
                                                <th>Color</th>
                                                <th>Color Effect</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(count($orderItems) > 0)
                                            @foreach($orderItems as $item)
                                            @php
                                            $product = $item->productVariation->product;
                                            $orderItem = $order->items->where('product_variation_color_id', $item->id)->first();
                                            $quantity = $orderItem->quantity ?? 0;
                                            $price = $item->productVariation->pricing ?? 0;
                                            $subtotal = $quantity * $price;
                                            @endphp
                                            <tr>
                                                <td class="product-cell">
                                                    <div class="product-info">
                                                        <img src="{{ asset($product->feature_image ?? 'demo.jpg') }}" class="product-image" alt="{{ $product->name }}">
                                                        <span class="product-name">{{ $product->name }}</span>
                                                    </div>
                                                </td>
                                                <td>{{ $quantity }}</td>
                                                <td>${{ number_format($price, 2) }}</td>
                                                <td>${{ number_format($subtotal, 2) }}</td>
                                                <td>{{ $product->division->code ?? '' }}</td>
                                                <td>{{ $product->specification->specification_number ?? '' }}</td>
                                                <td>{{ $product->manufacturer->name ?? '' }}</td>
                                                <td>{{ $item->productVariation->size->name ?? 'N/A' }}</td>
                                                <td>{{ $item->productVariation->thickness->name ?? 'N/A' }}</td>
                                                <td>{{ $item->productVariation->finish->name ?? 'N/A' }}</td>
                                                <td>{{ $item->productVariation->paint_type->name ?? 'N/A' }}</td>
                                                <td>{{ $item->color->name ?? 'N/A' }}</td>
                                                <td>{{ $item->productVariation->color_effect->name ?? 'N/A' }}</td>
                                            </tr>
                                            @endforeach
                                            @else
                                            <tr>
                                                <td colspan="13" class="text-center text-muted">No products found</td>
                                            </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-9"></div>
                            <div class="col-lg-3 col-md-6 col-12">
                                <div class="card shadow-sm" style="top: 20px; margin-bottom:40px;">
                                    <div class="card-header bg-light py-3">
                                        <h5 class="mb-0">Order Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal</span>
                                            <span>${{ number_format($order->subtotal ?? 0, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Sales Tax @if($order->tax_rate) ({{ $order->tax_rate }}%) @endif</span>
                                            <span>${{ number_format($order->tax ?? 0, 2) }}</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between mb-3">
                                            <span class="fw-bold">Total</span>
                                            <span class="fw-bold">${{ number_format($order->total ?? 0, 2) }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Payment Method</span>
                                            <span>{{ strtoupper($order->payment_method ?? '-') }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Status</span>
                                            <span class="text-capitalize">{{ $order->status ?? '-' }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Order Date</span>
                                            <span>{{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y h:i A') }}</span>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light no-print">
                                        <p class="small text-muted mb-0">
                                            <strong>Note:</strong> {{ $order->notes ?? 'No additional notes' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
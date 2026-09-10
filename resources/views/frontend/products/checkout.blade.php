@extends('frontend.layouts.app')
@push('seo')
<title>Checkout {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endpush
@push('css')
<style>
    .checkout-form label {
        font-weight: 600;
        margin-bottom: 0.25rem;
        display: block;
    }

    .checkout-form .form-control, 
    .checkout-form .form-select {
        border-radius: 0.25rem;
        border: 1px solid #ced4da;
        padding: 0.5rem 0.75rem;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .checkout-form .form-control:focus, 
    .checkout-form .form-select:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
    }

    .checkout-form .form-control.is-invalid,
    .checkout-form .form-select.is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }

    .checkout-form .form-control.is-invalid:focus,
    .checkout-form .form-select.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }

    .invalid-feedback {
        display: block;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }

    .order-summary { 
        border-radius: 0.5rem;
        padding: 1.25rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .order-summary table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1.5rem;
    }

    .order-summary th,
    .order-summary td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
        text-align: left;
    }

    .order-summary thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
        text-align: left;
    }

    .order-summary .total-row {
        font-weight: bold;
        background-color: #f8f9fa;
    }

    .product-name {
        font-weight: 600;
    }

    .product-details {
        font-size: 0.875em;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .place-order-btn {
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
        border-radius: 0.25rem;
        width: 100%;
        border: 2px solid #fd7e14;
        background-color: white;
        color: #fd7e14;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .place-order-btn:hover {
        background-color: #fd7e14;
        color: white;
    }

    .page-header {
        background-color: #f8f9fa;
        padding: 1.5rem 0;
        margin-bottom: 2rem;
    }

    .breadcrumb {
        padding: 0;
        margin: 0;
        background: transparent;
    }

    @media (max-width: 767.98px) {
        .order-summary {
            padding: 0.75rem;
        }
        .order-summary table {
            font-size: 0.97em;
        }
        .order-summary th,
        .order-summary td {
            padding: 0.5rem 0.4rem;
            text-align: left !important;
        }
        .order-summary thead th {
            text-align: left !important;
        }
        .order-summary .table-responsive {
            overflow-x: auto;
        }
        .order-summary table {
            min-width: 350px;
        }
    }

    /* Tighter vertical rhythm for form groups */
    .checkout-form .mb-3 { margin-bottom: 0.75rem !important; }
    .checkout-form .row.g-2 { --bs-gutter-x: .5rem; --bs-gutter-y: .5rem; }
    
    /* Card header styling */
    .checkout-form .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem 1.25rem;
    }
    
    .checkout-form .card-header h5 {
        margin: 0;
        color: #495057;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .checkout-form .card {
        border: 1px solid #dee2e6;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .card{
        border: 1px solid #e2b144;
    }
</style>
@endpush

@section('content')
<main class="main">
    <div class="page-header breadcrumb-wrap pb-0 mb-2">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{ url('/') }}" rel="nofollow">Home</a>
                <span></span> Checkout
            </div>
        </div>
    </div>

    <section class=" mb-5">
        <div class="container-fluid px-4">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="mb-4">Billing & Shipping</h3>
                            
                            @if ($errors->any())
                                <div class="alert alert-danger mb-4">
                                    <h6 class="alert-heading">Please fix the following errors:</h6>
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            
                            <form id="checkoutForm" method="POST" action="{{ route('checkout.process') }}" class="checkout-form">
                                @csrf
                                
                                <!-- Project Information Card -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Project Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-6 mb-3">
                                                <label for="project_name" class="form-label">Project Name *</label>
                                                <input type="text" class="form-control @error('project_name') is-invalid @enderror" id="project_name" name="project_name" 
                                                    value="{{ old('project_name', $palletAddress && $palletAddress->project_name ? $palletAddress->project_name : '') }}" required>
                                                @error('project_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="name" class="form-label">Jobsite General Contractor *</label>
                                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
                                                    value="{{ old('name', $palletAddress && $palletAddress->name ? $palletAddress->name : (Auth::user() ? Auth::user()->name : '')) }}" required>
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-6 mb-3">
                                                <label for="phone" class="form-label">Phone *</label>
                                                <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" 
                                                    value="{{ old('phone') }}" required>
                                                @error('phone')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email address *</label>
                                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" 
                                                    value="{{ old('email', Auth::user() ? Auth::user()->email : '') }}" required>
                                                @error('email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Information Card -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Address Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-md-12 mb-3">
                                                <label for="address1" class="form-label">Address *</label>
                                                <input type="text" class="form-control @error('address1') is-invalid @enderror" id="address1" name="address1" 
                                                    value="{{ old('address1', $palletAddress ? $palletAddress->address1 : '') }}" placeholder="Address" required>
                                                @error('address1')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-4 mb-3">
                                                <label for="city" class="form-label">City *</label>
                                                <input type="text" class="form-control @error('city') is-invalid @enderror" id="city" name="city" 
                                                    value="{{ old('city', $palletAddress ? $palletAddress->city : '') }}" required>
                                                @error('city')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="state" class="form-label">State *</label>
                                                <select class="form-control @error('state') is-invalid @enderror" id="state" name="state" required>
                                                    <option value="">Select State</option>
                                                    @foreach($states as $state)
                                                        <option value="{{ $state->state }}" 
                                                            {{ old('state', $palletAddress && $palletAddress->state && $palletAddress->state->state == $state->state ? $state->state : '') == $state->state ? 'selected' : '' }}>
                                                            {{ $state->state }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('state')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="postcode" class="form-label">Postcode / ZIP *</label>
                                                <input type="text" class="form-control @error('postcode') is-invalid @enderror" id="postcode" name="postcode" 
                                                    value="{{ old('postcode', $palletAddress ? $palletAddress->postcode : '') }}" required>
                                                @error('postcode')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Notes Card -->
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Order Notes</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-12 mb-3">
                                                <label for="notes" class="form-label">Order notes (optional)</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Notes about your order, e.g. special notes for delivery."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body order-summary">
                            <div class="table-responsive">
                            <h3 class="mb-4">Your order</h3>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $subtotal = 0;
                                    @endphp
                                    
                                    @foreach($palletItems as $item)
                                        @php
                                            $product = $item->productVariation->product;
                                            $variation = $item->productVariation;
                                            $quantity = $pallet[$item->id]['quantity'];
                                            $price = $variation->pricing;
                                            $itemTotal = $quantity * $price;
                                            $subtotal += $itemTotal;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="product-name">{{ $product->name }}</div>
                                                <div class="product-details">
                                                    Size: {{ $variation->size->name ?? 'N/A' }}<br>
                                                    Color: {{ $item->color->name ?? 'N/A' }}<br>
                                                    {{ $quantity }} X ${{ number_format($price, 2) }}
                                                </div>
                                            </td>
                                            <td class="text-end">$ {{ number_format($itemTotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    
                                    <tr class="total-row">
                                        <td><strong>Subtotal</strong></td>
                                        <td class="text-end">$ {{ number_format($subtotal, 2) }}</td>
                                    </tr>
                                    <tr class="total-row">
                                        <td><strong>Shipping</strong></td>
                                        <td class="text-end" id="shipping-amount">$0.00</td>
                                    </tr>
                                    <tr class="total-row">
                                        <td><strong>Sales Tax <span id="tax-rate-label"></span></strong></td>
                                        <td class="text-end" id="tax-amount">$0.00</td>
                                    </tr>
                                    <tr class="total-row">
                                        <td><strong>Total</strong></td>
                                            <td class="text-end" id="total-amount">$ {{ number_format($subtotal, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>

                            <div class="payment-method mb-4">
                                <h5 class="mb-3">Payment Method</h5>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" checked>
                                    <label class="form-check-label" for="cod">
                                        Cash on delivery
                                    </label>
                                </div>
                                <p class="mt-2 small text-muted">Pay with cash upon delivery.</p>
                            </div>

                            <p class="small text-muted mb-4">
                                Your personal data will be used to process your order, support your experience
                                throughout this website, and for other purposes described in our
                                <a href="#">privacy policy</a>.
                            </p>

                            @canDo('procurement', 'S')
                            <button type="submit" form="checkoutForm" class="btn place-order-btn">PLACE ORDER</button>
                            @else
                            <button type="button" class="btn place-order-btn" disabled title="You do not have permission to place orders">PLACE ORDER</button>
                            <p class="small text-danger mt-2 mb-0"><i class="ti ti-shield-x me-1"></i>Your role does not have permission to place orders. Contact your organization owner.</p>
                            @endCanDo
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script id="state-tax-map" type="application/json">{!! json_encode(\App\Models\StateTax::pluck('combined_tax_rate', 'state')) !!}</script>
<script id="order-subtotal" type="application/json">{!! json_encode($subtotal ?? 0) !!}</script>
<script>
    // Parse tax rates to avoid linter errors from inline Blade in JS
    let stateTaxRates = {};
    try { stateTaxRates = JSON.parse(document.getElementById('state-tax-map').textContent || '{}'); } catch(e) { stateTaxRates = {}; }
    let subtotal = 0;
    try { subtotal = JSON.parse(document.getElementById('order-subtotal').textContent || '0') || 0; } catch(e) { subtotal = 0; }

    function updateTaxAndTotal() {
        const state = document.getElementById('state').value;
        const taxRate = stateTaxRates[state] ? parseFloat(stateTaxRates[state]) : 0;
        const shipping = 0; // placeholder for future estimated shipping calculation
        const tax = (subtotal + shipping) * (taxRate / 100);
        const total = subtotal + shipping + tax;
        document.getElementById('tax-rate-label').textContent = taxRate ? '(' + taxRate + '%)' : '';
        document.getElementById('shipping-amount').textContent = '$' + shipping.toFixed(2);
        document.getElementById('tax-amount').textContent = '$' + tax.toFixed(2);
        document.getElementById('total-amount').textContent = '$' + total.toFixed(2);
    }

    document.getElementById('state').addEventListener('change', updateTaxAndTotal);
    document.addEventListener('DOMContentLoaded', updateTaxAndTotal);
</script>
@endpush
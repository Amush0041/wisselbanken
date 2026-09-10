@extends('frontend.layouts.app')

@push('seo')
    <title>Order Confirmation - {{ env('APP_NAME') }}</title>
@endpush

@section('content')
<main class="main">
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{ url('/') }}" rel="nofollow">Home</a>
                <span></span> Order Confirmation
            </div>
        </div>
    </div>

    <section class="mt-50 mb-50">
        <div class="container-fluid px-4">
            <div class="row">
                <div class="col-lg-10 m-auto">
                    <div class="card">
                        <div class="card-body text-center">
                            <span class="display-1 text-success">
                                <i class="fas fa-check-circle"></i>
                            </span>
                            <h1 class="display-3 mb-4 pt-4">Thank You!</h1>
                            <p class="lead mb-5">Your order <strong>{{ $order }}</strong> has been placed successfully.</p>
                            
                            <!-- <div class="order-details mb-5">
                                <h4 class="mb-4">What's Next?</h4>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <i class="fas fa-envelope fa-3x text-primary mb-3"></i>
                                                <h5>Order Confirmation</h5>
                                                <p>You'll receive an email confirmation with your order details shortly.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <i class="fas fa-truck fa-3x text-info mb-3"></i>
                                                <h5>Shipping Updates</h5>
                                                <p>We'll notify you when your order ships and provide tracking information.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> -->

                            <div class="actions pt-4">
                                <a href="{{ route('product-filter') }}" class="btn btn-primary btn-lg mr-3">
                                    <i class="fas fa-home mr-2"></i> Continue Shopping
                                </a>
                                <a href="{{ route('view.orders') }}" class="btn btn-primary btn-lg">
                                    <i class="fas fa-list-alt mr-2"></i> View Your Orders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    // Track order conversion in analytics
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'conversion', {
                'send_to': 'AW-123456789/AbC-D_efG-h12',
                'value': 1.0,
                'currency': 'USD',
                'transaction_id': '{{ $order }}'
            });
        }
    });
</script>
@endpush
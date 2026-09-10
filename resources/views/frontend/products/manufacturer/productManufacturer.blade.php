@extends('frontend.layouts.app')

@push('seo')
<title> Product Manufacturer | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endpush

@push('css')
<style>
    .manufacturer-grid {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 10px;
    }
    .manufacturer-card {
        flex: 1 1 calc(12.5% - 10px);
        max-width: calc(12.5% - 10px);
        margin: 5px;
        min-width: 120px;
    }
    @media (max-width: 1200px) {
        .manufacturer-card {
            flex: 1 1 calc(25% - 10px);
            max-width: calc(25% - 10px);
        }
    }
    @media (max-width: 768px) {
        .manufacturer-card {
            flex: 1 1 calc(50% - 10px);
            max-width: calc(50% - 10px);
        }
    }
    @media (max-width: 480px) {
        .manufacturer-card {
            flex: 1 1 calc(100% - 10px);
            max-width: calc(100% - 10px);
        }
    }
    .card-title, .text-muted {
        writing-mode: initial !important;
        text-align: center;
        white-space: normal;
    }
</style>
@endpush

@section('content')
<main class="main" style="background-color: #f5f5f5;">
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{ url('/') }}" rel="nofollow">Home</a>
                <span></span> Product Manufacturer
            </div>
        </div>
    </div>

    <section class="mt-30 mb-50">
        <div class="container-fluid px-4 pb-4">
            <div class="d-flex flex-wrap justify-content-start manufacturer-grid">
                @if(count($manufacturerProducts) > 0) 
                @foreach($manufacturerProducts as $manufacturer)
                <div class="manufacturer-card">
                    <div class="card text-center border-0 shadow-sm p-2 rounded">
                        <a href="{{ url('product-filter?filter='.$divisionCode. '-' . $specificationNumber.'-'.$manufacturer->id) }}">
                            <img src="{{ asset('storage/'.$manufacturer->image) }}"
                                class="card-img-top mx-auto"
                                onerror="this.onerror=null; this.src='{{ asset('demo.jpg') }}';"
                                alt="{{ $manufacturer->name ?? '' }}"
                                style="width: 70px; height: 70px;">
                        </a>
                        <div class="card-body p-2">
                            <h6 class="card-title mb-1">
                                <a href="{{ url('product-filter?filter='.$divisionCode. '-' . $specificationNumber.'-'.$manufacturer->id) }}"
                                    class="text-primary font-weight-bold small">
                                    {{ $manufacturer->name ?? '' }}
                                </a>
                            </h6>
                            <p class="text-muted small">{{ number_format($manufacturer->product_count) }} Items</p>
                        </div>
                    </div>
                </div>
                @endforeach
                @else
                <div class="w-100 text-center">
                    <h4>No Products Found</h4>
                    <a href="{{ url('product-filter') }}" class="btn btn-primary mt-3">See Other Products</a>
                </div>
                @endif
            </div>
        </div>
    </section>
</main>
@endsection
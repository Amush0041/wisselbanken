@extends('frontend.layouts.app')@push('seo')
<title> {{$manufacturer->name}} | {{env('APP_NAME','Wisselbanken')}}</title>
@endpush
@push('css')
<style>
    .old-price {
        text-decoration: none !important;
    }

    .product-cart-wrap .product-content-wrap h2 {
        font-size: 14px;
        font-weight: 500;
    }

    .manufacturer-product-card {
        flex: 1 1 calc(16.666% - 20px);
        max-width: calc(16.666% - 20px);
        min-width: 120px;
        margin: 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: box-shadow 0.2s;
    }

    .manufacturer-product-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.10);
    }

    .manufacturer-product-card .card-img-top {
        width: 100px;
        height: 100px;
        object-fit: contain;
        margin: 0 auto 2px auto;
        display: block;
    }

    .manufacturer-product-card .card-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: center;
        width: 100%;
        padding: 0.2rem 0.5rem 0 0.5rem;
    }

    .manufacturer-product-card .card-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        min-height: 1.5em;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .manufacturer-product-card .text-muted {
        font-size: 0.85rem;
        margin-bottom: 0.25rem;
    }

    .manufacturer-products-row {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
    }

    @media (max-width: 1200px) {
        .manufacturer-product-card {
            flex: 1 1 calc(33.333% - 20px);
            max-width: calc(33.333% - 20px);
        }
    }

    @media (max-width: 768px) {
        .manufacturer-products-row { justify-content: center; }
        .manufacturer-product-card {
            flex: 1 1 calc(50% - 20px);
            max-width: calc(50% - 20px);
        }
    }

    @media (max-width: 576px) {
        .manufacturer-product-card {
            flex: 1 1 calc(100% - 20px);
            max-width: calc(100% - 20px);
        }
    }

    .pagination-area {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
    }
</style>
@endpush
@section('content')
<main class="main">
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{url('/')}}" rel="nofollow">Home</a>
                <span></span> Manufacturer
                <span></span> {{$manufacturer->name ?? ''}}
            </div>
        </div>
    </div>

    <section class="mt-30 mb-50">
        <div class="container-fluid px-5 pb-4">
            <div class="manufacturer-products-row" style="margin: 0 -10px;">
                @if(count($products) > 0)
                @foreach($products as $product)
                <div class="manufacturer-product-card">
                    <a href="{{url('product-detail/'.$product->slug)}}" class="pt-2">
                        <img class="card-img-top mx-auto" src="{{asset($product->feature_image)}}" onerror="this.onerror=null; this.src='{{ asset('demo.jpg') }}';" alt="{{$product->name ?? ''}}">
                        </a>
                        <div class="card-body p-2">
                            <h6 class="card-title mb-1">
                                <a href="{{url('product-detail/'.$product->slug)}}"
                                    class="text-primary font-weight-bold small">
                                    {{ $product->name ?? '' }}
                                </a>
                            </h6>
                            <p class="text-muted small">{{ $product->manufacturer->name }}</p>
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
        <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
            {{$products->links()}}
        </div>
    </section>
</main>
@endsection
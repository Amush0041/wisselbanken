@extends('frontend.layouts.app')@push('seo')
<title> {{$division->code ?? ''}} - {{$division->name ?? ''}} | {{env('APP_NAME','Wisselbanken')}}</title>
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

    .division-product-card {
        flex: 1 1 calc(16.666% - 20px);
        max-width: calc(16.666% - 20px);
        min-width: 120px;
        margin: 10px;
    }

    @media (max-width: 1200px) {
        .division-product-card {
            flex: 1 1 calc(33.333% - 20px);
            max-width: calc(33.333% - 20px);
        }
    }

    @media (max-width: 768px) {
        .division-product-card {
            flex: 1 1 calc(50% - 20px);
            max-width: calc(50% - 20px);
        }
    }

    @media (max-width: 576px) {
        .division-product-card {
            flex: 1 1 calc(100% - 20px);
            max-width: calc(100% - 20px);
        }
    }

    .pagination-area {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        margin-top: 20px;
    }
</style>
@endpush
@section('content')
<main class="main">
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{url('/')}}" rel="nofollow">Home</a>
                <span></span> Division
                <span></span> {{$division->name ?? ''}}
            </div>
        </div>
    </div>
    <section class="mt-50 mb-50">
        <div class="container-fluid px-5 pb-4">
            <div class="d-flex flex-wrap justify-content-start" style="margin: 0 -10px;">
                @if(count($products) > 0)
                @foreach($products as $product)
                <div class="division-product-card">
                    <div class="card text-center border-0 shadow-sm p-2 rounded">
                        <a href="{{url('product-detail/'.$product->slug)}}">
                            <img class="card-img-top mx-auto" src="{{asset($product->feature_image)}}" onerror="this.onerror=null; this.src='{{ asset('demo.jpg') }}';" alt="{{$product->name ?? ''}}"
                                style="max-width: 80px; height: auto;">
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
                </div>
                @endforeach
                @else
                <div class="w-100 text-center">
                    <h4>No Products Found</h4>
                    <a href="{{ url('product-filter') }}" class="btn btn-primary mt-3">See Other Products</a>
                </div>
                @endif
            </div>

            @if(count($products) > 0)
            <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
                {{$products->links()}}
            </div>

            @endif
        </div>
    </section>
</main>
@endsection
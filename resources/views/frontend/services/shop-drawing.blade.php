@extends('frontend.layouts.app')
@push('seo')
<title> Shop Drawings | Plan Review | Design | {{env('APP_NAME','Wisselbanken')}}</title>
<meta name="description" content="">
@endpush
@push('css')
<style>
    .bg-danger {
        background-color: #ff8899 !important;
    }
    .service-img {
        border-radius: 12px;
        height: 350px;
        width: 1200px;
        object-fit: cover;
    }
    .text-color{
    color: #4A171E;
}
    blockquote:before {
        display: block !important;
        /* left: 10px; */
        /* top: 0; */
        content: "“";
        font-size: 80px;
        font-style: normal;
        /* line-height: 1; */
        color: #e3b143 !important;
        position: relative;
        right: 27%;
    }

    blockquote:after {
        color: #e3b143 !important;
        display: block !important;
        font-size: 80px;
        font-style: normal;
        /* line-height: 1; */
        position: relative;
        /* bottom: -0.5em; */
        /* bottom: -0.5em; */
        left: 27%;
        content: "”";
    }

    blockquote .line-height-7 {
        line-height: 1.7 !important;
    }

    blockquote .text-5 {
        font-size: 1.50em !important;
    }
</style>
@endpush
@section('content')
<main class="main single-page">
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{url('/')}}" rel="nofollow">Home</a>
                <span></span> Services
                <span></span> Shop Drawings / Plan Review / Design 
            </div>
        </div>
    </div>
    <section class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row">
                <div class="col-lg-8 align-self-center mb-lg-0 mb-4">
                    <h6 class="mt-0 mb-15 text-uppercase font-sm text-brand wow fadeIn animated">Our Services</h6>
                    <h1 class="font-heading mb-20">Shop Drawings / Plan Review / Design </h1>
                    <p class="font-weight-medium text-4">An integral part of making an Architects basis of design come to fruition is the bridge between design and installation. Shop drawings, the ways of means of installation, allows verification that the basis of design can be installed in the first place, and secondarily if there are alternative methods those can be discussed. Our network of engineers can discuss any shortcomings or suggest industry standards that may have been overlooked in the design bid process.</p>
                    <p class="text-3-5 line-height-9 mb-5">This service can be as detailed as to where every fastener gets placed in the system, how each caulk joint gets treated, and architectural layouts achieved. We are licensed in most jurisdictions and are familiar with the codes necessary to achieve the right fit of products to the job.</p>
                    <p class="text-3-5 line-height-9 mb-5">Most exterior siding companies both starting out and seasoned, don’t have their own engineering department, let us help your team out with our expertise.</p>

                </div>
                <div class="col-lg-4 mt-4">
                    <img src="{{asset('frontend/services/shop-drawing.webp')}}" class="service-img" alt="shop-drawing">
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row mb-50">
                <div class="col-lg-12 col-md-12 text-center">
                    <blockquote>
                        <p class="w-50 m-auto text-grey-3 text-5 line-height-7 wow fadeIn animated">This is a highly specialized service that our network allows us to offer,</p>

                        <h4 class="mb-15 text-grey-1 wow fadeIn animated animated mt-4 text-color">please contact Mitch@wisselbanken.com or call 651-392-9405.</h4>
                    </blockquote>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
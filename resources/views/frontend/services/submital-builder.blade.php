@extends('frontend.layouts.app')
@push('seo')
<title> Turnkey Construction Partnerships | {{env('APP_NAME','Wisselbanken')}}</title>
<meta name="description" content="">
@endpush
@push('css')
<style>
    .bg-danger {
        background-color: #ff8899 !important;
    }

    .text-color {
        color: #4A171E;
    }

    .service-img {
        border-radius: 12px;
        height: 350px;
        width: 1200px;
        object-fit: cover;
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
                <span></span> Submittal Builder
            </div>
        </div>
    </div>
    <section class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row">
                <div class="col-lg-6 align-self-center mb-lg-0 mb-4">
                    <h6 class="mt-0 mb-15 text-uppercase font-sm text-brand wow fadeIn animated">Our Services</h6>
                    <h1 class="font-heading mb-20">Submittal Builder</h1>
                    <p class="font-weight-medium text-4">Let’s get everyone on the same page! From supplier to installer, General Contractor to architect, we want to make sure there is no guesswork in the products chosen and the exact variants are agreed upon.</p>
                    <p class="text-3-5 line-height-9 mb-5">We have set up a system that has a vast library of construction materials and their corresponding SDS Sheets, Spec Sheets, Brochures, Color Options and any other important documentation that is required during the submittal process.</p>
                    <p class="text-3-5 line-height-9 mb-5">Just add all of the job stakeholder’s information and we will produce the all-inclusive document with your branding included. Our database is a constantly evolving library as new products are coming into the marketplace or lesser-known products rise into the design meta.</p>

                </div>
                <div class="col-lg-6 mt-4">
                    <img src="{{asset('frontend/services/submital-builder.webp')}}" class="service-img" alt="submital-builder">
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row mb-50">
                <div class="col-lg-12 col-md-12 text-center">
                    <blockquote>
                        <p class="w-50 m-auto text-grey-3 text-5 line-height-7 wow fadeIn animated">Let us walk you through this process and show you how easy it is</p>

                        <h4 class="mb-15 text-grey-1 wow fadeIn animated animated mt-4 text-color">please contact Mitch@wisselbanken.com or call 651-392-9405</h4>
                    </blockquote>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
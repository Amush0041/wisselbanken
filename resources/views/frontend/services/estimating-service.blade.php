@extends('frontend.layouts.app')
@push('seo')
<title> Take off & Estimating Services | {{env('APP_NAME','Wisselbanken')}}</title>
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
                <span></span>Take off & Estimating Services
            </div>
        </div>
    </div>
    <section class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row">
                <div class="col-lg-6 align-self-center mb-lg-0 mb-4">
                    <h6 class="mt-0 mb-15 text-uppercase font-sm text-brand wow fadeIn animated">Our Services</h6>
                    <h1 class="font-heading mb-20">Take off & Estimating Services</h1>
                    <p class="font-weight-medium text-4">Wisselbanken specializes in exterior siding takeoffs and estimating of materials that are related to the exterior envelope. As we expand these estimating services, we hope to help other construction divisions as well. We provide accurate and detailed takeoffs for a variety of exterior siding materials, including vinyl, fiber cement, wood, and aluminum. <span class="bg-danger">This service can be used to “check” your estimating department, or to order materials from.</span></p>

                    <p class="text-3-5 line-height-9 mb-5">Wisselbanken has a team of experienced estimators who are skilled in conducting in depth analysis of plans, measuring and quantifying materials, and accurately estimating the cost of the project. The company uses state-of-the-art software and tools to ensure that their estimates are accurate and reliable, and they are able to provide quick turnaround times for their services.</p>

                </div>
                <div class="col-lg-6 mt-4">
                    <img src="{{asset('frontend/services/take-off-estimated.webp')}}" class="service-img" alt="Take off & Estimating Services">
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row mb-50">
                <div class="col-lg-12 col-md-12 text-center">
                    <blockquote>
                        <p class="w-50 m-auto text-grey-3 text-5 line-height-7 wow fadeIn animated">From the beginning of your project, and throughout it’s duration, we would be honored to help you with your project.</p>

                        <h4 class="mb-15 text-grey-1 wow fadeIn animated animated mt-4 text-color">Contact Mitch@Wisselbanken.com or call 651-392-9405.</h4>
                    </blockquote>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
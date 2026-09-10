@extends('frontend.layouts.app')
@push('seo')
<title> Material Quotes | {{env('APP_NAME','Wisselbanken')}}</title>
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
                <span></span>Material Quotes
            </div>
        </div>
    </div>
    <section class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row">
                <div class="col-lg-6 align-self-center mb-lg-0 mb-4">
                    <h6 class="mt-0 mb-15 text-uppercase font-sm text-brand wow fadeIn animated">Our Services</h6>
                    <h1 class="font-heading mb-20">Material Quotes</h1>
                    <p class="font-weight-medium text-4">From the estimating stage to the material procurement process, we can bridge the gap and work with your estimating department to put together and order the most comprehensive material list that is tailored to your specific project.</p>
                    <p class="text-3-5 line-height-9 mb-5">We don’t cut corners, and understand the manufacturer’s specifications so that you have everything needed to fully complete your project without delays.</p>
                    <p class="text-3-5 line-height-9 mb-5">We are experts in this field and value discussions with our clients and other exterior envelope specialist to further the industry. The end game of our service is to build better buildings, reduce stress level and burnout from estimating departments, and ultimately have a more cohesive industry in general. We have the technology in place to reduce scope gaps, and protect from project overrun, while simultaneously reducing excess materials that end up in a landfill.</p>

                </div>
                <div class="col-lg-6 mt-4">
                    <img src="{{asset('frontend/services/material-quote.webp')}}" class="service-img" alt="material-quote">
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row mb-50">
                <div class="col-lg-12 col-md-12 text-center">
                    <blockquote>
                        <p class="w-50 m-auto text-grey-3 text-5 line-height-7 wow fadeIn animated">Let us help you dial in your material ordering processes, if you are ready to partner with Wisselbanken</p>

                        <h4 class="mb-15 text-grey-1 wow fadeIn animated animated mt-4 text-color">Contact Mitch@Wisselbanken.com or call 651-392-9405.</h4>
                    </blockquote>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
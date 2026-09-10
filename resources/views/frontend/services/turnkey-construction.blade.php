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
.text-color{
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
                <span></span> Turnkey Construction Partnerships
            </div>
        </div>
    </div>
    <section class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row">
                <div class="col-lg-6 align-self-center mb-lg-0 mb-4">
                    <h6 class="mt-0 mb-15 text-uppercase font-sm text-brand wow fadeIn animated">Our Services</h6>
                    <h1 class="font-heading mb-20">Turnkey Construction Partnerships</h1>
                    <p class="font-weight-medium text-4">Wisselbanken ownership comes from an installation background, and has maintained it’s valuable relationships with installers, manufacturers, and suppliers alike. We can help developers, General Contractors, owners, and architect put a qualified siding team together that makes their project happen.</p>
                    <p class="text-3-5 line-height-9 mb-5">We verify the quality of installers gauged by their reputation in the industry and align them with your project. Treat us as an extension of your network and allow us to connect the dots to a successful job completed on time and on budget.</p>
                    <p class="text-3-5 line-height-9 mb-5">Our network includes fiber cement installers, metal panel installers, and other specialty installers that are prequalified to fit your project</p>

                </div>
                <div class="col-lg-6 mt-4">
                    <img src="{{asset('frontend/services/turnkey.webp')}}"  class="service-img" alt="Take off & Estimating Services">
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="section-padding">
        <div class="container-fluid px-4 pt-25">
            <div class="row mb-50">
                <div class="col-lg-12 col-md-12 text-center">
                    <blockquote>
                        <p class="w-50 m-auto text-grey-3 text-5 line-height-7 wow fadeIn animated">Let us know what products are on your project and we can help design, put a material list together, ship your materials, and connect you with the right installer. Complete turnkey!</p>

                        <h4 class="mb-15 text-grey-1 wow fadeIn animated animated mt-4 text-color">Contact Mitch@Wisselbanken.com or call 651-392-9405.</h4>
                    </blockquote>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
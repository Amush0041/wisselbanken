<footer class="main">
    <section class="newsletter p-30 text-white wow fadeIn animated">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-lg-7 mb-md-3 mb-lg-0">
                    <div class="row align-items-center">
                        <div class="col flex-horizontal-center">
                            <!-- <img class="icon-email" src="{{asset('frontend/assets/imgs/theme/icons/icon-email.svg')}}" alt=""> -->
                            <i class="fi-rs-envelope" style="font-size: 25px;padding-right: 6px;"></i>
                            <h4 class="font-size-15 mb-0 ml-4 text-white"> Sign up to Newsletter</h4>
                        </div>
                        <div class="col my-4 my-md-0 des">
                            <h5 class="font-size-15 ml-4 mb-0  text-white">...for getting latest update about product</h5>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <!-- Subscribe Form -->
                    <form class="form-subcriber d-flex wow fadeIn animated">
                        <input type="email" class="form-control bg-white font-small" placeholder="Enter your email">
                        <button class="btn bg-dark text-white" type="submit">Subscribe</button>
                    </form>
                    <!-- End Subscribe Form -->
                </div>
            </div>
        </div>
    </section>
    <section class="section-padding footer-mid">
        <div class="container-fluid px-4 pt-15 pb-20">
            <div class="row">
                <div class="col-lg-3 col-md-3">
                    <div class="widget-about font-md mb-md-5 mb-lg-0">
                        <div class="logo logo-width-1 wow fadeIn animated">
                            <a href="index.html"><img src="{{asset('logo.png')}}" alt="{{env('APP_NAME','Wisselbanken')}}"></a>
                        </div>
                        <h5 class="mt-20 mb-10 fw-600 text-grey-4 wow fadeIn animated">Contact</h5>
                        <p class="wow fadeIn animated">
                            <strong>Address: </strong>562 Wellington Road, Street 32, San Francisco
                        </p>
                        <p class="wow fadeIn animated">
                            <strong>Phone: </strong>651-392-9405
                        </p>
                        <p class="wow fadeIn animated">
                            <strong>Email: </strong>Mitch@wisselbanken.com
                        </p>
                        <h5 class="mb-10 mt-30 fw-600 text-grey-4 wow fadeIn animated">Follow Us</h5>
                        <div class="mobile-social-icon wow fadeIn animated mb-sm-5 mb-md-0">
                            <a href="https://www.facebook.com/wisselbanken/"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-facebook.svg')}}" alt=""></a> 
                            <a href="https://www.instagram.com/wisselbanken/"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-instagram.svg')}}" alt=""></a> 
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-3">
                    <h5 class="widget-title wow fadeIn animated">About</h5>
                    <ul class="footer-list wow fadeIn animated mb-sm-5 mb-md-0">
                        <li><a href="{{url('/')}}">Home</a></li>
                        <li><a href="{{url('product-filter')}}">Products</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms &amp; Conditions</a></li>
                        <li><a href="{{url('contacts')}}">Contact Us</a></li> 
                    </ul>
                </div>
                <div class="col-lg-3  col-md-3">
                    <h5 class="widget-title wow fadeIn animated">Services</h5>
                    <ul class="footer-list wow fadeIn animated">
                        <li><a href="{{route('estimating-service')}}">Take off & Estimating Services</a></li>
                        <li><a href="{{route('material-quote-service')}}">Material Quotes</a></li>
                        <li><a href="{{route('turnkey-construction-service')}}">Turnkey Construction Partnerships</a></li>
                        <li><a href="{{route('shop-drawing-service')}}">Shop Drawings / Plan Review / Design</a></li>
                        <li><a href="{{route('submital-builder-service')}}">Submittal Builder</a></li>
                    </ul>
                </div> 
                <div class="col-lg-3  col-md-3">
                    <h5 class="widget-title wow fadeIn animated">Divisions</h5>
                    <ul class="footer-list wow fadeIn animated">
                        @foreach(\App\Helper\Helper::getDivisions() as $key => $division)
                        <li><a href="{{route('product-division',$division->slug)}}">{{$division->name}}</a></li>
                        @if($key == 5)
                        @php break; @endphp
                        @endif
                        @endforeach
                    </ul>
                </div> 
            </div>
        </div>
    </section>
    <div class="container-fluid px-4 pb-20 wow fadeIn animated">
        <div class="row">
            <div class="col-12 mb-20">
                <div class="footer-bottom"></div>
            </div>
            <div class="col-lg-12">
                <p class="float-md-left font-sm text-muted mb-0">All rights of &copy; {{date('Y')}} reserved by, <strong class="text-brand">{{env('APP_NAME','Wisselbanken')}}</strong></p>
            </div> 
        </div>
    </div>
</footer>
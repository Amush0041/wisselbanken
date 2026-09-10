@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                    @endif

                    {{ __('You are logged in!') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('frontend.masterlayouts.app')
@section('content')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

<style>
    .product-title {
        font-weight: bold;
        color: #343a40;
        text-align: left;
    }

    .text-muted {
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .hm-product-detail table th,
    .hm-product-detail table td {
        border: none;
    }

    .hm-product-detail .table th,
    .hm-product-detail .table td {
        font-size: 14px !important;
        padding: 10px !important;
        border: none;
        border-bottom: 1px solid #cccccc;
    }

    .basic-details table th,
    .basic-details table td {
        border: none !important;
        padding: 14px !important;
    }

    .basic-details table th {
        width: 50%;
        color: #414141;
        text-align: right;
    }

    .product-attributes table th {
        text-align: right;
        color: #6c757d;
    }

    .product-attributes table th,
    .product-attributes table td {
        border: none !important;
        border-bottom: 1px solid #cccccc !important;
    }


    .btn-link {
        padding: 0;
        text-decoration: underline;
    }

    .card {
        background-color: #f6f5f5;
        border: none;
        box-shadow: 0px 4px 16px 0px rgba(0, 0, 0, 0.305);
        border-radius: 0;
        padding: 10px;
        margin-bottom: 20px;
    }

    .pricing-table {
        max-width: 600px;
    }

    /* carousel */
    .media-carousel {
        margin-bottom: 0;
        padding: 10px 30px 30px 30px;
    }

    /* Previous button  */
    .media-carousel .carousel-control.left {
        left: -12px;
        background-image: none;
        background: none repeat scroll 0 0 #222222;
        border: 4px solid #f6f5f5;
        border-radius: 23px 23px 23px 23px;
        height: 40px;
        width: 40px;
        margin-top: 30px
    }

    /* Next button  */
    .media-carousel .carousel-control.right {
        right: -12px !important;
        background-image: none;
        background: none repeat scroll 0 0 #222222;
        border: 4px solid #f6f5f5;
        border-radius: 23px 23px 23px 23px;
        height: 40px;
        width: 40px;
        margin-top: 30px
    }

    /* Changes the position of the indicators */
    .media-carousel .carousel-indicators {
        right: 50%;
        top: auto;
        bottom: 0px;
        margin-right: -19px;
    }

    /* Changes the colour of the indicators */
    .media-carousel .carousel-indicators li {
        background: #c0c0c0;
    }

    .media-carousel .carousel-indicators .active {
        background: #333333;
    }

    .media-carousel img {
        width: 250px;
        height: 100px
    }

    .quantity-feild {
        border-radius: 8px !important;
        width: 200px;
        padding: 18px !important;
        border: 1px solid #000;
        font-size: 14px !important;
    }

    .quantity-feild:focus {
        box-shadow: none;
        border: 1px solid #000
    }

    .report-btn,
    .add-list-btn {
        background-color: #f6f5f5;
        border: 1px solid #c1c1c1;
        padding: 10px 20px;
    }

    .report-btn:hover,
    .add-list-btn:hover {
        background-color: #e6e6e6;
        border: 1px solid #c1c1c1;
        padding: 10px 20px;
    }

    .view-similar-btn,
    .add-cart-btn {
        background-color: rgb(255, 2, 2);
        color: white;
        border-radius: 25px !important;
        border: 1px solid #e70202;
        padding: 10px 20px;
    }

    .view-similar-btn:hover,
    .add-cart-btn:hover {
        border: 1px solid #e70202;
        background-color: #e70202;
        color: white
    }
</style>

<div class="hm-product-detail container-lg my-5">
    <div class="mt-5">
        <div class="row">
            <div class="col-md-7">
                <div class="card basic-details">
                    <div class="row">
                        <!-- Header -->
                        <div class="col-12">
                            <h4 class="product-title text-center">ECS-50-20-5PX-TR</h4>
                        </div>
                    </div>
                    <div class="row">
                        <!-- Image Section -->
                        <div class="col-md-4 text-center">
                            <img src="{{ asset('storage/'.$product->feature_image) }}" alt="Product Image" class="img-fluid">
                            <p class="mt-2 text-muted">
                                Image shown is a representation only. Exact specifications should be obtained from the
                                product
                                data
                                sheet.
                            </p>
                        </div>
                        <!-- Details Section -->
                        <div class="col-md-8">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th>Digikey Part Number</th>
                                        <td>{{ $product->specification->specification_no  }}-{{ $product->specification->material_type  }}</td>
                                    </tr>
                                    <tr>
                                        <th>Manufacturer</th>
                                        <td>{{ $product->manufacture->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Manufacturer Product Number</th>
                                        <td>ECS-50-20-5PX-TR</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <h4 class="fw-bold">Product Attributes</h4>
                <div class="card product-attributes">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width: 20%;">TYPE</th>
                                    <th scope="col" style="width: 60%;">DESCRIPTION</th>
                                    <th scope="col" style="width: 20%;">
                                        <div class="d-flex justify-content-between">
                                            SELECT ALL <input type="checkbox">
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Category</th>
                                    <td>
                                        <a href="#" class="text-primary">Crystals, Oscillators, Resonators</a><br>
                                        <a href="#" class="text-primary">Crystals</a>
                                    </td>
                                    <td class="text-end">
                                        <input type="radio" name="select"><br>
                                        <input type="radio" name="select">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Mfr</th>
                                    <td><a href="#" class="text-primary">ECS Inc.</a></td>
                                    <td class="text-end"><input type="checkbox" name="select"></td>
                                </tr>
                                <tr>
                                    <th>Series</th>
                                    <td>CSM-7X</td>
                                    <td class="text-end"><input type="checkbox" name="select"></td>
                                </tr>
                                <tr>
                                    <th>Packaging</th>
                                    <td>
                                        Tape & Reel (TR)<br>
                                        Cut Tape (CT)<br>
                                        Digi-Reel®
                                    </td>
                                    <td class="text-end">
                                        <input type="checkbox" name="select"><br>
                                        <input type="checkbox" name="select"><br>
                                        <input type="checkbox" name="select">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between mt-3 py-3">
                        <button class="report-btn btn-rounded py-3">Report Product Information Error</button>
                        <button class="view-similar-btn btn-rounded py-3">View Similar</button>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card pricing-table">
                    <h5 class="text-start">In-Stock: <span class="fw-bold">41,848</span></h5>
                    <p class="text-start">Can ship immediately</p>
                    <div class="d-flex flex-column gap-3 mb-3">
                        <input type="number" class="form-control quantity-feild" placeholder="Quantity">
                        <div class="w-100 d-flex gap-3">
                            <button class="add-list-btn btn-rounded w-50 py-3">Add to List</button>
                            <button class="add-cart-btn btn-rounded w-50 py-3">Add to Cart</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mb-3">All prices are in USD</small>

                    <h6>Cut Tape (CT) & Digi-Reel®</h6>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>QUANTITY</th>
                                <th>UNIT PRICE</th>
                                <th>EXT PRICE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>{{ $product->unit_price }}</td>
                                <td>{{ $product->extended_price }}</td>
                            </tr>


                        </tbody>
                    </table>
                    <p class="text-muted"><small>* All Digi-Reel orders will add a $7.00 reeling fee.</small></p>

                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <h5 class="fw-bold">You May Also Be Interested In <a href="#">See all</a></h5>
        </div>
        <div class='row hidden-xs hidden-sm'>
            <div class='col-md-12'>
                <div class="carousel slide media-carousel" id="media">
                    <div class="carousel-inner overflow-visible">
                        <div class="item active">
                            <div class="row">
                                @foreach ($products->take(3) as $data)
                                <div class="col-md-4 p-3">
                                    <div class="row d-flex justify-content-center p-3"
                                        style="border: 1px solid #ccc; border-radius: 8px;">
                                        <div class="col-md-3">
                                            <img src="{{ asset('storage/'.$data->feature_image) }}" alt="Trimmer"
                                                style="width: 80px; height:  90px; display: block; margin: 0 auto;">
                                        </div>
                                        <div class="col-md-7">
                                            <h3 style="font-size: 16px; color: #0000EE; margin: 8px 0;">{{ $data->finish }}</h3>
                                            <p style="font-size: 14px; color: #555;">TRIMMER 10KOHM 0.25W PC PIN SIDE
                                            </p>
                                            <p style="font-size: 14px; color: #777;">TT Electronics/BI</p>
                                            <div
                                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
                                                <span
                                                    <button
                                                    style="background-color: #FF4500; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                                                    Details
                                                    </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @endforeach


                            </div>
                        </div>

                        <a data-slide="prev" href="#media" class="left carousel-control">‹</a>
                        <a data-slide="next" href="#media" class="right carousel-control">›</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection

    @section('script')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#media').carousel({

            });
        });
    </script>
    @endsection
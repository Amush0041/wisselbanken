<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function estimating_service()
    {
        return view('frontend.services.estimating-service');
    }

    public function material_quote()
    {
        return view('frontend.services.material-quote');
    }

    public function shop_drawing()
    {
        return view('frontend.services.shop-drawing');
    }

    public function turnkey_construction()
    {
        return view('frontend.services.turnkey-construction');
    }

    public function submital_builder()
    {
        return view('frontend.services.submital-builder');
    }
}

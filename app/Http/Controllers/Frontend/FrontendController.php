<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Http\Request;

class FrontendController extends Controller
{
    public function mainPage(){ 
        return view('frontend.welcome');
    }

    public function contacts(){
        return view('frontend.contacts');
    }

    public function productDivision($slug){
        $division = Division::where('slug',$slug)->first();
        if($division){
            $products = Product::where('division_id',$division->id)->paginate(12);
        }else{
            $products = [];
        }
        return view('frontend.products.divisionsProducts',compact('products','division'));
        
    }

    public function productManufacturer($slug){
        $manufacturer = Manufacturer::where('slug',$slug)->first();
        if($manufacturer){
            $products = Product::where('manufacturer_id',$manufacturer->id)->paginate(24);
        }else{
            $products = [];
        } 
        return view('frontend.products.manufacturerProducts',compact('products','manufacturer'));
        
    }
}

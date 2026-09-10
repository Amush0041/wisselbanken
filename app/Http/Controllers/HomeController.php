<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Remove auth middleware to prevent redirect loops
        // Authentication is checked in the index method
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        
        if(Auth::user()->role == 'admin'){
            return redirect()->route('admin.dashboard');
        }else{
            // Clear any session link to prevent redirect loops
            session()->forget('link');
            return redirect()->route('product-filter');
        }
    }
}

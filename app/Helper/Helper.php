<?php

namespace App\Helper;

use App\Models\Division;
use App\Models\Manufacturer;
use App\Models\Pallet;
use App\Models\SavedList;
use Illuminate\Support\Facades\Auth;

class Helper
{
    public static function getDivisions()
    {
        $divsions = Division::with('specifications')->whereBetween('code', [3, 16])->get();
        return $divsions;
    }

    public static function getManufacturer()
    {
        $manufacturers = Manufacturer::orderBy('name', 'asc')->get(['name', 'slug']);
        return $manufacturers;
    }

    public static function getListsCount()
    {
        $listcount = 0;
        if (Auth::user()) {
            $listcount = SavedList::where('user_id', Auth::user()->id)->count();
        }
        return $listcount;
    }

    public static function getPalletCount()
    {
        if (Auth::check()) {
            // User is logged in - get count from database
            return (int) Pallet::where('user_id', Auth::user()->id)->count();
        } else {
            // User is not logged in - get count from session
            $pallet = session()->get('pallet', []);
            return (int) count($pallet);
        }
    }
}

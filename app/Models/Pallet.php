<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pallet extends Model
{
    protected $table = 'palletes'; 

    protected $fillable = [
        'user_id',
        'product_variation_color_id',
        'saved_list_id',
        'pallet_address_id',
        'quantity'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function variationColor()
    {
        return $this->belongsTo(ProductVariationColor::class, 'product_variation_color_id')
            ->with(['productVariation.product', 'color']);
    }

    public function savedList()
    {
        return $this->belongsTo(SavedList::class);
    }

    public function palletAddress()
    {
        return $this->belongsTo(PalletAddress::class, 'pallet_address_id');
    }

    public function subtotal()
    {
        return $this->quantity * $this->price;
    }

    // Helper method to get the current user's pallet items
    public static function currentUserItems()
    {
        return static::with('variationColor')
            ->where('user_id', auth()->id())
            ->get();
    }

    // Calculate subtotal for the user's pallet
    public static function currentUserSubtotal()
    {
        return static::where('user_id', auth()->id())
            ->sum(DB::raw('quantity * price'));
    }
}

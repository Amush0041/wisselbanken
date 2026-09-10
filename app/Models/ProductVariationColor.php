<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariationColor extends Model
{
    protected $table = 'product_variation_color'; // Ensure correct table name
    protected $fillable = ['product_variation_id', 'color_id'];

    // Relationship with ProductVariation
    public function productVariation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    // Relationship with Color
    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function savedListItems()
    {
        return $this->hasMany(SavedListItem::class, 'product_color_variation_id');
    }

    public function pallets()
    {
        return $this->hasMany(Pallet::class, 'product_variation_color_id');
    }
}

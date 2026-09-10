<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = [
        'name',
        'paint_type_id',
        'slug',
        'description',
        'status',
    ];
    public function color_effect()
    {
        return $this->belongsTo(ColorEffect::class); // Adjust the model name if different
    }

    public function paint_type()
    {
        return $this->belongsTo(PaintType::class); // Adjust the model name if different
    }

    public function productVariations()
    {
        return $this->belongsToMany(ProductVariation::class, 'product_variation_color');
    }
}

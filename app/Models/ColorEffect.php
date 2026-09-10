<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColorEffect extends Model
{
    protected $fillable = [
        'name', 
        'slug',
        'description',
        'status',
    ];
    public function productVariations()
    {
        return $this->hasMany(ProductVariation::class, 'color_effect', 'id');
    }
}

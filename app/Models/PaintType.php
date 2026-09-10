<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaintType extends Model
{
    protected $fillable = [
        'name', 
        'slug',
        'description',
        'status',
    ];
    public function productVariations()
    {
        return $this->hasMany(ProductVariation::class, 'paint_type', 'id');
    }

}

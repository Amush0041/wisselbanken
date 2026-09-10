<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = [
        'name',
        'code',
        'slug',
        'description',
        'status',
    ];

    public function specifications() 
    {
      return $this->hasMany(Specification::class);
    }

    // public function material_types()
    // {
    //   return $this->hasMany(MaterialType::class);
    // }

    // public function product_details()
    // {
    //   return $this->hasMany(ProductDetail::class);
    // }

}

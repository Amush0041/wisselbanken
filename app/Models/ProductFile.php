<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFile extends Model
{
    protected  $fillable = ['product_id','name','user_id','type','extension','size','path' ];
}

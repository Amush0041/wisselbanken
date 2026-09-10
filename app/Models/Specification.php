<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specification extends Model
{
    protected $fillable = [
        'division_id',
        'specification_number',
        'material_type',
        'slug',
        'description',
        'status',
    ];

    public function division() 
    {
      return $this->belongsTo(Division::class);
    }
}

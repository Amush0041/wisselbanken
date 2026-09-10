<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'content',
        'featured_image',
        'meta_description',
        'meta_keywords',
        'status',
        'views',
    ];
}

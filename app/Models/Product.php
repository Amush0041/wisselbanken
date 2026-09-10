<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'user_id',
        'division_id',
        'specification_id',
        'material_type_id',
        'manufacturer_id',
        'product_description',
        'feature_image',
        'status'
    ];

    // Automatically generate a unique slug before saving
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $product->slug = static::generateUniqueSlug($product->name);
        });
    }

    // Generate Unique Slug
    public static function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }
    public function productFiles()
    {
        return $this->hasMany(ProductFile::class);
    }
    
    public function productVariation()
    {
        return $this->hasMany(ProductVariation::class)->with([
            'size',
            'thickness',
            'finish',
            'paint_type',
            'colors',
            'color_effect'
        ]);
    }
    
   
    // Relationship with Division
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    // Relationship with Specification
    public function specification()
    {
        return $this->belongsTo(Specification::class, 'specification_id');
    }

    // Relationship with Manufacturer
    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    // Relationship with ProductVariations (One product can have multiple variations)
    public function variations()
    {
        return $this->hasMany(ProductVariation::class, 'product_id');
    }
}

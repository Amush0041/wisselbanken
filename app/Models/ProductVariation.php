<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $table = 'product_variations';
    
    protected $fillable = [
        'product_id','user_id' ,'size_id', 'thickness_id', 'finish_id', 
        'paint_type_id', 'color_effect_id','pricing'
    ];
    public static function boot()
    {
        parent::boot();
        static::deleting(function ($variation) {
            // Check if variation colors are used in lists or pallets before deleting
            $variationColors = \App\Models\ProductVariationColor::where('product_variation_id', $variation->id)->get();
            
            foreach ($variationColors as $variationColor) {
                // Check if used in pallets or lists
                $usedInPallet = \App\Models\Pallet::where('product_variation_color_id', $variationColor->id)->exists();
                $usedInList = \App\Models\SavedListItem::where('product_color_variation_id', $variationColor->id)->exists();
                
                if (!$usedInPallet && !$usedInList) {
                    // Safe to delete - not used anywhere
                    $variationColor->delete();
                }
                // If used, keep the record (it will be orphaned but still accessible)
            }
        });
    }
  
    public function colors()
    {
        return $this->belongsToMany(Color::class, 'product_variation_color');
    }
    // Relationship with Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Relationship with ProductVariationColor (One variation can have multiple colors)
    public function variationColors()
    {
        return $this->hasMany(ProductVariationColor::class, 'product_variation_id');
    }

    // Relationship with Size
    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    // Relationship with Thickness
    public function thickness()
    {
        return $this->belongsTo(Thickness::class, 'thickness_id');
    }

    // Relationship with Finish
    public function finish()
    {
        return $this->belongsTo(Finish::class, 'finish_id');
    }

    // Relationship with PaintType
    public function paint_type()
    {
        return $this->belongsTo(PaintType::class, 'paint_type_id');
    }
 
    public function color_effect()
    {
        return $this->belongsTo(ColorEffect::class, 'color_effect_id');
    }
  
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'saved_list_id',
        'product_variation_id',
        'color_id',
        'product_color_variation_id',
        'quantity'
    ];

    public function savedList()
    {
        return $this->belongsTo(SavedList::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function productColorVariation()
    {
        return $this->belongsTo(ProductVariationColor::class, 'product_color_variation_id');
    }
}

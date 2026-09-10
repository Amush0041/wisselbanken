<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id',
        'product_variation_color_id',
        'description',
        'item_type',
        'quantity',
        'unit_price',
        'subtotal',
        'item_notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'item_type' => 'string',
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function productVariationColor()
    {
        return $this->belongsTo(ProductVariationColor::class, 'product_variation_color_id');
    }

    public function calculateSubtotal()
    {
        $this->subtotal = $this->quantity * $this->unit_price;
        return $this->subtotal;
    }
}

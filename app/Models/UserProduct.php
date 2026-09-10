<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserProduct extends Model
{
    protected $fillable = [
        'user_id',
        'parent_product_id',
        'product_variation_color_id',
        'name',
        'sku',
        'variant_size',
        'is_archived',
        'archived_at',
        'category',
        'quantity',
        'unit_type',
        'description',
        'default_unit_price',
        'buy_price',
        'buy_price_tax',
        'sell_price',
        'sell_price_tax',
        'currency',
        'stock',
        'inventory_enabled',
        'on_hand_stock',
        'committed_stock',
        'available_for_sale',
        'to_be_invoiced',
        'to_be_billed',
        'image_url',
        'item_notes',
    ];

    protected $casts = [
        'default_unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'buy_price' => 'decimal:2',
        'buy_price_tax' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'sell_price_tax' => 'decimal:2',
        'stock' => 'decimal:2',
        'inventory_enabled' => 'boolean',
        'on_hand_stock' => 'decimal:2',
        'committed_stock' => 'decimal:2',
        'available_for_sale' => 'decimal:2',
        'to_be_invoiced' => 'decimal:2',
        'to_be_billed' => 'decimal:2',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productVariationColor(): BelongsTo
    {
        return $this->belongsTo(ProductVariationColor::class, 'product_variation_color_id');
    }

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_product_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProductPrice extends Model
{
    protected $fillable = [
        'user_id',
        'product_variation_color_id',
        'custom_price',
        'last_used_at',
    ];

    protected $casts = [
        'custom_price' => 'decimal:2',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productVariationColor()
    {
        return $this->belongsTo(ProductVariationColor::class, 'product_variation_color_id');
    }

    public static function getPriceForUser($userId, $productVariationColorId)
    {
        $userPrice = self::where('user_id', $userId)
            ->where('product_variation_color_id', $productVariationColorId)
            ->first();
        
        return $userPrice ? $userPrice->custom_price : null;
    }

    public static function savePrice($userId, $productVariationColorId, $price)
    {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'product_variation_color_id' => $productVariationColorId,
            ],
            [
                'custom_price' => $price,
                'last_used_at' => now(),
            ]
        );
    }
}

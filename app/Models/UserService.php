<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserService extends Model
{
    protected $table = 'user_services';

    protected $fillable = [
        'user_id',
        'title',
        'service_name',
        'sku',
        'variant_size',
        'quantity',
        'unit_type',
        'tax_label',
        'inventory_enabled',
        'description',
        'content_hash',
        'default_unit_price',
        'item_notes',
    ];

    protected $casts = [
        'default_unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'inventory_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

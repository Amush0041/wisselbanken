<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerActivityLog extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'action',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}


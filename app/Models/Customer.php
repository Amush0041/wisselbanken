<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'vat',
        'fax',
        'billing_street_1',
        'billing_street_2',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_country',
        'shipping_same_as_billing',
        'shipping_street_1',
        'shipping_street_2',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'shipping_same_as_billing' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(CustomerActivityLog::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}


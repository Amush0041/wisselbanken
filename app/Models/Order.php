<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'order_number',
        'name',
        'project_title',
        'email',
        'phone',
        'company',
        'address1',
        'address2',
        'city',
        'state',
        'postcode',
        'notes',
        'payment_method',
        'status',
        'subtotal',
        'tax',
        'tax_rate',
        'total',
        'approved_by',
        'rejected_by',
        'approval_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(\App\Models\Rbac\Organization::class, 'org_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->order_number = 'ORD-' . strtoupper(uniqid());
        });
    }
   
}

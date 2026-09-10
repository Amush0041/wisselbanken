<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqResponse extends Model
{
    protected $fillable = [
        'rfq_request_id',
        'seller_org_id',
        'created_by',
        'total_price',
        'valid_until',
        'notes',
        'status',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'total_price' => 'decimal:2',
    ];

    public function rfqRequest(): BelongsTo
    {
        return $this->belongsTo(RfqRequest::class);
    }

    public function sellerOrganization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Rbac\Organization::class, 'seller_org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

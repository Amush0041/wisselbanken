<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqRecipient extends Model
{
    protected $fillable = [
        'rfq_request_id',
        'seller_org_id',
        'status',
    ];

    public function rfqRequest(): BelongsTo
    {
        return $this->belongsTo(RfqRequest::class);
    }

    public function sellerOrganization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Rbac\Organization::class, 'seller_org_id');
    }
}

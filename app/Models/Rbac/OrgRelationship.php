<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgRelationship extends Model
{
    protected $fillable = [
        'from_org_id',
        'to_org_id',
        'relationship_type',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function fromOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'from_org_id');
    }

    public function toOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'to_org_id');
    }
}

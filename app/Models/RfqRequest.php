<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqRequest extends Model
{
    protected $fillable = [
        'org_id',
        'created_by',
        'title',
        'notes',
        'deadline',
        'status',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Rbac\Organization::class, 'org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(RfqRecipient::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RfqResponse::class);
    }
}

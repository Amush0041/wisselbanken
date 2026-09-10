<?php

namespace App\Models\Rbac;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    protected $fillable = [
        'quote_id',
        'user_id',
        'org_id',
        'granted_by',
        'granted_at',
        'is_active',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * In this platform a "project" is a Quote/Estimate.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}

<?php

namespace App\Models\Rbac;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'rbac_audit_logs';

    protected $fillable = [
        'user_id',
        'org_id',
        'method',
        'route_uri',
        'matched_pattern',
        'permission_group',
        'required_level',
        'project_id',
        'batch',
        'outcome',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

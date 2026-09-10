<?php

namespace App\Models\Rbac;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAssignmentLog extends Model
{
    protected $table = 'role_assignment_logs';

    protected $fillable = ['org_id', 'target_user_id', 'role_id', 'action', 'performed_by'];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public static function record(
        int $orgId,
        int $targetUserId,
        int $roleId,
        string $action,
        ?int $performedBy = null,
    ): void {
        static::create([
            'org_id'         => $orgId,
            'target_user_id' => $targetUserId,
            'role_id'        => $roleId,
            'action'         => $action,
            'performed_by'   => $performedBy,
        ]);
    }
}

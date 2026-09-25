<?php

namespace App\Models\Rbac;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ProjectMemberLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'project_member_logs';

    protected $fillable = ['org_id', 'project_id', 'target_user_id', 'action', 'performed_by'];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public static function record(int $orgId, int $projectId, int $targetUserId, string $action, ?int $performedBy): void
    {
        try {
            static::create([
                'org_id' => $orgId,
                'project_id' => $projectId,
                'target_user_id' => $targetUserId,
                'action' => $action,
                'performed_by' => $performedBy,
            ]);
        } catch (QueryException $e) {
            // Code may ship before migration 2026_09_24_000006 has run; a missing table is a statement-level error, so the surrounding transaction stays usable.
            $missing = ($e->errorInfo[1] ?? null) === 1146
                || $e->getCode() === '42S02'
                || str_contains($e->getMessage(), 'no such table');

            if (! $missing) {
                throw $e;
            }

            Log::warning('project_member_logs table is missing; membership event not logged. Run migration 2026_09_24_000006_create_project_member_logs_table.');
        }
    }
}

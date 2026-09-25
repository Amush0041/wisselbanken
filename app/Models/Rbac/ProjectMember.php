<?php

namespace App\Models\Rbac;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ProjectMember extends Model
{
    protected $fillable = [
        'project_id',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function enrol(Project $project, int $userId, int $orgId, ?int $grantedBy): self
    {
        if (! $project->exists) {
            throw new \InvalidArgumentException('Cannot enrol a member on an unsaved project.');
        }

        if ($project->trashed()) {
            throw new \InvalidArgumentException('Cannot enrol a member on a deleted project.');
        }

        if ((int) $project->org_id !== $orgId) {
            throw new \InvalidArgumentException('Organization does not match the project organization.');
        }

        $key = ['project_id' => $project->id, 'user_id' => $userId];
        $grant = [
            'org_id' => $orgId,
            'granted_by' => $grantedBy,
            'granted_at' => now(),
            'is_active' => true,
        ];

        // Membership change and its log row commit or roll back together.
        return DB::transaction(function () use ($key, $grant, $project, $userId, $orgId, $grantedBy) {
            $event = null;
            $member = static::where($key)->first();

            if (! $member) {
                try {
                    $member = static::create($key + $grant);
                    $event = 'added';
                } catch (UniqueConstraintViolationException) {
                    // A plain re-select reuses the caller transaction's REPEATABLE READ snapshot and can't see the concurrent row.
                    $member = static::where($key)->sharedLock()->firstOrFail();
                }
            }

            if (! $member->wasRecentlyCreated && ! $member->is_active) {
                $member->update($grant);
                $event = 'reactivated';
            }

            if ($event !== null) {
                ProjectMemberLog::record($orgId, (int) $project->id, $userId, $event, $grantedBy);
            }

            return $member;
        });
    }

    public function deactivate(?int $performedBy): void
    {
        DB::transaction(function () use ($performedBy) {
            $wasActive = $this->is_active;
            $this->update(['is_active' => false]);

            if ($wasActive) {
                ProjectMemberLog::record((int) $this->org_id, (int) $this->project_id, (int) $this->user_id, 'removed', $performedBy);
            }
        });
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

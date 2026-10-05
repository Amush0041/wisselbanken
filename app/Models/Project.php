<?php

namespace App\Models;

use App\Models\Rbac\Organization;
use App\Models\Rbac\ProjectMember;
use App\Support\Rbac\UniversalAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'on_hold', 'awarded', 'lost', 'archived'];

    protected $fillable = [
        'org_id',
        'name',
        'status',
        'address',
        'bid_due_at',
        'created_by',
    ];

    protected $casts = [
        'bid_due_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function rfqRequests(): HasMany
    {
        return $this->hasMany(RfqRequest::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function crosswalk(): HasMany
    {
        return $this->hasMany(PlanCrosswalk::class);
    }

    public function scopeVisibleTo(Builder $query, int $userId, int $orgId): Builder
    {
        if (UniversalAdmin::is($userId)) {
            return $query->where('projects.org_id', $orgId);
        }

        return $query->where('projects.org_id', $orgId)->whereExists(function ($sub) use ($userId, $orgId) {
            $sub->selectRaw('1')
                ->from('project_members')
                ->whereColumn('project_members.project_id', 'projects.id')
                ->where('project_members.user_id', $userId)
                ->where('project_members.org_id', $orgId)
                ->where('project_members.is_active', true);
        });
    }
}

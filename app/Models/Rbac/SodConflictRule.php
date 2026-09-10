<?php

namespace App\Models\Rbac;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SodConflictRule extends Model
{
    protected $table = 'sod_conflict_rules';

    protected $fillable = ['role_id_a', 'role_id_b', 'reason', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function roleA(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id_a');
    }

    public function roleB(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id_b');
    }

    /**
     * All role IDs that conflict with $roleId (bidirectional — one row per pair).
     *
     * @return Collection<int, int>
     */
    public static function conflictingRoleIds(int $roleId): Collection
    {
        return DB::table('sod_conflict_rules')
            ->where('is_active', true)
            ->where(function ($q) use ($roleId) {
                $q->where('role_id_a', $roleId)->orWhere('role_id_b', $roleId);
            })
            ->get()
            ->map(fn ($row) => $row->role_id_a === $roleId ? $row->role_id_b : $row->role_id_a)
            ->unique()
            ->values();
    }
}

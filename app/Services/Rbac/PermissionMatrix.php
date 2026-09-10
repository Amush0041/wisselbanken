<?php

namespace App\Services\Rbac;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * In-memory view of the static permission matrix (roles × permission groups → access
 * level). This data is immutable seed data, so it is cached and reused instead of being
 * re-joined on every checkPermission() call. The result: a permission check is one
 * indexed lookup on user_org_roles plus an array read here — no joins to the static
 * roles / permission_groups / role_permissions tables, regardless of how large those
 * usage tables grow.
 *
 * Call flush() after re-seeding or editing the matrix (e.g. RbacSeeder does this).
 */
class PermissionMatrix
{
    private const CACHE_KEY = 'rbac.permission_matrix.v1';
    private const CACHE_TTL = 86400; // 24h; also flushed explicitly on re-seed

    /**
     * role_id => [ permission_group_slug => access_level ]
     *
     * @var array<int, array<string, string>>|null
     */
    private ?array $byRole = null;

    /**
     * Strongest access level a set of role ids grants for a permission group slug,
     * or null if none of them grant anything for that group.
     *
     * @param  array<int, int>  $roleIds
     */
    public function levelFor(array $roleIds, string $permissionGroup): ?string
    {
        $matrix = $this->matrix();
        $best = null;

        foreach ($roleIds as $roleId) {
            $level = $matrix[$roleId][$permissionGroup] ?? null;
            if ($level !== null && ($best === null || self::rank($level) < self::rank($best))) {
                $best = $level;
            }
        }

        return $best;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function matrix(): array
    {
        if ($this->byRole !== null) {
            return $this->byRole;
        }

        return $this->byRole = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $rows = DB::table('role_permissions as rp')
                ->join('permission_groups as pg', 'pg.id', '=', 'rp.permission_group_id')
                ->select('rp.role_id', 'pg.slug', 'rp.access_level')
                ->get();

            $matrix = [];
            foreach ($rows as $row) {
                $matrix[$row->role_id][$row->slug] = strtoupper($row->access_level);
            }

            return $matrix;
        });
    }

    public function flush(): void
    {
        $this->byRole = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Hierarchy rank: F > A > O > S > R (lower number = stronger).
     */
    public static function rank(string $level): int
    {
        return match (strtoupper($level)) {
            'F' => 0,
            'A' => 1,
            'O' => 2,
            'S' => 3,
            'R' => 4,
            default => PHP_INT_MAX,
        };
    }
}

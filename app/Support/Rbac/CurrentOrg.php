<?php

namespace App\Support\Rbac;

use Illuminate\Support\Facades\DB;

class CurrentOrg
{
    public static function id(int $userId): ?int
    {
        $selected = session(config('rbac.current_org_session_key'));

        if ($selected !== null && $selected !== '' && self::hasActiveRole($userId, (int) $selected)) {
            return (int) $selected;
        }

        $first = DB::table('user_org_roles')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->value('org_id');

        return $first !== null ? (int) $first : null;
    }

    private static function hasActiveRole(int $userId, int $orgId): bool
    {
        return DB::table('user_org_roles')
            ->where('user_id', $userId)
            ->where('org_id', $orgId)
            ->where('is_active', true)
            ->exists();
    }
}

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

        if ($selected !== null && $selected !== '' && UniversalAdmin::is($userId)
            && DB::table('organizations')->where('id', (int) $selected)->exists()) {
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

    /**
     * Raw session value for non-listed users (their existing behaviour, unchanged); the
     * validated org for universal admins, whose session org needs no membership row.
     */
    public static function sessionOrg(int $userId): mixed
    {
        return UniversalAdmin::is($userId)
            ? self::id($userId)
            : session(config('rbac.current_org_session_key'));
    }
}

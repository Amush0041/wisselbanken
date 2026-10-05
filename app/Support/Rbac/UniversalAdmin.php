<?php

namespace App\Support\Rbac;

use App\Models\Rbac\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UniversalAdmin
{
    public static function is(int|User|null $user): bool
    {
        $ids = config('rbac.universal_admin_user_ids', []);

        if ($ids === [] || $user === null) {
            return false;
        }

        $id = $user instanceof User ? (int) $user->getKey() : $user;

        if (! in_array($id, $ids, true)) {
            return false;
        }

        return DB::table('users')->where('id', $id)->whereNotNull('email_verified_at')->exists();
    }

    public static function recordBypass(
        int $userId,
        int $orgId,
        ?string $group,
        ?string $level,
        ?int $projectId = null,
        string $reason = 'acted_as_platform_admin',
    ): void {
        $request = request();
        $key = implode('|', [$reason, $userId, $orgId, $group, $level, $projectId]);
        $seen = $request->attributes->get('universal_admin_seen', []);

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $request->attributes->set('universal_admin_seen', $seen);

        $method = $request->method();
        $uri = $request->route()?->uri() ?? $request->path();

        $context = [
            'user_id' => $userId,
            'org_id' => $orgId,
            'method' => $method,
            'route_uri' => $uri,
            'permission_group' => $group,
            'required_level' => $level,
            'project_id' => $projectId,
            'reason' => $reason,
        ];

        try {
            AuditLog::create($context + ['outcome' => 'allowed_universal_admin']);
        } catch (\Throwable $e) {
            Log::error('universal_admin audit row write failed', ['user_id' => $userId, 'error' => get_class($e)]);
        }

        try {
            Log::channel('universal_admin')->info('universal_admin', $context);
        } catch (\Throwable $e) {
            Log::error('universal_admin log channel write failed', ['user_id' => $userId, 'error' => get_class($e)]);
        }
    }
}

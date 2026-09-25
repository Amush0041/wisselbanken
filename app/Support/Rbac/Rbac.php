<?php

namespace App\Support\Rbac;

use App\Services\Rbac\PermissionService;

/**
 * Thin convenience wrapper around PermissionService::checkPermission(). The service
 * remains the single source of truth; this just makes call sites terse.
 *
 *   Rbac::check($user->id, $orgId, 'procurement', 'S');
 *   Rbac::check($user->id, $orgId, 'estimate_management', 'F', $projectId);  // $projectId is a projects.id
 */
class Rbac
{
    public static function check(
        int $userId,
        int $orgId,
        string $permissionGroup,
        string $requiredLevel,
        ?int $projectId = null,
    ): bool {
        return app(PermissionService::class)->checkPermission(
            $userId,
            $orgId,
            $permissionGroup,
            $requiredLevel,
            $projectId,
        );
    }
}

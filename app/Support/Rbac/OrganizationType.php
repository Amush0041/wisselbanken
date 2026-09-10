<?php

namespace App\Support\Rbac;

/**
 * The 20 supported organization types. Stored as a string slug on the
 * organizations.org_type column (not a separate table). The org type drives the
 * auto-assigned role bundle at registration (Phase 2).
 */
class OrganizationType
{
    /**
     * @return array<int, array{0:string,1:string,2:string}> list of [slug, name, purpose]
     */
    public static function all(): array
    {
        return require dirname(__DIR__, 3)
            . '/database/seeders/Rbac/data/organization_types.php';
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_map(fn ($row) => $row[0], self::all());
    }

    public static function exists(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }
}

-- Production data fix (client item M11): Organization Owner and Organization Admin
-- get Audit & Logging = R. Idempotent: safe to run twice. Touches only role_permissions.
-- Run the SELECT first; expect 0 rows before, 2 rows after.

SELECT r.slug AS role, pg.slug AS grp, rp.access_level
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permission_groups pg ON pg.id = rp.permission_group_id
WHERE pg.slug = 'audit_and_logging' AND r.slug IN ('organization_owner','organization_admin');

INSERT INTO role_permissions (role_id, permission_group_id, access_level, created_at, updated_at)
SELECT r.id, pg.id, 'R', NOW(), NOW()
FROM roles r
JOIN permission_groups pg ON pg.slug = 'audit_and_logging'
WHERE r.slug IN ('organization_owner','organization_admin')
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions x WHERE x.role_id = r.id AND x.permission_group_id = pg.id
  );

-- Re-run the first SELECT: it must now return 2 rows, both 'R'.
-- The permission matrix is cached; after the INSERT run:  php artisan cache:clear
-- The matrix cache lasts 24 hours, so the clear is required before the Owner retries Organization -> Audit Log.

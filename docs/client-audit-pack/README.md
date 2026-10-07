# Audit pack: routes and permission map

Generated from branch `release/1.0-prod` (commit e5b1284).

- `route-list.json`: output of `php artisan route:list --json` (291 routes: method, URI, name, action, middleware).
- `route_permission_map.php`: a copy of `config/route_permission_map.php`. Key is `"METHOD uri"`; value is `[permission group, required level, batch, optional project_param / quote_param]`.

## How to diff them
For every route in `route-list.json` that has the `Authenticate` middleware, look up `"METHOD uri"` (or `"* uri"`) in the map. Result on this branch: every authenticated route is in the map except the 13 below.

## Authenticated routes intentionally NOT in the map
They only touch the caller's own account or session, so there is no organization permission to check:

| Route | Why open |
|---|---|
| POST logout | ends the caller's own session |
| GET/POST password/confirm, GET email/verify, POST email/resend | account scaffolding for the caller's own account |
| GET register-complete | registration wizard for the caller's new account |
| GET user-dashboard, GET workspace | landing pages; only the caller's own data |
| POST org/switch | switches between organizations the caller already belongs to |
| GET org-admin/my-roles | caller's own roles and delegations |
| GET get-list-count | navbar badge: caller's own list count |
| GET/PUT profile | caller's own profile |

## Notes
- The enforcement mode is the database row `rbac_settings.rbac_mode` (not `.env`). Per-organization enforcement is the row `rbac_enforced_org_ids`.
- Controllers also check the level server-side, mirroring this map, so they hold in audit mode too.
- The middleware reads this map in `app/Http/Middleware/RbacAudit.php`.
- Reverse check on this branch: every entry in the map matches a real route (method + URI) in `route-list.json`.

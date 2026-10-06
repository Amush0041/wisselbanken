# Wisselbanken: developer and operations handover

For the client's developers and operations staff. It replaces the old
`HANDOFF.md`, which contained mistakes and has been removed. Everything here
was checked against the code on 2026-09-26 (branch `fix/p2-cleanup`). Where a
fact could not be checked from the repository, it says so. The technical
detail behind this guide is in `ARCHITECTURE.md`.

## 1. What this is

Wisselbanken is a B2B construction-materials marketplace. Buyers (general
contractors, subcontractors, owners) create projects, estimates and RFQs;
sellers (manufacturers, distributors, rep agencies) answer RFQs. Access is
controlled by a custom, multi-tenant role-based permission system (RBAC) built
for this project: users hold roles per organization, and each role grants a
level on each permission group.

## 2. Stack

- PHP 8.2, Laravel 11 (`composer.json`: `php ^8.2`, `laravel/framework ^11.31`).
- MariaDB 10.6+ or MySQL 8+ (as stated in `README.md`; the tests run on sqlite).
- Server-rendered Blade views; Tailwind and Bootstrap via Vite (`package.json`).
  Do not assume Livewire: it is installed as a vendor package but nothing in
  `app/`, `resources/` or `routes/` uses it.
- PDF export: `barryvdh/laravel-dompdf`. Excel import: `maatwebsite/excel`.
  Admin tables: `yajra/laravel-datatables`.
- Queue and cache default to the database (`.env.example`).

## 3. Setup (development)

1. `composer install` and `npm install`.
2. `cp .env.example .env`, then `php artisan key:generate`.
3. Set the database in `.env` (`DB_CONNECTION=mysql`, host, name, user, password).
4. Create the schema and seed: see the warnings below, then `php artisan migrate`
   and `php artisan db:seed` (`DatabaseSeeder` runs `AdminSeeder` and the RBAC
   seeder).
5. `npm run build` (or `npm run dev`), then `php artisan serve`.

Warnings:

- `AdminSeeder` creates a platform admin (`admin@admin.com`) with a default
  password written in the seeder file. Change that password immediately on any
  shared or live environment.
- The three `2026_09_25_*` migrations are deliberately guarded (section 9). On a
  new empty database, `php artisan migrate` will stop at the first of them
  unless `APP_ENV=testing`; see section 9 for what to do.
- The test base class (`tests/Feature/Rbac/RbacTestCase.php`) states that the
  full migration set cannot `migrate:fresh` on an empty database (a few early
  `2025_01_15_*` migrations alter tables created later). We did not re-check
  this. For a new environment, restore a copy of an existing database.
- `.env.example` sets `APP_ENV=local`, `APP_DEBUG=true`. Production must use
  `APP_DEBUG=false` (reported to be set on the live server; not verifiable here).

## 4. Environment variables that matter

| Variable | Meaning |
|---|---|
| `RBAC_MODE` | Only used once, by migration `2026_07_07_000001`, to seed the DB row `rbac_settings.rbac_mode`. Changing it later has no effect. |
| `RBAC_ENFORCE_BATCHES` | Comma list (`admin,read,write,approve`) of batches that block when the mode is `enforce`. Empty = all batches block. |
| `PHASE5_BACKUP_CONFIRMED` | Must be `1` for the Phase 5b migrations to run (section 9). |
| `APP_ENV`, `APP_DEBUG`, `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | Standard Laravel. |

## 5. The permission model in brief

- Users belong to one or more organizations. A user's roles are rows in
  `user_org_roles` (user, org, role, active). The `users.role` column only
  distinguishes a platform admin (`admin`) from everyone else (`user`).
- Each role has a level on each of 25 permission groups. Levels, strongest
  first: **F** (full), **A** (approve), **O** (own), **S** (submit), **R**
  (read). A higher level satisfies a lower requirement.
- Seed size (counted from `database/seeders/Rbac/data/`): 49 roles, 25
  permission groups, 19 organization types, 365 role-permission rows.
- Every check goes through one method:
  `PermissionService::checkPermission($userId, $orgId, $group, $level, $projectId = null)`.
  Always pass the organization; get it from `CurrentOrg::id($userId)`. Never
  test a role name in code.
- Project scope: when a `projectId` (a `projects.id`) is passed, the user must
  also be an active member of that project. There is no owner override.
- Quotes belong to a project; a quote is visible to a user when the project is
  visible to them and they hold `estimate_management` R, or they wrote it.
  RFQs also belong to a project. Sellers never see the buyer's project.
- Add project members only with `ProjectMember::enrol()` and remove them with
  `deactivate()`; both write the `project_member_logs` history.
- Views can hide blocks with `@canDo('group', 'level') ... @endCanDo`. This is
  cosmetic; the server-side checks are what protect data.

## 6. Two RBAC modes and how to switch

The middleware `RbacAudit` looks up each request in
`config/route_permission_map.php`.

- **audit**: a failed check is written to `rbac_audit_logs` as `would_block`
  and the request goes through.
- **enforce**: a failed check is logged as `blocked` and denied (JSON: 403;
  browser: redirect back with a message).

The operative mode is the database row `rbac_settings.rbac_mode` (cached for up
to 60 seconds), not `.env`. Change it as a platform admin under
`admin/rbac/enforcement` (`POST admin/rbac/enforcement/toggle-mode`), or update
the row. Local `.env` says enforce; the live server is reported to be in
enforce mode (not verifiable from the repository).

Because audit mode lets requests through, controllers also check levels
themselves (section 7), so the important protections hold in both modes.

## 7. How to add a route or permission correctly

Do all four steps for any route behind login. The automated guard fails the
suite if a step is missing.

1. **Map entry.** Add `'METHOD uri' => [group, level, 'batch' => ...]` to
   `config/route_permission_map.php`. The key must match the route exactly.
   Level: R for reads, S for create, O for edit, F for delete or manage, A for
   approvals. Batch: `read` for reads, `write` for create/update, `approve` for
   deletes and approvals, `admin` for management. If the route is scoped to a
   project add `'project_param' => 'name'` (parameter holds a `projects.id`) or
   `'quote_param' => 'name'` (holds a `quotes.id`, resolved to its project);
   never both.
2. **Controller check that mirrors the map.** In the action, check the same
   group and level and return 403 otherwise (see `requireOrgLevel()` in
   `OrgAdminController`, `CustomerController` or `RfqController`). Use the org
   from `CurrentOrg::id`, and pass the project id when the route is
   project-scoped. For project-scoped data, look the object up through
   `Project::visibleTo(...)` / `Quote::visibleTo(...)` so a hidden object
   returns 404.
3. **Tests.** Add a test that covers: the allowed role, a wrong role (denied),
   a wrong organization (denied), and, for project-scoped routes, a user with
   the role who is not a member (denied). Run it in both modes. The tests are
   PHPUnit classes (`tests/Feature/Rbac/`); extend `RbacTestCase` (or
   `ProjectTestCase` for project features) instead of building your own setup.
   (`CLAUDE.md` calls them Pest tests; the repository has PHPUnit only.)
4. **Open routes.** A route that is deliberately open to every logged-in user
   (like the profile page) is not mapped. Add it to the `OPEN_ROUTES` list in
   `tests/Feature/Rbac/AuditFindingsRound2Test.php` with a reason.

The every-route guard (`AuditFindingsRound2Test`) runs each mapped route as a
read-only viewer and as a user with no matching grants, in audit and enforce
mode, and expects 403 with no database change. Two behaviours are accepted and
documented there: some reads (quotes list/details/pdf, projects list/show,
plan crosswalk index) return filtered or empty data or 404 in audit mode rather
than 403, and writes on a hidden project/quote/RFQ answer 404 instead of 403.

Never change an approval or membership path without keeping `ProjectMember`
and `ApprovalRoutingService` as the only ways to do it.

## 8. RFQs, approvals and orders in brief

- RFQ flow: `sent` -> `closed` (buyer selects a response) -> `converted`
  (turned into an order). A seller can respond only while the RFQ is `sent`
  and their own recipient row is still pending.
- Converting an RFQ to an order needs `quote_rfq_management` F and
  `procurement` S. Approval routing (`ApprovalRoutingService`): sole approver =
  the requester -> auto-approved; other approvers -> order goes to
  `pending_approval`; **nobody has approval authority -> the request is refused
  with a message** telling the user to ask an owner to assign an Executive
  Approver. Checkout behaves the same way.
- An "approver" is any user with an active role that has `approval_authority`
  at A or F in that organization.

## 9. Migrations and release order

Deployment rules for this release (derived from the migration files and
guards):

1. Back up the database and test-restore the backup.
2. Run the additive migrations **before** uploading the code:
   `2026_09_24_000006` (membership log) and `2026_09_26_000001`
   (`rfq_requests.project_id`). If the code goes first, buyer RFQ pages fail
   and membership changes are not logged.
3. **Keep the `2026_09_25_*` files (Phase 5b) out of the release.** They sort
   before `2026_09_26_000001`, and outside maintenance mode they throw, so a
   plain `php artisan migrate` stops there and never reaches
   `2026_09_26_000001`. Either exclude those three files from the upload, or run
   the needed migrations by path: `php artisan migrate --path=database/migrations/2026_09_26_000001_add_project_id_to_rfq_requests_table.php`.
4. Run the projects backfill (section 10) with the release that contains Phase
   5a. Without it, quotes that have no project are visible to nobody.

**Phase 5b** (`2026_09_25_000001` to `000003`) makes `project_id` mandatory on
quotes, project members and the crosswalk, and drops the legacy `quote_id`
columns. Two of them cannot be reversed. Each refuses to run unless the app is
in maintenance mode (`php artisan down`) and `PHASE5_BACKUP_CONFIRMED=1`, and
refuses if the data is not clean (quotes without a project, active legacy
memberships without a project membership, crosswalk rows without a project or
in a different organization, duplicate line codes). Plan: run 5b only after the
backfill and about 14 days of running 5a, then remove the backfill command about
30 days after. 5b has never been run anywhere.

## 10. Commands

- `php artisan projects:backfill [--dry-run] [--overrides=file.csv] [--force] [--allow-live]`
  Groups quotes without a project into projects and moves their old
  memberships and crosswalk rows to the project. Reports go to
  `storage/app/backfill/projects-entity/<timestamp>/`. A real run needs
  maintenance mode (or `--allow-live`); on `APP_ENV=production` it also needs
  `--force`. Exit code 0 clean, 1 rows unresolved or errors, 2 refused, 3
  verification failed. Suggested order: backup -> `php artisan down` ->
  `--dry-run` -> real run (`--force --no-interaction`) -> `--dry-run` again
  (must find nothing to do) -> `php artisan up`. The overrides CSV header is
  `quote_id,org_id,project_key,project_name`.
- Seeders: `php artisan db:seed --class="Database\Seeders\Rbac\RbacSeeder" --force`
  re-seeds roles, groups and the permission matrix; do not re-run it on a live
  database without checking what it changes.
- No other custom artisan commands exist in `app/Console/Commands`.

## 11. Running the tests

Tests run on in-memory sqlite. Never point them at a real database.

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test                 # full suite
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=Rbac   # RBAC suite
```

`phpunit.xml` has the sqlite lines commented out, so pass the two variables as
shown. Expected baseline: only these 4 known failures, none caused by the
projects, RFQ or audit work:

1. `ExampleTest` "the application returns a successful response": Laravel
   scaffolding, needs a table that the test database lacks.
2. `AuditMiddlewareTest` "enforce mode blocks and records blocked": expects a
   403 for a user with no organization but gets the by-design 302 redirect.
3. `SeedMatrixTest` "seeds expected counts": expects 20 organization types, the
   seed has 19.
4. `SeedMatrixTest` "phase distribution": expects `api_system` as a P2 role, it
   is seeded as P3.

Failures 3 and 4 wait for the client's decision on the matrix (do not edit the
test or the seed to make them pass before that decision). See `TESTING.md` for
the fuller test notes.

What the tests cannot prove (sqlite): row locks and the concurrent-enrol race,
`FOR UPDATE` behaviour, MariaDB collation/timezone behaviour, the
`quotes.user_id` cascade, and behaviour on production-sized data.

## 11b. Operations: universal (platform) admins

- Listed in `.env`: `UNIVERSAL_ADMIN_USER_IDS=1,2,3` (user ids). After changing it run `php artisan config:clear` (the value is read at config time).
- The user must have a verified email, or the listing has no effect.
- Keep the list short. Remove an id and run `config:clear` to revoke.
- Use non-listed accounts when running the client's RBAC audit, since listed users are never denied.
- Listed users can also delete an organization and transfer its ownership without being the owner, and revoke other users' delegations and API tokens in the organization they are working in. Each is logged. The owner-only and own-record limits still apply to every non-listed user.
- Listed users get 'Platform admin panel' and 'Enforcement mode' links in the top bar.
- Bypasses are recorded in `rbac_audit_logs` (outcome `allowed_universal_admin`) and in `storage/logs/universal-admin-*.log`, which must be writable by the web server user. Logs are never pruned.

## 12. Known limitations and open items

- **Matrix decisions pending with the client**: 25 groups (plan says 24), 19
  organization types (plan says 20), `api_system` phase P2 vs P3. The two
  `SeedMatrixTest` failures are these.
- **Delegation (plan question Q11) is undecided.** A delegate is checked
  against the principal's roles, but projects are scoped by the delegate's own
  user id, so a delegate can pass the check and then get a 404.
- **MariaDB-only checks were never run** (enrol race, locks, cascade). They
  need a real MariaDB copy before Phase 5b.
- **Phase 5b** has not been run (section 9); the backfill command stays until
  then.
- **Dead code:** `OrgRelationshipService::sellerAuthorizationError()` compares
  the organization type to `rep_agency`; the real slug is
  `manufacturer_s_rep_sales_agency`. Rep agencies are therefore never checked
  for a manufacturer principal when answering an RFQ. Distributors are.
- **Data-scoped reads in audit mode** return filtered/empty data or 404, not
  403 (section 7). In enforce mode the route map answers 403 (JSON) or a
  redirect (browser).
- **RBAC mode on the live server** and whether the backfill has been run
  cannot be verified from the repository: check `rbac_settings`, run
  `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL;` and
  `php artisan migrate:status`.

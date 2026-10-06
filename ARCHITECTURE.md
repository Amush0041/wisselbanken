# Architecture: RBAC, Projects, RFQ (living reference)

Last verified against the code: 2026-09-26, branch `fix/p2-cleanup`. Every
statement below was checked in the live code (routes, controllers, services,
migrations, `config/route_permission_map.php`, models). If code and this file
disagree, the code wins: fix this file. The operating guide for the client's
developers is `HANDOVER-CLIENT.md`.

Source of the original spec: `Revised_RBAC_Module_Implementation_Plan.pdf`
(client-approved, 12-week, "Release 1" = the P1 + P2 roles).

## 1. Core permission model

- Single entry point: `PermissionService::checkPermission(userId, orgId, permissionGroup, requiredLevel, projectId?)`
  (`app/Services/Rbac/PermissionService.php`). `orgId` is mandatory. Nothing
  else may decide access; never inline-check a role name.
- Roles live in `user_org_roles` (user_id, org_id, role_id, is_active), per
  organization. Org roles are never a column on `users`. The only role-like
  column on `users` is `users.role` (`user` or `admin`), which marks a
  **platform admin**: `CheckRole` (`checkRole:admin` / `checkRole:user` route
  middleware) uses it, and `RbacAudit` skips the org check for `role = admin`.
- Access levels rank F > A > O > S > R (`PermissionMatrix::rank()`; F strongest).
  A grant satisfies a requirement when its rank is at or above the required
  level. The matrix (role x permission group -> level) is seeded from
  `database/seeders/Rbac/data/` and served from a cache by `PermissionMatrix`.
- Check order inside `checkPermission()`: (0) if the user is a delegate,
  evaluate against the principal (`DelegationService::resolveEffectiveUserId`);
  (1) collect the active role ids in that org; (2) strongest level for the
  group must satisfy the required level; (3) if `projectId` is given, the
  effective user must be an active member of that project.
- Seed size (counted from the seed data files): 25 permission groups, 49
  roles (P1 17, P2 14, P3 18), 19 organization types, 365 role-permission rows.
  The client's plan says 24 groups / 20 org types / 31 Release-1 roles: see
  section 12.
- `CurrentOrg::id($userId)` (`app/Support/Rbac/CurrentOrg.php`) is how
  controllers get the acting org: the session key `rbac_current_org_id`
  (set by `OrgSwitchController`, seeded by `RbacAudit` when missing) if the
  user still has an active role there, else the user's first active org, else
  `null`. Controllers must use it rather than reading the session directly.

## 2. Projects are their own entity

- `projects` table (soft deletes): `org_id`, `name`, `status`
  (`active`, `on_hold`, `awarded`, `lost`, `archived`), `address`,
  `bid_due_at` (datetime), `created_by`. Model: `App\Models\Project`.
- A quote (estimate) belongs to a project via `quotes.project_id`. RFQs belong
  to a project via `rfq_requests.project_id` (nullable for legacy rows).
  `plan_crosswalk.project_id` links crosswalk rows to a project.
- `checkPermission()`'s `projectId` is a `projects.id`, never a `quotes.id`.
- `Project::scopeVisibleTo($userId, $orgId)`: project is in that org AND the
  user has an **active** `project_members` row for it. There is no owner
  bypass: an org owner sees only the projects they are a member of.
- Creating a project needs `project_management` S; the creator is enrolled
  automatically (`ProjectController::store` calls `ProjectMember::enrol`).
  Update needs O, delete needs F, member changes need F.
- Deletion refusals: a project that still has quotes or RFQs cannot be
  deleted (`ProjectController::destroy`, under a row lock). An organization
  that owns any project (soft-deleted included) cannot be deleted (admin and
  org-settings paths), and the platform-admin "delete user" action refuses
  when the user solely owns an org that has projects, created any project, or
  owns quotes (`Admin/RbacController`).

## 3. Project membership

- Table `project_members`: `project_id`, `user_id`, `org_id`, `granted_by`,
  `granted_at`, `is_active`. The membership org must equal `projects.org_id`.
  The check in `PermissionService::isActiveProjectMember` joins `projects` and
  also requires the project not to be soft-deleted.
- Legacy rows keyed only by `quote_id` (with `project_id` NULL) never grant
  access. The `quote_id` column still exists until Phase 5b (section 9).
- Always change membership through `ProjectMember::enrol($project, $userId, $orgId, $grantedBy)`
  and `$member->deactivate($performedBy)`. Both run in a DB transaction and
  write `project_member_logs` (`action` = `added`, `reactivated`, `removed`;
  `deactivate()` logs only when the member was active). Never insert or update
  `project_members` directly. The backfill command deliberately does not use
  `enrol()` (it must not write log rows).
- `ProjectMemberLog::record()` is fail-soft: if the table does not exist yet
  it logs a warning and continues (so code can ship a moment before migration
  `2026_09_24_000006`). Any other database error is rethrown.
- The platform-admin "delete user" action hard-deletes the user's
  `project_members` rows, but first writes a `removed` log row for each
  active membership (performed_by = the admin).
- Membership changes are shown on the org audit-log page (Role Changes tab).

## 4. Enforcement: the route map, RbacAudit, and controller checks

### 4.1 The route map

`config/route_permission_map.php` maps `"METHOD uri"` (or `"* uri"` when every
verb shares the rule) to `[group, level, 'batch' => ..., 'project_param' | 'quote_param' => ...]`.
Currently 242 entries: 108 `admin`, 75 `read`, 35 `write`, 24 `approve` batch.

- `project_param`: the named route parameter holds a `projects.id` (8 entries,
  all under `projects/{project}...`).
- `quote_param`: the named route parameter holds a `quotes.id` (10 entries:
  `quotes/{id}...`, `quotes/{quoteId}/items/{itemId}`, and
  `projects/{quote}/workspace`). `RbacAudit` resolves the quote to its
  `project_id` (soft-deleted quotes included) and checks project membership.
  An entry never carries both.
- A scoped route whose project cannot be resolved (missing id, or the quote
  has `project_id` NULL) fails closed with reason `project_unresolved`.
- Routes with no entry are not checked and not logged by the middleware.

### 4.2 RbacAudit middleware

`App\Http\Middleware\RbacAudit` is appended to the `web` middleware group
(`bootstrap/app.php`), so it runs before route middleware such as `auth` and
`checkRole`. For each request it: seeds the org session key if missing; looks
up the rule; passes through if there is no rule, no user, or the user is a
platform admin; resolves org and project scope; calls `checkPermission()`.
A failed check is written to `rbac_audit_logs` (`outcome` = `would_block` or
`blocked`, with `reason` = `no_org`, `project_unresolved`, `no_grant` or
`no_grant_or_not_project_member`, plus `project_id` and `quote_id`).

### 4.3 Audit mode versus enforce mode

- **The operative mode is the database row `rbac_settings.rbac_mode`**
  (`audit` or `enforce`), read through `RbacSetting::get()` with a 60-second
  cache. Default when the row is missing: `audit`. `.env` `RBAC_MODE` is only
  copied into that row once, by migration `2026_07_07_000001`; changing
  `RBAC_MODE` later does nothing. Switch modes at
  `POST admin/rbac/enforcement/toggle-mode` (platform admin) or by updating
  the row.
- `RBAC_ENFORCE_BATCHES` (`config('rbac.enforce_batches')`, still read from
  `.env`) limits which batches block while in enforce mode. Empty list =
  every batch blocks. Batches not listed keep behaving as audit.
- Audit mode: a failed check is only logged; the request continues.
- Enforce mode: JSON/AJAX requests get `403` with a JSON body
  (`rbac_error`, `message`); ordinary browser requests are redirected back
  with a `rbac_denied` flash (a 302, by design). This is why a no-org user in
  enforce mode gets a 302 (known test failure, section 12).
- Local `.env` says `RBAC_MODE=enforce` and `RBAC_ENFORCE_BATCHES=admin,read,write,approve`.
  Production is reported to be in enforce mode; this cannot be verified from
  the repository.

### 4.4 Server-side level checks in controllers (the audit-mode guard)

Because audit mode lets everything through, every controller action behind a
mapped route also checks the level itself and returns 403 (org from
`CurrentOrg::id`, project id passed when the route is project-scoped),
mirroring the map entry. Pattern: a private `requireOrgLevel($group, $level)`
(e.g. `OrgAdminController`, `CustomerController`, `RfqController`). The
controllers covered: org-admin, org-settings, delegation, API tokens, project
and project-member, plan crosswalk, quotes, customers, user products, user
services, saved lists, pallet/checkout, orders, order approvals, RFQ buyer and
seller.

**The guard test** is `tests/Feature/Rbac/AuditFindingsRound2Test.php`. It
loops over every entry in the route map and, in both audit and enforce mode,
calls the route as `viewer_read_only` and as a user whose role holds none of
the mapped groups (`inventory_manager`). Rules it enforces:

- a route that the actor is not granted must answer 403 and leave the
  database unchanged (tables: quotes, quote_items, customers, saved_lists,
  plan_crosswalk, rfq_requests, rfq_responses, orders, project_member_logs and
  the RBAC tables);
- a mapped route that does not exist, or has no fixture for a URL parameter,
  fails the test;
- `viewer_read_only` may pass only routes mapped at level R;
- every mapped `admin/...` route must sit behind `checkRole:admin`;
- every authenticated route (one carrying the `auth` middleware) must be in
  the map or in the `OPEN_ROUTES` allow-list in that test file, with a reason.

**Intentionally open authenticated routes** (`OPEN_ROUTES`, no map entry, no
level check): `POST logout`, `GET/POST password/confirm`, `GET email/verify`,
`POST email/resend`, `GET register-complete`, `GET user-dashboard`,
`GET workspace`, `POST org/switch` (membership is verified),
`GET org-admin/my-roles` (self-scope only), `GET get-list-count` (navbar
badge), `GET profile`, `PUT profile`. Public and guest routes (catalog,
pallet, login, invite) are outside the guard.

**Accepted behaviours, documented in that test (not bugs to fix silently):**

1. Data-scoped reads are not hard-denied in audit mode. They filter their data
   to the caller's visible projects instead, so a member without the level gets
   an empty result or a 404 in audit mode, and the map's 403 only in enforce
   mode. Existing suites pin this. The list (`DATA_SCOPED_READS`):
   `GET quotes/list`, `GET quotes/{id}/details`, `GET quotes/{id}/pdf`,
   `GET quotes/{id}/pdf-preview`, `GET projects`, `GET projects/list`,
   `GET projects/{project}`, `GET projects/{quote}/workspace`,
   `GET plan-crosswalk`. The guard requires that they leak no fixture data
   (answer 200, 403 or 404 and no project/quote/crosswalk marker text).
2. 404 before 403 on scoped writes: writes on project-, quote-, RFQ- or
   crosswalk-scoped objects resolve visibility first, so a hidden object
   answers 404 instead of 403. Still a denial; nothing is written.
3. `GET org-admin/projects` now requires `project_management` R (403 below).
4. Ordinary-browser denials in enforce mode are 302 redirects, not 403.

## 5. Quote (estimate) visibility

`Quote::scopeVisibleTo($userId, $orgId, $members = true)` (Phase 5a):
`quotes.project_id` must be in `Project::visibleTo(user, org)`, and when
`$members` is false the quote must also be authored by the user. Controllers
pass `$members = checkPermission(..., 'estimate_management', 'R')`, so the
rule is: project visible AND (`estimate_management` R OR author). A quote with
`project_id` NULL is visible to nobody, and with `orgId` null nothing is
visible. Creating a quote requires `estimate_management` S inside a project
the user can see (`POST projects/{project}/quotes` and `.../create-from-list/{listId}`).
Writes (`QuoteController::writableQuote`) check visibility and then the level
with the quote's project id. `QuoteAuthorScopeGuardTest` fails if any code
outside `scopeVisibleTo` reads quotes by `user_id`.

## 6. Plan crosswalk

`plan_crosswalk` maps a buyer's plan line code -> WisselBanken product ->
manufacturer part number. Since Phase 4 it is written by `project_id`: create
is `POST projects/{project}/crosswalk`; update/delete are keyed by the row
(`plan-crosswalk/{planCrosswalk}`); uniqueness is (`project_id`, `plan_line_code`)
enforced in validation (the database unique index arrives with 5b). Permission
group `estimate_management`: read R, write F; writes require the project to
be visible and F with the project id (`PlanCrosswalkController::writableProject`).
Reads list only rows of visible projects, and only for users holding R.
The legacy `quote_id` column remains until 5b.

## 7. RFQ and approval routing

Buyer routes (`RfqController`), all mapped to `quote_rfq_management`:
list R, view R, create form S, send S, select a response O, convert F.
Seller routes (`RfqSellerController`): incoming R, respond S, decline S.

- **Project scope.** New RFQs must be created inside a project the user can
  see (`project_id` required; checked with `checkPermission(..., 'quote_rfq_management', 'S', projectId)`).
  Buyer reads are filtered to NULL-project (legacy) RFQs plus visible
  projects; a hidden project's RFQ answers 404. Sellers never see the buyer's
  project: the seller query selects a column list without `project_id`.
- **Status flow.** RFQ: `sent` -> `closed` (when the buyer selects a
  response) -> `converted` (when converted to an order). Recipient row:
  `pending` -> `responded` or `declined`. Response: `pending_review` ->
  `selected` / `rejected`.
- **Seller respond rules.** Allowed only while the RFQ is `sent` and the
  seller's recipient row is `pending`, otherwise 422; also needs an active
  `buyer_seller` relationship with the buyer and passes
  `OrgRelationshipService::sellerAuthorizationError()`. Decline returns 422
  once the RFQ is `converted`. A buyer cannot select a response on a converted
  RFQ (422).
- **Convert to order** (`RfqController::convertToOrder`) needs
  `quote_rfq_management` F (with the RFQ's project) AND `procurement` S. Inside
  one transaction with a row lock: 422 if already `converted` or not `closed`
  or no selected response (this prevents a double conversion). Then
  `ApprovalRoutingService::route($orgId, $requesterId)` decides:
  - the requester is the **sole** user with `approval_authority` A or F in the
    org: `auto_approve`, order status `pending`;
  - other approvers exist (the requester, if one, is removed from the list):
    order status `pending_approval`;
  - **no approver at all**: the conversion is **refused**, nothing is written
    (no order, RFQ stays `closed`). Browser: redirect back with an `error`
    flash; JSON: 422. Message: "Your organization has no one who can approve
    orders. Ask an organization owner to assign an Executive Approver (or
    another role with approval authority), then try again."
  Checkout (`CheckoutController`) follows the same routing and the same refusal.
- Approving or rejecting an order needs `approval_authority` A
  (`OrderApprovalController`); approve sets status `processing`, reject sets
  `cancelled`.
- The dashboard "pending RFQs" panel (`UserWorkspaceController`) shows RFQs
  in status `sent` or `closed` (not yet converted) for users with
  `quote_rfq_management` S, restricted to NULL-project or visible-project RFQs.
  The pending-approvals panel requires `approval_authority` A.

## 8. Backfill command

`php artisan projects:backfill {--dry-run} {--overrides=} {--force} {--allow-live}`
(`app/Console/Commands/BackfillProjects.php`, planner in
`app/Support/ProjectBackfillPlanner.php`) groups quotes that have no project
into projects, moves legacy `quote_id` memberships and crosswalk rows up to
the project, and writes CSV/summary reports to
`storage/app/backfill/projects-entity/<timestamp>/`. Rules: it refuses to run
if the schema is incomplete (exit 2); in the `production` environment it
needs `--force`; a real (non-dry) run needs maintenance mode
(`php artisan down`) or `--allow-live`; exit code 0 = clean, 1 = unresolved
rows or errors, 2 = refused, 3 = verification failed. The `--overrides` CSV has
the header `quote_id,org_id,project_key,project_name`. Run a dry run again
after the real run: it must report nothing left to do.

## 9. Phase 5a and 5b

- **5a (code):** quote visibility is project-only (section 5). Deploying it
  while any `quotes.project_id` is NULL hides those quotes from everyone, so
  the backfill must run first.
- **5b (migrations, `database/migrations/2026_09_25_*`, never run anywhere):**
  `000001` makes `quotes.project_id` NOT NULL (reversible); `000002` deletes
  legacy `project_members` rows with no `project_id`, drops `quote_id`, makes
  `project_id` NOT NULL (`down()` throws: irreversible); `000003` drops
  `plan_crosswalk.quote_id`, makes `project_id` NOT NULL, adds the unique
  (`project_id`, `plan_line_code`) index (`down()` throws). Each refuses to run
  unless the app is in maintenance mode and `PHASE5_BACKUP_CONFIRMED=1`
  (`config('rbac.phase5_backup_confirmed')`), except under the `testing`
  environment, and refuses when the data pre-conditions fail (NULL project
  ids, uncovered active legacy memberships, cross-org or duplicate crosswalk
  rows). Plan: run them only after the backfill and a 14-day soak of 5a. When
  5b lands, `ProjectBackfillTest` and the legacy schema tests are deleted, and
  the backfill command is removed 30 days later.
- `rfq_requests.project_id` stays nullable; 5b does not touch it.

## 10. Migrations and release order

- Migration `2026_09_24_000006` (`project_member_logs`) and
  `2026_09_26_000001` (`rfq_requests.project_id`) must run **before** the code
  that uses them. If the code ships first: buyer RFQ index/create fail with a
  database error (missing column) and membership events are not logged (the
  log write is fail-soft).
- **Trap:** the guarded `2026_09_25_*` files sort between `2026_09_24_*` and
  `2026_09_26_000001`. A plain `php artisan migrate` on a live server throws at
  the first guarded file (outside maintenance mode) and stops, so
  `2026_09_26_000001` would never run. Keep the `2026_09_25_*` files out of the
  release upload (or run the two needed migrations with `--path`).
- Release gates: back up and test-restore the database; run the backfill
  (section 8) before or with any release that contains 5a; do not run 5b in the
  same release.

## 10b. Universal admin (platform admin bypass)

- **Identity**: user ids in env `UNIVERSAL_ADMIN_USER_IDS` (comma list, read into `config('rbac.universal_admin_user_ids')`), and the user must have a verified email. `App\Support\Rbac\UniversalAdmin::is()` is the only check.
- **Where the bypass lives**: `PermissionService::checkPermission` and `Project::scopeVisibleTo` (permission and project visibility), `CurrentOrg` / session org resolution, `OrgSwitchController` (a listed user may switch into any org), and `CheckRole`, which admits listed ids to the `/admin` panel.
- **Audit**: every bypass writes an `rbac_audit_logs` row with outcome `allowed_universal_admin` (deduplicated per request) and a line in the never-pruned `universal-admin-*.log` channel. A failure of either write is caught and falls back to `Log::error`; the bypass decision stays true.
- **Unchanged**: owner-only actions (delete org, transfer ownership) stay owner-only; a listed user is not in any approver pool; delegation is not transitive; users not on the list behave exactly as before.
- **UI**: listed users see 'Platform admin panel' and 'Enforcement mode' links in the user navbar (`$navUniversalAdmin`). The org audit-log Enforcement tab labels rows by stored outcome (Blocked / Would Block / Platform admin) and its banner reflects the real `rbac_mode` and `rbac.enforce_batches`.
- **Deviation**: this departs from plan section 3.4 (no role outside `user_org_roles`) and needs the client's written acknowledgement.

## 11. Known dead code

`OrgRelationshipService::sellerAuthorizationError()` tests
`$sellerOrgType === 'rep_agency'`, but the real organization type slug is
`manufacturer_s_rep_sales_agency`. That branch never runs, so a rep agency
responding to an RFQ is never checked for an active manufacturer principal.
The distributor branch (`str_starts_with($type, 'distributor')`) works. Do not
assume rep-agency seller authorization is enforced until this is fixed.

## 12. Open items (do not silently fix as a side effect of unrelated work)

1. **Matrix / seed decisions pending with the client**: 25 groups vs the plan's
   24; 19 organization types vs the plan's "20"; `api_system` seeded as P3 while
   `SeedMatrixTest` expects P2. These are the two `SeedMatrixTest` failures.
2. **`AuditMiddlewareTest` "enforce mode blocks and records blocked"** expects
   403 for a no-org user but gets the by-design 302 redirect.
3. **Delegation (plan question Q11):** `checkPermission()` evaluates a
   delegate against the principal, but controllers scope project visibility by
   the logged-in user id, so a delegate can pass the check and then get a 404.
   Decision pending.
4. **MariaDB-only behaviour not proven by tests** (the tests run on in-memory
   sqlite): the concurrent-enrol race, `lockForUpdate` semantics, the
   `quotes.user_id` cascade behaviour.
5. Phase 5b tightening (section 9) and the backfill command removal.

## Change log (newest first)

- 2026-09-26 - Rewritten for the Projects entity, project-keyed membership and
  logging, RFQ project scope and approval routing (zero approvers refused),
  server-side checks mirroring the map with the every-route guard test,
  backfill, Phase 5a/5b and release order. Replaced the old "project = quote"
  text and the "Verified corrections to HANDOFF.md" table (that file is gone).
- 2026-09-23 - File created from a verification pass of the original handoff
  document against the plan PDF and the code.

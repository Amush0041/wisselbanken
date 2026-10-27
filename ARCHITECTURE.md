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
Currently 243 entries: 109 `admin`, 75 `read`, 35 `write`, 24 `approve` batch.

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

Two additions from client audit 2, Phase B batch 1 (section 4.6): in enforce
mode a scoped route whose project is not a live project of the acting org is
answered 404, and on the allowed path of a mapped route a request made by a
delegate writes one `delegated_action` row. `RbacAudit` is constructed with
`PermissionService` and `DelegationService`.

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
  enforce mode gets a 302 (known test failure, section 12). Exception: a scoped
  project or quote that is not a live project of the acting org answers 404
  "Not found." for JSON and browser alike (section 4.6, O05).
- **Per-organization enforcement.** The `rbac_settings` row
  `rbac_enforced_org_ids` (JSON array of org ids, no migration; missing, empty
  or invalid = no orgs) makes `RbacAudit::isEnforcing($batch, $orgId)` true for
  those orgs on all batches, ignoring `RBAC_ENFORCE_BATCHES`, while the global
  mode stays audit. It is OR-ed with the global rule, so it can never weaken
  global enforce. Edit it at `POST admin/rbac/enforcement/orgs` (checkRole:admin,
  Admin > RBAC > Enforcement card "Enforced organizations"); each org added or
  removed writes an `rbac_audit_logs` row (`enforcement_changed`, reason
  `enforcement_enabled`/`enforcement_disabled`) plus a `rbac-enforcement-change`
  log line. 60-second cache, same as the mode. Rollback: save an empty selection.
- **Blocked versus Would Block.** `App\Support\Rbac\DenialRecorder` is called
  from the central 403 handler in `bootstrap/app.php` AND explicitly, right before
  the `abort(404)`, at the five controller sites of section 4.6 (the handler
  ignores every status except 403, so a bare 404 would write nothing; the result is
  one `blocked` row with reason `controller_denied`, or, for a below-level actor
  in audit mode, the middleware's `would_block` row upgraded in place, which keeps
  its reason `no_grant`). A controller `abort(403)`
  in audit mode upgrades the same request's `would_block` row to `blocked`;
  if the middleware wrote no row (unmapped route or integrity abort) it inserts
  one with `reason = controller_denied`. So Blocked = a real refusal; Would Block
  = only the middleware would have refused. Responses are unchanged.
- **Outcome values in `rbac_audit_logs.outcome`** (free text, no migration):
  `would_block`, `blocked`, `allowed_universal_admin`, `enforcement_changed`, and
  the business events written by `AuditLog::event()`: `auto_approved` (reason
  `sole_approver order ORD-x`, or `sole_approver_backfill order ORD-x` from the
  backfill command), `self_approved` (reason `sole_approver_self_approval order ORD-x`),
  and the delegation events `delegation_granted` / `delegation_revoked` (user =
  the actor, org = the delegation's org, group `delegation_and_impersonation`,
  level O on the org screens and F on the platform-admin screen, reason `delegate
  <name> (#id) for <name> (#id), <start> to <end>`) and `delegated_action` (user =
  the delegate, reason `acting for <principal> (#id)`, with method, route, matched
  pattern, group, level, project, quote and batch). Both admin audit pages and
  the org Enforcement tab (`?tab=enforcement`) have an explicit label for each;
  an unknown outcome shows its raw value.
- **Unreachable branch.** The `project_unresolved` message branch in
  `RbacAudit::friendlyMessage` is no longer reachable in enforce mode (that reason
  now always ends in the 404 of section 4.6); it is kept, do not rely on it. The
  `no_grant_or_not_project_member` branch of the same function stays reachable
  (same-org non-member).
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
badge), `GET profile`, `PUT profile`, `GET invite/{token}/accept` and
`POST invite/{token}/accept` (invitee-only, section 4.5). Public and guest
routes (catalog, pallet, login, register, `register/invite/{token}`) are
outside the guard.

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
5. 404 for another org's record (client audit 2, O05, section 4.6): the five
   controller sites answer 404 instead of 403 when the record belongs to another
   organization. That 404 is reached only by actors who pass the route's level;
   a below-level actor gets the middleware's 403 in enforce mode, and on
   route-model-bound routes (`{rfq}`, `{order}`, `{userOrgRole}`,
   `{projectMember}`, `{orgRelationship}`) a missing id answers 404 before the
   middleware runs (`SubstituteBindings` runs before `RbacAudit`). Each of those
   controller 404s for another org's record leaves one `blocked` row (reason
   `controller_denied`; the explicit `DenialRecorder::record(request())` call, as
   the old 403 did; for a below-level actor in audit mode the middleware's
   `would_block` row is upgraded in place and keeps reason `no_grant`); a missing
   id (route binding) leaves none.

### 4.5 Who may receive which role (client audit 2, Phase A)

All of this is enforced in the controllers (so it holds in audit mode too) and
leaves `RoleAssignmentService::assign()` untouched: the platform-admin screen,
invite acceptance, delegation and onboarding still call it directly.

- **Membership.** `RoleAssignmentService::isActiveMember($userId, $orgId)` is
  true when the user has an active `user_org_roles` row in that org. A user whose
  rows there are all inactive (removed) is not a member and must be invited
  again. `OrgAdminController::assignRole` refuses non-members (403 "Only existing
  members of your organization can receive a role. Invite the person first.").
  `OrgSettingsController::transferOwnership` refuses a new owner who is not an
  active member, with the same 403 for an unknown e-mail (no account-existence
  hint; the old `exists:users,email` validation is gone). `DelegationController::store`
  refuses a delegate who is not an active member.
- **Role categories.** `RoleAssignmentService::isAssignableByOrg($role, $orgType)`:
  Platform roles are never assignable from an org screen; Manufacturer/Seller
  roles only in orgs whose type is in `config('rbac.onboarding.manufacturer_org_types')`
  (manufacturer, distributor_stocking, distributor_non_stocking); every other
  category is allowed. The same rule feeds the server checks (`assignRole`,
  `generateInvite`, invite acceptance) and the role dropdowns on the Team,
  Overview and Roles pages. The P1/P2 phase filter stays a UI-only filter. The
  platform-admin routes (`Admin\RbacController`) are not restricted, and an org
  owner can still remove a stray Platform assignment. A rep agency
  (`manufacturer_s_rep_sales_agency`) is not in that config list, so it cannot
  assign Sales Rep to its own staff (see section 11).
- **Invites.** `POST org-admin/invite` never assigns directly any more. Every
  e-mail, new or already registered, gets an `org_invites` row (7 days, one use)
  and the same JSON answer (`type: invite`, `email_sent`, `link`; no account
  name). A new e-mail gets the register link; an existing account gets
  `/invite/{token}/accept` and a mail variant that asks it to sign in.
  `Auth\InviteAcceptController` (`GET/POST invite/{token}/accept`, behind
  `auth`, `verified`, `checkRole:user`; not in the route map, listed as open in
  the guard test because the invitee has no membership yet): the signed-in
  user's verified e-mail must equal the invite e-mail (compared case- and
  space-insensitively) or the answer is 404; a used, expired or no longer
  assignable invite is also a 404 (with the "invite invalid" page); the role is
  assigned through `RoleAssignmentService::assign` (SoD still applies; a conflict
  sends the user back to the review page and leaves the invite unused); the
  invite is claimed with a conditional `used_at` update in the same transaction
  as the assignment. At review and at accept the inviter must also still be an
  active member of the invite's org and still pass `user_management` F there
  (judged live on `invite.org_id`, never on the invitee's session org), or be a
  universal admin (`UniversalAdmin::is`: listed and verified; no extra audit row
  at accept); otherwise the invite is the same 404 "invite invalid" page as an
  expired one, and a current admin re-invites. The guest path
  `Auth\InviteController::register` treats an invite whose role is no longer
  assignable as invalid; it does not check the inviter (section 12 item 13).
- **Universal admin** (section 10b) may still assign to non-members, hand
  ownership to an existing non-member and assign restricted roles from the org
  screens; each is logged (`universal_assign_non_member`,
  `universal_transfer_non_member`, `universal_assign_restricted_role`). There
  is no exemption for delegations to non-members or for a universal admin
  accepting an invite as the INVITEE (the verified-e-mail match still applies).
  Separately, an invite CREATED by a listed, verified universal admin stays
  valid at accept without the inviter holding a role in the org.
- **Audit helper.** `AuditLog::event($outcome, $userId, $orgId, $group, $level, $reason, $method?, $routeUri?)`
  writes a business-event row to `rbac_audit_logs` (method and route from the
  request, `CLI` / `artisan <name>` outside a request). It is not fail-soft.

### 4.6 Client audit 2, Phase B batch 1

No migration, no new route, no route map change. Each item is covered by its own
test class (TESTING.md).

- **Separation of Duties in a sole-member org (N01).** `SodService::conflictsFor`
  returns no conflict when the target is the only distinct active member of the
  org (`user_org_roles`, `is_active`), because the solo onboarding bundle
  (Owner, Project Manager, Estimator, Executive Approver) holds roles the rules
  forbid together. As soon as a second member with an active row exists, SoD
  applies again to every NEW assignment; nothing is revoked retroactively. Applies
  to all rules, whatever `sod_conflict_rules` holds. Test: `SodSoloOrgTest`.
- **JSON error bodies (S13).** `App\Exceptions\Handler` (bound in
  `bootstrap/app.php` with `withSingletons`, after `withExceptions`, so the 403
  `rbac_error` renderer still runs) overrides `convertExceptionToArray`: JSON
  errors carry only `message`: 404 "Not found.", other 4xx their own message, 5xx
  and non-HTTP exceptions "Server error.", whatever `APP_DEBUG` says (no
  exception class, file, line, trace or model name). `.env.example` now ships
  `APP_ENV=production`, `APP_DEBUG=false`. HTML error pages still depend on the
  server's `APP_DEBUG` (and a stale `bootstrap/cache/config.php`). Test: `JsonErrorBodyTest`.
- **Partner lookup (D21).** `GET org-admin/connections` no longer lists other
  organizations. `POST org-admin/connections` (throttle `20,1`, keyed by user)
  takes `partner_name`: after the `organization_management` F check it matches
  `organizations.name` exactly; zero matches, several matches or the caller's own
  org all redirect back with the same flash `error` ("No organization matches
  that name. Check the exact spelling with your partner."), the typed name kept.
  The connection is still created active at once and the partner sees it under
  incoming; there is no invitation or accept step (needs a migration). MariaDB
  compares names case-insensitively, sqlite case-sensitively; `organizations.name`
  is not unique. The throttle has no named limiter, so its counter is shared with
  the other unnamed `throttle:N,M` routes of the same user (e.g. e-mail resend).
  Test: `ConnectionsLookupTest`.
- **Another org's record answers 404 (O05).** In enforce mode `RbacAudit::fail()`
  (after writing the `blocked` row, reason unchanged) throws
  `NotFoundHttpException("Not found.")` when the reason is
  `no_grant_or_not_project_member` or `project_unresolved` and the scoped project
  is not a live (not soft-deleted) project of the acting org: another org's
  project or quote, a missing id, a soft-deleted project, and a quote whose
  `project_id` is NULL (a same-org NULL-project quote also answers 404 now; audit
  mode already did). A same-org non-member keeps the 403 with the membership text.
  Five controller sites answer 404 for the org mismatch only:
  `RfqController::authorizeRfq`, `OrderApprovalController::authorizeApprovalAction`
  (a wrong status keeps its 403), `OrgAdminController::removeRole`,
  `destroyConnection` and `removeProjectMember`. `destroyConnection` lets only the
  org that created a relationship (`from_org_id`) deactivate it, so the counterpart
  org also gets 404 (only a crafted request reaches it). Audit mode is unchanged.
  At each of those five org-mismatch branches (and only there) the controller calls
  `DenialRecorder::record(request())` before the `abort(404)`, so a cross-org probe
  still leaves exactly one `blocked` row (reason `controller_denied`), as the 403
  did before; for a below-level actor in audit mode the middleware's `would_block`
  row of the same request is upgraded in place instead and keeps its reason
  `no_grant`;
  a missing id, the own-org success path, the status 403 of the order site and the
  hidden-project 404 in `RfqController::authorizeRfq` (same org, a project the
  actor cannot see) are not recorded as 404s. The remaining 403 of
  `authorizeApprovalAction` now reads "This order is no longer pending approval."
  A delegate probing a foreign id leaves two rows (the `delegated_action` row of
  the middleware plus the denial row). A user acting in their own org can probe
  record ids: each id that exists in another org leaves a Blocked row in THEIR OWN
  Enforcement Log and a missing id leaves none; that is the same signal the old 403
  leaked, and the row holds no id or name of the foreign record (open item 16,
  recommended: accept).
  Tests: `CrossOrgNotFoundTest`, `CrossOrgDenialRowEdgesTest`.
- **Delegation in the audit log (L06).** `DelegationService::audit()` writes
  `delegation_granted` / `delegation_revoked` from `DelegationController::store`
  and `destroy` and from `Admin\RbacController::revokeAdminDelegation`; a revoke
  logs only when the delegation was active (an expired but still active one
  counts), so a later revoke of an already revoked delegation is not attributed to
  the later actor. `RbacAudit` writes `delegated_action` once per allowed request
  on a mapped route when the user is acting as a principal. All writes are
  fail-soft (`try/catch` plus `Log::error`): an audit failure never blocks a
  delegation change or a request. Unmapped (open) routes are not logged. A
  universal admin who also holds a delegation is evaluated as the principal first,
  so such a request logs `delegated_action`. Test: `DelegationAuditTest`.
- **Not built in this batch.** W10 (owner sees every project) is a separate later
  batch and needs the client's written acknowledgement; sections 2, 3 and 5 still
  describe membership-only project visibility.

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
    org: `auto_approve`; the order is created already approved: status
    `processing`, `approved_by` = the requester, `approval_note` "Auto-approved:
    you are the only approver in your organization.", and one `rbac_audit_logs`
    row with outcome `auto_approved` (written inside the order transaction, so an
    audit failure rolls the order back). Before client audit 2 this case was
    created as status `pending`, which nothing ever moved on;
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
  `cancelled`. **Self-approval guard:** an approver cannot approve or reject
  their own order (or the order of the principal they act for as a delegate)
  while another approver exists (403 "You cannot approve|reject your own order
  while another approver is available."). The approver pool is distinct users,
  so one person with several approver roles is still one approver
  (`ApprovalRoutingService::otherApprovers` = pool minus the submitter). When the
  submitter is the only approver they may decide the order themselves, and an
  approve is logged as `self_approved` (reject is not logged). Universal admins
  get no exemption. The approvals list shows "Awaiting another approver" instead
  of the buttons for those orders (`$awaitingOthers`).
- **Stuck `pending` orders.** `php artisan orders:backfill-auto-approval {--dry-run} {--force}`
  (`app/Console/Commands/BackfillAutoApprovedOrders.php`) approves orders left
  in status `pending` with an org by the old behaviour: it selects `status =
  pending`, `org_id` not null, `approved_by` and `rejected_by` null, `user_id`
  not null, and per order (own transaction) sets `processing`, `approved_by =
  user_id`, the same note, and writes an `auto_approved` row with reason
  `sole_approver_backfill order <no>` (method `CLI`). Orders with `org_id` NULL
  are never selected. Idempotent; a real run in production needs `--force`;
  reports go to `storage/app/backfill/orders-auto-approval/<timestamp>/`.
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

## 10c. Customers are organization data

`customers.org_id` (nullable, FK `organizations.id` nullOnDelete, index `(org_id, is_active)`) owns a customer; `customers.user_id` is only the creator. Everyone in the org with the route-map level sees the same customers (list, show, edit, delete, estimate pickers). New customers take `org_id` from `CurrentOrg`, never from request input. A customer with `org_id` NULL (legacy, or left ambiguous by the backfill) is invisible and unattachable to everyone until fixed (fail closed). The project workspace dropdown and `QuoteController::store` use the project's org; `saveEditor` allows the org's customers plus the quote's own current customer only if that one is NULL-org. Universal admins see the customers of whatever org they are in. Permission levels and the route map are unchanged. Backfill: `php artisan customers:backfill-org [--dry-run] [--overrides=csv customer_id,org_id] [--force]` (rules: single org of the customer's quotes, else the creator's single active org, else unresolved and left NULL; idempotent; reports in `storage/app/backfill/customers-org/<timestamp>/`). Release order: migration `2026_10_14_000001` by `--path` (it sorts after the guarded `2026_09_25_*` files), `--dry-run`, real backfill with `--force`, upload the code, run the backfill again for customers created in the gap.

**User deletion guard**: `Admin\RbacController::destroyUser` (the only code path that deletes a `users` row) refuses, with an error flash (same style as the other refusals), when the user created any customer referenced by a quote (`quotes.customer_id`, soft-deleted quotes included): "This user created N customers that are used by estimates ... Reassign or remove those estimates first." The old `customers.user_id` ON DELETE CASCADE is unchanged, so a user with no in-use customers is deleted as before and their unused customers go with them.

## 10b. Universal admin (platform admin bypass)

- **Identity**: user ids in env `UNIVERSAL_ADMIN_USER_IDS` (comma list, read into `config('rbac.universal_admin_user_ids')`), and the user must have a verified email. `App\Support\Rbac\UniversalAdmin::is()` is the only check.
- **Where the bypass lives**: `PermissionService::checkPermission` and `Project::scopeVisibleTo` (permission and project visibility), `CurrentOrg` / session org resolution, `OrgSwitchController` (a listed user may switch into any org), and `CheckRole`, which admits listed ids to the `/admin` panel.
- **Audit**: every bypass writes an `rbac_audit_logs` row with outcome `allowed_universal_admin` (deduplicated per request) and a line in the never-pruned `universal-admin-*.log` channel. A failure of either write is caught and falls back to `Log::error`; the bypass decision stays true.
- **Lifted for listed users only** (each logged as `allowed_universal_admin` with reason `universal_org_delete`, `universal_ownership_transfer`, `universal_delegation_revoke`, `universal_api_token_revoke`): org delete and ownership transfer without being the owner (transfer replaces the sitting owners), revoking another user's delegation (same org) or API token (token owner is a member of the current org), and a quote's `staff_notes` visible to non-authors. Org delete wipes the org's `rbac_audit_logs` rows, so its trail survives in the `universal-admin` log file only. A delegation revoked this way also writes a `delegation_revoked` row (section 4.6), but only when it was still active. Everyone else keeps owner-only and own-record rules.
- **Role assignment (client audit 2)**: a listed user may assign a role to a non-member, hand ownership to an existing non-member, and assign Platform or Seller roles from the org screens; logged with reasons `universal_assign_non_member`, `universal_transfer_non_member`, `universal_assign_restricted_role` (section 4.5). Not lifted: delegating to a non-member, accepting someone else's invite (that is about the INVITEE accepting). Separately, an invite created by a listed, verified user stays valid at accept without the inviter holding a role in the org, while they are listed and verified (section 4.5).
- **Unchanged**: SoD, seller-never-sees-buyer-project scoping, personal data scoped by `user_id` (carts, pallets, saved lists; customers are org data, see 10c), approval business rules (including the self-approval guard of section 7: a listed user cannot approve their own order while another approver exists); a listed user is not in any approver pool; delegation is not transitive; users not on the list behave exactly as before.
- **UI**: listed users see 'Platform admin panel' and 'Enforcement mode' links in the user navbar (`$navUniversalAdmin`). The org audit-log Enforcement tab labels rows by stored outcome (Blocked / Would Block / Platform admin / Auto-approved / Self-approved (sole approver)), shows the row's `reason` in a Details column (underscores as spaces), and its banner reflects the real `rbac_mode` and `rbac.enforce_batches`.
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
   `quotes.user_id` cascade behaviour, two simultaneous accepts of one invite
   (the claim is a conditional `UPDATE ... WHERE used_at IS NULL`; the
   inviter-authority check runs before the claim transaction, so a removal in
   the same milliseconds is not caught), `orders:backfill-auto-approval` on a
   restored production backup, and an order whose `user_id` has no matching
   user (`orders.user_id` has no FK, while `orders.approved_by` and
   `rbac_audit_logs.user_id` do: the command reports that order as `failed`,
   exits 1, and the other orders are still approved).
5. Phase 5b tightening (section 9) and the backfill command removal.
6. **Global role and permission editing** (Verifier C1, CRITICAL, pre-existing).
   `OrgAdminController::updateRolePermission` (`PUT org-admin/roles-list/permissions`)
   and `storeRole` (`POST org-admin/roles-list/create`) are reachable by every
   `user_management` F holder (Organization Owner, Organization Admin,
   Estimator, Manufacturer Admin). Roles and `role_permissions` are global: any
   of them can change the levels of any role in every organization and create
   roles of any category (an Owner can mint a role with `system_administration`
   F and assign it to themselves through the normal Team page, because the
   category is not Platform), and one tenant can change another tenant's
   approver pool. `updateRolePermission` does not call `PermissionMatrix::flush()`,
   so `checkPermission` (cached) and `approverPool` (table read) disagree until
   the cache expires. Decision pending with the human; not fixed by client audit 2
   Phase A.
7. **Stale-session approvals** (Verifier C2, HIGH, pre-existing).
   `OrderApprovalController::index` (:25) and `authorizeApprovalAction` (:88),
   and `CheckoutController` (:211, :231), compare the order's org with
   `CurrentOrg::sessionOrg()` (the raw session value for non-universal admins)
   while the permission check uses `CurrentOrg::id()` (falls back to the first
   active org when the session org is no longer valid). A removed member with a
   stale session who holds an approver role in another org can list, approve and
   reject the old org's orders, and place an order into it (never
   auto-approved), until the session ends. Decision pending with the human; not
   fixed by client audit 2 Phase A.
8. **Invited new hires are never marked verified** (Verifier C3, pre-existing).
   `Auth\InviteController::register` passes `email_verified_at => now()` but the
   column is not in `User::$fillable` (app/Models/User.php:34-41), so the
   account is created unverified, no `Registered` event fires, no verification
   mail is sent, and the user sits behind the `verified` middleware until they
   use "resend". An invited approver counts in `approverPool` but cannot reach
   the approvals page. **Do not fix this with `forceFill` or by adding the
   column to `$fillable`.** The inviter receives the register link, so the
   inviter (or anyone holding it) could register the address with a password of
   their choosing and own a verified account for a mailbox they do not control;
   that defeats the verified-e-mail-equals-invite-e-mail binding of the accept
   route and satisfies the verified-e-mail condition of `UniversalAdmin::is`. The
   safe fix is to fire the normal verification notification and leave the
   address unverified until the mailbox owner clicks it. Today's accidental NULL
   is the safe behaviour. Decision pending with the human; not fixed by client
   audit 2 Phase A.
9. **Delegation deadlock between exactly two approvers** (Verifier B4). With
   pool = {P, D} and an active delegation P -> D, P is blocked by the
   self-approval guard and D is treated as P (`selfIds` includes the principal)
   and gets the misleading "You cannot approve your own order ..." for P's
   order. Needs exactly two approvers; ends on revoke or expiry. Options:
   accept and document, or count a delegate who is an approver in his own right
   as "another approver". Decision pending with the human; not fixed by client
   audit 2 Phase A.
10. **An Owner can strip other approvers and become the sole approver**
    (Verifier B1). The Owner role holds `approval_authority` A and
    `user_management` F, so the Owner can deactivate the other approvers
    (`DELETE org-admin/roles/{userOrgRole}`), then approve their own order
    (logged `self_approved` / `auto_approved`). Same power as before the guard;
    the guard is only as strong as the approver roster. Options: accept
    (logged), refuse removing the last other approver while orders await
    approval, notify on `self_approved`, require a platform admin. Decision
    pending with the human; not fixed by client audit 2 Phase A.
11. **`POST org-admin/invite` is not throttled** (Verifier C5) and sends an
    e-mail with an owner-chosen name and organization name to any address,
    registered or not. Decision pending with the human; not fixed by client
    audit 2 Phase A.
12. **Invite modal script ignores error responses** (Verifier C4). The script in
    `user/org-admin/overview.blade.php` (around :301-331) and `index.blade.php`
    (around :560-590) reads only an `error` key; any non-2xx JSON (403
    `rbac_error`, 422 validation body) falls into the success branch and prints
    `undefined` as the link. The `confirm_existing` / `existing` branches are
    unreachable now and may stay. Decision pending with the human; not fixed by
    client audit 2 Phase A.
13. **Inviter authority on the guest new-hire path** (optional follow-up from
    Verifier A1). Inviter authority is checked at accept for existing accounts
    (section 4.5) but not on the guest path (`Auth\InviteController::register`
    and `show`): a register link outlives its inviter's role, and the inviter
    knows the link. The unverified state of invited new hires (item 8) currently
    keeps that registrant behind the `verified` wall and so masks the gap;
    fixing item 8 must not land without this item. Optional: apply the same rule
    to `register` and `show`; a pending-invites list with revoke on the Team
    page. `InviteInviterAuthorityTest::test_the_guest_register_link_is_deliberately_unchanged`
    pins today's behaviour and must be flipped if this is approved. Decision
    pending with the human; not fixed by client audit 2 Phase A.
14. **Signup verification e-mail (client audit 2, B06(a)).** The code sends exactly
    one `VerifyEmail` per registration (proof test `RegistrationTest`), so a missing
    mail is a server-side cause (mailer config, SMTP, logs). Two facts to know
    before touching it: (1) `Registered` has two `SendEmailVerificationNotification`
    listeners (the app `EventServiceProvider::$listen` and the framework provider's
    own `configureEmailVerification()`); the per-request guard in
    `User::sendEmailVerificationNotification` is what keeps it at one mail, so do
    not remove the guard without removing the duplicate listener; (2) the guard
    marks the user as sent BEFORE the send, and a transport exception is caught
    nowhere: if the mailer throws at signup, `register` ends in a 500 after the
    user, organization and roles exist and no mail is sent (the user recovers
    through login and 'resend'). Hardening (catch and log the failure) is a
    decision pending with the human, not done.
15. **Existence signal on route-model-bound routes (client audit 2, batch 1).**
    `SubstituteBindings` runs before `RbacAudit`, so on routes bound to a model
    (`{rfq}`, `{order}`, `{userOrgRole}`, `{projectMember}`, `{orgRelationship}`) a
    below-level actor in enforce mode gets 403 for an id that exists in ANY org and
    404 for an id that does not exist. The signal is independent of the record's
    org and existed before O05. Fixing it means running `RbacAudit` before
    `SubstituteBindings`, an engine-wide change that needs its own plan and a
    Verifier pass. Decision pending with the human; not fixed.
16. **Cross-org id probing leaves a row in the prober's own log (client audit 2,
    batch 1, Verifier R1).** A user acting in their own org can try record ids on
    the five controller sites (section 4.6). Each id that exists in another org
    answers 404 and leaves one Blocked row in THEIR OWN org's Enforcement Log; an
    id that does not exist leaves none (route binding answers 404 first). So the
    row count tells the prober which ids exist elsewhere. This is the same signal
    the old 403 gave, and the row contains no id, name or other data of the foreign
    record. Recommended: accept. Decision pending with the human; not changed.

## Change log (newest first)

- 2026-10-27 - Client audit 2, Phase B batch 1, verifier amendment V1 (PLAN 10.13):
  the five controller 404s for another org's record call `DenialRecorder` before the
  abort, so a cross-org probe again leaves one `blocked` row (reason
  `controller_denied`; `no_grant` for a below-level actor in audit mode)
  (sections 4.3, 4.4, 4.6); the order site's 403 text is "This order is no longer
  pending approval." No migration, no route or route map change.

- 2026-10-26 - Client audit 2, Phase B batch 1 (N01, S13, D21, O05, L06; B06(a) is
  a proof test with no code change; W10 is a later batch): SoD is skipped for the
  only member of an org; JSON error bodies never carry debug data; connections are
  created from an exact partner name with a throttle and no org list; another org's
  record, project or quote answers 404 (middleware in enforce mode plus five
  controller sites); delegation grants, revokes and delegated actions reach the
  audit log (section 4.6). Open items 14 and 15 added. No migration, no route or
  route map change (the `route-list.json` of the audit pack only gains the
  throttle middleware on POST org-admin/connections).

- 2026-10-26 - Client audit 2, Phase A verifier amendments: an unused
  existing-account invite is valid at review and accept only while its inviter is
  still an active member of the invite's org with `user_management` F (or a
  listed, verified universal admin), otherwise the same 404 "invite invalid"
  page (section 4.5, 10b); open items documented in section 12 (items 4, 6-13).
  No migration, no route or route map change.

- 2026-10-25 - Client audit 2, Phase A (branch `feature/client-audit-2-fixes`):
  role assignment only to active members, no Platform roles and no Seller roles
  in buyer orgs from the org screens, ownership transfer and delegation only to
  members, existing accounts invited and accepted while signed in instead of
  being attached directly (section 4.5); self-approval guard and auto-approved
  orders for the sole approver, with `auto_approved` / `self_approved` audit
  outcomes and the `orders:backfill-auto-approval` command (section 7); audit
  pages show the two outcomes and a Details column. No migration, no route map
  change; two open routes added (`invite/{token}/accept`).

- 2026-10-07 - Per-organization enforcement (`rbac_enforced_org_ids`), real
  controller denials recorded as Blocked via `DenialRecorder`, audit-mode banner
  reworded, `/org-admin/projects` shows only own projects unless
  `project_management` F (section 4.3).

- 2026-09-26 - Rewritten for the Projects entity, project-keyed membership and
  logging, RFQ project scope and approval routing (zero approvers refused),
  server-side checks mirroring the map with the every-route guard test,
  backfill, Phase 5a/5b and release order. Replaced the old "project = quote"
  text and the "Verified corrections to HANDOFF.md" table (that file is gone).
- 2026-09-23 - File created from a verification pass of the original handoff
  document against the plan PDF and the code.

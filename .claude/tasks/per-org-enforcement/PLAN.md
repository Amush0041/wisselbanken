# PLAN: per-organization enforcement, single truth for real blocks, org-admin/projects scoping

Status: for human review (Checkpoint 1). No code written. Production system: with the new setting empty or unset every path must behave exactly as today.

## 1. Goal

Mitch (SprocketWorx, org 2, auditor) sees the global mode on Audit but is still being blocked, and the log says "Would Block" for things that were really refused. After this change: (A) an admin can switch enforcement on for chosen organizations only (all batches) while the global mode stays Audit; (B) every real refusal appears in the Enforcement Log as "Blocked", and "Would Block" means only "the middleware would have refused, nothing else did"; (C) `/org-admin/projects` stops showing every project (and its members) to Viewers.

## 2. What the code does today (verified)

- `RbacAudit::isEnforcing($batch)` (app/Http/Middleware/RbacAudit.php ~line 308) is global: `RbacSetting::get('rbac_mode')` must be `enforce`, then `config('rbac.enforce_batches')` (empty = all batches). It does not know the org. It is only called from `fail()`, which already has `$orgId` (null for `no_org`).
- Controllers (`OrgAdminController::requireOrgLevel` and the same pattern elsewhere) call `abort(403)` in every mode. They write nothing to `rbac_audit_logs`. The 403 handler in `bootstrap/app.php` turns the abort into a redirect-with-flash / JSON / `errors.rbac-403` page and also writes nothing. That is the contradiction Mitch sees: a real block, no log row (or only a `would_block` row from the middleware for the same request).
- Middleware enforce path returns a redirect/JSON itself (no exception), so it never reaches the 403 handler.
- `OrgAdminController::projects()` (Frontend, line ~585) requires `project_management` R, then returns `Project::where('org_id')` with every active member, for anyone at R. Viewers have R. This is the reported leak. `Project::scopeVisibleTo` already gives the member-only rule.
- Universal admin: bypass happens inside `checkPermission` and `scopeVisibleTo`, so a listed user never reaches `fail()`. Platform `users.role = admin` returns early in the middleware. Neither is touched.

## 3. (A) Per-org enforcement

**Storage: no migration.** `rbac_settings` is a key/value table (`key` string PK, `value` text). New row key `rbac_enforced_org_ids`, value a JSON array of ints, e.g. `[2]`. The row is created by `RbacSetting::set()` (`updateOrCreate`), so nothing to migrate. Missing row, empty string, `[]`, invalid JSON, non-int items: all parse to an empty list (never throw).

**Cache.** `RbacSetting::get` caches 60 s per key (`Cache::remember`); `set` forgets the key on the server that wrote it. Same latency as the mode toggle: a change takes up to 60 s on other servers if the cache store is per-server. The page text will say so (already does for the mode). Accepted; no new cache code.

**Read.** Add `RbacSetting::enforcedOrgIds(): array` (parse as above). Change `RbacAudit::isEnforcing(?string $batch, ?int $orgId)`; `fail()` passes `$orgId`. Rule:
```
global enforce AND batch rule (exactly as today)   -> true
OR  $orgId !== null AND in_array($orgId, enforcedOrgIds)  -> true (all batches, batch ignored)
else false
```
Org source is the `$orgId` the middleware already resolved with `CurrentOrg::id`. For `no_org` (org null) only the global rule applies. Global enforce can never be weakened by the set (OR only), so other orgs and production default are unchanged. The `rbac_audit_logs` row for an enforced org is `outcome = blocked` (existing code, now reachable per org).

**Edit.** Admin > RBAC > Enforcement page (`RbacController::enforcement` + `admin/rbac/enforcement.blade.php`): new card "Enforced organizations" with a multi-select of all orgs (checked = enforced) and a Save button. New route `POST admin/rbac/enforcement/orgs` (`admin.rbac.enforcement.orgs`) -> `RbacController::updateEnforcedOrgs`. It sits in the existing admin route group (`checkRole:admin`, which admits platform admins and listed universal admins). Validation: `org_ids` array of ints, each `exists:organizations,id`. Save = `RbacSetting::set('rbac_enforced_org_ids', json_encode(sorted unique ids))`. Saving an empty selection stores `[]` (this is the "off" state).

**Logging each change.** For every org added or removed (diff old vs new) write one `rbac_audit_logs` row: `user_id` actor, `org_id` that org, `permission_group system_administration`, `required_level F`, `batch admin`, `outcome enforcement_changed`, `reason enforcement_enabled` / `enforcement_disabled`, method/route_uri of the request; plus `Log::info('rbac-enforcement-change', [...])`. Both writes in try/catch with `Log::error` fallback (same rule as `UniversalAdmin::recordBypass`; a log failure must not block the save). Views get a label for the new outcome ("Setting changed"); unknown outcomes already render raw, so nothing breaks if a view is missed. Note: an org delete wipes that org's rows (existing behaviour), the Log line survives.

**Labels/banners.**
- Org audit-log banner (`OrgAdminController::auditLog` passes `orgEnforced = in_array(org id, enforcedOrgIds)`; `audit-log.blade.php` line ~215): first branch, if `orgEnforced`: "Enforcement ON for this organization (all batches): denied requests are recorded as Blocked." Otherwise the existing three branches, unchanged. The "No users are currently blocked" sentence in the audit branch is reworded (see B: controller checks do block in every mode).
- Admin enforcement page and `admin/rbac/index`: show the list of enforced orgs next to the global mode. Global audit-logs filter unchanged.
- Row badge logic already follows the stored outcome; no change.

**Interplay.** Universal admin and platform admin: unchanged (never reach `fail()`). Batches: enforced orgs ignore `enforce_batches` by design ("all batches"). Other orgs: identical code path, set does not contain them. Unset setting: `enforcedOrgIds()` returns `[]`, `isEnforcing` result identical to today (proven by test).

**Behaviour warning for the client.** Switching org 2 on will newly block anything the middleware would have blocked and no controller refuses. Before enabling, run a count of org 2 `would_block` rows grouped by route/reason and review it with Mitch.

## 4. (B) Single truth for real blocks

Options weighed:
1. Do nothing, only relabel. Rejected: the log still hides real denials.
2. Log in every controller `abort`. Rejected: dozens of call sites, easy to miss one, violates "match existing patterns".
3. **Record in the central 403 handler (bootstrap/app.php), recommended.** One place, covers every `abort(403)` including `requireOrgLevel`, `requireProjectFull`, integrity aborts and `CheckRole`.

Design of option 3 (handler already filters to 403 and an authenticated user; recording happens before building the response, inside try/catch so a DB failure never changes the response):
- The middleware `fail()` stores the id of the row it wrote in `$request->attributes` (`rbac_audit_row`). If, in audit mode, the same request later 403s in a controller, the handler **updates that row in place** to `outcome = blocked` (keeps group/level/batch/reason, sets `reason` unchanged). So no duplicate, and `would_block` then truly means "middleware-only".
- If there is no middleware row (route unmapped, or middleware passed and a controller refused, e.g. data-integrity or SoD abort): insert one row: user, org (`CurrentOrg::id`), method, route uri, matched pattern/group/level/batch from the map when the route is mapped (else null), `outcome = blocked`, `reason = controller_denied`.
- Enforce-mode middleware blocks return a response, never an exception, so no double row.
- Exclusions: guests (handler already requires a user); universal admin / platform admin 403s (they should not occur; if one does for an integrity abort, still record, it is a real denial); same-request guard so one request writes at most one row; skip when the 403 comes from the middleware's own JSON (not an exception).
- Noise: only denials, one insert each, no insert on success. A user hammering a forbidden URL adds one row per hit, the same as the middleware does today in audit mode. No new index needed. Accepted.
- Risk to non-listed behaviour: response is unchanged byte for byte; only an extra insert is added. The only user-visible change is more `blocked` rows and fewer duplicate `would_block` rows in the log. Table count assertions in existing tests (AuditMiddlewareTest, AuditFindingsRound2Test "database unchanged" lists the RBAC tables, AuditLogLabelsTest) may need updating, because a refused request now adds a row. Test-impact list is in the QA step.
- Put the logic in a small class `App\Support\Rbac\DenialRecorder` (one static method, called from the handler) rather than a closure in `bootstrap/app.php`, so it can be unit tested.

Result: Enforcement Log = every real denial as Blocked; Would Block = middleware-only rows; org banner text corrected accordingly. Mitch's two requests are met.

## 5. (C) /org-admin/projects rule

Recommended rule in `OrgAdminController::projects()`: keep `requireOrgLevel(project_management, R)`. Then:
- if `checkPermission(user, org, project_management, F)` (people who manage membership, `$canManageProjects`): all org projects, as today;
- otherwise: `Project::visibleTo($userId, $orgId)` only (active member of the project). The members list per project is built only for projects in the result. The `$members` dropdown (org users) is only needed for managers; non-managers get an empty collection (no email list leak).
Universal admin: `scopeVisibleTo` already returns all org projects; unchanged. Quote counts: `withCount('quotes')` unchanged.
Open question on F-vs-O (see section 9). Same review applies to `addProjectMember`/`removeProjectMember` (already F), no change.

## 6. Permission mapping

| Route | Group | Level | Batch | Project scoping |
|---|---|---|---|---|
| NEW `POST admin/rbac/enforcement/orgs` | system_administration | F | admin | no (also behind `checkRole:admin`) |
| `GET org-admin/projects` (existing, unchanged map) | project_management | R | read | no; data scoping added in controller (member-only unless F) |
| all others | unchanged | | | |

The new route needs a map entry (the guard test `AuditFindingsRound2Test` requires every `admin/...` mapped route behind `checkRole:admin`, and every authenticated route in the map). Controller-side check: `updateEnforcedOrgs` relies on `checkRole:admin` like `toggleMode`; no org-level check applies (platform scope). No new permission_group or role: no deviation from the 25-group / 49-role baseline. No migration, so no migration order issue.

## 7. File list

Touch: `app/Models/Rbac/RbacSetting.php` (enforcedOrgIds); `app/Http/Middleware/RbacAudit.php` (isEnforcing signature, store row id); `app/Support/Rbac/DenialRecorder.php` (new); `bootstrap/app.php` (call recorder); `app/Http/Controllers/Admin/RbacController.php` (enforcement data, updateEnforcedOrgs); `routes/web.php` (+route); `config/route_permission_map.php` (+entry, count 242 -> 243, admin batch 108 -> 109); `app/Http/Controllers/Frontend/OrgAdminController.php` (auditLog passes orgEnforced; projects scoping); `resources/views/admin/rbac/enforcement.blade.php`, `admin/rbac/index.blade.php`, `admin/rbac/audit-logs.blade.php` (label/filter option), `user/org-admin/audit-log.blade.php` (banner, label), `user/org-admin/projects.blade.php` (only if members/dropdown vars need guarding). Docs: ARCHITECTURE.md (4.2, 4.3, 4.4 accepted-behaviour item 3, 10b banner line, route counts, change log), CLAUDE.md (RBAC enforcement bullet: per-org set), HANDOVER-CLIENT.md, TESTING.md (baseline unchanged), `.claude/tasks/per-org-enforcement/PROGRESS.md`.
Tests (new): `tests/Feature/Rbac/PerOrgEnforcementTest.php`, `DenialRecordingTest.php`, `OrgAdminProjectsScopeTest.php`. Existing tests that count log rows may need edits (listed in D7).

## 8. Test plan

PHPUnit-style classes; both Pest and a new permission_group are absent.
- PerOrgEnforcementTest: global audit + org 2 in set: mapped route denial for org 2 user returns the enforce response and writes `blocked`; same user in another org (not in set) passes through with `would_block`; global enforce unchanged; batch outside `enforce_batches` still blocked for set org; empty / missing / `[]` / invalid JSON / `"abc"` / non-int items = identical to today (run the existing audit-mode and enforce-mode scenarios with the setting unset); `no_org` unaffected by set; universal admin and role=admin unaffected with org in set; non-listed unaffected. Admin save: platform admin can save, ids validated (`exists`), non-admin and org admin get 403, empty selection stores `[]`, one `enforcement_changed` row per added/removed org, log failure does not block save, cache refreshed on save. Banner: per-org text for org 2, audit text for org 3 in the same run; enforce+batches text unchanged.
- DenialRecordingTest: controller `requireOrgLevel` 403 in audit mode with a mapped route: exactly one row, `blocked` (middleware `would_block` row upgraded, not duplicated); unmapped route abort: one `controller_denied` row; enforce-mode middleware block: one row, unchanged; allowed requests: zero rows; response (status, redirect, flash, JSON body) identical with and without the recorder; DB failure in recorder (table dropped) still returns the same 403; guest 403 writes nothing; two aborts in one request: one row.
- OrgAdminProjectsScopeTest: Viewer member of project A sees only A and A's members, not B; Viewer with no membership sees none and no org-user dropdown; project_management F sees all; org 2 user from another org sees nothing of org 1; universal admin sees all; other-org project members not leaked; counts correct.
- Mutation targets: `in_array` org check (remove -> org 2 not blocked; invert -> other orgs blocked); `orgId !== null` guard; the OR (replace with AND); in-place upgrade vs duplicate; recorder try/catch; F-branch in `projects()`; `visibleTo` filter; `exists` validation; `checkRole:admin` on the new route.
- No-regression: full suite must equal the baseline, the 4 known failures only: ExampleTest, AuditMiddlewareTest "enforce mode blocks and records blocked", SeedMatrixTest "seeds expected counts" and "phase distribution" (1224 passed / 4 failed at last run; passed count rises with new tests). Known flake: AuditMembershipLogTest newest-first (timestamp ordering), not in the baseline.

## 9. Risks

- Org 2 enforcement will newly block middleware-only gaps; review its `would_block` rows first (3, warning). Rollback is saving an empty selection (60 s).
- Row-count assertions in existing tests will shift (B); the guard test "database unchanged" tables list includes RBAC tables, so it must exclude `rbac_audit_logs` for denied calls or assert only on non-log tables. QA decides, Architect approves.
- Cache: per-server cache stores delay a change up to 60 s (same as the mode toggle).
- Known open items in ARCHITECTURE.md section 12: audit middleware redirect (302 not 403 for browsers in enforce mode): the per-org block uses the same response, so org 2 browser users get the redirect-with-flash, not a 403; this plan does not change it. Rep-agency authorization bug and api_system phase: not touched. Org type count: not touched.
- `reason controller_denied` and outcome `enforcement_changed` are new string values in a free-text column (no enum, no migration).
- Org delete leaves a stale id in the set (harmless, filtered on read against nothing; cleaned on next save).

## 10. Step breakdown (Lead Developer logs each in PROGRESS.md)

- D1 RbacSetting::enforcedOrgIds + RbacAudit::isEnforcing(batch, orgId) + fail() passes org + stores row id.
- D2 Admin save: route, map entry, controller method, change logging, enforcement page card, index list.
- D3 Banner/labels: auditLog passes orgEnforced, audit-log.blade.php banner and wording, `enforcement_changed` label, global audit-logs filter option.
- D4 DenialRecorder + bootstrap/app.php hook (upgrade-in-place, insert, try/catch).
- D5 OrgAdminController::projects scoping (and view guard).
- D6 Docs (ARCHITECTURE.md, CLAUDE.md, HANDOVER-CLIENT.md, TESTING.md).
- D7 Adjust existing tests that count rows; then QA writes the three new test classes, runs mutation proofs, full suite.
- Then: Architect compliance pass (REVIEW.md), QA, one Verifier (permission engine touched), Checkpoint 2. No migration to run; deploy is code plus config:clear, then set org 2 via the admin page.

## 11. Checkpoint 1: decisions needed (recommended default first)

1. Storage as a JSON row in `rbac_settings`, no migration? Default: yes.
2. Enforced org ignores `enforce_batches` (all batches)? Default: yes, as requested.
3. Who edits: `checkRole:admin` (platform admin and listed universal admins) via the Enforcement page? Default: yes.
4. Record each set change as an `rbac_audit_logs` row (`enforcement_changed`) plus a log line? Default: yes.
5. Hook denial recording in the central 403 handler, upgrading the middleware's `would_block` row in place? Default: yes.
6. Record controller denials for unmapped routes too (`controller_denied`)? Default: yes.
7. Org 2 go-live: review its `would_block` rows with Mitch before switching it on? Default: yes.
8. `/org-admin/projects`: project_management F sees all, everyone else only their own projects? Alternative: O or above sees all. Default: F.
9. Audit-mode banner wording: replace "No users are currently blocked" with text that says controller-level checks already refuse some actions in every mode? Default: yes.
10. Accept existing row-count test edits (D7) and the 302-redirect behaviour for browsers in enforce mode (open item stays)? Default: yes.

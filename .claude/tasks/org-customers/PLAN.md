# PLAN: org-customers (shared customers per organization + Estimator access)

Branch base: release/1.1-prod. Mode: STRICT (schema change on live data). Status: awaiting Checkpoint 1.
Hard rules: nothing is migrated or backfilled against the local MySQL (.env DB is real). Tests use in-memory sqlite.
No new permission_group and no new role: nothing deviates from the 25-group / 49-role baseline.

## 1. Goal

Today a customer belongs to the user who created it (`customers.user_id`), so a second SprocketWorx (org 2) user sees no customers and cannot save an estimate ("Please select customer"). After this change a customer belongs to an organization (`customers.org_id`). Everyone in the org who holds the right level sees the same customers, can attach them to estimates in that org's projects, and no other org can ever see or attach them. `user_id` stays as "created by". Second request (Estimator access) is investigated in section 5: it is most likely a data fix on production, not a code or seed change.

## 2. Data model (A)

### 2.1 Migration `2026_10_14_000001_add_org_id_to_customers_table.php`
- `up()`: `Schema::table('customers')`: add `unsignedBigInteger org_id` NULLABLE after `user_id`; FK `customers_org_id_foreign` -> `organizations.id` with `nullOnDelete()` (an org that owns customers can still be deleted; its customers become legacy-NULL = invisible, never re-homed); index `customers_org_id_is_active_index (org_id, is_active)`. No data change, no drop, no NOT NULL. Guard with `Schema::hasColumn` so a re-run is harmless.
- `down()`: drop FK, index, column (safe: additive).
- Tightening to NOT NULL is deliberately NOT part of this plan (later, after a soak, like Phase 5b).
- Model: `Customer` add `org_id` to `$fillable`; `organization()` belongsTo; replace `scopeForUser` with `scopeForOrg(Builder, int $orgId)` -> `where('customers.org_id', $orgId)`. Remove `forUser` (grep shows it is used only in CustomerController).

### 2.2 Backfill: new artisan command `customers:backfill-org`
Recommend a command, not a migration step: a migration cannot be dry-run, cannot write reports, and a failed rule would block deploy. Pattern copied from `projects:backfill`.
Options: `--dry-run`, `--overrides=<csv customer_id,org_id>`, `--force` (required when APP_ENV=production), exit codes 0 clean / 1 unresolved rows remain / 2 refused (column missing). Reports (CSV + summary) to `storage/app/backfill/customers-org/<timestamp>/`. No maintenance mode needed (additive, only fills NULLs).
Idempotent: every write is `UPDATE customers SET org_id=? WHERE id=? AND org_id IS NULL`; rows with an org are never touched; a second run reports "nothing to do". Includes soft-deleted customers (withTrashed) so a restore later is consistent. Chunked, one transaction per chunk, no timestamps changed (`updated_at` untouched via query builder).

Deterministic rule, first match wins, per customer:
1. Orgs of the quotes using it: `quotes.customer_id = c.id` -> `quotes.project_id` -> `projects.org_id`, soft-deleted quotes and projects included. Exactly one distinct org -> that org. (This is the strongest evidence; it also fixes a creator who has since moved.)
2. Two or more distinct orgs (customer used across orgs) -> UNRESOLVED, report only. Never guess: a wrong guess leaks a customer to the wrong org.
3. No quote evidence: creator (`user_id`) has exactly one active `user_org_roles` org -> that org.
4. Creator has several active orgs, or none (inactive roles only, no org) -> UNRESOLVED.
5. Overrides CSV wins over rules 1-4 only for listed ids (org must exist; refuse otherwise).
Edge cases to list in the report: quotes whose `project_id` is NULL (ignored as evidence); customer with quotes in one org but creator active only in another (resolved by rule 1, warning row); creator deleted (`customers.user_id` is `ON DELETE CASCADE` and platform "delete user" hard-deletes, so orphans should not exist; a count check is in the pre-flight); customers with no quotes and creator in several orgs (the likely hard cases, human decides).
Unresolved rows stay `org_id = NULL` = invisible to everyone (fail closed, see 3.2). Quotes already pointing at them still show the name on the quote.

### 2.3 Pre-flight SQL for the human (read-only, run on production after backup)
```sql
-- totals
SELECT COUNT(*) total, SUM(deleted_at IS NULL) live, COUNT(DISTINCT user_id) creators FROM customers;
-- orphan creators (expect 0)
SELECT c.id FROM customers c LEFT JOIN users u ON u.id=c.user_id WHERE u.id IS NULL;
-- customers whose quotes span 0 or 2+ orgs (rule 1 does not apply)
SELECT c.id, c.user_id, COUNT(DISTINCT p.org_id) AS quote_orgs
FROM customers c LEFT JOIN quotes q ON q.customer_id=c.id LEFT JOIN projects p ON p.id=q.project_id
GROUP BY c.id, c.user_id HAVING quote_orgs <> 1;
-- creators with 0 or 2+ active orgs (rule 3 does not apply)
SELECT c.id, c.user_id, COUNT(DISTINCT r.org_id) AS active_orgs
FROM customers c LEFT JOIN user_org_roles r ON r.user_id=c.user_id AND r.is_active=1
GROUP BY c.id, c.user_id HAVING active_orgs <> 1;
-- a customer that is in BOTH lists above is a manual-decision row
```

### 2.4 Migration-order trap
The new file sorts AFTER the guarded `2026_09_25_*` files. A plain `php artisan migrate` on production stops at the first guarded file (not in maintenance mode) and never reaches it, exactly the `2026_09_26_000001` trap in ARCHITECTURE.md section 10. So: keep the `2026_09_25_*` files out of the upload, or run `php artisan migrate --path=database/migrations/2026_10_14_000001_add_org_id_to_customers_table.php --force`. The Developer must not run any migrate locally (tests only).

## 3. Code that reads or writes customers (B)

### 3.1 Switch to org scope (org = `CurrentOrg::id(Auth::id())`)
| File / spot | Today | Change |
|---|---|---|
| `Frontend/CustomerController::list` (L26) | `forUser(Auth::id())` | `forOrg($orgId)` |
| `::resolveUserCustomer` (L209), used by show / update / destroy | `forUser` | rename `resolveOrgCustomer`, `forOrg`; foreign or NULL-org id -> 404 |
| `::store` (L116) | `user_id = Auth::id()` | `user_id = Auth::id()` (creator) AND `org_id = $orgId` (from `requireOrgLevel`'s org, never from request) |
| `::requireOrgLevel` | returns void | return the org id so callers reuse it |
| `Frontend/QuoteController::store` (L378) | `where user_id = Auth::id()` | `where org_id = $project->org_id` (project already proven visible by `creatableProject`) |
| `::saveEditor` (L702-706) | `user_id = me OR id = quote.customer_id` | `org_id = quote's project org` OR (`id = quote.customer_id` AND `org_id IS NULL`, so an old estimate can be re-saved untouched but cannot be re-pointed to another org's customer). Load `quote->project` for the org. |
| `::getCustomersForEstimate` (L788) | `where user_id` | `forOrg(CurrentOrg::id)`; null org -> empty list |
| `Frontend/ProjectWorkspaceController::show` (L72) | `where user_id` | `where org_id = $orgId` (the `$project->org_id`) |
| `::duplicate` (L635) | copies `customer_id` | unchanged (same project, same org); no check needed |
| `::index/details/pdf/email` (L61, 217, 960, 983) | relation load only | unchanged; the quote is already gated by `Quote::visibleTo`, and the customer relation is read-only display |
| `QuotePdfPresenter`, views (`user/customers/index`, `project-workspace/show`, `quotes/index`, pdf-template, dashboard, sidebar, orderDetail, viewPallet) | display only | no change expected; Developer greps views for `user_id`-based customer logic and reports |
| `exists:customers,id` validation (QuoteController L354, L678) | existence only | keep; the scoped `firstOrFail` afterwards returns 404 for foreign ids (same answer as a missing id after the 422 passes). Optional hardening: `Rule::exists('customers','id')->where('org_id', ...)`; default: not needed. |
| plan crosswalk, RFQ, orders | no customer use found | none |

### 3.2 Legacy NULL-org rows: fail closed (recommended)
`org_id IS NULL` is visible in no list, cannot be selected, shown, edited or deleted by anyone. Falling back to "creator" would show a multi-org creator's customer in every org they belong to (a cross-org leak) and re-creates the problem being fixed. This matches Phase 5a (NULL project = invisible). Unresolved rows are fixed by the overrides CSV and a second backfill run, which is why the backfill is re-runnable.

### 3.3 IDOR and universal admin
- Org A can never read or attach org B's customer: every read, write and attach is `where org_id = <org of the request / project>`; the id from the request is never trusted, and `org_id` is not in any validated payload (so it cannot be mass-assigned).
- Universal admin: no special code. Their org is whatever `CurrentOrg` resolves (they may switch into any org), so they see that org's customers; `checkPermission` bypass is unchanged. They no longer see "their own" customers in unrelated orgs. State this in the docs.
- Multi-org users: the customer list follows the active org (switcher), like projects.

## 4. Permission semantics (C)

Current map (`config/route_permission_map.php` L154-162, controllers mirror it): `GET customers`, `customers/list`, `customers/{id}`: `quote_rfq_management` R (read). `POST customers`: `user_management` S (write). `PUT customers/{id}`: `user_management` O (write). `DELETE customers/{id}`: `user_management` F (approve). `GET quotes/customers`: `estimate_management` R.
- Recommendation: NO map change, no new group. Anyone in the org holding the map's level may edit/delete any shared customer; the creator is no longer special (`user_id` is audit information only; `CustomerActivityLog` already records who did what and is now visible org-wide).
- Report (not fixed here): the mapping is odd. Creating a customer needs `user_management` S, a staff-admin group; roles that build estimates but lack `user_management` (e.g. `project_engineer`: estimate O, no user_management) cannot create customers, and read needs `quote_rfq_management` R while the estimate picker needs `estimate_management` R. The client asked earlier for a Customer/Account group; still pending, belongs to the open matrix decisions (ARCHITECTURE.md section 12 item 1). The seed Estimator has user_management F and quote_rfq S, so it works for them.
- Side effect to flag: delete is a soft delete and `Quote::customer()` is a plain belongsTo, so a shared customer deleted by one user makes other users' quotes show "Customer"/N/A. Same behaviour as today for the creator; with sharing it is more likely. Default: accept and document; alternative: refuse delete while quotes use it (needs a human decision).
- No new `route_permission_map.php` entries or edits (no new routes). Map count stays 243.

## 5. Matrix item: Estimator (D)

Findings from code:
- Seed (`role_permission_matrix.php`): estimator = estimate_management F, procurement F, project_management F, quote_rfq_management S, user_management F. So "give Estimator Project Management S" would LOWER the seed. Do not do that.
- Opening a project (`GET projects/{project}`, `ProjectWorkspaceController::show`) needs `project_management` R AND an active `project_members` row (`Project::visibleTo`). Missing either gives 404/403/redirect-with-denial.
- "Create lists" = `POST save-shopping-list` (`PalletProductController::saveShoppingList`) needs `project_management` S, org-wide, NOT project-scoped; viewing lists (`ListController`) needs R, editing O. Lists are personal (`saved_lists.user_id`).
- If production matched the seed, the Estimator could do both lists actions already. They cannot, so production almost certainly differs: the user's role row has `project_management` below S (the symptom "cannot even open a project" suggests none or below R), or the user holds a different/custom role, or they are in the wrong org, plus possibly no `project_members` row for the project.
- Recommendation: NO code change, NO seed change, NO migration. Remedy is data-only: (1) run the diagnostic SQL below; (2) if the role row is below S, raise it to exactly S in Admin > RBAC > Role Designer (leave it alone if already >= S); (3) if a membership is missing, add the user with the project Members panel (`ProjectMember::enrol`, logged), requires `project_management` F. Never re-run the seeder: `RolePermissionSeeder` uses `updateOrCreate` and would overwrite every production customization. If the client really wants the seed to say S for Estimator that is a separate, explicit decision (it would lower seeded F; reject by default).

Diagnostic SQL (read-only, production):
```sql
SELECT id, slug, name FROM roles WHERE slug='estimator';
SELECT pg.slug, rp.access_level FROM role_permissions rp
  JOIN permission_groups pg ON pg.id=rp.permission_group_id
  WHERE rp.role_id=<estimator role id>
    AND pg.slug IN ('project_management','estimate_management','quote_rfq_management','user_management');
-- which role(s) does the test user really hold in org 2?
SELECT r.slug, uor.is_active, uor.org_id FROM user_org_roles uor JOIN roles r ON r.id=uor.role_id WHERE uor.user_id=<id>;
-- membership for the project the estimate is in
SELECT pm.project_id, pm.org_id, pm.is_active, p.org_id AS project_org, p.deleted_at
FROM project_members pm JOIN projects p ON p.id=pm.project_id WHERE pm.user_id=<id>;
SELECT q.id, q.project_id FROM quotes q WHERE q.id=<estimate id>;
```
Expected reading: level < S or no row => Role Designer; level >= S and no member row => enrol; both fine => report back, this is a new bug (route back to Architect). Check the Role Designer save refreshes the matrix cache (`PermissionMatrix`); if not, note the wait/flush needed.

## 6. File list
Create: `database/migrations/2026_10_14_000001_add_org_id_to_customers_table.php`; `app/Console/Commands/BackfillCustomerOrg.php` (+ small planner class `app/Support/CustomerOrgBackfillPlanner.php` only if the rule logic needs unit tests, otherwise inline); `tests/Feature/Rbac/OrgCustomersTest.php`; `tests/Feature/Rbac/CustomerOrgBackfillTest.php`.
Touch: `app/Models/Customer.php`; `app/Http/Controllers/Frontend/CustomerController.php`; `app/Http/Controllers/Frontend/QuoteController.php`; `app/Http/Controllers/Frontend/ProjectWorkspaceController.php`; tests that hand-build the `customers` table or insert customers: `tests/Feature/Rbac/ProjectTestCase.php` (add `org_id` column), `ProjectWritePathsTest.php` (L80 helper), `ProjectCheckpoint2Test.php` (L55 helper, L496-533 asserts), `ProjectReadPathsTest.php` (L582, L726), `AuditFindingsRound2Test.php` (L288-317 own schema); docs `ARCHITECTURE.md` (sections 5/10/10b/12 and change log: customers are org data; remove "customers" from the personal-data list in 10b; add backfill + release order), `CLAUDE.md` (one line), `HANDOVER-CLIENT.md`, `TESTING.md` only if the baseline changes. `config/route_permission_map.php`: no change.

## 7. Tests (E)
PHPUnit, sqlite memory. Cases for `OrgCustomersTest`:
- Second user (same org, different creator) lists, shows, attaches the first user's customer in create and in saveEditor; allowed path for each map level (R list, S create, O edit, F delete) and denied path (wrong role, `viewer_read_only`, wrong org, no org).
- Org B user: list excludes org A's customers; show/update/destroy -> 404; attach to own project's estimate -> 404; `GET quotes/customers` and workspace dropdown exclude them. Multi-org user: list follows the switched org.
- New customer gets `org_id = CurrentOrg`, `user_id = creator`; payload `org_id` ignored.
- NULL-org customer: invisible everywhere, not attachable; existing quote with a NULL-org customer can still be saved unchanged (and cannot be re-pointed to another org's NULL/foreign customer).
- Universal admin sees the active org's customers only.
- Backfill: each rule 1-5, cross-org customer unresolved, multi-org creator unresolved, overrides, `--dry-run` writes nothing, rerun is a no-op (idempotent), already-set org never changed, soft-deleted included, refuses when column missing (exit 2), production needs `--force`.
Mutation targets (scratch copy): drop the `org_id` filter in each of list, resolveOrgCustomer, QuoteController::store, saveEditor, getCustomersForEstimate, workspace dropdown; flip `org_id` source to request input; remove `org_id` on create; backfill rule 1 uses user instead of quote org; backfill update without `IS NULL`.
No-regression: other orgs' flows unchanged; customers, quotes, pdf tests keep passing after helpers get `org_id`. All-routes guard (`AuditFindingsRound2Test`): no route is added or re-mapped, so its loops are unaffected, but its hand-built `customers` table (L288) and fixture inserts (L296: user_id only) need `org_id`, and the "database unchanged" table list already includes `customers`. Baseline: the 4 known failures (ExampleTest, AuditMiddlewareTest enforce-blocks, SeedMatrixTest x2) must stay exactly those; suite was 1324 passed / 4 failed.

## 8. Task breakdown (Developer logs to PROGRESS.md)
- D1 Migration + `Customer` model (fillable, relation, `forOrg`, drop `forUser`). Tests schema: add `org_id` to `ProjectTestCase` and other hand-built tables; no run against MySQL.
- D2 `CustomerController` to org scope (list/show/update/destroy/store, `requireOrgLevel` returns org).
- D3 `QuoteController` store, saveEditor, getCustomersForEstimate; `ProjectWorkspaceController` dropdown. Grep views and PDF for other `user_id`-based customer logic; report.
- D4 `customers:backfill-org` command (+ planner), reports, overrides, exit codes.
- D5 Update existing tests/helpers (section 6) so the suite is green except the 4 known.
- D6 Docs (ARCHITECTURE.md, CLAUDE.md line, HANDOVER-CLIENT.md release steps + pre-flight SQL + matrix diagnostics).
- D7 Full suite run, `php artisan view:cache` + `view:clear`. QA then writes section 7 tests and mutation proofs; Architect compliance pass before QA; Verifier once (schema + IDOR).

## 9. Deploy order (production)
1. Backup + test restore. 2. Human runs pre-flight SQL (2.3). 3. Run only the new migration (`--path`, see 2.4). 4. `customers:backfill-org --dry-run`, review CSVs, supply overrides for unresolved. 5. Real run `--force`. Backfill must precede the new code: otherwise every customer disappears (same trap as Phase 5a). 6. Upload code. 7. Re-run the backfill once (idempotent) to catch customers created by old code in the gap; confirm "nothing left". 8. Estimator SQL diagnostics and Role Designer fix (independent of the deploy). Rollback: revert the code; the column is harmless and can stay.

## 10. Risks / open items touched
- Seed/production drift (matrix) is real and decided by SQL, not assumed.
- Cross-org and ambiguous customers need a human decision per row; fail closed until then.
- Known open items: not touched (rep-agency auth, api_system phase, org type count, audit middleware redirect). The pending Customer/Account permission group stays pending. Delegation (item 3): `CustomerController` uses `Auth::id()`/`CurrentOrg` so a delegate is evaluated as principal by `checkPermission` but scoped by org only; org-scoped data means no 404 mismatch here.
- MariaDB-only behaviour (FK `nullOnDelete`, backfill SQL) is not proven by sqlite; the Verifier reviews the SQL by hand and the human runs the dry run on a restored copy first.
- Mitch's first sentence says customers should be "shared across the org": this plan makes them visible org-wide, not project-scoped. Confirm that is wanted.

## Checkpoint 1: decisions needed
1. Approve the additive migration `org_id` (nullable, FK nullOnDelete, index) and `customers:backfill-org` as a command, not a migration step. Default: yes.
2. Backfill rule order (quotes' org, then creator's single active org, else unresolved + overrides CSV). Default: yes; ambiguous rows are never auto-assigned.
3. Legacy NULL-org rows: fail closed. Default: yes.
4. Edit/delete of a shared customer: any org member with the map's level (creator not special). Default: yes.
5. No permission map or group change; the Customer/Account group stays with the client's pending matrix decisions. Default: yes.
6. Deleting a customer used by quotes: accept current soft-delete behaviour, or refuse? Default: accept and document.
7. Estimator: no matrix/seed/code change; human runs the diagnostic SQL and fixes via Role Designer (raise to S only if lower) and/or project membership. Never re-seed, never lower. Default: yes. Send Mitch's test user id, estimator org, and the estimate id so the Architect can read the SQL results (issue intake if results show something else).
8. Release shape: migration via `--path`, backfill before code, one more backfill after code. Default: yes.

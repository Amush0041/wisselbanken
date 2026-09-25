# Review: <feature name>

Status: PENDING — awaiting human approval (Checkpoint 2)

## QA results (qa-tester agent)

## Reviewer findings (reviewer agent)

## Architect compliance pass — Phase 1

Date: 2026-09-24. Scope: PLAN.md "Data model changes → Phase 1", "Model relations", Task breakdown steps 1-2. Reviewed the uncommitted `git diff` (4 modified models) and the 6 untracked files, and checked them against the originals `2026_06_25_000007` and `2026_09_01_000001` and against Laravel v11.41.3 `vendor/.../Schema/Grammars`.

### Verdict: **COMPLIANT** (no blocking deviations; 5 non-blocking findings below)

**What matches the plan**
- Migrations 000001-000005: file names and order match, and they sort after the last existing migration (`2026_09_01_000003`). Every column, type, nullability, default, FK target and on-delete rule matches the plan's table (projects: `restrictOnDelete` org, `nullOnDelete` created_by, `(org_id,status)` and `(created_by)` indexes, softDeletes; quotes.project_id nullable, `restrictOnDelete`, placed after `user_id`; project_members.project_id `cascadeOnDelete` plus `unique(project_id,user_id)`; plan_crosswalk.project_id `cascadeOnDelete` plus `(org_id,project_id)`; audit `quote_id` unsignedBigInteger nullable, no FK, after `project_id`).
- `->change()` (000003:19, 000004:19): the originals are `foreignId('quote_id')->constrained('quotes')->cascadeOnDelete()`, i.e. `bigint unsigned NOT NULL` with no default or comment. Restating `unsignedBigInteger()->nullable()` loses nothing. Laravel emits ``alter table `…` modify `quote_id` bigint unsigned null`` (MySqlGrammar::compileChange; MariaDbGrammar doesn't override it). That is valid MariaDB. MODIFY keeps the FK, `unique(quote_id,user_id)` and `(org_id,quote_id)`, and the column position. A nullable column under a CASCADE FK is valid: NULLs skip the FK check, and a unique index allows several NULLs. Relaxing to NULL doesn't trigger MariaDB's FK column-change guard.
- `down()` 000003/000004: the null-row abort is present and runs before any DDL. Making quote_id NOT NULL **before** dropping project_id is the safer order. MariaDB DDL isn't transactional, so if that MODIFY fails (a race, or see F3), `project_id` is still there and the migration is still "up". Nothing is lost. `strict => true` (`config/database.php:58`) means a stray NULL makes MODIFY fail loudly instead of coercing it to 0. The FK is dropped before the unique/index, which is correct. On InnoDB the FK on project_id is backed by `unique(project_id,user_id)`, because the auto-created FK index is silently dropped once the unique exists. Dropping the unique first would raise errno 1553. `dropColumn` comes last. For 000004, `(org_id,project_id)` can't back the project_id FK, and the `org_id` FK is still backed by the original `(org_id,quote_id)`.
- `App\Models\Project`: in namespace `App\Models`, as the plan says. SoftDeletes. `organization()` points to `App\Models\Rbac\Organization` via `org_id`. `creator()`, `quotes()`, `members()` (Rbac\ProjectMember), `crosswalk()`. `bid_due_at` is cast to datetime, and `$fillable` has no `id` or timestamps. `scopeVisibleTo` (Project.php:55-65) is exactly the plan rule: an active row matching `project_id`, `user_id` and `org_id`.
- Quote/PlanCrosswalk/ProjectMember/AuditLog: fillable and relations as planned. The legacy `quote()` is kept. Imports are correct.
- Scope: `git status` shows only the 4 models, `Project.php`, the 5 migrations and task docs. There are no changes under `app/Services`, `app/Http`, `config`, `routes`, `resources` or `tests`.
- No change to existing runtime behaviour. Nothing calls `ProjectMember->project` / `project()`. The only uses of `ProjectMember` (OrgAdminController:570,603,610; ProjectWorkspaceController:70; UserWorkspaceController:33; User.php:97) are query-builder calls on `quote_id`. Nothing calls `Quote->project`. Adding `project_id` to fillable is not reachable from a request: `QuoteController:418` uses `$request->only([...])` without project_id, and `PlanCrosswalkController:79` merges validated `$data` with no project_id rule. `RbacTestCase` doesn't run the new migrations yet (step 3, QA).

**Findings (all non-blocking)**
- **F1 `app/Models/Rbac/ProjectMember.php:43-45`**: `enrol()` writes `org_id`, `granted_by` and `granted_at = now()` unconditionally. Re-enrolling an **already active** member overwrites the original grantor and date, and can move `org_id`. For example, backfilled rows keep the legacy row's `org_id` (Phase 2), and a later UI enrol would rewrite it silently. The plan only says "reactivates inactive rows", so this is ambiguous, not a deviation. *Plan update needed (before Phase 4):* say whether enrol() on an active row is a no-op (keeping grantor, date and org) or a refresh. Also state that the Phase 2 backfill must **not** use `enrol()`, because its `granted_*` comes from the earliest source row.
- **F2 same method**: `updateOrCreate` isn't atomic. Two concurrent enrols of the same (project,user) raise a `unique(project_id,user_id)` QueryException instead of succeeding. Low likelihood. *Plan note:* the step 8/11 controllers should catch this or retry once. Legacy quote-only rows can't collide, because their `project_id` is NULL.
- **F3 000003:32, 000004:32 (rollback on MariaDB unverified)**: the developer only ran `--pretend`, which doesn't validate against the server, and step 3 tests on sqlite only. Changing an FK column from NULL to NOT NULL passes on InnoDB in-place. If the server chooses ALGORITHM=COPY (for example because of the `alter_algorithm` setting or the version), MariaDB's `fk_check_column_changes` rejects it with ER_FK_COLUMN_CANNOT_CHANGE (1832). The failure is safe (see ordering above), but then `migrate:rollback --step=5` wouldn't complete. *Plan update needed:* add to step 3 (QA) a `migrate` → `rollback --step=5` → `migrate` run against a scratch MariaDB that matches production `SELECT VERSION()` (pre-flight Q0), in addition to sqlite. If 1832 appears, the fix is to drop the quote_id FK, MODIFY, then re-add the FK in `down()`, as a plan correction.
- **F4 000002:12-13**: there is no explicit `->index()`. On MariaDB/InnoDB the FK auto-creates `quotes_project_id_foreign`, so production matches the plan's "indexed". The sqlite test schema has no index, which only affects speed. No action.
- **F5 Rollback partial state**: `--step=5` runs 000005 → 000001 in reverse. After Phase 2 it drops `rbac_audit_logs.quote_id` and `plan_crosswalk.project_id` **before** the 000003 guard stops it. The plan already limits rollback to "before Phase 2". *Runbook note:* after the backfill, don't use `--step=5`; roll back per migration with `--step=1` only if needed. Minor: `Project` has no `$attributes` default for `status`, so a freshly created model shows `status = null` until `refresh()`. That matters for the Phase 4 `store` JSON, not Phase 1.

**Handoff:** Architect → Orchestrator. Phase 1 steps 1-2 are compliant, so QA can start step 3. Before Phase 2, fold F1, F3 and F5 into PLAN.md through the Architect (issue-intake role).

- 2026-09-24 — Issue intake: F1-F5 addressed in PLAN.md "Amendment 1 (2026-09-24, from Phase 1 compliance pass)" (A1-A5; new Phase 1 step 2a).

## Architect compliance re-check — step 2a

Date: 2026-09-24. Scope: PLAN.md Amendment 1, A1 and A2, against `app/Models/Rbac/ProjectMember.php:38-55`.

### Verdict: **COMPLIANT** (1 non-blocking finding)

- **Scope:** `git status` shows the same Phase 1 file set as before. Only `ProjectMember.php` was modified in this step; the other files still have their 12:06 mtimes. No other change.
- **A1, no row:** `createOrFirst($key, $grant)` (:48) inserts `project_id`, `user_id`, `org_id`, `granted_by`, `granted_at=now()` and `is_active=true`. `quote_id` stays NULL and `wasRecentlyCreated` is true. :50 skips the update. This matches A1.
- **A1, active row:** there is no write, so `updated_at` and `org_id` are unchanged. Afterwards `! wasRecentlyCreated && ! wasChanged()` is true. This matches A1.
- **A1, inactive row:** `update($grant)` (:51) writes exactly `is_active`, `granted_by`, `granted_at` and `org_id` (plus `updated_at`). `wasChanged('is_active')` is true after `save()`. `org_id` is rewritten only here. This matches A1.
- **A2:** Laravel 11.41's `createOrFirst` (`Eloquent/Builder.php:607-614`) wraps the insert in a savepoint when inside a transaction. It catches `UniqueConstraintViolationException` and re-selects with the write PDO. The row it re-selects follows the same :50 rule. A race-lost row is active, because a concurrent enrol always inserts an active row, so it reports "already a member". That is accurate, not a misreport. If the row is inactive, it is reactivated. There is no caller-level catch. This matches A2.
- **`granted_at`:** `now()` is a Carbon value in the app timezone, the `datetime` cast serializes it into the `timestamp` column, and existing rows are written the same way. No issue.

**N1 (non-blocking), `ProjectMember.php:40,48`:** legacy rows are "never matched" only while `$project` is saved. If `$project->id` is null (an unsaved `Project`), `where(['project_id' => null, ...])` is compiled as `project_id IS NULL`. That matches the user's legacy quote-only row, and if the row is inactive, :51 reactivates it and rewrites its `org_id`. No planned caller passes an unsaved project, because step 8 creates the project first. *Plan update suggested, not required for Phase 1:* add to A1 the precondition "`$project->exists`, else throw `InvalidArgumentException`", as a one-line guard at the top of `enrol()`, and route it through issue intake if the human wants it.

**Handoff:** Architect → Orchestrator. Step 2a is compliant. QA step 3 can proceed with the A1/A2 test cases. A3 (the MariaDB round-trip) is still BLOCKED ON INPUT.
- 2026-09-24 — Issue intake: N1 decided in PLAN.md Amendment 1 A6 (guard added; Phase 1 step 2b).

## Architect compliance re-check — step 2b

**COMPLIANT.** In `app/Models/Rbac/ProjectMember.php:40-42`, the `! $project->exists` check throws `\InvalidArgumentException` as the first statement of `enrol()`, before any query. The global class is referenced with a leading backslash, which is correct inside `App\Models\Rbac`. The rest of `enrol()` (:44-58) is identical to the step 2a version (4-line delta).
Scope: `git status` shows the same Phase 1 file set, and only `ProjectMember.php` was modified after the step 2a check (mtimes). No findings. QA step 3 can proceed; A3 is still BLOCKED ON INPUT.

## QA results — Phase 1 step 3

Date: 2026-09-24. Role: QA. Application code, migrations and the plan were not touched.

**Safety check:** `phpunit.xml` does not set a DB (the sqlite lines are commented out), but `RbacTestCase::setUp()` forces `database.default=sqlite` with `:memory:`, purges and reconnects before any query. The Rbac filter matches only classes under `tests/Feature/Rbac/`. The local MySQL from `.env` was not touched. The non-Rbac scaffolding tests were not run.

### Files changed (tests/ only)
- `tests/Feature/Rbac/RbacTestCase.php`: the migration list now also runs `2026_09_01_000001_create_plan_crosswalk_table.php` and `2026_09_24_000001..000005`. The stub `quotes` table is unchanged, because 000002 adds `project_id` to it. `PermissionServiceTest.php` needed no change.
- `tests/Feature/Rbac/ProjectSchemaTest.php` (new, 19 tests).

### Counts
- `php artisan test --filter=Rbac`: **74 passed / 3 failed, 196 assertions**. That is the 55/3 baseline plus 19 new passing tests.
- `ProjectSchemaTest` alone: **19 passed, 83 assertions, 0 failed**.
- The 3 failures are exactly the known baseline, matched by name: `AuditMiddlewareTest > enforce mode blocks and logs`, `SeedMatrixTest > seeds expected counts` (19 vs 20 org types), `SeedMatrixTest > phase distribution` (14 vs 15 P2 roles). **No new failures.**
- Two of my own first-run tests failed because of test mistakes (`timestamps()` are nullable; the audit-log insert lacked required columns). I fixed the tests, not the app. No application defect was found.

### Coverage
- **Schema:** projects columns, nullability and indexes; `quotes.project_id` is a nullable FK to projects; `project_members` has a nullable `project_id`, a nullable `quote_id`, unique(project_id, user_id) (enforced by a duplicate insert), the legacy unique(quote_id, user_id) kept, and multiple legacy rows with NULL project_id allowed; `plan_crosswalk` has a nullable `project_id` and `quote_id`, with the new and legacy indexes; `rbac_audit_logs.quote_id` is nullable, with `project_id` kept and the column round-tripped through the `AuditLog` model.
- **Rollback:** the 000003 and 000004 `down()` methods throw `RuntimeException` on a NULL-quote_id row and leave the schema and row intact. With only legacy rows they roll back cleanly (column dropped, `quote_id` NOT NULL again, row kept). A full reverse `down()` of 000005 to 000001 on empty tables also works.
- **enrol():** the three A1 cases (none, active, inactive), including a different org_id, an unchanged `updated_at`, `wasChanged('is_active')`, and a single row per (project, user). The A2 forced race uses a one-shot `creating` listener; enrol returns the racing row without an exception and exactly one row exists. A6: an unsaved Project throws `InvalidArgumentException`, and the legacy inactive quote-only row is byte-identical afterwards.
- **scopeVisibleTo:** returns only the active membership in the given org. It excludes an inactive membership, another user, another org_id (visible only when queried with that org), a soft-deleted project, and a legacy quote-only row.
- **Relations:** `Quote::project`, `ProjectMember::project`, `PlanCrosswalk::project`, and Project `quotes`, `members`, `crosswalk`, `organization` and `creator` all resolve.

### Gaps and limits
- **sqlite is not MariaDB.** It proves the migration logic and the guards. It does not prove the MariaDB DDL: `->change()` on an FK column with ALGORITHM=COPY (ER_FK_COLUMN_CANNOT_CHANGE 1832, finding F3), InnoDB's auto-created FK index on `quotes.project_id` (F4), real FK `ON DELETE` behaviour, or the transactional and savepoint behaviour of `createOrFirst`. The race test simulates the unique violation inside a non-transactional sqlite connection.
- The sqlite rollback test rebuilds tables, which is not the same as MariaDB `ALTER`.
- The `quotes.project_id` on-delete assertion is weak: sqlite reports RESTRICT as "no action", so the assertion treats the two as equal.
- The `after()` column placement is not testable on sqlite.

### A3 (MariaDB migrate, rollback --step=5, migrate round trip)
**Not executed.** It needs the production `SELECT VERSION()` and a scratch MariaDB from the human. No database was created and no production connection was used.

**Handoff:** QA -> Orchestrator, then the Verifier. Nothing to route to the Architect from this step.

## Verifier findings — Phase 1

Date: 2026-09-24. Role: Verifier. I only appended to this file; nothing else was edited. Scope: the uncommitted Phase 1 diff, meaning 4 models, the `RbacTestCase` list, the 5 migrations, `Project.php` and `ProjectSchemaTest.php`, checked against PLAN.md (APPROVED + Amendment 1).
Evidence gathered:
- Compiled every migration's DDL offline with Laravel 11.41.3 `MySqlGrammar`. The connection's PDO resolver throws if touched, so no DB was contacted.
- Grepped all callers of `ProjectMember`, `->project`, `PlanCrosswalk`, `Quote::create/update`, `AuditLog::create`, and every org/user deletion path.
- Read vendor `Eloquent/Builder.php:607-614,1839-1844`.
- Ran `php artisan test --filter=Rbac`: **74 passed / 3 failed, 196 assertions.** The 3 failures are the known ones, by name and by reason: 403 vs 302; 19 vs 20 org types; 14 vs 15 P2 roles.
- Nothing ran against MySQL/MariaDB. There was no production or SSH access.

### Verdict: **CANNOT CONFIRM "no missing edge cases / no regression risk".**
Phase 1 deployed **on its own** has no runtime regression that I could find (see "Confirmed" below). Several edge cases are still missing from the plan, and one of them makes the plan's own MariaDB gate impossible to run as written. Strict mode: every item marked "PLAN.md update" goes Orchestrator → Architect. None of them is a direct Developer fix.

### Blocking

**V1 — The A3 MariaDB gate can't be run as written** (PLAN.md A3, "Checkout without the 5 new files, run `migrate`").
- *Evidence:* the full migration set can't run on an empty database. `2025_01_15_000001_add_address_fields_to_saved_lists_table.php:14` alters `saved_lists`, but that table is only created by `2025_05_18_102745_create_saved_lists_table.php`. `2025_01_15_000002/000003` have the same problem with `palletes`. `RbacTestCase.php:14-16` already records this.
- *Impact:* on a scratch MariaDB, B0 can never be captured. The only gate meant to validate the real DDL (F3/1832, FK index handling, lock behaviour) can't produce a result, and Phase 1 can't be marked done under the plan's own rule.
- *Needs PLAN.md update:* run A3 against a **restored production snapshot**, or at minimum a `mysqldump --no-data` of the production schema plus its `migrations` table, on the production major.minor version. That run also gives real ALTER timings for S4 below.

### Should-fix

**S1 — A2's "createOrFirst is safe inside a transaction" is wrong under InnoDB REPEATABLE READ** (`app/Models/Rbac/ProjectMember.php:53`; vendor `Builder.php:612`).
- *Evidence:* `enrol()` first runs a plain consistent read, `static::where($key)->first()`. Inside an outer transaction, that read opens the InnoDB read view. When a concurrent transaction commits the same `(project_id,user_id)`, the INSERT gets a duplicate-key error. The fallback `useWritePdo()->where(...)->first()` is also a non-locking consistent read on the same snapshot, so it can't see the committed row. It then hits `?? throw $e` and rethrows `UniqueConstraintViolationException`, which surfaces as a 500. The savepoint only rolls back the failed statement; it doesn't refresh the snapshot.
- *Impact:* this can't happen in `ProjectController::store`, because the project is new and uncommitted. It can happen if `ProjectMemberController::store` or `OrgAdminController::addProjectMember` in Phase 4 wraps `enrol()` in `DB::transaction`. The sqlite race test (`ProjectSchemaTest.php:276-300`) can't catch it: it runs with no transaction, and the racing insert is on the same connection.
- *Needs PLAN.md update:* correct A2, then choose one fix. Either forbid an outer transaction around `enrol()` for existing projects, or make the fallback re-select a locking read (`sharedLock()`/`lockForUpdate()`), which reads the latest committed row.

**S2 — A third org-deletion path isn't in the plan, and it will raise a raw FK error once any project exists.**
- *Evidence:* `app/Http/Controllers/Admin/RbacController.php:506`. `destroyUser()` deletes every org where the user is the only active member, using `Organization::where('id', $orgId)->delete()`. `projects.org_id` is `restrictOnDelete` (`000001:13`; compiled DDL is `... references organizations (id) on delete restrict`).
- *Exact behaviour:*
  - (a) **Phase 1 alone:** no regression. Nothing inserts `projects` rows, so the restrict never fires.
  - (b) **From the first real `projects:backfill` onward:** all three paths throw `QueryException` SQLSTATE 23000 / 1451 for any org that owns a project. The paths are `OrgSettingsController::destroy` (`:65`), `RbacController::destroyOrganization` (`:459`) and `RbacController::destroyUser` (`:506`). Each runs inside `DB::transaction`, so everything rolls back and nothing is partly deleted. The user sees a generic 500.
  - (c) **After Phase 4:** the plan (Q8, step 11) only turns the first two into a graceful refusal. `destroyUser` keeps returning a 500, so a platform admin can't delete a user who is the only member of an org with projects.
- *Needs PLAN.md update:* add `destroyUser` to Q8/step 11, decide how it should behave, and add it to the Phase 4 tests.

**S3 — Neither `scopeVisibleTo` nor `enrol()` ties membership to `projects.org_id`, so cross-org isolation depends on the data and on each caller** (`app/Models/Project.php:55-65`; `ProjectMember.php:38-58`).
- *Evidence:* `ProjectSchemaTest.php:341,354` enrols a user on an **org-A** project with `org_id = otherOrg`. It then **asserts** that the project is visible through `visibleTo(user, otherOrg)`. `enrol()` accepts any `$orgId`. A6 closed the same kind of gap with a guard ("not by caller convention"), but no equivalent guard exists here. The Phase 2 backfill deliberately keeps legacy `org_id`s, so mismatched rows are expected.
- *Consequences:*
  - A user acting in org B sees org A's project. That breaks the plan's acceptance line "org B can't list, open … an org-A project, even with the id" whenever such rows exist.
  - Under A1 an active mismatched row is never rewritten, so re-enrolling reports "already a member" while the user still can't see the project in org A.
  - Today's `removeProjectMember` check (`OrgAdminController.php:626`, `org_id !== $org->id`) stops org A's admin from removing that row.
- *Needs PLAN.md update:* decide explicitly between two options:
  - add `projects.org_id = $orgId` to `visibleTo`, and have the backfill report and re-home mismatched member rows; or
  - keep the current rule, add an `$orgId === $project->org_id` guard to `enrol()`, and state the exception explicitly in the acceptance criteria.

**S4 — A migration that fails half-way can't be recovered by re-running it, and the plan has no recovery steps or pre-flight checks for this.**
- *Evidence (compiled DDL):* every multi-statement `up()` runs separate auto-committing DDL, and the migrations row is written only after `up()` returns.
  - `000001`: `create table projects`, then 2× `add constraint`, then 2× `add index`.
  - `000002`: `add project_id`, then `add constraint`.
  - `000003`/`000004`: `add` column, then `add constraint`, then unique/index, then `modify quote_id … null`.
- If a later statement fails, re-running stops with 1050 (table exists) or 1060 (duplicate column).
- Realistic triggers:
  - With `foreign_key_checks=1`, ADD FOREIGN KEY can't use INPLACE, so `quotes`, `project_members` and `plan_crosswalk` are each rebuilt with ALGORITHM=COPY, and writes are blocked for the whole copy.
  - That copy runs under Laravel's strict `sql_mode` (`config/database.php:58`, which includes NO_ZERO_DATE), so existing invalid rows such as zero dates fail it.
  - Metadata-lock waits (MariaDB `lock_wait_timeout` defaults to 86400 s), or a dropped connection.
  - Engine, id-type or schema drift on the production tables.
- The 000003/000004 `down()` guards and A4 only cover rollback, not a failed `up()`.
- *Needs PLAN.md update (runbook/pre-flight):* either give per-migration manual recovery DDL, or make `up()` idempotent. Also add these read-only pre-flight checks:
  - `SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 15` compared with the repo. Given the earlier code-ahead-of-migrations incident, any pending older migrations, such as `2026_09_01_*`, would run in the same `migrate`.
  - `SHOW CREATE TABLE` for `quotes`, `project_members`, `plan_crosswalk`, `rbac_audit_logs`, `organizations` and `users` (engine InnoDB, `bigint unsigned` ids, FK names such as `project_members_quote_id_foreign`).
  - `SELECT @@sql_mode, @@lock_wait_timeout`.
  - Row counts for the 4 altered tables.
  - A zero-date check on `quotes`.

**S5 — Deploy order across the release.**
- *Evidence:* Phase 1 alone works in either order. Code that ships ahead of the migrations never reads or writes the new columns: `Project` is unused, and `Quote`/`AuditLog` fillable entries are never passed. Migrations that run ahead of the code are tolerated: all new columns are nullable, the legacy `unique(quote_id,user_id)` is kept, and multiple NULLs are allowed.
- Phases 1-4 ship as one release, though, and the repo root has `.ftpquota`, which suggests non-atomic FTP-style uploads.
- *Impact:* if the Phase 3/4 files go live before `migrate`, then `RbacAudit`'s `quote_param` resolution and every `Project` query hit a missing `projects` table or column, and quote routes return 500. The earlier incident was exactly this pattern.
- *Needs PLAN.md update (runbook):* state a hard order: `down` → `migrate --force` → verify `migrate:status` → backfill → upload code → `up`. Optionally ship the Phase 1 migrations in an earlier, separate window; they're safe on their own, and then the code deploy no longer depends on a same-window migrate. That choice is for the Architect and the human.

**S6 — The `000005` `down()` drops audit data with no guard** (`2026_09_24_000005_…:18-20`).
- *Evidence:* 000003 and 000004 abort their rollback when new-format data exists. 000005 just drops `rbac_audit_logs.quote_id`. Phase 3 writes that column on every denied quote request from the moment it deploys, and Phases 1-4 ship together, so any real rollback after deploy silently deletes forensic audit data. A4 step 4 doesn't check this column.
- *Needs PLAN.md update:* either add the same `whereNotNull('quote_id')->exists()` guard, or accept the loss explicitly and document it in A4.

### Notes (no action needed for Phase 1)

- **N1 — test quality.** Most of the 19 tests are real, not tautological. The active-row no-op test compares the whole row, and the A6 test proves the legacy row is untouched byte for byte. Weak or missing coverage:
  - FK `ON DELETE` behaviour is barely tested. The `quotes.project_id` restrict assertion is tautological, since it maps `no action` to restrict. There are no assertions for the cascade on `project_members.project_id` and `plan_crosswalk.project_id`, the restrict on `projects.org_id`, or `nullOnDelete` on `created_by`.
  - The race test only covers an **active** race-lost row, not the A1 "inactive race-lost row is reactivated" branch.
  - The stub `quotes` table (`RbacTestCase.php:58-68`) has no `user_id` FK cascade, so the user-deletion interaction isn't modelled. That doesn't hide anything in Phase 1.
- **N2 — what sqlite can't prove:**
  - MariaDB ALTER algorithm and lock behaviour (COPY vs INPLACE), and 1832 in `down()`.
  - InnoDB's automatic creation and dropping of FK indexes, and `after()` column placement.
  - How strict `sql_mode` validates rows during a table copy.
  - REPEATABLE READ and savepoint semantics (S1).
  - Real InnoDB ON DELETE enforcement.
- **N3 — soft-deleted projects in `enrol()`.** `enrol()` accepts a soft-deleted project, because `exists` is true for a trashed model. Callers that use route binding exclude trashed projects. Phase 4 should keep it that way.
- **N4 — unfiltered NULLs in legacy readers.** `UserWorkspaceController.php:33-36`, `ProjectWorkspaceController.php:70-81` and `PlanCrosswalkController.php:37` (`with('quote')`) assume `quote_id` is non-null. That only matters if Phase 4 native rows exist before the Phase 3 rewrites, and those phases ship together.
- **Confirmed:**
  - There are no callers of `ProjectMember::project()`, `Quote::project()`, `PlanCrosswalk::project()`, `User::projectMemberships()`, or any eager-load of `'project'`.
  - `project_id` can't be mass-assigned from a request. `QuoteController:418` uses `only()`; `:223,316,552,633` use explicit arrays; `PlanCrosswalkController:79` has no `project_id` rule.
  - `isActiveProjectMember` (`PermissionService.php:124-132`) is unchanged, and new-format rows can never match it.
  - Legacy `ProjectMember::create` (`OrgAdminController.php:610`) still works with the new unique, because NULL `project_id` values are allowed to repeat.
  - The `2026_09_24_*` names don't collide on any branch, and they sort after `2026_09_01_000003`, which doesn't touch the affected tables.
  - RBAC checklist: no route, `route_permission_map` or `checkPermission` changes; no inline role checks; no new permission group or role; SoD not affected; none of ARCHITECTURE.md's Known open items were touched.
  - Conventions: no needless comments in application code, and the stale "project = Quote" docblock was removed. The `// ---- section ----` dividers in the test are cosmetic only.

**Handoff:** Verifier → Orchestrator. V1 and S1-S6 go to the Architect for PLAN.md updates (issue intake). The pre-merge decision belongs to the human at Checkpoint 2.
- 2026-09-24 — Issue intake: Verifier V1, S1-S6, N1-N4 addressed in PLAN.md "Amendment 2" (B1-B9; new Phase 1 steps 2c/2d/2e; HUMAN DECISIONS in B3, B4, B6).

## Architect compliance re-check — steps 2c, 2d, 2e

Date: 2026-09-24. Scope: PLAN.md Amendment 2 B2, B4 and B7, checked against `app/Models/Rbac/ProjectMember.php`, `app/Models/Project.php` and `2026_09_24_000005_…`.

### Verdict: **COMPLIANT** (the code matches B2/B4/B7; 1 plan gap in the B4 test list, and QA test work is pending)

- **Scope:** `git status` and mtimes show that only the 3 planned files changed in this step, all at 12:42. `RbacTestCase.php` (12:17) and `ProjectSchemaTest.php` (12:18) are QA's step 3 files and weren't touched here.
- **B2, `ProjectMember.php:57-66`:**
  - The plain `first()` runs first. Only if it finds nothing does `create()` run in a `try`. `UniqueConstraintViolationException` (imported at :10) is caught and followed by `sharedLock()->firstOrFail()`. This matches B2.
  - **MariaDB:** a duplicate-key error rolls back only the failed INSERT, so the caller's transaction stays open and usable. The old `createOrFirst` savepoint only mattered for PostgreSQL, which isn't a target. B2 says no savepoint is needed. Laravel keeps no transaction state on a caught `QueryException`.
  - The `sharedLock()` read (`LOCK IN SHARE MODE`) returns the latest committed version, not the REPEATABLE READ snapshot. It is compatible with the shared lock InnoDB already holds on the duplicate record, so there is no lock-upgrade deadlock.
  - **sqlite:** the default ABORT resolution also rolls back only the statement, and `sharedLock()` compiles to nothing.
  - The comment at :63 explains why, which the conventions allow.
- **A1 on the race-lost row, :68-70:** a model from `firstOrFail()` has `wasRecentlyCreated=false`. An active row gets no write, so `wasChanged()` is false and it reports "already a member". An inactive row gets `update($grant)` with exactly `is_active`, `granted_by`, `granted_at` and `org_id`, so `wasChanged('is_active')` is true. The insert path sets `wasRecentlyCreated=true` and skips :68. `firstOrFail` gives a 404 if the row disappears in between, as B2 specifies.
- **B4 guard order, :41-47:** the A6 `exists` check runs first, then `(int) $project->org_id !== $orgId`. This matches B4, including the cast.
- **B4 scope, `Project.php` (`scopeVisibleTo`):**
  - The top-level `where('projects.org_id', $orgId)` is added and the member `org_id` check is kept.
  - Soft-deleted projects are excluded by the SoftDeletes global scope unless a caller uses `withTrashed()`.
  - Legacy rows (`project_id` NULL) never satisfy `whereColumn(project_members.project_id = projects.id)`.
  - Laravel groups a scope's wheres when the query already has wheres, so a caller's `orWhere` can't bypass the org condition.
- **B7, `000005:19-23`:** the `whereNotNull('quote_id')->exists()` guard runs before `dropColumn`, with the same pattern as 000003/000004. `DB` is imported, and `RuntimeException` resolves globally because the file has no namespace. This matches B7.

**Test run** (`php artisan test --filter=ProjectSchemaTest`, in-memory sqlite): **16 passed / 3 failed.** All 3 failures are `InvalidArgumentException` from the B4 guard at `ProjectMember.php:46`. The tests still assert the old cross-org behaviour: `test_enrol_active_member_is_unchanged_even_with_different_org` (:~230-251), `test_enrol_inactive_member_is_reactivated_with_new_grant` (:263) and `test_scope_visible_to` (:341). These are expected results of B4, not code defects.

**Findings**
- **D1 (non-blocking; PLAN.md update needed, B4 test list):** B4 only names `ProjectSchemaTest.php:341-354` for rewriting. Two more tests break under B4 and must be rewritten as well:
  - the active-row test, which should now assert that enrolling with another org throws and leaves the row byte-identical;
  - the reactivation test, which should reactivate with `$this->org`, assert the A1 fields, and assert that `org_id` equals the project's org.

  Until QA does this, these 3 failures must not be reported as regressions.
- **D2 (non-blocking, no plan update; already B8):** QA hasn't yet added the inactive race-lost test, the FK ON DELETE behaviour tests, the B4 mismatched-row tests or the B7 guard test. They are in step 3.
- **D3 (note, no action):** inside a caller transaction, the plain `first()` at :57 can return a stale snapshot of an existing row. If the row was concurrently reactivated, :69 overwrites the concurrent `granted_by`/`granted_at`. If it was concurrently deactivated, the result is "already a member". Both are harmless races between concurrent admin actions. The UPDATE itself always applies to the latest committed version.

**Handoff:** Architect → Orchestrator. Steps 2c/2d/2e are compliant. Route D1 through issue intake (PLAN.md B4 test list), then QA does step 3 (the B4/B8 test rewrites and additions). B1 is still BLOCKED ON INPUT.

## QA results — Phase 1 B10

Date: 2026-09-24. Only `tests/Feature/Rbac/ProjectSchemaTest.php` was changed; application code, migrations and the plan were not touched. Safety is unchanged: `RbacTestCase` forces in-memory sqlite, only the Rbac filter was run, and no production or SSH access was used.

### Counts
- `ProjectSchemaTest`: **29 passed / 0 failed, 123 assertions**.
- `php artisan test --filter=Rbac`: **84 passed / 3 failed, 236 assertions**. The 3 failures are exactly the known baseline: `AuditMiddlewareTest > enforce mode blocks and logs`, `SeedMatrixTest > seeds expected counts` and `SeedMatrixTest > phase distribution`. No new failures and no application defects.

### B10 items covered
- **R1:** split into `test_enrol_active_member_is_unchanged` and `test_enrol_mismatched_org_on_existing_row_throws_and_writes_nothing`.
- **R2:** `test_enrol_inactive_member_is_reactivated_with_new_grant` (project's org, same id, later `granted_at`, one row), plus `test_enrol_mismatched_org_on_inactive_row_throws_and_stays_inactive`.
- **R3:** `test_scope_visible_to` rewritten. The mismatched row is inserted with `DB::table()` and is visible in neither org. An other-org project with a matching membership is a positive control. The inactive, other-user, soft-deleted and legacy-only assertions are kept.
- **N1:** `test_enrol_mismatched_org_on_new_member_throws_and_writes_nothing`.
- **N2:** `test_enrol_race_lost_active_row_is_returned_unchanged`, with the added assertions (`wasChanged()` false, `is_active` true, `granted_by` is the racer).
- **N3:** `test_enrol_race_lost_inactive_row_is_reactivated`.
- **F1-F4:** the tautological restrict assertion is removed. Each of the four FK tests asserts `PRAGMA foreign_keys = 1` first: `test_force_deleting_project_referenced_by_quote_fails`, `test_force_deleting_project_cascades_members_and_crosswalk`, `test_deleting_org_that_owns_a_project_fails` and `test_deleting_creator_nulls_projects_created_by`.
- **G1-G2:** `test_rollback_000005_aborts_when_quote_id_set` and `test_rollback_000005_clean_when_all_null`.
- **Confirmed:** `RbacTestCase` and `PermissionServiceTest` need no further change.

### What sqlite cannot prove
- `sharedLock()` compiles to nothing on sqlite and sqlite has no REPEATABLE READ snapshot. The race tests prove only the branch logic. The real B2 proof is the two-session check in B1 step 5.
- Real InnoDB ON DELETE enforcement, FK auto-indexes, and `->change()` and ALTER algorithm behaviour (ER 1832) are not proven.

### B1 (MariaDB gate)
**Not executed.** It needs the production `SELECT VERSION()`, a restorable copy of the production DB, and a scratch MariaDB of the same version, all from the human. No database was created.

## Verifier re-review — Phase 1

Date: 2026-09-24. Role: Verifier. I only appended to this file; nothing else was edited.
Scope: PLAN.md header decisions, Amendment 2 (B1-B10) and the updated Task breakdown/Runbook; the current `ProjectMember.php`, `Project.php` and `000005`; the rewritten `ProjectSchemaTest.php` (29 tests); and the Architect's 2c-2e re-check and QA's B10 sections.
Evidence gathered:
- Re-ran `php artisan test --filter=Rbac`: **84 passed / 3 failed, 236 assertions.** The 3 failures are the known ones, by name and reason: 403 vs 302; 19 vs 20; 14 vs 15.
- `--filter=ProjectSchemaTest`: 29 passed, 123 assertions.
- Read vendor `Query/Builder.php:2919-2928`: `lock()` forces the write PDO.
- Read `MySqlGrammar.php:301-308`: `sharedLock` compiles to `lock in share mode`. `SQLiteGrammar.php:30-33` compiles it to an empty string.
- `git diff --stat main dev`: the only differences are docs and agent files. There is no app-code drift between `main` and `dev`.
- `composer.json:65`: `optimize-autoloader` is on and the classmap is not authoritative, so the new `App\Models\Project` loads through PSR-4.

### Status of the earlier findings
| Item | Status | Evidence |
|---|---|---|
| **V1** | **RESOLVED in plan; now a gate.** | B1 runs on a restored production copy and requires `migrate:status` to show only the 5 pending migrations. It adds real ON DELETE checks, guard checks, timings and the B2 two-session check. It is still BLOCKED ON INPUT, and PLAN.md:491 makes it gate any production `migrate`. |
| **S1** | **RESOLVED in code. Proof pending at gate B1 step 5.** | `ProjectMember.php:57-66`: `first()`, then `create()` in a `try`, then `sharedLock()->firstOrFail()`. A locking read returns the latest committed row whatever the read view, so this does fix the REPEATABLE READ miss. The duplicate-key error only fires once the competing row is committed; while the other transaction is still open, the INSERT waits for it. InnoDB rolls back only the failed statement and Laravel keeps no transaction state on a caught exception, so an outer `DB::transaction` stays usable. The inactive-row deadlock (two losers both upgrading S to X, error 1213) is a residual risk the plan accepts (B2). The sqlite race tests (`:333-392`) would also pass on the pre-2c `createOrFirst`: sqlite has no MVCC, and `sharedLock` compiles to nothing there. So automated tests prove only the branch logic, and the fix itself is proven only at B1 step 5, as B2 says. |
| **S2** | **RESOLVED in plan (B3, Option A decided).** | The code lands in Phase 4 step 11, with tests in step 16, covering all 3 paths and soft-deleted projects. Phase 1 creates no `projects` rows, and B6 keeps the backfill in the same window as the refusal code, so no window is exposed. |
| **S3** | **RESOLVED in code and tests. Plan updated for Phases 2 and 3.** | `Project.php` scope: `where('projects.org_id', $orgId)` plus the member `org_id` check. `ProjectMember.php:45-47` has the guard after A6, with an int cast. `test_scope_visible_to` isn't tautological: without the projects.org_id filter, `visibleTo(user, otherOrg)` would also return `mismatched-member-org`, so it would fail. It also has a positive control. The three mismatched-org enrol tests assert byte-identical rows. Phase 3 step 5 joins `projects.org_id`, and B4 Option A was decided. |
| **S4** | **PARTIAL (plan wording, see R1 below).** | B5 adds pre-flight queries Q10-Q15, a stop rule and a table of recovery DDL. |
| **S5** | **RESOLVED in plan (B6, separate Phase 1 window decided).** | One new gate item, R2, is listed below. |
| **S6** | **RESOLVED in code and tests.** | `000005:19-23` guard. Tests G1 and G2 check both that the column is kept and the row count. A4 step 3a exports the audit data first. |
| N1 | Mostly RESOLVED. | The tautological restrict assertion is gone. F1-F4 check `PRAGMA foreign_keys=1` first, and each would fail if the ON DELETE rule changed. The inactive race-lost case has been added. |
| N2-N4 | Covered by B1, or accepted in B9. | |

### New findings from the Amendment 2 changes
**No defect in the 2c/2d/2e code.**
- **Guard vs callers:** today nothing calls `enrol()`. The backfill never calls it (B4). The planned callers all pass `project.org_id == currentOrg`: `ProjectController::store` creates the project in that org, and `visibleTo` now forces the same org for bound projects. A Phase 4 caller that forgets its own org check gets a 500 (InvalidArgumentException) instead of a 403. That fails closed, and the "wrong org" rows in step 16 would catch it.
- **Unsaved or partially selected projects:** a `Project` loaded without `org_id` casts to 0 and throws. That also fails closed.
- **The comment at `:63`:** it explains a hidden constraint, which the conventions allow.

- **R1 — should-fix, PLAN.md update (B5 recovery step 4).** Step 4 says: "If every object is present and only the `migrations` row is missing, insert that row". For 000003 and 000004, the last statement is `MODIFY quote_id … NULL`, and the "Objects to remove" table doesn't mention it. If that MODIFY fails or is interrupted, the FK, the unique/index and the column are all present, but `quote_id` is still NOT NULL. An operator following step 4 would record the migration as Ran on a schema that rejects new-format rows. Phase 4's `enrol()` INSERT (quote_id NULL) would then fail in production, and the 000003 `down()` guard would think the table is in the "up" state. Fix: in B5, state that for 000003 and 000004 "every object present" also requires `quote_id … DEFAULT NULL` in `SHOW CREATE TABLE`; otherwise run the MODIFY manually first. Route via the Architect.
- **R2 — should-fix, PLAN.md update (B6 step 3; also a gate).** The Phase 1 window **overwrites** 4 existing production files by FTP: `Quote.php`, `PlanCrosswalk.php`, `Rbac/ProjectMember.php` and `Rbac/AuditLog.php`. `main` and `dev` have identical app code, but nothing checks that production's copies match `main`. That matters here because the earlier incident shows production and git can drift. Add to B6: before uploading, diff production's copies of those 4 files against `main`; stop and go to the Architect on any difference. There is also a small inconsistency: B6 step 3 lists `BackfillProjects.php`, but PLAN.md:603 says it goes up in the release window. It doesn't exist in Phase 1.
- **R3 — note, optional test hardening, no plan change required.**
  - F1 and F3 accept any `QueryException` and have no negative control. They aren't tautological today, but they would keep passing if some unrelated FK started blocking the delete.
  - No sqlite test calls `enrol()` inside an outer `DB::transaction`.
  - Both of these are covered at B1 steps 5-6.

### Verdict
**CONFIRMED for Phase 1 code and tests:** no missing edge cases and no regression risk from deploying Phase 1 on its own.
- Nothing calls the new code paths. The old code tolerates the new schema.
- The guards fail closed.
- The tests prove what they claim, within the sqlite limits stated in B2 and B10.

R1 and R2 are runbook/plan wording only, not code changes. Strict mode: route them through the Architect before the Phase 1 window.

**Gates. These must be true before any production `migrate`; they are not defects:**
1. B1 passes on a restored production copy, on MariaDB of the same version:
   - only the 5 migrations are pending;
   - B0' = B0 and B2 = B1;
   - the B2 two-session check returns the row with no exception;
   - the 4 ON DELETE checks hold;
   - the guards hold;
   - the timings are recorded.
2. The B5 pre-flight queries Q10-Q15 are clean (zero dates checked on every date column of the 3 copied tables).
3. The production copies of the 4 modified models match `main` (R2).
4. A full backup has been taken and test-restored; maintenance mode is on; `migrate:status` shows only the 5 pending (B6 steps 1-5).
5. The human acknowledges Amendments 1 and 2 at Checkpoint 2.

**Handoff:** Verifier → Orchestrator. R1 and R2 go to the Architect (issue intake). R3 is optional. The pre-merge call belongs to the human.
- 2026-09-24 — Issue intake: Verifier re-review R1-R3 addressed in PLAN.md Amendment 2 addendum B11 (manual migration-row verification), B12 (production file-diff gate), B13 (optional O1-O2; Phase 1 exit criteria). No code change.

## Architect compliance re-verification — Phase 1 (Sonnet)

Independent second pass over commit 8363ed3, re-derived from the live code. Verdict: **COMPLIANT**. There are no code deviations. `php artisan test --filter=Rbac` gives 84 passed / 3 failed (236 assertions). The failures are exactly the 3 known baseline ones: `SeedMatrixTest` seeds expected counts (`:20`), `SeedMatrixTest` phase distribution (`:30`), and `AuditMiddlewareTest` enforce mode. I confirmed the first two in the output. The third is the only other failure, so it is the one named in TESTING.md. B1 (MariaDB) is still not run, so nothing here proves DDL behaviour on MariaDB.

**1. Migrations (all match the plan)**
- 000001 (`create_projects_table.php:13-24`): org_id restrict, name string(255), status string(32) default active, address text null, bid_due_at dateTime null, created_by nullOnDelete null, timestamps, softDeletes, indexes `(org_id,status)` and `(created_by)`.
- 000002 (`:12-13`): nullable FK to projects, restrict, `after('user_id')`. No explicit `->index()`; the FK supplies one (A5).
- 000003 (`:12-20`): `project_id` nullable FK cascade, unique `(project_id,user_id)`, then a separate `unsignedBigInteger('quote_id')->nullable()->change()`.
  - The original was `foreignId` = unsigned bigint NOT NULL with no default or comment (`2026_06_25_000007:19`), so the restated `change()` loses nothing.
  - The legacy FK, `unique(quote_id,user_id)` and `(user_id,is_active)` index are untouched.
- 000004 (`:12-20`): same pattern. The original quote_id is bigint unsigned NOT NULL (`2026_09_01_000001:24`). The new index is `(org_id,project_id)` and the legacy `(org_id,quote_id)` index is kept.
- 000005 (`:12-14`): `unsignedBigInteger` nullable, after `project_id`, no FK.
- down() guards:
  - 000003 `:26-30` and 000004 `:26-30` abort on `quote_id IS NULL`, before any DDL.
  - 000005 `:20-24` aborts on `quote_id IS NOT NULL`.
  - In 000003/4 the NOT NULL restore comes first, then dropForeign, dropUnique/dropIndex, dropColumn, which is the A3 order. Reverse order 5→1 is exercised by the test at `ProjectSchemaTest.php:199`.
- Untested on sqlite by nature: whether MariaDB accepts the NOT NULL MODIFY on a CASCADE FK column (1832 risk). It stays with B1.

**2. Project model (`Project.php`)**
- SoftDeletes, fillable and the `bid_due_at` datetime cast are correct.
- All 5 relations exist.
- `scopeVisibleTo` (`:55-65`) requires `projects.org_id = $orgId`, plus an active member row with `project_id`, `user_id`, `org_id = $orgId`.
- The SoftDeletes global scope excludes trashed projects. Legacy rows (`project_id` NULL) never join.

**3. `ProjectMember::enrol` (`:39-73`)**
- Guard order matches the plan: A6 (`:41`), then B4 with an int cast (`:45`), before any query.
- Lookup is by `(project_id,user_id)`, so legacy rows are never matched.
- No row: `create` writes `project_id`, `user_id`, `org_id`, `granted_by`, `granted_at`, `is_active` (`:61`). `quote_id` stays NULL.
- Duplicate key: `catch UniqueConstraintViolationException`, re-select with `sharedLock()->firstOrFail()` (`:62-64`).
- Active row: nothing is written, so `wasRecentlyCreated` and `wasChanged()` are both false.
- Inactive row, including the race-lost path: `update($grant)` (`:68-69`) sets `is_active`, `granted_by`, `granted_at`, `org_id` and `updated_at`, and `wasChanged('is_active')` is true.
- Caller flags work on all paths. The race-lost row is a fresh instance from `firstOrFail`, so `wasRecentlyCreated` is false.

**4. Other models and scope**
- `Quote`: `project_id` fillable and `project()`.
- `PlanCrosswalk`: `project_id` fillable, `project()`, and `quote()` kept.
- `AuditLog`: `quote_id` fillable.
- `ProjectMember::project()` now returns a `Project`, and `quote()` is kept. No caller of `->project` exists in `app/` or `resources/views/`.
- The commit touches exactly 12 files, all in the plan. Nothing in PermissionService, RbacAudit, the route map, controllers, views or routes changed. `RbacTestCase` gained the 5 migrations and `2026_09_01_000001` as planned.

**5. Tests**
- `ProjectSchemaTest` is 29 tests in the file and covers B10 R1-R3, N1-N3, F1-F4, G1-G2 and A6. No tautological assertions were found.
  - Rows are compared byte-for-byte via `memberRows()`.
  - The race listener is guarded by `$fired` and asserted.
  - FK behaviour tests check `PRAGMA foreign_keys` first.
- Missing, but optional (B13): O1 negative controls and O2 the transaction-usable test.

**6. PLAN.md inconsistencies (all non-blocking; PLAN.md wording update recommended, no code impact)**
- `:204` and `:363` say "55 passed / 3" and "exactly 55". The suite is now 84/3, and B13 (`:727`) already says 84. Fix the wording to "baseline 55 plus new tests".
- `:642` says "Status right now: 16 passed / 3 failed". It is stale; the file is fully green. The B10 line numbers (`:231`, `:253`, `:326`, `:341`) are stale too.
- Steps 3 and 2b-2e are out of order (`:204-215`). Step 3's text and its "Test:" block hang under 2e, so the numbering is confusing.
- `:198` still says "Phases 1-4 ship together as one release". B6 has replaced that with the split, and only the Runbook paragraph (`:295`) notes it.
- `:204` says step 3 updates the "PermissionServiceTest stubs". Nothing was needed and the file is untouched. The real rewrite belongs to Phase 3 step 5, and the file list (`:189`) puts it there.
- `:418` (A3) contains a garbled sentence ("...still apply. until the human supplies...").
- The A1 table (`:404`) says an active row is untouched "even if org_id != $orgId". Under B4 that state is unreachable, and `enrol()` throws first. `:407` notes this.
- Step 1 (`:201`) mentions guards only for 000003/4. The 000005 guard is added in B7/2e.
- `PLAN.md` and `REVIEW.md` are untracked and not part of 8363ed3 (the `.claude/agents` files are modified but uncommitted, and no Phase 1 file references them). Commit them with the Checkpoint 2 paperwork.

**Findings summary:** none blocking; six non-blocking documentation nits in item 6, and PLAN.md wording needs updating; no code change required. Open gate: B1/B5/B12 on production remain BLOCKED ON INPUT.

## QA results — Phase 1 optional O1/O2

Rbac suite: 85 passed / 3 failed (243 assertions). The 3 failures are the known baseline by name: AuditMiddlewareTest "enforce mode blocks and logs", SeedMatrixTest "seeds expected counts", SeedMatrixTest "phase distribution". No new failures, no defects found. ProjectSchemaTest: 30 passed. RbacTestCase forces in-memory sqlite, so the real MySQL DB was never touched. Only `tests/Feature/Rbac/ProjectSchemaTest.php` was edited.

- O1a `test_force_deleting_project_referenced_by_quote_fails`: original assertions kept. Negative control: after the failure the quote is detached (project_id = null) and the same forceDelete succeeds, so quotes.project_id is the blocking FK.
- O1b `test_deleting_org_that_owns_a_project_fails`: original assertions kept. Negative control: the project is force-deleted and the same org delete succeeds, so projects.org_id is the blocking FK.
- O2 `test_enrol_inside_outer_transaction_stays_usable` (new): a ProjectMember::creating listener inserts the conflicting row inside DB::transaction, enrol() returns the racing row (wasRecentlyCreated false), a further write follows in the same transaction, the transaction commits (level back to 0) and both the member row and the extra write persist.

sqlite limits: O2 proves only that the transaction stays usable after the unique-violation retry. REPEATABLE READ / sharedLock behaviour on InnoDB is proven only at gate B1 step 5. FK enforcement is real on sqlite (PRAGMA asserted) but InnoDB FK behaviour is B1 step 6.

## Verifier re-review — Phase 1 (Sonnet, independent)

Scope: commit 8363ed3 plus the uncommitted O1/O2 tests. Evidence: my own greps of app/, resources/, routes/, tests/, database/seeders; `php artisan test --filter=Rbac` (85 passed, 3 failed, and the 3 are exactly the TESTING.md baseline: SeedMatrixTest x2, AuditMiddlewareTest enforce); ProjectSchemaTest 30/30; 6 mutations run on a scratch copy, which I deleted. No tracked file was modified.

### Findings (most severe first)

1. **should-fix (test quality) — the A6 guard `! $project->exists` is masked, so no test guards it.**
   - Location: `tests/Feature/Rbac/ProjectSchemaTest.php:427-445` and `app/Models/Rbac/ProjectMember.php` (the `exists` check).
   - Evidence: I replaced the guard with `if (false)` and all 30 tests still passed. The test enrols `new Project()`, whose `org_id` is null, so the org guard throws `InvalidArgumentException` first.
   - Impact: with `new Project(['org_id' => $org])`, an unsaved project gives `project_id = null`. `where(['project_id' => null, ...])` becomes `IS NULL` and matches every legacy quote-only row for that user. `enrol()` would then reactivate and re-grant a legacy row, which is the A6 hazard. The guard is present, but nothing would catch its removal.
   - Fix: the test should build `new Project(['org_id' => $this->org->id])`. Route through the Architect (PLAN.md test-task wording).

2. **note (test, MariaDB-only) — `sharedLock()` is unguarded by any test.**
   - Evidence: removing it left 30/30 passing, because sqlite ignores locking. O2 proves only that the transaction stays usable.
   - Impact: the REPEATABLE READ behaviour is covered only by gate B1 step 5. This is already declared in PLAN, so it is a gate and not a defect.

3. **note — `enrol()` accepts a soft-deleted `Project` instance.**
   - Evidence: `exists` is true for a trashed model. No test covers it.
   - Impact: no Phase 1 caller exists. Phase 4 callers must resolve projects through the default (non-trashed) scope. Suggest a plan line for Phase 4, or a guard on `trashed()`.

4. **note — no test that the legacy `quote_id` FK and its cascade survive the `->change()` in 000003/000004.**
   - Evidence: the tests assert the column is nullable and the unique index is kept, but never that the FK survives.
   - Impact: MySQL `MODIFY` keeps the FK; sqlite's table rebuild is unproven. B1 step 6 covers it.

5. **note — name collision.** `PlanCrosswalkController` and the plan-crosswalk and project-workspace views already use a request or query parameter called `project_id` (it holds a quote id). That is unrelated to the new column, but Phase 4 must not conflate them. Nothing to change in Phase 1.

6. **note — `enrol()` returns an existing active row whose `org_id` differs from the project's.** This can only happen if `projects.org_id` were changed after enrolment. Nothing in Phase 1 can do that.

### Checks that came out clean (independently confirmed)

- **Regression risk if Phase 1 is deployed alone: none found.**
  - Every caller of ProjectMember, PlanCrosswalk and AuditLog uses `quote_id` and never reads `->project`. That covers `OrgAdminController` add/remove/list, `UserWorkspaceController` (`pluck('quote_id')`), `ProjectWorkspaceController`, `PlanCrosswalkController`, `RbacAudit` (which does not set the new `quote_id` column), and `PermissionService::isActiveProjectMember`.
  - `User::projectMemberships` is a plain `hasMany`. No blade or PHP code calls `ProjectMember::project()`.
  - All three `Quote::create` calls use explicit arrays, so the new `$fillable` `project_id` is not reachable from user input.
  - `PlanCrosswalkController::store` passes validated data only.
  - No caller creates a row with `quote_id` NULL.
  - Legacy rows and unique indexes are untouched.
  - Pre-existing and out of scope: `addProjectMember` will hit the unique index on a re-add after a soft removal. I did not touch it.
- **Migrations:**
  - Timestamps sort after the last existing one (2026_09_01_000003).
  - `foreignId` types match (unsigned bigint).
  - The `down()` order is safe: FK, then index, then column, and the guard runs before any DDL.
  - Partial-failure handling is covered by B5/B11.
  - The 000005 `down()` guard is tested.
- **Logic:**
  - The guard order is unsaved, then org, then lookup, so nothing is written on a mismatch.
  - The key is `(project_id, user_id)`, so legacy quote-only rows are never touched.
  - `scopeVisibleTo` filters `projects.org_id`, `project_members.org_id` and `is_active`, and excludes soft-deleted projects through the SoftDeletes global scope.
- **Mutations (scratch copy, real source unchanged):**

| Mutation | Result |
|---|---|
| Org guard removed | killed (3 tests fail) |
| Reactivation condition widened | killed (2 fail) |
| `UniqueConstraintViolationException` catch removed | killed (3 fail) |
| 000004 `down()` guard neutralised | killed (1 fail) |
| `sharedLock()` removed | survives (sqlite; gate) |
| `exists` guard removed | survives (finding 1) |

- **Scope:** nothing outside Phase 1 is in the commit. `RbacTestCase` only registers the new migrations plus the existing plan_crosswalk one. No Known open item is touched.

### Verdict

I do NOT confirm "no missing edge cases / no regression risk" without qualification.

- **Regression risk for a Phase 1-only deploy: none found.** I confirm this.
- **Edge cases and tests:** the code has no wrong result I could construct, but finding 1 is a real test gap (the A6 guard has no effective test). Under strict mode this goes to the Orchestrator, then the Architect, for a PLAN.md test-task update. Findings 3 and 4 are optional Architect notes.

### Production gates (not defects)

B1 (MariaDB gate on a restored production copy, including the B2 two-session check and real FK enforcement), B5 pre-flight and recovery, B12 file-diff check before FTP upload, and a fresh backup. None of these can be exercised on sqlite.

Handoff: findings to the Orchestrator, then the Architect.

## Architect compliance re-check — step 2f

Verdict: **COMPLIANT** with B14.2.

- `app/Models/Rbac/ProjectMember.php` diff is one added guard, `if ($project->trashed())`, throwing `InvalidArgumentException('Cannot enrol a member on a deleted project.')`.
- Guard order is A6 (`! exists`), then trashed, then B4 org mismatch, then the lookup. The trashed guard sits after A6 and before B4, as specified.
- `trashed()` is correct because `Project` uses `SoftDeletes` (`Project.php:14`). The message differs from both the A6 and B4 messages, so tests can tell the guards apart.
- Nothing else changed in `app/`. The signature and the A1/A2/B2/B4 logic are untouched.
- Other modified files are only `tests/Feature/Rbac/ProjectSchemaTest.php` (O1/O2 and step 3 test work) and `.claude/agents/architect.md` and `.claude/agents/reviewer.md`. Nothing unexpected.
- Callers: `grep enrol(` finds none in `app/`, `resources/`, `routes/` or `database/seeders/`. Nothing can break.
- Tests: the B14.2 test and the B14.1 mutation proofs are QA's step 3 tasks and are not part of this check. The RBAC suite still runs; the run's failures should be only the 3 known baseline ones.
- Non-blocking: the uncommitted change still needs a commit, and the B14.2 test must be confirmed by mutation.

## QA results — Phase 1 B14

Rbac suite: 96 passed / 3 failed (known baseline by name: AuditMiddlewareTest "enforce mode blocks and logs", SeedMatrixTest "seeds expected counts", SeedMatrixTest "phase distribution"). No new failures, no defects. ProjectSchemaTest: 41 passed. Only `tests/Feature/Rbac/ProjectSchemaTest.php` edited (in-memory sqlite). `app/Models/Rbac/ProjectMember.php` shows in `git diff` only as the Developer's uncommitted step 2f guard (4 lines); QA did not touch it.

Coverage
- B14.1: `test_enrol_unsaved_project_throws_and_leaves_legacy_row_untouched` now uses `new Project(['org_id' => $org])`, asserts the exact A6 message, and that the member rows (including an inactive legacy quote-only row for the same user) are identical before and after.
- B14.2: soft-deleted project throws the exact "deleted project" message; existing member row and legacy row unchanged; no row created when none existed. Guard order: unsaved+org mismatch -> A6 message; unsaved+trashed -> A6 message; trashed+org mismatch -> trashed message; live+org mismatch -> B4 message.
- B14.3: `test_legacy_quote_id_fk_and_unique_survive_change` (FK on quote_id -> quotes on both tables, unique(quote_id,user_id), index(org_id,quote_id)), FK rejects an unknown quote_id on both tables, force-deleting a quote cascades legacy member and crosswalk rows, legacy unique enforced. sqlite proves the schema state after Laravel's table-rebuild change() plus FK enforcement (PRAGMA asserted). It cannot prove that MariaDB's CHANGE ... NULL keeps the FK or the real ON DELETE; that stays with B1 steps 4/6 and the SHOW CREATE TABLE comparison.

Mutation proof (scratch copy under the scratchpad; the guard's `if (...)` replaced by `if (false)`; copy baseline was 41/41)

| Guard removed | Tests that failed |
|---|---|
| A6 `! $project->exists` | unsaved project throws and leaves legacy row untouched; guard order unsaved before org mismatch; guard order unsaved before trashed (3 failed) |
| `$project->trashed()` | soft deleted project throws and writes nothing; soft deleted project with no member creates no row; guard order trashed before org mismatch (3 failed) |
| B4 org mismatch | mismatched org on existing row; on new member; on inactive row; org mismatch on live project reports org error (4 failed) |

The real repo was not modified by the mutation runs.

## Verifier final pass — Phase 1 (Sonnet)

Evidence: `php artisan test --filter=Rbac` gives 96 passed and 3 failed (the TESTING.md baseline). ProjectSchemaTest is 41/41. I ran mutations on a scratch copy (now deleted), and the tracked files are unchanged from the working-tree state I was given.

### My earlier findings

| # | Finding | Status | Evidence |
|---|---|---|---|
| 1 | The A6 test did not guard the `exists` check | RESOLVED | The test now uses `new Project(['org_id' => …])` and asserts the message. With the guard neutralised, 3 tests fail. |
| 3 | `enrol()` accepted a soft-deleted project | RESOLVED | `trashed()` guard added at `ProjectMember.php:45`. With it neutralised, 3 tests fail. Guard-order tests exist (unsaved, then trashed, then org). |
| 4 | No test that the legacy `quote_id` FK and unique survive `->change()` | RESOLVED | FK-present, FK-rejects-unknown-quote, cascade and legacy-unique tests exist for both tables. |
| 5 | `project_id` name collision | RESOLVED for Phase 1 | Recorded in PLAN B14.4 as a Phase 4 step 10 requirement. |
| 2 | `sharedLock()` not covered on sqlite | OPEN, as expected | Removing it leaves 41/41 passing. This is only provable at gate B1 step 5, not a defect. |

Org-guard mutation: 4 tests fail.

### Re-derivation of the final `enrol()` and `scopeVisibleTo`

I found no sequence that yields a wrong result. The guard order is unsaved, trashed, org, then lookup. The key is `(project_id, user_id)`, so legacy rows are never touched. An inactive row is reactivated with a full grant. The race path re-selects under `sharedLock`.

New in the last round:
- A 4-line guard and tests only. Nothing out of scope, and no other application files changed.

Residual note (no action in Phase 1):
- `trashed()` checks the in-memory instance. A project deleted elsewhere after it was loaded would pass. Phase 4 callers should load the project inside the same request and transaction. This is not a Phase 1 defect.

### Verdict

I CONFIRM no missing edge cases and no regression risk for Phase 1 code and tests, within sqlite's limits. No PLAN.md or code change is needed. B1, B5, B12 and the backup remain gates, not defects.

## Verifier review — Phase 2 specification

Scope: PLAN.md Amendment 3, C1-C13 (L776-1135), the step 4 and step 14 pointers, and the runbook (L293), A4 and Phase 5 for consistency. I read the live code and did not run any command or write any file other than this review. Line numbers below are PLAN.md lines.

### Factual check of C1 (all confirmed against live code)

- `quotes` columns are as C1 states: `name` and `project_name` are nullable strings, `project_address` is nullable text, `quote_number` is unique, `deleted_at` exists, and `user_id` is a cascade FK.
- There is no `quotes.org_id`, which is consistent with Q7's "the store path can't succeed today".
- `user_org_roles` has unique `(user_id, org_id, role_id)`, so orgs must be counted DISTINCT. It has no soft delete.
- `Organization` and `User` have no soft delete. `Quote` does.
- `withCommands()` is called by `Application::configure()` (`Application.php:245`), with the default path `app/Console/Commands`. That directory does not exist yet, so the command creates it.
- `app/Support` holds `QuotePdfPresenter.php` and an `Rbac` folder.
- `mbstring` is present and `intl` is absent, as C1 states.
- The test stub `quotes` (`RbacTestCase.php:58-68`) has `created_at`, `updated_at` and `deleted_at` but no `project_address`, as C1 and C11 state.
- The controller copy behaviour behind H1 is accurate: `QuoteController.php:307-321` and `:636` set `project_name = request->project_name ?: estimateLabel`.

### Findings (most severe first)

**F1 — blocking. C5 (L904) contradicts C4 step 4 (L849, L856) on overrides for already-attached quotes.**
- Evidence: C5 says an override row for an already-attached quote is ignored with a warning. C4 step 4 says the sibling key of the owner's attached quotes is computed "using the same overrides", and the operator must pass the same file on every run.
- Impact:
  - Take the worked example (L911-928). Quote 103 is attached to "Main St – Phase 2" under key `main st phase 2`.
  - If the override is ignored, 103's recomputed key becomes `main st`, so the key `main st` maps to two projects (P1 and P2).
  - The next straggler named "Main St" then gets `ambiguous_existing_project` and is stranded.
  - Quote 104 (`Estimate`) attached by an override key has the same problem in the other direction.
  - Idempotency is safe but wrong, and a Developer has to guess.
- PLAN change:
  - State that overrides apply to key computation for attached quotes (siblings) but never cause re-attachment.
  - The "attached quote, override ignored" warning must not fire when the row is used for sibling keys, or the warning must be worded accordingly.
  - Add a test: run 1 with a merge and a split override, then a straggler in run 2 with the same file joins the right project and gets no ambiguity.

**F2 — should-fix. The group key namespace can collide (L845-846).**
- Evidence: a singleton key is the string `'#q' . id`, and named keys are `normalize(project_name)`. A quote named `#Q5` normalizes to `#q5` and merges with quote 5's singleton (same owner and org). An override `project_key` can do the same.
- Impact: it merges a real project with an unnamed quote of the same owner. That is contrived, but "unnamed quotes are never merged" is then not guaranteed.
- PLAN change: make the key a typed tuple (`kind = singleton|named`, value), or prefix named keys, and add a test with a `#q<id>` project name.

**F3 — should-fix. Under-specified ordering, tie-breaks and NULLs (C4 steps 5-6). A Developer would improvise.**
- The sort order of groups within an owner.
- `updated_at` NULL in "most recently updated" for the name and address choice (`id desc` is only the second tie-break).
- `granted_at` NULL on legacy rows when picking the "earliest active row".
- `created_at` NULL when picking the "earliest" (L861 says only `now()` if every one is null).
- The owner's `granted_at` when every quote `created_at` is NULL.
- Which quotes count as "attached quotes" in the sibling lookup: trashed ones or not.
- PLAN change: state "NULL sorts last / is ignored" for each, and say that attached trashed quotes are included in the sibling lookup (as C4 step 4's trashed-project rules imply).

**F4 — should-fix. The C2 precondition test is optional (test 27, L1070).**
- Evidence: the schema precondition is the guard for the B11 half-migrated state, and there is no mutation-table entry for it (L1103-1118).
- Impact: this is the only protection against writing new-format rows when `quote_id` is still NOT NULL.
- PLAN change: make test 27 mandatory. Add a mutation row (remove the precondition; test 27 must fail). Also extend the precondition to check that the columns the command reads exist (`quotes.project_address`, `deleted_at`), since the test stub lacks `project_address`.

**F5 — should-fix. Missing or weak tests and mutation-table gaps.**
- No test for the `concurrent_change` set comparison. It is listed as an unresolved reason but nothing exercises it, and `FOR UPDATE` is a no-op on sqlite (B1 covers only locking).
- No test for "exactly one trashed project and every quote in the group trashed: reuse it" (L854).
- No test for (iii) when the legacy org is not among the owner's active orgs.
- No mutation entries for: `existing_untouched`, address choice, the trashed-project-reuse rule, `concurrent_change`, and the C2 precondition.
- Test 33 (exit 3) is allowed to be skipped. The mutation "remove the final `rollBack()`" is still killed by test 23 only if test 23 asserts the DB counts after the run and the exit code. Say so explicitly.
- PLAN change: add these tests and mutations to C12/C13.5, or record why sqlite cannot cover each.

**F6 — should-fix. CSV injection and PII in reports (C7, L941-959).**
- Project names, addresses and override text are client-controlled and are written to CSVs that a human will open in a spreadsheet. A value starting with `=`, `+`, `-` or `@` is an injection vector.
- PLAN change: prefix such values with `'` in report files only (never in the DB), or state the reports are text-only and must not be opened in Excel. Also state that the report directory is created with mode 0700 and that the run does not log names or addresses to `storage/logs`.

**F7 — should-fix. The `--force` versus `--no-interaction` runbook conflict (L802-803, L989).**
- C8 tells the operator to run with `--no-interaction`, and C2 exits 2 in production without `--force` (a confirm under `--no-interaction` returns its default, "no").
- Impact: the documented command declines in production. Also, if production's `APP_ENV` is not literally `production`, the prompt never appears.
- PLAN change: the runbook line must say `--force --no-interaction`, and the pre-flight must confirm production's `APP_ENV`.

**F8 — note. Rollback wording does not match A4.**
- C9 step 4 omits the check that `plan_crosswalk WHERE quote_id IS NULL` counts 0, which A4 (L436-441) includes.
- C9 3.2 restores only crosswalk rows with `quote_id IS NOT NULL`, which is correct only before Phase 4.
- PLAN change: add the missing check to C9 step 4.

**F9 — note. The real run has no maintenance-mode guard.**
- C8 restricts the dry-run to the B1 copy or a maintenance window. The real run (L293) is documented inside `php artisan down` but the command does not check it.
- Rows added by old code to an already-attached quote after run 1 (a crosswalk row, or a legacy member add or removal) are never picked up. Their `project_id` stays NULL, because only unattached quotes are in scope.
- Impact: none inside the runbook window, because the site is down. It becomes a silent loss if the run is ever done while the site is live.
- PLAN change: add an operator rule ("real run only under `down`") and optionally a warning line when `app()->isDownForMaintenance()` is false.

**F10 — note. H2 rows stay NULL forever.**
- Crosswalk rows with `org_id != project.org_id` are reported but not linked, so they are invisible after Phase 4 and never retried.
- Exit code stays 0. The Phase 5 gate ("crosswalk conflicts resolved", L248) covers this, but the runbook step 6 exit-0 check does not.
- PLAN change: include the unlinked count in the console summary and in the runbook's review step.

**F11 — note. Normalization is safe.**
- `\p{Z}` covers NBSP. Zero-width characters (U+200B, category Cf) are not collapsed. This is safe (it splits, never merges).
- Doing all grouping in PHP avoids MariaDB collation differences. I found no equivalence gap.

### Answers to the requested checks

- **Data safety:**
  - I found nothing that widens access beyond the decided B4 and widening-report behaviour.
  - Foreign owners are never merged (owner is in the key, plus C13.1).
  - Writes are query-builder only, so there are no model events or `updated_at` bumps.
  - Dry-run runs the same path inside one outer transaction with a final `rollBack()` and a count check. It is only safe on the B1 restored copy or under maintenance (locks are held for the whole run), as C8 says.
  - Auto-increment gaps from the rolled-back dry-run are harmless.
  - Per-group transactions are correct. Partial failure leaves complete groups only.
- **Consistency:** the only substantive contradiction is F1. Step numbering is otherwise consistent (step 4 points to Amendment 3, and step 14 points to C12).
- **The spec must forbid (add to C11):**
  - Any change to Phase 1 models or migrations.
  - Any Eloquent write on `quotes`, `plan_crosswalk` or `project_members`.
  - `enrol()`.
  - Any new config or command option beyond the C2 signature.
  - Any legacy `project_members` write.

### Verdict

NOT READY FOR DEVELOPMENT as written.

- F1 blocks: a Developer would have to choose between two contradictory rules, and it affects idempotency.
- F2-F7 are should-fix. Route all of them to the Architect for a PLAN.md update.
- F8-F11 are notes.

After the Architect fixes F1 and F2-F7, the spec is otherwise precise, factually accurate against the live code, and safe on data. No factual error found in C1.

## Verifier re-review — Phase 2 specification (after C14)

Read-only review. I re-read C1-C14 (PLAN.md L776-1230), the runbook (L293), B6 step 6 (L612), A1, A4, B4, B5, B11 and Phase 5 (L248) for consistency. I re-derived the C5 worked example by hand under C4/C5/C14 and checked `storage/app/.gitignore` (it ignores `*`, so `storage/app/backfill/` is git-ignored).

### Status of F1-F11

| # | Original | Status | PLAN.md evidence |
|---|---|---|---|
| F1 | C5 contradicted C4 step 4 | RESOLVED, with two residual defects (N2, N3) | L850, L907, L933-937, L1172 |
| F2 | Group-key collision | RESOLVED | L844-849, L1174 |
| F3 | Ordering, NULLs, clock | RESOLVED, with one residual (N7) | L859-865, L875 |
| F4 | Optional precondition test | PARTIAL | L801, L1088 |
| F5 | Missing tests and mutations | PARTIAL | L1180-1208 |
| F6 | CSV injection and PII | PARTIAL | L969-974, L1185 |
| F7 | `--force --no-interaction` | RESOLVED for C2/C8/B6 step 6; contradicted by N1 | L803, L1006, L293, L612 |
| F8 | Rollback wording | RESOLVED | L1017 |
| F9 | Live-run guard | PARTIAL: it creates a new blocking contradiction (N1) | L804, L1216-1220 |
| F10 | Unlinked crosswalk rows | RESOLVED (H4 provisional default, exit 0) | L985, L1222-1226 |
| F11 | Forbidden list | RESOLVED | L1047 |

### F1 adversarial re-derivation

I traced the example step by step.
- **Run 1.** Rows 101 and 102 give key `(7,42,named,main st)`. Row 104 has override key `main st`, which takes precedence over its `Estimate` name, so it joins that group. Row 103 gets its own key. Result: P1 = {101, 102, 104} and P2 = {103}.
- **Run 2, same file.**
  - The sibling keys of the attached quotes are 101 `named:main st`, 102 `named:main st`, 104 `named:main st` (override) and 103 `named:main st phase 2` (override).
  - So `named:main st -> {P1}` and `named:main st phase 2 -> {P2}`.
  - Straggler 106 joins P1. Straggler 107 (override key) joins P2. Straggler 108 (`Main St - Phase 2`) creates a new project. Without the file, 106 is `ambiguous_existing_project` and is not attached.
- **Foreign owner.** The sibling map is per owner and filtered by `created_by = owner`, so it cannot merge foreign quotes.
- **Second run.** Only unresolved quotes remain in scope, so the counts for created, attached, inserted and linked are zero. Attached quotes are never re-attached.
- **Wrong `ambiguous_existing_project`.** It can fire only when a key maps to two projects, and that is safe.
- **Key collision.** `named:#q5` and `singleton:5` are distinct tuples.
- **Mutable names.** Sibling keys come from mutable quote names. A rename after run 1 can only make a key ambiguous or create a new project (safe), never merge a foreign owner's quote. This is acceptable inside the runbook window.

No counterexample to the "wrong join, foreign merge, second run changes rows" cases. Two defects remain (N2, N3).

### New and remaining findings (most severe first)

**N1 — blocking. The H3 guard contradicts the runbook.**
- Evidence: C2 precondition 4 (L804) refuses a real run unless the app is in maintenance mode or `--allow-live` is given.
- The runbook (L293) and B6 step 8 (L613) run the straggler backfill after `php artisan up`, with no `--allow-live`.
- C14.9 (L1218) claims "none in the documented runbook, which already runs under down". That is false for the straggler run.
- Impact: as written, the documented straggler run exits 2. An operator would either add `--allow-live` ad hoc or skip the run.
- PLAN change:
  - Run the straggler pass before `php artisan up` (still under `down`, so H3 holds), or
  - Document `--allow-live` explicitly for that step, with the reason.
  - Fix the C14.9 claim. State `--force --no-interaction` on that command too.
  - Say whether the straggler run is still meaningful given that Phase 4 code sets `project_id` natively.

**N2 — should-fix. Override warnings are noisy and inconsistent with the worked example.**
- Evidence:
  - The worked-example file (L921-927) has `org_id` 7 on every row.
  - C5 (L907) warns `attached_quote_org_or_name_ignored` for any non-empty `org_id` or `project_name` on an attached quote.
  - So run 2 with "the same file" warns on 101-104, while test 34 and C14.1 say "zero warnings for key-only rows".
- Impact: the operator learns to ignore warnings. The `source` values allowed in `warnings.csv` (L963) don't cover the new message.
- PLAN change: warn only when the override's `org_id` differs from `projects.org_id`, or `project_name` is non-empty and differs from the project's name. Add the source value.

**N3 — should-fix. Test coverage of the F1 rules is incomplete.**
- Evidence:
  - Test 34 has no override on an attached quote that names a different key or org than the project has. The mutant "let an override attach or move" (L1197) is killed only by test 9 (L1065), yet the table lists only 34.
  - Nothing kills "use the override `org_id` for the sibling key" (`org_id` must equal `projects.org_id`, L850).
  - Nothing kills "capture `$runAt` per call" unless time is frozen.
  - Nothing tests group processing order (ascending lowest quote id).
- PLAN change: add an attached row with a mismatching `org_id` and key to test 34 or 9. Add the mutants above. Name test 9 in the "Killed by" column. Freeze time (`Carbon::setTestNow`) and assert the same `$runAt` everywhere.

**N4 — should-fix. Test 38 (`concurrent_change`) is not implementable as worded (L1182).**
- Evidence: the hook fires "right after the planner's crosswalk read", but C4 step 1 loads the attached quotes after it. A quote attached at that point would be seen in the sibling read and change the plan before the write phase.
- PLAN change: hook the start of the group's transaction (for example `Event::listen(TransactionBeginning::class)` on the first group), which is after planning and before the `FOR UPDATE`.

**N5 — should-fix. Test 43 can leave the app in maintenance mode.**
- Evidence: activating maintenance mode writes `storage/framework/down` in the real storage path.
- PLAN change: require mocking `app()->maintenanceMode()->active()` (or a temp path), with cleanup in `tearDown`, so a crash cannot leave the developer's app down.

**N6 — should-fix. Report-handling gaps.**
- Escaping vs verbatim copy: L973 escapes "override text" in every cell, but `overrides.csv` (L965, test 46) is a byte-for-byte copy. State that `overrides.csv` and `summary.txt` are exempt.
- `fputcsv` default escape: the default escape character mangles values with a backslash before a quote. Specify the empty escape parameter, or accept lossy reports.
- Console leak: C5 (L912) lists fatal override rows "on the console with its line number". State that only the line number and reason are printed, never the values (L972 says the console shows counts, ids and paths).
- `umask(0077)` is process-global: restore it in a `finally` so later tests are not affected.
- Retention (H5) stays a human decision.

**N7 — note. F3 residuals.**
- Whitespace-only `project_key` after normalization is `''`: state whether "non-empty" means before or after normalization (a `named:` group with an empty value would merge unrelated quotes).
- Row order inside `memberships.csv`, `unresolved.csv` and the other files is undefined, so tests must not assert order.

**N8 — note. F4 is only partly covered.** The precondition list (L801) checks the columns read but not the columns written: `projects.*` and `project_members.created_at/updated_at`. Add them.

**N9 — note. Consistency with A1, A4, B4, B5, B11 and Phase 5.** I found no contradiction.
- C9 step 4 is a superset of A4's checks.
- B11 precondition wording matches C2 precondition 1.
- Phase 5's gate on `unresolved.csv` and crosswalk conflicts is consistent with H4.

### Developer file list (C11, L1044-1049)

It is still exactly right: `BackfillProjects.php` and `ProjectBackfillPlanner.php` only, with the new `--allow-live` option inside C2's signature. `app/Console/Commands` is auto-discovered, so no registration file is needed, and `RbacTestCase.php` needs no change.

### Verdict

NOT READY FOR DEVELOPMENT. N1 is a blocking contradiction between H3 and the runbook straggler run, and it must be resolved through the Architect. N2-N6 should be folded into the same PLAN pass. After that, I consider F1, F2, F3 and F8 closed and the spec implementable.

## Verifier third review — Phase 2 specification

Read-only. PLAN.md is now 1257 lines. I did my own grep of PLAN.md for `down`, `up`, `--force`, `--no-interaction`, `--allow-live`, `--overrides`, `--dry-run`, `maintenance`, `straggler`, `verification` and `projects:backfill`, and I read the surrounding text at each hit.

### (1) Status of N1-N9

| # | Status | Evidence |
|---|---|---|
| N1 | PARTIAL | The order is now consistent (see section 2). The verification run (L293, L614, L1247) has no defined next step when it finds changes. See N10 and N12 below. |
| N2 | RESOLVED | L907 and L1174 give the warning rule (warn only on a mismatch with the project), and the worked-example file is silent on re-run. |
| N3 | RESOLVED | The mutation table L1197-1225 has entries for sibling org, `$runAt` (test 47), group order (test 48), and test 9 is named for the attach/move mutant. |
| N4 | RESOLVED | L1182 hooks `TransactionBeginning` (after planning, before the `FOR UPDATE`) and says to use a real run, not dry-run. |
| N5 | RESOLVED | L1189 binds a fake `MaintenanceMode` contract. I verified `Application::isDownForMaintenance()` resolves that contract (`Application.php:1390-1403`) and the test never writes the real `down` file. |
| N6 | RESOLVED | L969-975: `overrides.csv` is exempt from escaping, the `fputcsv` escape is empty, `umask` is restored in a `finally`, the console prints no values. Tests 41 and 46 assert these. |
| N7 | PARTIAL | The rule "a whitespace-only `project_key` is fatal" appears only in the addendum (L1253) and the mutation table (L1206). C5 (L909-911) and test 9 (L1065) were not edited, so a Developer reading C5 would not see it. See N11. |
| N8 | RESOLVED | L801 now also checks every column the command writes. |
| N9 | RESOLVED | No change was needed. |

### (2) Consistency of real-run order and flags (lines I checked)

| Lines | Statement |
|---|---|
| 293 | Runbook: `down`, dry-run, real run with overrides, code upload, verification run under `down`, then `up`. All backfill calls carry `--force --no-interaction`. |
| 612, 614 | B6 steps 6 and 8 agree with L293. |
| 798, 803, 804 | C2 signature, precondition 3 (`--force --no-interaction`) and H3 precondition 4. |
| 857 | The operator rule now says "including the verification run". |
| 1001, 1008 | C8: dry-run on live production only in maintenance mode; the real run under `down` with `--force --no-interaction`. |
| 1229, 1247 | C14.9 and C14.12 state the decision. |
| 434-443 | A4 (rollback) does its own `down` and `up`. |
| 248 | Phase 5 gate: no backfill statement. |

These agree with each other, and no line contradicts the H3 guard, with these exceptions:
- **A4 L443:** "re-running `projects:backfill` restores `plan_crosswalk.project_id`". A4 ends with `up` at step 6. A re-run after that hits the H3 guard (exit 2), and it needs `--force --no-interaction`.
- **The same sentence is also wrong on substance (N13).**

### (3) Trying to break the N1 logic

- **Can old code create an unattached quote under `down`?** In this repo, no.
  - The only `Quote::create` calls are in `QuoteController` (HTTP: L223, L316, L552).
  - There is no `app/Jobs`, no scheduler entry (`routes/console.php` holds only `inspire`), no `routes/api.php`, and `app/Imports` and `app/Services` create no quotes.
  - `QUEUE_CONNECTION=database`, but queue workers and scheduled tasks skip work in maintenance mode by default.
  - The remaining routes in are tinker or a manual DB write.
- **`down --secret` bypass.** `isDownForMaintenance()` stays true for a bypass-cookie holder, so the guard proves only that the flag is set, not that no traffic arrives.
  - The smoke check (B6 step 8) needs that bypass. It runs after the verification run, and the new code sets `project_id` natively, so this is safe.
  - The plan never mentions `--secret`. State that nobody uses the bypass before the code upload (step 7).
- **What the verification run does.** It is a real, writing run. If it finds unattached quotes it attaches them silently, which defeats its purpose as a check.

### New and remaining findings (most severe first)

**N10 — should-fix. The real run is never dry-run with the final overrides file.**
- Evidence: L293 and L612 run the dry-run without `--overrides`, then "fill in overrides", then the real run with them.
- Impact: the reviewed reports (groups, widening, unresolved) belong to a different plan than the one executed. Override rows change grouping and membership.
- PLAN change: add a second dry-run with the final overrides file before the real run, and review its reports.

**N11 — should-fix. Undefined next step for the verification run.** State what "0 changes" means (C6 acceptance: created, attached, inserted and linked all 0, plus exit 0). State what happens otherwise:
- Do not run `up`. Keep the site down.
- Do not re-run blindly. Route to the Architect and the human.
- Consider making the verification run `--dry-run`, which cannot write, and read the counts.
- Also copy the whitespace-only `project_key` rule into C5 and test 9 (N7).

**N12 — note. `--secret` and smoke-check wording.** See section 3.

**N13 — should-fix. A4 L443 contradicts C4 and C14.9.**
- Evidence: after the A4 partial revert, `migrate` re-adds an empty `plan_crosswalk.project_id`, but quotes stay attached (`quotes.project_id` remains). The backfill scope is unattached quotes only (C4), so it never relinks those crosswalk rows.
- Impact: the recovery sentence is false, and the crosswalk links would be lost.
- PLAN change: correct L443 (restore from backup, or a documented manual `UPDATE` from `quotes.project_id`), and say any re-run happens under `down` with `--force --no-interaction`.

### (4) Tests 47-48 and the mutation table

Both are load-bearing.
- Test 47 advances the clock inside each group's `TransactionBeginning`, so a per-write `now()` mutant fails.
- Test 48 kills descending iteration.
- The table's "Killed by" column is consistent with tests 9, 34, 36-43. Test numbering lists 47-48 before 46, which is cosmetic.
- N2-N6 text is consistent with tests 34, 38, 41, 43 and 46.

### (5) New contradictions

Only N13 (pre-existing, now exposed) and the N7 partial edit.

### Verdict

NOT READY FOR DEVELOPMENT, narrowly. Nothing in the algorithm blocks it. The blockers are PLAN-text only, and all route through the Architect:
1. N10 (dry-run with the final overrides file).
2. N11 (defined next step for a verification run with changes, and the C5/test 9 text for N7).
3. N13 (A4 L443).

The spec is otherwise implementable and safe. After those three edits I would treat it as READY FOR DEVELOPMENT with no further re-review needed.

## Architect compliance pass — Phase 2

Scope: `app/Support/ProjectBackfillPlanner.php` (670 lines) and `app/Console/Commands/BackfillProjects.php` (791 lines), both uncommitted, against PLAN.md Amendment 3 (C1-C14.13). `git status` shows only these two files plus `.claude/tasks/`. `php -l` is clean on both. I did not run the command or any DB command.

**Verdict: COMPLIANT.** No blocking deviations. The six findings below are non-blocking. Findings 1-3 are gaps or ambiguities in the spec text, so I add wording for them (PLAN update needed). Findings 4-6 are implementation notes for QA.

### Section-by-section result

- **C2 (signature and preconditions):**
  - The signature includes `--allow-live` (H3, `BackfillProjects.php:14-18`).
  - The order is schema check (`:64`), overrides validation (`:76-99`), production confirm unless `--force` (`:101-107`), then the H3 guard (`:109-114`).
  - `--dry-run` is unaffected by H3 (`:115-117`, warning only).
  - Exit codes 0/1/2/3 are at `:158`.
  - No report directory is created before the preconditions pass.
  - The schema check covers every column read and written, plus nullable `quote_id` (`:26-32`, `:191`).
- **C3:** `normalize()` uses `\p{Z}`, mb lowercase, and no accent folding (`Planner.php:16-26`). Invalid UTF-8 counts as unnamed with a `normalize` warning (`:233-235`, `:86`).
- **C4:**
  - Org resolution order (i)-(iv) is at `Planner.php:252-272`.
  - The group key is the typed tuple `[org, owner, kind, value]` (`:98-128`).
  - The sibling lookup is limited to projects with `created_by = owner`, includes trashed attached quotes, and uses `projects.org_id` (`:325-360`).
  - `decideSibling` covers ambiguous, trashed and reuse (`:365-389`).
  - `$runAt` is computed once (`Command:125`).
  - NULL-last ordering is in `cmpAsc`/`cmpDesc`, and names are truncated with `mb_substr`.
  - B4 re-home vs mismatch, owner explicit removal, `existing_untouched` and the org invariant are at `:539-546` and `:591-601`.
  - Groups are processed in ascending lowest quote id (`:57`, `:127-147`).
- **C5:**
  - The BOM is stripped after the hash (`Command:84,205`).
  - The header is required.
  - Trimmed blank cells are fatal (`:242`), including a whitespace-only `project_key`.
  - Duplicate `quote_id`, unknown quote, `org_without_role` and empty rows are fatal.
  - Console errors print the line and code only (`:93-95`).
  - An attached-quote override feeds only the sibling key (`Planner:333`), and warnings follow the N2 rule (`:73-78`).
- **C6/C7:**
  - All 11 report files and headers match C7 (`Command:34-46`).
  - `fputcsv` uses an empty escape (`:736`), and formula escaping is applied to client-text cells only (`:739-750`).
  - The `overrides.csv` byte copy and the SHA-256 over raw bytes are at `:84,715-719`.
  - The directory is 0700 and files are 0600. The umask is restored in `finally` (`:695-722`).
  - There is no logging of values.
- **C8:**
  - Per-group `DB::transaction` (`:514`) gives failure isolation and `errors.csv`.
  - Dry-run uses one outer transaction, a `finally` rollback, and a 4-count check giving exit 3 (`:126-158`).
  - Chunking is used everywhere. The summary has the H4 line always printed, with exit 0 for mismatches (`:769`).
- **C9:** it is runbook text only, with no code counterpart. There is no relink and no extra option.
- **Amendment 1:** there are no Eloquent models, no `enrol()`, and no writes outside `projects`, `project_members` (inserts only), `quotes.project_id` and `plan_crosswalk.project_id`. Every write uses the query builder, so `updated_at` isn't bumped. Legacy member rows are never modified.
- **Hand trace of the C5/C14 worked example:**
  - Run 1: 101, 102 and 104 group into `named:main st` (104 via its override key, `Estimate` ignored), 103 gets its own group and the override name, and 105 is `multi_org_no_consensus` (exit 1).
  - Run 2 with the same file: the sibling map is `main st -> {P1}` and `main st phase 2 -> {P2}`. 106 reuses P1, 107 (override key) reuses P2, 108 (hyphen) creates a new project, and there are zero warnings.
  - Without the file, 103's key reverts to `main st`, the key maps to {P1, P2}, and 106 is `ambiguous_existing_project`.

### Rulings on the Developer's six self-resolved ambiguities

1. **`candidate_merges.csv` lists any bucket with at least one H1-unnamed quote, even a group of one. ACCEPT.** Add to C4 H1: "`candidate_merges.csv` lists, per owner and org, every bucket keyed by normalized name that contains at least one quote made unnamed only because `project_name` equals `name`, including buckets of a single quote. The bucket also lists ordinary named quotes with the same normalized name, so the human sees what option B would merge. Quotes with an override key are excluded."
2. **Member write and report order is by lowest legacy source row id, owner without a legacy row last, then user id. ACCEPT.** Add to C4 step 6: "Members are inserted and reported in ascending order of their lowest source legacy row id, an owner with no legacy row last, ties by user id."
3. **"Line" in override errors is the CSV record number, with the header as record 1. ACCEPT.** Add to C5: "`line` is the CSV record number (header = 1, blank lines counted). It equals the physical line number unless a cell contains a newline."
4. **Invalid UTF-8 in an override cell is fatal (`invalid_utf8`), and two override names conflict when their trimmed strings differ exactly. ACCEPT.** Add to C5: "An override cell that isn't valid UTF-8 is fatal (`invalid_utf8`). Two override `project_name` values in one group conflict when they differ after trimming, compared exactly (case and accents count)."
5. **Chunk size is 400, not 500. ACCEPT (and it is correct).** With 500 ids plus the `SET` binding, the update statement would have 501 bindings, which breaks the "no statement above 500 bindings" assertion (test 31). Replace "chunked at 500" (C13.2) with: "chunk size 400, so no statement exceeds 500 bindings including the `SET`/other bindings. Inserts use 50 rows per statement."
6. **Override-name conflicts are found by a read-only planning pre-pass over all owners before any write. ACCEPT.** This is required to meet "fatal error, exit 2, nothing written". Add to C5: "Override-name conflicts are detected by a read-only planning pre-pass over all owners before the confirm and before any write. It doubles the read cost of a run; the dry-run on the B1 copy sizes it."

### Findings

1. **Non-blocking — `Planner.php:68-79`, `Command:461`, C5/N2.** Attached-quote override warnings are only emitted while processing owners that still have unattached quotes, because the loop runs over `ownerIds()`. An override on an attached quote of an owner with nothing left to attach never warns. Harmless, since there is nothing to group. PLAN update: add to C5 "warnings for attached overrides are emitted only for owners that still have unattached quotes".
2. **Non-blocking — `Command:298`, C5.** The `org_without_role` check is applied only to unattached quotes. That is consistent with the rule that an attached quote's `org_id` is ignored. PLAN update: add "the `org_id` role check applies to unattached quotes only".
3. **Non-blocking — `Planner.php:425-431`, C4 step 5.** An address that is invalid UTF-8 normalizes to `''` and is silently ignored, with no warning. Edge case. PLAN update: add "an invalid UTF-8 address is ignored and gets a warning", or accept as is.
4. **Non-blocking, QA note — `Command:87-89`.** The pre-pass calls `loadContext` for every owner, so its warnings are discarded and read cost is doubled. Correct as specified; sqlite tests should not assert warnings from the pre-pass.
5. **Non-blocking, QA note — `Command:162-168`.** If an exception is thrown after the report handles are opened (outside the guarded `processOwners`), the handles are not closed. Not reachable in the normal paths.
6. **Non-blocking, QA note — `Command:526-538`.** A `concurrent_change` unresolved row is written after the transaction returns `null`; the group's counts stay out of `created`/`attached` as intended. Test 38 must assert exactly this.

### Not verified (sqlite or gate)

Locking, savepoint and undo behaviour in the long dry-run transaction, the timezone of `now()` vs DB timestamps, peak memory at production volume, and TIMESTAMP conversion stay with the B1 dry-run and the real run on the restored copy.

PLAN.md updates are needed only for the wording in rulings 1-6 and findings 1-3. No code change is required.

## Architect compliance re-check — Phase 2 H6

Verdict: **COMPLIANT**.

- **Row shape and order:** `ProjectBackfillPlanner.php:88-90` adds `['normalize', $qid, 'project_address_invalid_utf8']` immediately after the `project_name_invalid_utf8` warning, in the per-quote loop before org resolution. This matches C4 step 5, C15.8 and test 51. `Command:472-475` writes and counts it in the existing `warnings` counter.
- **No value written:** the row carries only the quote id and a fixed message. Address values reach `address`, `address_conflicts` and reuse data only through `normalize`/`trimText`, and an invalid address normalizes to `''`, so it is filtered out before any output. The command has no `Log` calls.
- **`isValidText`:** `null` is valid, `''` is valid, valid UTF-8 is valid, and invalid UTF-8 makes `preg_match('//u')` return `false` (not `1`), so it is invalid.
- **Address selection:** an invalid address is still skipped (`Planner:428`), so a valid address on another quote wins, otherwise `NULL`.
- **Scope:** `php -l` is clean. The command file is still 791 lines and predates the planner edit. Both files are untracked, so `git diff` can't prove "untouched"; `git status` shows nothing else changed.
- **Note:** the pre-pass discards its warnings (C15.6), so each warning appears once.

## QA results — Phase 2

Date: 2026-09-24. Role: QA. Files: `tests/Feature/Rbac/ProjectBackfillTest.php` (new, 73 tests, 1115 assertions) only. No app code, migration or plan edited. Nothing committed.

**Counts.** `ProjectBackfillTest`: 73 passed / 0 failed. `php artisan test --filter=Rbac`: 169 passed / 3 failed (1385 assertions). The 3 failures are the known baseline by name: `AuditMiddlewareTest` "enforce mode blocks and logs", `SeedMatrixTest` "seeds expected counts", `SeedMatrixTest` "phase distribution". No new failures.

**Safety.** The class extends `RbacTestCase` (forces in-memory sqlite; `setUp` also asserts driver `sqlite` and database `:memory:` before any query), uses no `DatabaseTransactions`, calls `useStoragePath()` on a per-test temp dir removed in `tearDown` (verified afterwards: no `storage/app/backfill` in the repo, no leftover temp dirs), and never runs `artisan down` (a fake `MaintenanceMode` is bound; `storage/framework/down` is asserted absent). No migrate/db command was run outside the tests; the real `.env` DB was never touched. Mutation runs were on a scratch copy of the repo (own `vendor/`), and the real repo's `git status` shows only the new test file plus the Developer's untracked Phase 2 files.

**Harness notes (not defects).**
- `RbacTestCase` runs with Laravel's mocked console output, so `Artisan::output()` is empty. The class calls `withoutMockingConsoleOutput()`; test 25 switches the mock back on for `expectsConfirmation`.
- The command keeps state on the instance (`overrides`, `stats`, `aborted`). Real use is one process per run, but two `Artisan::call()`s in one PHP process reuse the instance and a second run without `--overrides` would silently reuse the first run's file. The helper resets the console kernel (`setArtisan(null)`) before each call. This is a test-harness artefact, not a production defect.

**Coverage map (test number to method).**

| # | Method(s) |
|---|---|
| 1 | test_01_case_and_space_variants_form_one_project |
| 2 | test_02_two_owners_same_name_same_org_get_two_projects |
| 3 | test_03_unicode_normalization |
| 4 | test_04_unnamed_quotes_are_singletons_with_fallback_names |
| 5 | test_05_h1_equal_name_is_singleton_and_listed_as_candidate_merge |
| 6 | test_06_two_role_rows_in_one_org_count_once |
| 7 | test_07_multi_org_consensus_or_unresolved |
| 8 | test_08_zero_active_org_and_override_to_inactive_row, test_08b_override_to_org_without_any_role_exits_2_and_writes_nothing |
| 9 | test_09_merge_split_name_and_bom, test_09b_fatal_override_files_... (7 data sets: conflicting names, duplicate quote, unknown quote, all empty, whitespace key, NBSP key, missing header; each exit 2, nothing written, no report dir, codes and line numbers only), test_09c_override_on_attached_quote_never_attaches_or_moves_it |
| 10 | test_10_trashed_and_mixed_groups |
| 11 | test_11_address_choice_and_conflicts |
| 12 | test_12_defaults_and_quote_updated_at_unchanged |
| 13 | test_13_membership_union_and_grant_source |
| 14 | test_14_widening_report |
| 15 | test_15_explicit_owner_removal |
| 16 | test_16_b4_rehome_versus_mismatch |
| 17 | test_17_enrol_is_never_called_and_invariant_holds (`ProjectMember::creating` listener) plus the automatic invariant/legacy-identical check in every `backfill()` call |
| 18 | test_18_crosswalk_link_duplicates_mismatch_and_unresolved |
| 19 | test_19_second_run_reports_zeros_and_changes_nothing (also byte-compares all 4 tables, clock advanced a day between runs) |
| 20 | test_20_straggler_joins_existing_project_and_existing_rows_are_untouched |
| 21 | test_21_key_mapping_to_two_projects_is_ambiguous |
| 22 | test_22_live_straggler_with_only_trashed_sibling_is_unresolved |
| 23 | test_23_dry_run_leaves_counts_unchanged_and_uses_dry_run_dir, test_23b_dry_run_clean_data_exits_0 |
| 24 | test_24_group_failure_is_isolated_and_reported (see deviation below) |
| 25 | test_25_production_without_force_declined_writes_nothing (plus `--force` proceeds) |
| 26 | test_26_report_headers_and_file_set_match_c7 |
| 27 | test_27a..27d (member quote_id NOT NULL, crosswalk quote_id NOT NULL, quotes.project_address missing, crosswalk.project_id missing) |
| 28 | test_28_straggler_does_not_join_a_project_created_by_another_user |
| 29 | test_29_existing_inactive_member_is_not_reactivated_on_reuse |
| 30 | test_30_timestamps_are_copied_as_stored_strings |
| 31 | test_31_large_group_is_attached_in_chunks (1,200 quotes, query log) |
| 32 | test_32_long_multibyte_name_is_truncated_by_characters |
| 33 | test_33_dry_run_verification_failure_exits_3 (see deviation below) |
| 34 | test_34_override_and_sibling_worked_example_with_stragglers |
| 35 | test_35_second_run_without_the_file_is_ambiguous_and_joins_nothing |
| 36 | test_36_typed_keys_do_not_collide |
| 37 | test_37_null_ordering_and_fallbacks |
| 38 | test_38_concurrent_change_is_detected_and_written_nowhere |
| 39 | test_39_all_trashed_group_reuses_trashed_sibling |
| 40 | test_40_rule_iii_requires_the_legacy_org_to_be_an_active_org |
| 41 | test_41_report_escaping_modes_and_umask (`= + - @`, tab, CR, backslash-quote, DB value unchanged, 0700/0600, umask) |
| 42 | test_42_no_client_text_reaches_the_log_or_console (`Log::listen`, failing group) |
| 43 | test_43_h3_live_run_guard (fake MaintenanceMode; four sub-cases) |
| 44 | test_44_h4_unlinked_crosswalk_line_is_always_printed |
| 45 | asserted inside 19, 20 and 29 (existing rows byte-identical after a second run) |
| 46 | test_46_overrides_copy_is_byte_identical_and_hashed_over_raw_bytes; the no-values console check is in 09b, 54 |
| 47 | test_47_one_run_at_is_used_for_every_write |
| 48 | test_48_owners_groups_and_members_are_processed_in_ascending_order |
| 49 | test_49_attached_override_warnings_only_for_owners_with_unattached_quotes |
| 50 | test_50_org_without_role_applies_to_unattached_quotes_only |
| 51 | test_51_invalid_utf8_address_is_warned_and_never_written (H6) |
| 52 | test_52_candidate_merges_rule |
| 53 | test_53_member_order_follows_lowest_source_row_then_owner_last |
| 54 | test_54_override_line_numbers_are_record_numbers_with_blank_lines_counted |
| 55 | test_55_override_text_rules |
| 56 | test_56_no_statement_exceeds_500_bindings_and_inserts_stay_within_50_rows (whole run: select, update, insert paths) |
| 57 | test_57_pre_pass_finds_a_later_owners_conflict_before_any_write, test_57b_pre_pass_warnings_are_not_duplicated |
| 58 | test_58_every_report_is_complete_after_each_exit_path (failed group real run and dry-run), test_58b (clean run and exit 3) |
| 59 | test_59_concurrent_change_accounting |

**Deviations from the plan's wording (each stricter or a necessary adaptation).**
- Test 24: the plan's `RAISE(ABORT)` trigger on a project named `boom` fires on the first write of the group, so it cannot tell whether the per-group transaction exists (the group would leave nothing behind either way). I kept that trigger and added a second one on `project_members` for a poison user, so the group has already inserted the project and attached its quotes when it fails. Removing the per-group transaction is then caught.
- Test 33: a raw `COMMIT` alone made `DB::rollBack()` throw, which also sets `verifyFailed` and masked the count check (mutant "remove the exit-3 verification" survived). The helper now commits, inserts a project directly, then re-opens a transaction with `BEGIN`; the final rollback succeeds and only the count comparison can raise exit 3.
- Test 34: the plan's stragglers 106/107 must exist at run 1 (the file is identical for both runs), so they start unresolved (multi-org owner, no consensus) and get a legacy row in org 7 between the runs.
- Test 36: the "collision" is real, a quote named with quote 5's id (`'5'`) against quote 5's singleton, plus the plan's `#q<id>` name and an override key equal to the id.

**Mutation proof (scratch copy, one mutation at a time, full `ProjectBackfillTest` run each; baseline of the copy 73/73).** "Killed by" lists the tests that failed. Test names are the numeric prefixes.

| Rule | Mutation | Plan says | Failed |
|---|---|---|---|
| Org invariant | write the legacy `org_id` | 16, 17 | 16 |
| B4 | always re-home | 16 | 16 |
| Explicit owner removal | force owner active | 15 | 15 |
| Idempotency scope | drop `project_id IS NULL` in the owner list and quote load | 19 | 19, 09c, 20, 21, 22, 28, 29, 34, 35, 39, 43, 44, 49, 50 |
| Idempotency scope | drop it in the quote load only | 19 | 09c, 20, 21, 22, 28, 29, 34, 35, 39, 43, 44, 49, 50 (equivalent for 19, see gaps) |
| Idempotency sibling lookup | no sibling lookup | 19 | 20, 21, 22, 29, 34, 35, 39 (19 has no straggler, see gaps) |
| `sibling_project_trashed` | reuse the trashed project | 22 | 22 |
| Sibling owner filter | drop `created_by = owner` | 28 | 28 |
| Unnamed singleton | group unnamed by name | 4 | 03, 04, 05, 36 |
| Multi-org (iii) | pick the first org | 7 | 07, 34, 35, 40 |
| No `updated_at` bump | Eloquent `update()` on quotes | 12 | 12 |
| Dry-run rollback | remove the final `rollBack()` | 23 | 23, 23b, 43 |
| Dry-run verification | remove the count comparison | 33, 58b | 33, 58b |
| Per-group transaction | remove it | 24 | 24, 33, 38, 58, 58b, 59 |
| Chunking | size 5000 | 31, 56 | 31, 56 |
| Chunking | size 500 | 31, 56 | 31, 56 |
| H2 provisional | link mismatched crosswalk rows | 18 | 18, 44 |
| Never calls `enrol()` | call `enrol()` for members | 17 | 17, 10, 13, 15, 29, 30, 37, 47 |
| Schema precondition | remove it | 27 | 27a, 27b, 27c, 27d |
| Attached override, sibling key | ignore its `project_key` | 34 | 34 |
| Attached override | let it attach/move the quote | 9, 34 | 09c, 34, 49, 50 |
| Sibling org | use the override `org_id` | 34 | 34 |
| `$runAt` | `now()` per member write | 47 | 47 |
| Group order | descending groups | 48 | 48 |
| Owner order | descending owners | 48 | 48 |
| Warning rule N2 | warn on every attached override row | 34 | 34 |
| Empty key N7 | accept whitespace-only key | 9 | 09b (whitespace and NBSP data sets) |
| Escape char | default `fputcsv` escape | 41 | 41 |
| umask | not restored | 41 | 41 (first run survived because the harness umask already was 0077; test 41 now sets 0022 and restores it) |
| Typed key | compare only the value | 36 | 36, 20, 21, 22, 29, 34, 35, 39 |
| NULL ordering | NULL `updated_at` newest | 37 | 37 |
| `concurrent_change` | remove the set comparison | 38, 59 | 38, 59 |
| Trashed reuse | never reuse | 39 | 39, 22 |
| Rule (iii) org check | accept a non-active legacy org | 40 | 40 |
| Escaping | remove the `'` prefix | 41 | 41, 46 |
| Log hygiene | log the project name | 42 | 42 |
| H3 guard | remove the maintenance check | 43 | 43 |
| `existing_untouched` | update existing member rows | 20, 29, 45 | 20, 29, 39 (first attempt survived 20 and 19 because both runs used the same second; the tests now advance the clock a day between runs) |
| Address choice | oldest address | 11 | 11, 37 |
| Address warning (H6) | remove the warning | 51 | 51 |
| Address warning (H6) | write the corrupt value into the warning | 51 | 51 |
| `overrides.csv` | escape the copy | 46 | 46 |
| Hash | over BOM-stripped bytes | 46 | 46 |
| Warning scope C15.7 | warn for every attached override | 49 | 49, 09c, 34 |
| Role check scope C15.7 | role check on attached quotes too | 50 | 50, 49 |
| `candidate_merges` C15.1 | only buckets of 2+ | 52 | 52 |
| Member order C15.2 | user id only | 53 | 53, 48 |
| Record numbers C15.3 | count only non-blank lines | 54 | 54 |
| Override names C15.4 | compare normalized names | 55 | 55 |
| Override UTF-8 C15.4 | accept invalid UTF-8 | 55 | 55 |
| Pre-pass C15.6 | remove it | 57 | 57, 09b, 55 |
| Report closing | skip the summary on error | 58 | 58, 58b |
| Report closing | leave handles unclosed | 58 | SURVIVES: equivalent mutant (below) |

**Survivors.** One: "leave handles unclosed". `$this->handles = []` drops the last reference, so PHP closes the plain-file streams anyway, and plain-file writes are not buffered in user space, so no observable difference exists in a single process. I judge it an equivalent mutant, not a test gap. Everything that first survived (dry-run verification, umask, existing_untouched via 19/20) was fixed in the tests and is now killed.

**Gaps and sqlite limits.** sqlite cannot prove any of the following; they belong to the B1 dry-run and real run on a restored production copy of the production MariaDB version:
- `FOR UPDATE` locking under concurrent writers (test 38 proves only the set comparison; sqlite ignores the lock);
- the long outer dry-run transaction: real savepoint, undo and lock growth (sqlite savepoints work but are a different engine), and InnoDB AUTO_INCREMENT behaviour after rollback (test 33 simulates an escaped write with a raw `COMMIT`/`BEGIN`, which is not how MariaDB fails);
- memory and duration at production volume (tests 31 and 56 use 1,200 quotes, and peak memory is printed only), including the doubled pre-pass read cost;
- the real 500-binding behaviour of MariaDB and `max_allowed_packet`; tests 31 and 56 only assert that no statement carries more than 500 bindings and inserts are at most 50 rows;
- timezone/TIMESTAMP conversion of copied `created_at`/`deleted_at` (sqlite stores the strings verbatim, so test 30 proves the code copies strings, not that MariaDB stores them unchanged);
- collation: PHP-side grouping is proven, but how it compares with `utf8mb4_unicode_ci` estimates (Q4/Q5/Q16/Q17) is not; sqlite `LOWER` is ASCII-only anyway and the code never uses it;
- file modes on the production filesystem (test 41 checks the local one);
- lost-connection abort (`aborted`, exit 1) is not exercised: no sqlite way to raise a genuine lost-connection error, so test 58 covers success, a failed group, dry-run and exit 3 but not that path.
- Test 19 cannot kill "no sibling lookup" alone (nothing is unattached on the second run); that mutant is killed by the straggler tests 20, 21, 22, 29, 34, 35, 39.
- Test 17 kills "write the legacy org" only through 16 (test 17's data has no org mismatch); the automatic invariant check in `backfill()` is what fails there.

**Defects found:** none. The implementation matched C2-C15 in every test written. The only surprises were harness-level (mocked console output, instance state between `Artisan::call`s) and are recorded above.

**Handoff:** QA -> Orchestrator, then the Verifier. Nothing to route to the Architect.

## Verifier review — Phase 2 code and tests

Scope: `app/Console/Commands/BackfillProjects.php` (791 lines), `app/Support/ProjectBackfillPlanner.php` (673 lines) and `tests/Feature/Rbac/ProjectBackfillTest.php` (73 tests). All three are untracked and uncommitted.

Evidence I gathered myself:
- I read both app files in full and re-derived the algorithm from the code.
- `php artisan test --filter=Rbac` gives 169 passed and 3 failed, and the 3 are exactly the TESTING.md baseline. ProjectBackfillTest alone is 73/73.
- I ran 47 mutations on a scratch copy (now deleted; nothing in the repo was touched).
- I grepped both files for raw SQL, Eloquent writes, logging, `enrol` and `ProjectMember`. There is none.

### Findings (most severe first)

**F1 — should-fix (test gap on a security rule). B4 "active role in the project org" is not proven for inactive roles.**
- Location: `BackfillProjects.php:432-436`, the `->where('is_active', true)` filter on the roles loaded for legacy members. `ProjectBackfillTest.php:697-727` (test 16) is the only re-home test.
- Evidence: mutation M25, which removes that `is_active` filter, leaves 73/73 passing. Test 16 uses a user with no role at all in org 7, never a user whose only role in org 7 is inactive.
- Impact: the code is correct today. But a regression here would re-home a legacy row into org 7 for a user whose org-7 role is inactive, giving an ex-member membership in the org's project (access widening).
- Required change: add a mismatch case with a user whose only role in the project org is inactive, and add it to the mutation table. This is a test-wording update, so route it via the Architect (C12 test 16 wording).

**F2 — should-fix (test gap). The owner's own active orgs are never proven to feed membership decisions.**
- Location: `ProjectBackfillPlanner.php:201` (`$userActive[$ownerId] = $activeOrgs`).
- Evidence: mutation N01, which deletes that line, leaves 73/73 passing.
- Impact: without it the owner's own legacy row in another org is listed as `org_mismatch` instead of `rehomed`, and `member_active_in_org` is wrong for the owner. Reports are wrong, but the DB effect is small.
- Required change: add a test with an owner legacy row in a different org where the owner is active in the project org. Route via the Architect.

**F3 — note. A project reused by two groups in one plan would insert the same member twice.**
- Location: `ProjectBackfillPlanner.php:199` (`existing_members` is read at plan time, before any group is written) plus the sibling map (`:328-363`).
- Evidence (reasoning only): two keys can map to one existing project only if the overrides file changed between runs or after native Phase 4 edits. The second group's insert then hits `unique(project_id, user_id)`, the group rolls back cleanly, is reported in `errors.csv`, and the next run succeeds. It fails safe (nothing half-written), but it is noisy.
- Required change: none for Phase 2. Consider a planner-level note in C4 step 4.

**F4 — note. The plan cost is quadratic per owner.** `planCrosswalk` (`:639`) scans every crosswalk row of the owner for every group, so an owner with G groups and C rows costs G x C. The planner also runs twice (pre-pass plus run). This is fine at expected volume, but the B1 dry-run must record duration and peak memory (C8 already requires it).

**F5 — note. The pre-pass runs before the production confirmation and the H3 guard** (`BackfillProjects.php:88` versus `:101-114`). It is read-only, so it is harmless, but a refused run spends the full planning time first.

**F6 — note. Surviving mutants that are defense in depth or equivalent.**
- M14, N04 and N14 remove a redundant `whereNull('project_id')` filter (on the quotes update, the legacy member read and the crosswalk update). They are not killable on sqlite, because the lock plus set-compare (killed by tests 38/59) already guards concurrency.
- M26 ("report handles left unclosed") is genuinely equivalent. `$this->handles = []` drops the last reference and PHP closes the stream, and the summary is written before that.

**F7 — note. Path handling of `--overrides`.** `is_file` follows symlinks and reads any file the process can read. A non-CSV file is rejected by the header check, and no cell values are echoed (only line numbers and reason codes). The verbatim copy is written only after validation passes, with mode 0600. That is acceptable for an operator-run CLI.

### Requested checks

**(1) Data safety**
- I found no input sequence that:
  - merges a foreign owner's quotes (mutation M21, dropping the per-owner filter, is killed by tests 02/18/23/48);
  - attaches a user to a project they should not be in, apart from the F1 test gap;
  - breaks the org invariant (M01, killed by 16, and the invariant helper runs after every `backfill()` call);
  - breaks idempotency (M07 killed by 13 tests);
  - leaves a half-written group (test 24, plus its extra `project_members` trigger, proves rollback of the project insert and the quote attach).
- In dry-run, the outer transaction wraps everything. `rollBack` sits in a `finally`, and the four counts are verified. M11 (commit instead) and N06 (no outer transaction) are both killed.
- There are no model events, observers or log writes, because everything uses the query builder.
- Auto-increment gaps are harmless. Timestamps are copied as stored strings.

**(2) MariaDB behaviour**

All grouping is done in PHP. Every SQL comparison is on integer ids, so collation and case sensitivity cannot matter.

| Construct | Location | Note |
|---|---|---|
| `distinct()` with `orderBy()` and `pluck()` | `:330`, `:360-366` | fine on MariaDB |
| `lockForUpdate()` on `whereIn(id)` with `whereNull(project_id)` | `:625` | sqlite ignores it; MariaDB locks. Check on B1. |
| `whereIn` chunk 400; inserts of 50 rows x 8 columns | `:22`, `:671` | max 400 bindings, well under 65,535 |
| Timestamps read and written as raw strings | `:346`, `:636` | round trip is identical in one session timezone. |
| `$runAt` | `:125` | uses the app timezone, like Eloquent |

What differs on MariaDB and only the B1 copy can show: `@@session.time_zone` versus `config('app.timezone')` and DST-ambiguous hours; zero-date or strict `sql_mode` behaviour on copied `created_at`/`granted_at`; `quotes.updated_at` really has no `ON UPDATE CURRENT_TIMESTAMP` (`SHOW CREATE TABLE quotes`, `plan_crosswalk`); `Schema::getColumns()` reporting nullability correctly; savepoint and lock behaviour of the long dry-run transaction; and deadlock behaviour (`DB::transaction` does no retry, so a deadlock is recorded in `errors.csv` and a re-run continues).

**(3) QA's three deviations.** None weakens the requirement.
- Test 24: the extra `project_members` trigger strengthens it (it proves a late-stage failure rolls back the earlier project insert and attach).
- Test 33: COMMIT plus a direct insert plus BEGIN inside a listener does prove the exit-3 path, which is what the plan's "sabotaged connection callback" allows.
- Test 34: the stragglers start unresolved (owner with two orgs, no consensus) and get a legacy row between runs. That is the only way they can resolve in the plan's own scenario, and it still proves sibling keys. Test 20 covers a single-org straggler.

**(4) Mutation results (47 total).**
- **Killed:**
  - the org invariant (M01);
  - always-re-home (M02, M23);
  - the sibling `created_by` filter (M03, M22);
  - the H1 rule (M04);
  - the H3 guard (M05);
  - override-only-feeds-sibling-keys (M06);
  - the idempotency scope (M07);
  - CSV escaping (M08, M20);
  - the overrides hash (M09);
  - umask (M10) and file modes (M29, N12);
  - dry-run rollback (M11, N06);
  - owner explicit removal (M12);
  - set-compare (M13);
  - chunk size (M15);
  - H2 crosswalk (M16);
  - trashed-sibling reuse (M17);
  - the `existing_untouched` skip (M18);
  - name truncation (M19);
  - the owner filter (M21) and the owner key part (M28);
  - the owner role `is_active` check (M24);
  - the `updated_at` bump (M27);
  - the production confirmation (M30);
  - the (iii) rules (N02, N03);
  - the pre-pass (N05, N16), `created_by` (N07), grant-source (N08), ambiguity (N09), exit code (N10), trashed `deleted_at` (N11), active flag (N13), Estimate (N15) and the override role check (N17).
- **Survived:** M25 (finding F1), N01 (F2), and M14, N04, N14, M26 (F6).

**(5) Test quality.**
- Tests are not order-dependent, and the maintenance-mode binding is faked so the real `down` file is never touched.
- Files go only under a temp storage path, and the class never uses `DatabaseTransactions`.
- The shared helper checks the org invariant and byte-identical legacy rows after every run.
- I found no tautological test among the killed mutants.

**(6) Security and privacy.**
- CSV escaping covers `=`, `+`, `-`, `@`, tab and CR. The overrides copy is exempt and byte-identical, with the hash over the raw bytes.
- The report directory is 0700 and files 0600 (umask restored in a `finally`).
- No client text reaches the log or console (test 42; group failures are caught before Laravel's handler).
- Reports hold client data, including exception messages.

**(7) Scope.** Only the two app files and the test file were added. No model, migration, route, config or `RbacTestCase` change. No `enrol()`, no legacy row writes, no Eloquent writes.

**(8) Operational hazards.** See F4, F5 and the MariaDB list above.

### Verdict

I CONFIRM no regression risk and no wrong-result edge case for the Phase 2 code within sqlite's limits. I do NOT confirm "no missing edge cases" for the tests: F1 (and F2) are real gaps, and strict mode routes them through the Architect for a C12 test-wording update. Both are test additions only, and no code change is needed.

### Must be checked on the B1 restored copy

1. Time zone: `@@session.time_zone` versus `app.timezone`, and a DST-ambiguous `created_at` round trip.
2. Zero dates, strict `sql_mode`, and the Q14 columns.
3. `SHOW CREATE TABLE` for `quotes`, `project_members` and `plan_crosswalk` (no `ON UPDATE CURRENT_TIMESTAMP`; the FK indexes used by the `whereIn` reads).
4. Dry-run duration, peak memory, lock waits and undo growth; then confirm the `--dry-run` counts are unchanged.
5. `FOR UPDATE` behaviour with a second session, and a deliberate deadlock or poison-row case.
6. Real run, then the verification run reporting 0 changes.
7. `groups.csv` versus the Q4/Q5/Q16 SQL estimates.

## QA results — Phase 2 tests 60-62

Date: 2026-09-24. Only `tests/Feature/Rbac/ProjectBackfillTest.php` changed (3 tests added, per C16 / C15.10 rows 60-62). No app code or plan edited; nothing committed.

**Counts.** `ProjectBackfillTest`: 76 passed / 0 failed (1178 assertions). `php artisan test --filter=Rbac`: 172 passed / 3 failed, the 3 being the known baseline by name (AuditMiddlewareTest "enforce mode blocks and logs", SeedMatrixTest "seeds expected counts", SeedMatrixTest "phase distribution"). No new failures.

**Tests.**
- 60 `test_60_b4_rehome_requires_an_active_role_in_the_project_org`: owner active only in org 7; legacy rows in org 9 for U (only role in 7 inactive, active in 9), V (active in 7), W (inactive plus active in 7). U has no member row and one `membership_org_mismatch.csv` row (`user_active_org_ids` 9, project org 7) and no rehomed row; V and W are inserted with org 7 and each appear once in `membership_rehomed.csv`; summary mismatch 1, rehomed 2; exit 0; invariant helper passes.
- 61 `test_61_owner_active_orgs_feed_membership_decisions`: fixture A (owner active only in 7, own legacy row in 9: rehomed, owner inserted with org 7, `member_active_in_org` yes), fixture B (owner active in 7 and 9, override to 9, own row in 7: rehomed, yes), fixture C (override to an org where the owner has only an inactive role, own row in 9: org_mismatch row, not rehomed, `member_active_in_org` no); exit 0 in all three.
- 62 `test_62_owner_with_inactive_and_active_roles_resolves_to_the_active_org`: inactive role in 7 plus active in 9, no override: project org 9, `single_org`, empty `unresolved.csv`, exit 0.

**Mutation proof (scratch copy, one at a time; copy baseline 76/76).**

| Mutation | Failed |
|---|---|
| Remove `->where('is_active', true)` on the legacy members' roles (`BackfillProjects.php:432-436`) | 60 |
| Delete `$userActive[$ownerId] = $activeOrgs;` (`ProjectBackfillPlanner.php:201`) | 61 |
| Owner's org query counts inactive roles (`BackfillProjects.php:360-366`) | 62, and also 08, 18, 23, 61 |

All three killed; no test needed a fix. The real repo's app files are byte-identical to the copy's restored originals (`diff` clean) and `git status` shows only the untracked Phase 2 files and the test file. No report files left under `storage/app`.

**Defects found:** none.

## Verifier final pass — Phase 2

Evidence: `php artisan test --filter=Rbac` gives 172 passed and 3 failed (the TESTING.md baseline; ProjectBackfillTest is 76/76). I ran the mutations on a scratch copy of the repo (now deleted; the real repo was not modified). `git status` shows only the Developer's two untracked app files and the test file. `storage/app` holds only `private` and `public`, so no reports were left behind.

### (1) F1 and F2 reproduced and killed

| Mutation | Result | Killed by |
|---|---|---|
| Remove the `is_active` filter on legacy members' roles (`BackfillProjects.php:432-436`) | 1 failed, 75 passed | test 60 |
| Delete `$userActive[$ownerId] = $activeOrgs` (`ProjectBackfillPlanner.php:201`) | 1 failed, 75 passed | test 61 |
| Owner-role query ignores `is_active` (`BackfillProjects.php:360-366`) | 5 failed | tests 08, 18, 23, 61, 62 |

F1 and F2 are both closed.

### (2) Earlier mutations re-run (no regression in kills)

All still killed, and the count of failing tests is unchanged or higher:
- Org invariant: now tests 16, 60 and 61 fail (was 16 only).
- Sibling `created_by` filter: test 28.
- H3 guard: test 43.
- Idempotency scope: 13 tests fail.
- CSV escaping: tests 41 and 46.
- Dry-run commits: tests 23, 23b, 33 and 43.
- Foreign owners merged: tests 02, 18, 23 and 48.

### (3) C16 and the B1 Phase 2 checklist (PLAN.md L511-524)

The 12 items cover every MariaDB concern from my review:
- Time zone and DST: item 1.
- Zero dates and strict `sql_mode`: item 2.
- `ON UPDATE CURRENT_TIMESTAMP`, FK indexes and `Schema::getColumns()` nullability: item 3.
- Collation versus the SQL estimates: item 4.
- Bindings and `max_allowed_packet`: item 5.
- `FOR UPDATE`, concurrency and deadlock: item 6.
- Dry-run cost, locks and undo growth: item 7.
- Real run followed by the verification run: item 8.
- File modes and location: item 9.
- Lost connection: item 10.
- Reports and no logging: item 11.
- Org invariant and legacy rows unchanged: item 12.

One minor gap, a note only: no item explicitly forces a failing group inside the dry-run's outer transaction to check that the savepoint rolls back that group alone and the run continues on MariaDB. Item 6's poison-row case, run once with `--dry-run`, would cover it.

M26 is recorded as equivalent, and M14, N04 and N14 as not killable on sqlite. I agree with both records.

### (4) Scope

Clean. The only untracked files are `app/Console/Commands/BackfillProjects.php`, `app/Support/ProjectBackfillPlanner.php` and `tests/Feature/Rbac/ProjectBackfillTest.php`. No model, migration, route, config or `RbacTestCase` change, no `enrol()` call, no legacy row writes. Nothing new was introduced by C16.

### Verdict

I CONFIRM "no missing edge cases / no regression risk" for the Phase 2 code and tests within sqlite's limits. Nothing blocks closing Phase 2 at agent level. B1, B5, B12 and the backup remain production gates, not defects, and the B1 Phase 2 checklist must be run and recorded before any production run.

## Verifier review — Phase 3 specification

_(Incremental log; started. Findings are appended as evidence is gathered; the verdict line is last.)_

## Architect compliance pass — Phase 3

Scope: the uncommitted working-tree diff against Amendment 4 (D1-D15). The changed files are exactly the D9/D15.8 list: `PermissionService`, `RbacAudit`, `route_permission_map`, `Quote.php`, `QuoteController`, `UserWorkspaceController`, `OrgAdminController`, `PlanCrosswalkController`, `ProjectWorkspaceController` and `Rbac.php`, plus the new `app/Support/Rbac/CurrentOrg.php`. `php -l` is clean on all of them. No route, view, migration, write path, dashboard or Phase 4 file changed. I hand-traced the queries and did not execute the app code.

**Verdict: DEVIATIONS FOUND. There is one omission (a test the Developer was to rewrite). The implementation itself matches the spec.**

### Findings

1. **`tests/Feature/Rbac/PermissionServiceTest.php:85-111`, D3 and D9 — blocking for QA handoff, not for the code.**
   - The spec assigns the rewrite of `test_project_scoping_requires_membership` to step 5. It still inserts a legacy `quote_id` member row, so it now fails.
   - `php artisan test --filter=Rbac` gives **171 passed / 4 failed**: the 3 known baseline failures plus this one.
   - The failure is the intended consequence of step 5, because legacy rows no longer grant. It only needs the rewrite on `projects` and `project_members.project_id`, keeping the three assertions.
   - No PLAN update is needed. `TESTING.md` (D9) is also not yet updated.

### Line-by-line results against the spec

- **D3 (`PermissionService.php:124-135`):**
  - The query joins `projects`, and `project_id`, `user_id`, `org_id`, `is_active` and `projects.org_id = orgId` (the org tie) all match.
  - `projects.deleted_at IS NULL` is present, so trashed projects deny.
  - Legacy rows can't match.
  - The docblock at `:42` and the comment at `:77` say projects.id.
- **D4 / D15.3 (`RbacAudit.php:59-134`):**
  - `resolveScope()` gives `project_param` a model-or-positive-integer id and NULL as unresolved. It gives `quote_param` a model's `project_id`, or `Quote::withTrashed()->whereKey()->value('project_id')` for a scalar.
  - Non-numeric, zero and absent values are unresolved.
  - Order is `no_org`, then `project_unresolved` (`checkPermission` is not called), then the check result.
  - `fail()` writes both `project_id` and `quote_id` (`:159-160`). `no_org` rows also carry them.
  - `project_param` rows have `quote_id` NULL.
  - `fail()` is otherwise unchanged. The audit and enforce paths, the 403 JSON for `expectsJson()`/`ajax()`, and the 302 redirect back are unchanged, so open item #4 is untouched.
- **D5 (map):**
  - The map has exactly 9 `project_param` entries renamed to `quote_param` (`:123-125,130,133-135,138-139`, with `id` or `quoteId` kept) plus the workspace addition (`:94`). Group, level and batch are unchanged.
  - The Orchestrator's counts are explained: `'quote_param'` appears 11 times = **10 entries + 1 in the header comment (`:14`)**, and `'project_param'` appears once, **only in the header comment (`:13`)**. No `project_param` entry remains, and none is on a `quotes/` URI.
  - The header comment at `:13-17` documents both keys.
  - Nothing was missed or wrongly renamed.
- **D6:**
  - `CurrentOrg::id` returns the session org if the user has an active role there, else the first active org, else NULL. It matches `RbacAudit::currentOrgId` (a blank or non-numeric session value falls back the same way).
  - `Quote::scopeVisibleTo` (`Quote.php:52-61`) is a grouped `user_id = me OR project_id IN Project::visibleTo(me, org)->select(projects.id)`.
  - The org-tie clause is skipped when the org is NULL.
  - It is org-independent for the owner clause, so a user's own quotes stay visible.
  - Trashed projects and inactive members are excluded through `Project::visibleTo`.
- **D7, hand-traced:**
  - The five `QuoteController` reads (list `:54`, total `:124`, details, PDF, preview) use `visibleTo`, and no `user_id` read remains for them. The `user_id` lines that remain are Phase 4 writes and per-user lookups, exactly as listed in D15.1a.
  - Multi-org user acting in org A: sees own quotes, plus quotes in org-A projects they are a member of. Quotes in org B projects are hidden until the org switches, which matches D6.
  - A NULL-project quote is visible only to its owner. It is invisible to everyone else, because `IN (subquery)` never matches NULL.
  - `UserWorkspaceController` builds the union of legacy quote ids (`whereNotNull`) and quotes in visible projects, deduplicated. The org comes from `CurrentOrg`, and the `myProjects` and pending-approval queries are unchanged.
  - `OrgAdminController::projects` uses quotes owned by members OR in the org's non-trashed projects. The chips are project rows only when `project_id` is set, and legacy rows only when it is NULL, so there are no duplicate chips. Removal is unchanged.
- **D8 / D15.6:**
  - `PlanCrosswalkController::index` no longer touches `quotes.org_id` or `title`. The projects list is `visibleTo` with `name as title`, rows are org-scoped and restricted to visible quotes or visible projects, the legacy `project_id` param is kept, and a NULL org gives an empty page. `store/update/destroy` are untouched apart from `currentOrgId()` using the helper, which D9 allows.
  - `ProjectWorkspaceController::show` denies a NULL `project_id` explicitly before `checkPermission` (`:39`, short-circuit), passes `(int) $quote->project_id`, redirects to `org-admin.projects.index` with the same flash, and uses `CurrentOrg`. The §4.5 check and the legacy lists are untouched.
- **D15.1 / D15.5:** the dashboard view is untouched (H8 default), and the `Rbac.php` change is a doc-only comment.

### Notes (non-blocking)

- QA must still write `ProjectMembershipTest`, `ProjectReadPathsTest`, `RoutePermissionMapTest` and `ProjectTestCase`, and the D10 mutation proofs.
- `getEstimatesList` and the details/PDF reads now do one `CurrentOrg` lookup (one or two queries) per request. This is the D13.8 cost.
- Not verified here: sqlite-only behaviour, query plans and PDF rendering (D10 "What sqlite cannot prove").

## QA results — Phase 3

Date: 2026-09-24. Role: QA. Files (tests only): new `tests/Feature/Rbac/ProjectTestCase.php`, `ProjectMembershipTest.php`, `ProjectReadPathsTest.php`, `RoutePermissionMapTest.php`; edited `PermissionServiceTest.php` (the D3 rewrite of `test_project_scoping_requires_membership` and its `makeProject` helper, on `projects` and `project_members.project_id`, same three assertions). No app code, plan or TESTING.md edited. Nothing committed.

**Counts.** `ProjectMembershipTest` 57, `ProjectReadPathsTest` 122, `RoutePermissionMapTest` 11, all passed. `php artisan test --filter=Rbac`: 362 passed / 3 failed (2872 assertions); the 3 failures are the known baseline by name (AuditMiddlewareTest "enforce mode blocks and logs", SeedMatrixTest "seeds expected counts", SeedMatrixTest "phase distribution"). The former 4th failure (`PermissionServiceTest::test_project_scoping_requires_membership`) now passes. `AuditMiddlewareTest` was not touched and the 302-vs-403 behaviour is asserted as it is, not fixed.

**Safety.** All tests extend `RbacTestCase` (in-memory sqlite, asserted again in `ProjectTestCase::setUp`). The real local MySQL DB, `.env` and production were not touched. Requests go through the real routes (`web` group, `RbacAudit`, controllers, views); only test stubs were added (quotes columns, customers, saved_lists, quote_items, products, orders, saved_list_items, rfq_*; a `DATE_FORMAT` stand-in on the sqlite PDO for the dashboard view). `Storage::fake('public')` covers PDFs.

**Fixture (D10).** Projects P1=1 (A), P2=2 (A), P3=3 (B); quotes Q0=1 (NULL project), Q1=2 and Q2=3 (P1), Q3=4 (P2), Q4=5 (P3). So quote 1 has P1's id, and Q2 (in P1) has P3's id (collision). Users: estimator member of P1, viewer (R) and superintendent (no estimate group) members of P1, a non-member estimator, an org B estimator (member of P3), a two-org estimator, the owner of Q0-Q3.

**Coverage map.**
| Rule | Tests |
|---|---|
| M1-M11 service (member, non-member, other project, member-row org, row org vs project org, inactive, legacy quote-only row with id collision, trashed project, wrong role, delegate, no project id) | `ProjectMembershipTest::test_m1..m11`, plus wrong-org project test |
| A1 allowed scalar, no audit row | `test_a1` (both modes) |
| A2 would_block (audit), 403 JSON and 302 back (enforce), row carries quote_id AND project_id | `test_a2` x3 |
| A3/A4 NULL project and missing quote: `project_unresolved`, `checkPermission` never called (mock `never()`), both modes | `test_a3`, `test_a4`, `test_a6b` |
| A5 trashed quote resolves to its project | `test_a5` |
| A6 bound model, no second quote query | `test_a6` |
| A7 `project_param` scalar and bound `Project`; well-formed missing id | `test_a7`, `test_a7b` |
| A8 non-numeric (`abc`, `0`, `-5`, `1.5`, `1e3`) and absent param, both keys, both modes, 403 JSON / 302 back | `test_a8` (24 data sets), `test_a8b` |
| A9 `no_org` first, quote_id still recorded | `test_a9` |
| A10 quote_id on quote routes, NULL on project and unscoped routes | `test_a10`, `a10b`, `a10c` |
| A11 `RbacAudit` and `CurrentOrg` agree (valid, stale, no session, no orgs) | `test_a11`, multi-org session test |
| 10 routes x (allowed member, wrong role, wrong org, missing membership, inactive, trashed project, legacy row, NULL-project owner fail-closed) | `ProjectReadPathsTest::test_route_*` (80 data sets, enforce mode, JSON) |
| R1 list, total = list set, own + project + NULL-project own quotes, mismatched row, other org, session org, stale org, inactive, legacy-only | `test_r1_*` |
| R2 details/PDF/preview owner, member 200; stranger, other user's NULL quote, other project, other org, missing id 404 | `test_r2_*` |
| R3 dashboard My Projects union without duplicates, stale org, no org, other org | `test_r3_*` |
| R4 org projects page (quotes of members and live projects, orphan owner, trashed project, chips rules, other org rows) | `test_r4_*` |
| R5 crosswalk index no throw, visibility, filter by legacy quote id, no org, org isolation | `test_r5_*` |
| R6 workspace member 200, legacy-only 302 with the error, NULL-project 302 (org-level grant), collision (project id, not quote id), validated org | `test_r6_*` |
| R7 writes still 404 for a member, tables unchanged, both modes | `test_r7_*` (6 data sets) |
| R8 audit-mode guarantee (404 from controller + would_block row) | `test_r8` |
| R9 enforce: JSON 403 and the controller never runs; non-JSON 302 | `test_r9_*` |
| R10 owner of a NULL-project quote, audit mode: 200 + would_block `project_unresolved` | `test_r10_*` |
| R11 dashboard counters owner-only (count, recent list, status chart) while the panel shows the teammate | `test_r11` |
| R12 backfilled member shown once (project row), removing the chip removes `Project::visibleTo` access | `test_r12` |
| R13 trashed project: member loses it, owner keeps it, org page excludes it | `test_r13`, route-matrix trashed case |
| Lint rules 1-7 (both keys, no project_param on quote URIs, param values exist in URI, every quote URI with id has quote_param, every quotes/projects route mapped, workspace quote_param, `{project}` URIs, exact 10 entries, group/level/batch kept, five unscoped GETs stay unscoped) | `RoutePermissionMapTest` |

**Mutation proof** (scratch copy of the repo with its own `vendor/`; one mutation at a time; runs `ProjectMembershipTest|ProjectReadPathsTest|RoutePermissionMapTest|PermissionServiceTest`; copy baseline 198/198). "Failed" lists the tests killing it.
| Rule | Mutation | Plan says | Failed |
|---|---|---|---|
| Membership reads `project_id` | query back on `quote_id` | M1, M7 | m1, m7 and 30 others |
| B4 org tie | drop `projects.org_id` | M5 | m5 (only) |
| Active | drop `is_active` | M6 | m6, route inactive x10 |
| Trashed | drop `deleted_at` | M8 | m8, route trashed x10 |
| Member org | drop `project_members.org_id` | M4 | m4, m5, org tie mismatch |
| Project join | drop the join | | 101 tests (SQL error) |
| Quote to project | pass quote id to `checkPermission` | A1, M7 | 58 tests (a1, a2, a10..) |
| Fail closed | NULL project treated as "no project" | A3, A4, R10 | a3, a4, a6b, a8, a11 and more |
| `withTrashed` | dropped | A5 | a5 |
| Model or scalar | model not read via `project_id` | A6 | a6 |
| Model or scalar | `positiveId` cannot take a model | A7 | a7 |
| Audit row | omit quote_id | A2, A10 | 42 tests |
| Audit row | quote id in project_id | A2, A10 | 43 tests |
| 302/403 | always 403 | A2 | a2 (302), a8, r6, r9 |
| Enforce JSON | drop JSON branch | R9, A2 | 89 tests |
| `no_org` first | `project_unresolved` first | A9 | a9 |
| Non-numeric id | cast to 0 | A8 | a8 (all), a8b |
| Audit-mode guarantee | short-circuit in audit mode | R8 | 27 tests (r8 and all would_block tests) |
| Validated org | raw session org in `CurrentOrg` | A11, R3 | a11, r1 org, r3, r6 x2 |
| Map atomicity | any of the 9 renames reverted to `project_param` (9 runs) | lint 2, 4, 7 | lint 2 and 4 every time, plus route tests (7-10 failures each) |
| Map atomicity | items route param renamed wrongly | lint 3 | param-in-URI lint, r7, route tests |
| Workspace scoped | remove `quote_param` | lint 5, R6 | param lint, 7 workspace route tests |
| Owner clause | remove `user_id` clause | R1, R2, R10 | r1, r2, r10, r13 |
| Member clause | remove `visibleTo` clause | R1, R2 | 11 tests |
| Org tie | membership subquery without org | R1 | r1 x2, r13 |
| NULL-project hidden | let NULL-project quotes through | R2 | 9 tests |
| Trashed | trashed projects visible | R13 | r13 |
| QuoteController reads | list, total, details, pdf, preview owner-only (5 runs) | R1, R2 | r1 (list and total), r2 (details, pdf, preview) |
| CurrentOrg null in list | org NULL | R1 | r1 x2 |
| Dashboard union | drop project quotes / drop legacy ids | R3 | r3, r11 |
| Dashboard union | no de-duplication | R3 | SURVIVES, equivalent: `Quote::whereIn('id', $ids)` is set-valued, so duplicate ids cannot change the result |
| Dashboard org | raw session org | R3 | r3 stale org |
| Chips | legacy and project rows together | R12 | r12, r4 chips |
| Org page | drop project clause / trashed projects count | R4 | r4 |
| Crosswalk | restore `quotes.org_id` query / drop org / drop visibility / drop project_id branch | R5 | r5 (1-2 each) |
| Workspace | pass `$quote->id` | R6 collision | r6 x3, route allowed |
| Workspace | route name reverted | R6 | r6 x4 |
| Workspace | drop NULL check, null passes to `checkPermission` | R6 | r6 null |
| Workspace | drop `!== null` only, keep `(int)` cast | R6 | SURVIVES, equivalent: `(int) null` is 0 and project 0 has no membership, so it still denies; the real bypass (null passed through) is killed above |
| Workspace | raw session org | R6 | r6 x2 |
| Writes | scope `update` / `destroy` with `visibleTo` | R7 | r7, route allowed |
| Dashboard counters | quotesCount / recent quotes / status chart via `visibleTo` (3 runs; mutations on the blade view) | R11 | r11 each |

**Survivors.** Two, both equivalent (reasons in the table). No unkilled non-equivalent mutant.

**Defects found (route to the Architect).**
1. **F-1, pre-existing, now reachable: the dashboard 500s for project members who hold `project_management` F.** `resources/views/user/dashboard.blade.php:83` calls `route('org-admin.projects')`, which does not exist (the real name is `org-admin.projects.index`). It runs only when `myProjects` is non-empty and `canManageProjects` is true. I reproduced it with the fixture: GET `user-dashboard` as the estimator member of P1 returns 500 (`Route [org-admin.projects] not defined`). Before Phase 3 the panel needed a legacy row; after Phase 3 every project member gets a non-empty panel, so every estimator, project manager or owner-type role that is a member of a project now loses the dashboard. D9 forbids views in Phase 3, so this needs a Phase 4 (or hot fix) plan line, the same one-word fix as the workspace redirect. Tests R3 and R11 use the viewer (project_management R only) to avoid it; no test asserts the crash.
2. Note (not a Phase 3 defect): `QuoteController::destroyItem` wraps `firstOrFail` in a catch-all that answers 500 instead of 404 for a non-owner. R7 asserts the unchanged 500 for that route only.

**What sqlite cannot prove** (B1 / staging): query plans and cost of `whereIn(project_id, visibleTo subquery)` on real `quotes` volume and index use of `project_members(project_id, user_id)`; the operative RBAC mode row (Q0) and real multi-org sessions; real data shapes, notably that no quote has a NULL `project_id` after the backfill (otherwise enforce mode blocks owners with `project_unresolved`); the real PDF engine, fonts and public-disk storage (tests use dompdf with a faked disk and only check status); MySQL `DATE_FORMAT` in the dashboard view (stubbed on sqlite); the FTP deploy order of `RbacAudit.php` and the map.

**Handoff:** QA -> Orchestrator. Defect 1 goes to the Architect (issue intake) before the Verifier.

## QA results — Phase 3 D16 / R14

Date: 2026-09-24. Added `test_r14_dashboard_renders_for_a_project_member_who_can_manage_projects` to `tests/Feature/Rbac/ProjectReadPathsTest.php`: the estimator (F on `project_management`, member of P1) gets 200 on `user-dashboard`, `canManageProjects` is true, "My Projects" holds Q1 and Q2, and the page contains `href` of `route('org-admin.projects.index')`. The viewer-based R3/R11 tests were left as they are (D16 does not ask to change them).

Mutation (scratch copy, view line 84 restored to `route('org-admin.projects')`): `ProjectReadPathsTest` 1 failed / 122 passed; the failing test is `r14 dashboard renders for a project member who can manage projects` (500, route not defined). Real repo unchanged by the mutation; its `git diff` for the view is the Developer's one line.

Counts: `--filter=Rbac` 363 passed / 3 failed (known baseline only). The F-1 defect from "QA results — Phase 3" is closed by D16. No new defects.

## Architect compliance re-check — Phase 3 D16

Verdict: **COMPLIANT**.

- **Diff:** the only change to `resources/views/user/dashboard.blade.php` is line 84, `route('org-admin.projects')` to `route('org-admin.projects.index')`. No other occurrence of the wrong name remains in that file, and the H8 counters (`:15,42,45,66`) are untouched.
- **Files:** `git status` shows only the previously approved Phase 3 list (the ten app and config files, `CurrentOrg.php`), plus the test files (`PermissionServiceTest` rewrite, `ProjectMembershipTest`, `ProjectReadPathsTest`, `ProjectTestCase`, `RoutePermissionMapTest`). No route, migration, controller or other view changed.
- **R14** (`ProjectReadPathsTest.php:352-360`): an estimator (F on `project_management`) requests the dashboard. It asserts 200, `canManageProjects` true, the visible project quotes in `myProjects`, and that the page contains `href="` + `route('org-admin.projects.index')`. This is what D16 requires. QA reports the mutation on a scratch copy fails only R14.

## Verifier review — Phase 3 code and tests

_(Incremental log, written as evidence is gathered; the verdict line comes last. The earlier "Verifier review — Phase 3 specification" stub above was cut off by a rate limit and is superseded by this section. Spec-vs-reality gaps found while reading the spec are recorded here too.)_

Scope read so far: `git diff` of the 12 modified files plus `app/Support/Rbac/CurrentOrg.php`; PLAN.md D1-D16.

**P3-01 — should-fix (isolation, dashboard). "My Projects" still unions legacy `project_members.quote_id` rows with no org tie on the quote.**
- Location: `app/Http/Controllers/Frontend/UserWorkspaceController.php:34-47` (`$legacyIds`, then `Quote::withCount('items')->whereIn('id', $myProjectIds)` with no org or visibility filter).
- Evidence (code): a legacy row `(me, org_id = current org, active)` puts its `quote_id` straight into `$myProjectIds`. Nothing checks that the quote's project is in that org or that I am a member of it.
- Impact: this is exactly the case B4 chose not to carry (`membership_org_mismatch.csv`: a multi-org owner's quote resolved to org 7 while the admin's legacy row is org 9). That user lost workspace access (D8 redirects) but still sees the quote's name and item count on the dashboard. It is metadata only, but it contradicts D3's "legacy rows never grant" and Q14.
- Required change: after the Phase 2 verification run, the legacy union is unnecessary (D11 requires zero NULL-project quotes). Drop the legacy set, or restrict it to quotes with `project_id IS NULL`. PLAN update needed via the Architect (D7 row 6).

**P3-02 — should-fix, needs a human decision (isolation, audit mode = production). Member reads bypass the role check in audit mode.**
- Location: the dual-read scope `Quote::visibleTo` (`app/Models/Quote.php:52-61`) and the three QuoteController reads (`:143-147`, `:874-878`, `:895-899`). Membership is the only test; `estimate_management` is checked only by the middleware, and the middleware passes everything through in audit mode.
- Evidence (scratch test, audit mode): the fixture `superintendent` (no `estimate_management` grant at all) who is a member of P1 gets **200** on `GET quotes/{Q1}/details` for a teammate's quote and sees Q1/Q2 in `GET quotes/list`, with one `would_block` audit row and no block.
- Impact: before Phase 3 such a user could read only their own quotes in audit mode. Now any project member, whatever their role, can read every quote in the project (H7 accepted "members see teammates' quotes", not "members without an estimating role see them"). The QA matrix proves the wrong-role denial only in enforce mode.
- Required change: either gate the member clause of the reads on `checkPermission(estimate_management, R, projectId)` (as `UserWorkspaceController` already gates on `canReadEstimates`), or record it as a human decision (H9) with the audit-mode consequence stated. PLAN update via the Architect (D4 "Modes", D7, D13).

**P3-03 — should-fix (privacy, H7 concretely).** A member (or the read-only `viewer_read_only` role) can now see, for a teammate's quote: `staff_notes` (the owner's internal notes), `notes`, `terms_and_conditions`, `customer_id` and customer name, all items with rates, and attachment names plus public-disk URLs (scratch probe: `staff_notes` and `attachments[].url` are returned to a member). `GET quotes/{id}/pdf` by a member with only R also **writes** the owner's `quotes.pdf_path` and bumps `updated_at` (probe: `pdf_path` null before, `"quotes/QT-2.pdf"` after) and overwrites the shared public-disk file. H7 names only "customer" and "pdf_path"; it does not name `staff_notes` or attachments. Required change: extend H7's wording to list `staff_notes` and attachments (or hide `staff_notes` from non-owners), and state that a read role triggers a write. Route via the Architect (D13 H7).

**P3-04 — note (enforce mode, multi-org owner).** With the session org set to org B, the owner of a quote in a project of org A gets **403** in enforce mode on their own quote (probe), while the controller would allow it through `user_id`. In audit mode it passes with a `would_block` row. It is a consequence of the B4 org tie, and enforce is not on in production, but the plan should say the owner must be in the project's org context.

**P3-05 — should-fix (tests). Three mutants survive; two of them are on the legacy dashboard union behind P3-01.**
- N5: drop the `org_id` filter on legacy member rows (`UserWorkspaceController.php:35-40`): all tests pass. N6: drop `is_active` on the same query: all tests pass. So nothing proves an inactive or other-org legacy row stays off the dashboard.
- N2: revert `PlanCrosswalkController::currentOrgId()` (`:126-129`) to the raw session org: all tests pass. It feeds `update()` and `destroy()` (write paths), so the change from raw to validated org is untested.
- Required change: if the legacy union stays, add the inactive-row and other-org-row cases (or drop the union, P3-01); add one `update`/`destroy` test with a stale session org. Test wording via the Architect.

**P3-06 — note. Scope and spec compliance.**
- Only files on the D15.8 list changed: `PermissionService`, `RbacAudit`, the route map, `Quote` (scope only), the QuoteController read queries only, `UserWorkspaceController`, `OrgAdminController::projects()`, `PlanCrosswalkController::index` and `currentOrgId`, `ProjectWorkspaceController::show` and `currentOrgId`, the `Rbac.php` docblock, `dashboard.blade.php` line 84 only, `CurrentOrg`, and the tests. No route, migration or write path was touched, and `addProjectMember` is unchanged.
- **`TESTING.md` was not updated**, although D9 requires it (new tests, and the note under known failure #1 that non-JSON denials redirect by design).

**P3-07 — note. D13.7 is inaccurate about the workspace §4.5 check.** `ProjectWorkspaceController.php:53-64` compares the quote owner's first active org with the current org. A multi-org owner whose first active org differs from the project's org makes a legitimate member get a 403 there. It is pre-existing and not a leak, but the plan says the check is only reachable for the project's own org.

**P3-08 — note (regressions with Phases 1-3 but not 4).**
- The org admin's add-member form still writes legacy rows. For a project quote the page now shows only project rows (D15.2), so the add silently grants nothing.
- Members read but cannot edit (writes stay `user_id = me`).
- In enforce mode, owners of NULL-project quotes are blocked (`project_unresolved`).
- `getEstimatesList` now loads every visible quote in memory for the total (`calculateTotal()` per quote), so the cost grows with the visible set.
- `GET org-admin/projects` stays org-wide in audit mode (pre-existing) and now also lists quotes of ex-members.

### Checks that came out clean

- **Route map:** I verified the diff by hand. Exactly the 9 `project_param` entries became `quote_param` with the same param names (`id`, and `quoteId` on the two items routes), and `GET projects/{quote}/workspace` gained `quote_param => 'quote'`. That is 10 changes, and none is missing or wrongly renamed. The 9 registered quote routes with an id all carry it. `RoutePermissionMapTest` is correct, and mutations I1-I3 are all killed.
- **`isActiveProjectMember`:** it joins `projects` and enforces the org tie, `deleted_at IS NULL`, the member row's org, `is_active` and `project_id`. Mutations A1-A5 are all killed.
- **`RbacAudit`:** the scalar, bound-model and `project_param` fail-closed branches, quote-as-project-id, `withTrashed`, the `quote_id` audit column, non-numeric ids, the no_org ordering and the JSON 403 are all killed by tests (B1-B9). The extra queries are one per scalar `quote_param` request. The 302-vs-403 branch is textually unchanged.
- **Dual-read:** every `Quote::visibleTo` use (list, total, details, PDF, preview), the dashboard union, OrgAdmin chips and clause, workspace (quote id versus project id, route name, validated org), crosswalk (org filter, visibility group, filter param), `CurrentOrg` validation and fallback are all killed (C1-C4, D1-D3, E1-E3, F1-F2, G1-G2, H1-H5, N1, N3, N4, N7, N8).
- **Equivalent survivors:** E4 (dropping the explicit NULL-project check still denies because `(int) null` is 0) and the first attempt at B8 (my mutation was a no-op; the corrected B8b is killed by A9).
- **Isolation, cross-org:** I could not build a cross-org, soft-deleted-project, inactive-member, org-tie-mismatch or NULL-project leak through the QuoteController reads, the crosswalk index or the workspace. The remaining isolation gaps are P3-01 (dashboard metadata) and P3-02 (role not checked in audit mode).

### Mutation summary

I ran 48 mutations on a scratch copy (deleted; the real repo shows the same 17 changed files and no reports in `storage/app`). Suite in the real repo: 363 passed and 3 failed (the TESTING.md baseline). Survivors: N2, N5, N6 (P3-05), and the two equivalent mutants above.

### Verdict

DO NOT CONFIRM "no missing edge cases / no regression risk" for Phase 3 code and tests.
- P3-02 (member reads bypass the role check in audit mode) and P3-03 (H7 wording omits `staff_notes` and attachments, and read roles trigger a write) need a human decision and an Architect PLAN update before deploy.
- P3-01 (dashboard legacy union) and P3-05 (test gaps) are should-fix through the Architect.
- The code itself has no wrong-result bug I could construct beyond those.

### Must be verified on the B1 restored copy

1. `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL` is 0 (trashed included) before release.
2. The real `rbac_mode` row (Q0), and Q9 would-block counts on quote routes to size the widening.
3. Query plans and latency of `quotes.project_id IN (visibleTo subquery)` on the real `quotes` size, index use of `project_members(project_id, user_id)`, and list latency with the in-memory total.
4. Multi-org users' real session behaviour (`CurrentOrg` versus middleware agree, and the enforce-mode owner case in P3-04).
5. The route-binding order: bound `Quote` model versus scalar reaching `RbacAudit` in the real middleware stack (test A6 covers the model path on sqlite).
6. PDF engine and public-disk writes by a non-owner, and what a member's details JSON exposes.
7. The dashboard for a multi-org owner with legacy rows in a second org (P3-01).

## Architect compliance pass — Phase 3 D18

Verdict: **COMPLIANT.** The implementation matches D18, D17.1, D7 and D15. There is one stale comment, and one test needs QA's update. `php -l` is clean on all four files. I ran the RBAC suite (in-memory sqlite): 362 passed / 4 failed, which is the 3 known baseline failures plus `ProjectReadPathsTest > r3 my projects is the union…`. That failure is expected, because the test still asserts the legacy union that D18.6 removes. It goes to QA.

**Scope.** The Developer's D18 diff touches only `Quote.php`, `QuoteController.php`, `PlanCrosswalkController.php` and `UserWorkspaceController.php`. `git status` shows the same file set as the approved Phase 3 list plus QA's tests. No view, PDF presenter, route or migration changed in this step. I can't separate the Developer's changes to `tests/` from QA's by git alone.

**Line-by-line**
- `Quote.php:52-61` (D18.1): the member clause is added only when `$members && $orgId !== null`. The owner clause `quotes.user_id = me` is always applied and needs no role. Trashed projects, inactive membership and the org tie are inherited from `Project::visibleTo`. A NULL-project quote is reachable only through the owner clause.
- `QuoteController.php:142-146` `canReadTeamQuotes`: `orgId !== null && checkPermission(user, org, estimate_management, R)` with no project id, using the validated `CurrentOrg`. It is computed once per request and reused for the list and total (`:56,58,126`). It is used in details (`:155`), `generatePDF` (`:887`) and `previewPDF` (`:910`).
- Every D18.2 read path uses it. The other endpoints (`index`, customers, products, services, `duplicate` and all writes) are unchanged and still `user_id`-only, so nothing was missed. There is no other quote read.
- Single reads use `firstOrFail`, so a role-less member gets 404. The list and total are filtered.
- `staff_notes` (`:208`): stored value only for the owner, `null` otherwise, key kept, details JSON only. No other output changed.
- `generatePDF` (`:893-899`): the download is generated for every visible viewer. `Storage::put` and `pdf_path` are written only when the quote's owner is the viewer, and that owner path is byte-for-byte as before. The only other `pdf_path` uses are the delete in `destroy` (`:542-543`) and `Quote` fillable, so nothing else persists it for non-owners.
- `PlanCrosswalkController::index`: the flag is computed once. The quote list and row clause use `Quote::visibleTo(..., $members)`, and the `project_id IN Project::visibleTo` clause is added only if `$members`. A NULL org gives an empty page. `currentOrgId()` uses the helper.
- `UserWorkspaceController::index`: the id set is `Quote::whereIn('project_id', Project::visibleTo(...))` only. The legacy union is gone, and the `ProjectMember` import was removed with no remaining use. The `canReadEstimates` gate and pending-approval query are unchanged.

**Hand traces**
- Owner: sees own quotes with or without the role; staff notes shown; PDF written.
- Member with role: sees the project's quotes; `staff_notes` is null; the PDF downloads but nothing is written.
- Member without role: own quotes only, 404 on a teammate's quote, and the crosswalk shows only own-quote rows.
- Multi-org user with a different session org: `CurrentOrg` picks the session org if valid, so the org tie hides the other org's project. Own quotes stay visible.
- NULL-project quote: owner only.
- Trashed project: invisible to members.

**Finding (non-blocking, no PLAN update):** `UserWorkspaceController.php:33` still carries the old comment "quotes where this user has an active project_members entry". Cosmetic.

## QA results — Phase 3 D18

Date: 2026-09-25. Role: QA. Tests only (`tests/Feature/Rbac/ProjectReadPathsTest.php`, `ProjectTestCase.php` customers stub gained `deleted_at`). No app code, PLAN.md or TESTING.md edited. Nothing committed.

**Audit of my own work (after the interruption).** All D18 tests exist and are complete: H9-1 (2 tests), H9-2/H9-3 (provider: details, pdf, preview; audit 404 + would_block row, enforce 403 with controller not run), H9-2 org-level role in multi-org sessions (valid, other-org, stale), H9-2 NULL-project and trashed-project quotes, H9-2 user without org, H9-4 crosswalk, H9-5 owner needs no role, H9-6 workspace and dashboard, H7-1 (JSON and rendered PDF), H7-2 (download and preview), T-dash1 (2 tests), T-xw1, and `r3` rewritten (not deleted) to the D18 expectation (legacy rows grant nothing). No test is half-written; each asserts state changes, none is tautological (every one is killed below). Filesystem: all PDF tests use `Storage::fake('public')`; one stray `storage/app/public/quotes/QT-2.pdf` written by my earlier un-faked probe run was found and removed; a re-run of the project tests leaves nothing new under `storage/`.

**Counts.** `ProjectReadPathsTest` 141 passed (799 assertions). `php artisan test --filter=Rbac`: 381 passed / 3 failed (3038 assertions); the 3 failures are the known baseline by name (AuditMiddlewareTest "enforce mode blocks and logs", SeedMatrixTest "seeds expected counts", SeedMatrixTest "phase distribution").

**Coverage map.**
| Rule | Tests |
|---|---|
| H9-1 list filtered by role, total = list set, enforce blocks role-less at the middleware | `test_h9_1_*` (2) |
| H9-2/H9-3 details, pdf, preview: role-less member 404 + `would_block` (audit), 403 blocked and no controller/file (enforce); role member, owner 200; non-member 404 | `test_h9_2_and_3_*` x3 |
| Role is the current org's, multi-org session, stale session | `test_h9_2_the_role_gate_uses_the_org_level_role_of_the_current_org` |
| NULL-project and trashed-project quotes stay hidden from a role member | `test_h9_2_role_gate_is_not_bypassed_*` |
| No org: own quotes only | `test_h9_2_a_user_without_any_org_*` |
| H9-4 crosswalk: role-less sees own rows/filter list only; role member sees project rows | `test_h9_4_*` |
| H9-5 owner needs no role | `test_h9_5_*` |
| H9-6 workspace 302, dashboard panel empty | `test_h9_6_*` |
| H7-1 `staff_notes` null with key for non-owners, stored for owner, notes/terms/customer/attachments/items still visible; PDF render has no `staff_notes` | `test_h7_1_*` (2) |
| H7-2 member download writes no file, `pdf_path` and `updated_at` unchanged; owner writes; preview never writes | `test_h7_2_*` (2) |
| T-dash1 legacy rows (other org's project quote, inactive, other org, NULL project) put nothing on the dashboard; project members see quotes once | `test_t_dash1_*` (2), `test_r3_*` |
| T-xw1 stale session org: PUT/DELETE act on own org's row, never on another org's | `test_t_xw1_*` |

**Mutation proof** (scratch copy with its own `vendor/`; one mutation per run; `ProjectReadPathsTest`, copy baseline 141/141; copy's app files verified identical to the real ones afterwards).
| Rule | Mutation | Failed |
|---|---|---|
| Role gate, list | drop the flag | h9_1 list, h9_1 empty list, h9_5 |
| Role gate, total | drop the flag | h9_1 list |
| Role gate, details | drop the flag | h9_2_and_3 (details), h9_2 org-level role |
| Role gate, pdf | drop the flag | h9_2_and_3 (pdf) |
| Role gate, preview | drop the flag | h9_2_and_3 (preview) |
| Scope | `visibleTo` ignores `$members` | 8 tests (h9_1 x2, h9_2_and_3 x3, h9_2 org role, h9_4, ...) |
| Owner clause | owner needs the role | h9_1, h9_2 no-org, h9_4, h9_5 |
| C1 role check with a project id | quote's project id (from the route) instead of org level | SURVIVES, equivalent: on the read routes the member clause needs project membership anyway, so a role check that also requires membership of the quote's own project gives the same result; the list and crosswalk have no route id, so the check stays org-level |
| C1 role check with a project id | hard-coded project 2 | 18 tests (members of P1 lose the role check) |
| `staff_notes` | shown to non-owners | h7_1 |
| `staff_notes` | hidden from the owner too | h7_1 |
| `staff_notes` | key dropped for non-owners | h7_1 |
| `pdf_path` | written for non-owners | h7_2 download |
| `pdf_path` | never written (owner too) | h7_2 download |
| Dashboard legacy union | no filters | r3, t_dash1 |
| Dashboard legacy union | org filter only (no `is_active`, N6) | r3, t_dash1 |
| Dashboard legacy union | `is_active` only (no org, N5) | r3, t_dash1 |
| Dashboard legacy union | org and `is_active` | r3, t_dash1 |
| Dashboard | `canReadEstimates` gate removed | h9_6 |
| Crosswalk `currentOrgId` | raw session org (N2) | t_xw1 |
| Crosswalk | row clause not gated | h9_4 |
| Crosswalk | filter list not gated | h9_4 |
| Crosswalk | quote rows not gated | h9_4 |
| Crosswalk | role check removed | h9_4 |
| Workspace | role/membership check bypassed | h9_6, r6 x3 |
| Role check | never true (members with the role lose access) | 18 tests |

**Defects:** none. Observation (not a defect): `QuotePdfPresenter::present()` passes the whole `Quote` model into the PDF view data, so `staff_notes` is in the view data; the template never prints it, and the rendered PDF HTML is asserted free of it (test H7-1).

**What sqlite cannot prove:** the real role matrix and cache under production data and the cost of the extra `checkPermission` per read; the real PDF engine output and the public-disk write on the production filesystem (tests fake the disk, dompdf runs for real but only status is asserted); real multi-org sessions; MySQL `DATE_FORMAT` in the dashboard view (stubbed on sqlite).

**Totals:** ProjectReadPathsTest 141/141; Rbac 381 passed / 3 failed (baseline only); 26 mutations, 25 killed, 1 equivalent (reason above).

## Verifier final pass — Phase 3

_(Incremental log; verdict line last.)_

Method: I re-ran every earlier probe on a scratch copy of the current tree (deleted afterwards) and ran 20 new mutations of my own choosing on the D18 rules. Suite in the real repo: 381 passed and 3 failed, and the 3 are exactly the TESTING.md baseline.

### (1) Earlier findings, reproduced on the current tree

| Finding | Status | Probe result |
|---|---|---|
| P3-01 dashboard shows another org's quote | RESOLVED | A legacy row (org A) on an org-B quote gives `myProjects = []`. The legacy union is gone (`UserWorkspaceController.php:33-36`). |
| P3-02 / H9 role-less member reads | RESOLVED | A member with no `estimate_management` role now gets 404 on details and preview, an empty list, and no crosswalk rows, in audit mode. |
| P3-03 / H7 staff_notes | RESOLVED | The details JSON carries `staff_notes: null` for a member (key present), and the real value for the owner. `staff_notes` is not in the PDF presenter or template, so the preview and PDF never contain it (probe: no "SECRET" in the preview body). |
| P3-03 / H7 pdf_path write | RESOLVED | A read-only viewer requesting the PDF gets 200, `pdf_path` stays null and no file is stored. The owner still writes `quotes/<number>.pdf` and `pdf_path`. |
| P3-03 attachments | PARTIAL (note) | A member still sees the owner's attachment names and public-disk URLs. H7's decision covered `staff_notes` and the `pdf_path` write only, so this is accepted by omission. The human should confirm it in one line. |
| P3-05 N5/N6 | RESOLVED | The legacy union they guarded no longer exists. |
| P3-05 N2 (`PlanCrosswalkController::currentOrgId`) | RESOLVED | Mutation D18 (revert to raw session) is now killed. |

### (2) Trying to break D18 (no break found)

- **Multi-org and wrong org:** a user with roles in A and B and a membership in A sees the P1 quotes under session A, and nothing under session B (details 404). A stale session org falls back to the first active org. A role holder in org B only, with a membership row in org A, gets 404. A user whose org-A role is inactive but has an active member row gets 404.
- **No role anywhere, or no org:** a member row alone shows an empty list and 404. `canReadTeamQuotes` returns false for a NULL org, and `visibleTo` then keeps only the owner clause.
- **Semantic drift:** the owner keeps own quotes regardless of role (the owner list is unchanged). The owner check for `staff_notes` and the PDF write is `quote.user_id == Auth::id()`, the same identity the owner clause uses.
- **`staff_notes` elsewhere:** grep of `app/`, `resources/` and `routes/` finds it only in the model fillable, the owner-scoped create/update/duplicate paths and the details JSON. It is not in any list JSON, the PDF or preview, the audit log, or an error page.
- **PDF for non-owners:** they get a freshly generated download and never read or overwrite the stored file, so there is no stale or owner-generated file served and no path collision. The stored `quotes/<number>.pdf` is written only by the owner (quote numbers are unique).
- **Cost:** one `checkPermission` per request (several small queries), not per row. No N+1.
- **QA's equivalent mutant** (role check with the quote's own project id) is genuinely equivalent, because `visibleTo` already requires membership of that project.

### (3) My mutations (20): 19 killed, 1 survives

Killed: always-true gate (D1), ignoring the role (D2), staff_notes shown to all or hidden from the owner (D4, D5, D20), PDF always written or `pdf_path` updated for non-owners (D6, D7), scope ignoring the members flag (D8), details/preview/PDF/list/total ignoring the flag (D9-D13), crosswalk role check dropped or its clauses ungated (D14-D17), crosswalk raw session org (D18), dashboard raw session org (D19).

**P3F-01 — should-fix (test gap). Mutant D3 survives:** changing the gate in `QuoteController::canReadTeamQuotes` (`:142-146`) from `estimate_management` to another group (`procurement`) leaves all 217 tests passing. The h9 fixtures do not separate the two groups, so a wrong group name would go unnoticed. Required change: add a user who holds R on `estimate_management` but not on the wrong group (or the reverse), and add the mutant to D18's table. Test wording via the Architect.

### (4) Scope and stray files

Only the approved files are modified. There are no routes, migrations, write paths or views other than the one-line dashboard link at `dashboard.blade.php:84`. `storage/app/public/quotes` still holds exactly the pre-existing `29/`, `30/`, `QT-2026-0016.pdf` and `QT-2026-0026.pdf`, so the tests leave nothing behind (`Storage::fake` is used).

### (5) Outstanding documents

`TESTING.md` is still not updated (D9: the new tests, and the note under known failure #1 that non-JSON denials redirect by design). `ARCHITECTURE.md` and `CLAUDE.md` are untouched, as D9 says (applied at Checkpoint 2). These are the only outstanding items besides P3F-01.

### Verdict

CONFIRM "no missing edge cases / no regression risk" for the Phase 3 code within sqlite's limits: I found no leak, wrong result or 500 path in the current tree. For the tests I DO NOT FULLY CONFIRM until P3F-01 is closed (one missing group-separation case). That is a test addition only, routed via the Architect. Attachments visibility (P3-03) needs a one-line human acknowledgement.

### Must be verified on the B1 restored copy

1. `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL` is 0 (trashed included), and the real `rbac_mode` row (Q0), plus Q9 would-block counts on quote routes.
2. Query plans and latency of `quotes.project_id IN (visibleTo subquery)`, the index use of `project_members(project_id, user_id)`, and list latency with the in-memory total on the real `quotes` size.
3. Multi-org sessions: `CurrentOrg` versus the middleware org, and the enforce-mode owner-in-other-org case (P3-04).
4. Route-binding order at `RbacAudit` (bound `Quote` model versus scalar) in the real stack.
5. PDF engine and public-disk behaviour for owner writes and non-owner downloads, and the attachment URLs a member sees.
6. `PermissionService::checkPermission` cost per list request (delegation lookup plus role queries).

## QA results — Phase 3 H9-7/H9-8

Date: 2026-09-25. Added to `tests/Feature/Rbac/ProjectReadPathsTest.php`: `test_h9_7_procurement_only_member_is_denied_on_every_read_path` and `test_h9_8_estimate_only_member_is_allowed_on_every_read_path`. Fixtures re-read in `role_permission_matrix.php`: `order_fulfillment_csr` has procurement R and no estimate_management; `architect` has estimate_management R and no procurement. A guard inside the fixture asserts those levels through `checkPermission` (so a re-seed cannot make the tests meaningless). Both members are enrolled in P1.
- H9-7 (audit): details, pdf-preview, pdf give 404 each with a `would_block` row (quote and project ids), no pdf_path or file written; the list and total hold only the own quote; the crosswalk index shows only the own-quote row and filter list. Enforce (JSON): 403 on all five paths.
- H9-8 (audit): 200 on the three single reads (no pdf_path written), list holds teammates' quotes and own (total 98.00 = same set), crosswalk shows the project's rows but not P2's, zero audit rows. Enforce: 200 on all five, zero audit rows.
- Counts so far: ProjectReadPathsTest 143 passed (855 assertions).

**Mutation proof** (scratch copy with own `vendor/`; baseline 143/143; the copy's two controller files were verified identical to the real ones afterwards).
| Mutation | Failed |
|---|---|
| Group `estimate_management` -> `procurement` in `QuoteController::canReadTeamQuotes` | `h9 7 procurement only member is denied on every read path`, `h9 8 estimate only member is allowed on every read path` |
| Same swap in `PlanCrosswalkController::index` | h9 7 and h9 8 (crosswalk part) |
| Required level R -> F in `canReadTeamQuotes` | h9 8 |
| Required level R -> S in `canReadTeamQuotes` | h9 8 |
| Required level R -> F in the crosswalk index | h9 8 |
| Required level R -> S in the crosswalk index | h9 8 |
All killed; no survivors, no defects. Real repo: only tests changed; `storage/app/public/quotes` holds only 29/, 30/, QT-2026-0016.pdf, QT-2026-0026.pdf.

**Totals:** ProjectReadPathsTest 143/143; `--filter=Rbac` 383 passed / 3 failed (baseline only); 6 mutations, 6 killed.

## QA results - Phase 4 (LEAN, 2026-09-25)

Scope: PHASE4.md sections 2-6 on the uncommitted `feature/projects-entity` tree. Tests only; no app code touched. All runs on in-memory sqlite; nothing run against the local DB.

### Suite counts
- Baseline: Rbac 383 passed / 3 known failures. After backend + views (before this pass): 356 passed / 30 failed.
- Now: `php artisan test --filter=Rbac` = **506 passed, 2 skipped, 3 failed** (4297 assertions). Full `php artisan test` = 508 passed, 2 skipped, 3 failed.
- The 3 failures are exactly the known baseline, by name: `AuditMiddlewareTest > enforce mode blocks and logs`, `SeedMatrixTest > seeds expected counts`, `SeedMatrixTest > phase distribution`. No new failures.
- The 2 skipped tests are the documented defect tests below (body intact, one `markTestSkipped` line each).

### Triage of the 27 extra failures (all resolved as (a) or (b); no (c) among them)
(a) stale test, updated in `ProjectReadPathsTest.php`:
- Workspace is now `projects/{project}` and the old `projects/{quote}/workspace` is a 301: `test_route_allowed_for_a_member...` (workspace row expects 301), all `test_r6_*` rewritten to the project-id URL plus 301/404 behaviour (legacy row, NULL-project quote, id-collision fixture q2/p3, validated org), `test_h9_6` (a role-less member now gets the page with `canReadEstimates=false` and empty panels).
- org-admin/projects now passes `projects` (each with `project_members_list`) not `quotes`: `test_r4_*` (3 tests) and `test_r12_*` rewritten; legacy quote-only rows must never show as chips.
- Cross-org crosswalk write is 404 not 403: `test_t_xw1_*`.
- PHASE4 rule 6 (human decision): `test_r7_a_project_member_still_cannot_write_a_teammates_quote` and the write rows of `test_route_allowed_for_a_member...` replaced by `test_r7_a_project_member_with_the_level_can_write_a_teammates_quote` (all 6 write routes, audit and enforce, asserts the effect, ownership unchanged, project unchanged), `test_r7_denied_cases_change_nothing_in_audit_and_enforce` (wrong org, non-member, R-only role, inactive member, legacy quote-only row; snapshot of quotes and items unchanged; 403 in enforce, 404/500 in audit), `test_r7_o_without_f_can_edit_but_not_delete_a_teammates_quote`, `test_r7_the_owner_keeps_writing_their_own_quote`.
(b) `RoutePermissionMapTest::test_no_project_param_on_a_quote_uri` refined: the guard now rejects `project_param` on a URI that starts with `quotes/` or carries a `{quote}`/`{quoteId}` parameter (not the substring `quotes/`), requires at least 8 project_param entries, and `test_the_guard_still_rejects_genuine_quote_keyed_uris` proves the predicate still flags genuine quote URIs and passes the new `projects/{project}/quotes...` ones.
(c) real app defects: none among the 27. Defects found while writing the new tests are listed below.

### New tests
- `tests/Feature/Rbac/ProjectWritePathsTest.php` (95 tests incl. dataset rows, audit and enforce): projects index/list/show (visibleTo only, other org, non-member, trashed, unauthenticated, audit `would_block` vs enforce `blocked` rows); store (project + active creator row + `granted_by`, org_id/created_by inputs ignored, validated session org, stale session org, transaction rollback when `enrol` fails, status/name validation, wrong role, no org); update (O allowed, org_id/created_by ignored, R denied, wrong org, non-member, legacy-only row, trashed); destroy (F, 422 with live quote, allowed once quotes trashed, soft delete, O/R denied, wrong org, trashed); members store (added / re-activated / already member messages, one row only, F holder who is not creator, target outside org or inactive role or unknown user refused, A/O/R denied in both modes, non-member and wrong org, org/project inputs ignored, trashed 404); members destroy (deactivates only that row, creator removal, last active member removal, level below F, IDOR: row of another project, other org, legacy row, unknown id all 404 with nothing deactivated); quotes (`projects.quotes.store`: project from the route only, `project_id` input including a quote id ignored, invisible/other-org/legacy/trashed project refused, customer must be the caller's; create-from-list: project from route, foreign and unknown `{listId}` 404, invisible project; the retired `POST quotes`, `POST quotes/create-from-list`, `POST plan-crosswalk` are gone: 405/404, no route, no map key, no NULL-project quote created); crosswalk (store sets `project_id`, `quote_id` NULL, org from project, quote id sent as project id ignored, duplicate `(project_id, plan_line_code)` = 422 and same code in another project allowed, F required: R/O/no-group/organization_admin denied, wrong org/non-member/trashed; update/destroy allowed for F, denied below F, IDOR: other project, other org, legacy quote-only row, org mismatch, trashed project, unknown row all 404); org-admin addProjectMember through `enrol()` (flags, no legacy row, foreign or trashed project 404, foreign target 403, `quote_id` no longer accepted) and removeProjectMember (other org row 403); the 301 (visible only, 404/403 otherwise, guest 401); project page flags and row scoping; enforce block rows carry the mapped group/level/project.
- `tests/Feature/Rbac/ProjectDeletionRefusalTest.php` (19): B3 for `OrgSettingsController::destroy`, `RbacController::destroyOrganization`, `RbacController::destroyUser`: refused with live and with soft-deleted projects, nothing deleted; allowed with none; other orgs' projects do not block; sole-org logic (inactive other members still count as sole, any one sole org with projects refuses, org with other active members allows); non-owner and non-admin refused.
- `RoutePermissionMapTest`: `test_the_phase4_entries_match_the_spec_and_are_registered_routes` (all 13 Phase 4 keys pinned to group, level, batch, project_param), `test_the_retired_quote_and_crosswalk_create_routes_are_gone_from_routes_and_map`; the existing `test_every_project_uri_has_project_param_project` (D15) already covers every `{project}` URI.

### Mutation proofs (scratch copy at scratchpad/repo with its own vendor; the real tree was never modified)
55 mutants run against ProjectWritePathsTest + ProjectDeletionRefusalTest + ProjectReadPathsTest (+ RoutePermissionMapTest for map mutants). **54 killed, 1 surviving (equivalent).**
- Membership add/remove auth: drop F check, weaken F to R, drop visibleTo, drop target-org check, org from input, drop row-belongs-to-project, deactivate the whole project: all killed.
- B3: drop refusal and drop `withTrashed` on all three paths, destroyUser sole-org rule (reject nothing, ignore `is_active`): all killed.
- Creator auto-enrol: remove `enrol`, remove the transaction, org/created_by from input, `granted_by` null: all killed.
- Quote creation only inside a visible project: drop visibleProject (store, create-from-list, and the helper itself), `project_id` from input (both), list/customer not owner-scoped, duplicate drops project copy, writableQuote level gate dropped: all killed.
- IDOR on nested routes: crosswalk row org-mismatch, writableProject visibleTo, F check, project_id/quote_id from input, unique rule dropped or unscoped, legacyRedirect visibility, project page/index/list/update/destroy visibleTo, live-quote 422, forceDelete, org_id accepted on update, org-admin add for any org's project, without target-org check, raw create instead of `enrol`: all killed.
- Map mutants: project_param removed (members store, members destroy, quotes store), levels changed (members store F to S, project delete F to O, project store S to R, project update O to R, crosswalk store F to R): all killed. Two of these first SURVIVED (members store F->S, crosswalk store F->R, because the controllers re-check the level); fixed by `test_enforce_blocks_are_logged_with_the_mapped_group_level_and_project` and the pinned Phase 4 map test, then re-run and killed.
- Surviving equivalent: `PlanCrosswalkController::writableRow` `abort_if($row->project_id === null, 404)` (writableRow). Dropping it changes nothing observable: a NULL project id casts to 0, `whereKey(0)` matches no project, `writableProject` 404s. It is redundant defense in depth, not a test gap.

### Defects found in app code (NOT fixed; route to Architect before any change, strict rule)
All three are audit-mode only. Enforce mode blocks each in the middleware (the tests prove it). CLAUDE.md says production is reported to be in audit mode, so in production the controller scoping is the only guard.
- **P4-QA-1 (medium)**: `app/Http/Controllers/Frontend/ProjectController.php` `store` (line 38), `update` (line 60), `destroy` (line 73) do only visibility scoping, no role check. In audit mode a project member holding only R (`viewer_read_only`) can rename or soft-delete a project and any org member can create one (verified: viewer POST projects = 201, PUT = 200). Compare `ProjectMemberController::manageableProject` and `PlanCrosswalkController::writableProject`, which re-check the level. Skipped test `test_audit_mode_role_gate_on_project_update_and_destroy` holds the correct assertion.
- **P4-QA-2 (low-medium, same pattern as the retired `POST quotes`, so pre-existing in spirit)**: `QuoteController::store` (line 307) and `createFromList` (line 251) do not check `estimate_management` S in the controller. In audit mode an R-only member creates an estimate in a visible project (verified: viewer POST projects/{p}/quotes = 200). `writableQuote()` does check the level for edits/deletes, so creation is the odd one out.
- **P4-QA-3 (medium)**: `OrgAdminController::addProjectMember` (line ~582) and `removeProjectMember` (line 610) have no `project_management` F check in the controller; in audit mode any org member can add anyone to, or remove anyone from, any project of the org through the org-admin routes (verified: viewer add = 302 success, remove = 302 success). Add is now org-wide (any project of the current org) where it was previously limited to quotes owned by org members. Skipped test `test_audit_mode_role_gate_on_quote_store_and_org_admin_membership` covers P4-QA-2 and P4-QA-3.
- Note (by design, not a defect): the org-admin add route needs no project membership, so an org-level F holder who is not on a project can add members through it (PHASE4 decision 3, the recovery route). `ProjectMemberController` on `projects/{project}/members` does require membership.

### Tests left unfixed / open
- The two skipped defect tests above (intentionally skipped until the Architect decides the audit-mode role gate; remove the `markTestSkipped` line to activate).
- The 3 known baseline failures are unchanged and untouched: `AuditMiddlewareTest > enforce mode blocks and logs`, `SeedMatrixTest > seeds expected counts`, `SeedMatrixTest > phase distribution` (do not bundle a fix for these into this PR, see TESTING.md).
- Not covered (sqlite / scope): real MySQL locks and unique-violation race in `enrol()`, view rendering beyond the controller view data (views were not compiled in a browser), `PlanCrosswalkController::index` (still quote-shaped, pinned by Phase 3 tests).
- TESTING.md still says 383 passed and does not list the two new files; I may only edit `tests/`, so the Orchestrator should update it (Rbac now 506 passed / 2 skipped / 3 known failures).


## QA results - Phase 4 Checkpoint 2 changes

New file `tests/Feature/Rbac/ProjectCheckpoint2Test.php` (41 tests, audit and enforce where scoping matters). `ProjectTestCase` customers stub gained `is_active` (default true). Two stale pins updated in `ProjectReadPathsTest` (h9 4 crosswalk index, h9 7 procurement-only member): `projects` now asserted as `[P1]` (visible only, P2/P3 absent) and the row assertions are kept unchanged.

Result: `php artisan test --filter=Rbac` = 554 passed / 3 failed (4812 assertions). The 3 failures are exactly the known baseline, by name: `AuditMiddlewareTest > enforce mode blocks and logs`, `SeedMatrixTest > seeds expected counts`, `SeedMatrixTest > phase distribution`. No new failures.

Coverage: (1) an R-only owner (architect) is 403 on update / editor / item update / duplicate / item delete / delete of their OWN project quote in audit and enforce and on their own legacy NULL-project quote in audit; nothing changed in the DB; an estimator-owner succeeds on all six; an invisible quote is 404 (audit) / 403 (enforce middleware); a legacy NULL-project quote gets the org-level check (audit: allowed for a holder, enforce: fails closed 403, writes nothing); an org-less owner is 403; destroyItem returns 403, not 500. (2) destroyUser: refused for a project creator (live and trashed-only), nothing deleted, member rows intact; a plain member is deleted with active, inactive and legacy member rows gone and no dangling member or created_by rows; cleanup proven with FK cascades switched off; a forced failure in `User::deleting` rolls the member delete back; sole-owner refusal unchanged; non-admin 403. (3) show: `$customers` only for a creator, own active non-trashed customers, sorted; architect (R), superintendent (no group) and viewer get none and no modal; a multi-org user never sees another org's/user's customers; the form action equals `projects.quotes.store` for that project and posting creates the quote in that project owned by the caller; another user's customer is 404. (4) crosswalk `$projects`: unconditional visible list for member, owner and role-less member (superintendent), never invisible / trashed / inactive-membership / other-org projects, empty for a stranger, rows and the `project_id` filter never widen; csr blocked in enforce.

### Mutation proofs (scratch copy, real repo untouched)
13 mutants, 13 killed, 0 surviving.
- M1 owner level check removed in `writableQuote` (abort_unless true): killed (14 failures). M1b `|| owner` bypass: killed. M1c destroyItem HttpException rethrow removed: killed.
- M2 created_by refusal removed: killed. M2b trashed projects excluded from the refusal: killed.
- M3 `ProjectMember` cleanup removed: first run SURVIVED because `project_members.user_id` has ON DELETE CASCADE (the explicit delete is redundant while the FK exists); added the FK-off test, then killed. M3b cleanup moved outside the transaction: killed (rollback test).
- M4 customers not scoped to user, M4b is_active filter dropped, M4c customers not gated on create, M4d create level lowered S->R: all killed.
- M5 crosswalk `$projects` gated on the role again, M5b unscoped to the org instead of visibleTo: killed.

### Defects in app code
None blocking. One pre-existing minor observation, unfixed, for the Architect: `app/Http/Controllers/Frontend/QuoteController.php:573-577` (destroyItem `catch (\Exception)`) still turns a missing item (`firstOrFail` ModelNotFoundException, e.g. an item id already replaced by `saveEditor`, which rewrites items) into a 500 JSON instead of 404. Not part of this change and not tested.

## QA (LEAN), destroyUser refusal for owners of project quotes (RbacController.php:501)

Run before changes: Rbac 551 passed / 6 failed. The 3 baseline failures (AuditMiddlewareTest enforce, SeedMatrixTest counts, SeedMatrixTest phase distribution) plus 3 new ones, all in `ProjectCheckpoint2Test`: `delete user is refused for a project creator` (audit, enforce) and `delete user is refused when only a trashed project was created by them`. Cause: stale exact-message assertion; the created_by message now ends with " Reassign or delete those projects first." App behaviour is correct (RbacController.php:497-499). Fixed by updating the expected string in the 3 tests (2 assertion sites).

New tests in `ProjectCheckpoint2Test` (12 cases, audit and enforce where mode-sensitive): refused with the redirect error and an unchanged footprint (user, quotes, project_members, roles, orgs, projects) for a live project quote (also owning a legacy quote) and for only a soft-deleted project quote; allowed when all quotes (live and trashed) have NULL project_id; allowed with only project_members rows; sole-org refusal precedes the quote refusal; created_by refusal precedes the quote refusal; quote in another org's project is refused; non-admin is 403 and nothing deleted.

Mutation proofs (scratch copy, tracked files untouched): M1 refusal removed, killed (5 tests); M2 withTrashed dropped, killed (soft-deleted project quote test, both modes); M3 whereNotNull('project_id') dropped, killed (NULL project_id allowed test, both modes). Survivors: none.

Note: the sqlite test schema has no quotes.user_id cascade, so the actual cascade-delete of legacy quotes cannot be asserted here; that is a B1 (MariaDB) item.

Final: Rbac 566 passed / 3 failed, and the 3 failures are exactly the known baseline. Defects in app code: none.

## Phase 5 compliance (Architect, STRICT, 2026-09-25)

Verdict: **DEVIATIONS FOUND** (one missed dual-read, one guard weakness, two process amendments). Everything else COMPLIANT. Diff reviewed: 7 modified files + 3 new migrations; no DB touched.

### Compliant
- A1 `Quote.php:52-61`: `orgId null` -> `0 = 1`; `project_id IN Project::visibleTo` AND (`$members` OR `user_id = me`) is exactly plan A1 / decision 3. No org-independent owner clause left.
- A2 `PlanCrosswalkController.php:49-55`: only the project branch, `$members` false -> no rows; `writableRow` NULL 404 removed.
- A3 `ProjectWorkspaceController.php:90` (301 kept, decision 6), `QuoteController.php:181` and `duplicate` 422, `RbacController.php:501` (`withTrashed` kept). `RbacAudit.php:106-116` untouched as planned. No new routes, so no route-map change: correct.
- Migrations: guard() runs before any DDL/DML; maintenance + `PHASE5_BACKUP_CONFIRMED` skipped only in `testing`; P1; P3 + P8; P4 x3; RuntimeException with counts and SQL; drop order FK -> unique/index -> column -> NOT NULL (B2 `_000002:16-43`, B3 `_000003:14-50`); all three resumable; `down()` B1 re-nullables, B2/B3 throw. FK actions (quotes RESTRICT, others CASCADE, none SET NULL) make MODIFY NOT NULL legal on MariaDB. B3: the FK auto-index on `quote_id` disappears with `dropColumn`.
- Grep of live `app/`, `resources/`, `routes/`: no other `quote_id` reader outside `BackfillProjects`/`ProjectBackfillPlanner` (kept by decision 8) and `RbacAudit`/`AuditLog` (audit trail, out of scope). `Quote` queries in `UserWorkspaceController` and `ProjectWorkspaceController` are already project-keyed.

### Deviations / required changes
1. **MISSED dual-read (Blade), non-compliant.** `resources/views/user/dashboard.blade.php:15,45,66` (`Quote::where('user_id', $userId)` for `$quotesCount`, `$quoteStatusCounts`, `$recentQuotes`) is an org-independent owner visibility clause. It is not in the plan section 1 inventory (my omission; the inventory grep did not cover Blade) and the Developer did not catch it. Under 5a an author removed from a project still sees that quote's number/status on the dashboard. See PLAN amendment A5.
2. **`env()` in migrations (`_000001:34`, `_000002:57`, `_000003:60`).** With `php artisan config:cache` (typical on shared-hosting FTP deploys) `env()` returns null outside config files: the guard would refuse forever (fail-safe, but confusing, and a "fix" tempts people to weaken it). Read it through a config key. See A6.
3. **Deviation (a), A4 removals on the 5a branch: ACCEPTED, no revert.** Verified: nothing in `app/` calls `ProjectMember::quote()`/`PlanCrosswalk::quote()` or mass-assigns `quote_id`; both columns are nullable since Phase 1, so inserts without `quote_id` work on the old schema; 5a rollback is a code redeploy. Cut rule (A7): 5a = app/ + view + test commit(s) only; 5b = the three migrations + their tests in a separate later commit, never uploaded or migrated in the 5a window.
4. **Deviation (b), P5 org-mismatch as release gate only: ACCEPTED.** Plan section 4 lists only P3+P8 as project_members guards; P5 is a section 3 gate. No change; recorded that gate P5 must be signed off in PROGRESS before 5b.
5. **Plan inconsistency (decision 8 vs B4).** After 5b `ProjectBackfillTest` (needs nullable `quote_id`) cannot pass on the new schema, while decision 8 keeps the command 30 days. See A8.

### Risks confirmed / added
- 5a with NULL `quotes.project_id`: owners lose those quotes even in audit mode. Consistent with the plan (gate 1 + P1 = 0 before 5a, section 6 risk 4). New quote paths all set `project_id` (`QuoteController.php:284,380,613`), so P1 cannot regrow during the soak. Gate P1 must be re-run immediately before 5a and before 5b.
- Audit mode (production): controller scoping is the guard; A1-A3 are in the controllers/model, so 5a still hides. `project_unresolved` is only logged. OK.
- What breaks when 5b runs: `projects:backfill` (inert, asserts nullable), `ProjectBackfillTest`, `ProjectSchemaTest` nullability cases, `ProjectTestCase` stubs (`legacyMember`, NULL-project `mkQuote`), and the 25 pinned tests the Developer listed; the Phase 1 migrations' `down()` (`_000003/_000004`) become unusable, which is expected. QA owns these.

### Phase 5 compliance, re-check of amendment 2 A5/A6 (2026-09-25)
Verdict: **COMPLIANT** (A5, A6). A7-A9 not in scope of this pass.
- A5: `UserWorkspaceController.php:94-98` computes `$canReadQuotes` (`estimate_management:R`, org_id passed, false with no org) and builds `quotesCount`, `quoteStatusCounts`, `recentQuotes` from `Quote::visibleTo($userId,$orgId,(bool)$canReadQuotes)`; passed to the view at `:111-113`. `dashboard.blade.php` lost the three `Quote::where('user_id')` reads (diff `:15,45,66`); orders/lists untouched. No Blade/JS fallback remains (only consumers are `:64-66`, `:253`, `:355`, `:392`, all injected vars). `pendingApprovals` and `myProjectIds` were already project-keyed.
- A6: `config/rbac.php:39` `phase5_backup_confirmed => env('PHASE5_BACKUP_CONFIRMED', false)`; migrations `_000001:37`, `_000002:59`, `_000003:66` read `config(...)`; guard skipped only under `testing` (`:32/:54/:61`), text unchanged.
- Grep app/ resources/ routes/ (PHP, Blade, JS): no org-independent `user_id` quote clause and no `quote_id` reader left, other than `QuoteItem.quote_id` (item FK, unrelated), the kept BackfillProjects/Planner, and audit code. Only remaining `user_id` on quotes is the AND-narrowing inside `Quote::scopeVisibleTo`.
- Ruling on r11 (`ProjectReadPathsTest.php:339`): failure is the intended supersession. A5 says the counters "must agree with the quotes index", which contradicts H8 (owner-only). H8 was a human decision, so this needs one line of human confirmation at Checkpoint 2 (Phase 5 decision 3, "no org-independent owner clause", is the basis). D15.1/D18/plan line 1535 are superseded by A5 for the dashboard.
- New expectation (QA rewrites r11 and adds the A5 tests). Counts and recent quotes = exactly the quotes index for the current org:
  1. Viewer is an active member of project P (org A) with estimate_management:R: sees every quote in P, any author (own + teammate), and in any other project they are a member of.
  2. Viewer is a member but has no R on estimate_management: sees only quotes they authored inside their member projects; teammate quotes (QT-2/QT-3) absent from count, recent list and status chart; "My Projects" panel unchanged.
  3. Author removed from P (membership inactive/deleted) or project soft-deleted: their own quote no longer counts or lists, with or without R.
  4. Quote with NULL project_id: never counted.
  5. Current org context = org B (viewer has no active membership there): quotesCount 0, empty recentQuotes, chart 0/0/0, even though viewer authored quotes in org A. No current org: same zeros.
  6. Orders/lists counters unchanged (author-scoped). Counts must equal the count on the quotes index for the same context.
  Mutations: swap scope back to `where user_id` (cases 3 and 5 fail); pass `true` for members (case 2 fails).

## QA results - Phase 5 (QA, STRICT, 2026-09-25)

Scope: Phase 5a app changes plus the three 5b migrations on branch `feature/projects-entity-phase5` (uncommitted). Tests only were edited (`tests/`), plus TESTING.md count/list as instructed. Nothing ran against a real database: every test uses in-memory sqlite, no `migrate`, no `projects:backfill`, the local MySQL was never queried.

### Result
- `php artisan test --filter=Rbac`: **651 passed / 3 failed**. The 3 failures are exactly the known baseline: `AuditMiddlewareTest::enforce mode blocks and logs` (:71), `SeedMatrixTest::seeds expected counts` (:20), `SeedMatrixTest::phase distribution` (:30). No new failure. Before QA the Developer state was 542 / 29 (3 baseline + 26 stale pins).
- New/rewritten tests this pass: 26 stale failures rewritten, 81 new tests (Phase5MigrationsTest 54, ProjectDashboardCountersTest 23, QuoteAuthorScopeGuardTest 4) plus 2 new tests in ProjectCheckpoint2Test (trashed NULL-project destroyUser refusal, writableQuote project-id call check).
- No application defect found. Every one of the 26 stale failures was a pin of the old dual-read or H8 behaviour. Observations O1-O5 below are not defects.
- No role or permission grant is added by Phase 5, so there is no new SOD rule to test (the 8 bidirectional rules are untouched and `SodTest` still passes).

### The 26 stale failures: triage and rewrite (denial coverage kept, never weakened)
All are stale pins of intended Phase 5 behaviour (Quote::visibleTo = project visible AND (R OR author); org null -> nothing; NULL-project quote visible to nobody; crosswalk index only project rows and only with R; destroyUser refuses any owned quote).

`ProjectReadPathsTest` (12 tests, 20 datasets):
- `r1 list shows own and project quotes...` -> `test_r1_list_shows_only_quotes_in_visible_projects_and_the_total_matches`: expects only Q1+Q2 (total 150.00, pagination 2); own NULL-project and own quote in a non-member project now asserted hidden.
- `r1 owner sees own quotes in any org context and null project quotes` -> `..._only_through_project_membership_never_a_null_project_quote`: owner sees Q1-Q3 only; after the owner is deactivated in P2 the own Q3 disappears (kills the owner clause); stale session org still falls back to org A.
- `r2 owner reads a null project quote` (3 datasets) -> `test_r2_owner_gets_404_for_their_own_null_project_quote`.
- `r11 counters stay owner only ...` -> `test_r11_counters_follow_the_quotes_index_while_a_null_project_own_quote_is_never_counted` (see H8 note below).
- `r5 crosswalk index no longer throws...`: rows now `[backfilled, native]`; the quote-only "own" row is asserted hidden.
- `r10 owner of a null project quote in audit mode` -> now 404 plus the `would_block`/`project_unresolved` audit row (was 200).
- `r13 a quote in a trashed project is visible to its owner only` -> `..._visible_to_nobody_its_author_included`.
- `h9 1` (list role gate): author-only fixtures moved into P1 (member project); role-less member sees only own quote in P1, not the teammates', not the NULL-project one; est total 169.00.
- `h9 2 a user without any org reads only own quotes` -> `..._reads_nothing_not_even_their_own_quotes` (list empty, details 404, own project quote and NULL quote).
- `h9 4`, `h9 7`, `h9 8`: role-less/procurement-only members get no crosswalk rows at all (was own-quote rows); estimate-only member gets `[backfilled, native]`; the group-separation guards and the enforce-mode 403s are unchanged.

`ProjectCheckpoint2Test` (5 tests, 13 datasets):
- `an r only owner is 403 on their own legacy null project quote` (6 datasets) -> `..._is_404_on_their_own_null_project_quote_and_nothing_changes` (no write, no duplicate, item kept). Status is 404 instead of 403 in audit because the quote is no longer resolvable; enforce still 403 (`project_unresolved`), asserted in the next test.
- `a legacy null project quote gets the org level check...` -> `test_a_null_project_quote_is_untouchable_even_for_its_author_with_the_level_in_audit_and_enforce` (audit 404 on PUT and DELETE, enforce 403, nothing written).
- `an owner without any org role is 403 on their own quote` -> `..._is_denied_...`: 404 for both a P1 quote and a NULL-project quote (no org context -> nothing visible), PUT and DELETE, nothing changed.
- `delete user is allowed when all their quotes have a null project_id` (2 datasets) -> `..._is_refused_when_their_only_quotes_have_a_null_project_id` (live and trashed NULL quote, footprint unchanged); new `..._refused_for_only_a_trashed_null_project_quote`.
- `crosswalk projects list ... rows stay narrow` and `crosswalk projects filter does not widen rows ...`: role-less member gets `rows == []` (was own quote-only row), `projects` still `[P1]`, an invisible `project_id` is still ignored, not widened.

### H8 supersession (r11): human-confirmed-at-Checkpoint-2 change
r11 pinned the Phase 3 H8 owner-only dashboard counter rule ("counters stay owner only while My Projects shows the teammates' quote"). Amendment 2 A5 supersedes it (Architect ruling in "Phase 5 compliance, re-check"): the counters equal the quotes index. r11 was rewritten to the new rule (viewer with R sees both P1 quotes, count 2, chart [2,0,0], the own NULL-project quote 'OWN-1' is never counted or listed, counter equals `quotes/list` total). **This replaces a human decision (H8); it needs the human's one-line confirmation at Checkpoint 2.**

### New tests
1. `ProjectDashboardCountersTest` (23, audit and enforce where scoping matters; the dashboard has no route-map entry so enforce is pass-through, both modes are still run): R member sees all authors in all member projects; recent list limited to 5 while the counter is not; no-R member sees only own quotes in member projects (teammates, non-member project, NULL project all absent); no-R with no own quotes -> 0/0/0; author removed (inactive or deleted row) with and without R (4 cases, both modes, and the quotes index agrees); other-org member row gives nothing; trashed project takes quotes off every dashboard incl. the owner's; NULL-project quotes never counted; org B context (multi user) -> 0, empty, 0/0/0 although org A quotes were authored; no org -> zeros; org B user does not see org A; orders and lists counters stay author-scoped; recent quotes equal `Quote::visibleTo` for 5 users. Every case asserts counter == view var == quotes-index `pagination.total` == chart sum.
2. `Phase5MigrationsTest` (54): each migration on the real pre-5b schema built by `RbacTestCase` (real Phase 1 migrations). Guards outside `testing` (simulated by setting the app env to `production`, faking the `MaintenanceMode` contract, and `config('rbac.phase5_backup_confirmed')`): refuses without maintenance mode, refuses without the confirmation, ordering (maintenance first, then confirmation, then data), data violation still refused with both, both given + clean data applies, in `testing` neither is required, the confirmation comes from config not the environment. Preconditions: quotes NULL project (live, trashed, counts), project_members (uncovered active legacy row, count with covered/inactive ignored, legacy row on a NULL-project quote, active row with neither key, inactive row with neither key), plan_crosswalk (NULL project, org mismatch, duplicates counted per pair, same code in two projects is fine). Exact refusal messages and check SQL asserted; a refusal leaves schema and rows byte-identical. Success: expected schema (NOT NULL, FK kept and still RESTRICT on quotes, `quote_id` gone with its FK/unique/index, `unique(project_id, plan_line_code)`, `(org_id, project_id)` index kept), legacy rows deleted only when covered or inactive, project rows kept, NULL and duplicate inserts rejected afterwards, models work (`ProjectMember::enrol`, `PlanCrosswalk::create`, `quote()`/`quote_id` fillable removed), deleting a project with quotes still restricted. Re-runnable: refuse -> fix data -> rerun; run twice; resume from each partial state I can build (members: after FK drop, after unique drop, after column drop; crosswalk: after FK drop, after column drop, before the unique; a resumed crosswalk run still refuses a duplicate created meanwhile). `down()`: quotes re-nullable (idempotent, FK kept), members and crosswalk throw "Irreversible ... Restore the backup" and change nothing. All three in order; a later refusal leaves earlier ones applied.
3. `QuoteAuthorScopeGuardTest` (4): text scan of `app/` and `resources/` for `Quote::`/`DB::table('quotes')` statements naming `user_id` and for `quotes.user_id`; allowlist is `Quote::scopeVisibleTo` (exactly 1), `RbacController::destroyUser`, the inert `BackfillProjects` command, and `Quote::create`. Also pins that `$quote->user_id` is compared exactly twice (staff_notes and PDF write in `QuoteController`) and that `User` has no `quotes()` relation. Self-test of the scanner on 9 samples. Limit: it cannot see a Quote builder stored in a variable and filtered in a later statement; the behavioural tests cover that path.
4. `ProjectCheckpoint2Test::test_writable_quote_level_check_carries_the_quotes_project_id` (new): a recording `PermissionService` subclass asserts `writableQuote` makes a level check scoped to the quote's project id (the behavioural effect is redundant with `visibleTo`, so this is the only way to pin the contract).

### Tests that die with 5b (nothing deleted yet, per A8)
Experiment on a scratch copy: appended the three migrations to `RbacTestCase`'s schema list. Result: 539 of 654 fail, because `ProjectTestCase::member()` writes `'quote_id' => null` and `buildFixture` creates Q0 with a NULL project. After patching only those two (scratch only) the residual is 243 failures, by root cause:
- To DELETE at the 5b step (A8): `ProjectBackfillTest` (all 70 residual, whole file), `ProjectSchemaTest` legacy/nullability cases (16: `test_quotes_project_id_is_nullable_fk`, `test_project_members_schema`, `test_project_members_allows_multiple_null_project_legacy_rows`, `test_plan_crosswalk_schema`, `test_rollback_000003/000004_*` x4, `test_full_down_in_reverse_order...`, `test_legacy_quote_id_fk_*` x3, `test_force_deleting_quote_cascades_legacy_*`, `test_legacy_unique_quote_user_enforced`, `test_force_deleting_project_referenced_by_quote_fails`, `test_enrol_unsaved_project_throws_and_leaves_legacy_row_untouched`, `test_scope_visible_to` legacy branch); `legacyMember` helper.
- Legacy-row or NULL-project tests to delete or reformulate: `ProjectReadPathsTest` `test_route_denies_a_legacy_quote_only_row`, `test_r1_legacy_only_membership_shows_nothing_in_the_list`, `test_r3_...` (legacy rows), `test_r4_chips_..._legacy_rows_never`, `test_r12_a_backfilled_member_...`, `test_r6_legacy_only_member_...`, `test_t_dash1_legacy_rows_put_nothing_on_the_dashboard`, `test_t_dash1_project_members_still_see_the_project_quotes_once`; NULL project: `test_route_fails_closed_for_a_null_project_quote_even_for_its_owner` (10 datasets; keep as "unknown quote id" if wanted), `test_r6_null_project_quote_old_url...`, `test_r10_owner_of_a_null_project_quote...`, `test_h9_2_role_gate_is_not_bypassed_by_a_null_project...` (trashed half survives), `test_r11...` (NULL half), `test_r1_list_shows_only...` and `test_h9_1...` (NULL fixtures); `ProjectCheckpoint2Test` the 6-dataset `..._404_on_their_own_null_project_quote...`, `test_a_null_project_quote_is_untouchable...`, NULL halves of `..._denied_on_their_own_quote`, the two `..._refused_when_their_only_quotes_have_a_null_project_id` datasets and `..._refused_for_only_a_trashed_null_project_quote`, `test_delete_user_removes_a_plain_members_...` and `..._does_not_rely_on_the_fk_cascade` (legacyMember); `ProjectMembershipTest` `test_m7_legacy_quote_only_row...`, `test_a3_null_project_quote_is_unresolved...` (2), `test_a6b_...null_project...`; `ProjectDashboardCountersTest` NULL-project cases (3 datasets/tests) and `test_a_member_of_another_orgs_row...` (writes `quote_id`).
- Helper-only failures, fix by dropping `quote_id` from the helpers: the crosswalk builders `crosswalk()` (ReadPaths), `xwRow()` (Checkpoint2), `xw()` etc. write `quote_id`; affected: all `ProjectWritePathsTest` crosswalk cases and the `r5`/`h9`/`t_xw1` crosswalk tests, plus tests whose fixture semantic depended on Q0 being NULL-project.
- `Phase5MigrationsTest` (6 residual) needs the PRE-5b schema: at the 5b step give it its own schema builder (or keep the migration list out of `RbacTestCase` for this class).

### Mutation proofs (scratch copy with its own vendor; verified `Quote::class` resolves to the scratch path; real repo untouched)
Suite per mutant: full Rbac minus the 3 baseline failures, stop on first failure. 57 mutants, **57 killed, 0 surviving**. One mutant (writableQuote project id dropped) first survived and was killed by the new recording test.
- visibleTo (9): drop role/author gate; invert it; author on the wrong column; drop org-null guard; org-null returns unscoped; org-null falls back to author; project clause -> any project; project clause OR author (owner clause back); project clause ignoring org. All killed (first killer: ProjectDashboardCountersTest, ProjectCheckpoint2Test).
- Dashboard A5 (7): scope back to `user_id`; members forced true; members forced false; R check bypass; recent, status counts, count each reverted individually. All killed by ProjectDashboardCountersTest.
- Crosswalk index (3): drop `$members` gate; drop project visibility; no-R falls back to all rows. All killed.
- writableQuote (4): drop level check; weaker level R; forced members flag; project id dropped (killed only by the new recording test; behaviour is otherwise equivalent because `visibleTo` already enforces the same active-member rule as `PermissionService::isActiveProjectMember`).
- destroyUser (3): re-add `whereNotNull('project_id')`; drop `withTrashed`; remove refusal. All killed.
- Migration guards (28): per migration remove the maintenance check, remove the confirmation check, `env()` instead of `config()` (9); 000001 remove NULL guard, ignore trashed, skip NOT NULL, `down()` no-op; 000002 remove uncovered guard, count inactive rows, ignore the user match, remove neither-key guard, skip legacy delete, delete all rows, skip column drop, skip NOT NULL, `down()` not throwing; 000003 remove each of the NULL/org-mismatch/duplicate guards, invert the mismatch comparison, skip the unique, unique on `project_id` only, skip NOT NULL, skip dropIndex, `down()` not throwing. All killed.
- Grep guard: a re-introduced `Quote::where(...)->orWhere('user_id', ...)` in `ProjectController` fails `QuoteAuthorScopeGuardTest`.

### Observations (not defects, for the Architect; nothing fixed by QA)
- O1. `_000002_tighten_project_members.php` guard: the "neither project_id nor quote_id" (P8) message is reachable only for INACTIVE rows; an active row with neither key is counted by the uncovered check first (different message). Both refuse; only the wording differs.
- O2. `_000002` guard returns early when `quote_id` is already gone (resumed run), so a stray NULL `project_id` row would be stopped only by the database's NOT NULL change: refused on sqlite and on strict-mode MariaDB (Laravel default `strict => true`); a non-strict `sql_mode` would coerce NULL to 0 and then hit the FK. Low risk; B1 should confirm `sql_mode` on production.
- O3. `RbacController.php:501` refusal text says "owns quotes inside projects"; it now refuses any owned quote including a NULL-project or trashed one. Cosmetic; the tests pin the message string, so a wording change is a test change.
- O4. `PHASE5_BACKUP_CONFIRMED` is truthy for any non-empty string except `0`/`false`: `off` or `no` would count as confirmed. Cosmetic hardening only.
- O5. The dashboard recent list is `orderByDesc('quotes.created_at')` with no tiebreaker (same as the quotes index behaviour before); tests do not rely on order.

### MariaDB-only gaps for the B1 checklist (sqlite cannot prove these)
1. Real FK names: `dropForeign(['quote_id'])` relies on Laravel's default names (`project_members_quote_id_foreign`, `plan_crosswalk_quote_id_foreign`); the migrations look the FK up by column first, but confirm on a restored production copy.
2. Drop-FK-before-unique/index ordering: MariaDB refuses to drop an index still used by an FK; sqlite has no such rule. The auto-index on `quote_id` disappearing with `dropColumn`.
3. `->nullable(false)->change()` on MariaDB rewrites the column definition (unsigned bigint, default, FK, `restrictOnDelete` on `quotes.project_id`); sqlite rebuilds the table instead. Also ALTER lock time on `quotes` and `plan_crosswalk`, and `Schema::getColumns()['nullable']` semantics.
4. Implicit DDL commit: my partial-failure tests build the intermediate states by hand; a real mid-migration failure on MariaDB (no DDL rollback) and the resumed run must be rehearsed. On sqlite the migration DDL is transactional, so a genuine mid-failure would roll back and never produce those states.
5. `unique(project_id, plan_line_code)` index key length: `plan_line_code` is `varchar(255)`; with utf8mb4 the key is 1028 bytes plus the bigint, fine under DYNAMIC (3072) but over the 767-byte limit on COMPACT/REDUNDANT row formats or MariaDB before 10.2. Check `ROW_FORMAT` and version.
6. Collation: duplicates under `utf8mb4_unicode_ci` are case- and trailing-space-insensitive; sqlite compares binary. The guard's GROUP BY uses the same collation as the unique, so it should be consistent on MariaDB, but sqlite cannot show it.
7. Maintenance-mode detection on the real `down` file/driver and the confirmation flag under `php artisan config:cache` (tests set `config()` directly, which proves config-not-env but not a cached config file).
8. `sql_mode` strictness (O2), and the three items already listed in the plan's B1 extension: the `ProjectMember::enrol()` unique-violation race, `lockForUpdate` in `ProjectController::destroy`, and the `quotes.user_id` CASCADE versus `quotes.project_id` RESTRICT interplay in `destroyUser`.

### Not done / left open
- No deletion of `ProjectBackfillTest`, backfill-only schema cases or `legacyMember` (A8, 5b step). No change to `RbacTestCase` (still builds the pre-5b schema, which is what the 5a suite and `Phase5MigrationsTest` need).
- The workspace 301 is unchanged and still covered (`test_route_allowed_for_a_member...` and r6).
- Defects to route to the Architect: none.

## Phase 5 Verifier findings (Architect rulings, 2026-09-25)
Verifier: no security defect in Quote::visibleTo, callers, dashboard (A5), crosswalk, legacyRedirect, destroyUser or guard ordering; 651 passed / 3 known failures. Rulings:
- **F1 (Medium, MariaDB only): FIX NOW (defensive) + B1 assertions.** `2026_09_24_000004` (line 15) creates `index(['org_id','project_id'])` on plan_crosswalk, and InnoDB accepts any index with org_id as leftmost column for the org_id FK, so dropping `(org_id, quote_id)` should not raise errno 1553. sqlite cannot prove it, so 5b migration 3 must not depend on that silently: it creates `(org_id, project_id)` if absent before dropping. The `change()` must be proven to keep the project_id FK (CASCADE) and the unique.
- **F2 (Medium): FIX NOW.** `env()` returns truthy strings for off/no/n/disabled.
- **F3 (Medium): DOCUMENT (runbook text only, no artisan command).** A7/A9/B5 are still open and are required before 5a. A pre-flight command would be speculative abstraction; the three SQL checks already live in the migration refusal messages.
- **F4 (Low): FIX NOW.** Coverage must require an active same-org project row; otherwise the migration refuses (re-enrol chance is preserved).
- **F5 (Low): HUMAN CONFIRM at Checkpoint 2** (r11 supersedes Phase 3 H8).
- **F6 (Low): DOCUMENT.** Guard test blind spots are covered by behavioural tests; strengthening a static scan further is not worth the cost. `pendingApprovals` (UserWorkspaceController:75-80) is already project-scoped through `$myProjectIds`; it needs approval_authority:A not estimate R. OUT of Phase 5, follow-up.
- **F7 (Info): none.** down() honesty, BackfillProjects fail-closed after 5b, ProjectBackfillTest dying at 5b (A8) all accepted.
- QA observations: destroyUser wording FIX NOW; dashboard recent list needs an `id` tiebreak FIX NOW.
See PLAN.md "Phase 5 amendment 3".

## QA results - Phase 5 round 2 (amendment 3 T1-T5, QA, STRICT, 2026-09-25)

Tests only (`tests/`, plus the TESTING.md count). No `migrate`, no backfill, local MySQL never touched; in-memory sqlite only. Not committed.

### Result
- `php artisan test --filter=Rbac`: **682 passed / 3 failed**. The 3 failures are exactly the known baseline: `AuditMiddlewareTest::enforce mode blocks and records blocked` (:71), `SeedMatrixTest::seeds expected counts`, `SeedMatrixTest::phase distribution`. No new failure. Developer state before this round was 642 / 12 (3 baseline + 9 stale).
- Application defects found: none.

### Stale tests updated (assertions kept, only the expected text/SQL changed)
- `ProjectCheckpoint2Test::QUOTE_REFUSAL` (line 355) now 'This user owns quotes and cannot be deleted. Reassign or delete those quotes first.' Fixes the seven "delete user is refused..." tests plus the "quote refusal applies when the project belongs to another org" test (8 tests, one constant). All still assert `assertSessionHas('error', ...)` with the full string.
- `Phase5MigrationsTest::test_members_refuses_an_active_legacy_row_not_covered_by_a_project_row`: regex now requires "not covered by an active same-organization project membership for the same user", `membership_org_mismatch.csv`, and the full check SQL including `LEFT JOIN project_members p2 ON p2.project_id=q.project_id AND p2.user_id=pm.user_id AND p2.org_id=pm.org_id AND p2.is_active=1` and the `WHERE pm.project_id IS NULL AND pm.is_active=1 AND p2.id IS NULL;` tail (stricter than before).

### New tests (31)
- T1 (`Phase5MigrationsTest`): config file re-required under `PHASE5_BACKUP_CONFIRMED` set via putenv/$_ENV/$_SERVER (restored in `finally`): off, no, n, 0, false, disabled, OFF, empty string, an arbitrary string -> false; 1, true, on, TRUE, yes -> true; unset -> false. Each of the three migrations refuses outside `testing` (maintenance on) with config false, null or 0 (9 datasets), message asserted and schema and rows unchanged.
- T2: members migration refuses when the only covering project row is inactive, and when it is in another organization (message and unchanged state asserted); passes with an active same-org row (legacy row dropped, covering row kept); an inactive legacy row with no covering row is still deletable.
- T3: with `(org_id, project_id)` dropped beforehand the only org-leading index is `(org_id, quote_id)`; after the migration `(org_id, project_id)` exists (exactly one org-leading index), `quote_id` column and index gone, unique(project_id, plan_line_code) exists. With the index present: same end state, no duplicate index, FKs on org_id and project_id still declared.
- T4: `ProjectDashboardCountersTest::test_recent_quotes_with_equal_created_at_are_ordered_by_id_descending` (7 quotes at one timestamp, expects the 5 highest ids in descending order). destroyUser wording is pinned by the updated constant above.

### Mutation proofs (scratch copy `.../scratchpad/mut` with its own vendor; `Quote::class` resolved to the scratch path; real repo untouched). 5 mutants, 5 killed, 0 surviving
- M1 `filter_var` removed from `config/rbac.php` -> killed by the config mapping tests (off, no, n, 0, false, disabled, OFF, ...).
- M2a `p2.is_active` dropped from the coverage join -> killed by `members refuses when the only project row is inactive` (only).
- M2b `p2.org_id` dropped -> killed by `members refuses when the only project row belongs to another organization` (only).
- M3 index creation skipped (`if (false)`) -> killed by `crosswalk recreates the org project index ...` (only).
- M4 `orderByDesc('quotes.id')` dropped -> killed by the new ordering test (sqlite returns rowid ascending among ties).

### What sqlite can and cannot prove (carry to the B1 checklist)
T3 proves only the migration's own logic: it looks at `Schema::getIndexes`, adds the index when no other org-leading one exists, and the end state has one. It cannot prove that MariaDB accepts `dropIndex(['org_id','quote_id'])` while the org_id FK exists (errno 1553/1451 depends on InnoDB choosing another index as the FK backing index); sqlite has no such rule and rebuilds tables on `change()`. Still MariaDB-only, all already on the B1 list: real FK/index names, drop order, `change()` preserving the project_id FK (CASCADE) and unique, `SHOW CREATE TABLE` before/after for plan_crosswalk, quotes and project_members, unique key length and collation, DDL not being transactional, `sql_mode`, maintenance-mode detection and `config:cache` behaviour. T1 additionally cannot prove behaviour under `php artisan config:cache` (with a cached config `env()` is not read at all, so `PHASE5_BACKUP_CONFIRMED` must be present when the cache is built): B1 must run the migration on the restored copy after `config:cache`.
- Test-only caveat: `configValueFor()` re-requires `config/rbac.php` directly, so it proves the file's expression, not that Laravel's dotenv loader would deliver the same string for a `.env` line (quotes/`export` handling).

## QA — post-walkthrough fixes

New file: `tests/Feature/Rbac/ProjectWalkthroughFixesTest.php` (19 tests, 159 assertions, all pass; enforce mode).
Covers P1 (non-JSON redirect + flash + nothing changed, flash rendered after redirect, JSON 422, empty project still deleted, soft-deleted-only estimates do not block, below-F / wrong org / non-member still denied, delete button disabled iff live estimates), P2 (both payload keys, project_name unchanged, no leak to non-visible users or to a member of a different project, one project query regardless of quote count, /quotes renders with PROJECT_SHOW_URL and parentProjectLinkHtml), P3 (active members excluded, inactive and non-members included, other org never listed, hint plus disabled submit when all are members, duplicate add creates no row), P4 (/rfq/create renders 200 for a buyer with no connections and links route('org-admin.connections.index')).

Mutations (scratch copy with its own vendor; real repo untouched): (a) drop blocked/redirect branch: 2 tests fail; (b) drop whereNotIn: 2 fail; (c) drop parent_project_* from list: 4 fail; from details: 1 fail; drop `project:id,name` eager load: 1 fail (N+1 test); (d) revert rfq route name: 1 fail. No unkilled mutants.

Full suite (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`): 702 passed / 4 failed. 3 known baseline failures (AuditMiddlewareTest enforce mode blocks and logs; SeedMatrixTest seeds expected counts; SeedMatrixTest phase distribution) plus tests/Feature/ExampleTest (GET / needs a real DB: no such table divisions). ExampleTest fails identically on a HEAD worktree, so it is not caused by this diff; it was forced onto sqlite to avoid touching the local MySQL.

Findings, no defects in the diff:
- Test-brief assumption wrong: under Phase 5a a NULL-project quote is visible to nobody, so no payload can show null parent keys; the test asserts it is absent from the list and denied on details.
- In enforce mode a non-visible quote's details returns 403 (middleware), not 404.
- Pre-existing, unrelated: quotes/list runs one quote_items query per quote (calculateTotal), so total query count grows with quotes; the project lookup does not.

## QA — audit 1.0 fixes

Branch fix/audit-1.0-findings, uncommitted diff (11 files). Test file: tests/Feature/Rbac/AuditFindingsTest.php (54 tests, 530 assertions, in-memory sqlite via ProjectTestCase, audit and enforce for every new controller check).

Coverage: (a) project member add/remove allowed for PM F (estimator, org admin, owner), denied for viewer/superintendent/project_engineer/procurement_manager and manufacturer_admin (OM F, UM F, no PM: group separation), wrong org, missing project_members row, view controls; (b) settings update F (owner/admin allowed; viewer, estimator (UM F/PM F, no OM), procurement_manager (OM R) denied; no cross-org write; view read-only vs editable incl. Danger Zone; transfer/delete owner-only); (c) my-roles unmapped + own-data-only, every other org-admin route still mapped, overview UM R allowed/denied/wrong org, nav+sidebar Overview link; (d) connections page R, store/destroy F, wrong org, view form/Deactivate, nav link; (e) customers map x2 + enforce behaviour (viewer allowed, superintendent denied); (f) rfq empty state; (g) user-services create pane; (h) 'also held by' flag (2 holders, sole holder, non-owner/admin slug, +N, other org ignored).

Mutations (scratch copy, one at a time, all killed): add/remove requireOrgLevel (killed only by the ordering tests; see note), settings update F, overview check, connections store F, my-roles map entry re-added, customers GET -> user_management R, settings view editable, projects view controls shown.

Note: requireOrgLevel(project_management F) in add/removeProjectMember is behaviourally redundant with requireProjectFull (same group and level, plus membership). It is observable only through check ordering (viewer gets 403 instead of 422/404 on bad payload / missing or trashed project) and only in audit mode (enforce mode 403s in middleware first). Not a defect.

Note: user-services create needs product_management S, and O outranks S, so catalog_data_steward (O) can create; only R roles are read-only. Matches the code.

Views: php artisan view:cache then view:clear with sqlite env: OK (view:clear also removed 15 pre-existing compiled views from storage/framework/views, untracked cache).

Full suite (DB_CONNECTION=sqlite DB_DATABASE=:memory:): 756 passed, 4 failed. All 4 known: AuditMiddlewareTest 'enforce mode blocks and logs' (302 vs 403), SeedMatrixTest 'seeds expected counts' (19 vs 20), SeedMatrixTest 'phase distribution' (14 vs 15), tests/Feature/ExampleTest (no such table: divisions). No new failures. Defects found: none.

Gaps: item #11 not implemented (by design); connections GET-R / destroy-F controller checks and settings GET have tests but no dedicated mutation proof; user-services and rfq rendering asserted on HTML fragments, not in a browser (JS behaviour of renderServiceEditor untested).

## Verifier — audit 1.0 fixes

Scope: 11 modified files + AuditFindingsTest; no migrations, no schema, RoutePermissionMapTest NOT modified (lint unchanged; it only covers quotes/projects routes). Every route() in changed views exists in route:list. Session org in all new checks is the validated currentOrg() (same logic as RbacAudit::currentOrgId). my-roles reads only Auth::user(), no request input: no IDOR.

### Findings (most severe first)

1. SHOULD-FIX (audit mode only; pre-existing, outside diff, but in the requested review surface) - OrgAdminController.php:81,122,142,211,248,392 (assignRole, peelOff, removeRole, updateRolePermission, storeRole, generateInvite) and index/rolesList/auditLog have NO controller-level level check. Probe on scratch copy, audit mode, viewer_read_only (no UM): PUT org-admin/roles-list/permissions 200 (global role matrix edit), POST roles-list/create 200, POST org-admin/roles 200 (viewer assigned themselves Organization Owner), DELETE roles/{id} 302 (removed), GET audit-log and org-admin 200. Enforce mode blocks all via the map. Impact: if production is ever flipped to audit (or a batch is dropped from enforce_batches) any org member can escalate to owner and rewrite the global matrix. Required: Architect to decide whether to add requireOrgLevel(user_management F) to these six writers (and R to index/rolesList, audit_and_logging R to auditLog) in this task or a deliberate follow-up; otherwise document that these rely solely on the middleware.
2. SHOULD-FIX - customers GET remap is half-done. map: GET customers, customers/list -> quote_rfq_management R, but GET customers/{id} stays user_management R and POST/PUT/DELETE stay UM S/O/F; sidebar.blade.php:62 still gates the Customers link on user_management R; customers/index.blade.php shows the "+" FAB unconditionally (line 77). Roles with QR>=R but no UM (procurement_coordinator, sales_rep, requisitioner, contract_manager, delegate_proxy, order_fulfillment_csr, integration_service_account, executive_approver, engineer, architect) can reach the list by URL but get 403 on opening a row (detail) and on create; no link is shown to them. No role loses access (every UM>=S role also has QR>=R), and create/edit is not stranded. Required: decide intent; either map customers/{id} to QR R too and gate the FAB/sidebar consistently, or revert.
3. NOTE (retracted as a defect) - Danger Zone is shown to OM F holders, but settings.blade.php:104-127 already gates the transfer/delete buttons on $isOwner, so admins see only the zone shell. Cosmetic.
4. NOTE - user-services/index.blade.php renderServiceEditor: inputs are disabled by CAN_CREATE (S) though PUT needs O. A holder at exactly S (none in the seeded P1/P2 matrix) would see an enabled editor and Save on an existing service that 403s. Harmless with current matrix.
5. NOTE - matrix: auditor_read_all holds OM F, so it can save settings and create/deactivate connections (unchanged by this diff, was already F for connections; settings O->F is behaviourally neutral because no role sits at OM O/A). SOD question for the Architect, not introduced here.
6. NOTE - Q6: product_management O creating services is correct (O outranks S; only R roles read-only) and does not contradict "below S" if read as rank. requireOrgLevel redundancy in add/removeProjectMember confirmed: it is observable only as check ordering in audit mode (mutation survives everything except the ordering tests). Harmless, but it is duplicate code.
7. NOTE - No regression found for owner, org admin, project_manager, estimator (PM F: can add/remove members; no OM so no settings/connections, as before in enforce), procurement roles (OM R: now see Connections read-only, correct). Overview/team already required UM R in the map; roles without UM (viewer, requisitioner, coordinator, etc.) lose only the previously-dead link. project_engineer (PM O) cannot manage members, as intended.

### Mutation runs (scratch copy, real repo untouched, 30 mutants)
Killed 29: settings update F->R / wrong group / check removed; add and remove project-member check removed; destroyConnection check removed; storeConnection F->R; connections page R->F / check removed; overview check removed / wrong group; canManageProjects, canManageConnections, canManageOrg F->R; settings inputs not disabled; nav and sidebar Overview gate removed; nav Connections gate reverted; my-roles map entry re-added; customers and customers/list group swapped; connections GET map R->F; rfq link gate weakened; user-services CAN_CREATE forced true; Cancel ungated; also-held slug filter removed / includes self.
Survivor: map 'POST org-admin/settings' F->O (equivalent under the seeded matrix, no OM O/A holder; the controller check still enforces F; add a test with an OM=O grant if you want the map level pinned).

### Verdict
DO NOT CONFIRM "no missing edge cases / no regression risk": findings 1-2 above go to the Orchestrator for an Architect PLAN.md update (strict mode). The diff itself introduces no security hole and no role loses needed access.

After deploy on the real server check: (a) as viewer/requisitioner in enforce mode, POST org-admin/projects/members, POST org-admin/settings, POST/DELETE org-admin/connections return the denial redirect/403 and rbac_settings.rbac_mode is really 'enforce' with enforce_batches empty or including read/write/admin/approve; (b) my-roles loads for a Viewer and redirects nowhere; (c) Customers list opens for a sales_rep and what happens on row click; (d) an org admin opening Settings sees Danger Zone and what transfer/delete does; (e) user-services editor for viewer (read-only, no console errors); (f) audit_logs shows no unexpected would_block/blocked for legitimate owner/admin flows.

## QA — audit 1.0 fixes (round 2)

New file tests/Feature/Rbac/AuditFindingsRound2Test.php (50 tests). Table-driven per method (17 cases x audit/enforce): every denied role gets 403 and a full snapshot of users/organizations/roles/role_permissions/user_org_roles/role_assignment_logs/org_invites/delegations/api_tokens/org_relationships/project_members/projects is unchanged; the first allowed role succeeds. Plus: viewer self-assign Owner, group separation, cross-org role row, settings destroy owner check (fresh org), customers map/sidebar/page, and generic guard (every mapped route of OrgAdmin/Delegation/ApiToken/OrgSettings controllers, as viewer_read_only, both modes: exactly 403, DB unchanged; 25 routes exercised, only GET org-admin/projects skipped because a viewer holds project_management R; route params without a resolver fail the test; a second guard fails if any route of the four controllers is unmapped other than my-roles).
Round-1 tests updated for intentional new behaviour (not loosened): viewer GET org-admin/settings is now 403 in audit (was read-only render; proc R still renders read-only); GET customers/{id} map assertion is now quote_rfq_management R.

M11 note: the coordinator's premise does not match the live matrix. organization_owner DOES hold audit_and_logging R (role_permission_matrix.php), so the Owner is ALLOWED on org-admin/audit-log in both modes, matching the map. Tested as allowed; estimator (UM F, no AL) is denied. If the client believes Owner should not see the audit log, that is a matrix decision, not a code defect.

Round 2 mutations (scratch copy, one at a time, all killed by AuditFindingsRound2Test, names in run): assignRole, updateRolePermission, generateInvite, auditLog check removed; DelegationController::store, ApiTokenController::store check removed; OrgSettingsController::destroy owner check removed (also AuditFindingsTest transfer/delete test); auditLog group swapped to user_management; GET customers/{id} reverted to user_management. Unkilled: none.
Full suite (sqlite :memory:): 806 passed, 4 failed = the 3 known baseline + ExampleTest (no such table: divisions). view:cache + view:clear OK. Defects: none.

## Verifier — audit 1.0 fixes (round 2)

Scope: 13 modified files (adds ApiTokenController, DelegationController), 2 untracked tests; no migrations/schema. Scratch copy, sqlite in-memory.

1. Probe as viewer_read_only, AUDIT mode (23 requests: roles matrix PUT, roles create, self-assign Owner, peel-off, invite, removeRole, audit-log/index/roles-list/overview/settings/connections/delegations/api-tokens reads, delegation + token store, settings save/transfer/delete, connections store/destroy, project member add/remove): ALL 403, DB hash unchanged after each. AuditFindings + Round2 = 104 pass.
2. Mutations on the generic guard (check removed from methods): peelOff, storeRole, auditLog, rolesList, generateInvite, DelegationController@destroy, ApiTokenController@destroy, OrgSettingsController@index all killed. Survivor: transferOwnership user_management F check removed - equivalent, isOwner (next line) subsumes it (owner holds UM F). NOTE only.
3. Customers: GET customers, list, {id} -> quote_rfq_management R; sidebar matches; create/edit/delete UI already gated on user_management S/O/F ($_cp, index.blade.php:14-16,76). No create flow stranded; QR-only roles get a read-only list.
4. Owner allowed on audit-log (AL R) accepted, not a defect.

### Residual unchecked routes (outside change set; AUDIT mode; no server-side level check in controller)
Frontend, mapped: WRITE - POST customers (UM S), POST user-products (PM S), POST user-services (PRD S), POST rfq (QR S), POST rfq/{rfq}/decline (QR S), POST remove-lists-items/{id} (PM O), POST/PUT/DELETE quotes/* (duplicate, update, updateItem, destroy, destroyItem; EM O/F), plan-crosswalk PUT/DELETE and POST projects/{project}/crosswalk (EM F), POST migrate-session-pallet (procurement S). READ - GET customers, quotes, quotes/product-variations, rfq, rfq/create, rfq-incoming, view-lists, list-view, list-detail-data, user-products, user-services, view-orders, order-detail, checkout/success, order-approvals (approval_authority A), get-pallet-checkout-data. Probe as viewer in audit: customers/user-products/user-services/remove-lists-items reached the handler (500 from stub tables, not 403); rfq/save-list reached validation (422); quotes/rfq-incoming/customers GET 200; order-approvals reached handler. Most are user- or org-scoped by query (forUser, org_id, Project::visibleTo), so exposure is same-org lower-role users, not cross-org; not individually proven. Admin/* routes sit behind checkRole:admin (platform admin), not org RBAC.

### Verdict
CONFIRM the changed code and tests (within sqlite limits): all round-1 gaps closed, no role in the matrix loses needed access, scope clean. The residual list above is a separate task.

## QA — audit 1.0 fixes (round 3)

Added tests/Feature/Rbac/AuditMembershipLogTest.php (28 tests: added/reactivated/no-op/removed via ProjectController::store, ProjectMemberController, OrgAdminController; race-lost active/inactive; trigger-forced non-missing-table failure rolls back enrol add/reactivate and deactivate; dropped table fails soft with warning and flows work; audit page org isolation, dual-org, System for null, denied roles in both modes, ppage pagination; test-session gone), plus test_backfill_writes_no_project_member_logs in ProjectBackfillTest, and the new migration in RbacTestCase's list.
Mutations (scratch copy, all killed): no 'added' log, no 'reactivated', log when already active, no 'removed' log, removed logged when already inactive, audit page org filter removed (first attempt hit the role-change query; re-done on the ProjectMemberLog query: killed), record() swallow-all, record() rethrow-missing. Unkilled: none.
Full suite: 835 passed, 4 failed = 3 known baseline + ExampleTest (divisions). view:cache/clear OK. Defects: none.

## Verifier — audit 1.0 fixes (round 3)

Scratch copy, sqlite. AuditMembershipLogTest + backfill/membership/write suites: 105 pass. 11 mutations, all killed (removed-when-inactive guard, wrong actions, reactivated/added unlogged, race-lost double log, swallow-all, missing-table detection off, audit org filter dropped, raw update on remove path, performer null, transaction removed).

1. enrol() transaction (reasoned for MariaDB 10.4 REPEATABLE READ). Guards (unsaved/trashed/org mismatch) remain first and unchanged. Standalone: first non-locking select opens the snapshot, but the sharedLock re-select is a current read and sees the committed row; a duplicate-key error is statement-level, so the transaction stays usable. Inside ProjectController::store / caller transactions Laravel uses a savepoint; same behaviour. wasRecentlyCreated / wasChanged('is_active') still work (model returned from closure). Race-lost path: winner logged 'added', loser logs nothing (correct); if the row was inactive the loser's update logs 'reactivated' once. NOTE (low): the shared lock is now held to commit (previously released at once in autocommit), so two concurrent enrols racing against a rolling-back insert can hit the classic S-lock deadlock (1213); it surfaces as an exception, DB::transaction is not retried. Rare; no change required.
2. Fail-soft: MariaDB 1146 is statement-level, transaction stays usable; sqlite likewise. Detection covers errno 1146, SQLSTATE 42S02 and 'no such table'. Only the log INSERT is wrapped, so other errors rethrow. Warning is emitted once per membership event only, not per request. NOTE: if the table is absent, events are silently lost until migrated (accepted by design).
3. Migration: plain create, default index names, nullable created_at, no FKs, down() drops; sorts before 2026_09_25_*, independent of them. Fine on 10.4.
4. Audit page: query filtered by org_id, controller check audit_and_logging R (round 2), eager loads (no N+1), project withTrashed ('Deleted project' only if hard-deleted), {{ }} escaped, ppage distinct from page/epage, 'System' for null performer. Fine. Sits in the roles tab only; the Enforcement tab does not show it (note).
5. Bypasses (no log): Admin/RbacController.php:508 hard-deletes a user's project_members and leaves their log rows (no removal event; log rows keep target_user_id of a deleted user, shown 'Unknown'); BackfillProjects.php:672 raw insert (intentional, test asserts no logs). No project_members writes in org deletion (refused while projects exist), seeders or services. NOTE, platform-admin path only, acceptable, but decide whether that is intended.
6. test-session route removed; only reference is the new test.

Verdict: CONFIRM (within sqlite limits). Post-deploy: run migration 000006 before use, then add/remove a member and check the audit-log table; watch laravel.log for the missing-table warning.

## QA — RFQ scope/approval

Branch fix/rfq-project-scope-and-approval, uncommitted. In-memory sqlite only.

Tests: new `tests/Feature/Rbac/RfqProjectScopeTest.php`, 91 tests / 772 assertions, all pass. Covers G1 (solo approver -> pending; several approvers -> pending_approval and order in the approver queue; org/status filtering of the queue; requester excluded from the pool; non-approver requester; no-approver org behaves like Checkout), G3 (F+procurement S allowed: project_manager, procurement_manager, procurement_coordinator F/O; denied: sales_rep F/no procurement, custom R grant, requisitioner, estimator, viewer; allowed once custom S granted; legacy NULL-project RFQ), G2 (store: missing/malformed/unknown/foreign-org/non-member/trashed/inactive-member project all 422, member without S 403, no org 403, allowed stores project_id; create form preselect and no-projects message; index filter; non-member 404 on show/select/convert; inactive membership 404; legacy NULL RFQ open to org users; wrong org/no org 403; project name+link on buyer index/show; sellers: incoming HTML+JSON, respond, decline never expose project id/name/relation; ProjectController::destroy RFQ refusal redirect+flash and JSON 422, estimates precedence, deletable again), audit AND enforce 403s for index, show, select, convert, incoming, respond, decline, create, store with DB unchanged, schema (nullable, nullOnDelete, up/down re-runnable), every changed view rendered.

Fixture changes (tests/ only): RbacTestCase migration list now runs the real `2026_09_01_000003_create_rfq_tables.php` plus the new project_id migration; ProjectTestCase's stub rfq_requests/rfq_recipients tables removed (they lacked created_by/notes/project_id). No assertions loosened; existing RFQ tests (ProjectWalkthroughFixesTest P4, AuditFindingsTest rfq empty state) pass unchanged.

Mutation proofs (scratch copy, one at a time, all KILLED): no ApprovalRoutingService call (4 tests); no procurement S check (2); no quote_rfq F check in convert (3); no project visibility 404 in show (3) / select (1) / convert (3); index project filter dropped (1); project requirement on store dropped (3); seller incoming leaks project relation (1); ProjectController::destroy RFQ guard removed (4); respond level check removed (2). Scratch copy deleted; real repo untouched (diff confirmed).

Full suite (sqlite :memory:): 926 passed / 4 failed. Failures are exactly the 3 TESTING.md baseline (AuditMiddlewareTest 'enforce mode blocks and records blocked', SeedMatrixTest 'seeds expected counts', 'phase distribution') plus ExampleTest scaffolding. No new failures. `view:cache` and `view:clear` OK.

Defects: none found. Observations (not defects, matching existing Checkout behaviour): with zero approvers in the org, convert yields pending_approval with nobody able to approve; in enforce mode the middleware runs before the controller, so a below-level user gets 403 rather than the 404 for an invisible project (in audit mode the 404 comes first).

## Verifier — RFQ scope/approval

Independent review of uncommitted changes on fix/rfq-project-scope-and-approval. Sqlite only; nothing run against MySQL. Scratch-copy mutation run (real vendor copy; first attempt with a symlinked vendor was invalid because the autoloader resolved back to the real repo, so it was discarded; the real repo was never modified).

### Should-fix (route to Architect per strict mode)

1. **Double convert / re-convert creates duplicate orders (approval-relevant).** app/Http/Controllers/Frontend/RfqController.php convertToOrder (~L167-210): no status guard and no row lock. Two concurrent or repeated POSTs to rfq/{rfq}/convert both find the 'selected' response and each create an Order (each with its own approval routing). Also selectResponse (~L145) can be re-run after 'converted' and flips the RFQ back to 'closed' and rejects the response that was converted. Pre-existing, but G1 makes duplicated pending_approval / auto-approved 'pending' POs materially worse. Change: in the transaction, lockForUpdate the RFQ, abort 422 unless status is 'closed' with a selected response (reject 'converted'); in selectResponse abort 422 when status is 'converted'. Add a test for convert twice, expecting one order.
2. **Dashboard leaks RFQ titles of invisible projects.** app/Http/Controllers/Frontend/UserWorkspaceController.php L50-58 (`$pendingRfqs`) queries RfqRequest by org_id and status only, not applying the project-visibility filter used in RfqController@index. A non-member with quote_rfq S sees the title (and a link that now 404s) of RFQs on projects they cannot see. Not the project name/id, but titles frequently carry the project. Change: apply the same whereNull OR whereIn(visibleTo) filter (extract nothing new; duplicate the where clause or add a scope on RfqRequest). Test it.
3. **Deploy-order: code before migration breaks the buyer RFQ pages.** index (where project_id + with('project')) and store (insert project_id) will 500 if 2026_09_26_000001 has not run; show/select/convert only read an attribute and degrade to legacy behaviour (no project check, so fail-open, but only until the migration runs). Seller pages are unaffected (explicit select excludes project_id, which also would 500 on an old schema? No: the select list omits it, so fine). Change: a deploy note in PLAN.md (run migration first, in the same release, before the code goes live) or, at minimum, state it in the release order in CLAUDE.md. Fail-soft code is not recommended (it would hide a missing column and skip the project check).
4. **Test gaps shown by mutation survivors** (of 17 mutants on a scratch copy, 15 killed, 2 survived, 1 equivalent):
   - M10 survived: authorizeRfq($rfq,'F') in convertToOrder changed to 'O' passes all tests. The controller F check is untested independently of the procurement check (no test with quote_rfq O/A plus procurement S under both audit and enforce). Add a custom-grant test (mirror of the existing procurement R/S grant test) for O+procurement S denied.
   - M11 survived: the project-scoped permission check in store() (checkPermission with projectId) is redundant with the validation rule (visibleTo membership); low risk, but add a test where the membership is active and S is held only outside the project, or drop the redundant call.
   - M15 (remove `$orgId === 0`) is an equivalent mutant since rfq.org_id is never 0; no action.

### Notes

- Security / isolation: cross-org, non-member (404), below-level (403), no-org, seller (explicit column select, no project key in HTML or JSON, respond/decline unchanged) all checked in code and covered by killed mutants (M1, M2, M13, M16, M17, M18). authorizeRfq uses CurrentOrg::id and requires rfq.org_id equals the current org; response-of-another-RFQ is 403. Someone who only created a response is a seller-org user: they fail the org check on select/convert.
- order.details (OrderController@orderDetail L41+) restricts to own orders or org-admin org orders and contains no project data (project_title is the RFQ title). No project leak.
- Approval: routing uses $rfq->org_id, which equals the acting org after authorizeRfq. Killed by M4/M12. OrderApprovalController index/approve filter by org_id + status pending_approval, so RFQ orders appear. Sole-approver auto-approve yields 'pending' exactly like checkout. Zero-approver org: convert gives pending_approval with no one able to approve; same as CheckoutController, so consistent, but it is a trap. Recommend a decision at Checkpoint 2 (not a code change here).
- Pre-existing, not touched: respond() has no RFQ status check; sellers can respond after close/convert and can respond repeatedly (extra pending_review rows do not alter the selected one). sellerAuthorizationError dead-branch bug untouched (correct).
- Migration: hasColumn guard, nullable foreignId to projects.id (bigint unsigned, matches `$table->id()`), nullOnDelete only fires on hard delete; soft-deleted projects keep project_id and are excluded by visibleTo (SoftDeletes scope), so their RFQs vanish from index/404 on show, while destroy's rfqRequests() count also skips... (the relation to a soft-deleted project is moot, the project is already gone). Small-table ALTER with FK on MariaDB 10.4 is quick. down() drops FK+column correctly. Filename ordering (000001 on 09_26) is after the 09_24/09_25 files and touches no shared table.
- Regressions: convert now requires quote_rfq F and procurement S (project-scoped when project set): still available to procurement_manager, organization_owner, project_manager, procurement_coordinator (F, O), super admin. Lost: none that previously held F; requisitioner (S/S) and estimator/engineer types can create but not convert (as the client document says). Creation now needs project membership with S: users with quote_rfq S but no visible project hit the "No projects available" dead end. Acceptable per the plan but should be communicated; roles at project-membership-less org level (organization_owner without membership) cannot create RFQs until enrolled. Legacy NULL-project RFQs remain org-level accessible (by design).
- destroy(): message precedence correct (estimates first), lock pattern preserved, `&$message` binding needed and correct.
- Scope: only the listed files changed; no RbacAudit, PermissionService, ApprovalRoutingService, route map, matrix changes. Views need no comment cleanup. Constructor injecting PermissionService and app() for ApprovalRoutingService is a mild style inconsistency (note).

### Verdict: DO NOT CONFIRM (strict mode: any finding goes back to the Architect)

Code is sound on isolation and RBAC for the specified flows; findings 1-3 need a PLAN.md update and a small patch before merge, and finding 4 needs two tests. Post-deploy checks on the real server: run the migration before serving the new code; confirm rfq_requests.project_id + FK exist; confirm the live rbac_mode; convert an RFQ as a non-approver and confirm it appears in the approval queue; as sole approver confirm status 'pending'; verify a user in an org without approvers is understood; a seller user's incoming page shows no project; try convert twice.

## QA — RFQ scope/approval (round 2)

Added 13 tests (104 in RfqProjectScopeTest.php, 889 assertions, all pass): double convert (two sequential HTTP posts, JSON message, other user; exactly one order), stale-model second convert via the controller, convert refused unless 'closed' with a selected response (draft/sent/cancelled/no selection: 422, no order), select after 'converted' 422 with selection unchanged, quote_rfq O and A + procurement S convert 403 (audit and enforce) and allowed again at F (kills Verifier M10), dashboard pendingRfqs visibility, store() project-scoped S check under delegation (kills M11).

Limits, stated plainly:
- sqlite ignores FOR UPDATE, so real row locking / true concurrency is NOT proven. What is proven: the RFQ is re-read inside the transaction and its status checked, so a stale in-memory model cannot create a second order.
- Dashboard: the query filters status 'open', which the enum never allows (pre-existing, not fixed). The test swaps rfq_requests for a copy with a free-text status so the visibility filter is exercised; the real-DB dashboard list is always empty today.
- M11 is NOT redundant only under delegation: visibleTo uses the delegate's own membership, checkPermission uses the principal's. Without delegation it duplicates the membership validation. Test uses a delegate who is a member for a principal who is not.

Mutations on a scratch copy (with a real vendor copy; a symlinked vendor loads the real repo's app and gives false survivors), all KILLED: convert lock+status guard removed (5 tests), lock removed with status guard kept on stale model (1), converted guard removed in select (2), pendingRfqs project filter removed (2), convert F->O (1), store project S check removed (1). Scratch deleted.

Full suite (sqlite :memory:): 939 passed / 4 failed = the 3 TESTING.md baseline + ExampleTest. No new failures, no defects found.

## Verifier — RFQ scope/approval (round 2)

Scratch copy, sqlite in-memory. Real repo untouched.

### Concurrency (MariaDB InnoDB, REPEATABLE READ) — code reasoning
- convertToOrder: `lockForUpdate` is the first statement in the transaction, so it is a locking (current) read: the second request blocks on the row lock until the first commits, then reads the committed 'converted' and 422s. The later plain reads (responses) take their snapshot after the lock, so they are fresh. The status check, ApprovalRoutingService (reads user_org_roles/role_permissions only), Order::create and the status='converted' update all run inside the locked section. Order number is uniqid, no sequence table. No deadlock: both convert and select lock rfq_requests first; only select then writes rfq_responses, convert only reads them.
- Caveat: locking is not provable on sqlite. Mutations N1 (drop convert lock) and N9 (drop select lock) SURVIVE by nature; sqlite has no row locks. Sequential double-convert/re-select tests do kill the guard removals (N2, N4, N8). Verify on MariaDB with two concurrent POSTs.

### State machine open -> closed -> converted
- select: allowed from open or closed (re-pick before conversion is intentional), refused once converted. convert: only from closed with a selected response. No way back from converted. Seller respond/decline after conversion only adds pending_review responses / changes recipient status and never touches the selected one. Note (pre-existing, not a blocker): respond has no RFQ status check.
- Minor: selectResponse updates via the unlocked `$rfq` model rather than `$locked`; harmless (same row) but could write stale attributes. Note only.

### F2 completeness
Only three files reference RfqRequest buyer-side: RfqController (index filtered), UserWorkspaceController (now filtered), and models. No sidebar badges, JSON, PDF or export listing found. Complete.

### Mutations (9): 7 killed, 2 survived (N1, N9 = sqlite lock limits, expected). Killed: stale status check, wrong status, select converted-guard removed, routing org swapped, dashboard inverted, dashboard unfiltered, converted not set.

### Verdict: CONFIRM (code and tests, within sqlite limits)
Server checks: fire two simultaneous POST rfq/{id}/convert and confirm one order and one 422; run migration before code; confirm rbac_mode; converted order appears in the approval queue; sole approver gets 'pending'; seller incoming shows no project; dashboard hides invisible-project RFQs.

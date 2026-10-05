# Testing

## Running the suite

```bash
php artisan test --filter=Rbac    # RBAC suite (tests/Feature/Rbac/) — this is the real coverage
php artisan test                  # full suite
```

`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` are unmodified
Laravel scaffolding — not meaningful coverage, don't count them toward
anything.

## Known-failing baseline (updated 2026-09-26, after P2 cleanup)

Full suite on in-memory sqlite (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`): **1150 passed / 4 failed** (1012 before the universal-admin work). The 4 failures are the 3 listed below plus `ExampleTest` (Laravel scaffold, "no such table: divisions"). Tests are PHPUnit-style classes (no Pest installed). The P2 additions are `AuditFindingsRound2Test` (guard over every mapped route, audit and enforce mode) and `P2CleanupBCTest`. Historical note follows: the RBAC suite was **682 passed / 3 failed** (Phase 5a rewrite plus the Phase 5 QA tests and amendment 3 round 2, 651 before round 2; Phase 4 baseline was 568 / 3). (The earlier
baseline of 55 passed / 3 failed, 113 assertions was measured **before** the
projects work.) These 3 failures are pre-existing and independent of any feature work — do not report them as
a regression you introduced, and do not treat a *new* failure as "probably
one of the known ones" without checking it's actually one of these three by
name:

1. **`AuditMiddlewareTest::enforce mode blocks and logs`**
   (`tests/Feature/Rbac/AuditMiddlewareTest.php:71`) — expects a 403 for a
   user with no org, actually gets a 302 redirect. Likely cause: the
   no-org path redirects (e.g. to an org-selection screen) rather than
   returning 403 under full enforcement. Needs a decision on intended
   behavior before fixing. Non-JSON denials redirect back (302) by design in
   `RbacAudit::fail()`; new tests assert denials with JSON requests; this is
   Known open item #4 and is unchanged.

2. **`SeedMatrixTest::seeds expected counts`**
   (`tests/Feature/Rbac/SeedMatrixTest.php:20`) — asserts 20 organization
   types; actual seeded count is 19. The original client plan's section
   header also says "20 types" but its own table only lists 19 — so the
   plan itself is ambiguous here. Needs a decision: is a 20th org type
   missing, or should the test assert 19?

3. **`SeedMatrixTest::phase distribution`**
   (`tests/Feature/Rbac/SeedMatrixTest.php:30`) — asserts 15 P2 roles / 17
   P3 roles (Release 1 = 32); actual seed data has 14 P2 / 18 P3. Root
   cause, per the test's own inline comment: `api_system` is seeded as
   phase **P3**, but the test's author intended it as phase **P2** (a
   second structural pull-forward alongside `integration_service_account`,
   which correctly lives in P1). This needs an explicit decision — move
   `api_system` to P2 in `database/seeders/Rbac/data/roles.php`, or update
   the test to match P3 — not a silent fix either way, since it changes
   which enforcement batch the role effectively lands in.

If a feature touches roles, org types, or the audit middleware, flag these
three explicitly in that task's `REVIEW.md` so a fix doesn't get bundled
into an unrelated PR by accident.

## Projects tests

- `ProjectSchemaTest` — schema, migration guards, `ProjectMember::enrol()`.
- `ProjectBackfillTest` — the `projects:backfill` command.
- `ProjectMembershipTest` — membership and `RbacAudit` quote/project resolution.
- `ProjectReadPathsTest` — controller read paths (project-only visibility since Phase 5a: no owner clause, NULL-project quotes visible to nobody), H7/H9 rules.
- `ProjectWritePathsTest` — Phase 4 write paths (projects, members, quote creation, crosswalk, org-admin member routes), audit and enforce, IDOR per nested route.
- `ProjectCheckpoint2Test` — Phase 4 Checkpoint 2 changes: owner-level check in `writableQuote`, `destroyUser` created_by refusal and member cleanup, inline Add estimate customers, crosswalk `$projects`.
- `ProjectDeletionRefusalTest` — B3 refusals on org and user deletion (soft-deleted projects counted).
- `ProjectDashboardCountersTest` — Phase 5 A5: dashboard quote counter, status chart and recent list equal the quotes index (R vs author-only, removed member, trashed project, NULL project, other org, no org); orders and lists stay author-scoped.
- `Phase5MigrationsTest` — the three 5b migrations on an in-memory sqlite copy of the pre-5b schema (guards, schema result, re-runs, `down()`); no real database. It needs the PRE-5b schema, so at the 5b step it must keep its own schema builder.
- `QuoteAuthorScopeGuardTest` — grep-style guard: no `Quote` read by `user_id` under `app/` and `resources/` outside `Quote::scopeVisibleTo`, `destroyUser` and the inert backfill command.
- `RoutePermissionMapTest` — route-permission map lint.
- `ProjectTestCase` — shared base for the projects tests.
- `PermissionServiceTest::test_project_scoping_requires_membership` — rewritten
  onto `projects` / `project_members.project_id`.

## RBAC test patterns

- `tests/Feature/Rbac/RbacTestCase.php` is the shared base — use it (or its
  helpers) rather than hand-rolling org/user/role setup per test.
- For any new permission-sensitive route or service method, cover: the
  allowed path, wrong-role denial, wrong-org denial, and — if project
  (quote) scoped — missing `project_members` row denial.
- SOD-sensitive role assignments: test both directions (the 8 rules in
  `sod_conflict_rules` are bidirectional).
- Id-collision fixture: a quote id equal to another project's id, to catch a
  quote id being read as a project id.
- Group-separation fixture: a role holding `procurement:R` only versus one
  holding `estimate_management:R` only, to prove the controller checks the
  right permission group.
- Enforce-mode tests use JSON requests (403); non-JSON denials are 302 by design.
- Mutation-proof practice: apply each load-bearing mutation on a scratch copy
  of the repo (never the tracked files) and show the named test fails; route
  every surviving mutant back to the Architect.
- Tests run on in-memory sqlite and never touch the real database. What sqlite
  cannot prove (locks, savepoints, collation, timezones, real volume, file
  modes) is listed in the B1 checklists (Phases 1, 2 and 3) in
  `.claude/tasks/projects-entity/PLAN.md`.

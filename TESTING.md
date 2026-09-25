# Testing

## Running the suite

```bash
php artisan test --filter=Rbac    # RBAC suite (tests/Feature/Rbac/) — this is the real coverage
php artisan test                  # full suite
```

`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` are unmodified
Laravel scaffolding — not meaningful coverage, don't count them toward
anything.

## Known-failing baseline (as of 2026-09-25, Phase 3 Checkpoint 2 run)

The RBAC suite is **383 passed / 3 failed, 3094 assertions**. (The earlier
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
- `ProjectReadPathsTest` — controller read paths, dual-read, H7/H9 rules.
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

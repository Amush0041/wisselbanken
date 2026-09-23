# Testing

## Running the suite

```bash
php artisan test --filter=Rbac    # RBAC suite (tests/Feature/Rbac/) — this is the real coverage
php artisan test                  # full suite
```

`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` are unmodified
Laravel scaffolding — not meaningful coverage, don't count them toward
anything.

## Known-failing baseline (as of 2026-09-23)

The RBAC suite is **55 passed / 3 failed, 113 assertions**. These 3 failures
are pre-existing and independent of any feature work — do not report them as
a regression you introduced, and do not treat a *new* failure as "probably
one of the known ones" without checking it's actually one of these three by
name:

1. **`AuditMiddlewareTest::enforce mode blocks and logs`**
   (`tests/Feature/Rbac/AuditMiddlewareTest.php:71`) — expects a 403 for a
   user with no org, actually gets a 302 redirect. Likely cause: the
   no-org path redirects (e.g. to an org-selection screen) rather than
   returning 403 under full enforcement. Needs a decision on intended
   behavior before fixing.

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

## RBAC test patterns

- `tests/Feature/Rbac/RbacTestCase.php` is the shared base — use it (or its
  helpers) rather than hand-rolling org/user/role setup per test.
- For any new permission-sensitive route or service method, cover: the
  allowed path, wrong-role denial, wrong-org denial, and — if project
  (quote) scoped — missing `project_members` row denial.
- SOD-sensitive role assignments: test both directions (the 8 rules in
  `sod_conflict_rules` are bidirectional).

# Wisselbanken

Laravel 11 / PHP 8.2 / MariaDB / Blade + Livewire + Tailwind. A B2B construction
materials marketplace with a custom multi-tenant RBAC system built from scratch
per a client-specified implementation plan.

## RBAC — read before touching anything permission-related

- Single entry point: `PermissionService::checkPermission(userId, orgId, permissionGroup, requiredLevel, projectId?)`.
  **Always** pass `org_id`. **Never** inline-check `$user->role` or similar.
- Roles are never a column on `users`. All assignments live in `user_org_roles`
  (user_id, org_id, role_id, is_active), scoped per organization.
- Access levels rank **F > A > O > S > R** (F strongest, R weakest —
  `PermissionMatrix::rank()`). A grant satisfies a requirement if its rank
  is <= the required rank.
- `projectId` in `checkPermission()` is actually a `quotes.id` — in this
  platform, "project" = Quote/Estimate. Project-scoping is enforced via the
  `project_members` table (quote_id, user_id, org_id, is_active).
- `plan_crosswalk` table exists (org_id + quote_id scoped; maps a buyer's
  plan line code -> WisselBanken SKU -> manufacturer part number).
  Permission-checked via the `estimate_management` group.
- Every new/changed route **must** get an entry in `config/route_permission_map.php`
  (permission group, required level, batch). Current state: `RBAC_MODE=enforce`
  with all batches on — a missing or wrong mapping fails closed (403), it
  does not silently allow access.
- Known bug: `OrgRelationshipService::sellerAuthorizationError()` checks org
  type `=== 'rep_agency'`, but the real slug is `manufacturer_s_rep_sales_agency`.
  That branch is dead code — don't assume rep-agency seller authorization is
  actually enforced until this is fixed.
- Full corrected reference: [ARCHITECTURE.md](ARCHITECTURE.md).
  `HANDOFF.md` is a historical onboarding snapshot with **known inaccuracies**
  (wrong column name in `role_assignment_logs`, a fabricated "88/88 tests
  pass" claim, undocumented `project_members`/`plan_crosswalk` tables, and
  more) — don't cite facts from it without checking the live code first.

## Conventions

- No comments unless explaining a non-obvious WHY (a hidden constraint, a
  workaround, something that would surprise a reader). Never explain WHAT
  the code does.
- No speculative abstraction, no feature flags for hypothetical futures.
  Match existing service/controller patterns rather than inventing new ones.
- Every permission-sensitive route needs both a `route_permission_map.php`
  entry AND a Pest test covering the allowed path and the denied path
  (wrong role, wrong org, and — if project-scoped — missing project
  membership).

## Git workflow

- Branches: `main` (production-tracking) and `dev` (integration). Feature
  branches cut from `dev`, PRs merge back into `dev`.
- Do not push to `main` or merge without explicit approval.

## Running tests

```bash
php artisan test --filter=Rbac   # the RBAC suite — see TESTING.md for the current known-failing baseline
php artisan test                 # full suite (tests/Feature/ExampleTest.php etc. are Laravel scaffolding, not real coverage)
```

See [TESTING.md](TESTING.md) before reporting any test result as a regression.

## Multi-agent development workflow — STRICT MODE

This repo uses a mandatory, no-skip subagent lifecycle for every task, feature,
and bug report: Architect (plan) → human Checkpoint 1 → Developer (+ UI/UX)
→ Architect (compliance pass) → QA → Verifier → human Checkpoint 2 → PR.
**Any issue found at any stage routes back to the Architect to update
PLAN.md — never a direct dev-fix.** Every handoff is stated explicitly and
logged to that task's `PROGRESS.md`/`REVIEW.md`. See `.claude/tasks/README.md`
for the full protocol and `.claude/agents/*.md` for each role. Start a new
task with `/new-feature <name>`.

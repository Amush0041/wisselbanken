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
- `projectId` in `checkPermission()` is a `projects.id`. A project is its own
  entity (`projects` table: org_id, name, status, address, bid_due_at); a quote
  belongs to a project via `quotes.project_id` (nullable until the Phase 5b migration runs).
  Project-scoping is enforced via `project_members` (`project_id`, user_id,
  org_id, is_active); membership org must equal `projects.org_id`. Legacy
  quote-only rows (`quote_id`, `project_id` NULL) never grant access; the
  `projects:backfill` command moves them up. Use `ProjectMember::enrol()` to
  add members, never a raw insert.
- Quote-keyed routes use `'quote_param'` in the route map (resolved to the
  quote's project by `RbacAudit`); `'project_param'` means a `projects.id`. An
  unresolved scope fails closed as `project_unresolved`.
- `plan_crosswalk` (org_id + quote_id scoped; maps a buyer's plan line code ->
  WisselBanken SKU -> manufacturer part number) has a nullable `project_id`
  column; it is keyed on `project_id` for writes since Phase 4. Permission-checked via the
  `estimate_management` group.
- Every new/changed route **must** get an entry in `config/route_permission_map.php`
  (permission group, required level, batch). The operative RBAC mode is the DB
  row `rbac_settings.rbac_mode`, NOT `.env` (local `.env` says `enforce`;
  production is reported to be in audit mode). In enforce mode a missing or
  wrong mapping fails closed; in audit mode it is only logged, so controller
  data scoping is the real guard there.
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

## Multi-agent development workflow

Two modes. **Strict** is the default for anything that changes the permission
engine, migrations on live data, or irreversible steps. **Lean** is used only
where stated below.

### STRICT MODE (default)

Mandatory, no-skip subagent lifecycle for every task, feature, and bug report:
Architect (plan) → human Checkpoint 1 → Developer (+ UI/UX) → Architect
(compliance pass) → QA → Verifier → human Checkpoint 2 → PR. **Any issue found
at any stage routes back to the Architect to update PLAN.md — never a direct
dev-fix.** Every handoff is stated explicitly and logged to that task's
`PROGRESS.md`/`REVIEW.md`. See `.claude/tasks/README.md` and
`.claude/agents/*.md`. Start a new task with `/new-feature <name>`.

### LEAN MODE — Projects feature, Phase 4 only

Reason: strict mode cost roughly 8-9M tokens for Phases 1-3. Phase 4 is mostly
screens and forms on top of permission rules that are already built and tested.
Phase 5 (irreversible schema tightening) goes back to STRICT.

- **Start in a fresh session.** State lives in `.claude/tasks/projects-entity/`
  (untracked; keep it) — read the last Status line of `PROGRESS.md` first — and
  in the auto-memory note `projects-entity-progress`. Do NOT re-read the whole
  `PLAN.md` (about 1,900 lines of stacked amendments).
- **Architect: once, briefly.** One run that extracts a one-page Phase 4 spec
  (routes, controllers, views, permission mapping for the write paths, the
  human decisions already made) into `.claude/tasks/projects-entity/PHASE4.md`.
  No Architect compliance pass afterwards.
- **Developer + ui-designer** build it; **QA** writes the tests. Fresh agents
  with short briefs — do not resume long-running agents.
- **Mutation proofs only for the load-bearing write-path rules:** membership
  add/remove authorization, org and user deletion refusals, creator
  auto-enrol, quote creation only inside a visible project, IDOR on
  project-nested routes. Not for every rule.
- **Verifier: once, at the end,** focused on the write paths and security.
- Human checkpoints stay: approve the one-page spec before coding, approve the
  result before commit. Findings from the Verifier are fixed by the Developer
  and re-checked by the Verifier only if they are security-relevant.
- Still never: run migrate/backfill against the local MySQL DB (`.env` says
  `APP_ENV=production`), touch production/SSH, push, or commit without approval.

### Projects feature status

Phases 1-4 are committed on `feature/projects-entity`; Phase 5 (built in STRICT
mode) is committed on `feature/projects-entity-phase5`. Neither branch is pushed.

- **5a (code, `344e857`):** quote visibility is project-only. `Quote::visibleTo`
  = project visible AND (`estimate_management` R OR author); NULL-project quotes
  are visible to nobody. Deploying 5a with any NULL `quotes.project_id` hides
  those quotes from everyone, so it needs the Phase 2 backfill first.
- **5b (migrations, `0728de0`):** three irreversible migrations
  (`2026_09_25_*`), never run. They refuse outside maintenance mode, without
  `PHASE5_BACKUP_CONFIRMED`, or when pre-conditions fail. The 5a upload must
  not contain them; run 5b only after a 14-day soak of 5a.
- `ProjectBackfillTest` and the legacy schema tests are deleted with 5b; the
  backfill command is removed 30 days after 5b.

Phases 3-5 must never deploy without the Phase 2 backfill. Nothing has been run
on production; the gates (B1, B5, B12, backup, pre-flight queries) are listed in
the memory note. Plan and review trail: `.claude/tasks/projects-entity/`.

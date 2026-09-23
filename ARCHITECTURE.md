# Architecture — RBAC System (Living Reference)

**Purpose of this file**: unlike `HANDOFF.md` (a dated onboarding snapshot
that has drifted from the code in several places), this file is meant to
stay accurate. The Architect agent proposes an update here at the start of
each feature; the update is confirmed and committed only after human
approval at the pre-merge checkpoint (see `.claude/tasks/README.md`). For
exhaustive API/schema detail not repeated here, `HANDOFF.md` is still a
useful *starting point* — but verify anything load-bearing against the
corrections below or the live code first.

Source of the original spec: `Revised_RBAC_Module_Implementation_Plan.pdf`
(client-approved, 12-week, $8,500, "Release 1" = 31 roles).

## Core model

- Entry point: `PermissionService::checkPermission(userId, orgId, permissionGroup, requiredLevel, projectId?)`.
- Roles live in `user_org_roles` only, scoped per org. Never a `users` column.
- Access levels rank F > A > O > S > R (`PermissionMatrix::rank()`); a role's
  granted level satisfies a requirement if its rank <= the required rank.
- `checkPermission()`'s `projectId` parameter is a `quotes.id` ("project" =
  Quote/Estimate in this platform). When passed, `project_members`
  (quote_id, user_id, org_id, is_active) is additionally checked.
- `plan_crosswalk` (org_id + quote_id scoped: plan_line_code -> product_id ->
  manufacturer_part_number) is fully implemented; permission-checked via
  `estimate_management`. UI for managing it is deferred.
- Enforcement: single middleware `RbacAudit`, driven by
  `config/route_permission_map.php` (route -> group, level, batch). Current
  `.env`: `RBAC_MODE=enforce`, `RBAC_ENFORCE_BATCHES=admin,read,write,approve`
  (full enforcement live, not audit-only).

## Verified corrections to HANDOFF.md

| HANDOFF.md claim | Actual | Where |
|---|---|---|
| "88/88 tests pass" via `/tmp/rbac_test.php` | Script doesn't exist. Real suite: 55 passed / 3 failed, 113 assertions | see `TESTING.md` |
| `role_assignment_logs` has column `user_id` | Actual column is `target_user_id` | migration `2026_07_08_000001...php`, model `RoleAssignmentLog.php` |
| "365 role_permission rows for 31 Release 1 roles" | 365 rows span all 49 roles (P1+P2+P3); Release 1 alone (31/32 roles) accounts for fewer | `database/seeders/Rbac/data/role_permission_matrix.php` |
| `auditor_read_all` is R on all groups | Actually holds **F** on `organization_management` | `role_permission_matrix.php` |
| `OrgAdminController` has `delegations()/grantDelegation()/apiTokens()`, `invite()`, `deactivateRole()` | Those live in separate `DelegationController`/`ApiTokenController`; real methods are `generateInvite()` and `removeRole(UserOrgRole $userOrgRole)` | `app/Http/Controllers/Frontend/` |
| Database schema section omits `project_members` and `plan_crosswalk` | Both tables are fully implemented (see above) | migrations `2026_06_25_000007...` and `2026_09_01_000001...` |
| 24 permission groups, 31+17=48 roles (per the original plan) | Actual seeded: **25** groups (extra: `system_administration`), **49** roles (extra: `api_system` in P3, alongside the already-planned P1 `integration_service_account`) | `database/seeders/Rbac/data/` |

## Known open items (not yet resolved — do not silently fix as a side effect of unrelated work)

1. **`OrgRelationshipService::sellerAuthorizationError()`** checks org type
   `=== 'rep_agency'`; the real slug is `manufacturer_s_rep_sales_agency`.
   Dead branch — rep-agency seller authorization is never actually enforced.
2. **`api_system` role phase (P2 vs P3)** — seeded as P3, but
   `SeedMatrixTest`'s own inline comment indicates it was intended as a P2
   structural pull-forward (like `integration_service_account`). Changes
   which enforcement batch it lands in either way. See `TESTING.md` #3.
3. **Organization type count (19 vs 20)** — both the seed data (19) and the
   original plan's own table (19 rows) disagree with the plan's section
   header ("20 types") and `SeedMatrixTest`'s assertion (20). Unclear if a
   20th type was dropped or the header/test are simply wrong. See
   `TESTING.md` #2.
4. **`AuditMiddlewareTest` 403-vs-302** — a no-org user under full
   enforcement gets redirected (302) rather than blocked (403). See
   `TESTING.md` #1.

## Change log

*(Each feature's pre-merge checkpoint appends an entry here once approved —
what changed in the data model, permission mapping, or role/group set, and
why. Newest first.)*

- 2026-09-23 — File created; corrections captured from a full verification
  pass against `HANDOFF.md`, the original plan PDF, and the live codebase.

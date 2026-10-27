# Testing

## Running the suite

```bash
php artisan test --filter=Rbac    # RBAC suite (tests/Feature/Rbac/) — this is the real coverage
php artisan test                  # full suite
```

`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` are unmodified
Laravel scaffolding — not meaningful coverage, don't count them toward
anything.

## Known-failing baseline (updated 2026-10-27, after client audit 2 Phase A, its verifier amendments, Phase B batch 1 incl. its V1 amendment and QA's edge tests, and Phase B batch 2 / W10)

Full suite on in-memory sqlite (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`): **2039 passed / 4 failed** (measured 2026-10-27 after the W10 amendment and QA's edge cases, 21966 assertions: 2031 plus the 8 cases of `W10AmendmentQaTest`; 2031 / 4 was measured 2026-10-27 with 21866 assertions: 2013 plus the 18 cases of `W10AmendmentTest`; 2013 / 4 was QA's measurement of 1961 plus the 52 cases of `W10RegressionTest`; 1961 / 4 was the first W10 build: 1922 plus the 39 cases of `OrgWideProjectVisibilityTest`, and with the W10 code but before the 7 permitted test-line edits it was 1906 / 20, the 4 below plus the 16 cases those edits move) (1922 / 4 before W10, measured by the final gates: 1899 after the V1 amendment plus the 23 cases of `CrossOrgDenialRowEdgesTest` (QA)) (1899 / 4 after the V1 amendment: 1851 after Phase B batch 1, plus the 34 cases of `PhaseBBatch1RegressionTest` (QA) = 1885, plus 14 new cases in `CrossOrgNotFoundTest`; 1851 / 4 after Phase B batch 1: 1698 after Phase A and the A1 amendment plus the 153 cases of its six classes; 1698 / 4 after the A1 amendment, the Verifier's 1650 of 2026-10-25 plus the 48 cases of `InviteInviterAuthorityTest`; 1409 passed / 4 failed on `release/1.1-prod` before client audit 2; 1150 / 4 at the P2 cleanup; 1012 before the universal-admin work). The 4 failures are the 3 listed below plus `ExampleTest` (Laravel scaffold, "no such table: divisions"). Tests are PHPUnit-style classes (no Pest installed). The P2 additions are `AuditFindingsRound2Test` (guard over every mapped route, audit and enforce mode) and `P2CleanupBCTest`. Historical note follows: the RBAC suite was **682 passed / 3 failed** (Phase 5a rewrite plus the Phase 5 QA tests and amendment 3 round 2, 651 before round 2; Phase 4 baseline was 568 / 3). (The earlier
baseline of 55 passed / 3 failed, 113 assertions was measured **before** the
projects work.) These 3 failures are pre-existing and independent of any feature work — do not report them as
a regression you introduced, and do not treat a *new* failure as "probably
one of the known ones" without checking it's actually one of these three by
name:

1. **`AuditMiddlewareTest::enforce mode blocks and records blocked`**
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

## Client audit 2 (Phase A) tests

- `RoleAssignmentGuardTest` - `assignRole` / `generateInvite` / invite acceptance: active members only, no Platform roles, no Seller roles in buyer orgs, universal-admin exceptions, the platform-admin screen still assigns anything.
- `OrgDropdownRolesTest` - the Team, Overview and Roles dropdowns offer exactly the roles the server accepts.
- `OwnershipDelegationMemberGuardTest` - ownership transfer and delegation only to active members; no account-existence hint on transfer.
- `ExistingUserInviteTest` - existing accounts are invited, not attached; `invite/{token}/accept` (own verified e-mail only, one use, SoD, 404 for anyone else).
- `PhaseARegressionTest` - 34 cases of regression pins for behaviour that must NOT change: Cross-Functional and Procurement roles still assignable, Seller roles still assignable in manufacturer orgs, SoD still applied inside `assign()`, the approval guard not blocking legitimate approvers, audit labels, the new-hire register link, transfer and delegation to legitimate members.
- `InviteInviterAuthorityTest` - verifier amendment A1: `invite/{token}/accept` (review and accept) refuses an invite whose inviter is no longer an active member holding `user_management` F in the invite's org (removed, demoted, authority in another org, deleted, delegation ended), keeps it usable for a still-valid or universal-admin inviter, answers with the same 404 as an expired invite, and leaves the guest register link unchanged (audit and enforce).
- `OrderSelfApprovalTest` - no approving or rejecting your own order while another approver exists (also via delegation); sole approver allowed and logged; the approvals list contract.
- `AutoApprovalTest` - sole-approver orders are `processing` with an `auto_approved` row (checkout and RFQ convert, audit and enforce), audit-failure rollback, audit page labels, and the `orders:backfill-auto-approval` command.
- Existing tests changed by this work: `P2CleanupBCTest` (2 asserts `pending` -> `processing`), `RfqProjectScopeTest` (1 assert and test name), `UniversalAdminTest::test_b_non_listed_owner_rules_are_unchanged` (the new owner is made a member of the org first), `AuditFindingsRound2Test::OPEN_ROUTES` (two invite-accept routes).

## Client audit 2 (Phase B batch 1) tests

- `SodSoloOrgTest` (20) - N01: the only active member of an org may hold any SoD-conflicting pair (every seeded rule, both directions); with a second active member SoD applies again and nothing is revoked; a sole member of another org, inactive rows and an org with no active member (audit and enforce).
- `JsonErrorBodyTest` (17) - S13: JSON 404 (another org's customer, missing record, unknown URL), 500 from a throwing route and 5xx aborts carry only a generic message with `APP_DEBUG` on and off; 409 keeps its message; the 403 `rbac_error` shape, the 422 body and the guest 401 are unchanged; the app handler is the bound one.
- `RegistrationTest` (9) - B06(a) proof: registration sends `VerifyEmail` exactly once (three org types), signed link, resend, no mail on failed validation; B06(b) inline team-size error and no `alert(` in the register page.
- `CrossOrgNotFoundTest` (52) - O05: another org's / missing / soft-deleted project and quote, and a NULL-project quote, give one identical 404 (JSON and HTML) in enforce mode while the `blocked` row stays; same-org non-member keeps the 403 membership text; below-level keeps the generic 403; audit mode unchanged; the five controller sites (role, connection, project-member removal, RFQ show/select/convert, order approve/reject) in both modes; since the V1 amendment (PLAN 10.13) also the audit row of those 404s: exactly one `blocked` row (reason `controller_denied`) per cross-org probe with the mapped route, group and level (for a below-level actor in audit mode the middleware's `would_block` row is upgraded in place and keeps reason `no_grant`); none for a missing id, the own-org success, the order status 403 (one row from the renderer) or the hidden-project 404; a below-level actor leaves one `no_grant` row and no `controller_denied` row.
- `CrossOrgDenialRowEdgesTest` (23, QA) - edges of the 404 audit row that `CrossOrgNotFoundTest` does not pin: browser (non-JSON) requests at the five sites give a plain 404 with one denial row and the same page as a missing id; a broken audit table never turns the 404 into an error; the row carries the validated acting org, not a stale session value; a same-org 404 (member row of an own-org trashed project) leaves no denial row.
- `PhaseBBatch1RegressionTest` (34, QA) - pins for behaviour that must NOT change in Phase B batch 1 and is not already covered by the classes above: the platform-admin role screen keeps SoD in multi-member orgs; JSON 405 / 419 / 429 / guest 403 keep their messages and headers; HTML 404 / 500 pages and the error log are unchanged; `.env.example` is production with debug off; allowed requests write no audit row; own-org role / connection / project-member removal keep their answers; a user without an org still gets 403; exact-name partner lookup (stored versus typed case), markup escaped on redisplay, a stray `to_org_id` ignored; delegation grant / revoke flash messages; a universal admin who is also a delegate; very long names in delegation audit rows.
- `ConnectionsLookupTest` (27) - D21: exact-name lookup (prefix, substring, own org, duplicate names, wildcards all get the identical generic flash and create nothing), `withInput`, message only in the flash, validation only in the `partner_name` bag, 403 before any lookup, no org list on the page, throttle `20,1` on the POST only.
- `DelegationAuditTest` (42) - L06: `delegation_granted` / `delegation_revoked` / `delegated_action` rows (org screens, platform-admin screen, universal-admin override), state check on revoke, scoping to the right org, no row on denied / unmapped / expired / revoked / non-delegate requests, fail-soft on audit failure, labels on the org and admin audit pages.
- Existing tests changed by this batch: `SodTest` (setUp adds a second active member so its conflict tests are not sole-member orgs); `ProjectMembershipTest`, `ProjectReadPathsTest`, `ProjectWritePathsTest` (enforce-mode status for another org's project / quote: 403 -> 404, same-org non-member lines keep 403); `ProjectCheckpoint2Test`, `ProjectWalkthroughFixesTest`, `AuditFindingsRound2Test`, `AuditFindingsTest`, `OrderSelfApprovalTest`, `RfqProjectScopeTest`, `UniversalAdminTest` (23 cases, PLAN 10.12.1/10.12.1a: another org's record, or a same-org NULL-project quote, 403 -> 404; `RfqProjectScopeTest` convert and select stay 403 in enforce mode because the actor is below the level); `AuditFindingsTest::test_d_connection_store_needs_organization_management_f` (payload `partner_name`).

## Client audit 2 (Phase B batch 2, W10) tests

- `OrgWideProjectVisibilityTest` (39, audit and enforce mode) - W10: the users whose OWN roles grant `organization_management` F (a pin over every seeded role: exactly Organization Owner, Organization Admin, Auditor/Read-All, Manufacturer Admin, Platform Super Admin; none of them in another org; any one granting role is enough; R is not enough) list, open and edit a project they were never enrolled in with zero audit rows (T1); the client's real scenario, an Estimator creates a project and the Owner sees it without being enrolled (T1b); estimates (T2, `quotes/list`, details, PUT; NULL-project quote and org B quote 404); visibility is not authority: Organization Admin and Auditor read, Auditor cannot edit a project, Organization Admin edits the project but not the estimate (T3); Estimator, Project Manager, Procurement Manager and a non-member keep membership scope, 404 in audit / 403 in enforce mode (T4); other org's Owner, seller-org Owner, a seller never seeing the buyer's project on a received RFQ, a multi-org Owner acting in each org (T5); soft-deleted project and its quote, service-level `checkPermission` true / false matrix (T6); deactivated Owner role (T7); `project_param` and `quote_param` routes with zero audit rows in both modes, and per-org enforcement with global audit mode (T8); a delegate of an Owner keeps his own list, is checked as the principal, and a delegation from a delegate is not a second hop (T9); universal admin with and without an org-wide role, non-listed Owner (T10); RFQs of a project the Owner is not in (T11); membership screens add / remove for the Owner without enrolling them, a non-member `project_management` F holder is still refused (T12); project delete refusal and delete of an empty project (T13). Page HTML assertions use renamed, distinctive project names, never the bare 'P3' (see the flaky test below).
- `W10RegressionTest` (52, QA) - W10 pins that the class above does not carry: roles that must stay membership-scoped in every list and route, org-wide holders against every id-keyed route of another org, a forged session org, quote and project creation, the membership and creation screens for org-wide holders, universal admin and delegation accounting (including an Owner who is the delegate of a member-scoped principal), dashboard counters equal the quotes index, Manufacturer Admin holding neither project nor estimate rights, a union of roles, and levels A / O / S on `organization_management` (no seeded role holds them).
- `W10AmendmentTest` (18, audit and enforce mode) - the Verifier's V1 re-scope and the V4 / V6 findings (PLAN 10.14.12): a delegate of a non-member Owner gets 404 on add, self-enrol and remove through `POST org-admin/projects/members` and `DELETE org-admin/projects/members/{projectMember}` with only the middleware's `delegated_action` rows and nothing written (A); the same delegate manages the members of a project he is in, attributed to him (B); Owner and Organization Admin unchanged (C); same-org non-members with `project_management` F get 404 (D); an expired or revoked delegation leaves the delegate on his own roles, 403 (E); an Owner acting for a member-scoped principal is checked as that principal, add and remove (F); `GET org-admin/projects` for the Auditor and Procurement Manager (H); Organization Admin deletes an empty project (I); Manufacturer Admin in audit versus enforce mode (J, pins the accepted V4 behaviour).
- `W10AmendmentQaTest` (8, QA, audit and enforce mode) - edges of the V1 lookup that `W10AmendmentTest` does not pin: a soft-deleted project is a 404 on add and remove even for its members and for org-wide holders; a former member gets 404 on his own deactivated row and cannot re-enrol himself; an Owner of two organizations manages members in the acting org and probes the other with 404; a member-scoped Project Manager of two organizations is judged in the acting org.
- Existing tests changed by this batch (9 lines in 7 methods, PLAN 10.14.5 and 10.14.12.2; the first five methods move a "member-scoped" expectation from an Owner to an actor that stays member-scoped, the last two a same-org non-member on the org-admin member screens from 403 to 404): `P2CleanupBCTest::test_pending_approvals_are_listed_for_approval_authority_a_within_visible_projects` (line 250: the Owner now also sees the pending estimate of P2) and `::test_pending_approvals_wrong_org_and_no_project_membership_see_nothing` (line 294: the "not a member" fixture is a `procurement_manager`); `UniversalAdminTest::test_a_owner_admin_account_and_viewer_are_denied_cross_org_and_cross_project` (line 320: `est` instead of `ownerA` for the P2 check), `::test_a_project_and_quote_visibility_is_unchanged` (line 341: `ownerA` sees `[p1, p2]`) and `::test_a_project_routes_still_hide_other_projects_from_non_listed_users` (lines 359, 362, 366: `est` instead of `ownerA`); `AuditFindingsTest::test_a_add_member_wrong_org_and_missing_project_membership` (line 149) and `::test_a_remove_member_wrong_org_and_missing_project_membership` (line 206): `est` on a P2 member screen, `assertForbidden()` -> `assertNotFound()`.

## Known flaky test (not fixed)

`AuditMembershipLogTest` 'audit page shows this orgs rows only newest first with system for null' fails in about 2% of full-suite runs. It does `assertDontSee('P3')` on the whole page, and the page contains the random 40-character CSRF `_token`, which sometimes includes the characters `P3`. Pre-existing, unrelated to client audit 2, passes in isolation and on a re-run; not fixed. It appeared once more in QA's full run of W10. Do not report it as a regression without re-running that class.

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

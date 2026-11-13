# Testing

## Running the suite

```bash
php artisan test --filter=Rbac    # RBAC suite (tests/Feature/Rbac/) — this is the real coverage
php artisan test                  # full suite
```

`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` are unmodified
Laravel scaffolding — not meaningful coverage, don't count them toward
anything.

## Known-failing baseline (updated 2026-11-13, after client audit 2 Phase A, its verifier amendments, Phase B batch 1 incl. its V1 amendment and QA's edge tests, Phase B batch 2 / W10, and round 2)

Round 2 of client audit 2 (2026-11-13), final full run by QA after the six new classes below: **2382 passed / 4 failed**, 25177 assertions, 165 s (2039 plus the 343 data sets of the new classes; PHPUnit's summary reads "2377 deprecated, 4 failed, 5 passed"), the same 4 failures by name. Measured by the Developer before QA added the new classes: **2039 passed / 4 failed**, 21967 assertions, the same 4 failures by name (`Tests\Feature\ExampleTest`, `AuditMiddlewareTest::enforce mode blocks and records blocked`, `SeedMatrixTest::seeds expected counts`, `SeedMatrixTest::phase distribution`); no new test was added by the Developer, so the count equals the 2026-10-27 baseline (2039 / 4, 21966 assertions). On PHP 8.5.12 with the default `memory_limit=128M` the full run dies with "Allowed memory size ... exhausted" (twice, before any code change); it completes with the limit lifted for the phpunit child process, which `php -d memory_limit=-1 artisan test` does not reach: set `PHP_INI_SCAN_DIR=<default conf.d>:<dir holding a file with memory_limit=-1>` instead (about 140 s). In PHPUnit's summary PHP 8.5 reports most passing tests as "deprecated" (2034 of the 2039).

Full suite on in-memory sqlite (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`), before round 2: **2039 passed / 4 failed** (measured 2026-10-27 after the W10 amendment and QA's edge cases, 21966 assertions: 2031 plus the 8 cases of `W10AmendmentQaTest`; 2031 / 4 was measured 2026-10-27 with 21866 assertions: 2013 plus the 18 cases of `W10AmendmentTest`; 2013 / 4 was QA's measurement of 1961 plus the 52 cases of `W10RegressionTest`; 1961 / 4 was the first W10 build: 1922 plus the 39 cases of `OrgWideProjectVisibilityTest`, and with the W10 code but before the 7 permitted test-line edits it was 1906 / 20, the 4 below plus the 16 cases those edits move) (1922 / 4 before W10, measured by the final gates: 1899 after the V1 amendment plus the 23 cases of `CrossOrgDenialRowEdgesTest` (QA)) (1899 / 4 after the V1 amendment: 1851 after Phase B batch 1, plus the 34 cases of `PhaseBBatch1RegressionTest` (QA) = 1885, plus 14 new cases in `CrossOrgNotFoundTest`; 1851 / 4 after Phase B batch 1: 1698 after Phase A and the A1 amendment plus the 153 cases of its six classes; 1698 / 4 after the A1 amendment, the Verifier's 1650 of 2026-10-25 plus the 48 cases of `InviteInviterAuthorityTest`; 1409 passed / 4 failed on `release/1.1-prod` before client audit 2; 1150 / 4 at the P2 cleanup; 1012 before the universal-admin work). The 4 failures are the 3 listed below plus `ExampleTest` (Laravel scaffold, "no such table: divisions"). Tests are PHPUnit-style classes (no Pest installed). The P2 additions are `AuditFindingsRound2Test` (guard over every mapped route, audit and enforce mode) and `P2CleanupBCTest`. Historical note follows: the RBAC suite was **682 passed / 3 failed** (Phase 5a rewrite plus the Phase 5 QA tests and amendment 3 round 2, 651 before round 2; Phase 4 baseline was 568 / 3). (The earlier
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

## Client audit 2, round 2 (M12, S15, O11, N01, L08, W12) tests

New classes (written by QA, 2026-11-13; 343 data sets in audit and enforce mode, all green on in-memory sqlite, mutation-proven, see `.claude/tasks/client-audit-2-round2-fixes/REVIEW.md`):

- `InviteUniformResponseTest` (41) - O11: `POST org-admin/invite` answers the same keys, types and link form (`url('/invite/'.$token)`) for an address with an account and a new one, with and without mail; the two mails render the same after the token is normalised; no `users` query carries the invited address and the two statement lists are identical (`DB::listen`); the guest `GET invite/{token}` page is the same for both and equal to `register/invite/{token}`, signed in it redirects to `invite.review` (wrong user and unknown token end at the same 404, unverified goes to verification), unknown / used / expired give the invalid page; `POST register/invite/{token}` for an existing address creates nothing and redirects to login with `url.intended` and the `status` flash, a bad form gets the same validation errors for both, a new address still registers; old links still work; route middleware (`invite.entry` public, `invite.show` / `invite.register` guarded by the route and by the controller); denied paths (below the level, no org, forged session org, unknown / platform / seller roles).
- `LastRoleConfirmationTest` (95) - L08: the last active role of a member is refused without a truthy `confirm_last_role` on `DELETE org-admin/roles/{id}` (422 JSON / 302 back with the `error` flash) and `POST org-admin/peel-off` (422 JSON), nothing written, no `role_assignment_logs` row; 12 parameter forms on each route; two roles, inactive second row, active row in another org, already-inactive row, lone inactive row; own last role; sole Owner (D6); listed universal admin; below-level actors get 403 and another org's row 404 with exactly one `controller_denied` row, both WITHOUT the parameter (the older classes now send it); `assignRole` JSON `peel_off_last_role`.
- `SodOwnerBundleExemptionTest` (57) - N01 option B: an Owner in a multi-member org holds Procurement Manager or Estimator with Executive Approver in either order (Team page, self-grant, Owner granted after the first role); the 8 seeded rules x 2 directions at service level (Owner: 4 exempt, 12 refused; plain member: 0 exempt); Organization Admin, Viewer, Project Manager, inactive Owner row, Owner of another org, mixed hold stay refused; the bundle is read from `rbac.onboarding.bundles.buyer_small`; invite acceptance and the platform-admin screen follow the same rule; A05 and auto-approval unchanged; `test_every_role_the_owner_exemption_covers_is_covered_by_the_owner_roles_grants` (no HTTP) pins that every role of the configured bundle other than the Owner is covered by the Owner role's SEEDED grants (same permission group, equal or stronger level, so the exemption adds no permission to a seeded Owner); it reads the seeded matrix only, not the live `role_permissions` rows, which `PUT org-admin/roles-list/permissions` can edit (ARCHITECTURE section 12 item 6), and it deliberately does not pin the Estimator -> Owner -> Executive Approver chain (that would record open item 6 as expected behaviour).
- `CreatorMembershipTest` (112) - M12: at estimate `store`, `createFromList`, `duplicate` and RFQ `store` the creator becomes an active project member through `ProjectMember::enrol` (member creator: no extra row or log; Owner: one row and one `added` log performed by himself; reactivation; membership org = project org), nothing is written for an unauthorised, invisible, soft-deleted or failing request, RFQ enrolment rolls back with the RFQ, a listed universal admin with no (or only an inactive, or only another org's) role gets no row and no error, a delegate is enrolled himself and not the principal; project create control.
- `HtmlSinkGuardTest` (18) - S15: static scan of the touched Blade files (no `innerHTML` in the invite pop-ups, one escaped `innerHTML` in the Team drawer, balanced-brace scan of every `${...}` in the drawer templates, dead branches gone, no `{!!`, every `onclick` function defined, every `data-action` handled, `@js` at the three `confirm()` sites) plus rendered pages with hostile names (hex-escaped `@json`, `@js` attributes). PHPUnit cannot run page JavaScript; the manual browser list in REVIEW.md is the other half.
- `Round2ClientWordingReplayTest` (20) - verification only for W10, L05, L06 and S13 in the client's words (the delegated-approval row carries the route pattern, not the order: PLAN R9, pinned on purpose; S13 with `APP_DEBUG=true`).

Tooling note for anyone editing `HtmlSinkGuardTest`: the hex-escape expectations are built with `chr(92).'u'.$code` because an editor tool that expands a literal backslash-u sequence turns the assertions into raw-character checks.

Existing tests changed by the Developer in this round (2026-11-13), each to the smallest edit that matches the new behaviour; 24 data sets failed before the edits, all inside these seven classes, and the full suite is back to the 4 known failures after them:

- `PhaseARegressionTest`: `test_a_brand_new_user_invited_by_e_mail_still_registers_through_the_register_link_with_the_invited_role` (the returned link and mail URL are `url('/invite/'.$token)`, `existingAccount` is gone, the registration POST goes to `register/invite/{token}` because the returned link is a GET-only entry URL); `test_a_manufacturer_owner_can_still_invite_a_new_hire_into_a_seller_role` (same POST target, unused `$r` dropped); `test_the_assign_answer_keeps_its_shape_and_the_peel_off_hint` (the pinned key list gains `peel_off_last_role`).
- `ExistingUserInviteTest`: `test_inviting_an_existing_account_creates_an_invite_and_assigns_nothing` (link is `/invite/{token}`); renamed `test_the_mail_links_to_the_invite_entry_for_an_existing_and_a_new_address`, `test_the_mail_text_is_the_same_for_an_existing_and_a_new_address` and `test_the_returned_link_has_the_same_invite_entry_form_for_a_new_and_an_existing_address` (each flipped from asserting the old difference to asserting none).
- `SodSoloOrgTest`: `test_a_member_with_any_active_row_ends_the_exemption` and `test_the_service_reports_no_conflict_for_a_sole_member_and_names_them_for_a_second_member` use Requisitioner + Executive Approver (a non-bundle pair) because the Owner target is now exempt for the bundle pairs.
- `CrossOrgNotFoundTest::test_removing_a_role_row_of_another_org_is_a_404_like_a_missing_row`, `PhaseBBatch1RegressionTest` ("an owner still removes a role, a connection and a project member") and the `peelOff` / `removeRole` fixtures of `AuditFindingsRound2Test` (lines 126-127): the request carries `confirm_last_role`, because the row removed is the member's only role.
- `W10RegressionTest::test_quote_creation_is_allowed_in_a_visible_project_at_the_estimate_level_and_never_elsewhere`: the Owner now has exactly one active `project_members` row after creating an estimate (was 0, "creating does not enrol the owner").
- Named in the plan but unchanged because they still pass: `RoleAssignmentGuardTest`, `CrossOrgDenialRowEdgesTest`, `OrgWideProjectVisibilityTest`, `W10AmendmentTest`, `W10AmendmentQaTest`, `RfqProjectScopeTest`, `ProjectWritePathsTest`, `InviteInviterAuthorityTest`.

Not covered by an automated test at all: the behaviour of the page JavaScript (invite pop-up, drawer, last-role modal, peel-off modal).

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

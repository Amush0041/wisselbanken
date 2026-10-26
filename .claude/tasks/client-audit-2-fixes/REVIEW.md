# Review: <feature name>

Status: PENDING — awaiting human approval (Checkpoint 2)

## QA results (qa-tester agent)

## Reviewer findings (reviewer agent)

## Architect compliance pass - Phase A

Date: 2026-10-25. Reviewer: Architect (second role). Scope: uncommitted working tree on `feature/client-audit-2-fixes` against the approved PLAN.md (items A1-A4, tasks 3a/3b/3c/5/6/7/8, section 9 rules). Method: `git diff` of every touched file, full read of every untracked file (InviteAcceptController, invite-accept view, BackfillAutoApprovedOrders, the six new test classes), grep of every caller of a changed method, grep of views and controllers for order status `pending` / `processing` / `approved_by`, and a read-only test run on in-memory sqlite (never the real DB). Nothing was migrated, backfilled or written outside this file.

Test evidence (run by me, `DB_CONNECTION=sqlite DB_DATABASE=:memory:`): `OrderSelfApprovalTest` 38 passed, `ExistingUserInviteTest` 26, `AutoApprovalTest` 40, `RoleAssignmentGuardTest` 38, `OwnershipDelegationMemberGuardTest` 28, `OrgDropdownRolesTest` 14. Full suite: 1593 passed / 4 failed, the 4 being exactly the baseline (ExampleTest, AuditMiddlewareTest enforce-302, SeedMatrixTest x2). The test names match what they assert (read OrderSelfApprovalTest, ExistingUserInviteTest in full; AutoApprovalTest rollback/command sections; guard test headers for the rest).

### VERDICT: COMPLIANT

No must-fix item. Every guard is where the plan puts it, with the plan's status codes and messages, on every code path the plan lists. I record 6 small deviations/additions below; all fail closed or are additive, I accept all of them, and 4 of them need the human's explicit acknowledgement at Checkpoint 2 (section b).

### Line-by-line against the plan

| Plan item | Where it landed | Result |
|---|---|---|
| A1 membership guard | `OrgAdminController::assignRole`, after validation, before SoD and `assign()`. 403 text is exactly the plan's. Universal admin allowed and `recordBypass(..., 'universal_assign_non_member')`. `RoleAssignmentService::assign()` untouched (diff adds only `isActiveMember` and `isAssignableByOrg`). | OK |
| A2 category rule | `isAssignableByOrg`: Platform never; Manufacturer/Seller only for `rbac.onboarding.manufacturer_org_types`; rest allowed. Used in `assignRole`, `generateInvite` (before the existing-account branch, so both invite paths), `InviteController::register` (treated as invalid: same login redirect + error, no user created), `InviteAcceptController`, and one `assignableRoles()` helper feeding `index()`, `overview()` (invite dropdown + coverage list) and `rolesList()`. Both 403 messages are verbatim. UA allowed and logged `universal_assign_restricted_role`. `Admin\RbacController` not in the diff (platform-admin screen still assigns Platform roles; pinned by test). | OK |
| A3 self-approval | `ApprovalRoutingService::otherApprovers` (pool minus submitter, `approverPool` still `distinct()`). `OrderApprovalController::guardOwnOrder` is called in BOTH `approve` and `reject`, right after `authorizeApprovalAction`; self = caller or the delegate's principal; own + other approvers => 403 with the exact plan text (approve/reject variants); own + sole approver => allowed; approve writes `self_approved`, reject does not; no universal-admin exemption. `index()` passes `$awaitingOthers`; view shows "Awaiting another approver" and omits that row's modals (balanced Blade, other rows unchanged). | OK |
| A4 auto-approve | `initialOrderState` (signature as planned), `recordAutoApproval`, `AUTO_APPROVAL_NOTE`. Checkout: audit call after `Order::create`, before `DB::commit()`. RFQ convert: inside the `DB::transaction` closure after `create`. Success messages untouched. Status `processing`, `approved_by` = requester, note = plan text. Audit views: Details column, two labels, two filter options (admin enforcement view has no filter, so none added), explicit badges before the fallbacks. | OK |
| 3a transfer ownership | `exists:users,email` removed; unknown e-mail and non-member give the same 403; UA allowed for an existing non-member (`universal_transfer_non_member`) but gets nothing for an unknown e-mail; the 422 "already the owner" path runs before any bypass is recorded. | OK |
| 3b delegation | `DelegationController::store`: 403 unless the delegate is an active member. See ruling 1 for the UA point. | OK with ruling |
| 3c existing-account invite | Direct-assign branch and `confirmed` rule gone; every e-mail gets an `org_invites` row and a `{type, email_sent, link}` answer with no account name; `OrgInviteMail` takes an optional flag (default false, only caller is `generateInvite`); new `InviteAcceptController` + view + two routes inside the existing `auth, verified, checkRole:user` group; verified e-mail equality (case/space-insensitive) else 404; used/expired/not-assignable => invalid page; assignment via `RoleAssignmentService::assign` (SoD applies, conflict leaves the invite unused); one-time claim by conditional `UPDATE ... WHERE used_at IS NULL` in the same transaction as the assignment. Audit pack regenerated: I diffed the old and new `route-list.json` by method+uri: exactly the two new routes added, nothing removed or changed; `docs/client-audit-pack/route_permission_map.php` is byte-identical to `config/route_permission_map.php`. | OK |
| Task 7 backfill | Selection exactly as planned (`pending`, `org_id` not null, `approved_by`/`rejected_by` null, `user_id` not null), chunked, one transaction and one conditional update per order, `auto_approved` row with `sole_approver_backfill`, method `CLI`, reports under `storage/app/backfill/orders-auto-approval/<ts>/`, `--force` for a real production run, idempotent, exit codes as the sibling `customers:backfill-org`. `storage/app/backfill/` contains no `orders-auto-approval` directory, so it was never run against the real storage. | OK |
| Task 8 docs | ARCHITECTURE.md (new 4.5, 4.3 outcomes, 4.4 open routes, 7, 10b, change log) and TESTING.md match the code (spot-checked every claim in 4.5 against the controllers). Only 4 lines of old text were removed from ARCHITECTURE.md, all superseded. Header "last verified 2026-09-26" correctly left alone. | OK |
| Route map | `config/route_permission_map.php` and `config/` are untouched (git status clean there); the two accept routes are in `AuditFindingsRound2Test::OPEN_ROUTES` with reasons. | Correct, see 4.1 |
| Migrations / config / env | None touched. `Admin\RbacController`, `OrgOnboardingService`, `PermissionService`, `DelegationService`, `bootstrap/app.php` are not in the diff. | OK |

#### 4.1 Map entry versus OPEN_ROUTES for `invite/{token}/accept`

OPEN_ROUTES is the right choice, and the plan allowed it ("map entry or OPEN_ROUTES reason"). A map entry is a (group, level) check against the caller's CURRENT org, but the invitee has no role in the inviting org yet and may have no org at all; in enforce mode a no-org user is redirected by `RbacAudit` (reason `no_org`), which would block exactly the person who must be able to accept. The route's real guard is identity: signed in, verified, e-mail equals the invite's. There is precedent (13 listed open routes). It does depart from the literal text of CLAUDE.md ("every new route must get a map entry"); the human should add one sentence to CLAUDE.md pointing at the OPEN_ROUTES exception (I may not edit CLAUDE.md).

#### 4.2 The two extra existing-test edits

Existing tests changed: `P2CleanupBCTest` (2 asserts `pending` -> `processing`), `RfqProjectScopeTest` (1 assert + rename), both named by the plan; plus `UniversalAdminTest` (+1 line) and `AuditFindingsRound2Test` (+2 OPEN_ROUTES entries), the two the plan allows conditionally. No other test file is modified.
- `UniversalAdminTest::test_b_non_listed_owner_rules_are_unchanged`: adds `assignRole($this->est, $orgC, 'estimator')` before the real owner's transfer. The target previously had no role in that org, which is exactly what 3a now refuses. The test's point (the real owner can still transfer, a non-listed admin cannot) is preserved, and the line sits after the non-listed-admin 403 assertion so that assertion is not weakened. Accept.
- `AuditFindingsRound2Test::OPEN_ROUTES`: required by plan 3c and by the guard's own rule (every authenticated route is mapped or listed with a reason). Accept.

### Rulings on the Developer's 12 points

1. **3b, no UA exemption for delegating to a non-member: accept, human to confirm.** A non-member delegate evaluates as the principal inside the org (`checkPermission` step 0), so exempting a UA would hand an outsider the principal's whole authority; the UA can first add the person with the logged non-member assignment. The plan said "same rule" without the decision-c wording, so this is the stricter reading. ARCHITECTURE.md 4.5 and 10b already document it.
2. **3c response shape: accept.** Keys are identical to the new-invite answer (a test compares `array_keys`); the old answer's disclosure of the account holder's NAME is gone. The `link` prefix does tell the inviter the account exists, which is unavoidable (the register link would fail on the unique e-mail) and not a new leak: `RegisterController.php:97` has `unique:users`, so existence is already public.
3. **3c 404s: accept.** Unknown token, wrong user and unverified account all give the identical bare 404; an unverified user is stopped earlier by the `verified` middleware (302) so no token oracle exists; the friendlier invalid page (still status 404) is shown only to the right user for a used/expired/no-longer-assignable invite.
4. **3c accept switches the session org, SoD conflict leaves the invite unused: accept.** Mirrors `InviteController::register` (line 72); `CurrentOrg` verifies the active role. The SoD path rolls the claim back with the transaction (tested). Human FYI: the user is moved out of the org they were working in.
5. **3c inviter authority not re-checked at accept: accept, human to note.** Parity with the existing guest flow, and the plan's accept rules do not include it. It is a new exposure for existing accounts (previously immediate, now up to 7 days): an inviter who is demoted or removed in that window leaves a usable invite behind. Recommend a follow-up (not in Phase A): revoke unused invites when the inviter loses `user_management` F or membership, or re-check `isActiveMember(invited_by)` at accept for both flows.
6. **3c UA inviting a Platform/Seller role creates an invite that is refused at accept: accept as a literal consequence of A2 + 3c, human to decide.** It fails closed, and it also writes a `universal_assign_restricted_role` row although nothing is granted. A UA can only be confused by it. If preferred, make `generateInvite` refuse restricted roles for UA as well and keep the exemption on `assignRole` only (small change, would need a PLAN.md amendment first).
7. **A2 `InviteController::show()` unchanged: accept.** The plan names `register` only; a stale Platform-role invite shows the form, and submit is refused with the standard invalid message and no user created (tested). Optional one-line follow-up to apply the same check in `show()`.
8. **Dead `confirm_existing` / `existing` JS branches left in `index.blade.php` and `overview.blade.php`: accept.** Verified by reading the JS: the server now only answers `type: invite` (or an `error`), which the `else` branch renders (status banner + link); the dead branches are unreachable and removing JS from a working modal adds risk for no gain. Cleanup can ride with a later change.
9. **A3 `self_approved` row and status update in one `DB::transaction`; delegate row carries the real actor: accept.** Not in the plan but consistent with A4 (audit failure means no state change); for non-sole approvers the transaction wraps only the single update, so normal approvals are unchanged. Real actor in `user_id` is right for an audit row. Consequence: an outage of the audit table blocks sole-approver self-approvals (fail closed, same as A4). The row does not name the principal when a delegate acts; Phase B7's `delegated_action` is the place for that.
10. **A4 `initialOrderState` signature and guest/no-org handling: accept.** Signature matches the plan. The plan's "no-org case returns status only" cannot live in a method that receives a routing result, so the call site keeps `['status' => 'pending']` when there is no session org (identical to before: no `approved_by`, no audit row). Note `checkout/process` sits inside the `auth, verified, checkRole:user` group, so the guest branch is defensive only.
11. **`AuditLog::event` not fail-soft: accept.** Required for the A4 and A3 rollbacks. Phase B7 must wrap it in try/catch + `Log::error` where the plan says writes are fail-soft (`delegated_action` in `RbacAudit`, delegation grant/revoke); I will add that note when I update PLAN.md for Phase B.
12. **sqlite-only proof of the backfill skip and the invite claim: accept for this gate, with a condition.** Both are plain standard SQL (conditional UPDATE, affected-rows = 1 because `used_at` / `status` really change; Laravel's default PDO flags count changed rows). The tests simulate the race by writing from a `DB::listen` hook, which proves the code path, not InnoDB locking. Condition: dry-run the command on a restored MariaDB backup before `--force`, and if a staging DB exists, fire two accepts at once.

### Regression hunt (things tests would not catch)

Checked and found unchanged or safe:
- **Other callers.** `RoleAssignmentService` only gained methods. `ApprovalRoutingService::route()` and `approverPool()` unchanged. `OrgInviteMail`'s only caller is `generateInvite` (new arg is optional, default false). `InviteController` only gained one condition. `AuditLog` only gained a static method. No code instantiates the changed controllers by hand (`OrgSettingsController` gained a constructor dependency; container-resolved).
- **New-user invite.** Link `/register/invite/{token}` and the mail body/button text are unchanged for new e-mails (conditional blocks render the old text); the register flow is pinned by a test.
- **Platform admin, onboarding, signup.** `Admin\RbacController` and `OrgOnboardingService` untouched; platform-admin assignment of Platform roles pinned by a test.
- **Delegation and ownership for legitimate members.** The delegation picker and the Team page list active members only, so the new 403s are unreachable from the UI for legitimate use; allowed paths are tested.
- **Checkout / orders.** Non-approver and multi-approver paths still produce `pending_approval`; the success message keys on `pending_approval` so auto-approved orders still read "placed successfully". `processing` is in the `orders.status` enum (migration `2026_09_01_000002`). Views: `user/orders/index.blade.php`, `frontend/orders/orderDetail.blade.php`, `user/dashboard.blade.php`, `admin/orders/show.blade.php` and `OrderDataTable` only read status for display (`ucfirst`/badge map with a default) and render "Approved by" from `approved_by`; nothing in app, routes or views transitions orders out of `pending`, so the old `pending` rows really were stuck and no admin workflow depends on them.
- **Orders already in `pending_approval`.** Unchanged for everyone except their own submitter-who-is-an-approver, who now gets "Awaiting another approver" in the list and a 403 on a forged POST while another approver exists.
- **Raw SQL.** None added (query builder only). The new audit columns fit MariaDB limits (`method` 'CLI'/'POST' under 10, `required_level` 1 char, `reason` limited to 255, `outcome` is a plain string).
- **Audit counters.** Admin dashboard counts only `would_block` / `blocked`; the new outcomes do not inflate them. `DenialRecorder` tracks its row by request attribute, so a business-event row in the same request is not mistaken for the middleware row.
- **Audit retention.** Nothing prunes `rbac_audit_logs`; org deletion wipes the org's rows including the new events (pre-existing behaviour).

Minor convention notes (no action required): a few docblocks (`guardOwnOrder`, `InviteAcceptController`, the routes comment) describe WHAT, which CLAUDE.md discourages; harmless.

### (a) Must-fix before QA

None.

### (b) Decisions for the human at Checkpoint 2

1. Acknowledge OPEN_ROUTES instead of a map entry for the accept routes, and add one sentence to CLAUDE.md so the rule reads "map entry, or an OPEN_ROUTES reason when no org permission can apply" (4.1).
2. Ruling 1: no UA exemption for delegating to a non-member (stricter than "same rule").
3. Ruling 5: invites outlive the inviter's authority (up to 7 days); follow-up item or accept as-is.
4. Ruling 6: UA can create a restricted-role invite that can never be accepted; leave, or refuse it at creation.
5. Reminder of approved decision a, which removes something that works today: owners of rep agencies (`manufacturer_s_rep_sales_agency`) and any other non-listed org type could previously assign Sales Rep and other Seller roles from the Team page; they now cannot (the config list is a one-line change if the client wants rep agencies in).
6. Behaviour change to announce to users: an existing account is no longer added instantly. It must sign in (e-mail verified, equal to the invited address) and accept; an unverified existing account must verify first; an owner transfer to an unknown or non-member e-mail now returns a redirect-back flash instead of the old inline modal error.
7. The stuck-order backfill: orders 4, 5, 7, 9 are approved with the note "you are the only approver in your organization" without checking that this is still true today (an org may have gained approvers since). Review the dry-run list, then run `--force`; orders with `org_id` NULL are not touched.
8. Do not commit the unrelated untracked files `docs/WEEK9-LOG-REVIEW-DRAFT.md` and `.pdf` with Phase A (they predate this task).
9. Ruling 4 (session moves to the invite's org) and ruling 9 (audit-table outage blocks sole-approver self-approval) are product calls I accept; shout if either is unwanted.

### (c) What QA and the Verifier must probe

- **Mutation proofs on the new code** (beyond the plan's lists): `strcasecmp` -> `===` (case test), drop `trim`, drop `hasVerifiedEmail()` in the controller (a test already sets `withoutMiddleware`), drop `isAssignableByOrg` in `InviteAcceptController::invitationFor` and separately in `InviteController::register`, make the claim unconditional, move `assign()` before the claim, remove `selfIds` principal branch, flip `otherApprovers` filter, make the `memberOverride` unconditional in `transferOwnership`, drop the 3b check, drop the `ApprovalRoutingService::AUTO_APPROVAL_NOTE` use in the command.
- **MariaDB/staging only** (sqlite cannot show these): the invite claim under two simultaneous accepts; the backfill on a restored backup; orders whose `user_id` points to a deleted user (`orders.user_id` has no FK, but `rbac_audit_logs.user_id` and `orders.approved_by` do, so that order should land in "failed" and roll back cleanly, exit 1); strict-mode column limits with real long values; collation: MariaDB matches an existing account case-insensitively when the inviter types a different case, sqlite does not, and `strcasecmp` at accept must still match.
- **Browser walkthrough** of the whole existing-user path: invite modal for an existing account (the JS `else` branch shows the accept link and the e-mail status), mail link while signed out -> login -> lands on the accept page (the test sets `url.intended` by hand; confirm it in a real session) -> accept -> dashboard in the new org; signed in as a different account (bare 404); unverified account; SoD conflict page; used/expired link. Also the stale Platform-role invite: `register/invite/{token}` shows the form but creates no user on submit.
- **JSON 403 from `generateInvite`**: the modal JS only handles an `error` key, so a 403 (`rbac_error`/`message`) falls into the success branch and renders "undefined" as the link. It is pre-existing for the level-403 and is not reachable from the UI for Platform/Seller roles (dropdowns no longer offer them), but a crafted request exposes it; note for the Verifier, not a Phase A blocker.
- **Delegate edge**: a delegate who is also an independent approver cannot approve the order of the principal they hold a delegation from while any other approver exists (`selfIds` treats the delegate as the principal). Intended by the plan; confirm the list text and 403 are sensible.
- **Approvals page**: sole approver sees buttons for their own order and the approval is logged; approver-with-peer sees "Awaiting another approver"; other approvers see normal buttons; no Blade breakage on a page with a mix of rows.
- **Stray data**: Phase A data review queries from PLAN section 7 (Platform/Seller roles already handed out, unused Platform invites) before the client re-tests A01/A05, because stray Platform roles inflate `approverPool`.
- Re-run the full suite after any fix; my baseline check is 1593 passed / 4 failed.

## QA - Phase A

Date: 2026-10-25. Reviewer: QA/Test Engineer. Scope: the uncommitted working tree on `feature/client-audit-2-fixes` (A1-A4, 3a, 3b, 3c, 7, 8) after the Architect compliance pass (COMPLIANT). Method: independent mutation proofs on two scratch copies of the repo (working tree plus untracked files, own `vendor/` copy, no `.env`, sqlite in memory, never the real working tree), regression probes in the real repo's tests, three full-suite runs, repeat runs of every touched class. Nothing was migrated, seeded, backfilled or tinkered against the local database, no application code (app/, routes/, config/, resources/) was edited, nothing was committed.

### Result: PASS. No defect in the Phase A code. Four notes for the Architect in section 4 (F1 pre-existing, F2 a design edge, F3 docs, F4 a pre-existing UI limit).

### 1. Suite numbers (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`)

| run | result |
|---|---|
| full suite, before my additions | 1593 passed / 4 failed (the Architect's number) |
| full suite, after my additions | 1650 passed / 4 failed (+57 test cases: the new `PhaseARegressionTest` 34, additions to three of the Developer's new classes 23) |
| `php artisan test --filter=Rbac`, after | 1649 passed / 3 failed |
| `php artisan test tests/Feature/Rbac/PhaseARegressionTest.php` | 34 passed |
| touched classes run twice (RoleAssignmentGuard, OrgDropdownRoles, OwnershipDelegationMemberGuard, ExistingUserInvite, OrderSelfApproval, AutoApproval, PhaseARegression, P2CleanupBC, RfqProjectScope, UniversalAdmin, AuditFindingsRound2, AuditMembershipLog) | 635 passed, then 636 passed (one test added between the runs); the full and Rbac runs after my last edit passed the same classes again. No non-determinism seen; `AuditMembershipLogTest` passed in all five runs |

The 4 failures are exactly the known baseline, checked by name against TESTING.md, in all three runs. No new failure:
1. `Tests\Feature\ExampleTest > the application returns a successful response` (Laravel scaffold, outside the Rbac filter)
2. `Tests\Feature\Rbac\AuditMiddlewareTest > enforce mode blocks and records blocked`
3. `Tests\Feature\Rbac\SeedMatrixTest > seeds expected counts`
4. `Tests\Feature\Rbac\SeedMatrixTest > phase distribution`

### 2. Mutation proofs (independent of the Developer's)

147 mutants recorded (full list in Appendix A): every target in PLAN A1-A4, every extra target in the Architect's section (c), and mutants for 3a (drop the member rule, override unconditional, override for an unknown e-mail, `exists:` validation restored, member check against the caller, differing answers), 3b (rule dropped, caller checked, UA exemption, wrong org), 3c (strict equality instead of `strcasecmp`, no `trim`, no verified check, wrong user accepted, no used/expired check, claim unconditional, claim result ignored, no role re-validation at accept, SoD bypassed by a direct insert, assign dropped, session not switched, 403 instead of 404, response/link/mail shape), the backfill command (`org_id IS NULL`, already-approved, rejected, no-user and every-status selection, dry run writes, force dropped, dry run needing force, audit row dropped, wrong/NULL `approved_by`, unconditional UPDATE, no per-order transaction, other note, other status, never-skip, exit code, failure swallowed, `chunk()` instead of `chunkById`), `AuditLog::event`, the audit views and the approvals view, plus the CSRF field of the accept page and the two `OPEN_ROUTES` entries (removing either makes the route guard test fail).

Counts: first pass 134 mutants, 126 killed, 8 survived. After my new tests: 147 mutants, 143 killed, 4 survive, all four equivalent or unreachable:

| mutant | disposition |
|---|---|
| I6 `assign()` before the claim | equivalent. Claim and assign share one `DB::transaction`; an abort after `assign()` rolls the assignment back, and an SoD exception before the claim rolls the claim back. Only the MariaDB lock order differs (see Not tested) |
| R13 default status `pending` -> `processing` for a user without an org | unreachable. `processCheckout` calls `requireOrgLevel('procurement','S')` first, so a user with no org never reaches that branch (probe: 302 back with `rbac_denied`). The refusal is now pinned |
| B15c `CHUNK` 400 -> 1000 | equivalent (`chunkById` makes the size a tuning knob) |
| C2 accept form posts to `invite.review` instead of `invite.accept` | equivalent (GET and POST share the URI `invite/{token}/accept`) |

Six first-pass survivors were real test gaps. I wrote the tests (in the real repo), copied them to the scratch copy and showed each kills its mutant:
- I18 (`generateInvite` answer leaks `exists: true/false`; the old shape test compared only the key names) -> `ExistingUserInviteTest::test_the_answers_for_an_existing_and_a_new_address_differ_only_in_the_link`
- I21 (the returned link for a NEW address was the accept link, which would send a new hire to a login page; nothing asserted the JSON link for a new address) -> `ExistingUserInviteTest::test_the_returned_link_is_the_register_link_for_a_new_address_and_the_accept_link_for_an_existing_account`
- S7 (`selfIds` without the caller: a delegate could approve their own order through the delegation, a real bypass) -> `OrderSelfApprovalTest::test_a_delegate_cannot_decide_their_own_order_through_the_delegation` and `..._a_delegate_of_the_sole_approver_cannot_decide_their_own_order_either`
- S9 (`self_approved` written after the transaction: an audit outage would leave the order approved without its row; Architect ruling 9 accepted fail-closed but nothing tested it) -> `OrderSelfApprovalTest::test_an_audit_failure_leaves_the_sole_approvers_own_order_pending`, plus `..._an_audit_outage_does_not_block_an_ordinary_approval`
- R19 (`AuditLog::event` ignored an explicit `$method`; the old test passed `'CLI'`, equal to the derived value) -> `AutoApprovalTest::test_event_explicit_method_overrides_the_derived_one`
- B16 (each of `approved_by` / `rejected_by` missing was never tested, only `approval_note`) -> `AutoApprovalTest::test_exit_2_for_each_missing_approval_column`

Three more gaps found while reading, each proved by a mutant: `chunk()`-with-offset would skip rows after the first 400 -> `AutoApprovalTest::test_a_real_run_approves_every_stuck_order_even_when_there_are_more_than_one_chunk` (430 orders); removing `@csrf` from the accept form passed every test (CSRF is off in tests) -> `ExistingUserInviteTest::test_the_review_page_posts_to_the_accept_route_with_a_csrf_token`; the config-driven seller org-type list could be hard-coded without a test noticing -> `PhaseARegressionTest::test_the_seller_role_rule_follows_the_configured_org_type_list`.

Harness note, for honesty: my first run of the view mutants V5-V10 was contaminated by Laravel's compiled-view cache (the restored source kept an older mtime than the compiled mutant), so they were "killed" by a stale compile. I cleared `storage/framework/views` before and after every mutant and re-ran V1-V10: all killed by their own test. The same clearing is in force for every other result.

### 3. Tests added (all under `tests/Feature/Rbac/`)

- New `PhaseARegressionTest.php` (34 test cases): owner assigns every role that was assignable before (all non-Platform, non-Seller roles in a buyer org; all non-Platform roles, Sales Rep included, in a manufacturer org; audit and enforce); assign answer keeps its shape and peel-off hint; SoD refuses BOTH directions of EVERY active `sod_conflict_rules` row on the org screen (HTTP 422, no row); the platform-admin screen still assigns a Platform role and a Seller role to a non-member of any org; a brand-new user invited by e-mail still registers through `/register/invite/{token}` end to end (mail link, JSON link, form, role, `performed_by`, invite used); a manufacturer owner can still invite into a Seller role; signup still creates the organization and the owner bundle (buyer 4 roles, small manufacturer 3 roles, larger team owner only) and sends the verification mail once; ownership transfer to a member gives the new owner owner powers and removes them from the old owner; owner delegates to a member who then acts with the owner's authority, until revoked; multi-approver org (checkout and RFQ convert, audit and enforce): non-approver order -> `pending_approval`, listed for the approvers with buttons, approver A approves (`processing`, `approved_by` A, no `auto_approved`/`self_approved` row), approver B cannot approve it again; a non-submitter approver can reject; the approvals queue lists only this org's `pending_approval` orders (not legacy `pending`, not `processing`, not another org); checkout with no approver is still refused; seller org-type rule follows the config list (rep agency refused by default, allowed once the slug is added); the org and both admin audit pages still label old rows (`would_block`, `blocked`, `allowed_universal_admin`, `enforcement_changed`) with the OLD labels and show the reason text.
- Added to three of the Developer's new classes (no existing test changed or removed; counts are test cases, data-provider sets included): `ExistingUserInviteTest` +9 (answer parity, link per address type, SoD in the other direction at accept, SoD is per organization at accept, CSRF/form action), `OrderSelfApprovalTest` +8 (delegate own order x2, audit failure keeps the order pending, audit outage does not block an ordinary approval), `AutoApprovalTest` +6 (explicit method, no-org user cannot check out, missing column x3, more than one chunk).
- Regression-pin teeth: 16 mutants aimed at the regression tests (RG1-RG16, e.g. Cross-Functional or Procurement roles no longer assignable, SoD skipped in `assign()`, over-blocking guard, auto-approve for multi-approver, relabelled old audit badges, wrong invite link, transfer/delegation refusing a legitimate member): 14 killed by `PhaseARegressionTest`, the other two are over-block probes killed by their natural owners (`RoleAssignmentGuardTest`, `OrderSelfApprovalTest`).

### 4. Findings and observations for the Architect (no code was changed; strict mode: route through PLAN.md)

F1. PRE-EXISTING, outside Phase A, but it touches 3c. `InviteController::register` creates the user with `'email_verified_at' => now()`, but `email_verified_at` is not in `User::$fillable`, so the value is silently dropped. Probe (scratch copy only): after registering through an invite link the user has `email_verified_at = NULL`, `hasVerifiedEmail() = false`, and `GET user-dashboard` answers 302 to `/email/verify`. No `Registered` event fires, so no verification mail is sent. Same in the first commit (`2ee1a55`), unchanged by Phase A. Effect on 3c: a person who joined through an invite link and never verified cannot later accept an existing-account invite (`verified` middleware, then the 404 rule), and the new-hire experience is already a dead end today. Not a Phase A blocker. Suggest a separate item (fix: `forceFill` or add the attribute to `$fillable`, or send the verification notification).

F2. DESIGN EDGE of A3 (the Architect already flagged the delegate case; this is the concrete consequence). Two-approver organization where approver P has an active delegation to approver D: P's own order cannot be decided by anyone. P gets 403 ("another approver is available"); D gets the same 403 because `selfIds` treats D as P (probe: both 403 with the message "You cannot approve your own order while another approver is available."). It resolves when the delegation is revoked or expires, or a third approver exists. The message is misleading for D, who is an approver in his own right. Options: accept and document, or treat a delegate who is in the approver pool in his own right as "another approver" for the principal's orders (small change to `guardOwnOrder`/`selfIds`; needs a PLAN.md amendment first).

F3. Docs, for the Architect (I may only edit tests/): `TESTING.md` should list `PhaseARegressionTest` and give the new baseline `1650 passed / 4 failed` (the same 4); the Phase A section there says 1593. The Architect's CLAUDE.md sentence about `OPEN_ROUTES` is still open. TESTING.md also names the first known failure "enforce mode blocks and logs"; the real test name is "enforce mode blocks and records blocked" (same test).

F4. Confirmed by reading the JS (not a Phase A change): the invite modal handles only an `error` key, so a JSON 403 from `generateInvite` (for example a crafted request with a Platform role) falls into the success branch and renders "undefined" as the link. Not reachable from the UI now that the dropdowns offer assignable roles only.

No other defects. The attempted bypasses all held: no other site grants a role to a non-member (the only callers of `assign()` / `UserOrgRole` creation are assignRole, invite accept, invite register, platform-admin assign, onboarding and the ownership transfer; the only delegation creation site is `DelegationController::store`); a user with no organization at all can accept in enforce mode (the route is unmapped, probe: GET 200, POST 302, role row created); the audit pack matches the code (`route:list --json` has 293 routes and differs from `docs/client-audit-pack/route-list.json` only in the environment-dependent `livewire.min.js` / `livewire.js` entry; the map copy is byte-identical).

### 5. Not tested (cannot be proven on in-memory sqlite or without a browser)

- MariaDB concurrency: two simultaneous accepts of the same invite (row lock on `UPDATE ... WHERE used_at IS NULL`, affected-row count), and the lock order if `assign()` ran before the claim (mutant I6); the tests simulate the race through a `DB::listen` hook, which proves the code path, not InnoDB.
- The backfill on a restored MariaDB backup (dry run first, then `--force`); an order whose `user_id` points to a deleted user (FKs on `rbac_audit_logs.user_id` and `orders.approved_by` are not in the sqlite stubs); strict-mode limits with real long values (`reason` / `route_uri` are cut at 255 in code, sqlite does not enforce lengths); enum behaviour of `orders.status` ('processing' is in the enum per migration `2026_09_01_000002`, sqlite stores any string).
- Collation: MariaDB matches an existing account case-insensitively when the inviter types another case, sqlite does not; only `strcasecmp` at accept is tested.
- Real browser flows: mail link while signed out -> login -> accept page -> dashboard in the new org (the test sets `url.intended` by hand), the invite modal JS rendering the new response for an existing account, the approvals page with a mix of rows (HTML structure is asserted, layout is not), CSRF in a real session (only the hidden field and the form action are asserted; the middleware is off in tests), the Blade views compiled on the production PHP version.
- Real mail delivery (invite mail for new and existing accounts, verification mail), spam or greylisting; `Mail::fake()` / `Notification::fake()` only.
- `--force` in a real production process (tested by switching `$app['env']`), file permissions of `storage/app/backfill/orders-auto-approval/`, the Phase A data-review SQL of PLAN section 7 (MariaDB syntax), orders 4, 5, 7 and 9 on the real data.
- Production is reported to run in audit mode with per-org enforcement; both modes are tested, per-org enforcement mixes were not added by me.

### Appendix A - mutation log (scratch copy, `phpunit --filter=<class(es)> --stop-on-failure`, view cache cleared around every mutant)

The "killed by" column is the first failing test (the run stops at the first failure).

| mutant | result | killed by / disposition |
|---|---|---|
| A1-1 delete membership check (assignRole) | KILLED | RoleAssignmentGuardTest::test_a_user_who_owns_another_org_and_has_no_row_here_is_refused |
| A1-2 drop is_active in isActiveMember | KILLED | OwnershipDelegationMemberGuardTest::test_ownership_is_refused_for_anyone_who_is_not_an_active_member |
| A1-3a isActiveMember ignores org | KILLED | OwnershipDelegationMemberGuardTest::test_ownership_is_refused_for_anyone_who_is_not_an_active_member |
| A1-3b assignRole checks the actor instead of the target | KILLED | RoleAssignmentGuardTest::test_a_user_who_owns_another_org_and_has_no_row_here_is_refused |
| A1-3c assignRole checks membership in the wrong org id | KILLED | RoleAssignmentGuardTest::test_owner_assigns_a_role_to_an_active_member |
| A1-4 universal-admin branch unconditional (non-member) | KILLED | RoleAssignmentGuardTest::test_a_user_who_owns_another_org_and_has_no_row_here_is_refused |
| A1-5 non-member bypass not logged | KILLED | RoleAssignmentGuardTest::test_a_universal_admin_may_assign_to_a_non_member_and_it_is_logged |
| A1-6 not-a-member message changed | KILLED | RoleAssignmentGuardTest::test_a_user_who_owns_another_org_and_has_no_row_here_is_refused |
| A2-1 remove Platform exclusion | KILLED | RoleAssignmentGuardTest::test_every_platform_role_is_refused_on_assign |
| A2-2 remove Seller exclusion | KILLED | RoleAssignmentGuardTest::test_a_seller_role_is_refused_in_a_buyer_org_on_assign_and_invite |
| A2-3 flip org-type test | KILLED | RoleAssignmentGuardTest::test_a_seller_role_is_refused_in_a_buyer_org_on_assign_and_invite |
| A2-3b seller allowed only for manufacturer (distributors excluded) | KILLED | RoleAssignmentGuardTest::test_a_seller_role_is_allowed_in_manufacturer_and_distributor_orgs |
| A2-3c seller allowed for every org type that is not buyer-ish (null/any string) | KILLED | RoleAssignmentGuardTest::test_a_seller_role_is_refused_in_a_buyer_org_on_assign_and_invite |
| A2-4 generateInvite skips requireAssignableRole | KILLED | RoleAssignmentGuardTest::test_every_platform_role_is_refused_on_invite_for_a_new_and_an_existing_account |
| A2-5 assignRole skips requireAssignableRole | KILLED | RoleAssignmentGuardTest::test_every_platform_role_is_refused_on_assign |
| A2-6 InviteController::register skips isAssignableByOrg | KILLED | RoleAssignmentGuardTest::test_a_stored_platform_role_invite_is_refused_at_accept |
| A2-7 category guard inside assign() | KILLED | RoleAssignmentGuardTest::test_a_universal_admin_may_assign_and_invite_restricted_roles_and_it_is_logged |
| A2-8 drop the filter in assignableRoles (all dropdowns) | KILLED | OrgDropdownRolesTest::test_a_buyer_org_is_offered_neither_platform_nor_seller_roles |
| A2-8a index dropdown unfiltered | KILLED | OrgDropdownRolesTest::test_a_buyer_org_is_offered_neither_platform_nor_seller_roles |
| A2-8b overview coverage list unfiltered | KILLED | OrgDropdownRolesTest::test_a_buyer_org_is_offered_neither_platform_nor_seller_roles |
| A2-8c overview invite dropdown unfiltered | KILLED | OrgDropdownRolesTest::test_a_buyer_org_is_offered_neither_platform_nor_seller_roles |
| A2-8d rolesList unfiltered | KILLED | OrgDropdownRolesTest::test_a_buyer_org_is_offered_neither_platform_nor_seller_roles |
| A2-9 universal-admin branch unconditional (restricted role) | KILLED | RoleAssignmentGuardTest::test_every_platform_role_is_refused_on_assign |
| A2-10 restricted-role bypass not logged | KILLED | RoleAssignmentGuardTest::test_a_universal_admin_may_assign_and_invite_restricted_roles_and_it_is_logged |
| A2-11 swap Platform/Seller messages | KILLED | RoleAssignmentGuardTest::test_every_platform_role_is_refused_on_assign |
| A2-12 accept page does not re-validate the role | KILLED | ExistingUserInviteTest::test_the_role_is_re_validated_at_accept |
| T1 drop the active-member rule on transfer | KILLED | OwnershipDelegationMemberGuardTest::test_ownership_is_refused_for_anyone_who_is_not_an_active_member |
| T2 memberOverride unconditional (anyone may hand to a non-member) | KILLED | OwnershipDelegationMemberGuardTest::test_ownership_is_refused_for_anyone_who_is_not_an_active_member |
| T3 restore exists:users,email validation | KILLED | OwnershipDelegationMemberGuardTest::test_an_unknown_e_mail_is_indistinguishable_from_a_non_member |
| T4 universal admin gets a pass for unknown e-mail | KILLED | OwnershipDelegationMemberGuardTest::test_a_universal_admin_gets_no_bypass_for_an_unknown_e_mail |
| T5 transfer non-member bypass not logged | KILLED | OwnershipDelegationMemberGuardTest::test_a_universal_admin_may_hand_ownership_to_a_non_member_and_it_is_logged |
| T6 member check uses the caller, not the new owner | KILLED | OwnershipDelegationMemberGuardTest::test_ownership_is_refused_for_anyone_who_is_not_an_active_member |
| T7 unknown e-mail answers differently from non-member | KILLED | OwnershipDelegationMemberGuardTest::test_an_unknown_e_mail_is_indistinguishable_from_a_non_member |
| T8 non-member 403 message differs | KILLED | OwnershipDelegationMemberGuardTest::test_ownership_is_refused_for_anyone_who_is_not_an_active_member |
| D1 drop the active-member check on delegation | KILLED | OwnershipDelegationMemberGuardTest::test_a_delegation_to_a_non_member_is_refused_and_writes_nothing |
| D2 delegation check uses the caller | KILLED | OwnershipDelegationMemberGuardTest::test_a_delegation_to_a_non_member_is_refused_and_writes_nothing |
| D3 universal admin exempt from the delegation rule | KILLED | OwnershipDelegationMemberGuardTest::test_a_universal_admin_gets_no_member_exemption_for_delegations |
| D4 delegation check in wrong org | KILLED | OwnershipDelegationMemberGuardTest::test_the_owner_can_delegate_to_an_active_member |
| I1 strcasecmp -> strict equality | KILLED | ExistingUserInviteTest::test_the_e_mail_match_ignores_case_and_surrounding_spaces |
| I2 drop trim | KILLED | ExistingUserInviteTest::test_the_e_mail_match_ignores_case_and_surrounding_spaces |
| I3 drop hasVerifiedEmail | KILLED | ExistingUserInviteTest::test_the_controller_itself_refuses_an_unverified_account_even_without_the_route_middleware |
| I4 drop e-mail equality (wrong user accepted) | KILLED | ExistingUserInviteTest::test_another_signed_in_account_gets_a_404_and_changes_nothing |
| I5 claim unconditional (drop whereNull used_at) | KILLED | ExistingUserInviteTest::test_an_invite_used_by_a_concurrent_request_between_load_and_claim_assigns_nothing |
| I6 assign before the claim | SURVIVED (first pass) | equivalent: claim and assign share one DB::transaction, an abort after assign rolls the assignment back, so the order of the two statements is not observable on sqlite (differs only in MariaDB lock order) |
| I7 ignore the claim result | KILLED | ExistingUserInviteTest::test_an_invite_used_by_a_concurrent_request_between_load_and_claim_assigns_nothing |
| I8 drop isUsed check | KILLED | ExistingUserInviteTest::test_an_invite_is_one_time |
| I9 drop isExpired check | KILLED | ExistingUserInviteTest::test_an_expired_invite_is_refused_for_the_right_account |
| I10 assign dropped (invite consumed, nothing granted) | KILLED | ExistingUserInviteTest::test_the_invited_account_reviews_and_accepts_and_gets_the_role |
| I11 SoD skipped at accept (direct insert) | KILLED | ExistingUserInviteTest::test_the_invited_account_reviews_and_accepts_and_gets_the_role |
| I12 session org not switched | KILLED | ExistingUserInviteTest::test_the_invited_account_reviews_and_accepts_and_gets_the_role |
| I13 wrong user gets 403 instead of 404 | KILLED | ExistingUserInviteTest::test_another_signed_in_account_gets_a_404_and_changes_nothing |
| I14 assigner is the invitee, not the inviter | KILLED | ExistingUserInviteTest::test_the_invited_account_reviews_and_accepts_and_gets_the_role |
| I15 SoD conflict not caught (500) | KILLED | ExistingUserInviteTest::test_separation_of_duties_still_applies_and_leaves_the_invite_unused |
| I16 invalid-invite page returns 200 | KILLED | ExistingUserInviteTest::test_an_invite_is_one_time |
| I17 generateInvite: existing account flag ignored | KILLED | ExistingUserInviteTest::test_inviting_an_existing_account_creates_an_invite_and_assigns_nothing |
| I18 generateInvite: response reveals account | SURVIVED (first pass) | gap closed: ExistingUserInviteTest::test_the_answers_for_an_existing_and_a_new_address_differ_only_in_the_link |
| I19 generateInvite: mail flag dropped | KILLED | ExistingUserInviteTest::test_the_existing_account_mail_points_to_the_accept_page_and_new_mail_is_unchanged |
| I20 generateInvite: link for existing account is the register link | KILLED | ExistingUserInviteTest::test_inviting_an_existing_account_creates_an_invite_and_assigns_nothing |
| I21 generateInvite: new account link is the accept link | SURVIVED (first pass) | gap closed: ExistingUserInviteTest::test_the_returned_link_is_the_register_link_for_a_new_address_and_the_accept_link_for_an_existing_account |
| S1 delete the guard (approve) | KILLED | OrderSelfApprovalTest::test_an_approver_cannot_approve_their_own_order_while_another_approver_exists |
| S2 skip the guard on reject | KILLED | OrderSelfApprovalTest::test_an_approver_cannot_reject_their_own_order_while_another_approver_exists |
| S3 do not subtract the submitter from the pool | KILLED | OrderSelfApprovalTest::test_the_sole_approver_may_approve_their_own_order_and_it_is_logged |
| S4 flip otherApprovers filter | KILLED | OrderSelfApprovalTest::test_the_sole_approver_may_approve_their_own_order_and_it_is_logged |
| S5 invert own-order test | KILLED | OrderSelfApprovalTest::test_an_approver_cannot_approve_their_own_order_while_another_approver_exists |
| S6 ignore the delegate/principal branch | KILLED | OrderSelfApprovalTest::test_a_delegate_of_the_submitter_cannot_approve_or_reject_the_principals_order |
| S7 principal only (drop the caller) | SURVIVED (first pass) | gap closed: OrderSelfApprovalTest::test_a_delegate_cannot_decide_their_own_order_through_the_delegation (and the sole-approver variant) |
| S8 drop the self_approved row | KILLED | OrderSelfApprovalTest::test_the_sole_approver_may_approve_their_own_order_and_it_is_logged |
| S9 self_approved written after the transaction (not atomic) | SURVIVED (first pass) | gap closed: OrderSelfApprovalTest::test_an_audit_failure_leaves_the_sole_approvers_own_order_pending |
| S10 remove distinct() in approverPool | KILLED | OrderSelfApprovalTest::test_a_submitter_holding_several_approver_roles_is_still_one_person |
| S11 invert other-approver test in guard | KILLED | OrderSelfApprovalTest::test_an_approver_cannot_approve_their_own_order_while_another_approver_exists |
| S12 guard returns true for somebody else's order | KILLED | OrderSelfApprovalTest::test_the_other_approver_can_still_approve_and_reject_it |
| S13a list flags own order without checking another approver | KILLED | OrderSelfApprovalTest::test_the_list_flags_nothing_for_the_sole_approver_or_for_a_plain_approver |
| S13b list flags every order | KILLED | OrderSelfApprovalTest::test_the_list_flags_own_orders_while_another_approver_exists |
| S14 guard answers 422 instead of 403 | KILLED | OrderSelfApprovalTest::test_an_approver_cannot_approve_their_own_order_while_another_approver_exists |
| S15 reject uses the approve verb | KILLED | OrderSelfApprovalTest::test_an_approver_cannot_reject_their_own_order_while_another_approver_exists |
| S16 approverPool counts inactive rows | KILLED | OrderSelfApprovalTest::test_the_sole_approver_may_approve_their_own_order_and_it_is_logged |
| S17 approverPool ignores the org | KILLED | OrderSelfApprovalTest::test_an_approver_in_another_org_does_not_count_as_another_approver |
| S18 reject by sole approver also logged as self_approved | KILLED | OrderSelfApprovalTest::test_the_sole_approver_may_reject_their_own_order_without_a_log_row |
| S19 approve: own-order check against caller only if sole (never self_approved row for delegate) | KILLED | OrderSelfApprovalTest::test_a_delegate_of_the_sole_approver_may_decide_the_principals_order |
| R1 restore pending | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R2 drop approved_by | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R3 drop the note | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R4 drop the audit call (checkout) | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R5 drop the audit call (rfq) | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R6 checkout audit after commit | KILLED | AutoApprovalTest::test_an_audit_failure_rolls_the_checkout_order_back |
| R7 rfq audit after the transaction | KILLED | AutoApprovalTest::test_an_audit_failure_rolls_the_rfq_conversion_back |
| R8 non-auto routing also gets processing | KILLED | AutoApprovalTest::test_an_approver_with_a_second_approver_goes_to_pending_approval_without_a_row |
| R9 checkout audit for every routed order | KILLED | AutoApprovalTest::test_an_approver_with_a_second_approver_goes_to_pending_approval_without_a_row |
| R10 rfq audit for every order | KILLED | AutoApprovalTest::test_an_approver_with_a_second_approver_goes_to_pending_approval_without_a_row |
| R11 route(): requester among several approvers auto-approves | KILLED | AutoApprovalTest::test_an_approver_with_a_second_approver_goes_to_pending_approval_without_a_row |
| R12a audit reason changed | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R12b audit org dropped | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R12c audit user changed | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R13 guest/no-org default status | SURVIVED (first pass) | unreachable: processCheckout calls requireOrgLevel(procurement,S) first, so a user without an org never reaches the default status branch; refusal pinned by AutoApprovalTest::test_a_user_without_an_organization_cannot_place_an_order_and_nothing_is_auto_approved |
| R14 checkout ignores orderState | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R15 rfq ignores initial state | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row |
| R16 checkout message keys on a constant | KILLED | AutoApprovalTest::test_the_checkout_messages_stay_as_they_were |
| R17 AuditLog::event no reason limit | KILLED | AutoApprovalTest::test_event_outside_a_request_records_cli_and_artisan |
| R18 AuditLog::event cli detection broken | KILLED | AutoApprovalTest::test_event_outside_a_request_records_cli_and_artisan |
| R19 AuditLog::event method ignores explicit | SURVIVED (first pass) | gap closed: AutoApprovalTest::test_event_explicit_method_overrides_the_derived_one |
| R20 AuditLog::event route_uri ignores explicit | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B1 select org_id IS NULL | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B1b drop org_id condition | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B2a select already-approved rows (drop approved_by null) | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B2b select rejected rows | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B2c select rows without user | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B2d select every status | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B3 --dry-run writes | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B4 force check dropped | KILLED | AutoApprovalTest::test_production_needs_force_for_a_real_run_but_not_for_a_dry_run |
| B4b dry run needs --force in production | KILLED | AutoApprovalTest::test_production_needs_force_for_a_real_run_but_not_for_a_dry_run |
| B5 audit row dropped | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B6a wrong approved_by | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B6b approved_by null | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B7 unconditional update | KILLED | AutoApprovalTest::test_an_order_that_changes_between_selection_and_write_is_skipped |
| B8 no transaction per order | KILLED | AutoApprovalTest::test_an_audit_failure_rolls_back_that_order_only_and_the_run_reports_it |
| B9 note differs from the shared constant | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B10 status set to something else | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B11 skip detection never fires | KILLED | AutoApprovalTest::test_an_order_that_changes_between_selection_and_write_is_skipped |
| B12 exit code always 0 | KILLED | AutoApprovalTest::test_an_audit_failure_rolls_back_that_order_only_and_the_run_reports_it |
| B13 audit reason differs | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B14 audit method/route taken from request | KILLED | AutoApprovalTest::test_a_real_run_approves_only_the_selected_set_with_a_note_and_an_audit_row_each |
| B15 chunk instead of chunkById | KILLED | AutoApprovalTest::test_dry_run_lists_the_stuck_orders_and_writes_nothing_but_the_report |
| B16 missing columns not refused | SURVIVED (first pass) | gap closed: AutoApprovalTest::test_exit_2_for_each_missing_approval_column (approved_by / rejected_by / approval_note) |
| B17 failure not reported (swallowed as approved) | KILLED | AutoApprovalTest::test_an_audit_failure_rolls_back_that_order_only_and_the_run_reports_it |
| V1 approvals: awaiting flag ignored | KILLED | OrderSelfApprovalTest::test_the_list_flags_own_orders_while_another_approver_exists |
| V2 org audit: auto_approved label removed | KILLED | AutoApprovalTest::test_the_org_audit_page_labels_both_outcomes_and_shows_the_details |
| V3 org audit: self_approved label removed | KILLED | AutoApprovalTest::test_the_org_audit_page_labels_both_outcomes_and_shows_the_details |
| V4 org audit: details column shows nothing | KILLED | AutoApprovalTest::test_the_org_audit_page_labels_both_outcomes_and_shows_the_details |
| V5 admin audit-logs: auto_approved badge removed | KILLED | AutoApprovalTest::test_the_admin_audit_pages_label_both_outcomes_and_never_call_them_would_block |
| V6 admin audit-logs: self_approved badge removed | KILLED | AutoApprovalTest::test_the_admin_audit_pages_label_both_outcomes_and_never_call_them_would_block |
| V7 admin audit-logs: auto_approved filter option removed | KILLED | AutoApprovalTest::test_the_admin_audit_pages_label_both_outcomes_and_never_call_them_would_block |
| V8 admin audit-logs: self_approved filter option removed | KILLED | AutoApprovalTest::test_the_admin_audit_pages_label_both_outcomes_and_never_call_them_would_block |
| V9 admin enforcement: auto_approved badge removed | KILLED | AutoApprovalTest::test_the_admin_audit_pages_label_both_outcomes_and_never_call_them_would_block |
| V10 admin enforcement: self_approved badge removed | KILLED | AutoApprovalTest::test_the_admin_audit_pages_label_both_outcomes_and_never_call_them_would_block |
| S9b audit write for every approval (not only own sole) | KILLED | OrderSelfApprovalTest::test_the_other_approver_can_still_approve_and_reject_it |
| B16a approved_by column check dropped | KILLED | AutoApprovalTest::test_exit_2_for_each_missing_approval_column |
| B16b rejected_by column check dropped | KILLED | AutoApprovalTest::test_exit_2_for_each_missing_approval_column |
| B16c approval_note column check dropped | KILLED | AutoApprovalTest::test_exit_2_when_the_approval_columns_are_missing_and_nothing_is_written |
| B15b orderBy(id)->chunk() instead of chunkById | KILLED | AutoApprovalTest::test_a_real_run_approves_every_stuck_order_even_when_there_are_more_than_one_chunk (new; no earlier test could see it, B15 plain chunk() is killed only by a Laravel RuntimeException) |
| B15c CHUNK constant 400 -> 1000 | SURVIVED | equivalent: chunkById makes the chunk size a tuning knob, results are identical |
| A2-7b guard inside assign(): admin pin only | KILLED | RoleAssignmentGuardTest::test_the_platform_admin_screen_still_assigns_a_platform_role |
| S10b remove distinct() in approverPool, AutoApprovalTest only | KILLED | AutoApprovalTest::test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row (and ::test_a_solo_user_with_four_roles_is_one_approver) |
| X1 OPEN_ROUTES entry GET invite/{token}/accept removed | KILLED | AuditFindingsRound2Test::test_guard_no_authenticated_route_is_silently_unmapped |
| X2 OPEN_ROUTES entry POST invite/{token}/accept removed | KILLED | AuditFindingsRound2Test::test_guard_no_authenticated_route_is_silently_unmapped |
| C1 invite-accept view: @csrf removed | KILLED | ExistingUserInviteTest::test_the_review_page_posts_to_the_accept_route_with_a_csrf_token (new) |
| C2 invite-accept view: form posts to invite.review instead of invite.accept | SURVIVED | equivalent: both named routes have the same URI (GET and POST invite/{token}/accept) |
| H1 hard-coded org type list in isAssignableByOrg | KILLED | PhaseARegressionTest::test_the_seller_role_rule_follows_the_configured_org_type_list |

Regression-pin mutants RG1-RG16 (aimed at `PhaseARegressionTest`): RG1 Cross-Functional roles refused, RG2 Procurement roles refused, RG3 Seller roles refused in manufacturer orgs, RG4 SoD skipped in `assign()`, RG6 guard blocks every approver, RG7 auto-approve for a multi-approver org, RG8 old `Would Block` label changed, RG9 admin `Denied` label changed, RG10 admin enforcement `Would Block` lost, RG11 register link replaced by the accept link, RG12 details column lost, RG13 transfer refuses a legitimate member, RG14 delegation refuses a legitimate member, RG15 invite register refuses every role: all KILLED by `PhaseARegressionTest`. RG5 (assignRole also demands the actor to be a member) is killed by `RoleAssignmentGuardTest::test_a_universal_admin_assigning_to_a_member_leaves_no_non_member_row`, RG16 (guard aborts for the sole approver too) by `OrderSelfApprovalTest::test_the_sole_approver_may_approve_their_own_order_and_it_is_logged`.

## Verifier - Phase A

Date: 2026-10-25. Reviewer: Verifier (security + RBAC), independent of the Developer, Architect and QA. Scope: the uncommitted working tree on `feature/client-audit-2-fixes` against PLAN.md (A1-A4, 3a, 3b, 3c, task 7, task 8). Method: full read of the diff, of the 3 new app files, the new view and the command; line-by-line trace of every `assign(` / `UserOrgRole` / `OrgInvite` write site; read of `CurrentOrg`, `PermissionService`, `DelegationService`, `RbacAudit`, `DenialRecorder`, the matrix seed and the migrations that matter; throw-away probe tests that live OUTSIDE the repo (`/private/tmp/claude-501/-Users-asad-Documents-QX-wisselbanken/9a30963d-09dd-4e0f-a88e-ae708d9e58db/scratchpad/VerifierProbeTest.php`, run with `DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit <file>`). No application code, test, config, route or env file was edited. The real DB was never touched.

Housekeeping, for honesty: my first probe run executed `orders:backfill-auto-approval --dry-run` without redirecting storage, so it wrote `storage/app/backfill/orders-auto-approval/20261024_205342/` (synthetic ids from the in-memory sqlite, two `would_approve` rows). I deleted exactly that directory; `storage/app/backfill/` again contains only `projects-entity`, as before. Later probes redirect storage to the scratchpad.

Suite (run by me, in-memory sqlite): full suite **1650 passed / 4 failed** (17330 assertions, 182 s). The 4 are exactly ExampleTest, AuditMiddlewareTest "enforce mode blocks and records blocked", SeedMatrixTest "seeds expected counts" and "phase distribution". No new failure. The new classes were included in that run (QA already ran each class on its own).

### VERDICT: FIX REQUIRED (minor), routed to the Architect per strict mode

I found NO defect in the guard logic of O10, S03, A05, A01/A07, 3a, 3b or 3c: every bypass I tried against them held, in audit and in enforce mode (list in section "Attacks that held"). The items that need action before commit are one hardening of the new invite flow (A1) and two documentation corrections (A2, A3). Separately, two PRE-EXISTING defects (C1 CRITICAL, C2 HIGH) are not regressions but they decide whether the client's re-test of S03/A05 will pass, so the human should read C1 and C2 before reporting "S03 closed" to the client.

Counts: CRITICAL 1 (pre-existing), HIGH 1 (pre-existing), MEDIUM 3 (A1 new surface, B1 business rule, C3 pre-existing), LOW 10, INFO 4. Defects introduced by this diff in the targeted fixes: 0.

Ranked index (details below): CRITICAL C1. HIGH C2. MEDIUM A1, B1, C3. LOW A2, A3, B2, B3, B4, B6, B7, C4, C5, C6. INFO B5, B8, C7, C8.

---

### (A) Must fix before commit (route to Architect, PLAN.md first; the Developer does not patch silently)

**A1 - MEDIUM - an unused invite outlives its inviter's authority, and nobody can see or revoke it** (new surface created by 3c; the same hole already existed for new-hire register links)
- Where: `app/Http/Controllers/Auth/InviteAcceptController.php:34-40` (accept) and `:55-72` (`invitationFor`) never look at `invited_by`; `app/Http/Controllers/Frontend/OrgAdminController.php:437-445` creates the row; there is no screen that lists or revokes pending invites (grep of `resources/views/user/org-admin`: none). The only revoke is re-inviting the same e-mail + org, or deleting the org.
- Scenario (reproduced, probe T12, audit and enforce): an Organization Admin invites the account `accomplice@...` (existing, verified) as `organization_owner` -> 200 + invite row. The inviter is then removed from the org (all `user_org_roles` rows inactive). The accomplice opens the link and POSTs accept -> 302, and now holds an ACTIVE `organization_owner` row in the org. The org's real owners have no way to see that this invite exists. Window: 7 days.
- Regression? New for existing accounts: before this diff an existing account was attached immediately by someone who was authorised at that moment, so nothing stayed pending. For new e-mails (register link) the same persistence existed before (`InviteController::register` also ignores `invited_by`). Architect ruling 5 accepted this and asked for a follow-up; I rate it as a fix-now because 3c made invite-then-accept the ONLY path for existing accounts, so a revoked admin's leftovers are now the way back in.
- Suggested direction for the PLAN (not a patch): at accept (and in `InviteController::register` for parity) treat the invite as invalid unless `invited_by` is still an active member of `org_id` and still passes `user_management` F; keep the generic "invite invalid" page so nothing leaks. Optional follow-up: a pending-invites list with revoke on the Team page. Needs a test per path (inviter removed, inviter demoted, inviter still valid) and mutation proofs.

**A2 - LOW - TESTING.md is stale** (confirms QA F3). It gives the baseline as 1593 passed / 4 failed; the tree is 1650 / 4. It lists six new classes and omits `PhaseARegressionTest` (34 cases). It names the known failure "enforce mode blocks and logs"; the real test name is "enforce mode blocks and records blocked".

**A3 - LOW - ARCHITECTURE.md section 12 does not record what Phase A learned** (docs only). (i) Open item 4 (MariaDB-only behaviour not proven by sqlite) should also name the invite claim under two simultaneous accepts, the backfill on a restored backup, and an order whose `user_id` has no matching user (`orders.user_id` has no FK; `orders.approved_by` and `rbac_audit_logs.user_id` do, so the command reports that order as `failed`, exit 1; I reproduced that with a ghost submitter id, it stayed `pending` and the others were approved). (ii) The "found, same class, NOT planned" item of PLAN section 8 (global role editing, C1 below) exists only in PLAN.md, which is untracked; it should become a numbered open item in section 12 so it is not lost.

---

### (B) Decisions for the human (business rules and conventions)

**B1 - MEDIUM - an Owner can make themselves the "sole approver" and then approve their own order** (business-rule issue the client should know about; describe, not fix)
- The Owner role itself carries `approval_authority` A, so every org's Owner is in `approverPool`. Reproduced (probe T3, both modes): Owner O and approver X exist. O submits an order -> `pending_approval`. O tries to approve it -> 403 "cannot approve your own order while another approver is available". O deactivates X's approver role on the Team page (`DELETE org-admin/roles/{id}`, allowed for `user_management` F). O approves again -> 302, order `processing`, one `self_approved` row. The same two steps make O the sole approver for the NEXT order, which `auto_approved` at checkout.
- It is logged (`self_approved` / `auto_approved` on the org Enforcement tab) and it is the same power the Owner had before (before this diff nothing stopped O approving anything). So it is not a regression, but A05 ("approver approved their own order while another approver existed") can still be reproduced by an Owner in two clicks, and the guard is only as strong as the org's approver roster. Options for the client: accept (logged); block removing the last other approver while orders await approval; send a notification on `self_approved`; or require a platform admin to confirm. Not planned, not coded.
- Related, same cause: approvers who cannot act (an invited approver who is still behind the e-mail-verification wall, see C3, or someone on leave) still count as "another approver", so the Owner's own orders wait for them. That is the intended trade-off of the rule.

**B2 - LOW - the two accept routes have no `route_permission_map.php` entry** (CLAUDE.md says every new route gets one). The Architect's reasoning (4.1) is sound: a map entry is checked against the caller's current org and the invitee has no role there, and in enforce mode a no-org user would be redirected with `no_org`. The routes are listed in `AuditFindingsRound2Test::OPEN_ROUTES` with reasons and in the audit-pack README. I verified: `config/route_permission_map.php` is unchanged and byte-identical to the pack copy; `route-list.json` differs from HEAD by exactly the two new routes (291 -> 293), nothing removed or changed. The human must acknowledge the departure and add one sentence to CLAUDE.md (I may not edit it).

**B3 - LOW - consequences of decision (a), spelled out**: `isAssignableByOrg` allows Seller roles only for `manufacturer`, `distributor_stocking`, `distributor_non_stocking` (`config/rbac.php:73`). The other 16 org types lose the ability to give a Seller role from the Team page and from the invite dropdown, including types that plausibly sell: `fabricator`, `manufacturer_s_rep_sales_agency` (already known), `service_vendor`, `rental_equipment_provider`, `logistics_provider`, `testing_certification_body`. The overview and roles dropdowns already hid Seller roles for those types; the Team page (`index()`) and the server did not, so this is a real narrowing for them. Platform roles are also no longer assignable from the org screens of the `platform_internal` org type (platform staff must use the admin panel). One-line config change if the client wants more types.

**B4 - LOW - delegation deadlock between exactly two approvers** (confirms and refines QA F2). Reproduced (probe T5): pool = {P, D}, P has an active delegation to D. P approves own order -> 403; D approves P's order -> 403 with the same text "You cannot approve your own order ...", although D is an approver in his own right, because `selfIds` treats the delegate as the principal. Nobody can decide the order until the delegation is revoked or expires, or a third approver exists. It needs EXACTLY the two-approver roster; with three or more approvers there is no deadlock. The message is misleading for D. Options: accept and document, or let a delegate who is in the pool in his own right count as "another approver" for the principal's orders (needs a PLAN amendment).

**B5 - INFO - Cross-Functional roles still carry seller-grade groups**: `integration_service_account` (P1, offered in all dropdowns) and `api_system` (P3, accepted by the server because the phase filter is UI-only) hold `order_fulfillment` F, `product_management` F, `pricing_management` F, `inventory_and_availability` F. The Seller exclusion is by category label, so a buyer org can still hand these groups out. Impact is small today: the non-admin routes that use them are `user-products*` and `user-services*` (product_management), which buyers already use for estimate line items. Decision (a) said Cross-Functional is allowed; this is the factual content of that decision.

**B6 - LOW - Architect rulings 4, 6, 9 restated for the checkpoint** (all accepted by me as fail-closed or product calls): accepting an invite switches the user's session org to the inviting org (the invitee's next checkout/orders land in that org until they switch back); a universal admin can create a Platform/Seller-role invite that can never be accepted but leaves a `universal_assign_restricted_role` row; an outage of `rbac_audit_logs` blocks sole-approver self-approval and checkout auto-approval.

**B7 - LOW - what the stuck-order backfill actually does** (orders 4, 5, 7, 9 and anything else). Selection is `status='pending' AND org_id IS NOT NULL AND approved_by IS NULL AND rejected_by IS NULL AND user_id IS NOT NULL`. I verified there are only two `Order::create` sites (checkout, RFQ convert) and no admin or user code that sets `status` back to `pending`, so on production data that set can only be orders written by the old sole-approver branch. It does NOT re-check that the submitter is still the only approver today: an org that has since gained approvers still gets those old orders force-approved with the note "you are the only approver in your organization". It never touches `org_id IS NULL` rows, guests, `pending_approval`, rejected or cancelled orders. Probe T6 (storage redirected): two pending orders approved, a ghost-submitter order stayed `pending` and was reported `failed`. The human should read the dry-run table against the client's own list before `--force`.

**B8 - INFO - run the data review BEFORE the client re-tests, and extend it**: `isActiveMember` (`RoleAssignmentService.php:120-126`) counts ANY active row, so a user who only holds a stray Platform or Seller row from the earlier abuse is a "member" for the new guard (and for ownership transfer and delegation) and can still be given more roles without an invite. PLAN section 7's queries find the stray rows; they do not find delegations that already point at non-members (3b only blocks new ones). Read-only, MariaDB syntax, untested here:
`SELECT d.id, d.org_id, d.from_user_id, d.to_user_id FROM delegations d WHERE d.is_active=1 AND NOT EXISTS (SELECT 1 FROM user_org_roles u WHERE u.user_id=d.to_user_id AND u.org_id=d.org_id AND u.is_active=1);`
and the users whose ONLY active rows are restricted-category roles:
`SELECT u.org_id, u.user_id FROM user_org_roles u JOIN roles r ON r.id=u.role_id JOIN organizations o ON o.id=u.org_id WHERE u.is_active=1 GROUP BY u.org_id, u.user_id HAVING SUM(NOT (r.category='Platform' OR (r.category='Manufacturer/Seller' AND o.org_type NOT IN ('manufacturer','distributor_stocking','distributor_non_stocking'))))=0;`

---

### (C) Pre-existing issues noticed (do not fix in this task; each needs its own decision)

**C1 - CRITICAL - any `user_management` F holder can rewrite the GLOBAL permission matrix and create roles of any category** (pre-existing; PLAN section 8 flagged it as "not planned, separate decision"; I reproduced it)
- Where: `OrgAdminController::updateRolePermission` (`app/Http/Controllers/Frontend/OrgAdminController.php:224-258`, route `PUT org-admin/roles-list/permissions`, map `user_management` F) and `storeRole` (`:262-290`, `POST org-admin/roles-list/create`). `user_management` F is held by Organization Owner, Organization Admin AND the seeded Estimator. Roles and `role_permissions` are global, not per org; there is no category, org-type or "own roles only" check.
- Probe T8 (enforce mode, 3 x 200): Owner creates a role "Super" with category `Organization`, sets `system_administration` = F on it, assigns it to themselves through `POST org-admin/roles` (which passes every new A1/A2 check, because the category is not Platform and the target is a member). Probe T7 (both modes): the Owner of org A sets `viewer_read_only` -> `approval_authority` = A; `approverPool(org B)` immediately changes from `[]` to org B's viewer, i.e. one tenant changes another tenant's approver pool and therefore its auto-approval and its self-approval guard.
- Why it matters now: S03's intent is "an Owner cannot obtain platform-level power through the org screens". The Phase A fix closes assigning Platform roles, but the same screen family still lets the Owner mint equivalent power, and the client's re-test will use these screens. Also, `updateRolePermission` does not call `PermissionMatrix::flush()`; `checkPermission` reads a 24 h cached copy while `approverPool` reads the table directly, so after an edit the two disagree until the cache expires or a deploy clears it (an inflated pool can leave an org's orders waiting for "approvers" who cannot yet approve).
- Recommendation: its own task before the client re-test, not part of Phase A.

**C2 - HIGH - an ex-member with a stale session can list, approve and reject the orders of the org they were removed from, if they hold an approver role in any other org** (pre-existing; not touched by this diff, not made worse by it)
- Where: `OrderApprovalController.php:25` (`index`) and `:88` (`authorizeApprovalAction`) compare the order's org with `CurrentOrg::sessionOrg()`, which returns the RAW session value for non-universal admins, while `requireOrgLevel` (`:~127`) checks the permission in `CurrentOrg::id()`, which silently falls back to the user's first active org when the session org is no longer valid. `CheckoutController.php:211` and `:231` use the raw value too (an ex-member can place an order into the old org; it is `pending_approval`, never auto-approved, because the pool no longer contains them).
- Reproduced (probe T1, audit and enforce): user is `executive_approver` in orgs A and B, session org = A, then their org-A rows are deactivated. `GET order-approvals` lists org A's pending order; `POST orders/{id}/approve` -> 302, order `processing`, `approved_by` = the ex-member. Window: the lifetime of the ex-member's existing session. The new A05 guard does not help (the order's submitter is someone else).
- Not in the plan. Fix direction for a later task: use the validated `CurrentOrg::id()` in these controllers. This also answers the "user holding the approver role via a role in another org" probe: it is real, but only through this stale-session path.

**C3 - MEDIUM - QA F1 confirmed, with a warning about the obvious fix**
- Confirmed (probe T2): `InviteController::register` (`app/Http/Controllers/Auth/InviteController.php:57`) passes `email_verified_at => now()`, but `email_verified_at` is not in `User::$fillable` (`app/Models/User.php:40-47`), so the user row is created with `email_verified_at = NULL` and no `Registered` event fires, so no verification mail is sent. Every invited new hire is therefore behind the `verified` wall (`user-dashboard`, order approvals and every other route in the `auth, verified, checkRole:user` group) until they use "resend" on the verify page. Interaction with Phase A: an invited Executive Approver counts in `approverPool` but cannot reach `order-approvals` until verified, so during the client's A05/A01 re-test the Owner's own orders wait for an approver who cannot yet act.
- Warning: do NOT fix it with `forceFill` / adding the attribute to `$fillable`. The inviter receives the register link ("Copy link instead" returns it), so the inviter (or anyone holding the link) can register the invited address with a password of their choosing. Marking that account verified would turn it into a "verified" account for a mailbox the registrant does not control, which defeats the verified-e-mail-equals-invite-e-mail binding that 3c relies on (the owner could later accept an existing-account invite for that address) and satisfies the verified-e-mail condition of `UniversalAdmin::is`. The safe fix is to fire the normal verification notification and leave the address unverified until the mailbox owner clicks it. Today's accidental NULL is the safe behaviour.

**C4 - LOW - QA F4 confirmed and widened**: the invite modal JS (`resources/views/user/org-admin/overview.blade.php:301-331`, `index.blade.php:560-590`) handles only an `error` key; any non-2xx JSON (a 403 `rbac_error`, a 422 validation body) falls into the success branch and prints `undefined` as the link. After Phase A it is reachable by a crafted request or a page left open from before the deploy (a Platform/Seller role id), not from the dropdowns. The `confirm_existing` and `existing` branches (`overview.blade.php:308,326`, `index.blade.php:567,585`) are now unreachable dead code; leaving them is acceptable (Architect ruling 8).

**C5 - LOW - `POST org-admin/invite` is not throttled** and sends a platform e-mail with an owner-chosen name and org name to any address (now also to registered users). Unchanged by this diff; the old existing-account answer additionally disclosed the account holder's name, which is gone.

**C6 - LOW - `InviteController::show()` still renders the sign-up form for an invite whose role is no longer assignable** (Architect ruling 7). Submitting it creates no user (tested), so this is cosmetic; a one-line check would make it consistent.

**C7 - INFO - approve/reject are not conditional on the order still being `pending_approval`** (`OrderApprovalController::approve/reject`): two concurrent requests both pass `authorizeApprovalAction`. A double-submit by a sole approver can write two `self_approved` rows, and an approve racing a reject by two approvers ends in whichever write is last. Pre-existing, no money moves.

**C8 - INFO - matrix decisions unchanged**: the seeded Estimator and Organization Admin still hold `user_management` F and can assign `organization_owner` to any member (ARCHITECTURE 12.1 territory); `auditor_read_all` still holds `organization_management` F. Not touched, not made worse. I confirmed the diff does not touch any ARCHITECTURE section 12 item (rep-agency dead code, the 302 test, delegation Q11, the SeedMatrix counts).

---

### Attacks that held (tried, did not break)

1. Role assignment, every writer: `assignRole`, `generateInvite` (new and existing), `InviteController::register`, `InviteAcceptController`, `DelegationController::store`, `OrgSettingsController::transferOwnership`, `Admin\RbacController::assignRole` (platform admin only, unrestricted by design), `OrgOnboardingService` (called only from signup, creates its own org), `ProjectMember::enrol` path (`addProjectMember` already required org membership). No other `UserOrgRole::create/updateOrCreate/insert` or `->assign(` exists in app code. `assignRole` with `user_id` as array / `"12abc"` / `"5.0"` and `role_id` as array: 422 (never a 500). A user of another org: 403 in both modes. The org used for the check is `CurrentOrg::id` (validated), the same one `requireOrgLevel` uses, so a session org that differs from the caller's real org cannot be abused. A fully removed user cannot be re-added by `assignRole` (403, "invite first"); a user who still has one active row can have a second row reactivated, which is the intended meaning of decision d.
2. Invite accept: unknown token, other user's token and unverified account all end in the same bare 404 (or the `verified` 302 before the controller); the invalid page is shown only to the right, verified user. `strcasecmp(trim())` on both sides. The claim is a conditional `UPDATE ... WHERE used_at IS NULL` in the same transaction as the assignment; an SoD conflict rolls the claim back; the 404 abort inside the closure rolls back and propagates. No open redirect (only named routes; `url.intended` is Laravel's own and the login controller uses `redirect()->intended`). All output is `{{ }}` escaped; `@csrf` present; `OrgInvite::create` and `User::create` take explicit validated fields (no mass assignment of request data). Token is 48 random characters behind `auth` + `verified` + e-mail equality; no enumeration of accounts or invites through the accept route (account existence is already public through the register form's `unique:users`). The invite's org id is the only org that can be joined; the role is re-validated for category (and SoD through `assign()`) at accept AND at guest register. FK cascade: `org_invites.org_id`/`invited_by` cascade on delete, so no null dereference after an org or inviter is deleted.
3. Approval: self-approval via direct, delegate-of-submitter, delegate-who-is-the-submitter, universal admin, `user_id` NULL or a user that no longer exists (treated as "not own", so any approver can act), two orgs, other-org order, a role in another org; the pool is `distinct()` users so one person with four approver roles is one approver; inactive rows are excluded. Delegation cannot launder auto-approval: `route()` uses the real `Auth::id()`, and a delegate who is not in the pool is routed to the pool. Reject has the same guard and a sole approver rejecting is allowed unlogged, as planned. Checkout and RFQ convert use the same `initialOrderState`; totals, tax and items are unaffected (the state array is server-built and has no keys the request can set).
4. Audit consistency: `auto_approved` is written inside the order transaction (checkout between `Order::create` and `DB::commit()`, RFQ inside the closure), `self_approved` inside `DB::transaction` with the status update; an audit failure leaves no approved order and no orphan row (QA mutation proofs R6, R7, S9). Reasons contain only constants and the system-generated `ORD-` number, so no log injection; views escape. `AuditLog::event` fits the MariaDB column limits (`method` 3-4 chars, `required_level` 1 char, reason cut at 255).
5. Both modes: every new check is an `abort()` in the controller, so it holds in audit and enforce mode and under per-org enforcement; `DenialRecorder` stores them as `controller_denied` Blocked rows. Universal admin: allowed paths are logged with the new reasons and deduplicated per (reason, user, org, group, level), so the specific reason row is not swallowed by the generic one.
6. Scope creep / leftovers: none in the diff. No `dd()`, `dump()`, `console.log`, skipped or empty tests. Comments that describe WHAT (docblocks on `guardOwnOrder`, `InviteAcceptController`, `AuditLog::event` first sentence, the routes comment) are harmless against CLAUDE.md. The unrelated untracked `docs/WEEK9-LOG-REVIEW-DRAFT.md/.pdf` must not be committed with Phase A.

### Not verifiable here (needs MariaDB / a browser)
Two simultaneous accepts of one invite (the conditional update is standard SQL; sqlite only proves the code path); the backfill on a restored backup; strict-mode behaviour with long real strings; MariaDB case-insensitive e-mail matching on the inviter's side (the accept side is explicit `strcasecmp`); the real mail -> login -> accept browser flow (code review: `Authenticate` stores `url.intended` for the GET and `LoginController` uses `redirect()->intended`, so it should work; an unverified account must verify first and then click the mail link again because `intended` is lost across verification).

### QA findings, confirmed or refined
F1 confirmed (C3, plus the warning not to "fix" it with forceFill). F2 confirmed and refined (B4: needs exactly two approvers; message misleading for D). F3 confirmed (A2). F4 confirmed and widened (C4). QA's statement that no other site grants a role to a non-member is also confirmed by my own trace.



## Verifier - amendment round

Date: 2026-10-26. Reviewer: Verifier, narrow re-check of the A1 inviter-authority rule (`Auth\InviteAcceptController::inviterStillAuthorised`, used by `invitationFor` for GET review and POST accept) and `tests/Feature/Rbac/InviteInviterAuthorityTest.php`. Scope per the Orchestrator brief; the rest of Phase A is not re-reviewed and the full suite is not run. In-memory sqlite only; the real DB is never touched. Findings are appended below as they are confirmed.

Method: read of `InviteAcceptController` (unchanged since 11:49), `UniversalAdmin`, `PermissionService::checkPermission`, `DelegationService::resolveEffectiveUserId`, `RoleAssignmentService::assign/isActiveMember`, `OrgAdminController::generateInvite/requireOrgLevel`, `InviteController`, the accept views; throw-away probes OUTSIDE the repo (`.../scratchpad/amend/AmendProbeTest.php`, `MatrixProbeTest.php`, and two mutant controllers bound over the real one through the container in `MutantARunTest.php` / `MutantBRunTest.php`), all on in-memory sqlite. No application, test, config or env file was edited; `git status` is identical to the start. Suites run: `InviteInviterAuthorityTest` 44 passed (622 assertions), `ExistingUserInviteTest` 35 passed (276 assertions). Full suite not run, per brief.

NOTE ON A MOVING TARGET: `tests/Feature/Rbac/InviteInviterAuthorityTest.php` was modified at 12:16:00 WHILE I reviewed it (QA appended a block "QA amendment round", 12 methods / 30 cases became 19 methods / 44 cases). My verdict is on the 12:16 version. The controller did not change.

### Result: the rule held against every attack tried. No CRITICAL, HIGH or MEDIUM finding. Counts: LOW 2, INFO 7.

### LOW

**L1 - LOW - process-history comment in the new test file** (`tests/Feature/Rbac/InviteInviterAuthorityTest.php:355`, plus the class docblock at `:14-17`). `// ---- QA amendment round: gaps found by mutation proofs ---` records who wrote the tests and why, not a hidden constraint, and will be wrong the day the file is read next. The docblock restates the rule (WHAT). CLAUDE.md: "No comments unless explaining a non-obvious WHY". The two comments that ARE legitimate: the controller's `:83` (ordering reason for the UA shortcut) and the test's `:340` (pins "accept route only", allowed by PLAN 10.1 test 12). Not a regression; pure convention. Suggested fix direction: delete the divider and shorten the docblock.

**L2 - LOW - audit-pack README names the exemption imprecisely** (`docs/client-audit-pack/README.md:21`: "...holding full user-management rights there (or a platform administrator)"). The code exemption is a user id in `UNIVERSAL_ADMIN_USER_IDS` with a verified e-mail (`UniversalAdmin::is`); ARCHITECTURE 4.5 words it correctly. "Platform administrator" is defined nowhere in the README and a client reader can take it to mean any holder of a Platform role (e.g. `platform_super_admin`), who is NOT exempt: such a user passes only as an active member of that org holding `user_management` F. The README is client-facing, so the phrase should read "or a listed, verified universal administrator".

### INFO (no action required unless the human wants it)

**I1 - INFO - the guest register link is still not covered (deliberate, pinned, masked)**: `register/invite/{token}` for a new e-mail still registers after the inviter lost authority (reproduced by `test_the_guest_register_link_is_deliberately_unchanged`, both modes). Unchanged by this round on purpose (PLAN 10.1; ARCHITECTURE 12 item 13). The mask holds today: `User::$fillable` lacks `email_verified_at`, so the registrant is unverified and stays behind `verified` on every `/user-*` and org route (`routes/web.php:115`; the only other `auth`-only route is `register.complete`, line 86). Anyone who fixes C3 (forceFill) without item 13 reopens it. Related and pre-existing: the same guest route hit with an EXISTING-account invite token answers 500 (probe P14: unique-email violation, user count and `used_at` unchanged), because `register()` has no `unique:users` rule; nothing is created, so no authority gain.

**I2 - INFO - residual check-then-claim race is real but small and documented**: `inviterStillAuthorised` (`InviteAcceptController.php:71`) runs in `invitationFor` before the transaction (`:39-44`); an inviter removed between the last authority query and the conditional `UPDATE ... WHERE used_at IS NULL` (`:40`) is not caught. Nothing inside the request can widen it (accept only adds rows; nothing the invitee sends is read by the check) and the invitee cannot time an admin's removal. Named in ARCHITECTURE 12 item 4 and PLAN 10.1. Closing it would need `lockForUpdate` on the inviter's `user_org_roles` rows inside the transaction; not worth the complexity.

**I3 - INFO - engine semantics make an inviter's pending invites go dormant while they hold an INCOMING delegation from a lower-privileged member** (probe P1, both modes): an Organization Owner who is also the delegate of a viewer is evaluated as that viewer in the org (`resolveEffectiveUserId`); creating an invite is then 403 and accepting an earlier invite is 404 until the delegation ends, after which it works again. Fail-closed and identical to what the inviter could do live. No change proposed.

**I4 - INFO - dormant invites revive if the inviter regains authority** (probe P9: inviter removed -> 404; re-added as Organization Admin within the 7-day window -> 200). It is the consequence of "authority judged at accept time" (PLAN 10.10) and the test suite pins the same revival for delegations. Bounded by the 7-day expiry. Because there is still no pending-invites list, whoever re-adds a previously removed admin cannot see what comes back with them; worth one sentence in the support notes.

**I5 - INFO - (3) universal-admin handling verified**: a listed inviter with no role passes without any `rbac_audit_logs` row at review or accept; a listed inviter who is also a low-level member writes none either (QA test at `:~395`); delisted or unverified inviter without a role is refused; a delisted inviter who still holds an F role in the org passes as an ordinary admin (correct). A LISTED INVITEE vouches for nobody (probe P3: removed non-listed inviter -> 404 on GET and POST, zero bypass rows; listed invitee + listed inviter -> usable, row count unchanged; non-listed inviter + listed invitee -> usable, unchanged). The inviter id is the only id given to `UniversalAdmin::is` (config ids are `intval`-cast, `UniversalAdmin::is` compares strictly); a dangling `invited_by` fails closed (probe P12) and a listed id with no users row is not exempt (probe P13). No path writes a bypass row under the wrong user.

**I6 - INFO - a test gap I found was closed by QA during my review**: against the 12:00 version (30 cases) the mutant `UniversalAdmin::is($inviterId) || UniversalAdmin::is((int) Auth::id())` (an invitee who is a listed universal admin vouching for a removed inviter) SURVIVED all 30 cases. Against the 12:16 version (44 cases) it is killed by `test_a_universal_admin_invitee_does_not_vouch_for_a_removed_inviter` (2 failures, audit and enforce). I also confirmed the binding mechanism is live (an always-true mutant fails 30 of 44). Mutant `F -> A` is equivalent in the seeded matrix (`MatrixProbeTest`: `user_management` is F for 6 roles, O financial_admin, S project_manager, R for 4; no role holds exactly A), and QA's custom-role test (`:358`) now kills it anyway with a role created in the test.

**I7 - INFO - (5) responses**: the 404 surface is as planned. Other accounts always get the framework 404 whatever the inviter's state (tests 10 and the third-account before/after check); an unverified account is redirected by the `verified` middleware before the controller, with the same Location for a valid and an orphaned token (probe P6, GET and POST); only the e-mail-matching verified invitee sees the "Invite link invalid or expired" page, byte-identical for expired, used, role-not-assignable and inviter-lost. The e-mail holder can tell "no such token" (bare 404) from "token exists but is dead" (invalid page); that is pre-existing and needs a 48-character token. Check order (membership/e-mail equality first, inviter clause after, claim last) is correct; a refused invite leaves `used_at` NULL and creates no `user_org_roles`, `role_assignment_logs` or `rbac_audit_logs` row (asserted in `assertRefused` for GET and POST).

### Items attempted and held (for the record)
(1) removed / demoted (financial_admin O, project_manager S, executive_approver none) / role replaced by another F role (valid) / user row deleted (cascade) / invite row surviving a deleted inviter with FK checks off / stale invitee session org (the rule never reads the session) / second token for the same e-mail (the orphan 404s and the valid one works, probe P8) / re-invite / invitee holding roles in two orgs: all as intended. (2) delegate inviter valid only while delegation AND own membership last; delegate whose own membership is in ANOTHER org refused although `checkPermission` alone would pass (QA test `:~418` asserts that precondition); inviter with F only in another org or only in the invitee's session org refused; Owner of B who is a low member of A refused (probe P10). (4) the check precedes the claim; refused invites are never claimed. (6) no regression: `ExistingUserInviteTest` 35/35 with only the PLAN 10.11 fixture change (`tok-m`, lines 302-303); guest register link, new-hire flow and `generateInvite` are untouched by this round; the only controller changes are the `PermissionService` constructor argument, the one extra clause in `invitationFor` and the private helper, exactly as PLAN 10.1 specifies (plus the single WHY comment at `:83`). Nothing outside the A1 scope was touched, and no ARCHITECTURE section 12 item is "fixed" as a side effect (item 13 is recorded, not implemented).

### VERDICT: CLEAR TO COMMIT (rule and tests). L1 and L2 are comment/wording polish with no behavioural risk; per strict mode the Orchestrator may route them to the Architect, but I do not consider them blocking.

Line refs for I5/(2) above: `test_a_universal_admin_inviter_who_is_also_a_low_level_member_writes_no_bypass_row_at_accept` is at InviteInviterAuthorityTest.php:379, `test_a_delegate_whose_own_membership_is_in_another_org_invalidates_the_invite` at :412 (the "~" figures were approximate).
Correction to the line refs above: the low-level-member universal-admin test is at InviteInviterAuthorityTest.php:385 and the other-org delegate test at :415.


## Architect compliance pass - amendment round

Date: 2026-10-26. Scope: PLAN.md section 10 (10.1-10.11) only, Developer tasks V1, V1a, V2, V3, V4, V5. Method: line-by-line read of the controller and the new test file, `git diff` / `git status` / file mtimes for every other path, comparison of the three edited docs and of `ExistingUserInviteTest.php` against the Phase A QA snapshot of 2026-10-25 (`scratchpad/qa/repo`, the last copy before the amendment), and in-memory sqlite runs only (`DB_CONNECTION=sqlite DB_DATABASE=:memory:`; the real DB was never touched).

### VERDICT: COMPLIANT

No deviation from the approved design. Two non-blocking notes (N1, N2) and two hand-off points (H1, H2) below. The notes do not need a PLAN change; they are for the human / the next person who touches the file.

### A. The rule (10.1) - `app/Http/Controllers/Auth/InviteAcceptController.php` (mtime 11:49:34, unchanged since V1)
- Constructor gains `private readonly PermissionService $permissions`; imports `PermissionService` and `UniversalAdmin` present. No new public method, no new abstraction.
- `invitationFor`: the original `abort_unless(invite exists AND verified AND strcasecmp e-mail equal, 404)` is first and unchanged; the existing used / expired / `isAssignableByOrg` condition is extended with `|| ! $this->inviterStillAuthorised($invite)`; outcome is the unchanged `HttpResponseException(response()->view('auth.invite-invalid', [], 404))`. No flash, no message, no audit write.
- `inviterStillAuthorised` is character-for-character the 10.1 spec: ids cast to int, `$orgId = (int) $invite->org_id` (never the session or `CurrentOrg`), `UniversalAdmin::is($inviterId)` first and NOT through `checkPermission`, then `isActiveMember($inviterId, $orgId) && checkPermission($inviterId, $orgId, 'user_management', 'F')`. Uses ids, not the `invitedBy` relation. The only addition is one WHY comment on the UA-first ordering (allowed by CLAUDE.md).
- Same code path for `show` (GET) and `accept` (POST), so audit and enforce behave identically; both routes remain unmapped OPEN_ROUTES.

### B. Nothing outside the plan changed
Files with an mtime of 2026-10-26 (the amendment day): exactly `InviteAcceptController.php`, `ExistingUserInviteTest.php`, `InviteInviterAuthorityTest.php`, `ARCHITECTURE.md`, `docs/client-audit-pack/README.md`, `TESTING.md` (plus the task files). Everything else in the working tree dates from 2026-10-25 or earlier. In particular: `Auth\InviteController` (its only diff is the Task 3 `isAssignableByOrg` clause in `register`; no inviter check, `show` untouched), `OrgAdminController::generateInvite`, `routes/web.php`, all views, `config/` and `database/` (not in `git status` at all), `CLAUDE.md` (mtime 2026-10-06), `AuditFindingsRound2Test` (still only the two Task 3c OPEN_ROUTES lines), `docs/client-audit-pack/route-list.json` (`cmp` identical to the 2026-10-25 QA snapshot) and `docs/client-audit-pack/route_permission_map.php` (not modified; byte-identical to `config/route_permission_map.php`). `git status` still lists the same 39 entries as at session start; no stray files. The 10.6 CLAUDE.md sentence is not applied (still waiting for the human); the 10.5 SQL lives in the plan only.

### C. V1a - the single sanctioned fixture change (10.11)
`diff` of `tests/Feature/Rbac/ExistingUserInviteTest.php` against the 2026-10-25 QA snapshot shows: (1) the last block of `test_the_role_is_re_validated_at_accept` only - `$mfrOwner = $this->mkUser($mfr, 'organization_owner');` added and `'invited_by' => $mfrOwner->id` added to the `tok-m` override; `tok-p` / `tok-s` (lines 290-291), the foreach and every assertion untouched; (2) one extra test, `test_separation_of_duties_is_checked_per_organization_at_accept`, which is the QA addition already recorded in this file (QA section 3: "ExistingUserInviteTest +9 ... SoD is per organization at accept"), not part of the amendment (26 original + 9 QA = 35 cases; the run shows 35). No other block, no other test file edited. `ExistingUserInviteTest`: 35 passed (276 assertions).

### D. `InviteInviterAuthorityTest.php` (as delivered by the Developer: 12 methods, 30 cases, mtime 11:58:59)
All 12 cases of 10.1 exist under the planned names, each over the `modes` provider (audit and enforce); I read each against its claim:
1 usable: GET 200 + "Accept invitation", POST 302 to `user.dashboard`, role active, `used_at` set, `role_assignment_logs.performed_by` = inviter. 2 Owner -> Organization Admin still usable. 3 all inviter rows deactivated -> refused. 4 provider over financial_admin / project_manager / executive_approver, with an explicit assertion that the inviter is still an ACTIVE member (kills the wrong-group and level mutants; no invented "A" role). 5 invite for org A with `invited_by` = Owner of org B, invitee holds a role in B and the session org is B (so a session-org mutant would pass wrongly). 6 listed, verified UA with no role in A creates the invite through the route; `allowed_universal_admin` count is asserted > 0 after creation and identical after the accept request. 7 removed from the list / `email_verified_at` NULL, both modes. 8 deleted inviter: 404 on GET and POST and no role (also holds if a row survived). 9 delegate inviter via direct delegation insert: usable; delegation deactivated -> refused; delegation active but the delegate's own rows deactivated -> refused (the dropped-`isActiveMember` mutant); everything restored -> full accept with `performed_by` = delegate. 10 inviter-removed page and status equal the expired-invite page (GET and POST); a third verified account gets an identical bare 404 before and after the inviter is removed and never sees the invalid-page text. 11 after case 3 a current Owner re-invites: old token 404 on GET and POST, new token usable. 12 guest `register/invite/{token}` still works after the inviter left, with the short WHY comment the plan allowed.
Every denial goes through `assertRefused`: GET and POST by the verified invitee -> 404 + "Invite link invalid or expired" text, `used_at` NULL, no new `user_org_roles` row (global and for the invitee), no new `role_assignment_logs` row, and additionally no new `rbac_audit_logs` row (an addition that matches the "no audit row" wording of 10.1, so not a deviation). Real inviters create the invite through the real `POST org-admin/invite` (cases 1-4, 6, 7, 9, 11, 12); cases 5 and 10 insert rows directly, as planned. Run: **30 passed (434 assertions)**.

### E. Docs say what the code does
- `ARCHITECTURE.md` (diff vs the 2026-10-25 snapshot is exactly these edits): 4.5 Invites bullet now states the review/accept inviter rule (active member of `invite.org_id` + `user_management` F, or `UniversalAdmin::is` with no extra audit row at accept; same 404 invalid page as an expired invite; a current admin re-invites; guest path unchanged, see item 13) - matches the code. 10b role-assignment bullet gains the one clause on a listed user's pending invites. Section 12: item 4 extended with the three (i) points (checked: `orders.approved_by` and `rbac_audit_logs.user_id` have FKs, `orders.user_id` has none); items 6-13 all present, each ends "Decision pending with the human; not fixed by client audit 2 Phase A", item 8 carries the forceFill warning, item 13 carries the masking dependency and names the pinning test; the line references in items 7, 8, 12 check out against the code (OrderApprovalController :25 / :88, CheckoutController :211 / :231, `User::$fillable` :34-41, modal scripts at overview :301 / index :560). Items 1, 2, 3, 5 and section 11 untouched, so rep-agency, `api_system`, org type count and the audit-middleware 302 are not claimed as resolved. Change log line dated 2026-10-26 present ("No migration, no route or route map change" is true). Header date "Last verified 2026-09-26" deliberately left as before (the whole file was not re-verified).
- `TESTING.md`: baseline paragraph and heading date updated; known failure 1 renamed to `AuditMiddlewareTest::enforce mode blocks and records blocked`; `PhaseARegressionTest` (34 cases - verified by a run: 34 passed) and `InviteInviterAuthorityTest` added to the Client audit 2 list. The figure "1680 passed / 4 failed" is 1650 + 30 and is consistent with the Developer's V5; I did not re-measure the whole suite (a full run I started was cut off by the shell, and the tree was being changed by QA at the time). The partial output (1191 passes, 1293 lines) showed only the two known failures reached so far (ExampleTest, AuditMiddlewareTest). The full-suite figure is therefore the Developer's measurement, not mine.
- `docs/client-audit-pack/README.md`: only the `invite/{token}/accept` row changed, now with the inviter condition; route counts (293 routes, 15 open) unchanged, correct since no route changed.

### Non-blocking notes
- **N1 (README wording, LOW).** The README row says the inviter must be an active member with full user-management rights "(or a platform administrator)". The code accepts only a listed and verified universal admin (`UniversalAdmin::is`), not every `users.role = admin` account (ARCHITECTURE section 1 uses "platform admin" for that column). In practice an unlisted `role = admin` user cannot create an invite at all (`checkRole:user`), so the sentence is not wrong in effect, but "(or a listed universal administrator)" would be exact. The product UI calls the listed users' panel "Platform admin panel", which is why I do not count this as a deviation. Human's call; if wanted it is a one-phrase doc edit via PLAN.
- **N2 (ARCHITECTURE 4.5 / 10b, LOW).** The older sentences "There is no exemption ... for accepting an invite" (4.5, Universal admin bullet) and "Not lifted: ... accepting someone else's invite" (10b) still stand next to the new inviter clause. Per 10.1 they refer to the INVITEE (a UA invitee still needs the verified-e-mail match), and the Developer left them as planned; a reader could still misread them as covering the inviter. Optional clarification only.
- Test-class docblock in `InviteInviterAuthorityTest` describes WHAT; same class of harmless docblock the earlier Architect pass accepted (REVIEW above, point 6). Not a finding.

### Hand-offs
- **H1 (stale figures once QA's tests stay).** At 12:31 QA appended 8 more methods (16 cases) under "QA amendment round" to `InviteInviterAuthorityTest.php`; the Developer's 12 methods above it are unchanged (only imports and appended tests differ), and no production file changed after V1 (controller mtime still 11:49:34). This pass certifies the Developer's delivered state. If those tests are kept, the class has 46 cases, so the "30 cases" wording and the "1680" baseline in `TESTING.md` (and the PROGRESS V5 line) must be re-measured and updated by whoever closes the round. That is a QA/Developer bookkeeping step, not a Developer deviation. Note also that QA and the Verifier started on the live tree before this gate closed, contrary to the order in the brief; harmless here because nothing production-side moved, but the gate order should be kept next time.
- **H2 (mutation mapping, for QA).** PLAN 10.1 lists V-M14 (`isActiveMember` ignores `is_active`) as killed by case 3. That is wrong, and the Developer noticed: in case 3 `checkPermission` still fails for a removed inviter, so M14 is killed by case 9 (delegate with own rows deactivated) and by `RoleAssignmentGuardTest`, not by case 3. This is an error in my mutation table only, not in the code or tests; no PLAN edit is needed for the Developer. QA should use "M14 -> case 9" when judging survivors.

Gate status: the amendment round is cleared for QA and Verifier to continue. Checkpoint 2 and the open human decisions (CLAUDE.md sentence 10.6; pending items 6-13; B3, B7; the N1 wording) are untouched by this pass.

---

## QA - amendment round

Scope: PLAN.md section 10 (A1, inviter authority at review and accept of `invite/{token}/accept`). Code: `app/Http/Controllers/Auth/InviteAcceptController.php` (`inviterStillAuthorised`). Method: mutants applied ONLY in a scratch copy (`<scratchpad>/repo`, real `vendor/` copied, not symlinked, because a symlinked vendor makes Composer autoload `App\` from the real tree); the real tree was never mutated; the scratch `app/` was diffed against the real `app/` after every batch (identical). No change to `app/`, `routes/`, `config/`, `resources/`. No commits, no migrate/seed against the real DB (all runs `DB_CONNECTION=sqlite DB_DATABASE=:memory:`).

Baseline in scratch before any mutant: `InviteInviterAuthorityTest` + `ExistingUserInviteTest` = 65 passed (30 + 35). Mutant runs use the filter `InviteInviterAuthorityTest|ExistingUserInviteTest|RoleAssignmentGuardTest|PhaseARegressionTest` (137 tests) against the ORIGINAL 30 cases.

### Status of this section: COMPLETE (the first table was written mid-run; the later parts were appended mutant by mutant, then the final summary)

### Mutants run against the original 30 cases (results as of this write)

| Id | Mutant | Result | Killed by |
|---|---|---|---|
| M1 / M1b | V-M1 remove the whole clause / body = `return true` | KILLED | cases 3, 4, 5, 7, 9, 10, 11 (20 failing data sets) |
| M2 | V-M2 drop `isActiveMember` | KILLED | case 9 (delegate whose own rows are deactivated) |
| M3 | V-M3 drop `checkPermission` | KILLED | case 4 (all three demoted roles) and others (8 failing) |
| M4a | V-M4 `F` -> `O` | KILLED | case 4 `financial_admin` |
| M4b | V-M4 `F` -> `A` | SURVIVED | equivalent in the seeded matrix (see below); killed by the new custom-role test |
| M4c, M4d | `F` -> `S`, `F` -> `R` | KILLED | case 4 |
| M5a | V-M5 group -> `project_management` | KILLED | case 4 `project_manager` |
| M5b | group -> `organization_management` | SURVIVED | no demoted role in case 4 holds `organization_management` F (only `auditor_read_all` does); killed by the new all-seeded-roles test (to be re-proved below) |
| M6a | V-M6 org from invitee `CurrentOrg` for both checks | KILLED | case 1 and 5 (valid cases fail because the invitee's first org is C) |
| M6b, M6c | same, only in `checkPermission` / only in `isActiveMember` | KILLED | the same trivial way |
| M6d | raw session value with fallback to the invite org, both checks | KILLED | case 5 |
| M7a | V-M7 check only on POST | KILLED | GET assertions in 3, 4, 5 |
| M7b | V-M7 check only on GET | KILLED | POST assertions in 3, 4, 5 |
| M8 | V-M8 remove the universal-admin shortcut | KILLED | case 6 |
| M9a | V-M9 UA shortcut ignores verified e-mail (call site) | KILLED | case 7 `unverified` |
| M9b | `UniversalAdmin::is` ignores verified e-mail (global) | KILLED | case 7 `unverified` |
| M9c | UA shortcut ignores the config list | KILLED | case 3 and others (18 failing) |
| M9d | UA shortcut unconditional | KILLED | same as M1 |
| M10 | V-M10 UA branch through `checkPermission` | KILLED | case 6 (bypass row count) |
| M11a | V-M11 UA placed after the `isActiveMember` conjunct | KILLED | case 6 |
| M11b | UA checked AFTER `(isActiveMember && checkPermission)` | SURVIVED | case 6 uses a UA with no role, so `isActiveMember` is false, `checkPermission` is never reached and no row is written; killed by the new "UA who is also a low-level member" test (to be re-proved below) |
| M11c | UA checked after a `checkPermission` that runs first for everyone | KILLED | case 6 |
| M12 | V-M12 new clause before the e-mail-equality abort | KILLED | case 10 |
| M13a-e | V-M13 refusal as 403 / redirect / bare `abort(404)` / different message / 410 | KILLED | cases 3, 4, 5, 10 (page text and status) |
| M13f, M13g | refusal keeps the invalid page but flashes `error` / `status` to the session | SURVIVED | the body is identical, no test looked at the session; killed by the new "flashes nothing" test (to be re-proved below) |
| M25, M25b | audit row written on denial (via `AuditLog::event` / raw insert) | KILLED | `assertRefused` counts `rbac_audit_logs` before and after |
| M16a | claim (`used_at`) set before the check and left set on refusal | KILLED | `assertRefused` asserts `used_at` still NULL |

Not yet run when this section was written (running next, one at a time, results appended below): M16b, M19a/b, M14, M15a/b, the plan's `isAssignableByOrg` re-run (R1) and the other accept-flow mutants R2-R9, plus new mutants M26 (invitee-is-UA vouches), M27 (null-deref through `invitedBy`), M33 (`checkPermission` in the inviter's most recent org), M34 (delegation ignored), M6e/M6f (session-with-fallback in only one check), and one `user_management` -> other-group mutant per permission group.

### Tests added to the REAL repo (`tests/Feature/Rbac/InviteInviterAuthorityTest.php`, existing 12 methods untouched)

Count went from 30 cases (12 methods) to 48 cases (21 methods): +9 methods x audit/enforce = +18 cases. All new methods run in audit and enforce.

- `test_an_inviter_holding_only_user_management_a_through_a_custom_role_invalidates_the_invite` - kills M4b (`F` -> `A`). The Developer's "equivalent in the seeded matrix" is confirmed independently (no seeded role holds `user_management` exactly `A`: F = platform_super_admin, platform_operations, organization_owner, organization_admin, estimator, manufacturer_admin; O = financial_admin; S = project_manager; R = 4 roles; none = 22), but it is not equivalent in production: C1 in this REVIEW (`storeRole` + `updateRolePermission`) lets any `user_management` F holder mint a role with `user_management` A. The test inserts such a role and asserts `checkPermission(..., 'A')` true and `'F'` false before asserting the 404.
- `test_a_universal_admin_inviter_who_is_also_a_low_level_member_writes_no_bypass_row_at_accept` - kills M11b (and strengthens M10/M11a/M11c): a UA with an active `viewer_read_only` row reaches `checkPermission` if the shortcut is not first, which writes a bypass row under the inviter's id during the invitee's request.
- `test_an_inviter_with_full_authority_only_in_the_invitees_session_org_invalidates_the_invite` - inviter is `project_manager` (S) in the invite's org and Owner in org B, invitee's session org = B; kills the subtle one-sided session-org mutants (M6e, M6f, M33) that the trivial valid-case failures of M6a-c do not prove.
- `test_a_delegate_whose_own_membership_is_in_another_org_invalidates_the_invite` - delegate with an own role only in org B and an active delegation from the org-A Owner; asserts the delegate does pass `checkPermission` in A, then expects the 404 (own membership must be in the invite's org); kills M6f and the `isActiveMember`-in-another-org shape.
- `test_a_universal_admin_invitee_does_not_vouch_for_a_removed_inviter` - kills M26.
- `test_an_invite_row_that_outlived_its_inviter_row_fails_closed_without_a_server_error` - FK checks off so the `org_invites` row survives its deleted inviter (case 8 cannot reach that branch while the cascade works); asserts 404 invalid page; kills M27 (null dereference through `invitedBy`).
- `test_the_guest_register_page_still_shows_the_form_after_the_inviter_left` - the existing case 12 only asserts `assertOk()` on `GET register/invite/{token}`, and the invalid page is also 200, so a mutant adding the check to `InviteController::show` only (M15b) would survive; this asserts the form text and the absence of the invalid text.
- `test_only_roles_holding_user_management_f_keep_an_invite_valid_across_every_seeded_role` - walks every seeded role, derived from `role_permissions` at run time: each F holder keeps the review page 200, every other role (43 of them in the seeded matrix) gets the 404 on GET and POST with `used_at` still NULL and no role granted; kills every wrong-group mutant for which some non-F role holds F in the mutated group (M5b and the rest of the group sweep).
- `test_the_refusal_flashes_nothing_to_the_session` - asserts no `error`/`status`/`success`/`warning`/`info`/validation errors and empty `_flash.old`/`_flash.new` after the refusal; kills M13f/M13g ("no flash" is in PLAN 10.1).

Remaining (each result appended below right after it runs): re-prove every new test against its mutant in the scratch copy; M16b, M19a/b, M14, M15a/b, R1-R9, M26, M27, M33, M34, M6e/f, group sweep; flakiness (two runs of `InviteInviterAuthorityTest` and `ExistingUserInviteTest`); full suite on the real tree.

### Strengthened suite: results appended one mutant at a time (scratch copy, tests synced from the real tree)

- [M4b] KILLED - V-M4 level F -> A - Tests:    2 failed, 46 passed (858 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - an inviter holding only user management a through a custom role invalidates the invite with data set "audit"
    - an inviter holding only user management a through a custom role invalidates the invite with data set "enforce"
- [M5b] KILLED - group user_management -> organization_management - Tests:    2 failed, 46 passed (684 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - only roles holding user management f keep an invite valid across every seeded role with data set "audit"
    - only roles holding user management f keep an invite valid across every seeded role with data set "enforce"
- [M11b] KILLED - UA checked AFTER (isActiveMember && checkPermission) - Tests:    2 failed, 46 passed (870 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - a universal admin inviter who is also a low level member writes no bypass row at accept with data set "audit"
    - a universal admin inviter who is also a low level member writes no bypass row at accept with data set "enforce"
- [M13f] KILLED - refusal keeps invalid page but flashes an error message to the session - Tests:    2 failed, 46 passed (838 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - the refusal flashes nothing to the session with data set "audit"
    - the refusal flashes nothing to the session with data set "enforce"
- [M13g] KILLED - refusal keeps invalid page but flashes errors via withErrors-style session key - Tests:    2 failed, 46 passed (840 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - the refusal flashes nothing to the session with data set "audit"
    - the refusal flashes nothing to the session with data set "enforce"
- [M6e] KILLED - org = session value (fallback invite org) ONLY in checkPermission - Tests:    2 failed, 46 passed (844 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - an inviter with full authority only in the invitees session org invalidates the invite with data set "audit"
    - an inviter with full authority only in the invitees session org invalidates the invite with data set "enforce"
- [M6f] KILLED - org = session value (fallback invite org) ONLY in isActiveMember - Tests:    2 failed, 46 passed (844 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - a delegate whose own membership is in another org invalidates the invite with data set "audit"
    - a delegate whose own membership is in another org invalidates the invite with data set "enforce"
- [M33] KILLED - checkPermission evaluated in the inviter's most recent org instead of the invite org - Tests:    2 failed, 46 passed (844 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - an inviter with full authority only in the invitees session org invalidates the invite with data set "audit"
    - an inviter with full authority only in the invitees session org invalidates the invite with data set "enforce"
- [M26] KILLED - UA shortcut also true when the INVITEE is a universal admin - Tests:    2 failed, 46 passed (858 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - a universal admin invitee does not vouch for a removed inviter with data set "audit"
    - a universal admin invitee does not vouch for a removed inviter with data set "enforce"
- [M27] KILLED - inviter id read through the invitedBy relation (null-deref on a dangling row) - Tests:    2 failed, 46 passed (858 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - an invite row that outlived its inviter row fails closed without a server error with data set "audit"
    - an invite row that outlived its inviter row fails closed without a server error with data set "enforce"
- [M16b] SURVIVED - authority check inside the transaction after the claim (rolled back) - Tests:    48 passed (870 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
- [M19a] KILLED - isActiveMember tested for the invitee instead of the inviter - Tests:    10 failed, 38 passed (602 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - a current admin can re invite after the inviter left with data set "audit"
    - a current admin can re invite after the inviter left with data set "enforce"
    - a delegate inviter is valid only while the delegation and own membership last with data set "audit"
    - a delegate inviter is valid only while the delegation and own membership last with data set "enforce"
    - a valid inviter leaves the invite usable with data set "audit"
    ... 5 more failing data sets
- [M19b] KILLED - checkPermission evaluated for the invitee instead of the inviter - Tests:    10 failed, 38 passed (602 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - a current admin can re invite after the inviter left with data set "audit"
    - a current admin can re invite after the inviter left with data set "enforce"
    - a delegate inviter is valid only while the delegation and own membership last with data set "audit"
    - a delegate inviter is valid only while the delegation and own membership last with data set "enforce"
    - a valid inviter leaves the invite usable with data set "audit"
    ... 5 more failing data sets
- [M34] KILLED - delegation ignored: checkPermission only for the inviter's OWN roles (delegate never valid) - Tests:    2 failed, 46 passed (824 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - a delegate inviter is valid only while the delegation and own membership last with data set "audit"
    - a delegate inviter is valid only while the delegation and own membership last with data set "enforce"
- [M15a] KILLED - V-M15 same check added to InviteController::register (POST) - Tests:    2 failed, 46 passed (866 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - the guest register link is deliberately unchanged with data set "audit"
    - the guest register link is deliberately unchanged with data set "enforce"
- [M15b] KILLED - same check added to InviteController::show (GET only) - Tests:    2 failed, 46 passed (868 assertions) (filter: InviteInviterAuthorityTest; vs. the strengthened suite)
    - the guest register page still shows the form after the inviter left with data set "audit"
    - the guest register page still shows the form after the inviter left with data set "enforce"
- [M14] KILLED - V-M14 isActiveMember ignores is_active - Tests:    5 failed, 81 passed (1344 assertions) (filter: InviteInviterAuthorityTest|RoleAssignmentGuardTest; vs. the strengthened suite)
    - a delegate inviter is valid only while the delegation and own membership last with data set "audit"
    - a delegate inviter is valid only while the delegation and own membership last with data set "enforce"
    - a user whose rows here are all inactive is refused with data set "audit"
    - a user whose rows here are all inactive is refused with data set "enforce"
    - is active member is scoped to the org and to active rows
    - M16b disposition: EQUIVALENT. The authority check inside `DB::transaction` after the claim throws, the transaction rolls the claim back, and the response, `used_at`, the role rows and the logs are identical to the shipped behaviour. Only M16a (claim left set outside a rollback) is a real defect shape, and it is killed.
    - M14 killed by case 9 AND by `RoleAssignmentGuardTest` ("a user whose rows here are all inactive is refused"): the Developer's claim is confirmed independently.

Accept-flow and plan re-run mutants (filter `ExistingUserInviteTest|InviteInviterAuthorityTest`):
- [R1] KILLED - remove isAssignableByOrg from invitationFor (plan re-run) - Tests:    1 failed, 82 passed (1136 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - the role is re validated at accept
- [R2] KILLED - remove isUsed check - Tests:    2 failed, 81 passed (1144 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - an invite is one time with data set "audit"
    - an invite is one time with data set "enforce"
- [R3] KILLED - remove isExpired check - Tests:    3 failed, 80 passed (1108 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - an expired invite is refused for the right account
    - the refusal is indistinguishable from an expired invite and hidden from other accounts with data set "audit"
    - the refusal is indistinguishable from an expired invite and hidden from other accounts with data set "enforce"
- [R4] KILLED - drop hasVerifiedEmail - Tests:    1 failed, 82 passed (1143 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - the controller itself refuses an unverified account even without the route middleware
- [R5] KILLED - drop e-mail equality - Tests:    4 failed, 79 passed (1118 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - another signed in account gets a 404 and changes nothing with data set "audit"
    - another signed in account gets a 404 and changes nothing with data set "enforce"
    - the refusal is indistinguishable from an expired invite and hidden from other accounts with data set "audit"
    - the refusal is indistinguishable from an expired invite and hidden from other accounts with data set "enforce"
- [R6] KILLED - claim no longer conditional on used_at IS NULL - Tests:    1 failed, 82 passed (1143 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - an invite used by a concurrent request between load and claim assigns nothing
- [R7] KILLED - drop abort_if($claimed !== 1) - Tests:    1 failed, 82 passed (1143 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - an invite used by a concurrent request between load and claim assigns nothing
- [R8] KILLED - performed_by = invitee instead of inviter - Tests:    14 failed, 69 passed (1138 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - a current admin can re invite after the inviter left with data set "audit"
    - a current admin can re invite after the inviter left with data set "enforce"
    - a delegate inviter is valid only while the delegation and own membership last with data set "audit"
    - a delegate inviter is valid only while the delegation and own membership last with data set "enforce"
    - a universal admin inviter who is also a low level member writes no bypass row at accept with data set "audit"
    ... 9 more failing data sets
- [R9] KILLED - do not set the session org after accept - Tests:    2 failed, 81 passed (1136 assertions) (filter: ExistingUserInviteTest|InviteInviterAuthorityTest; vs. the strengthened suite)
    - the invited account reviews and accepts and gets the role with data set "audit"
    - the invited account reviews and accepts and gets the role with data set "enforce"

Group sweep, batch 1 (`user_management` -> another permission group in `inviterStillAuthorised`; filter `InviteInviterAuthorityTest`):
- organization_management: KILLED (only roles holding user management f keep an invite valid across every seeded role)
- project_management: KILLED (an inviter demoted below full user management invalidates the invite)
- estimate_management: KILLED (an inviter demoted below full user management invalidates the invite)
- procurement: KILLED (an inviter demoted below full user management invalidates the invite)
- product_management: KILLED (a current admin can re invite after the inviter left)
- pricing_management: KILLED (a current admin can re invite after the inviter left)
- approval_authority: KILLED (a current admin can re invite after the inviter left)
- financial_access: KILLED (an inviter demoted below full user management invalidates the invite)

Group sweep, batch 2:
- manufacturer_controls: KILLED (a current admin can re invite after the inviter left)
- reporting_and_analytics: KILLED (an inviter who changed to another full user management role is still valid)
- ai_extraction_review: KILLED (a current admin can re invite after the inviter left)
- system_administration: KILLED (a current admin can re invite after the inviter left)
- quote_rfq_management: KILLED (an inviter demoted below full user management invalidates the invite)
- contract_and_agreement_management: KILLED (an inviter who changed to another full user management role is still valid)
- invoice_and_payment_processing: KILLED (a current admin can re invite after the inviter left)
- order_fulfillment: KILLED (a current admin can re invite after the inviter left)

Group sweep, batch 3 (all 24 other permission groups tried; only `organization_management` needed the new all-seeded-roles test, the others are caught because the valid Owner/Admin inviter lacks F in that group or because case 4's `project_manager` / `financial_admin` hold it):
- returns_and_rma: KILLED (a current admin can re invite after the inviter left)
- catalog_taxonomy_and_data_quality: KILLED (a current admin can re invite after the inviter left)
- compliance_and_certification: KILLED (a current admin can re invite after the inviter left)
- budget_and_cost_control: KILLED (a current admin can re invite after the inviter left)
- delegation_and_impersonation: KILLED (an inviter who changed to another full user management role is still valid)
- audit_and_logging: KILLED (a current admin can re invite after the inviter left)
- seller_payout_and_settlement: KILLED (a current admin can re invite after the inviter left)
- inventory_and_availability: KILLED (a current admin can re invite after the inviter left)

### Final summary - QA amendment round (A1)

**Mutants: 81 run in the scratch copy, every one reverted (scratch `app/` diffed identical to the real `app/` after each batch).** 80 killed, 1 equivalent (M16b). Against the ORIGINAL 30 cases, 5 survived (M4b `F`->`A`, M5b group -> `organization_management`, M11b UA checked after `isActiveMember && checkPermission`, M13f and M13g flash on refusal); all 5 are killed by the new tests (table above). All fifteen plan targets V-M1..V-M15 are covered: M1/M1b (V-M1), M2 (V-M2), M3 (V-M3), M4a-d (V-M4), M5a/b plus the 24-group sweep (V-M5), M6a-f/M33 (V-M6), M7a/b (V-M7), M8 (V-M8), M9a-d (V-M9), M10 (V-M10), M11a-c (V-M11), M12 (V-M12), M13a-g (V-M13), M14 (V-M14), M15a/b (V-M15). Own mutants: audit row on denial (M25/M25b), claim before the check (M16a), delegate without own membership (M2, M6f, M34), invitee-as-UA (M26), null dereference (M27), wrong org source in one check only (M6e/M6f/M33), authority for the invitee instead of the inviter (M19a/b), and the plan's `isAssignableByOrg` re-run plus the accept-flow mutants R1-R9, all killed.

- **Disposition of V-M4 `F`->`A`**: the Developer's "equivalent in the seeded matrix" is confirmed (no seeded role holds `user_management` exactly `A`), but it is NOT equivalent in production because of C1 (a `user_management` F holder can mint a role with `user_management` A). A test with such a role now kills it.
- **Disposition of M14** (`isActiveMember` ignores `is_active`): killed by case 9 and by `RoleAssignmentGuardTest` ("a user whose rows here are all inactive is refused"); confirmed independently.
- **M16b equivalent**: see above (rollback makes it observationally identical).
- **Plan re-run** (remove `isAssignableByOrg` from `invitationFor`): killed by `ExistingUserInviteTest::test_the_role_is_re_validated_at_accept`.

**Defects found: none.** The shipped `inviterStillAuthorised` is correct against every mutant and the new tests; the only gaps were in test strength (listed above), now closed. No application code was touched.

**Tests added** (all in `tests/Feature/Rbac/InviteInviterAuthorityTest.php`; the 12 original methods are unchanged; plus 3 imports `PermissionMatrix`, `PermissionService`, `Schema`): 9 methods x audit/enforce = 18 new cases, 30 -> 48 cases in the file. Documentation that says "30 cases" for this file (TESTING.md, the Client audit 2 tests list and the baseline line, PROGRESS.md) needs updating to 48 and the full-suite figure to 1698 / 4 (QA may only edit `tests/`, so the docs are left for the Developer/Architect).

**Full suite (real tree, in-memory sqlite, background run to a scratchpad file)**: **1698 passed / 4 failed** (18200 assertions) = the expected 1680 + 18 new cases. Failing names, all known baseline, none new:
1. `Tests\Feature\ExampleTest > the application returns a successful response` (Laravel scaffold)
2. `Tests\Feature\Rbac\AuditMiddlewareTest > enforce mode blocks and records blocked`
3. `Tests\Feature\Rbac\SeedMatrixTest > seeds expected counts`
4. `Tests\Feature\Rbac\SeedMatrixTest > phase distribution`

**Flakiness**: `InviteInviterAuthorityTest` + `ExistingUserInviteTest` run twice on the real tree: 83 passed (1146 assertions) both times (48 + 35).

Not covered here (needs MariaDB or a browser, unchanged from the earlier QA round): two simultaneous accepts of one invite, MariaDB case-insensitive e-mail matching on the inviter side, a real browser mail -> login -> accept flow.

Scratch artifacts (outside the repo): `<scratchpad>/qa2/mutrun.py` (all mutant definitions), `results.txt`, `full-amend.txt`.

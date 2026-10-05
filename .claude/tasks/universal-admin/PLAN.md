# PLAN: Universal admin (Option B, real cross-org bypass)

Branch: cut from `fix/p2-cleanup`. Mode: STRICT (touches the permission engine). Author: Architect, 2026-10-05.

## 1. Goal
One or a few named people (the owner's accounts) can open any organization and any project, see and do everything the permission system guards, without being added to each org or project. They pick the org they want to work in, and every action where the bypass was actually needed is recorded as "acted as platform admin". Everyone else, including org owners and today's `role='admin'` accounts, is checked exactly as now.

## 2. Findings that shape the design (verified in code)
- `routes/web.php:115`: ALL user/org/project/quote/RFQ routes sit behind `checkRole:user`. `CheckRole` redirects `users.role='admin'` to login. So a `role='admin'` account cannot even reach the screens a universal admin must use. Reusing `role='admin'` as the identity would force changes to ~10 views that test `role === 'user'` (navbar x8, sidebar, dashboard). Rejected.
- `RbacAudit` already skips `role='admin'`, and `@canDo`/`@cannotDo` (AppServiceProvider) already short-circuit it. Controllers do not: `requireOrgLevel`, `RfqController`, etc. call `checkPermission()` with no org role, so they 403.
- Project visibility is NOT in `checkPermission` only. `Project::scopeVisibleTo` (membership join) is used by ProjectController, ProjectMemberController, RfqController, QuoteController, workspace, crosswalk and by `Quote::scopeVisibleTo` (which wraps it).
- Org context is membership based in 5 places: `CurrentOrg::id`, `OrgSwitchController`, the navbar composer (AppServiceProvider), and four duplicated private `currentOrg()` methods (`OrgAdminController`, `OrgSettingsController`, `DelegationController`, `ApiTokenController` ~L87) that read the session key directly and require a `user_org_roles` row (`ProjectMemberController` already uses `CurrentOrg::id`, it is NOT a copy). Also `RbacAudit::currentOrgId()/userBelongsToOrg()` (L228-255) has its own membership-based copy, and raw `session(...)` org reads exist in OrderApprovalController (21, 68), OrderController (18, 27, 47), CheckoutController (210, 228) and 9 views. `OrgAdminController::myRoles` lists roles by membership (fine, self scope).

## 3. Design decisions
### 3.1 Identity (no migration)
Recommend: an explicit allow-list of USER IDS in config: `config/rbac.php` key `universal_admin_user_ids`, filled from env `UNIVERSAL_ADMIN_USER_IDS` (comma list). New class `App\Support\Rbac\UniversalAdmin::is(int|User $user): bool` (static, request-cached). Reasons: no schema change; not editable from any web screen (an `rbac_settings` row or `users` flag could be granted by anyone who reaches the admin panel); ids not emails (profile PUT can change email, an id cannot be hijacked that way). Also require the user to exist and `email_verified_at` not null. The account keeps `users.role='user'`, so `checkRole:user`, navbar and dashboards work unchanged. Cost: granting/revoking = an env change plus `config:clear`/deploy (a feature, document it). Alternative if the client wants in-app granting: a new `users.is_universal_admin` column = a migration, flagged, NOT recommended now.
Existing `role='admin'` accounts are not universal admins unless their id is also in the list (they keep /admin catalog access via checkRole:admin).
`checkRole:admin` must also admit a universal admin so they get /admin/rbac too: one line in `CheckRole`. (Guard test says every mapped admin/ route sits behind checkRole:admin; still true.)

### 3.2 Where the bypass lives (central, one rule)
1. `PermissionService::checkPermission` (the only decision point): at the top, BEFORE delegation, `if UniversalAdmin::is($userId)`: require the org exists; if `$projectId` given require the project exists, is not soft-deleted and `projects.org_id == $orgId` (cross-org project ids still fail); then return true. Compute the real result first so the audit row (3.4) is written only when the bypass changed the answer. Bypass tests the ORIGINAL `$userId` only: a delegate whose principal is a universal admin must not inherit it (delegation resolution result is never tested). Delegations to/from a universal admin stay as they are.
2. `Project::scopeVisibleTo`: if `UniversalAdmin::is($userId)` return `where projects.org_id = $orgId` (no membership join). This single change covers Quote::visibleTo, RFQ lists, project list/show, workspace, crosswalk, because they all derive from it. `Quote::scopeVisibleTo` passes `$members` from `checkPermission(...R)` which is true for universal.
3. `RbacAudit`: no bypass code, but `currentOrgId()` must change (see 11.1). It then calls `checkPermission`, so universal passes through the same engine and gets logged. It still fails closed on `no_org` and `project_unresolved` (a quote with NULL project stays hidden: data problem, not a permission problem).
4. Not bypassed on purpose: `ApprovalRoutingService`, `SodService`, `isOwner()` (org delete and ownership transfer, see 3.5), RFQ business rules (relationship, status), `Project` delete refusals.
5. Per-controller checks need no edit (they call checkPermission) EXCEPT the membership-based org lookups below.

### 3.3 Org context ("current org" without membership)
- `CurrentOrg::id`: for a universal admin the session org is valid if the org exists (no role row needed). If none chosen, fall back to their own first membership, else null. Replace the 4 duplicated `currentOrg()` bodies (`OrgAdminController`, `OrgSettingsController`, `DelegationController`, `ApiTokenController`) with `CurrentOrg::id` plus `Organization::find`; behaviour for normal users must be byte-identical (same fallback rule).
- `OrgSwitchController::switch`: universal admin may switch to any existing org; each switch writes an audit row (`reason=universal_org_switch`). Normal users unchanged.
- Navbar composer (`user.layouts.navbar` data): for a universal admin `navUserOrgs` is all orgs. Hundreds of orgs: render a searchable select (Developer/UI), not a plain list. With no org selected, show "Select organization" and redirect org-needing pages to it.
- `RbacAudit` session seeding stays (own first org).
- `@canDo`/`@cannotDo`: add the universal check next to the `role==='admin'` line (they already call checkPermission, so universal passes anyway; keep it simple, no duplicate logic: just let them call checkPermission, and make them use `CurrentOrg::id` instead of raw session).
- Banner in the user layout, always visible when acting as universal admin: "Platform admin mode - <org name>" (also clearly differentiates the account in the client's screenshots).

### 3.4 Audit trail (no migration)
- Table `rbac_audit_logs` (outcome and reason are plain strings, so no schema change). When the bypass changed an answer, write one row per request and distinct (org, group, level, project): `user_id`, `org_id`, `method`, `route_uri`, `permission_group`, `required_level`, `project_id`, `outcome='allowed_universal_admin'`, `reason='acted_as_platform_admin'`. Org switches: `outcome='allowed_universal_admin'`, `reason='universal_org_switch'`. Implemented in `UniversalAdmin::recordBypass()` using the request route.
- Data-changing admin actions already write `role_assignment_logs` (performed_by) and `project_member_logs` (performed_by = the admin); no change, but the org audit-log page will show an admin who is not in the org: fine, label by name.
- Second sink (org deletion wipes `rbac_audit_logs` rows by org_id at RbacController:461/532 and OrgSettings:88): also `Log::channel('daily')->info('universal_admin', [...])` for the same events. Keep the log permanently.
- Surface: add `allowed_universal_admin` to the outcome filter on the admin RBAC audit page and the org audit page (Developer to locate the filter in `Admin/RbacController` lines ~215-245). Existing counters `would_block/blocked` unaffected.

### 3.5 Safety rules
- Org delete / ownership transfer: keep `isOwner()` strict, so a universal admin cannot delete or transfer an org through the org settings screens. They can still use the existing platform-admin delete screens (`Admin/RbacController`), which already refuse orgs with projects. No new capability.
- SoD: `SodService` is about the target user's roles and stays enforced when the universal admin assigns roles. The universal admin holds no `user_org_roles`, so SoD never sees them.
- No impersonation (not requested; "acts as themself in another org"). The user/id recorded is always the real person.
- Approvals: the universal admin is NOT counted in `approverPool` (it reads role tables, stays untouched). So an org with no real approver still refuses conversion/checkout, and "sole approver auto-approve" is unaffected. The universal admin MAY approve/reject an order through `OrderApprovalController` (approval_authority A passes) - logged. Recommend allowing: otherwise a stuck org cannot be unblocked. They also pass procurement S to convert RFQs but routing still treats them as a non-approver requester, so their orders go to `pending_approval` (safe; no self-approval shortcut).
- Sellers never see a buyer's project: unchanged. `RfqSellerController` query still omits `project_id` even for a universal admin acting in the seller org. They see the buyer's project only by switching to the buyer org (a deliberate, logged act).
- Project creation by a universal admin enrols them as a member (existing `enrol`, unchanged). Acceptable; they then appear in the member list.
- Rate/blast radius: ids are env-only; keep the list to 1-3 people; require verified email.

## 4. Data model
No migration, no new table, no new permission_group or role (stays 25 groups / 49 roles). The `platform_super_admin` org role is unchanged and unrelated. New config key + env var only. Deviation from client plan §3.4 (org_id scoping for everyone) is intentional and limited to listed ids: FLAG to the client in writing.

## 5. Permission mapping
No new routes. No `route_permission_map.php` change. Existing mapped routes now return allowed for listed ids. `org/switch` stays an `OPEN_ROUTES` entry; its membership check becomes "membership OR universal admin". Non-listed users: identical results.

## 6. File list
Create: `app/Support/Rbac/UniversalAdmin.php`; `tests/Feature/Rbac/UniversalAdminTest.php`.
Touch: `app/Http/Middleware/RbacAudit.php` (currentOrgId, remove userBelongsToOrg); `OrderApprovalController`, `OrderController`, `CheckoutController` (raw session reads -> `CurrentOrg::id`); 6 views (11.3); `config/rbac.php`; `.env.example`; `app/Services/Rbac/PermissionService.php`; `app/Models/Project.php` (scopeVisibleTo); `app/Support/Rbac/CurrentOrg.php`; `app/Http/Controllers/Frontend/OrgSwitchController.php`; `OrgAdminController.php`, `OrgSettingsController.php`, `DelegationController.php`, `ApiTokenController.php` (currentOrg only); `app/Http/Middleware/CheckRole.php`; `app/Providers/AppServiceProvider.php` (navbar composer, canDo/cannotDo); `resources/views/user/layouts/navbar.blade.php` + user layout (org picker, banner); `app/Http/Controllers/Admin/RbacController.php` + audit views (outcome filter); `ARCHITECTURE.md` (section 1, 4.2, 12; change log), `HANDOVER-CLIENT.md` (how to grant, how to test as a normal user).
Read-only checks for Developer: `RbacAudit::currentOrgId`, `Quote::scopeVisibleTo`, every `session(config('rbac...'))` read (grep) for missed membership lookups, `OrderApprovalController` org scoping.

## 7. Test plan (PHPUnit classes, no Pest)
`UniversalAdminTest`:
1. Listed user, no roles, no membership: `checkPermission` true for all 25 groups at F in an org they do not belong to, with and without projectId (project of that org). Same user with a project id from ANOTHER org: false. Soft-deleted project: false. Org that does not exist: false.
2. Unlisted org owner and unlisted `role='admin'` user: still denied in a foreign org and on a non-member project (regression).
3. Listed id but unverified email: denied. Config empty: denied. Listed id changed email: still the same person (id based).
4. Delegate of a universal admin does NOT get the bypass.
5. HTTP, audit AND enforce mode: universal admin on org B (switch first) can GET/POST representative routes of each controller family (org-admin team/roles, settings update, project show/update/members, quote create, RFQ create/convert, approvals approve, crosswalk write, API tokens, delegations) and sees projects they are not a member of; unlisted user cannot (403/404).
6. `org/switch`: universal -> any existing org OK (and audit row); normal user -> other org refused; session org with no org selected -> picker redirect.
7. Seller scoping: universal admin in seller org: incoming RFQ payload has no `project_id`/project name.
8. Approvals: universal admin not in `approverPool`; zero real approvers + universal requester still refused; sole approver auto-approve unchanged.
9. Org delete and ownership transfer by universal admin through org-settings: 403 (isOwner).
10. SoD still blocks a conflicting role assignment made by the universal admin.
11. Audit: bypass writes exactly one `rbac_audit_logs` row per request/distinct check with `outcome=allowed_universal_admin`; no row when the user already had the grant naturally; org-switch row; normal users write none.
Mutation targets (QA scratch copy): remove the project-org match in the bypass; test the effective instead of original user id (delegation leak); drop the verified-email condition; make the allow-list match on email; remove `recordBypass`; remove the bypass in `Project::scopeVisibleTo`; make `isOwner` accept universal; widen `approverPool`; change `CurrentOrg` so any user (not only listed) may use an unowned session org.
`AuditFindingsRound2Test`: not expected to break (actors are `viewer_read_only` and `inventory_manager`, not listed). Add to it: assert both actors are not universal admins (config empty in tests), and one new guarded case: listed user passes every mapped route (not 403) in both modes. The route-count/allow-list tests are untouched because no route changes. Known baseline stays 4 failures (2 SeedMatrixTest, AuditMiddlewareTest enforce-302, plus the fourth listed in TESTING.md); suite expected 1016 passed + new tests, 4 failed. Do not "fix" the 4.

## 8. Risks / client-audit impact
- The client's RBAC audit must use a NON-listed account for every negative test; a listed account will pass everything by design. Provide a dedicated test org owner and a viewer account; never allow-list them. Document in `HANDOVER-CLIENT.md`.
- Audit-mode vs enforce: the bypass sits in the engine, so it works in both modes.
- Known open items touched: (a) rep-agency authorization bug (`OrgRelationshipService`) - not touched, the bypass does not cover it; (b) delegation Q11 - bypass is explicitly non-transitive, adds no new behaviour; (c) audit middleware 302 - unchanged, listed users never reach it; (d) org-type count, `api_system` - not touched.
- Direct DB reads that use `user_org_roles` for "who is in the org" (team lists, approver pool, mention/assignee pickers) will not include the universal admin: intended.
- Single point of failure: a wrong id in env gives full access. Keep deploy review and 2 admins max.
- Risk of missed membership-based lookups (grep list in section 6); QA must smoke the orgs the admin has never joined across all screens.

## 9. Steps
Developer (log to PROGRESS.md): D1 `UniversalAdmin` class + config/env; D2 `checkPermission` bypass (before delegation) with project/org integrity and audit; D3 `Project::scopeVisibleTo`; D4 `CurrentOrg` + replace the 4 `currentOrg()` copies; D5 `OrgSwitchController` + navbar composer + `canDo` + CheckRole + picker/banner views; D6 outcome filters + docs. Architect compliance pass (REVIEW.md) after D6. QA: section 7 tests + mutation proofs + full-suite baseline compare. Verifier: one pass on engine bypass, delegation non-transitivity, org-id integrity, owner-only actions, seller scoping.

## 10. Open questions (recommended default in brackets)
1. Identity: env id allow-list [yes] vs new DB column (migration) [no].
2. Are current `role='admin'` accounts also universal? [No; list their ids explicitly if wanted.]
3. May the universal admin approve/reject orders? [Yes, logged; never in the approver pool.]
4. Org delete / ownership transfer through org settings for non-owner universal admin? [No; use existing /admin screens.]
5. Reads logged too? [Yes, only when the bypass was needed; may be noisy, can limit to non-GET later.]
6. Banner/persistent "platform admin mode" indicator [Yes].
7. Written client sign-off on the deviation from plan §3.4 [Required before enabling in production].

## Checkpoint 1 (human decides)
- [ ] Approve env id allow-list, no migration (Q1).
- [ ] Approve: role='admin' accounts not automatically universal (Q2).
- [ ] Approve: can approve orders, not in approver pool (Q3).
- [ ] Approve: org delete/transfer stay owner-only (Q4).
- [ ] Approve audit design: rbac_audit_logs rows plus permanent log file (Q5).
- [ ] Client acknowledgment of the §3.4 deviation and of using unlisted test accounts (Q7).

## 11. Amendment 1 (Developer findings, verified in code)
### 11.1 RbacAudit org resolution
`RbacAudit::currentOrgId()` re-implements `CurrentOrg::id` with a role-row check, so a universal admin switched to org B would be judged in their own org. Change: keep the session seeding block exactly as is (it runs first, so the session key is set), then make `currentOrgId()` return `CurrentOrg::id($userId)` and delete `userBelongsToOrg()`. `CurrentOrg::id` reads the same session store (RbacAudit runs after StartSession in the web group). Normal users: identical result, because the new `CurrentOrg` branch for listed ids is the only difference (session org valid if own role, else first active org, else null). `ProjectMembershipTest::test_a11` compares `CurrentOrg::id` with the `org_id` the middleware logs for four cases (valid session, stale session, none, no orgs); delegation makes them agree by construction and the test must stay green unchanged. Add a fifth a11 case in `UniversalAdminTest`: listed user, session org B with no role row -> both return B.
### 11.2 Duplicates
Correct list of private `currentOrg()` copies: `OrgAdminController`, `OrgSettingsController`, `DelegationController`, `ApiTokenController`. Each becomes `$id = CurrentOrg::id((int) Auth::id()); return $id ? Organization::find($id) : null;`. Normal-user behaviour identical (same rule).
### 11.3 Raw session-org reads
Rule: any server-side read that decides data or access must use `CurrentOrg::id`; the raw session value is only safe where a membership check is not needed, which is never for a listed user with a stale value, so convert the controllers and leave presentational views on a shared helper.
- MUST change (controllers): `CheckoutController` 210 (approval routing) and 228 (`orders.org_id`) - orders must carry the validated org, also fixes the stale-key case QA noted; `OrderApprovalController` 21 and 68, `OrderController` 18, 27, 47 (they filter orders by org). Use `CurrentOrg::id`; if null keep each action's present behaviour (empty list or abort).
- Views (`user/layouts/sidebar`, `user/dashboard`, `user/org-admin/_nav`, `org-admin/index:345`, `frontend/quotes/index`, `frontend/lists/listDetail`, `user/customers/index`, `user-products/index`, `user-services/index`): they only decide which buttons show (the server re-checks). They can stay on the raw session value because `OrgSwitchController` stores the chosen org, but for a listed user whose session is unset they would hide buttons. Cheap fix with no behaviour change for others: one Blade-callable helper `CurrentOrg::id(auth()->id())` in each of those 9 reads (2 lines each). Recommend doing it; low risk, and it keeps the panel consistent with the controllers.
- Universal admin with no org selected: `CurrentOrg::id` returns their own first membership, else null (they usually have none). Null means "no org context": nothing org-scoped is shown or writable, no data from any org leaks, mapped routes fail closed as `no_org` (redirect with message in enforce mode, log only in audit mode, then the controller aborts 403/redirects).
### 11.4 Null-org redirect (one place)
In `CurrentOrg` itself no redirect (it is a plain helper). Put it in ONE place: the existing `RbacAudit` middleware is wrong (only mapped routes, and it logs). Use a small new route middleware `universal.org` is NOT wanted (route edits break the guard test's route list). Recommended: `UserWorkspaceController` (the post-login landing, `user-dashboard`/`workspace`) and `OrgAdminController::index/overview` redirect a listed user with null org to a new picker section on the dashboard (the navbar picker from 3.3 is the control). Everywhere else the existing 403/redirect-with-error behaviour stays, and the navbar always shows the "Select organization" picker for listed users, so they are never stranded. No new route, no map entry, no OPEN_ROUTES change.
### 11.5 Updated steps
D1 `UniversalAdmin` + config/env. D2 `checkPermission` bypass + audit. D3 `Project::scopeVisibleTo`. D4 `CurrentOrg` + `RbacAudit::currentOrgId` delegation + 4 `currentOrg()` copies. D5 raw-session reads in OrderApproval/Order/Checkout controllers + the 9 view reads. D6 `OrgSwitchController`, navbar composer/picker/banner, `CheckRole`, `canDo/cannotDo`, null-org redirect (11.4). D7 outcome filters + docs.
### 11.6 Updated tests
Add: a11 fifth case (above); order created at checkout by a listed user in org B has `orders.org_id = B` and is routed with B's approver pool; stale session key (user lost the role) for checkout and order list falls back like `CurrentOrg` (org_id = first active org, not the stale one); `OrderController`/`OrderApprovalController` list only the validated org's orders for a normal user (regression); listed user with no org: dashboard redirect to picker, org-scoped pages show nothing, no cross-org data in any response. New mutation targets: revert `RbacAudit::currentOrgId` to the membership copy; revert CheckoutController 228 to the raw session value.
### What changed in the plan
(1) RbacAudit now delegates to `CurrentOrg::id` and a11 is checked. (2) Duplicate list corrected: ApiTokenController in, ProjectMemberController out. (3) Raw session reads: 6 controller sites must change, 9 views get a helper read, behaviour with no selected org defined. (4) Null-org redirect lives in the dashboard/org-admin landing plus the always-present navbar picker. Steps D1-D7, tests and file list updated.

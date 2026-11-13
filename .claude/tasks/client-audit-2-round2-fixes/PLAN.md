# Plan: client-audit-2-round2-fixes (Mitch's round-2 list, items 1 and 2)

Status: APPROVED (Checkpoint 1, 2026-11-13) with the Architect's recommended defaults D1 (option B), D2 (i), D3, D4 (A+B+C), D5 (keep link), D6 (warn only). The human said "show me the plan ... and start working on it" after the plan summary was presented; R9 (delegated order id) is NOT approved and stays out.

Written by the Architect on 2026-11-13. Branch `feature/client-audit-2-round2-fixes`, cut from `release/1.2-prod` (HEAD `3026313`), strict mode. Baseline: 2039 passed / 4 failed (the 4 known: `ExampleTest`, `AuditMiddlewareTest` enforce-mode, `SeedMatrixTest` x2).

Scope rule (human, hard): only what Mitch asked in items 1 and 2 of his message. No extras, no cleanups, no speculative abstraction. Items 3, 4 and 5 of his message are the user's production tasks and are listed once, at the end, under "Out of scope / user actions".

Sources read: `CLAUDE.md`, `ARCHITECTURE.md` (4.3-4.7, 10b, 12), the end of `.claude/tasks/client-audit-2-fixes/PROGRESS.md`, `docs/HANDOFF-client-audit-2-2026-10-28.md`, the client's `WB_RBAC_Unblock_the_Audit - To Shafiq.docx` (26 Sep 2026), `Revised_RBAC_Module_Implementation_Plan.pdf` (sections 6.1-6.3), `WB_Workspaces_RBAC Front.pdf`, and the live code. The "WB RBAC - Audit 1.0 (Basic Limited Role Audit).pdf" is NOT on this machine (searched `~/Downloads`, `~/Documents`).

## Goal

After this round, Mitch's list reads as follows from the user's side. (1) Whatever a member creates inside a project (the project, an estimate, an RFQ), they are a member of that project afterwards (M12; the exact meaning needs the client's sheet row, see D2). (2) Another account's name or e-mail can no longer run as markup or script in the Team page drawer, the two invite pop-ups, or the platform-admin screens that show other accounts (S15). (3) Inviting an e-mail address looks identical whether or not it already has an account: same JSON answer, same link form, same e-mail wording, same pop-up (O11). (4) An Organization Owner can hold Estimator + Executive Approver and Procurement Manager + Executive Approver in a team organization, not only in a one-person organization (N01; needs a rule decision, see D1). (5) Removing a member's last role shows a specific warning that names the member, says the role is their last one and that removing it removes them from the organization, and the server refuses to do it without an explicit confirmation (L08). (6) The invite pop-up buttons that are demonstrably broken work (W12; the rest needs the sheet row). W10, L05, L06 and S13 are already built and deployed; this round only adds a replay test in the client's own words and lists what stays with the user (for S13: `APP_DEBUG` on the server).

| Client item | Status | Plan tasks |
|---|---|---|
| M12 creator is a member of anything they create | NEEDS HUMAN INPUT (sheet row M12). Safe default is small NEW WORK | M1-M3, D2 |
| S15 escape account names in the invite pop-up | NEW WORK | S1-S4, D4 |
| N01 Owner holds Estimator/Procurement Manager + Executive Approver | NEEDS HUMAN DECISION (recommend option B) | N1-N2, D1 |
| W10 Owner sees all org projects | ALREADY DONE - VERIFY ONLY | V1 |
| L05 log auto-approvals | ALREADY DONE - VERIFY ONLY (no gap found) | V2 |
| L06 log delegations | ALREADY DONE - VERIFY ONLY (one limitation, R9) | V2 |
| S13 JSON errors show debug details | ALREADY DONE in code - VERIFY ONLY; remainder is server config (user) | V3 |
| O11 invite must not reveal an account | NEW WORK | O1-O5, D5 |
| L08 warn before removing a last role | NEW WORK | L1-L5, D6 |
| W12 invite pop-up buttons | NEW WORK for the clearly broken parts; the rest NEEDS HUMAN INPUT (sheet row W12) | W1-W3, D3 |

## Data model changes

None.

- No migration. No new table, column or index. No new env key, no new config key.
- No new `permission_group` and no new role: the 25-group / 49-role baseline is unchanged.
- `sod_conflict_rules` is not touched (N01 option C would be a data change; it is not recommended).
- Reused as they are: `project_members` and `project_member_logs` (M12, through `ProjectMember::enrol`), `org_invites` (O11), `user_org_roles` and `role_assignment_logs` (L08, N01).
- Rollback is redeploying the previous release.

Behaviour deviations from the original client plan that must be acknowledged, not assumed:

1. N01 option B (recommended): an Organization Owner is exempt from the two seeded SoD pairs that sit inside the registration bundle (Procurement Manager + Executive Approver, Estimator + Executive Approver), in every organization, not only when alone. The client's plan section 6.1 itself gives "solo or small" buyer organizations Owner + Procurement Manager + Estimator + Executive Approver on day one, so this is consistent with 6.1, but it narrows what plan section 6.2 calls "deliberately different people".
2. M12 default: the creator of an estimate or RFQ becomes an explicit project member. ARCHITECTURE 4.7 says the Owner is "not auto-enrolled" and that "the creator auto-enrol ... [is] as before" (it covers project creation only). The default extends creator auto-enrol to estimates and RFQs, so an org-wide creator (Owner) gets an explicit membership row on first creation. Listed again in D2.

## Permission mapping

Only one route is new. Every other route keeps its existing map entry; they are listed because their behaviour or response changes. Every mapped controller action keeps its server-side level check (`requireOrgLevel` pattern) as it is today; the new checks below sit after those checks and never replace them.

| Route | Method | Permission group | Level | Batch | Project-scoped? | Change |
|---|---|---|---|---|---|---|
| `invite/{token}` (name `invite.entry`) | GET | none (public, like `register/invite/{token}`) | - | - | no | NEW. Guest or signed-in. Signed-in: 302 to `invite/{token}/accept`. Guest: renders the invite register page. No `config/route_permission_map.php` entry and no `OPEN_ROUTES` entry: it carries no `auth` middleware, so `AuditFindingsRound2Test` (which only looks at `auth` routes) does not see it, the same as `register/invite/{token}`. A test pins that it has no `auth` middleware. |
| `org-admin/invite` | POST | `user_management` | F | admin | no | Map unchanged. Answer is now one shape and one link form for every address (O11). |
| `register/invite/{token}` | GET, POST | none (guest) | - | - | no | Map unchanged. POST gains a guard: an address that already has an account creates nothing and goes to login (today it would hit the unique index on `users.email`). |
| `invite/{token}/accept` | GET, POST | none, listed in `OPEN_ROUTES` | - | - | no | Unchanged. |
| `org-admin/roles/{userOrgRole}` | DELETE | `user_management` | F | admin | no | Map unchanged. New optional `confirm_last_role` input; without it, a member's last active role in the org is not removed (L08). |
| `org-admin/peel-off` | POST | `user_management` | F | admin | no | Map unchanged. Same `confirm_last_role` rule for the acting user's own last role (L08). |
| `org-admin/roles` | POST | `user_management` | F | admin | no | Map unchanged. JSON gains `peel_off_last_role` (L08). SoD result changes for Owners (N01-B). |
| `projects/{project}/quotes`, `projects/{project}/quotes/create-from-list/{listId}` | POST | `estimate_management` | S | write | yes (`project_param`) | Map unchanged. Creator is enrolled on the project (M12 default). |
| `quotes/{id}/duplicate` | POST | `estimate_management` | O | write | yes (`quote_param`) | Map unchanged. Same enrol (M12 default). |
| `rfq` | POST | `quote_rfq_management` | S | write | project id in the body, checked in the controller | Map unchanged. Same enrol (M12 default). |

Pure view changes (no route or map change): `GET org-admin` (Team page: drawer, last-role modal, invite pop-up), `GET org-admin/overview` (invite pop-up), `GET org-admin/api-tokens`, `GET admin/rbac/users`, `GET admin/rbac/organizations/{organization}`, `GET admin/rbac/service-accounts`.

`checkPermission()`'s `projectId` is a `projects.id` (the Architect agent definition still says `quotes.id`; `CLAUDE.md` and ARCHITECTURE section 2 are right). `quote_param` routes are resolved to the quote's project by `RbacAudit`.

Every new check must hold in audit mode as well as enforce mode (CLAUDE.md): the L08 confirmation, the O11 register guard and the M12 enrol all live in the controllers, not in the middleware.

## Findings (evidence behind the decisions)

### F1. S15: every place where JS or markup puts another account's text into HTML

Why the client's finding exists: before the "3c" change of the last round, `generateInvite` answered an existing address with `type: confirm_existing` and the existing account's registered name (`user_name`), and the pop-up pasted it into `innerHTML`. A registered name is attacker-chosen, so that was stored XSS across accounts. The controller no longer sends `user_name` (it now sends one shape, `type: invite`), but the branches that print it are still in the pages, and the live branches still paste the inviter's own `email` and `name`.

| # | Site | What is interpolated | Who authored it | Plan |
|---|---|---|---|---|
| 1 | `resources/views/user/org-admin/index.blade.php`, `doInviteFetch` (about lines 538-620) | `${data.error}`, `${data.message}`, `${data.user_name}`, `${data.role_name}`, `${email}`, `${name}`, `${link}` into `innerHTML`; and `'${email}'`, `'${name}'` inside an inline `onclick` string | `email`/`name`: the inviter; `data.user_name`: another account (dead branch) | FIX (Tier A, the client's item). Delete the dead `confirm_existing` / `existing` branches and `confirmExistingAssign`; build the live result with `textContent` / created nodes (or one local `escapeHtml`); no interpolation into `onclick`. |
| 2 | `resources/views/user/org-admin/overview.blade.php`, the same function (about lines 280-345) | Same list | Same | FIX (Tier A). Same change, same result. |
| 3 | `index.blade.php`, `renderDrawer` (about lines 387-446) | `${m.name}`, `${m.email}`, `${r.roleName}` and the `${r.category} - ${r.name}` option labels, all into `innerHTML` | `m.name`, `m.email`: every other member (their registered name); role names: editable by any `user_management` F holder (ARCHITECTURE open item 6) | FIX (Tier B: other accounts' names and e-mails; same attack, it fires the moment the Owner clicks a member). `@json` already hex-escapes the data inside `<script>`, so only the JS side is unsafe. |
| 4 | `resources/views/admin/rbac/users.blade.php`, user drawer (about lines 255-285) | `og.org_name`, `og.org_type`, `r.name`, `d.from` (the principal's account name) into `innerHTML`. `u.name` and `u.email` already use `textContent`. | Other accounts and orgs, shown to the platform admin | FIX (Tier B). Highest-impact victim (platform admin). |
| 5 | `resources/views/admin/rbac/organization-show.blade.php:78` | `onclick="return confirm('Remove {{ role }} from {{ member->name }}?')"` | Another account's name | FIX (Tier B). `{{ }}` HTML-escapes `'` to `&#039;`, but the browser decodes the attribute before running the JS, so a name containing `'` breaks out of the string. Use `@js(...)` for the whole message. |
| 6 | `resources/views/user/org-admin/api-tokens.blade.php:114` and `resources/views/admin/rbac/service-accounts.blade.php:171` | `confirm('Revoke token {{ $token->name }}?')`, same attribute problem | The token's owner (a token label, not an account name). Other users' tokens show only to a listed universal admin (api-tokens page) and to platform admins (service-accounts) | Tier C. Same one-line fix as 5. Recommended IN because the victim is a platform admin; strike at Checkpoint 1 if you want only "account names" (D4). |

Left alone, with the reason (checked, not changed):

- Server-rendered `{{ }}` text nodes everywhere, including the Team table, the audit pages (the delegation reason string carries account names and is printed through `{{ }}`), connections, `invite-accept`, `invite-register`, `invite-invalid` and the mail `emails/org-invite.blade.php`: Blade escapes them and there is no `{!! !!}` in any of these files.
- `@json(...)` blocks inside `<script>`: Laravel's `@json` uses the HEX flags, safe in a script block.
- `index.blade.php:482` `form.innerHTML` (CSRF token only) and the `alert()` texts that are static strings.
- Customers and Estimates screens: their JS already escapes through `escapeHtml(...)` (`user/customers/index.blade.php:389`, `frontend/quotes/index.blade.php:604`). Customer and company names are org data, not account names.
- The remaining `{!! !!}` sites in `resources/views` render static strings, admin-authored blog/product HTML, JSON for state tax and a DataTable; none renders an account name.
- Convention to follow: each affected screen defines its own local `escapeHtml` (5 lines, as the two screens above do). Do not introduce a shared asset.

### F2. O11: every observable difference between "has an account" and "has none"

Read in `OrgAdminController::generateInvite` (:420), `Mail/OrgInviteMail`, `emails/org-invite.blade.php`, `Auth/InviteController`, `Auth/InviteAcceptController`.

| Difference | Seen by | Fix |
|---|---|---|
| JSON `link`: `/invite/{token}/accept` for an existing account, `/register/invite/{token}` for a new one. Everything else in the JSON is already equal. | the inviter | One link form: `url('/invite/' . $token)` (new public route `invite.entry`) |
| `User::where('email', ...)->exists()` decides the branch | server only (a query), but it is the source of every other difference | Remove the lookup from `generateInvite` and the `$existingAccount` parameter from `OrgInviteMail` |
| Mail: different link, button text ("Sign in to accept" / "Accept invitation") and an extra paragraph | the invitee (and an inviter who is also the invitee) | One text for both: how to accept; "new to Wisselbanken: create your account; already have an account: sign in with this address" |
| Opening the link as a guest: the new-account form versus a redirect to login, if the single entry decided by account existence | the inviter, who holds the token | The guest page is the same page for both cases (the existing register form with a clear "Already have an account? Sign in to accept" link). The entry never branches on existence. |
| `POST register/invite/{token}` for an address that already has an account: hits the unique index on `users.email` and ends in a 500, a different outcome from a new account's success | whoever holds the token | Guard: if an account with the invite address exists, create nothing, send the person to login with the accept page as the intended URL and the flash `status` "Sign in to accept this invitation." |
| `email_sent` | the inviter | Same code path for both, nothing to fix |

Timing and side channels: after the change both paths run the same queries and the same mail send, with no `users` lookup at all, so nothing differs but the data. Cost is lower than today.

Residual leak that this plan does NOT close (D5, risk R4): the inviter holds the token. If the inviter opens the link as a guest and submits the register form, a NEW address gets an account (and a logged-in session for the inviter: the pre-existing hole in ARCHITECTURE 12 items 8 and 13) and an EXISTING address gets the redirect to login. That is observable. Closing it needs a mailbox-verification step on guest registration (items 8/13), which is a separate decision.

Checked and left alone: public `POST register` ("e-mail already taken") and password reset ("no user with that address") are standard signup/recovery behaviour, not invite behaviour; `OrgSettingsController::transferOwnership` already answers the same 403 for unknown and non-member addresses (Phase A); `DelegationController` takes no e-mail; `assignRole` takes a numeric `user_id` from the Team list. Throttle on `POST org-admin/invite`: NOT added. After this fix the answer carries no information about the address, so a throttle would not stop any enumeration; the mail-volume concern is ARCHITECTURE open item 11 and stays a separate decision.

Safety properties that must be unchanged (the existing tests pin them): the accept route needs sign-in, `verified`, `checkRole:user` and the verified e-mail equal to the invite e-mail (case/space-insensitive) or it answers 404; a used, expired or no-longer-assignable invite, and an invite whose inviter lost authority (`inviterStillAuthorised`), answer the 404 "invite invalid" page; the role goes through `RoleAssignmentService::assign` (SoD applies, S03 category rule applies); the claim is a conditional `used_at` update inside the assign transaction. In-flight links stay valid for their 7 days: old `/register/invite/{token}` and `/invite/{token}/accept` links keep working.

### F3. L08: removal paths and the last-role question

- `OrgAdminController::removeRole` (`DELETE org-admin/roles/{userOrgRole}`) calls `RoleAssignmentService::deactivate` with no awareness of how many active rows the member has. Removing the last active row silently removes the member from the org (membership is "has an active `user_org_roles` row", ARCHITECTURE 4.5); they can only return through an invite, because `assignRole` refuses non-members.
- The only UI for it is the Team drawer: `removeRole(assignmentId, memberId)` in `index.blade.php` (~line 477) uses `confirm('Remove this role from the member?')`, then submits a hidden DELETE form.
- `OrgAdminController::peelOff` (`POST org-admin/peel-off`) can also remove the acting user's own last role (an Owner with only the Owner role who assigns Owner to a hire and answers "Yes, remove from mine"). The peel-off modal says nothing about leaving the organization. That is "removing a member's last role" from an org screen, so it is in scope.
- Not an org screen, so left alone: `Admin\RbacController::removeRole` (platform admin, `confirm()` in `admin/rbac/organization-show.blade.php`). `OrgSettingsController::transferOwnership` hard-`delete()`s the sitting owner's Owner row, but only after granting the new owner in the same transaction, so it never empties an org's Owner set.
- Finding about the last Owner (reported, no block planned): nothing in the code requires an organization to keep an Owner. `removeRole` and `peelOff` can remove the last Owner row, by the Owner himself or by any `user_management` F holder (Organization Admin and the seeded Estimator, see open item 6; item 10 describes a related power). Afterwards owner-only actions (transfer, org delete) work only for listed universal admins. The plan adds one sentence to the warning when the row is the org's only Owner and nothing else.

### F4. N01: why the previous fix did not satisfy Mitch

`SodService::conflictsFor` returns no conflict only when the target is the SOLE active member of the org (`isSoleActiveMember`). SprocketWorx has several members, so every new assignment is judged by the eight seeded pairs (`SodConflictRuleSeeder`). Mitch's account holds Procurement Manager + Estimator + Executive Approver because `OrgOnboardingService::seedOrgRoles` inserts the registration bundle directly and bypasses SoD on purpose; the same pair is refused when he gives it to himself through the Team page now that the org has other members. Nothing is revoked from an account that already holds a pair. The bundle in `config('rbac.onboarding.bundles.buyer_small')` is Organization Owner, Procurement Manager, Estimator, Executive Approver, and the seeded rules inside it are exactly two: Procurement Manager + Executive Approver, and Estimator + Executive Approver. The client's plan 6.1 gives that bundle to "Subcontractor / GC / Owner, solo or small" organizations.

### F5. M12: what the documents say, and a create-path audit

Documents. The only definition on this machine is in `WB_RBAC_Unblock_the_Audit - To Shafiq.docx` (26 Sep 2026, "Fix now" row 2): "Quote membership auto-enrol (M12). Creator of a quote is added to its membership on create; existing quotes back-filled. How I will verify: I open Test 1 without a would-block." It was written when membership was keyed by quote. `docs/WEEK9-LOG-REVIEW-DRAFT.md` records the answer given after the Projects work: quotes are created only inside a visible project, the project creator is enrolled, old quotes were moved by `projects:backfill`, "TO CONFIRM: whether the backfill has been run on production". Mitch's new sentence ("creator must be added as member of anything they create") generalises it. The Audit 1.0 PDF and the sheet row are not available, and no document defines W12.

Create-path audit (live code):

| Path | Creator ends as a project member? | Note |
|---|---|---|
| Project: `ProjectController::store` (the only code that creates a `Project` for users) | YES, in the same transaction (`ProjectMember::enrol`) | A delegate acting as principal: the logged-in delegate is enrolled. The creator can be removed later (PHASE4 decision 4). |
| Estimate: `QuoteController::store` (`Quote::create` :398), `createFromList` (:302), `duplicate` (:631) | NO code enrols. A project member is a member already; an org-wide creator (Organization Owner, via W10) or a listed universal admin creates without a membership row | Needs a visible project and `estimate_management` S with the project id (`creatableProject`) |
| RFQ: `RfqController::store` (:110) | Same as estimates | Needs a visible project and `quote_rfq_management` S with the project id |
| RFQ convert to order, checkout order (`Order::create`) | No project membership concept | The order is tied to `user_id` and `org_id` |
| `PlanCrosswalkController::store` | No membership concept | The row belongs to a project; needs F and a visible project |
| Saved lists, customers (`org_id`), org relationships, delegations, API tokens, invites | No project membership concept (personal or org-level) | Customers are org-shared since `customers.org_id`; the creator sees them through the org |
| Organization: `OrgOnboardingService::onboard` (registration) | YES: the creator gets the Owner/bundle roles in the org | |
| Backfill command | Group owner enrolled unless explicitly removed | Not a user path |

Candidate readings of the client's row, with the evidence:

1. Estimate/RFQ creators who are not yet members (Owner and other org-wide creators; a listed universal admin). Evidence: the audit table; W10's documented "Owner is not auto-enrolled". A real gap in the code.
2. Production data: quotes whose `project_id` is NULL are visible to NOBODY (Quote scope since Phase 5a), the creator included; or projects/quotes whose creator has no active membership row. Evidence: the Week 9 draft "TO CONFIRM backfill". NOT a code gap; see the read-only queries under user actions.
3. A creator later removed from a project loses their own estimates (the project must be visible AND (R or author)). Allowed by design (PHASE4 decision 4).
4. "Anything" includes things with no project membership (lists, customers, orders). Nothing to enrol; the creator's access is by `user_id` or org.

Safe default (D2): reading 1, the smallest change that makes the sentence true wherever a project membership exists.

### F6. W12 candidates (the pop-up's buttons), from the code only

Clearly broken (planned):

- `index.blade.php` calls `resetInviteModal()` from "Try again" (error) and "Cancel" (dead branch); the function does not exist (ReferenceError, the button does nothing). `overview.blade.php` uses `openInviteModal()` instead, which calls `new bootstrap.Modal(...)` again on the already-open element.
- After any result the footer is hidden, so the only way out is the header "x"; there is no Done/Close button and no way to invite the next person without closing and reopening.
- A non-2xx answer (403 `abort`, 422 validation, 419, 500) has no `error` key, so it falls into the success branch and prints "Link for ... undefined" (ARCHITECTURE open item 12).
- "Copy" uses `alert('Link copied to clipboard!')` in `index.blade.php` and gives no feedback at all in `overview.blade.php`; the form step uses `alert()` for missing fields. The client already objected to `alert()` in signup (B06b), so the same inline-message treatment is applied. Lower certainty than the three above.
- Dead branches: a "Yes, add them" button that the controller can no longer produce (removed together with S15).

Not clearly broken (flagged for the sheet row, not planned): the label "Copy link instead" (the button creates the invite and shows the link; a second click copies it); two near-identical copies of the modal in two pages; any layout or wording request.

### F7. Already built: what the code does today (verified, not rebuilt)

- W10: `PermissionService::seesAllOrgProjects` (:125), used by `Project::scopeVisibleTo` (:66) and `isActiveProjectMember` (:170). Owner, Organization Admin, Auditor/Read-All, Manufacturer Admin, Platform Super Admin see every live project of their own org; visibility only. Covered by `OrgWideProjectVisibilityTest`, `W10RegressionTest`, `W10AmendmentTest`, `W10AmendmentQaTest`.
- L05: an auto-approved order writes one `rbac_audit_logs` row from `ApprovalRoutingService::recordAutoApproval`, inside the order transaction: outcome `auto_approved`, user = the submitter, org = the order's org, group `approval_authority`, level `A`, reason `sole_approver order ORD-xxxx` (the order number, which is what the order screens print), `created_at`, method and route (`POST checkout/process` or `POST rfq/{rfq}/convert`; `CLI` / `artisan` for the backfill command). A self-approval by a sole approver writes `self_approved` with `sole_approver_self_approval order ORD-xxxx` (`OrderApprovalController::approve`). Both are labelled ("Auto-approved", "Self-approved (sole approver)") and the reason is printed in the Details column of the org Audit Log "Enforcement" tab (`org-admin/audit-log?tab=enforcement`) and on both platform-admin audit pages; pinned by `AutoApprovalTest` (`test_the_sole_approver_is_auto_approved_with_a_note_and_one_audit_row`, `test_the_org_audit_page_labels_both_outcomes_and_shows_the_details`). NO GAP: who, when and which order are in the row and on the page. Only orders placed after the deploy, or approved by `orders:backfill-auto-approval`, have rows (user action, R10).
- L06: `DelegationService::audit` writes `delegation_granted` / `delegation_revoked` (who acted, org, delegate and principal names and ids, start and end) and `RbacAudit` writes `delegated_action` (delegate, route, group, level, "acting for <principal> (#id)") on the allowed path of every mapped route; `DelegationAuditTest`. One limitation, not a bug: a `delegated_action` row stores the route PATTERN (`order-approvals/{order}/approve`), not the record id, so "which order" is not in a delegated-approval row (R9).
- S13: `App\Exceptions\Handler::convertExceptionToArray` returns only `message` for every JSON error whatever `APP_DEBUG` is; `.env.example` ships `APP_ENV=production`, `APP_DEBUG=false`; `JsonErrorBodyTest`. The HTML error page still depends on the server's `APP_DEBUG` and a stale `bootstrap/cache/config.php`, which only the user can change.

## Decisions for Checkpoint 1 (human)

Each has a recommendation. D1-D3 are blocked until you answer. D4-D6 default to my recommendation if you approve the plan as written.

### D1. N01: the SoD rule for an Organization Owner in a team organization (needs human decision)

| Option | What changes | Consequences |
|---|---|---|
| A. Keep the sole-member exemption only; explain to Mitch | Nothing in code | Mitch's account lives in a multi-member org, so his N01 retest fails again. SoD stays strictly as seeded. Cheapest, and the one that does not satisfy the sentence "Owner must be able to hold Estimator + Executive Approver". |
| B. Owner exemption for the registration-bundle pairs, in any org (RECOMMENDED) | `SodService::conflictsFor`: a conflict is dropped when the target holds an active `organization_owner` row in THAT org AND both roles of the pair are in `config('rbac.onboarding.bundles.buyer_small')` (Procurement Manager, Estimator, Executive Approver). That is exactly the two seeded pairs Procurement Manager + Executive Approver and Estimator + Executive Approver. The sole-member exemption stays. | An Owner of any org can hold the bundle pairs, both directions, as the client's plan 6.1 already hands out at registration. Everyone else (Organization Admin, any other role) and every other pair for an Owner (Requisitioner + Executive Approver, AP Invoice Clerk + Executive Approver, Procurement Manager + Financial Admin, ...) stay refused. Per organization: Owner of A is not exempt in B. A05 stays: with another approver present the Owner still cannot approve their own order; as sole approver the order auto-approves and is logged. Each exempt assignment is still written to `role_assignment_logs` with the assigner and time (shown on the org Audit Log). Narrower variant B': exempt the Owner from every pair; not recommended (reaches pairs the client never discussed). |
| C. Deactivate or delete the two rules (Admin > RBAC > SoD screen, or the seeder) | No code | The two protections disappear for every role in every org (the seeded reasons are "cannot create and approve purchase orders" and "cannot author and approve the same estimates"); plan 6.2 calls that separation a control. A fresh seed would bring the rows back, so production and repo drift. Not recommended. |

Recommendation: B, for these reasons: it is the smallest change that makes Mitch's sentence true in his own (multi-member) org; it follows the client's own onboarding table; it leaves every non-Owner and every non-bundle pair as strict as before; it needs no data change. Marked: needs human decision at Checkpoint 1. Tests and mutation proofs are planned for B only.

### D2. M12: what "creator must be added as member of anything they create" means (needs human input: client sheet row M12)

I could not establish the exact failing step from the documents (F5). Options:

- (i) Default, recommended: when a member creates an estimate (`store`, `createFromList`, `duplicate`) or an RFQ (`store`) in a project, make them an active project member if they are not one yet and are an active member of the project's org. It uses the existing `ProjectMember::enrol` (transactional, idempotent), which writes the usual `project_member_logs` `added` row (performed by themselves). Project creation already does this. Consequence: an Owner who creates the first estimate in a colleague's project becomes an explicit member of that project (a roster row and a log row), a small departure from W10's "Owner not auto-enrolled" which applied to being enrolled on every project; the Owner keeps org-wide visibility either way.
- (ii) No code change; answer Mitch that creators are members wherever a membership exists and that org-wide roles see everything. Risk: he re-tests the same step and fails it a second time.
- (iii) Hold M12 until the sheet row arrives.

Recommendation: approve (i) now, and in parallel (a) ask Mitch for the M12 and W12 sheet rows (step, expected, actual), (b) run the three read-only SQL checks in the user actions; if they show NULL-project quotes or creators without membership, the cause is data, not code, and a separate backfill decision is needed (NOT planned here: it writes to production data).

### D3. W12: the invite pop-up buttons (needs human input: client sheet row W12)

Recommendation: plan only the demonstrably broken items of F6 (undefined `resetInviteModal`, no Done/Close after a result, non-2xx answers shown as success with "undefined", `alert()` replaced by inline messages, dead "Yes, add them" branch removed). Anything else Mitch means (label "Copy link instead", layout, wording) waits for the sheet row.

### D4. S15: how far to go (default: the recommendation)

Tier A (the invite pop-up in `index` and `overview`) is the client's item and is certain. Tier B (Team drawer, platform-admin user drawer, platform-admin member `confirm()`) renders other accounts' names and e-mails, which is how you defined the scope. Tier C (two token-name `confirm()` lines) is the same attack through a token label; platform admins are the victims. Recommendation: A + B + C (all small, none changes behaviour). Strike C if you want only account names.

### D5. O11: keep the link in the invite answer? (default: keep)

The pop-up's "Copy link" and the failed-mail fallback need the link. Keeping it means the inviter, as a token holder, can still learn existence by trying the guest register form (F2, ARCHITECTURE items 8 and 13). Alternative: omit `link` whenever the mail was sent. Recommendation: keep the link, say so to Mitch plainly if he asks, and treat items 8/13 (mailbox verification on guest registration) as the real closure, as a separate decision.

### D6. L08: scope and behaviour (default: the recommendation)

Warn and require confirmation; do not block. Covers the Team-page "remove role" and the peel-off prompt. The sole-Owner case gets one extra sentence in the warning, no block (F3 finding: the code already allows an org with no Owner). The platform-admin removal screen is not touched. If you want a hard block on removing the last Owner, say so; it is a new rule and needs its own plan.

## File list

Backend (Developer):

| File | What changes |
|---|---|
| `app/Http/Controllers/Frontend/OrgAdminController.php` | `generateInvite`: drop the `User` lookup and `$existingAccount`; JSON link = `url('/invite/' . $token)`. `removeRole(Request, UserOrgRole)` and `peelOff`: last-role confirmation. `assignRole` JSON: add `peel_off_last_role`. |
| `app/Services/Rbac/RoleAssignmentService.php` | New `isLastActiveRole(UserOrgRole $row): bool` (active row and the member's only active row in that org). No change to `assign`, `deactivate`, `peelOff`. |
| `app/Services/Rbac/SodService.php` | D1 option B: Owner exemption for bundle pairs. |
| `app/Mail/OrgInviteMail.php` | Constructor `(OrgInvite $invite)`; remove `existingAccount`; `inviteUrl` = entry URL. |
| `app/Http/Controllers/Auth/InviteController.php` | New `entry(string $token)`; the constructor's `guest` middleware must exclude it; `register`: existing-address guard. |
| `routes/web.php` | New `Route::get('invite/{token}', ...)->name('invite.entry')` outside the `auth` group, next to the `register/invite/{token}` routes. |
| `app/Models/Rbac/ProjectMember.php` | D2 (i): one static helper beside `enrol()` that does nothing for a user who is not an active member of the project's org (universal admin acting cross-org) and otherwise calls `enrol()`. |
| `app/Http/Controllers/Frontend/QuoteController.php` | `store`, `createFromList`, `duplicate`: call the helper after validation and authorisation, immediately before `Quote::create`. |
| `app/Http/Controllers/Frontend/RfqController.php` | `store`: call the helper inside the existing transaction, before `RfqRequest::create`. |
| `docs/client-audit-pack/route-list.json` | Regenerate with `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan route:list --json` (293 -> 294 routes; the client receives this file under his item 3). `route_permission_map.php` copy is unchanged. |

Views and copy (ui-designer):

| File | What changes |
|---|---|
| `resources/views/user/org-admin/index.blade.php` | Invite pop-up (S15 + W12 + the O11 answer); drawer escaping; last-role modal; peel-off modal text; per-role `soleOwner` flag in `membersData`. |
| `resources/views/user/org-admin/overview.blade.php` | Invite pop-up, identical result to `index`. |
| `resources/views/emails/org-invite.blade.php` | One wording for every recipient. |
| `resources/views/auth/invite-register.blade.php` | The line "Already have an account? Sign in - your role will be assigned automatically" becomes "Already have an account? Sign in to accept this invitation", linking to `route('invite.review', $token)` (the current text is wrong since the 3c change). |
| `resources/views/admin/rbac/users.blade.php` | Drawer escaping (D4 tier B). |
| `resources/views/admin/rbac/organization-show.blade.php` | Line 78, `@js` (tier B). |
| `resources/views/user/org-admin/api-tokens.blade.php` (line 114), `resources/views/admin/rbac/service-accounts.blade.php` (line 171) | `@js` (tier C). |

Tests (Developer updates existing, QA writes new):

- New: `tests/Feature/Rbac/InviteUniformResponseTest.php` (O11), `LastRoleConfirmationTest.php` (L08), `SodOwnerBundleExemptionTest.php` (N01-B), `CreatorMembershipTest.php` (M12), `HtmlSinkGuardTest.php` (S15, static source guard), `Round2ClientWordingReplayTest.php` (W10, L05, L06, S13).
- Existing tests expected to change, to be confirmed by the Developer's first run before editing (T0): `PhaseARegressionTest` (~346-375), `ExistingUserInviteTest` (~101, 105-160, 364-385: these assert the old difference and flip to asserting no difference); `SodSoloOrgTest::test_a_member_with_any_active_row_ends_the_exemption` and `::test_the_service_reports_no_conflict_for_a_sole_member_and_names_them_for_a_second_member` (the target there is an Owner); tests that remove a member's only role by `DELETE org-admin/roles/{id}` or `POST org-admin/peel-off` (`CrossOrgNotFoundTest` ~236, `PhaseBBatch1RegressionTest` ~225, `RoleAssignmentGuardTest` ~355, `AuditFindingsRound2Test` fixtures at :126-:127, `CrossOrgDenialRowEdgesTest`); tests that assert an Owner has no `project_members` row, or an exact `project_member_logs` count, after creating an estimate or RFQ (`OrgWideProjectVisibilityTest`, `W10*Test`, `RfqProjectScopeTest`, `ProjectWritePathsTest`). Any further failure is reported to the Architect, not edited around.

Docs: `ARCHITECTURE.md` (4.5 invites bullet; 4.6 N01 bullet; 2 and 4.7 on creator enrol; a new 4.8 for this round; section 12 items 11, 12, 13 annotated; change log), `TESTING.md` (new classes, changed tests, measured baseline). `CLAUDE.md` is not edited (the human's file).

## Task breakdown

Order matters only where stated. Each step is independently completable and logged in `PROGRESS.md`. All test runs: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test ...`; the full suite in the background with output to the scratchpad and polled (the previous agents stalled at 600 s on silent commands). No migrate, backfill, tinker write or any other command against the local database.

Developer (backend)

- T0. Run the full suite once in the background; confirm 2039 / 4. Then, before editing any existing test, run the predicted-affected classes after each task below and report the exact list of newly failing existing tests to the Architect.
- O1. `generateInvite`: remove the account lookup; one answer `{type: 'invite', email_sent, link: url('/invite/'.$token)}`; mail built with the invite only.
- O2. `OrgInviteMail`: new constructor and `inviteUrl`; delete `existingAccount`.
- O3. `GET invite/{token}` (`invite.entry`): unknown, used or expired token -> the same `auth.invite-invalid` view as `register/invite/{token}`; signed in -> redirect to `invite.review`; guest -> exactly what `register/invite/{token}` renders. `InviteController::register`: if a user with the invite's address exists, create nothing and `redirect()` to login with `url.intended` = the accept URL and flash `status` "Sign in to accept this invitation." (login prints `status`, not `error`).
- O4. Update the existing tests listed above (only after T0's list is confirmed).
- N1 (D1 B). `SodService::conflictsFor`: after the existing sole-member shortcut, filter the held conflicting roles: drop a held role when the target is an active Owner of this org and both it and the incoming role are in the `buyer_small` bundle. Read the bundle from config; no hard-coded slugs.
- N2. Update the two `SodSoloOrgTest` cases (target becomes a non-Owner, or a non-bundle pair).
- L1. `RoleAssignmentService::isLastActiveRole`.
- L2. `removeRole`: after the org, 404 and `requireOrgLevel` checks and before `deactivate`, if the row is the member's last active role and `confirm_last_role` is not truthy: no write; `expectsJson()` -> 422 `{last_role: true, message}`; otherwise `back()` with flash `error` naming the member ("<name> has no other role in <org>. Removing this role removes <name> from the organization. Confirm to continue."; the self variant says "you"). With the parameter, behave exactly as today, including the log row. A row that is already inactive or not the last role is unchanged.
- L3. `peelOff`: same rule for the acting user's own last role (422 JSON `{success: false, last_role: true, message}`); `assignRole` JSON gains `peel_off_last_role` (true when the assigner is a peel-off candidate and has no other active role in the org).
- M1. `ProjectMember` helper (D2 i). M2. Call it at the four sites as specified in the file list; nowhere else (no change to project creation, which already enrols).
- R1. Regenerate `docs/client-audit-pack/route-list.json`; confirm that `config/route_permission_map.php` and `docs/client-audit-pack/route_permission_map.php` are untouched and still identical.

UI (ui-designer), depends on O1 and L3

- U1. Invite pop-up in `index` and `overview`, identical behaviour in both. Rewrite `doInviteFetch` and its helpers: every dynamic value through `textContent` or a local `escapeHtml`; no interpolation into `onclick`; remove the `confirm_existing` / `existing` branches and `confirmExistingAssign`. Handle `!r.ok` and a missing `link`: show the server `message` (or the first validation error) in an alert box, never "undefined". Buttons: form step unchanged (Send invite email, Copy link instead, Cancel); required-field messages inline instead of `alert()`; after a result the footer stays hidden but the result has its own buttons: success -> "Done" (closes) and "Invite another person" (cleared form); error -> "Try again" (back to the form, values kept) and "Close". "Copy" selects the link and shows "Copied" on the button for two seconds (no `alert`). Define every function the markup calls.
- U2. Team page drawer: escape `m.name`, `m.email`, role names and option labels. Last-role flow: if the member has exactly one role, open a Bootstrap modal (same pattern as the existing peel-off modal) instead of `confirm()`: title "Remove <name>'s last role?"; body "<Role> is the only role <name> has in <Org>. Removing it removes <name> from the organization: they lose access to its projects, estimates, orders and approvals, and can only come back by being invited again." Self variant: "This is your only role in <Org>. Removing it removes you from the organization and you lose access immediately; you can only come back by being invited again." Extra sentence when `soleOwner`: "<Name> is also the only Owner of <Org>. After this the organization has no Owner." Buttons: "Cancel" (default focus) and a danger button "Remove from organization", which submits the DELETE form with `confirm_last_role=1`. Roles that are not the last keep the current simple confirmation. All names inserted with `textContent`. Peel-off modal: when the answer says `peel_off_last_role`, add the self-variant paragraph and send `confirm_last_role: true`.
- U3. Platform-admin views, tier B and C (D4): escape the drawer fields; `@js(...)` for the three `confirm()` messages.
- U4. Copy: one invite e-mail text (subject unchanged; the button reads "Accept invitation"; two sentences: "New to Wisselbanken? You will be asked to create your account. Already have an account? Sign in with this e-mail address and the invitation is added to it."), and the sign-in line on the guest page.

QA (qa-tester)

- Q1. `InviteUniformResponseTest`, both RBAC modes: (a) `POST org-admin/invite` for an address with an account and for a new address, same name and role: identical key set, types and `email_sent`; links identical except the token and equal to `url('/invite/'.token)`; (b) the two sent mails render to the same HTML after the token is normalised; (c) the guest `GET invite/{token}` pages are equal for both addresses after the CSRF token and invite token are normalised, 200 for a guest in both cases; (d) a signed-in invitee is redirected to the accept page; the wrong signed-in user still gets the 404, an unknown token the same invalid page as before; (e) `POST register/invite/{token}` with an existing address creates no user, no role, leaves the invite unused, answers a redirect to login with `url.intended` = the accept URL, no 500; with a new address it works as today; (f) the number of database queries made by `generateInvite` is equal for the two cases and no `users` lookup by the invite address is made; (g) old `/register/invite/{token}` and `/invite/{token}/accept` links still work; (h) the route `invite.entry` has no `auth` middleware and `AuditFindingsRound2Test` stays green. Denied paths: viewer / wrong org / unknown role still 403 on `org-admin/invite` (existing guard cases).
- Q2. `LastRoleConfirmationTest`, audit and enforce: last role without the parameter: 302 with the warning flash (or 422 JSON), row still active, no `role_assignment_logs` row; with `confirm_last_role=1`: removed and logged; two roles: removal without the parameter works and logs once; the actor's own last role; sole Owner row still removable with the parameter (documents the finding); peel-off last role refused without the parameter, works with it; a viewer is still 403 and another org's row still 404 with its `controller_denied` row (the confirmation check comes after those); a stale or already-inactive row is not a "last role"; removing the last role makes `isActiveMember` false; a listed universal admin gets the same confirmation (no exemption).
- Q3. `SodOwnerBundleExemptionTest` (D1 B), audit and enforce: Owner in a multi-member org is assigned Estimator then Executive Approver, and Procurement Manager then Executive Approver, in both orders; the roles are held afterwards and `role_assignment_logs` has the rows; Organization Admin and a plain member are still refused (422 `sod_conflict`); an Owner is still refused Requisitioner + Executive Approver, AP Invoice Clerk + Executive Approver, Procurement Manager + Financial Admin; an Owner whose Owner row is inactive is refused; Owner of org A is refused in org B; the platform-admin screen goes through the same service (same result); A05 intact: the Owner holding Procurement Manager + Executive Approver in a team org cannot approve their own order while another approver exists, and as the sole approver the order auto-approves with an `auto_approved` row.
- Q4. `CreatorMembershipTest` (D2 i), audit and enforce, for each of project create, estimate `store`, `createFromList`, `duplicate`, RFQ `store`: an Estimator member creates: member afterwards, no duplicate row, no extra log row; an Owner (org-wide, not a member) creates: one active row and exactly one `project_member_logs` `added` row performed by himself; a listed universal admin acting in another org: no row and no error; an Organization Admin (estimate R only) is refused with 403 and no row (enrol happens only after authorisation); a project the actor cannot see: 404 and no row; a validation failure leaves no row; membership org equals the project org; a removed member who creates again is reactivated with a `reactivated` log row.
- Q5. `HtmlSinkGuardTest`: static scan of `index.blade.php`, `overview.blade.php`, `admin/rbac/users.blade.php`: no `innerHTML` template containing an unescaped `${data.`, `${email}`, `${name}`, `${link}`, `${m.name}`, `${m.email}`, `${r.roleName}`, `${r.name}`, `${r.category}` or `${og.`/`${d.`; none of `confirm_existing`, `user_name`, `confirmExistingAssign`; every function named in an `onclick=` in those files is defined in the same file (catches the `resetInviteModal` class of bug); no `confirm('...{{` left in the three attribute sites. PHPUnit cannot execute JS: this guard plus the manual browser check below are the proof, and the plan says so openly.
- Q6. `Round2ClientWordingReplayTest` (verification only, no production code): see V1-V3.
- Q7. Mutation proofs, each reverted and verified byte for byte, each must turn a named test red:
  - S15: put `${name}` back into an `innerHTML` template; re-add the `confirm_existing` branch; remove `@js` at `organization-show.blade.php:78` (all killed by Q5).
  - O11: restore the link branch on account existence in `generateInvite` (Q1a); restore the mail difference (Q1b); make the guest entry redirect to login when an account exists (Q1c); drop the register guard (Q1e); skip the redirect for a signed-in user (Q1d).
  - L08: drop the confirmation check in `removeRole`; check only in the UI (JS-less request still removes); move the check before the level/org-mismatch checks (denial rows disappear); count inactive rows as roles; drop it in `peelOff`.
  - N01: drop the Owner condition (Q3 Organization Admin case); widen to all rules (Q3 non-bundle pairs); drop org scoping (Q3 cross-org); ignore `is_active` on the Owner row; read the bundle with hard-coded slugs plus a different set (config test).
  - M12: remove the helper call at each of the four sites (four mutants); enrol before authorisation; enrol a universal admin into another org; enrol with the wrong org id.
- Q8. Manual browser step for Checkpoint 2 (human, 5 minutes, written into `REVIEW.md` by QA): (1) register or edit a test account so its name is `<img src=x onerror=alert(1)>` and add it to a test org; open Team and click the member: the name appears as literal text and no alert fires; (2) in the invite pop-up use that string as the name and `a@b.test` as the address: literal text, no alert; (3) invite an address that has an account and one that does not: the two pop-up results look identical; (4) remove a member's only role: the warning names the member; Cancel changes nothing; (5) force an error (invite with an empty role through dev tools) and confirm the pop-up shows a message and "Try again" works.
- V1 (W10). Replay: an Estimator creates a project; the Owner (not a member) sees it in `projects/list`, opens it, in audit and enforce mode; the Owner is not enrolled by merely opening it.
- V2 (L05, L06). Replay in the client's words, as the Owner of an org with several members: a sole approver places an order -> one `auto_approved` row (submitter, org, order number, time) visible on `org-admin/audit-log?tab=enforcement` with its label and details; a self-approval row likewise; a delegation grant and revoke each write a row on the same page; a delegate's request writes a `delegated_action` row naming the principal. Also asserts the one limitation of F7: the delegated row carries the route pattern, not the order id (so a future change is a deliberate decision).
- V3 (S13). Replay: with `APP_DEBUG=true`, a JSON 404, 403, 422, 500 and a thrown non-HTTP exception carry no `exception`, `file`, `line`, `trace` or model class name; `.env.example` has `APP_DEBUG=false`.
- Q9. Full suite once in the background at the end: expected 2039 + new tests passed, with the same 4 failures by name and no other. Report any change in the known flaky `AuditMembershipLogTest` ('P3' in the CSRF token) as the known flake, not a regression.

Docs (Developer, last)

- Dx. `ARCHITECTURE.md`, `TESTING.md` as listed above, plus a change-log entry dated by the day of the work. Write the facts only as built.

## Risks / open questions

Needs human input (each has a default):

- R1. N01 rule: D1, default B.
- R2. M12 meaning: D2, default (i) plus the sheet row; may turn out to be a production-data issue.
- R3. W12 meaning: D3, default the minimal broken set plus the sheet row.
- R4. O11 residual leak through the guest register form: D5, default accept and document. It is inherent while the inviter holds the link and a guest can register straight from it; closing it is ARCHITECTURE open items 8 and 13 (mailbox verification on guest registration, inviter-authority check on the guest path). This plan must not be extended into either: item 8 explicitly says "do not fix with forceFill".
- R5. S15 reach: D4, default A + B + C.
- R6. L08 and the last Owner: warn only (D6). The code already permits an Owner-less org; owner-only actions then need a universal admin. Because `user_management` F is also held by Organization Admin and the seeded Estimator (open item 6), a modal does not stop those users from removing the Owner; it only makes the consequence visible.
- R7. L08 does not cover the platform-admin removal screen (not an org screen). Default: leave.

Findings reported, no change planned:

- R8. W10 is live without the client's written OK (ARCHITECTURE 2/4.7 still say PENDING). Nothing in this round depends on it; the replay test V1 only confirms behaviour.
- R9. L06 limitation: `delegated_action` rows name the route pattern and the principal, not the record (the order id). If Mitch's "which order" includes delegated approvals, the smallest remedy is one extra `AuditLog::event` in `OrderApprovalController::approve` / `reject` when `Auth::id()` differs from the effective user, carrying the order number. Not planned (scope rule); say yes at Checkpoint 1 if wanted.
- R10. L05 rows exist only for orders placed after the deploy or approved by `orders:backfill-auto-approval`. Orders 4, 5, 7 and 9 have no row until the user runs the command (dry run first).
- R11. S13: nothing more to do in code; with `APP_DEBUG=true` on the server the HTML error pages still show the debug page and a stale `bootstrap/cache/config.php` can keep it on after editing `.env`.
- R12. Not throttling `POST org-admin/invite` is deliberate (F2); mail volume is open item 11.
- R13. PHPUnit cannot run the page JavaScript (no Dusk, no JS test runner). S15 and W12 are proven by the static guard (Q5) plus the manual step (Q8). If you want an automated browser check, that is a separate tooling decision.
- R14. Unverified: nothing was run against MariaDB, and the lists of "existing tests expected to change" come from reading the tests, not from a scratch-copy run (I was told not to write code, so I did not prototype). The register guard replaces a unique-index failure; the MariaDB error text differs from sqlite's, so the plan checks existence first instead of catching the exception. The Developer's T0 run confirms or corrects the test lists before any existing test is edited.
- R15. The two invite pop-up copies (`index`, `overview`) stay duplicated on purpose (no refactor); the Q5 guard covers both so they cannot drift silently.

Cross-check of ARCHITECTURE section 12 "Known open items": the four classic ones (matrix/org-type counts and `api_system`, `AuditMiddlewareTest` 302, rep-agency dead code in `sellerAuthorizationError`, the audit-middleware redirect) are not touched. Touched: item 12 (invite modal ignores error responses: fixed by U1; the item is annotated, and the "may stay" dead branches are removed because they are also the S15 sinks), items 8, 11 and 13 (explicitly not fixed, R4, R12), item 6 (role names are an S15 source; not fixed), item 10 (related to L08; not fixed).

## Out of scope / user actions (items 3, 4, 5 of Mitch's message, and what remains of S13/L05)

- Item 3: send the updated `docs/client-audit-pack/route-list.json` (task R1, 294 routes after this round), `config/route_permission_map.php` (unchanged), the Week 9 log review and the enforcement batch record. Note for the batch record: there is no "enforcement batches table" in this code. The batches are the `.env` value `RBAC_ENFORCE_BATCHES`; the mode (`rbac_mode`) and the per-org list (`rbac_enforced_org_ids`) are rows of `rbac_settings`. For the DigitalOcean move (item 5) the `.env` value and the `rbac_settings` table must both move, `APP_DEBUG=false` and `APP_ENV=production` in the new `.env`, then `php artisan config:cache` and `php artisan about --only=environment`.
- Item 4: the D/M/S/V/R proofs are query output or screenshots from production; not code work.
- S13: `APP_DEBUG=false` in the server `.env`, `config:cache`, confirm with `about`.
- L05: run `php artisan orders:backfill-auto-approval --dry-run`, then the real run, so orders 4, 5, 7 and 9 get their rows.
- M12 data check (read-only, MariaDB; run on production, send the three results):

```sql
SELECT COUNT(*) AS quotes_without_project FROM quotes WHERE project_id IS NULL AND deleted_at IS NULL;
SELECT p.id, p.org_id, p.name, p.created_by FROM projects p
  LEFT JOIN project_members m ON m.project_id = p.id AND m.user_id = p.created_by AND m.is_active = 1
  WHERE p.deleted_at IS NULL AND m.id IS NULL;
SELECT q.id, q.user_id, q.project_id FROM quotes q
  LEFT JOIN project_members m ON m.project_id = q.project_id AND m.user_id = q.user_id AND m.is_active = 1
  WHERE q.deleted_at IS NULL AND q.project_id IS NOT NULL AND m.id IS NULL;
```

- Ask Mitch for the sheet rows M12 and W12 (steps, expected, actual) and for his written OK on W10 (still PENDING).
- Reply date for items 1 and 2: yours to give after Checkpoint 1; the plan has no calendar estimate.

## Amendments

### Amendment 1 (Architect compliance pass, 2026-11-13)

Status: written by the Architect after reading the full diff. Nothing here changes a decision the human approved at Checkpoint 1 (D1-D6 stand, R9 stays out). It records what the built code does that the plan did not say (accepted retroactively, with the reason), and corrects two omissions in the plan's own file list. No backend behaviour changes are required.

**A1-1. Team drawer: `data-action` plus one delegated listener instead of inline `onclick` (ui-designer, `user/org-admin/index.blade.php`). ACCEPTED retroactively; manual-check scope widened.**
The old drawer interpolated only integers (`removeRole(${r.id}, ${m.id})`), so the inline handlers were not an S15 sink and the change was not strictly needed. It is accepted because it is behaviour-neutral (the generated drawer markup now contains no `on*=` attribute at all, which makes the static guard Q5 simpler and permanent), it touches the same lines the L08 remove button had to change anyway, and reverting would put untested edits back on those lines. Because only DOM-less execution was done for it (see REVIEW.md), Q8 must click all four drawer buttons in a browser (see A1-5). The human may veto this one and ask for a revert; it is the single non-S15, non-L08 refactor in the diff.

**A1-2. `admin/rbac/users.blade.php`: `escapeHtml` also applied to `t.name` / `t.last_used` inside the already commented-out token block, and to `d.expires`. ACCEPTED.**
Zero runtime effect (the block is inside a JS `/* */` comment); it keeps the S15 sink from coming back when the block is enabled in "Phase 3". Same function, same sink class as the planned edits.

**A1-3. Details of U1/U2 that the plan did not spell out. ACCEPTED, folded into U1/U2:**
- Peel-off modal shows the server `message` in a red box and stays open when the peel-off call is refused or fails (it used to ignore the answer and reload). Needed: with L3 the server can now answer 422 `last_role` (for example when the other role was removed in a second tab after the assign answer said "not last"); without this the new refusal would be a silent reload with the role still on the account. It also applies to 403/419/500, which is the same W12 principle ("never show success for a failed call").
- The hidden DELETE form in `removeRole` is built with `createElement` inputs (the planned "leave `form.innerHTML` alone" is superseded because the form now carries `confirm_last_role` conditionally).
- `resetInviteModal` is defined in both pop-up views and also runs on `show.bs.modal`: the overview header button opens the modal with `data-bs-toggle` and never calls `openInviteModal()`, so only the event hook guarantees a clean form there.
- "Copy failed" label when `document.execCommand('copy')` returns false; `escapeHtml` also escapes quotes; self-variant title "Remove your last role?"; `RBAC.userId` and `RBAC.orgName` added to the Team page's `RBAC` object (needed for the self variant and the organization name in the modal).
- `assignRoleFromDrawer` keeps its `alert(data.message || 'Could not assign role.')`: D3 concerns the invite pop-up only; this is a plain-text alert, not a sink.

**A1-4. Plan file list was incomplete: `docs/client-audit-pack/README.md`. NEW TASK R2 (Developer).**
The README that ships with `route-list.json` states "293 routes" (line 5). The regenerated file has 294. The client receives this folder under his item 3, so the number must match. Edit exactly: line 5 "293 routes" -> "294 routes", and add one sentence to the first paragraph: round 2 of client audit 2 adds the guest route `GET invite/{token}` (name `invite.entry`, no `auth` middleware, therefore not in the map and not in the "intentionally not in the map" table, which lists authenticated routes only); the map is unchanged. Do not touch anything else in the README.

**A1-5. QA additions (Q10) and Q8 widening.** See REVIEW.md "QA must also cover". In short: the unparameterised denied cases for L08 (the existing fixtures now pass `confirm_last_role`, so QA's tests must NOT), falsy forms of `confirm_last_role`, config-driven N01 bundle, N01 through invite acceptance and the platform-admin screen, RFQ enrol rollback, duplicate-by-non-author, and the Q8 browser list extended with the four drawer buttons, the peel-off 422 message, the Overview header-button path and an apostrophe in a name for the three `@js` confirms.

**A1-6. Doc wording (Developer, task Dx2).** In `ARCHITECTURE.md` section 4.8 rename the last bullet from "Verified, not rebuilt (W10, L05, L06, S13)" to "Not rebuilt (W10, L05, L06, S13); verification pending": the replay class does not exist yet and nothing in this round has verified them. In section 12 item 12, strike "The `confirm_existing` / `existing` branches are unreachable now and may stay." (the annotation right after it says they were removed; the two sentences contradict each other). Nothing else in `ARCHITECTURE.md` or `TESTING.md` needs to change; both were checked against the code as built.

**A1-7. Process note (no action).** T0 asked the Developer to report the list of newly failing existing tests to the Architect before editing any. The Developer edited in the same session. Accepted retroactively: all 24 failing data sets sit in the seven classes the plan names (the one pinned key list in `PhaseARegressionTest::test_the_assign_answer_keeps_its_shape_and_the_peel_off_hint` is inside a named class and is a direct consequence of L3), and every edit was checked in REVIEW.md as minimal.

### Amendment 2 (Architect ruling on QA findings F1 and F2, 2026-11-13)

Status: written by the Architect after the QA report ("## QA report (2026-11-13)" in REVIEW.md). No decision the human approved at Checkpoint 1 changes: D1 option B and D2 (i) stand, the scope rule (only Mitch's items 1 and 2) holds, R9 stays out. No application code, migration, route, config or route-map change. Output: one QA test (A2-T1), two documentation edits (A2-T2, A2-T3). Evidence: the live code and the seeded matrix (`database/seeders/Rbac/data/role_permission_matrix.php`, compared by a throw-away PHP script in the Architect scratchpad; nothing ran against any database).

**A2-1. F1: an Estimator can reach Estimator + Organization Owner + Executive Approver in two requests. RULING: ACCEPT N01-B as built. Do not narrow it.**

Facts, verified (not taken from QA's report):

1. Matrix. For every permission group that Estimator, Procurement Manager or Executive Approver has, the Owner role has the group at the same or a stronger level (F > A > O > S > R). Estimator: estimate_management, procurement, project_management, user_management F = Owner F; quote_rfq_management S, Owner F; product_management, pricing_management, order_fulfillment R = Owner R. Procurement Manager: procurement F, quote_rfq_management F, contract_and_agreement_management F, approval_authority A, all = Owner; estimate_management, project_management, user_management, organization_management, reporting_and_analytics R and budget_and_cost_control R are below the Owner's F/A. Executive Approver: approval_authority A and budget_and_cost_control A = Owner A; contract_and_agreement_management A and quote_rfq_management A below Owner F; procurement, estimate_management, project_management, financial_access, reporting_and_analytics R below Owner F; pricing_management R = Owner R. The script found no group where any of the three outranks the Owner. So the N01-B exemption (a bundle role is added to an active Owner of this org) can never add a grant the Owner row does not already carry. That is the real justification of D1 B, and it is now to be pinned (A2-T1).
2. Roles are matrix-driven, not name-driven. `PermissionService::holdsLevel` takes the strongest level across the active role rows; `ApprovalRoutingService::approverPool` is a DISTINCT user query on `role_permissions` (`approval_authority` F or A). A grep of `app`, `config`, `routes`, `resources` finds no code that decides anything from the slug or name `executive_approver` / `procurement_manager` (only `config/rbac.php`, register-page labels, an error-message sentence and a docblock). So an extra Executive Approver or Procurement Manager row on an Owner changes no check, no approver pool and no routing. A05 is intact: `OrderApprovalController::guardOwnOrder` -> `otherApprovers` -> `approverPool`; neither file is in the diff; `SodOwnerBundleExemptionTest` pins the own-order 403 and the sole-approver auto-approval.
3. The authority gain in QA's chain is step 1, not step 2. Estimator -> `organization_owner` adds organization_management F, delegation_and_impersonation F, approval_authority A, financial_access F, reporting_and_analytics F and more. Step 1 does not depend on this round: `OrgAdminController::assignRole` requires only `user_management` F, an active member as target and `isAssignableByOrg` (category "Organization" is assignable in every org); nothing restricts who may grant `organization_owner`, to himself included. That is ARCHITECTURE open item 6 (the seeded Estimator holds `user_management` F) and the same power item 10 describes. Before N01-B step 2 was refused; after it step 2 succeeds, with identical effective authority, because the Owner already holds more than Executive Approver. For anybody who can self-grant Owner the seeded pairs were already cosmetic in capability terms; SoD on the Team page guards honest mistakes, not a `user_management` F holder.
4. One real, small difference: sticky residue. If the Owner row is later removed, the chain leaves Estimator + Executive Approver on the account (a seeded forbidden pair that SoD never revokes; same property as the registration bundle, F4 "nothing is revoked from an account that already holds a pair"). Before this round the same user could keep any single role he had given himself while Owner. Whoever removes an Owner row from such a user must look at his other rows; `role_assignment_logs` records each assignment and its assigner.

Narrowing options considered, all within the same scope; none recommended:

| Option | Cost | Stops QA's chain? | Mitch's sentences stay true? | Verdict |
|---|---|---|---|---|
| N-a. Exempt only if the Owner row was not granted by the target himself (`assigned_by` NULL or different from `user_id`; one `where` in `SodService::holdsOwnerRole`). This is the cheapest narrowing that keeps Mitch's account exempt: his Owner row comes from registration (`OrgOnboardingService::seedOrgRoles` sets no `assigned_by`, so NULL). | About 3 lines, 3 tests, 2 mutants, and a Verifier look because it is the SoD engine | Only the one-user form. A second `user_management` F holder (another Estimator, an Organization Admin) grants him Owner, or two Estimators grant each other, and the chain works again | Yes | REJECT. Security theatre. It also adds a rule with edge cases (`assign()` rewrites `assigned_by` when it reactivates a row; `transferOwnership` writes none) |
| N-b. Only for the org creator | `organizations` has no `created_by`; the Owner row carries no marker: needs a migration | n/a | No: an Owner by ownership transfer or from a co-Owner loses the exemption | REJECT (also a data-model change) |
| N-c. Only if the Owner row is older than N days | A clock in a security rule; trivially waited out; time-dependent tests | No | Yes | REJECT |
| N-d. Only an active Owner (or a listed universal admin) may grant or remove `organization_owner` | New rule in `assignRole`, `removeRole`, `peelOff`, invite acceptance, plus the platform screen; needs its own plan and a Verifier pass | Yes, at the root | Yes | The real fix, but it is open items 6 and 10, not Mitch's items 1 and 2: out of scope by the scope rule. Stays "decision pending with the human" |

Human decision? None needed to proceed: D1 B stays, effective authority is unchanged. For Checkpoint 2, one line for the human to acknowledge: "A seeded Estimator can end up holding Estimator + Executive Approver in two requests through the Owner role; no extra permission while the Owner row exists; root cause is open item 6; the fix is N-d, a separate plan." If the human is not comfortable, the only options with teeth are N-d (separate plan) or reverting N01 to option A; N-a to N-c do not help.

Tasks:

- A2-T1 (QA). Add ONE method to `tests/Feature/Rbac/SodOwnerBundleExemptionTest.php`, no HTTP, no production code: `test_every_role_the_owner_exemption_covers_is_covered_by_the_owner_roles_grants`. With the RBAC seed that the class already loads: read `config('rbac.onboarding.bundles.buyer_small')`, drop `organization_owner`, assert the remainder is not empty (three roles today, so an empty config cannot pass vacuously); for each remaining role and each of its `role_permissions` rows, assert the Owner role has a row for the same permission group and `PermissionMatrix::rank($ownerLevel) <= PermissionMatrix::rank($roleLevel)`. The failure message names role, group and both levels. Mutation proofs, each reverted and verified byte for byte with `cmp`: (m1) add `financial_admin` to `buyer_small` in `config/rbac.php` (budget_and_cost_control F against Owner A; invoice_and_payment_processing F against no Owner row) -> this test red; (m2) in `database/seeders/Rbac/data/role_permission_matrix.php` change `executive_approver` `pricing_management` from R to F (Owner R) -> this test red and no other test of the class needs to fail; (m3) reduce `buyer_small` to `organization_owner` only -> the non-empty assertion fails. Then update the class count in `TESTING.md` (56 -> 57) and the suite total (2381 -> 2382) only after one full background run on in-memory sqlite confirms 2382 passed / the same 4 failures by name. Do NOT write a test that pins the Estimator -> Owner -> Executive Approver chain: it would record an open hole (item 6) as expected behaviour.
- A2-T2 (Developer, docs only, after A2-T1 exists so the test name is real). `ARCHITECTURE.md` section 12 item 6: append at the end of the item, as built, "Round 2 (section 4.8, N01-B) consequence: because `assignRole` lets any `user_management` F holder grant `organization_owner` to himself, a seeded Estimator can reach Estimator + Executive Approver in two requests (first `organization_owner`, then `executive_approver`, which the Owner exemption allows); asking for `executive_approver` directly is still a 422. The Owner role's seeded grants cover Estimator, Procurement Manager and Executive Approver in every permission group (pinned by `SodOwnerBundleExemptionTest::test_every_role_the_owner_exemption_covers_is_covered_by_the_owner_roles_grants`), no code decides anything from those role names, and `approverPool` is matrix-driven, so the extra row adds no permission, no approver-pool membership and no routing change; A05 is unchanged. What the chain leaves behind: if the Owner row is removed later, the account keeps Estimator + Executive Approver (SoD never revokes a pair already held). SoD on the Team page therefore guards honest mistakes, not a `user_management` F holder. Real fix, not done: only an active Owner (or a listed universal admin) may grant or remove `organization_owner`. Decision pending with the human, together with item 10." In section 4.8, N01 bullet, add as the last sentence: "The exempt roles are exactly roles whose seeded grants are all covered by the Owner role's grants (pinned by the test named in section 12 item 6), so the exemption adds no permission to an Owner; a non-Owner with `user_management` F can reach the same state through the Owner role (section 12 item 6)." Nothing else in `ARCHITECTURE.md`.
- A2-T3 (Developer, docs only). `ARCHITECTURE.md` section 4.8, M12 bullet, add as the last sentence: "Atomicity: at the three estimate sites (`store`, `createFromList`, `duplicate`) the enrolment commits on its own, immediately before `Quote::create`; the estimate flows are not transactional (items, attachments and the catalogue sync are written after the create). A database failure between the two (for example two simultaneous creates colliding on the unique `quotes.quote_number`, which `Quote::generateQuoteNumber` derives by counting) leaves the creator enrolled with no estimate: a roster row and a log row for an org-wide creator, nothing for a member, and a creator who had been removed is re-added, exactly as on success. That is the same state as creating an estimate and deleting it, because membership does not depend on the estimate's lifecycle. `RfqController::store` enrols inside the RFQ transaction, so there the enrolment rolls back with the RFQ." Nothing else.

**A2-2. F2: M12 enrolment is atomic with the RFQ but not at the three estimate sites. RULING: ACCEPT. No code change, no test.**

Reasons, from the code:

- The estimate flows contain no transaction at all: `QuoteController` has no `DB::` call; `store` writes the quote, then attachments, then each `QuoteItem`, then `PersistUserCatalogFromQuoteItemPayload::sync`; `createFromList` and `duplicate` write items one by one after `Quote::create`. A transaction around only `enrolCreator` + `Quote::create` would still leave "quote exists, items missing" on a later failure, which the plan never promised to fix. Wrapping the whole flow would also change behaviour (files already stored by `$file->store(...)` are not rolled back, the catalogue sync would join the transaction), which is a different task.
- The wrap is not the smallest diff: `Quote::create([...])` is a 20-line array at `store`, so the wrap is a closure with a six-variable `use` list and a re-indented array at each site. Accepting is smaller.
- Membership does not depend on the estimate: deleting an estimate (`destroy` only soft-deletes the quote) leaves the creator a member, and project creation already enrols independently of anything created later. "Enrolled, nothing created" is therefore a state the system already reaches legitimately, and it is the harmless direction. The opposite order (enrol after the create) would reach "estimate exists, creator not a member", which breaks Mitch's sentence, so the plan's order (before `Quote::create`) stays.
- The stray state needs the caller to have passed `creatableProject` / `writableQuote` (visibility plus level), so it never grants access the caller did not have; for a member it is a no-op, for an org-wide creator one roster row and one log row (plus a reactivation if he had been removed, as on success).
- No nested-transaction question arises because nothing is wrapped. For the record: `ProjectMember::enrol` already opens its own `DB::transaction` and is written to run inside a caller's transaction (its REPEATABLE READ comment), as `ProjectController::store` and `RfqController::store` do, so a wrap would have worked as a savepoint; the Quote model has no observers or `creating` / `created` hooks (grep), so no side effect would have moved. Not needed.
- Realistic trigger, so that the note is honest: two simultaneous estimate creations can collide on the unique `quotes.quote_number` (pre-existing; `generateQuoteNumber` counts rows). The second request answers 500 as before, and now also leaves its creator enrolled.

Documentation: A2-T3. If the human prefers atomicity anyway, it is a follow-up plan, not part of this round: wrap `enrolCreator` and `Quote::create` in one `DB::transaction` at the three sites, plus a test per site with `Quote::creating` throwing (0 quotes, 0 `project_members` rows, 0 `project_member_logs` rows) and the mutant "call `enrolCreator` before the `DB::transaction`".

New risk notes (appended to the Risks section by reference):

- R16. The N01-B state "Estimator + Executive Approver on one account" can now exist without the registration bundle (A2-1 point 4). Accepted; the fix is open item 6 / N-d.
- R17. M12 at the estimate sites leaves a stray membership if `Quote::create` fails (A2-2). Accepted and documented.

**A2-3. For the Verifier (not in the brief the Architect knows of; the Verifier runs in parallel and has not seen this amendment).** (a) Confirm A2-1 point 2 independently: no consumer of the role slug or name `executive_approver` / `procurement_manager` outside config, register labels and message text. (b) `OrgSettingsController::transferOwnership` grants `organization_owner` with `updateOrCreate`, outside `RoleAssignmentService::assign`: no SoD check, no `assigned_by`, no `role_assignment_logs` row. Pre-existing and not changed by this round, but the 4.8 statement "each exempt assignment is written to `role_assignment_logs`" is true for the Team page, invite acceptance and the platform screen only; the ownership transfer is not logged. (c) After A2-T2 and A2-T3 land, the two ARCHITECTURE sentences must be checked against the code as built.

### Amendment 3 (Architect ruling on the Verifier's findings S1, S2 and N1-N10, 2026-11-14)

Status: written by the Architect after the Verifier's "## Verifier (2026-11-13), continued" in REVIEW.md and after QA finished A2-T1. Verdict of the Verifier was FIX REQUIRED, DOCUMENTATION ONLY. This amendment changes no decision the human approved at Checkpoint 1 (D1 B, D2 i, D3, D4 A+B+C, D5 keep the link, D6 warn only; R9 stays out), adds nothing to Mitch's items 1 and 2, and touches no application code, view, migration, route, route map, config or test. Output: documentation edits in `ARCHITECTURE.md` and `TESTING.md` only, written out below as exact before/after text. Nothing was run; the evidence is the code as built and the two REVIEW.md sections, read again for this amendment.

**Supersession.** A2-T2 and A2-T3 (Amendment 2) are replaced by A3-T5, A3-T6 and A3-T8 below, restated in full with the Verifier's precision fixes N9 and N10, N5 and the N2 caveat. The Developer applies Amendment 3 only and does not copy wording from Amendment 2. A2-T1 is DONE (QA addendum at the end of REVIEW.md: `SodOwnerBundleExemptionTest` 57 data sets, new classes 343, full suite 2382 passed / 4 failed, 25177 assertions, 165 s, three mutants killed, nothing else in the class depends on the mutated matrix cell). Those numbers are already in `TESTING.md` and belong to QA: the Developer must not change any existing figure on `TESTING.md` lines 16, 107 and 111 (class count, data sets, totals, assertions, duration, "deprecated" count); only prose is added (A3-T12).

#### A3-1. Rulings

| Finding | Ruling | Docs edit |
|---|---|---|
| S1 stale or false statements in 4.8 and TESTING.md | ACCEPT. Documentation only. Every item the Verifier named is rewritten, plus the further items found in A3-2 | E1, E2, E3, E7, E10, T-a, T-b |
| S2 = QA F1 (chain Estimator -> Owner -> Executive Approver) | ACCEPT N01-B as built (Amendment 2 ruling stands, the Verifier concurs). Wording corrected by N10, N5 and N2. The sentence "stay refused" in 4.8 becomes "request by request", because the human approves D1 B at Checkpoint 2 on that text | E5a, E5c, E9 |
| N1 = QA F2 (M12 enrolment not atomic at the three estimate sites) | ACCEPT (Amendment 2 ruling stands). Sentence restated with N9 | E6 |
| N2 (ownership transfer: no SoD, no `assigned_by`, no `role_assignment_logs` row, no last-role warning) | Pre-existing, outside Mitch's items 1 and 2: NO code change, NO change to the transfer modal. The docs may not imply that how someone became Owner is logged. The 4.8 logging sentence is narrowed to the paths that go through `assign()`; the gap is recorded as new open item 19 (decision pending with the human). L08's "Not covered" list gains the transfer | E4, E5b, E11 |
| N3 (L08 confirmation is advisory under concurrency) | ACCEPT. One sentence in 4.8, marked as reasoned from the code (the tests are single-process, nothing ran on MariaDB) | E4 |
| N4 (`window.openInviteModal` dead in `overview.blade.php`, pinned by `HtmlSinkGuardTest`) | LEAVE. See A3-3 | none (R18) |
| N5 (exemption set follows the onboarding bundle config) | DOCUMENT in 4.8, inside the N01 bullet | E5c |
| N6 (public invite page shows org, role, invited name and address to any token holder) | DOCUMENT, one sentence in the 4.8 O11 bullet. Inherent to O11 and D5 (keep the link); the page was already public for new addresses | E2 |
| N7 (stale route cache makes the new link 404) | DEPLOY NOTE for the human. Documented in `ARCHITECTURE.md` section 10 (release order), pointed to from the 4.8 last bullet; listed under user actions below. NOT in `CLAUDE.md`, NOT in `TESTING.md` (it is not a test matter: the local checkout has no `bootstrap/cache/routes-v7.php`, only `packages.php` and `services.php`) | E7, E8 |
| N8 (UX edges) | NO ACTION. See A3-3 | none |
| N9 (`generateQuoteNumber` does not count rows) | Wording fixed. Code, `app/Models/Quote.php:83-110`: takes the highest numeric suffix (last four characters) of this year's quote numbers, soft-deleted included, adds one, then loops up to 100 times while an `exists()` check finds the number. The unique index is `quotes.quote_number` (`create_quotes_table.php:20`). The race conclusion stands | E6 |
| N10 (the exempt set IS the configured bundle; "covered by the Owner's grants" is a property of the seed) | Wording fixed. `SodService.php:41-47`: the exempt roles are those whose ids are in `config('rbac.onboarding.bundles.buyer_small')` (the Owner role is in that list too, so a rule an admin adds between the Owner and a bundle role would also be exempt). The test pins the SEEDED matrix; the live `role_permissions` rows are editable (open item 6) | E5c, E9, T-c |

#### A3-2. Audit of the round-2 text: every statement that is wrong or not yet true

Checked against the code as built and against `git diff` of `ARCHITECTURE.md` and `TESTING.md` (the working tree, 2026-11-14). Found, in file order:

1. `ARCHITECTURE.md` 4.8 O11 bullet, lines 543-545: "a missing permission the 403 `{message}`" is wrong. The renderer in `bootstrap/app.php:30-52` answers a JSON request with `{rbac_error: true, message}` (`:43`); when enforce mode blocks first, `RbacAudit.php:187-192` adds `group` and `level`. The "no org" answer is `response()->json(['error' => 'No active organization.'], 403)` (`OrgAdminController.php:449`), correct as written. (E1)
2. Same bullet, line 563: "planned class `InviteUniformResponseTest`": the class exists. (E2)
3. S15 bullet, lines 572-574: "planned class `HtmlSinkGuardTest`": the class exists, and the "manual browser step in `REVIEW.md`" should name which REVIEW.md. (E3)
4. L08 bullet, lines 593-594: says nothing about concurrency (N3) and lists only the platform-admin removal under "Not covered"; the ownership transfer also removes a last role without a confirmation (`OrgSettingsController.php:155`). (E4)
5. N01 bullet, lines 606-609: "stay refused with the 422 `sod_conflict`" is true per request only; the chain through the Owner role is not refused (S2). (E5a)
6. N01 bullet, line 612: "The exempt assignment is written to `role_assignment_logs` like any other." True for the Team page (`assign()` at `OrgAdminController.php:111`), invite acceptance (`InviteAcceptController.php:43`) and the platform-admin screen (`Admin/RbacController.php:143`), all through `RoleAssignmentService::assign` (`:63` writes the row, only when the row changes state). It must not be read as "how someone became Owner is logged": the ownership transfer (`OrgSettingsController.php:112`, delete `:155`, `updateOrCreate` `:158-162`, inside one transaction; the file's only `RoleAssignmentLog` use is the org-deletion cleanup at `:99`) leaves no row, no `assigned_by`, no SoD look. (E5b)
7. N01 bullet, lines 616-617: nothing says what the exempt set is, that the coverage of the Owner's grants is a property of the seed, that a later onboarding-bundle change widens the exemption, or that the two-request chain exists. (E5c)
8. M12 bullet, line 637: nothing on atomicity (N1, N9). (E6)
9. Last 4.8 bullet, lines 643-647: "verification pending ... `Round2ClientWordingReplayTest` does not exist yet": the class exists (20 data sets: nine tests in audit and enforce mode plus two single tests) and passes. It says nothing of what was verified, and the L06 limitation (the `delegated_action` row carries the route pattern, not the order) is not stated. (E7)
10. Section 10 has no note for the new route (N7). (E8)
11. Open item 6 does not mention the chain (A2-T2 restated). (E9)
12. Open item 12, lines 928-936: quotes `overview.blade.php (around :301-331)` and `index.blade.php (around :560-590)`; the rewrite moved the scripts (now around `overview.blade.php:252-475` and `index.blade.php:629-850`), and the item still reads as open although round 2 fixed it. (E10)
13. No open item for the ownership transfer. (E11)
14. `TESTING.md:14` heading "updated 2026-10-27, ..." although the paragraph under it is the 2026-11-13 round-2 run. (T-a)
15. `TESTING.md:18` "Full suite on in-memory sqlite ...: **2039 passed / 4 failed**" now reads as the current number under the new heading; it is the pre-round-2 figure. (T-b)
16. `TESTING.md:111` describes `SodOwnerBundleExemptionTest` without the new method. (T-c, prose only)

Checked and correct, no edit: 4.8 intro (no migration, config, env, permission group or role, no map change, `invite.entry` unmapped, 294 routes, controller checks hold in audit mode); `entry` / `register` behaviour (`InviteController.php:17-29`, `:44-65`, guard `:60`); the L08 order of checks and bodies (`OrgAdminController.php:144-169`, `:174-197`); `peel_off_last_role` (`:125-135`); the four `enrolCreator` call sites (`QuoteController.php:302`, `:401`, `:636`, `RfqController.php:111`); sections 2, 4.5, 4.6, 4.7 annotations; the change-log entry (still true: "verified only" means no code change; seven existing classes changed); `TESTING.md` lines 16, 105-127 apart from T-c (all numbers are QA's current ones: 57 / 343 / 2382 / 25177). `ARCHITECTURE.md` line 3 "Last verified against the code: 2026-09-26, branch `fix/p2-cleanup`" is old but pre-dates and is not specific to round 2; later sections carry their dates in the change log. Not touched (no extra). The four classic open items (matrix and org-type counts, `api_system` phase, the audit-middleware redirect, the rep-agency dead code in `sellerAuthorizationError`) are not touched by this amendment or by the round.

#### A3-3. Rulings on N4 and N8, with reasons

**N4: LEAVE.** Facts: `window.openInviteModal` is defined at `overview.blade.php:304-306` and has no caller in that file (the header button at `:38` uses `data-bs-toggle="modal"`). In `index.blade.php` the name is live: the Team-page button calls it (`:70`) and it is defined at `:681`. `HtmlSinkGuardTest.php:250` requires the name in both views through one loop over `INVITE_VIEWS`. It is a three-line leftover of the W12 rewrite (the old "Try again" and "Cancel" callers were removed). Why leave: Mitch's scope rule (items 1 and 2 only, no extras) and strict mode: removing it is a view edit (ui-designer) plus a test edit (QA, the shared name list must keep `openInviteModal` for `index`), then another QA run and Verifier look, to delete code that does nothing. Nothing to document in `ARCHITECTURE.md` or `TESTING.md` (an implementation detail, not behaviour). Recorded as R18 below so that whoever next edits the Overview pop-up removes the function and splits the name out of the guard's shared list in the same change.

**N8: NO ACTION.** (i) A signed-in user who is not the invitee gets the 404 "invite invalid" page from `invite/{token}`. This is already what 4.8 says ("signed in -> 302 to `invite.review` ...: wrong user or unknown token 404") and is step 7 of the manual browser checklist; the old `register/invite/{token}` URL still bounces a signed-in user to the dashboard through its `guest` middleware. Practical consequence for Mitch's retest: an inviter who opens his own invite link in the browser where he is signed in as the Owner sees the 404 page; he must use a private window or another browser. (ii) The peel-off modal keeps the `lastRole` value it captured when it opened, so after the "second tab" 422 it answers 422 again until it is closed and reopened (as the Verifier read the script). Amendment 1 (A1-3) accepted the message, not a retry; it is a page-script edge that no PHPUnit test can prove and `ARCHITECTURE.md` does not describe modal internals. Nothing is written.

#### A3-4. Exact documentation edits (ARCHITECTURE.md, in file order)

The Developer matches each "Before" block against the file text (they are unique; line numbers are today's and only a guide), replaces it with the "After" block, and keeps the surrounding line wrapping. Backticked names are literal. Do not reword.

**E1. `ARCHITECTURE.md` section 4.8, O11 bullet (lines 544-545). The 403 body.**

Before:
```
  `{message, errors}`, a missing permission the 403 `{message}`, no org the existing
  `{error}`. `Auth\InviteController::entry` (`invite.entry`): signed in -> 302 to
```
After:
```
  `{message, errors}`, a missing permission (JSON request) the 403
  `{rbac_error: true, message}` (the exception renderer in `bootstrap/app.php`; when
  enforce mode blocks first, the `RbacAudit` body also carries `group` and `level`),
  no org the existing 403 `{error}`. `Auth\InviteController::entry` (`invite.entry`):
  signed in -> 302 to
```

**E2. Same bullet (lines 562-563). "Planned" wording and N6.**

Before:
```
  closing that needs mailbox verification on guest registration (items 8 and 13).
  Tests: see TESTING.md (planned class `InviteUniformResponseTest`).
```
After:
```
  closing that needs mailbox verification on guest registration (items 8 and 13).
  The public page (`register/invite/{token}`, and `invite/{token}` for a guest)
  shows the organization name, the role, the invited name and the invited e-mail
  address to anyone who holds the token; before this round that was already true
  for the link of a new address, while the link of an existing account
  (`invite/{token}/accept`) needed a signed-in matching user. The token is 48
  random characters (a bearer secret); accepted with D5 (keep the link in the
  answer). Tests: `InviteUniformResponseTest` (TESTING.md).
```

**E3. S15 bullet (lines 572-574).**

Before:
```
  removed. PHPUnit cannot execute the page JavaScript: the proof is a static source
  guard (planned class `HtmlSinkGuardTest`) plus the manual browser step in
  `REVIEW.md`.
```
After:
```
  removed. PHPUnit cannot execute the page JavaScript: the proof is a static source
  guard plus rendered pages with hostile names (`HtmlSinkGuardTest`) and the manual
  browser checklist in the `REVIEW.md` of
  `.claude/tasks/client-audit-2-round2-fixes/` (untracked; back it up).
```

**E4. L08 bullet (lines 593-594). N3 and the transfer.**

Before:
```
  `role_assignment_logs` row. Not covered: the platform-admin removal
  (`Admin\RbacController::removeRole`). Not blocked: removing the org's last Owner
```
After:
```
  `role_assignment_logs` row. The confirmation is a UX safeguard, not a lock: no
  transaction or row lock sits between `isLastActiveRole` and `deactivate`, so (by
  code reading; the tests are single-process and nothing ran on MariaDB) two
  simultaneous removals of a member's two roles each see the other role as still
  active and both succeed, and the member loses both without a confirmation. Not
  covered: the platform-admin removal (`Admin\RbacController::removeRole`) and the
  ownership transfer (`OrgSettingsController::transferOwnership`, section 12 item
  19), which deletes the sitting Owner's row and tells the user only that the Owner
  role is lost. Not blocked: removing the org's last Owner
```

**E5. N01 bullet, three edits.**

E5a (lines 606-609). Per request.

Before:
```
  Executive Approver, in both orders. Everything else is unchanged: Organization
  Admin and every other role, every other pair for an Owner (Requisitioner,
  AP / Invoice Clerk, Financial Admin pairs), an Owner whose Owner row is inactive,
  and an Owner of another org stay refused with the 422 `sod_conflict`. The rule is
```
After:
```
  Executive Approver, in both orders. Everything else is unchanged, request by
  request: Organization Admin and every other role, every other pair for an Owner
  (Requisitioner, AP / Invoice Clerk, Financial Admin pairs), an Owner whose Owner
  row is inactive, and an Owner of another org stay refused with the 422
  `sod_conflict` (what is not stopped is the chain of two requests through the Owner
  role, described at the end of this bullet). The rule is
```

E5b (lines 611-612). N2: what the log sentence covers.

Before:
```
  acceptance and delegation behave the same. `sod_conflict_rules` is not touched.
  The exempt assignment is written to `role_assignment_logs` like any other. A05 is
```
After:
```
  acceptance and delegation behave the same. `sod_conflict_rules` is not touched.
  An exempt assignment made through `RoleAssignmentService::assign` (Team page,
  invite acceptance, platform-admin screen) is written to `role_assignment_logs`
  like any other, with its assigner. That does not extend to how someone became an
  Owner: the ownership transfer grants `organization_owner` outside `assign()`
  (`OrgSettingsController::transferOwnership`), so it runs no SoD check and writes
  no `assigned_by` and no `role_assignment_logs` row (neither `assigned` for the new
  Owner nor `removed` for the old one); an Owner made that way is exempt like any
  other (section 12 item 19). A05 is
```

E5c (lines 616-617). N10, N5 and the chain (replaces A2-T2's bullet sentence).

Before:
```
  "deliberately different people" for the Owner; consistent with plan 6.1, which
  hands the same four roles to a solo or small buyer org at registration.
```
After:
```
  "deliberately different people" for the Owner; consistent with plan 6.1, which
  hands the same four roles to a solo or small buyer org at registration. The exempt
  set is the configured bundle, `rbac.onboarding.bundles.buyer_small`, read at every
  assignment. With the seeded matrix every role in it other than the Owner
  (Procurement Manager, Estimator, Executive Approver) holds each of its permission
  groups at the same or a weaker level than the Owner role, so the exemption adds no
  permission to a seeded Owner; `SodOwnerBundleExemptionTest::test_every_role_the_owner_exemption_covers_is_covered_by_the_owner_roles_grants`
  pins that for the seeded matrix. It is a property of the seed, not a definition
  and not a statement about the running system: any `user_management` F holder can
  edit the live `role_permissions` rows (section 12 item 6). Because the set follows
  the config, a role added to the onboarding bundle later (for example Financial
  Admin for solo orgs) widens every Owner's exemption to each SoD rule between that
  role and the other bundle roles with no other change (the test above fails only
  if that role's seeded grants are not covered by the Owner's), and a rule an admin
  adds between two bundle roles is exempt for an Owner as well; change the bundle
  knowingly. Not stopped: a `user_management` F holder who is not yet an Owner (the
  seeded Estimator, an Organization Admin) can take the Owner role himself and then
  the pair, in two requests; asking for the pair directly is still a 422 (section 12
  item 6).
```

**E6. M12 bullet (lines 636-637). Atomicity, with N9 corrected (replaces A2-T3).**

Before:
```
  failing step turns out to be production data (quotes with NULL `project_id`,
  creators without a membership row) the cause is data, not code.
```
After:
```
  failing step turns out to be production data (quotes with NULL `project_id`,
  creators without a membership row) the cause is data, not code. Atomicity: at the
  three estimate sites (`store`, `createFromList`, `duplicate`) the enrolment
  commits on its own (`ProjectMember::enrol` opens its own transaction),
  immediately before `Quote::create`; the estimate flows are not transactional
  (`QuoteController` has no `DB::` call; items, attachments and the catalogue sync
  are written after the create). A database failure between the two leaves the
  creator enrolled with no estimate: one roster row and one log row for an
  org-wide creator, nothing for a member, and a creator who had been removed is
  re-added, exactly as on success. The realistic trigger is two simultaneous
  creates colliding on the unique `quotes.quote_number`: `Quote::generateQuoteNumber`
  derives the number from the highest one issued this year (soft-deleted included)
  plus a non-atomic `exists()` check, so both requests can pick the same number and
  the second fails on the unique index with a 500. That is the same state as
  creating an estimate and deleting it, because membership does not depend on the
  estimate's lifecycle. `RfqController::store` enrols inside the RFQ transaction,
  so there the enrolment rolls back with the RFQ.
```

**E7. 4.8 last bullet (lines 643-647). Replay class verified; limitation; release pointer.**

Before:
```
- **Not rebuilt (W10, L05, L06, S13); verification pending.** No code change for
  these in this round; section 4.6 and 4.7 describe them. The planned replay class
  `Round2ClientWordingReplayTest` does not exist yet, so nothing in this round has
  verified them. S13 remainder is server configuration (`APP_DEBUG=false`,
  `config:cache`).
```
After:
```
- **Not rebuilt (W10, L05, L06, S13); replayed in the client's words.** No code
  change for these in this round; sections 4.6 and 4.7 describe them.
  `Round2ClientWordingReplayTest` (verification only, 2026-11-13; counts in
  TESTING.md) replays each in the client's own steps, in audit and enforce mode,
  and passes. W10: an Estimator creates a project; the Owner, who is not a member,
  finds it in the project list and on the projects page, opens it, is not enrolled
  by opening it, and no `blocked` / `would_block` row is written; a non-org-wide
  non-member and another org's Owner do not see it. L05: in an org with several
  members whose Owner is the only approver, an RFQ conversion gives status
  `processing` and exactly one `auto_approved` row (who, org, order number, date),
  shown with its label on `org-admin/audit-log?tab=enforcement`; a sole approver
  approving his own pending order writes a `self_approved` row; with a second
  approver there is no row. L06: a delegation grant and its revoke each write one
  row, and a delegate's request writes one `delegated_action` row ("acting for
  <principal>"), all shown on the same page. Known limitation, pinned on purpose:
  the `delegated_action` row carries the route pattern (`orders/{order}/approve`)
  and the principal, not the order number or id; the order's own `approved_by`
  records the delegate (PLAN R9, not approved). S13: with `APP_DEBUG=true`, JSON
  403, 404, 422 and 500 answers (a thrown `RuntimeException`, `TypeError` and
  `QueryException` included) carry no `exception`, `file`, `line` or `trace` key and
  no class name, SQL text or server path. Not testable here: the HTML error page and
  a stale `bootstrap/cache/config.php`. S13 remainder is server configuration
  (`APP_DEBUG=false`, `config:cache`); the new route also needs the route cache
  refreshed where one exists (section 10).
```

**E8. Section 10 (after the "Release gates" bullet, line 796). N7.**

Before (anchor, keep as is; insert the new bullet right after it):
```
- Release gates: back up and test-restore the database; run the backfill
  (section 8) before or with any release that contains 5a; do not run 5b in the
  same release.
```
Insert after it:
```
- Route cache (client audit 2 round 2): the release adds the public route
  `GET invite/{token}` (`invite.entry`). If the server has a cached route file
  (`bootstrap/cache/routes-v7.php`), every newly mailed `/invite/{token}` link
  answers 404 until the cache is refreshed, while the old `/register/invite/{token}`
  and `/invite/{token}/accept` links keep working. After uploading the code run
  `php artisan route:clear` (or `route:cache` to rebuild it); `optimize:clear` also
  works but removes the config cache too, so run `config:cache` again afterwards
  (`APP_DEBUG=false`, S13). The local checkout has no route cache file.
```

**E9. Section 12 item 6 (lines 877-878). The chain (replaces A2-T2's item-6 text).**

Before:
```
   expires (`PermissionMatrix::CACHE_TTL` is 86400 s, 24 h). Decision pending with
   the human; not changed.
```
After:
```
   expires (`PermissionMatrix::CACHE_TTL` is 86400 s, 24 h). Decision pending with
   the human; not changed. Round 2 (section 4.8, N01-B) consequence: because
   `assignRole` lets any `user_management` F holder grant `organization_owner` to
   himself, a seeded Estimator can reach Estimator + Executive Approver in two
   requests (first `organization_owner`, then `executive_approver`, which the Owner
   exemption allows); asking for `executive_approver` directly is still a 422. The
   seeded Owner role covers Estimator, Procurement Manager and Executive Approver in
   every permission group (pinned by
   `SodOwnerBundleExemptionTest::test_every_role_the_owner_exemption_covers_is_covered_by_the_owner_roles_grants`;
   that pins the seed, not the live `role_permissions`, which this very item lets
   any `user_management` F holder edit), no code decides anything from those role
   names, and `approverPool` is a query over `role_permissions` (`approval_authority`
   F or A). So with the seeded matrix the extra row adds no permission, no
   approver-pool membership and no routing change, and A05 is unchanged. What the
   chain leaves behind: if the Owner row is removed later, the account keeps
   Estimator + Executive Approver (SoD never revokes a pair already held). SoD on the
   Team page therefore guards honest mistakes, not a `user_management` F holder. An
   ownership transfer (item 19) is a second way to become Owner outside the
   assignment engine. Real fix, not done: only an active Owner (or a listed universal
   admin) may grant or remove `organization_owner`. Decision pending with the human,
   together with item 10.
```

**E10. Section 12 item 12 (lines 928-936). Line numbers and status.**

Before:
```
12. **Invite modal script ignores error responses** (Verifier C4). The script in
    `user/org-admin/overview.blade.php` (around :301-331) and `index.blade.php`
    (around :560-590) reads only an `error` key; any non-2xx JSON (403
    `rbac_error`, 422 validation body) falls into the success branch and prints
    `undefined` as the link. Decision pending with the human; not fixed by
    client audit 2 Phase A. Round 2 (section 4.8, S15 and W12): both pop-up
    scripts are rewritten; a non-2xx answer or a missing `link` shows the server
    message, the unreachable branches are removed (they were also the S15
    sinks). Page JavaScript has no automated test (PHPUnit cannot run it).
```
After:
```
12. **Invite modal script ignored error responses** (Verifier C4; fixed in round 2,
    see the end of this item). Before round 2 the script in
    `user/org-admin/overview.blade.php` and `index.blade.php` read only an `error`
    key; any non-2xx JSON (403 `rbac_error`, 422 validation body) fell into the
    success branch and printed `undefined` as the link. Not fixed by client audit 2
    Phase A. Round 2 (section 4.8, S15 and W12): both pop-up scripts
    (`window.sendInvite` and `doInviteFetch` in each file) are rewritten; a
    non-2xx answer or a missing `link` shows the server message, the unreachable
    branches are removed (they were also the S15 sinks). The behaviour of page
    JavaScript has no automated test (PHPUnit cannot run it; `HtmlSinkGuardTest`
    scans the source and renders the pages with hostile data).
```

**E11. Section 12, new item 19 (insert after item 18, before "## Change log"). N2.**

Insert:
```
19. **Ownership transfer sits outside the role-assignment engine (pre-existing;
    found while checking N01 in round 2).**
    `OrgSettingsController::transferOwnership` hard-deletes the sitting Owner's
    `user_org_roles` row and grants `organization_owner` to the new Owner with
    `updateOrCreate`, inside one transaction. It does not call
    `RoleAssignmentService`, so there is no SoD check, no `assigned_by` /
    `assigned_at` on the new row and no `role_assignment_logs` row (no `assigned`
    for the new Owner, no `removed` for the old one); only a listed universal
    admin's override writes an audit row. Consequences: how a user became Owner is
    not always in the role log; an Owner made this way is covered by the N01
    exemption (section 4.8) like any other; and an Owner who holds no other role
    leaves the organization when he transfers, with no last-role confirmation (L08,
    section 4.8, covers the Team page and peel-off only), because the Settings modal
    says only that the Owner role is lost. Options: route the transfer through
    `assign()` / `deactivate()` (SoD, log) and add "and you will leave the
    organization" to the modal when he holds no other role. Decision pending with
    the human; not changed by round 2.
```

#### A3-5. Exact documentation edits (TESTING.md). Prose only; QA's numbers stay as they are

**T-a. Heading (line 14).**

Before:
```
## Known-failing baseline (updated 2026-10-27, after client audit 2 Phase A, its verifier amendments, Phase B batch 1 incl. its V1 amendment and QA's edge tests, and Phase B batch 2 / W10)
```
After:
```
## Known-failing baseline (updated 2026-11-13, after client audit 2 Phase A, its verifier amendments, Phase B batch 1 incl. its V1 amendment and QA's edge tests, Phase B batch 2 / W10, and round 2)
```

**T-b. Line 18, first words only.**

Before:
```
Full suite on in-memory sqlite (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`): **2039 passed / 4 failed** (measured 2026-10-27
```
After:
```
Full suite on in-memory sqlite (`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`), before round 2: **2039 passed / 4 failed** (measured 2026-10-27
```
(The rest of the line stays byte for byte.)

**T-c. Line 111, the `SodOwnerBundleExemptionTest` bullet: append one clause at the end. Do not touch "(57)" or any other existing figure.**

Before (the end of the bullet):
```
invite acceptance and the platform-admin screen follow the same rule; A05 and auto-approval unchanged.
```
After:
```
invite acceptance and the platform-admin screen follow the same rule; A05 and auto-approval unchanged; `test_every_role_the_owner_exemption_covers_is_covered_by_the_owner_roles_grants` (no HTTP) pins that every role of the configured bundle other than the Owner is covered by the Owner role's SEEDED grants (same permission group, equal or stronger level, so the exemption adds no permission to a seeded Owner); it reads the seeded matrix only, not the live `role_permissions` rows, which `PUT org-admin/roles-list/permissions` can edit (ARCHITECTURE section 12 item 6), and it deliberately does not pin the Estimator -> Owner -> Executive Approver chain (that would record open item 6 as expected behaviour).
```

#### A3-6. Task list for the Developer (docs only, in this order, log each to PROGRESS.md)

Scope: `ARCHITECTURE.md` and `TESTING.md` only. No other file, including `CLAUDE.md`, `HANDOVER.md`, `docs/client-audit-pack/*`, code, views, tests, PLAN.md and REVIEW.md. Match by the "Before" text, not by line number. Do not change any existing figure on `TESTING.md` lines 16, 107 and 111 (QA's counts: 57, 343, 2382, 25177, 165 s, 2377).

- A3-T1. E1 (403 body), `ARCHITECTURE.md` 4.8 O11 bullet.
- A3-T2. E2 (planned wording and N6), same bullet.
- A3-T3. E3 (`HtmlSinkGuardTest` exists, which REVIEW.md), S15 bullet.
- A3-T4. E4 (N3 and the transfer in "Not covered"), L08 bullet.
- A3-T5. E5a, E5b, E5c (N01 bullet: per request, the log sentence narrowed for N2, the exempt set / N10 / N5 / the chain). Replaces A2-T2's 4.8 sentence.
- A3-T6. E6 (M12 atomicity, N9 wording), M12 bullet. Replaces A2-T3.
- A3-T7. E7 (replay class, limitation, release pointer), last 4.8 bullet.
- A3-T8. E9 (open item 6 chain text). Replaces A2-T2's item-6 text.
- A3-T9. E8 (section 10 route-cache bullet) and E10 (open item 12).
- A3-T10. E11 (new open item 19). Do this before the final self-check, because E4, E5b and E9 point to it.
- A3-T11. T-a, T-b (TESTING.md heading and "before round 2").
- A3-T12. T-c (TESTING.md line 111 clause, prose only, no digit changed).
- A3-T13. Self-check, no execution against any database: `grep -n "planned class\|does not exist yet\|verification pending\|:301-331\|:560-590" ARCHITECTURE.md` returns nothing; `grep -n "403 .{message}., no org" ARCHITECTURE.md` returns nothing; `grep -n "^19\\." ARCHITECTURE.md` finds the new item and 4.8 points to it from the L08 and N01 bullets and from item 6; `git diff -U0 TESTING.md` shows only the three prose edits on lines 14, 18 and 111 on top of QA's earlier numbers (the figures on lines 16, 107 and 111 are unchanged from what QA left); no file other than the two named shows a new change. Then write one PROGRESS.md entry and hand back to the Architect with the list of lines changed. The Verifier's open check A2-3 (c) then runs read-only against the new text.

Nothing in Amendment 3 needs the ui-designer, QA or any code change. QA's A2-T1 is complete. The only gate left after the Developer is the Verifier's A2-3 (c), a read-only comparison of the new sentences with the code, then human Checkpoint 2.

#### A3-7. New risk notes (added to the Risks section by reference)

- R18. `window.openInviteModal` in `overview.blade.php:304-306` has no caller; `HtmlSinkGuardTest.php:250` pins its presence in both pop-up views. Left on purpose (N4, scope rule). When the Overview pop-up is next edited: delete the function and take `'openInviteModal'` out of the shared name list for that view in `test_the_invite_pop_up_functions_the_markup_and_the_script_call_exist_in_both_pop_ups` (it stays for `index`).
- R19. Ownership transfer is outside SoD and the role log (N2). Pre-existing, documented as open item 19; the Settings modal wording is unchanged. If Mitch's L08 retest uses Settings > Transfer Ownership he will see no confirmation; tell him the Team page and the peel-off prompt are the L08 paths.
- R20. A cached `bootstrap/cache/routes-v7.php` on the server makes every newly mailed `/invite/{token}` link 404 (N7). User action below.
- R21. The public invite page shows organization, role, invited name and address to any token holder (N6); inherent to O11 and D5, documented in 4.8.
- R22. The L08 confirmation is advisory under concurrency (N3) and the exemption set follows the onboarding bundle config (N5); both documented in 4.8, no code.

#### A3-8. User actions added (to "Out of scope / user actions")

- After uploading this release: if the server has `bootstrap/cache/routes-v7.php`, run `php artisan route:clear` (or `route:cache`), and `config:cache` again if `optimize:clear` was used (it removes the config cache). Check with `ls bootstrap/cache` before and `php artisan route:list --path=invite` after (it must list `invite/{token}`, name `invite.entry`).
- Tell Mitch to open invite links in a private window or another browser (N8): in the browser where he is signed in as the Owner the link ends in the "invalid" 404 page.

#### A3-9. What the human must acknowledge at Checkpoint 2 (plain words)

1. F1 chain: a seeded Estimator (or anyone who can manage roles) can give himself Owner and then Estimator + Executive Approver in two requests; with the seeded matrix this adds no permission while he is Owner; the root cause is open item 6 and the real fix is a separate plan.
2. D2 side effect: creating an estimate or RFQ re-adds a creator who had been removed from that project, and gives an Owner an explicit roster row (one row and one log entry per project).
3. R4 residual: whoever holds an invite link (the inviter) can still tell an existing address from a new one by submitting the guest register form; closing it needs mailbox verification (open items 8 and 13).
4. N2: transferring ownership removes and grants Owner with no SoD check, no log row and no last-role warning; it already was so, now recorded as open item 19.
5. N6: the public invite page shows the organization, role, invited name and address to anyone holding the link (the link is a 48-character secret; accepted with D5).
6. N7: after the upload, if the server caches routes, run `php artisan route:clear`; otherwise every new invite link answers 404.

# Wisselbanken — Handover (snapshot 2026-09-27)

> **P2 status (2026-09-27): implemented on `fix/p2-cleanup` (cut from `fix/rfq-project-scope-and-approval`), committed locally (`7021dd8` + one follow-up commit) — NOT pushed, human pushes.**
> Done: P2-A (server-side level checks on every mapped route + all-routes guard test), P2-B (items 11, 12 already correct, 13, 15, 16),
> P2-C (zero approvers refused), P2-D (ARCHITECTURE.md refreshed, HANDOVER-CLIENT.md, docs/WEEK12-QA-REPORT.md/.pdf, TESTING.md).
> Suite 1016 passed / 4 failed (the known 4); views compile; mutations killed (one equivalent survivor); one Verifier: CONFIRM.
>
> **2026-09-27 follow-up (client doc "WB RBAC — Unblock the Audit", item 5, plus 3 Verifier notes fixed on the same branch):**
> - `RbacAudit::friendlyMessage()` now takes the denial `reason`. A project-scoped denial where the user's org-level role
>   already grants the required level, but they just aren't on the project, now shows "You are not a member of this
>   project..." instead of the generic "you don't have permission" text (matches what the audit log already recorded as
>   `no_grant_or_not_project_member`). `no_org` and `project_unresolved` also got clearer text while in there.
> - `RfqSellerController::respond` now locks the RFQ and recipient rows and re-checks status inside the transaction
>   before creating a response (was: plain read then write, racy).
> - `RfqSellerController::decline` now also requires the recipient to still be `pending` (was: only blocked on RFQ
>   `converted`, so a seller who'd already responded could still decline, leaving the response selectable).
> - Removed the stray `dd($e->getMessage())` in `CheckoutController::processCheckout`'s catch block (dumped the raw
>   exception to the customer and skipped `DB::rollBack()` regardless of `APP_DEBUG`); now logs via `Log::error` and
>   rolls back before redirecting.
> - Still open, not touched: `processCheckout` reads the session org for routing/`orders.org_id` while the S check
>   uses `CurrentOrg::id` (Verifier note, low severity); unmapped pallet routes have no auth (pre-existing, out of scope).
>
> Accepted: data-scoped reads and 404-before-403 on scoped writes (documented in `AuditFindingsRound2Test`). Section 10 below is the original work order.

Purpose: everything needed to resume in a new chat without re-deriving anything. Read this, then `CLAUDE.md`,
then the last "Status" line of `.claude/tasks/projects-entity/PROGRESS.md`. Do NOT read the whole
`PLAN.md` (2,018 lines of stacked amendments) unless a specific section is needed.

Facts below were verified against the code/git on 2026-09-26 unless marked **(reported)** = told to us by the
human and not verifiable from this repo, or **(unverified)**.

---

## 1. Where we are in one paragraph
The client (Mitch, SprocketWorx, org_id 2) is acceptance-testing the custom RBAC module (Laravel 11). We
delivered the "Projects as a first-class entity" feature they demanded (Phases 1-5), fixed the findings in
their "WB RBAC — Audit 1.0" report, and closed three gaps we found in the scope document (RFQ approval
routing, RFQ project scoping, procurement check on convert). All of it is committed and pushed. Production is
**(reported)** running with `APP_DEBUG=false` and RBAC **enforcement ON** (all batches), with the release
deployed. Still open: production verification, client communication, documentation refresh, staging, a few
known small gaps (section 7).

## 2. Repo, branches, tests
- Repo: `/Users/asad/Documents/QX/wisselbanken`, GitHub `Amush0041/wisselbanken` (remote `origin`).
- **Latest branch: `fix/rfq-project-scope-and-approval` (`cad611e`)** — contains everything below; pushed.
  Branches build on each other (each contains the previous):

| Branch | Tip | Contents |
|---|---|---|
| `feature/projects-entity` | `a5395e7` (local only; ancestor of the others) | Projects Phases 1-4 |
| `feature/projects-entity-phase5` | `62f4272` (pushed) | + Phase 5a/5b, post-walkthrough fixes |
| `fix/audit-1.0-findings` | `3507b5b` (pushed) | + client audit fixes, membership log |
| `fix/rfq-project-scope-and-approval` | `cad611e` (pushed) | + RFQ project scope / approval routing |
| **`fix/p2-cleanup`** | **committed locally, not yet pushed** | + P2: server-side checks on every mapped route, zero-approver refusal, small fixes, docs |
| `dev` / `main` | `813d150` / `759c7fd` | untouched; no PR/merge has been made |

- Test baseline (run on in-memory sqlite): `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`
  → **1012 passed / 4 failed** (was 939 before P2, branch `fix/p2-cleanup`). The 4 known failures, none caused by our work:
  1. `AuditMiddlewareTest` "enforce mode blocks and logs" (expects 403, gets 302 redirect — non-JSON denials
     redirect back by design, known open item).
  2. `SeedMatrixTest` "seeds expected counts" (asserts 20 org types, seed has 19).
  3. `SeedMatrixTest` "phase distribution" (`api_system` seeded P3, test expects P2).
  4. `ExampleTest` "the application returns a successful response" (laravel scaffold; "no such table: divisions").
- Working tree: clean. `.claude/tasks/projects-entity/` (plan, review trail, progress) is now committed on `fix/p2-cleanup`.

## 3. What was completed
**A. Verification/setup (start of engagement).** Verified the old `HANDOFF.md` against code and the plan PDF
(it had errors; it has since been removed from the tree). Built `CLAUDE.md`, `ARCHITECTURE.md`, `TESTING.md`,
`.claude/agents/*` (architect, developer, qa-tester, reviewer, ui-designer — all model `sonnet`).

**B. Client's first urgent report.** Root cause of the `/user-dashboard` 500: production ran code ahead of its
migrations (`rfq_requests` missing) — the human ran the migration. `APP_DEBUG=false` set **(reported)**.

**C. Projects feature (client's main demand).** A project now contains several quotes; membership, permission
checks and the crosswalk are project-keyed; creator auto-enrolled; Projects in main nav; existing quotes
migrated by a backfill command.
- Phase 1 additive schema + models; Phase 2 `projects:backfill`; Phase 3 permission layer + read paths;
  Phase 4 write paths/UI (lean mode); Phase 5a code (quote visibility is project-only) and 5b guarded
  irreversible migrations (**never run anywhere**).
- Browser walkthrough (scratch DB clone) found 3 UX issues, fixed: raw 422 page on project delete,
  estimates/editor now link to parent project, add-member dropdown excludes existing members; also fixed
  `org-admin.connections.index` route name on RFQ create.

**D. Client Audit 1.0 fixes (branch `fix/audit-1.0-findings`).**
- Server-side level checks mirroring the route map on OrgAdmin/OrgSettings/Delegation/ApiToken/ProjectMember
  actions (previously in AUDIT mode a viewer could self-assign Owner and edit the role matrix — closed).
- View gating: project access controls, settings read-only below F, connections, RFQ empty state,
  user-services, "also held by" flag on Team; `my-roles` open to every org member (self-scope);
  `overview` stays `user_management` R; connections GET → R; customers reads → `quote_rfq_management` R.
- New additive table `project_member_logs` (+ model `ProjectMemberLog`, fail-soft if table missing) logging
  membership added/reactivated/removed; shown on the audit-log page. Removed the leftover `GET test-session`.

**E. Scope-document gaps (branch `fix/rfq-project-scope-and-approval`).**
- G1 `RfqController::convertToOrder` now uses `ApprovalRoutingService` (auto-approve vs `pending_approval`).
- G3 convert needs `quote_rfq_management` F **and** `procurement` S.
- G2 RFQs scoped to projects: nullable `rfq_requests.project_id`; new RFQs must be in a visible project;
  buyer reads filtered; sellers never see the project; a project with RFQs cannot be deleted.
- Double-convert race fixed (`lockForUpdate` + status guard). Mirrored level checks on RFQ controllers.

**F. Client deliverables produced.** Reply PDFs to the audit: `~/Downloads/WB_RBAC_Audit_1.0_Response.pdf` (v1)
and `..._v2.pdf` (current; **does not yet mention RFQs-on-a-project**). Attachment pack:
`~/Downloads/WB_RBAC_Audit_Attachments/` (route-list.json, route_permission_map.php, CheckRole.php,
RbacAudit.php, unmapped_routes.txt [13 routes], production_checks_READ_ONLY.sql).

## 4. Current system state (key facts a new session must know)
- **Single permission entry**: `PermissionService::checkPermission(userId, orgId, group, level, projectId?)`.
  Levels F > A > O > S > R. `RbacAudit` (global web middleware) reads `config/route_permission_map.php`
  (`quote_param` = a quotes.id resolved to its project; `project_param` = a projects.id; unresolved scope
  fails closed as `project_unresolved`). Unmapped routes pass through with no check and no log.
- **Operative RBAC mode is the DB row `rbac_settings.rbac_mode`, not `.env`.** Local `.env` says `enforce`;
  production **(reported)** now enforce (human chose to keep it ON; the client's own rule was "nothing
  enforced on production before staging" — the v2 reply says so plainly and offers to revert).
- **Controllers ALSO check levels server-side** (mirroring the map) so protections hold in audit mode too.
  New write actions must add the same (`OrgAdminController::requireOrgLevel`, etc.).
- **Projects**: `projects`, `quotes.project_id`, `project_members.project_id` (+ org tie), `plan_crosswalk.project_id`,
  `rfq_requests.project_id`. Use `ProjectMember::enrol()` / `deactivate()` (they log to `project_member_logs`);
  the backfill must NOT call `enrol()`. `Quote::visibleTo` = project visible AND (`estimate_management` R OR
  author); NULL-project quotes are visible to nobody (Phase 5a). Current org via `CurrentOrg::id`.
- **Phase 5b migrations** (`2026_09_25_000001-3`) are guarded (maintenance mode + `PHASE5_BACKUP_CONFIRMED` +
  pre-conditions) and were **not** to be deployed with the release; run only after backfill and a ~14-day soak.
- **Backfill** `php artisan projects:backfill {--dry-run} {--overrides=} {--force} {--allow-live}`: real run
  needs maintenance mode (or `--allow-live`); reports in `storage/app/backfill/projects-entity/<ts>/`.
  Runbook order: backup (test-restored) → `down` → dry-run → dry-run with final overrides → real run
  (`--force --no-interaction`) → verification dry-run must be all zeros → `up`.
- **Deploy order for this release**: run migrations first (`2026_09_24_000006` membership log,
  `2026_09_26_000001` RFQ project link), then upload code; keep `2026_09_25_*` out; backfill with the release.
  If the code ships before migrations: buyer RFQ index/create 500 and membership events are not logged.
- **Approvals**: `ApprovalRoutingService` (checkout + RFQ convert). Zero-approver org ⇒ order stuck in
  `pending_approval` (trap, same as checkout).
- Matrix facts: `organization_owner` has `audit_and_logging` R in the repo seed and local DB (client claims
  otherwise — see open task P0-3). 25 groups / 19 org types / 49 roles in seed vs plan's 24/20/31+17.

## 5. Human decisions on record
Project model: no membership bypass for owners (Q1); refuse deleting a project with estimates (Q6) or RFQs; refuse
whole user/org deletion when an org owns projects (B3); re-home valid legacy membership rows else list for
review (B4); H1 own project per unnamed quote; H3 real backfill only in maintenance mode; H4 unlinked crosswalk
= exit 0 + summary; H5 keep backfill reports with the backup until Phase 5 gate; H6 warn on invalid-UTF-8
address. H7 members see teammate's quote incl. customer/attachments; `staff_notes` hidden, `pdf_path` written
by owner only; H9 member reads need `estimate_management` R. Phase 4: project_management levels S create / O
update / F delete / F members; `bid_due_at` datetime (UTC); org-admin/projects page kept.
Later: **keep enforcement ON in production**; build membership logging (#11) with an additive table; remove
the debug route; build G1+G2+G3; **G4 (Contract→Price List, GPO pricing) — leave out, do not raise with the
client yet**; docs refresh (G6) not requested yet.

## 6. Workflow & cost notes
- Agents (`.claude/agents`): architect, developer, qa-tester, reviewer (Verifier), ui-designer — all `sonnet`.
- Strict mode (Architect→Developer→Architect check→QA→Verifier→human) cost ~8-9M tokens for Phases 1-3. The
  approved economy: Phase 4 in LEAN mode; audit/RFQ fixes used **Developer → QA (mutation proofs on a scratch
  copy) → one Verifier pass**, fresh short-brief agents, orchestrator re-runs the suite itself. Schema/permission
  engine changes still deserve a Verifier. Independent review repeatedly found real bugs — keep one Verifier.
- Claude Code's auto-mode blocked `git push` from the session: **the human pushes**.

## 7. Open tasks (priority order)
**P0 — verify production (needs the human)**
1. Run `~/Downloads/WB_RBAC_Audit_Attachments/production_checks_READ_ONLY.sql` on production and give the results:
   Owner's `audit_and_logging` row (M11), `rbac_mode`, quotes without a project, Would Block list (Week-9
   review), **blocked rows under enforcement** (who is being wrongly denied), migrations run.
2. Confirm the deploy really followed the order in section 4 and `migrate:status` matches (the 5b files absent).
3. If the Owner's `audit_and_logging` row is missing on production: matrix data fix (do not blind re-seed).
4. Has the backfill been run? With Phase 5a live, NULL-project quotes are invisible to everyone.
5. Test the double-convert protection on the real server (two simultaneous POSTs to `rfq/{id}/convert` ⇒ 1 order).

**P1 — client communication**
6. Send the reply PDF (update v2 to mention RFQs on a project; enforcement statement needs the human's OK);
   attach the pack; still owed to the client: APP_DEBUG 404 screenshot, `migrate:status`, seed SQL CSV (their
   Sep 23 queries), staging URL, written Week-9 log review, date for the project container (only after staging).
7. A **staging environment** does not exist; the client requires all real-403 testing there.
8. Client matrix decisions pending (24 vs 25 groups, Customer/Account Mgmt group, crosswalk group, approver pool
   distinct users, Viewer read on Quote/RFQ/Customers, etc.).

**P2 — development follow-ups: DONE on `fix/p2-cleanup`** (server-side checks on every mapped route, delete-user log, pendingRfqs,
zero-approver refusal, respond guard, header layout, ARCHITECTURE.md, HANDOVER-CLIENT.md, Week-12 QA report). Still open:
9. Client matrix decisions: `SeedMatrixTest` failures (org types 19 vs 20; `api_system` P2 vs P3; 24 vs 25 groups);
   Auditor / Read-All holds F on `organization_management` (client decision).
10. Delegation (Q11) open; MariaDB-only checks (enrol race, `FOR UPDATE`, quotes.user_id cascade) never run outside sqlite.
11. Low-severity Verifier notes: `respond` not atomic; `decline` after a response leaves it selectable; `processCheckout` routing
    reads the raw session org key while the S check uses `CurrentOrg::id`; stray `dd()` in the `processCheckout` catch block;
    unmapped pallet routes have no auth.

**P3 — later**
18. Phase 5b tightening (after backfill, P1=0, soak); backfill command removal 30 days after 5b.
19. G4 Contract→Price List / GPO pricing — out of scope for now (client may raise it).

## 8. Gotchas
- Local `.env` has `APP_ENV=production` and `DB_DATABASE=wisselbanken` = the REAL local database. Never run
  migrate/backfill/tinker writes against it; tests use in-memory sqlite; for browser checks clone the DB into a
  scratch DB and drop it afterwards (used `wb_walkthrough`, dropped). The real local DB already had the Phase 1
  migrations applied (by someone else) and holds a `storage/app/backfill/.../dry-run` folder (git-ignored).
- The shell's `head` command is broken (aliased) — use `sed -n 1p`/`grep`. `timeout` is not installed.
- PDFs: rendered with headless Google Chrome from HTML (no reportlab installed).
- Two mistakes we corrected earlier: `RbacAudit` runs FIRST and wraps `CheckRole` (not the reverse); and the
  client's M11 claim (Owner lacks audit-log access) does NOT match our seed.
- Files: plan/review trail `.claude/tasks/projects-entity/{PLAN,PROGRESS,REVIEW,PHASE4}.md` (untracked), scope PDF
  `~/Downloads/Wiselbanen_RBAC/Revised_RBAC_Module_Implementation_Plan.pdf`, client audit
  `~/Downloads/WB RBAC — Audit 1.0 (Basic Limited Role Audit).pdf`, memory notes
  `~/.claude/projects/-Users-asad-Documents-QX-wisselbanken/memory/`.

## 9. Commands
```
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test                # full suite
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=Rbac
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan view:cache && php artisan view:clear   # compile all views
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan route:list --json
```

---

## 10. P2 WORK ORDER (decided 2026-09-26 — do this in a NEW chat; "complete P2, nothing missed")

Human status: **P0 and P1 are done and verified by the human. Do not re-ask about them.** The human wants ALL of P2
implemented, in one fresh context. Items 18 and 19 are excluded (see below).

**Process (agreed):** new branch `fix/p2-cleanup` cut from `fix/rfq-project-scope-and-approval` (already pushed).
Developer → QA (tests + mutation proofs on a scratch COPY of the repo, never the real repo) → ONE independent
Verifier → orchestrator re-runs the suite itself → human approves before any commit. Fresh short-brief agents
(`.claude/agents`, all sonnet). Claude cannot `git push` (auto-mode blocks it): the human pushes. Never touch the
local MySQL DB (`.env` = APP_ENV=production, DB `wisselbanken` is real); tests run on in-memory sqlite. For
browser checks clone the DB into a scratch DB (`mysqldump wisselbanken | mysql wb_walkthrough`; login as
`gc-owner@rbac-test.local` / `password`, mark the user's email verified in the CLONE only; serve with
`env DB_DATABASE=wb_walkthrough APP_ENV=local APP_DEBUG=true php artisan serve --port=8001`), then drop it.

**P2-A (item 10) — server-side level checks on every remaining mapped route (audit-mode hole).** Enforce mode
already blocks these via the route map; in AUDIT mode the middleware only logs, so each controller action must
also check the level server-side, mirroring its `config/route_permission_map.php` entry exactly (403, org from
`CurrentOrg::id`, project id passed where the route is project-scoped). Residual list found by the Verifier
(see REVIEW.md "Verifier — audit 1.0 fixes (round 2)"): **writes** — POST `customers`, `user-products`,
`user-services`, `rfq` (already done), `remove-lists-items`, `migrate-session-pallet`, quotes `duplicate/update/
saveEditor/updateItem/destroy/destroyItem`, `plan-crosswalk` PUT/DELETE, POST `projects/{project}/crosswalk`, and
other mapped writes; **reads** — `customers`, `quotes` (list/details/pdf), `rfq`, `rfq-incoming`, `view-lists`,
`list-view`, `user-products`, `user-services`, `view-orders`, `order-detail`, `order-approvals`, pallet/checkout
reads. Start by producing the authoritative list programmatically (iterate the route map, resolve each route's
controller action, flag actions with no `checkPermission`/`requireOrgLevel`/`visibleProject`-style check).
Watch for: routes whose map group differs from the controller's own scoping (quotes use project visibility +
`estimate_management`; customers are `quote_rfq_management` R reads / `user_management` S,O,F writes); do not
change matrix data or the map unless a route is wrongly mapped (report it instead); keep the 302-vs-403 behaviour.
**Completion criterion:** extend the data-driven guard in `tests/Feature/Rbac/AuditFindingsRound2Test.php`
(currently covers the four org-admin controllers) into a guard over EVERY mapped route: as `viewer_read_only`
in AUDIT mode each state-changing route returns 403 and the DB is unchanged, and each read route is denied where
the role lacks R; a route with no resolver or no check fails the test; keep an explicit, commented allow-list for
the few intentionally open routes (`my-roles`, user-dashboard, workspace, profile, logout, org/switch, etc.).

**P2-B (items 11, 12, 13, 15, 16) — small fixes.**
- **11** `Admin/RbacController` (~L508, admin "delete user") hard-deletes the user's `project_members` rows with no
  removal event: write a `removed` row to `project_member_logs` (via `ProjectMemberLog::record`) for each ACTIVE
  membership before deleting; performed_by = the admin; test it.
- **12** `UserWorkspaceController::pendingApprovals` uses the wrong permission (estimate R): require
  `approval_authority` at the approving level (A) as `OrderApprovalController` does; test allowed/denied roles.
- **13** `UserWorkspaceController::pendingRfqs` filters status `'open'`, which no RFQ ever has (statuses are
  `sent` → `closed` → `converted`): decide and document which statuses the panel should show (recommended: RFQs not
  yet converted, i.e. `sent` and `closed`), keep the project-visibility rule, test it (the current test swaps in a
  free-text status only because of this bug — update it to the real statuses).
- **15** The estimate editor header (`resources/views/frontend/quotes/index.blade.php`, header ~L1375) is crowded:
  the customer name wraps beside the "Project: <name>" link. Small markup/CSS tweak; verify in the browser clone.
- **16** `RfqSellerController::respond` has no RFQ status check: allow a response only while the RFQ is `sent` and
  the recipient row is still pending; otherwise 422 with a clear message; a seller cannot change a selected
  response after conversion; tests incl. audit and enforce mode.

**P2-C (item 14, DECIDED) — zero-approver trap: REFUSE with a clear message.** In BOTH
`CheckoutController` (~L195-270) and `RfqController::convertToOrder`, when `ApprovalRoutingService::route()`
returns no approvers and not `auto_approve` (i.e. the org has nobody with approval authority; a sole approver who is
the requester still auto-approves), stop before creating the order (browser: redirect back with an `error` flash;
JSON: 422) with a message like: "Your organization has no one who can approve orders. Ask an organization owner to
assign an Executive Approver (or another role with approval authority), then try again." Do not create the order,
do not change `ApprovalRoutingService` semantics for other cases. Tests: no approver → refused, nothing written;
sole approver → auto-approve; several approvers → `pending_approval`; both entry points; audit + enforce.

**P2-D (item 17) — documentation and the Week-12 QA report (plan §7.3).**
1. Refresh `ARCHITECTURE.md` (it still describes project = quote and the old enforcement state) with the current
   model: projects, project-keyed membership, `quote_param`/`project_param`, `CurrentOrg`, server-side checks
   mirroring the map, RFQ project scope + approval routing, membership log, backfill, Phase 5a/5b, deploy order.
2. Write a new developer/operations handoff document for the client (replaces the deleted, inaccurate `HANDOFF.md`);
   correct facts only, verified against the code.
3. **Week-12 QA report across all 31 Release-1 roles:** generate it from the seed matrix and the tests — for every
   role × permission group the expected level (plan) vs seeded level, pass/fail; the plan's §2.4/§4.6 spot checks;
   solo-operator (auto-approve) and registration-bundle edge cases; a table of automated test coverage per area with
   the final suite counts. Produce it as a document (Markdown, and a PDF via headless Chrome:
   `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --no-pdf-header-footer
   --print-to-pdf=OUT.pdf file://IN.html`). State plainly what is NOT proven (sqlite limits, MariaDB-only items).
4. Update `TESTING.md` (new baselines) and `CLAUDE.md`/`HANDOVER.md` status at the end.

**EXCLUDED from P2 (need something Claude cannot supply):**
- **18** `SeedMatrixTest` failures (org types 19 vs 20; `api_system` P2 vs P3) — wait for the client's matrix decisions
  (24 vs 25 groups, etc.). Do not "fix" the tests to match either side before that decision.
- **19** delegation (Q11) design decision, and the MariaDB-only behaviours (enrol race, `FOR UPDATE`,
  `quotes.user_id` cascade) — need a human decision / a real MariaDB copy (gate B1).
- P3: Phase 5b tightening, G4 (Contract→Price List / GPO pricing: leave out, do not raise with the client).

**Done means (acceptance):** full suite `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test` shows only the
4 known failures (section 2) plus new tests all green; `php artisan view:cache && view:clear` compiles every view;
mutation proofs killed for each new load-bearing check; one Verifier CONFIRM; the UI items (13, 15, 16, 14 message)
checked once in the browser clone; docs written; `PROGRESS.md`/`HANDOVER.md` updated; the human approves the commit.
No new migrations are expected in P2 — if one seems necessary, stop and ask.

**Paste this to start the new chat:**
> Read HANDOVER.md (section 10 = the P2 work order), then CLAUDE.md, then the last Status line of
> .claude/tasks/projects-entity/PROGRESS.md. P0 and P1 are done — don't ask about them. Implement the complete P2
> exactly as section 10 says on a new branch `fix/p2-cleanup` from `fix/rfq-project-scope-and-approval`: P2-A
> (server-side checks on every remaining mapped route, with the all-routes audit-mode guard test), P2-B (items 11,
> 12, 13, 15, 16), P2-C (zero-approver → refuse with the message), P2-D (docs + Week-12 QA report for all 31 roles).
> Use Developer → QA (mutation proofs) → one Verifier with fresh short-brief agents, re-run the suite yourself,
> and ask me before any commit. I will do the pushing.

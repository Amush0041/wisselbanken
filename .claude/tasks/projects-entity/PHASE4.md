# Phase 4 spec (LEAN mode) — write paths, UI, nav, quote creation inside a project

Source: PLAN.md steps 8-12 (lines 247-253), B3, B14.2/B14.4/B14.6, D2, D15; live code checked 2026-09-25 on `feature/projects-entity`.
Status: DRAFT, needs human approval of section 5 before coding. Run no migrate/backfill locally (`.env` APP_ENV=production).

## 1. Scope
In: project CRUD, project-keyed membership add/remove, creator auto-enrol, quote creation/edit/delete scoped to a visible project, crosswalk write paths keyed on `{project}`, project page (workspace), org/user deletion refusals (B3), sidebar/nav/dashboard, views.
Out (Phase 5, STRICT): dropping `user_id` OR-clauses, NOT NULL on `quotes.project_id`, dropping legacy `quote_id` columns/rows, moving quotes between projects (Q5), org-scoped customers (Q10), delegation (Q11), cross-org membership (Q14), new permission groups or roles (none needed: 25 groups / 49 roles baseline unchanged).
Ship rule (B6): Phases 2-4 deploy as one release; Phase 3 alone must never deploy.

## 2. Routes, controllers, views (live state -> change)
Exists today (routes/web.php):
- `org-admin.projects.index|members.store|members.destroy` (:139-141) -> `OrgAdminController::projects/addProjectMember/removeProjectMember`. Add still writes a legacy `quote_id` row (:620) and checks quote ownership (:607): REWRITE to project-keyed via `ProjectMember::enrol()` (`app/Models/Rbac/ProjectMember.php:39`).
- `project.workspace` GET `projects/{quote}/workspace` (:143) -> `ProjectWorkspaceController::show(Quote)`, minimal D8 fixes only.
- `quotes.*` (:176-191) take raw `{id}`/`{quoteId}`, all `where user_id = me` for writes (`QuoteController.php` :219,224,297,303,399,444-448,493-503,525,547,595,625).
- `plan-crosswalk.*` (:264-267): store/update/destroy still quote-keyed; `store` throws (queries `quotes.org_id`).
- Dashboard "My Projects" panel (`user/dashboard.blade.php:78-164`) links `project.workspace` with a quote id; `Manage` -> `org-admin.projects.index` is now a valid name. Sidebar (`user/layouts/sidebar.blade.php`) has no Projects item; `org-admin/_nav.blade.php:50` gates on `user_management:F`.
Add:
- `App\Http\Controllers\Frontend\ProjectController`: `index` GET `projects` (`projects.index`), `list` GET `projects/list` (JSON picker; visibleTo only), `store` POST `projects`, `update` PUT `projects/{project}`, `destroy` DELETE `projects/{project}`.
- `ProjectMemberController`: `store` POST `projects/{project}/members`, `destroy` DELETE `projects/{project}/members/{projectMember}` (verify `projectMember->project_id == project->id`, else 404).
- Change `ProjectWorkspaceController::show(Project $project)` at GET `projects/{project}` (`projects.show`); keep `legacyRedirect(Quote)` 301 at `projects/{quote}/workspace` (404 unless visible). Route-binding `{project}` excludes trashed rows (B14.6).
- Quote creation: `POST projects/{project}/quotes` (`projects.quotes.store`) and `POST projects/{project}/quotes/create-from-list/{listId}`; retire `POST quotes` and `quotes/create-from-list/{listId}` (remove routes + map entries, no dual path).
- Crosswalk: `POST projects/{project}/crosswalk`, `PUT|DELETE plan-crosswalk/{planCrosswalk}` (row's `project_id` must be visible, else 404). Old `project_id` request param retired (B14.4).
- Views (ui-designer): new `user/projects/index.blade.php`; `project-workspace/show.blade.php` becomes project page (quotes, members, crosswalk, "Add estimate"); required project picker in the estimate form and list-to-quote SweetAlert (`frontend/quotes/index.blade.php`, `frontend/lists/listDetail.blade.php:1829-1845`); rewrite `org-admin/projects.blade.php` (member chips project-only); crosswalk view fields at `plan-crosswalk/index.blade.php:40,96,211` and `show.blade.php:244` under new names; sidebar item + dashboard links use project id.

## 3. Permission mapping (`config/route_permission_map.php`)
Ranking F>A>O>S>R; every entry uses `project_param => 'project'` (a `projects.id`) where the URI has `{project}`; `quote_param` stays on `quotes/{id}...`. Middleware `RbacAudit` calls `checkPermission(userId, orgId, group, level, projectId)`; controllers then re-scope by `Project::visibleTo(me, org)` (the real guard in audit mode).
| Route | Group | Level | Batch | Project scope |
|---|---|---|---|---|
| GET projects | project_management | R | read | no (list is visibleTo-filtered) |
| GET projects/list | project_management | R | read | no |
| GET projects/{project} | project_management | R | read | project_param |
| POST projects | project_management | S | write | no (creates; creator enrolled) |
| PUT projects/{project} | project_management | O | write | project_param |
| DELETE projects/{project} | project_management | F | approve | project_param |
| POST projects/{project}/members | project_management | F | admin | project_param |
| DELETE projects/{project}/members/{projectMember} | project_management | F | admin | project_param |
| POST projects/{project}/quotes | estimate_management | S | write | project_param |
| POST projects/{project}/quotes/create-from-list/{listId} | estimate_management | S | write | project_param |
| POST projects/{project}/crosswalk | estimate_management | F | write | project_param |
| PUT plan-crosswalk/{planCrosswalk} | estimate_management | F | write | none in map; controller checks `checkPermission(..., $row->project_id)` |
| DELETE plan-crosswalk/{planCrosswalk} | estimate_management | F | approve | same |
| GET projects/{quote}/workspace (redirect) | estimate_management | R | read | quote_param (existing) |
Unchanged, already project-scoped by `quote_param`: `quotes/{id}` update/editor/items/duplicate (O write), deletes (F approve), reads (R). Remove map keys for `POST quotes`, `POST quotes/create-from-list/{listId}`, `POST plan-crosswalk`, `GET org-admin/projects` stays (R) and org-admin member routes are removed once project-keyed ones exist (keep `org-admin/projects` as a redirect or repoint to `projects`; open decision 3).
A test must fail if any new `{project}` URI lacks `project_param` (Phase 3 rule D15 item 6).

## 4. Rules to enforce
1. Membership add/remove: caller needs `project_management:F` AND `visibleTo`; target must be an active member of `project.org_id` (its `org_id` must equal `projects.org_id`); use `ProjectMember::enrol()` only; no own catch, message branches on the A1 flags (added / re-activated / already member). Remove sets `is_active=false` on a row of that project only; removing the last active member is allowed (decision 4). No owner bypass (Q1): an owner not a member cannot open the project except via the org-admin recovery route.
2. Creator auto-enrol: `store` = one transaction: `Project::create` (org_id = validated current org, `created_by = me`, status in active/on_hold/awarded/lost/archived, name required) then `enrol($project, me, $org->id, me)`. `update` never accepts `org_id` or `created_by` (B14 note).
3. Project delete: 422 while any non-trashed quote exists (Q6); soft delete.
4. B3 deletion refusal: `OrgSettingsController::destroy` (:51), `RbacController::destroyOrganization` (:448), `RbacController::destroyUser` (:470) refuse when the org (or any org the user owns via `created_by`/only-owner) has projects, INCLUDING soft-deleted (`withTrashed()`); redirect with error, no partial delete.
5. Quote creation only inside a visible project: `project_id` comes solely from route binding, never from input or mass assignment; `Project::visibleTo` else 404; `duplicate` copies `project_id`; no NULL-project creation path remains. `saveEditor` customer rule per Q10. `getEstimatesList` optional `project_id` filter validated against visible projects.
6. Quote edit/delete: dual-read scoping (`user_id = me OR project visibleTo`) with the row's project resolved by `RbacAudit` (`quote_param`); a NULL-project quote falls to `project_unresolved` in enforce (D13 risk 3).
7. IDOR on nested routes: every `{project}`, `{projectMember}`, `{planCrosswalk}`, `{listId}` must belong to the visible project/org or return 404 (not 403, no existence leak). A quote id sent as `project_id` is ignored (B14.4 regression 13).
8. Crosswalk to project scope (in Phase 4 per step 10): writes set `project_id` (and leave `quote_id` NULL); duplicate check on `(project_id, plan_line_code)`; list rows already read via project (Phase 3).
9. Stale-object rule (B14.6): load the project in the same request as `enrol()`; never cache across requests.

## 5. Decisions
Already made: no owner bypass (Q1); Q5 no moving quotes; Q6 delete blocked while quotes exist; Q7 statuses active/on_hold/awarded/lost/archived; Q10 keep quote's customer or pick own; B3 refuse whole org/user deletion when projects exist (count soft-deleted); B6 phases 2-4 ship together; H1 own project per unnamed quote; Phase 3 H7/H8/H9 (members read attachments, counters owner-only, role gate on member reads); crosswalk keyed on project in Phase 4; no new groups or roles.
ANSWERED by the human (2026-09-25):
1. Project CRUD uses `project_management` (S create, O update, F delete, F members), reusing the group that also governs saved lists.
2. S is enough to create a project, for now.
3. Keep the `org-admin/projects` page (project-keyed admin overview, `_nav` gate unchanged); the human will test it later and decide. Do not redirect it.
4. Removing the last active member is ALLOWED (no last-member guard). The human did not address the creator specifically; the same rule applies (creator removal allowed, no special case).
5. `bid_due_at` is a datetime with time zone; `address` is free text.
6. Q11 delegation stays open (not answered; assumed per recommendation). 7. Tests run in BOTH enforce and audit mode (not answered; assumed per CLAUDE.md).

Known open items touched: none of the four (rep-agency, api_system, org type count, audit 302 redirect); the audit-mode note above is the closest.

## 6. Tests (Pest, `tests/Feature/Rbac/`, run in enforce and audit)
Per route: allowed (member with the level), denied wrong role (level too low, e.g. R on POST), denied wrong org (member of org B), denied non-member of the project (visible only via project_members), unauthenticated. IDOR: foreign project, foreign projectMember, foreign planCrosswalk, trashed project all 404.
- `projects` index/list/show: only visibleTo rows; other-org and non-member projects absent.
- `store`: creates project + active member row atomically (fail `enrol` -> no project); invalid status 422; `org_id`/`created_by` inputs ignored.
- `update`: O allowed, S denied; `org_id` input ignored. `destroy`: F, 422 with a live quote, soft-deletes when empty.
- members store/destroy: F allowed; A/O/S denied; target outside org denied; re-activation and duplicate messages; row of another project 404.
- quotes: create inside visible project sets `project_id`; non-visible/trashed project 404; no route creates a NULL-project quote; request `project_id` ignored; duplicate copies project; member (not owner) can edit with O and cannot with R.
- crosswalk: store/update/destroy by project; duplicate code 422; `project_id=<quote id>` ignored.
- B3: org delete, admin org delete and user delete refused with live and soft-deleted projects, allowed with none.
- Map completeness test: each new URI has an entry, `project_param` where `{project}`.
Mutation proofs (scratch copy, only these): membership add/remove auth (drop F check, drop org-match, drop project-belongs check); org and user deletion refusals (drop check, drop `withTrashed`); creator auto-enrol (remove `enrol`, break transaction); quote creation only in visible project (drop `visibleTo`, accept input `project_id`); IDOR on project-nested routes (drop the binding/ownership check on members, crosswalk).
Baseline: Rbac suite 383 passed / 3 known failures; anything else is a regression (TESTING.md).

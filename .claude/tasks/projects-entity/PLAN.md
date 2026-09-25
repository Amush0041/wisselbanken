# Plan: Projects as a first-class entity

Status: APPROVED at Checkpoint 1 on 2026-09-24 by the human supervisor. Open questions Q1-Q17 resolved as the plan's proposals (Q1: no membership bypass, keep org-admin recovery route; Q2/Q3: per (org, owner, name) grouping + singleton for unnamed; Q5: moving quotes out of scope; Q6: soft-delete blocked while quotes exist; Q7: statuses active/on_hold/awarded/lost/archived; Q15 and the rest: as proposed in the plan). Phase 1 authorized. Phases 2-5 each need their own go-ahead.

**Amendment 1 (2026-09-24, from Phase 1 compliance pass)**: see the section of that name at the end. It needs the human's acknowledgement at Checkpoint 2. All six items (A1-A6) refine the approved scope; none is a scope change. There is no new route, table, column, permission group, role or behaviour visible to users. Two things are new: small Phase 1 code changes (steps 2a and 2b), and a gate that is **blocked on human input** (A3: production MariaDB `VERSION()` plus a scratch MariaDB instance). Until A3 passes, Phase 2 can't start.

**Amendment 2 (2026-09-24, from the Verifier's Phase 1 findings)**: see the section of that name at the end. It also needs the human's acknowledgement at Checkpoint 2.
- It is refinement and correction only: no new route, table, column, permission group or role, and no change to feature scope.
- It adds Phase 1 code steps 2c, 2d and 2e, plus test tasks in step 3.
- **Three HUMAN DECISIONS**:
  - B3: how `destroyUser` behaves.
  - B4: how the backfill treats legacy member rows from another org. This changes an approved Phase 2 rule.
  - B6: whether Phase 1 ships in an earlier, separate window. That is a release-plan change, not a scope change.
- The MariaDB gate is re-specified as B1. It is still **BLOCKED ON INPUT**, and it now also gates any production `migrate`.

**Checkpoint 2 for Phase 1 — APPROVED (2026-09-24)** by the human supervisor; Amendments 1 and 2 acknowledged; Phase 1 committed to feature/projects-entity as 8363ed3 (not pushed). **Human authorized starting Phase 2** and thereby overrides the Amendment 1 status-line rule that Phase 2 cannot start before A3/B1 passes, for *development and sqlite testing of the backfill command only*. B1 (MariaDB round trip on a restored production copy), B5 pre-flight, the B12 file diff and a test-restored backup remain hard gates for running ANYTHING on production, including `projects:backfill`. Production pre-flight queries Q0-Q9 have not been run yet.

**Human decisions on Phase 3 (2026-09-24):** H9 = Option B (project-member reads also require estimate_management:R in the controllers, so audit mode denies role-less members too); H7 = hide `staff_notes` from non-owners and stop the `pdf_path` write by non-owners (attachment URLs stay visible; customer visible); H8 = dashboard counters stay owner-only (default, unchanged). P3-01 dashboard legacy union is dropped (defect fix), P3-05 tests added. The Architect finalizes exact steps (D18) before the Developer proceeds.

**H6 (2026-09-24) — human decision:** a project address containing invalid UTF-8 is REPORTED as a warning (not silently ignored); the run still succeeds. The Architect converts this into final spec text (which report/column, exit-code impact, test + mutation entry) and the Developer applies it (Phase 2 step, planner change) before QA starts.

**Human decisions on the Phase 2 spec (Amendment 3, 2026-09-24):** H1 = own project per unnamed quote (empty / default 'Estimate' / equal to its own quote name); H3 = ADOPT the live-run guard (a real run refuses with exit 2 unless the app is in maintenance mode or --allow-live is passed; --dry-run unaffected); H4 = unlinked crosswalk rows: exit 0 + summary line + runbook sign-off; H5 = reports kept with the backup (0700/0600, never committed) until the Phase 5 gate, then deleted. H2 (crosswalk rows whose org differs from the project's org are not linked, listed in the report) stays at its provisional default — no objection raised. The Phase 2 spec passed three independent Verifier reviews; development authorized. Development and sqlite testing only; running the command on production stays gated by B1/B5/B12/backup, and it must never be run against the local MySQL DB.

**Human decisions on Amendment 2 (2026-09-24):** B3 = Option A (refuse the whole user deletion when the user is the only member of an org that owns projects, archived included). B4 = Option A (re-home a legacy membership row to project.org_id if the user is an active member of that org, else list it in membership_org_mismatch.csv for manual review). B6 = separate earlier release window for Phase 1 schema, then Phases 2-4 together. B1 (MariaDB gate) still BLOCKED on human input: VERSION(), a restorable copy/dump of production, and a scratch MariaDB of the same version.

**Amendment 2 addendum B11-B13 (2026-09-24, from the Verifier re-review R1-R3):** these are runbook and test-task wording only, with no code change. They are included in the Amendment 2 acknowledgement at Checkpoint 2. **Phase 1 exit criteria: B13.**

**Amendment 4 (2026-09-24): the Phase 3 specification (D1-D14)**, at the end. The human authorized Phase 3 after Phase 2 was approved and committed (d41eb99). It is the single implementable spec for steps 5-7 and supersedes their older wording. The Phase 3 human decisions are **decided**: H7 (hide `staff_notes` from non-owners, no `pdf_path` write by non-owners), H8 (dashboard counters stay owner-only) and H9 (Option B: member reads also require `estimate_management:R`); D18 is their implementable spec. It also corrects two plan facts found against the live code: the 11 `project_param` entries are **9**, and `ProjectWorkspaceController::show` passes a quote id as the project id (D8).

**Amendment 3 (2026-09-24): the Phase 2 backfill specification (C1-C15)**, at the end. It replaces the step 4 bullets as the single implementable spec. It needs the human's acknowledgement at Checkpoint 2 for Phase 2. **Four HUMAN DECISIONS are still open: H1 and H2 (C4), H3 (live-run guard) and H4 (unlinked-crosswalk exit rule) (C14).** H6 (invalid-UTF-8 address) is decided: report it as a warning (C15.8). Each open one has a provisional default that development and tests may use. All must be confirmed before any production run, and H1 needs production data (Q16). *(C14)* H5 (report retention) is a client/human handling decision, not a code default. Nothing here is a scope change.

Branch: `feature/projects-entity` (cut from `dev`). Author: Architect, 2026-09-24.
Every `file:line` below was checked against the live code on this branch. The client plan PDF (§3.5, §4.6) is **not checked in**, so those section references come from the client's request and could not be checked here.

## Goal

Users get a **Projects** item in the main sidebar. A project (name, status, address, bid due date) belongs to one organization and holds several quotes/estimates (base, alternates, adders). The flow is: create a project, add estimates to it, add members. Membership is managed once per project, and it covers every quote in that project. The person who creates a project is enrolled automatically, so they can always open the quotes they create. This fixes the client's "cannot open my own quote" would-block. Every new estimate is created inside a project. The plan crosswalk is managed per project and shown on the project page. Existing quotes (such as "Test 1") are moved into generated parent projects, and their quote-level memberships move up to the project. PDFs, quote numbers and the estimate editor otherwise stay the same.

**For the client:** (a) Confirmed as specified, with the open decisions listed under Risks. (b) The migration plan is Phase 2 below. (c) Staging date: none proposed. It depends on Checkpoint 1 approval and on the production counts from the §"Production pre-flight queries" section. This must be deployed and backfilled before RBAC enforcement is switched on in any environment.

## Verified current state (load-bearing facts)

| Fact | Where |
|---|---|
| Membership check queries `project_members.quote_id` | `app/Services/Rbac/PermissionService.php:124-132` (called at `:79` with the *effective* user after delegation, `:59`) |
| Middleware passes the raw route param as projectId: `(int) $request->route($projectParam)` | `app/Http/Middleware/RbacAudit.php:61`, rule parsing `:137-164` |
| **The operative RBAC mode is the DB row `rbac_settings.rbac_mode`, not `.env`.** `.env RBAC_MODE` only seeds that row when the migration runs. It is toggled from the admin UI. Local `.env` says `enforce`; production's DB row must be checked (pre-flight Q0). | `RbacAudit.php:236`; `database/migrations/2026_07_07_000001_create_rbac_settings_table.php:20-21`; `app/Http/Controllers/Admin/RbacController.php:247` |
| Denials on non-JSON requests redirect back (302); only AJAX/JSON requests get a 403. This is the likely real cause of known open item #4. | `RbacAudit.php:111-125` |
| **9** quote routes carry `project_param` (its values are quote ids): `:121,122,123,128,131,132,133,136,137`. *(Amendment 4, D5: the earlier count of 11 was wrong. With the new workspace entry, 10 entries change.)* | `config/route_permission_map.php:121-137` |
| `GET projects/{quote}/workspace` is mapped **without** project scoping | `config/route_permission_map.php:92`, `routes/web.php:143` |
| **Every** `QuoteController` read/write is scoped `where('user_id', Auth::id())`. That makes membership-based sharing to teammates impossible today, whatever the RBAC settings. | `app/Http/Controllers/Frontend/QuoteController.php:53,121,144,400,445,494,526,549,596,875,896` |
| Customer and saved-list lookups are per-user too. A teammate editing someone else's quote fails the customer check. | `QuoteController.php:219,297,303,625` |
| Quote creation happens in exactly 3 places: `createFromList` `:223`, `store` `:316`, `duplicate` `:552`. No other creator exists in `app/`, `database/seeders/` or `routes/`. There are no Livewire components. | grep |
| Front-end callers of creation: the estimate form posts to `route('quotes.store')`; list-to-quote is a SweetAlert AJAX call | `resources/views/frontend/quotes/index.blade.php:137,2182-2199`; `resources/views/frontend/lists/listDetail.blade.php:1829` |
| PDF uses `quote.name` as the title and `quote.project_name`/`project_address` as the subtitle and address | `app/Support/QuotePdfPresenter.php:59-61` |
| `PlanCrosswalkController` queries `quotes.org_id` (the column doesn't exist) **and** a non-existent `title` column. Both `index` and `store` would throw a QueryException. | `app/Http/Controllers/Frontend/PlanCrosswalkController.php:31-33,62` |
| Crosswalk update/destroy only check org, not project | `PlanCrosswalkController.php:90,108` |
| `ProjectWorkspaceController` redirects to the non-existent route name `org-admin.projects` (the real name is `org-admin.projects.index`). It works out the owner org as "the creator's first active org", which is unsafe for users in more than one org. | `ProjectWorkspaceController.php:47,53-56`; `routes/web.php:139` |
| The dashboard uses the same non-existent route name | `resources/views/user/dashboard.blade.php:84` |
| Org ownership of a quote is inferred as "owned by any active org member" | `OrgAdminController.php:561-564,596-598` |
| Re-adding a previously removed member hits `unique(quote_id,user_id)`: the code checks only active rows, then runs `create` | `OrgAdminController.php:603-618`; migration `2026_06_25_000007:28` |
| The org-admin nav gates Projects on `user_management:F`, but the route map requires `project_management:R` | `resources/views/user/org-admin/_nav.blade.php:50`; map `:89` |
| `quotes.user_id` cascades on user delete, so deleting a user hard-deletes their quotes | `2026_01_16_122033_create_quotes_table.php:16` |
| Org hard-delete flows delete RBAC rows, and child tables cascade on the org FK | `OrgSettingsController.php:59-66`; `RbacController.php:452-460` |
| `UserWorkspaceController` queries `status = 'pending_approval'`, but that value isn't in the quotes status enum `draft/completed/sent`, so the query is dead. Left alone (out of scope). | `UserWorkspaceController.php:75`; quotes migration `:21` |
| The RBAC test base builds a stub `quotes` table and runs an explicit migration list, which does **not** include `plan_crosswalk` | `tests/Feature/Rbac/RbacTestCase.php:57-68,72-87` |
| CLAUDE.md says "Pest test", but the repo only has `phpunit/phpunit ^11` and tests are PHPUnit classes | `composer.json:24`; `tests/Feature/Rbac/*.php` |
| RFQs have no quote link. Orders, pallets and saved lists carry a free-text `project_name`/`project_title`. **Not** linked to projects in this task. | migrations grep |

## Data model changes

**No new `permission_group` and no new role.** Everything maps onto the existing `project_management` and `estimate_management` groups. The 25-group / 49-role baseline is unchanged.

### Phase 1 — additive, reversible (all new columns nullable)

1. **`2026_09_24_000001_create_projects_table.php`** creates `projects`:
   | column | type | null | notes |
   |---|---|---|---|
   | `id` | bigint PK | no | |
   | `org_id` | `foreignId` → `organizations.id`, **restrictOnDelete** | no | the owning org |
   | `name` | string(255) | no | |
   | `status` | string(32), default `'active'` | no | vocabulary is open question Q7 |
   | `address` | text | yes | |
   | `bid_due_at` | dateTime | yes | stored in UTC |
   | `created_by` | `foreignId` → `users.id`, **nullOnDelete** | yes | nullable so user deletion doesn't fail |
   | `created_at`, `updated_at` | timestamps | yes | `updated_at` is added beyond the client's column list (Eloquent default) |
   | `deleted_at` | softDeletes | yes | matches `quotes`' soft delete |
   Indexes: `(org_id, status)` and `(created_by)`.
2. **`2026_09_24_000002_add_project_id_to_quotes_table.php`** adds `quotes.project_id` as a `foreignId` → `projects.id`, **nullable**, **restrictOnDelete**, indexed, placed after `user_id`. No `quotes.org_id` is added: a quote's org is `project.org_id`.
3. **`2026_09_24_000003_add_project_id_to_project_members_table.php`** adds `project_id` as a `foreignId` → `projects.id`, nullable, cascadeOnDelete. It adds `unique(project_id, user_id)`, and MariaDB allows multiple NULLs in that index. It makes `quote_id` **nullable** via `->change()`, keeping its FK. Laravel 11 `change()` must restate every modifier. Legacy quote-keyed rows are **not modified**. New-format rows have `project_id` set and `quote_id` NULL.
4. **`2026_09_24_000004_add_project_id_to_plan_crosswalk_table.php`** adds `project_id` as a `foreignId` → `projects.id`, nullable, cascadeOnDelete. It adds index `(org_id, project_id)` and makes `quote_id` nullable via `->change()`.
5. **`2026_09_24_000005_add_quote_id_to_rbac_audit_logs_table.php`** adds `quote_id` as an unsignedBigInteger, nullable, no FK, the same style as `project_id`. From Phase 3 onward `project_id` holds a **projects.id**. Historic rows hold quote ids (Q13).

### Model relations

- **New `App\Models\Project`** (SoftDeletes). Relations: `organization()`, `creator()`, `quotes()` hasMany, `members()` hasMany ProjectMember, `crosswalk()` hasMany PlanCrosswalk. Scope `scopeVisibleTo($q, int $userId, int $orgId)`: the user has an active `project_members` row with `project_id`, `user_id` and `org_id = $orgId`. This is the **one** data-scoping rule every controller uses.
- `Quote`: add `project_id` to fillable and a `project()` belongsTo.
- `ProjectMember`: add `project_id` to fillable. `project()` becomes a belongsTo on `Project` (nothing in `app/` or `resources/views/` calls `->project` today). Keep a legacy `quote()` until Phase 5. Add a static `enrol(Project $p, int $userId, int $orgId, ?int $grantedBy)` that runs `updateOrCreate` on `(project_id, user_id)` and reactivates inactive rows. This fixes the unique-violation bug. *(Amendment 1)* **Superseded:** exact semantics are in A1. It is not an unconditional `updateOrCreate`.
- `PlanCrosswalk`: add `project_id` to fillable and a `project()` relation. Keep `quote()` until Phase 5.
- The semantics of `project_members.org_id` don't change: it is the **member's acting org** (what `isActiveProjectMember` matches against the session org). New writes require `project.org_id == current org` and the target user must be in that org, as today. Cross-org membership stays out of scope (Q14).

### Phase 5 — later release, NON-REVERSIBLE (separate PR, separate approval)

- `quotes.project_id` becomes NOT NULL.
- `project_members`: delete rows `WHERE project_id IS NULL`, drop `unique(quote_id,user_id)`, the `quote_id` FK and column. `project_id` becomes NOT NULL.
- `plan_crosswalk`: drop the `quote_id` FK, index and column. `project_id` becomes NOT NULL. Add `unique(project_id, plan_line_code)`.
- Remove the Phase 3 controller dual-read (`user_id` OR-clause) and the legacy `quote()` relations.

## Permission mapping

**Resolving a project from a quote-keyed route param.** The map gets a second, distinct key: `'quote_param' => '<param>'`, which means "this param holds a quotes.id". The existing `'project_param'` now **only** means "this param holds a projects.id". For `quote_param`, `RbacAudit` resolves `Quote::withTrashed()->whereKey($id)->value('project_id')` and calls `checkPermission(..., projectId: <projects.id>)`. If the quote doesn't exist or has a NULL `project_id`, the check **fails closed** with reason `project_unresolved`. Both `quote_id` and `project_id` go into the audit row. For `project_param`, the value may be a bound `Project` model: `{project}` is bound by SubstituteBindings, which runs before the appended `RbacAudit`. Use `getKey()` for a model and `(int)` for a scalar. Distinct keys, rather than a type flag, mean a forgotten flag can never make a quote id be read as a project id. The 9 renamed entries plus the new `quote_param` on the workspace route (10 entries, listed by line in D5) **must ship in the same commit** as the `RbacAudit` change.

`checkPermission()` keeps its signature. Its `projectId` is now a **projects.id**.

| Route | Method | Permission group | Level | Batch | Project-scoped? |
|---|---|---|---|---|---|
| **New** | | | | | |
| `projects` | GET | project_management | R | read | No. The list shows only `Project::visibleTo` in the current org |
| `projects/list` (JSON picker) | GET | project_management | R | read | No. Filtered by membership |
| `projects` | POST | project_management | S | write | No (the project doesn't exist yet). Creator auto-enrolled in the same transaction |
| `projects/{project}` | GET | project_management | R | read | Yes, `project_param => project`. Quote list and crosswalk sections render only if `checkPermission(estimate_management,R,projectId)` passes; members section always |
| `projects/{project}` | PUT | project_management | O | write | Yes, `project_param` |
| `projects/{project}` | DELETE | project_management | F | approve | Yes, `project_param`. Soft delete; blocked while live quotes exist (Q6) |
| `projects/{project}/members` | POST | project_management | F | admin | Yes, `project_param` |
| `projects/{project}/members/{projectMember}` | DELETE | project_management | F | admin | Yes, `project_param`; the controller asserts the member belongs to the project (404) |
| `projects/{project}/quotes` | POST | estimate_management | S | write | Yes, `project_param`. **Replaces** `POST quotes` |
| `projects/{project}/quotes/from-list/{listId}` | POST | estimate_management | S | write | Yes, `project_param`. **Replaces** `POST quotes/create-from-list/{listId}` |
| `projects/{project}/crosswalk` | POST | estimate_management | F | write | Yes, `project_param`. **Replaces** `POST plan-crosswalk` |
| `projects/{project}/crosswalk/{planCrosswalk}` | PUT | estimate_management | F | write | Yes, `project_param`; the controller asserts `planCrosswalk.project_id == project.id` (404). **Replaces** `PUT plan-crosswalk/{planCrosswalk}` |
| `projects/{project}/crosswalk/{planCrosswalk}` | DELETE | estimate_management | F | approve | Yes, as above. **Replaces** `DELETE plan-crosswalk/{planCrosswalk}` |
| **Changed** | | | | | |
| `projects/{quote}/workspace` (becomes a 301 redirect to `projects/{project}`) | GET | estimate_management | R | read | **Yes, now** via `quote_param => quote` (was unscoped) |
| `quotes/{id}/details`, `quotes/{id}/pdf-preview`, `quotes/{id}/pdf` | GET | estimate_management | R | read | Yes. `project_param => id` **renamed to** `quote_param => id` |
| `quotes/{id}/duplicate` | POST | estimate_management | O | write | Yes, `quote_param => id`. The copy stays in the same project |
| `quotes/{id}`, `quotes/{id}/editor` | PUT | estimate_management | O | write | Yes, `quote_param => id`. `project_id` is not updatable here |
| `quotes/{quoteId}/items/{itemId}` | PUT | estimate_management | O | write | Yes, `quote_param => quoteId` |
| `quotes/{id}` | DELETE | estimate_management | F | approve | Yes, `quote_param => id` |
| `quotes/{quoteId}/items/{itemId}` | DELETE | estimate_management | F | approve | Yes, `quote_param => quoteId` |
| `quotes/list` | GET | estimate_management | R | read | No (unchanged). The controller filters to visible projects and gains an optional `project_id` filter |
| `org-admin/projects` | GET | project_management | R | read | No (unchanged). Lists projects `WHERE org_id = current org` |
| `org-admin/projects/members` | POST | project_management | F | admin | No (unchanged). The body takes `project_id` instead of `quote_id`. This is the org-level recovery path (Q1) |
| `org-admin/projects/members/{projectMember}` | DELETE | project_management | F | admin | No (unchanged) |
| `plan-crosswalk` | GET | estimate_management | R | read | No (unchanged). Filtered to visible projects; `?project_id=` now a projects.id |
| **Removed** (map entries deleted in the same commit) | | | | | |
| `quotes` (POST), `quotes/create-from-list/{listId}` (POST), `plan-crosswalk` (POST), `plan-crosswalk/{planCrosswalk}` (PUT, DELETE) | | | | | superseded by the project-nested routes above |

Unchanged and not scoped: `GET quotes`, `quotes/customers`, `quotes/product-variations`, `quotes/services`.

All `{project}` routes get `->whereNumber('project')` so `projects/list` can't be swallowed.

**Data scoping in controllers stays live whatever the RBAC mode.** Production is in audit mode, so `RbacAudit` only logs. The effective access control in production is therefore the controller query scoping, which moves from `user_id = me` to `Project::visibleTo(me, currentOrg)`. Every controller resolves the current org the validated way (the `OrgAdminController::currentOrg()` pattern, `:633-647`; *Amendment 4, D6: one shared helper `CurrentOrg`*), **not** the raw session value (`PlanCrosswalkController.php:115-118` and `ProjectWorkspaceController.php:107-110` currently trust the raw session).

## File list

**Create**
- `database/migrations/2026_09_24_000001_create_projects_table.php`
- `database/migrations/2026_09_24_000002_add_project_id_to_quotes_table.php`
- `database/migrations/2026_09_24_000003_add_project_id_to_project_members_table.php`
- `database/migrations/2026_09_24_000004_add_project_id_to_plan_crosswalk_table.php`
- `database/migrations/2026_09_24_000005_add_quote_id_to_rbac_audit_logs_table.php`
- `app/Models/Project.php`
- `app/Console/Commands/BackfillProjects.php` (the directory doesn't exist yet; Laravel 11 auto-discovers it)
- *(Amendment 3)* `app/Support/ProjectBackfillPlanner.php` (Phase 2; see C11)
- `app/Http/Controllers/Frontend/ProjectController.php` (index, list, store, update, destroy)
- `app/Http/Controllers/Frontend/ProjectMemberController.php` (store, destroy)
- `resources/views/user/projects/index.blade.php` (list and create modal)
- `tests/Feature/Rbac/ProjectTestCase.php` (extends `RbacTestCase`; adds stubs for `customers`, `quote_items`, `saved_lists`, `saved_list_items`, `products`, `product_variation_color`, only as needed)
- `tests/Feature/Rbac/ProjectMembershipTest.php`
- `tests/Feature/Rbac/ProjectRoutesTest.php`
- `tests/Feature/Rbac/ProjectBackfillTest.php`
- `tests/Feature/Rbac/RoutePermissionMapTest.php`
- *(Amendment 2)* `tests/Feature/Rbac/ProjectSchemaTest.php` (QA created it in step 3; B4 and B8 extend and correct it)
- *(Phase 5, later release)* `database/migrations/<date>_tighten_projects_constraints.php`

**Modify**
- `app/Services/Rbac/PermissionService.php`: `isActiveProjectMember` reads `project_id`; docblock `:42`
- `app/Http/Middleware/RbacAudit.php`: `quote_param` resolution, model/scalar `project_param`, `project_unresolved` reason, audit `quote_id`
- `app/Models/Rbac/AuditLog.php`: add `quote_id` to `$fillable` (`:13`)
- `config/route_permission_map.php`: the table above; header comment `:13-15` documents both keys
- `routes/web.php`: new routes, removed routes, legacy workspace redirect `:143`
- `app/Models/Quote.php`, `app/Models/Rbac/ProjectMember.php`, `app/Models/PlanCrosswalk.php`
- `app/Http/Controllers/Frontend/QuoteController.php`: visibility scoping, the 3 creation paths, `project_id` filter, the customer rule for teammates (Q10), `project` in the details JSON
- `app/Http/Controllers/Frontend/ProjectWorkspaceController.php`: `show(Project)`, `legacyRedirect`, `project.org_id` for the §4.5 check, route-name fix, no hard redirect on the display-only check
- `app/Http/Controllers/Frontend/PlanCrosswalkController.php`: project-keyed; fixes `:31-33,62`
- `app/Http/Controllers/Frontend/OrgAdminController.php`: `projects()`, `addProjectMember()` (`project_id`, `ProjectMember::enrol`), `removeProjectMember()`
- `app/Http/Controllers/Frontend/UserWorkspaceController.php`: "My Projects" from `Project::visibleTo`
- `app/Http/Controllers/Frontend/OrgSettingsController.php` (`destroy`) and `app/Http/Controllers/Admin/RbacController.php` (`destroyOrganization`): refuse while the org has projects (Q8)
- `resources/views/user/layouts/sidebar.blade.php`: top-level "Projects" item, `$_sc('project_management','R')`, placed before Quotes (`:80`)
- `resources/views/user/org-admin/_nav.blade.php:50`: gate on `project_management:R` to match the map
- `resources/views/user/org-admin/projects.blade.php`: project-keyed rows and member forms (`project_id`)
- `resources/views/user/project-workspace/show.blade.php`: becomes the project page (quotes list with "Add estimate", members, crosswalk); fix the `quote_id` hidden fields `:244`
- `resources/views/user/dashboard.blade.php:107,164`: links to `projects/{project}` *(the `:84` route-name fix moved to Phase 3, D16; `:107,164` use an existing route name)*
- `resources/views/user/plan-crosswalk/index.blade.php`: project select/label (`:40,96,211`), nested form actions (`:130,146,202`)
- `resources/views/frontend/quotes/index.blade.php`: required project picker in the create form (prefilled from `?project_id=`), post to `projects/{id}/quotes` (`:137,2182`), project column and filter in the list
- `resources/views/frontend/lists/listDetail.blade.php:1815-1845`: project picker before create-from-list
- `tests/Feature/Rbac/RbacTestCase.php`: add the 5 new migrations and `2026_09_01_000001_create_plan_crosswalk_table.php` to the list `:72-87`
- `tests/Feature/Rbac/PermissionServiceTest.php:85-111,141-150`: rewrite project scoping on `projects` (Phase 3, step 5; not Phase 1)
- `ARCHITECTURE.md`: Core model `:21-26` (project is its own entity; projectId = projects.id; quote routes use `quote_param`), `:27-30` (the operative mode is `rbac_settings.rbac_mode`), change log. Applied at Checkpoint 2 per protocol.
- `CLAUDE.md` `:14-19`: same correction, plus the Pest vs PHPUnit wording. **Only with the human's explicit approval** at Checkpoint 2; no agent edits it on its own.
- `TESTING.md`: new tests; note under #1 that non-JSON denials redirect by design at `RbacAudit.php:120-125` (observation only, not a fix)

**Not touched:** `HANDOFF.md`, the old migrations `2026_06_25_000007` and `2026_09_01_000001` (their comments are historical), the seed data, `QuotePdfPresenter` (Q12).

## Task breakdown

Each step is one commit that can be tested on its own. *(B14)* Step 3 is listed after 2f because it is the test step for 1-2f; run order is 1, 2, 2a-2f, 3. Per B6, Phase 1 (schema and models) ships in its own earlier window, then Phases 2-4 ship together in the runbook order below. Phase 5 is a separate, later release.

### Phase 1 — Additive migrations
1. Create migrations 000001–000005 as specified. Each `down()` drops what `up()` added. The `down()` for 000003 and 000004 first **aborts with a clear message** if any row has `quote_id IS NULL` (rows in the new format exist, so rolling back would silently orphan them).
2. Add `Project` and the model relations, `ProjectMember::enrol`, and `Project::scopeVisibleTo`.
2a. *(Amendment 1)* Developer: rewrite `ProjectMember::enrol()` (`app/Models/Rbac/ProjectMember.php:38-49`, currently an unconditional `updateOrCreate` that overwrites `org_id`/`granted_by`/`granted_at` on active rows) to the A1 table. Look the row up by `(project_id, user_id)`. If it is active, return it unchanged. If it is inactive, update exactly the A1 fields. If there is none, `createOrFirst`, and if that returns an existing row (the race was lost), apply the same active/inactive rule to it. The signature is unchanged. No other file.
2b. *(Amendment 1)* Developer: add the A6 guard as the first line of `ProjectMember::enrol()`. Only `app/Models/Rbac/ProjectMember.php` changes.
2c. *(Amendment 2)* Developer: B2. The duplicate-key fallback in `enrol()` becomes `create()` plus a `sharedLock()` re-select. Only `app/Models/Rbac/ProjectMember.php` changes.
2d. *(Amendment 2)* Developer: B4. `Project::scopeVisibleTo` requires `projects.org_id = $orgId`, and `enrol()` rejects an `$orgId` that isn't the project's org. Only `app/Models/Project.php` and `app/Models/Rbac/ProjectMember.php` change.
2e. *(Amendment 2)* Developer: B7. Add an audit-data guard to `down()` in `2026_09_24_000005`. Only that migration changes.
2f. *(Amendment 2, B14.2)* Developer: `enrol()` refuses a trashed project (`InvalidArgumentException`, after the A6 guard, before the B4 guard). Only `app/Models/Rbac/ProjectMember.php` changes.
3. Update the `RbacTestCase` migration list (done in 8363ed3). *(B14)* No `PermissionServiceTest` stub change was needed; its rewrite belongs to Phase 3 step 5. Result at the time of the Phase 1 commit: the baseline of **55 passed / 3 known failures** plus the new tests (84 passed / 3 failed with `ProjectSchemaTest`). "55" is the pre-Phase-1 baseline, not a target. The failing 3 are always the TESTING.md three, by name.
   *Test:* migrations run up and down on sqlite; `enrol()` reactivates an inactive row with no unique violation.
   *(Amendment 1)* Also, on sqlite: the three A1 cases (insert, active no-op with `granted_by`/`granted_at`/`org_id`/`updated_at` unchanged, reactivation), and the forced race from A2(c). Also the A6 case: an unsaved `Project` throws `InvalidArgumentException`, the `project_members` row count is unchanged, and a pre-existing inactive legacy row (`quote_id` set, `project_id` NULL) for the same user keeps `is_active`, `org_id`, `granted_by`, `granted_at` and `updated_at`.
   *(Amendment 1)* **MariaDB round-trip, A3. BLOCKED ON INPUT** until the human supplies the production `VERSION()`. It is required, in addition to the sqlite run.
   *(Amendment 2)* The A3 procedure is **replaced by B1** and still BLOCKED ON INPUT. The test tasks for B2, B4, B7 and B8 belong to this step.
   *(Amendment 2)* **The complete step 3 test checklist is B10.** QA works from B10 only; it supersedes the scattered test notes in A1, A2, A6, B2, B4, B7 and B8.
   *(Amendment 2, B11-B13)* **Phase 1 is finished only when every B13 exit criterion holds.**
   *Rollback:* `php artisan migrate:rollback --step=5`. This is safe before Phase 2 runs. *(Amendment 1)* After a real backfill, follow A4 instead.

### Phase 2 — Idempotent backfill `php artisan projects:backfill {--dry-run} {--overrides=} `
4. *(Amendment 3)* **Superseded by Amendment 3 (C1-C14), which is the spec the Developer implements.** The bullets below are kept for history. Where they differ from Amendment 3, Amendment 3 wins.
   Implement the command:
   - **Scope:** `Quote::withTrashed()->whereNull('project_id')`, ordered by id.
   - **Org resolution per quote.** (i) The overrides CSV (`quote_id,org_id,project_key`) wins; `org_id` must be an org where the owner has a `user_org_roles` row. (ii) Otherwise, if the owner has exactly 1 distinct **active** org, use it. (iii) Otherwise, if the owner has more than 1 active org and all of the quote's `project_members` rows share one `org_id` that is among them, use that. (iv) Otherwise the quote goes to **manual review** and is skipped.
   - **Grouping key:** `(org_id, owner user_id, normalized project_name)`. Normalize with lowercase, trim and collapsed whitespace. `project_key` from the overrides CSV replaces the name part and can merge or split groups. **Fallback:** if `project_name` is empty or normalizes to the store default `estimate` (`QuoteController.php:311`), the quote gets its own **singleton** project named `quote.name`, or `Untitled project (<quote_number>)` if that is empty too. Unnamed quotes are never merged.
   - **Reuse, for idempotency:** if a non-trashed or trashed project already exists with the same `(org_id, created_by, normalized name)`, attach to it and don't create a new one.
   - **Project attributes:** `name` is the trimmed `project_name` of the most recently updated quote in the group. `address` is the most recent non-empty `project_address`; conflicts are reported. `status='active'`, `bid_due_at=NULL`, `created_by` is the owner, `created_at` is the earliest quote `created_at`. If **every** quote in the group is trashed, `deleted_at` is the latest quote `deleted_at`.
   - **Membership move-up.** For each project, upsert on `(project_id, user_id)` over the union of the quote owner(s), with `org_id` = project org, and every legacy `project_members` row of its quotes, keeping that row's `org_id`. A user is active if any source row is active. The owner is always active **unless** the owner has only inactive legacy rows; explicit removal wins, and it's reported. `granted_by`/`granted_at` come from the earliest active source row. For the owner with no row: `granted_by=NULL`, `granted_at` = quote `created_at`. Legacy rows are left untouched. *(Amendment 1)* This upsert must **not** call `ProjectMember::enrol()`. enrol() stamps `now()`, rewrites `org_id` on reactivation and has none of these source-row rules. *(Amendment 2)* Legacy rows whose `org_id != project.org_id` follow B4 (**HUMAN DECISION**), not "keeping that row's `org_id`".
   - **Crosswalk move-up:** set `plan_crosswalk.project_id` from its quote. Report duplicate `(project_id, plan_line_code)` pairs and rows where `crosswalk.org_id != project.org_id`. Nothing is deleted.
   - Each group is processed in its own DB transaction.
   - **`--dry-run`** runs the identical code path inside one transaction that is **always rolled back**. Recommended on a restored production snapshot on staging rather than on live production.
   - **Reports** go to `storage/app/backfill/projects-entity/<timestamp>/`: `groups.csv` (every proposed project with its quote ids and numbers, for human review), `unresolved.csv`, `membership_widening.csv` (users who gain access to quotes they weren't members of), `crosswalk_conflicts.csv`, `address_conflicts.csv`. A console summary prints counts. The exit code is non-zero when `unresolved > 0`.
   *Test:* see step 14. *Rollback:* run in order: `DELETE FROM project_members WHERE quote_id IS NULL AND project_id IS NOT NULL; UPDATE plan_crosswalk SET project_id = NULL WHERE quote_id IS NOT NULL; UPDATE quotes SET project_id = NULL; DELETE FROM projects;`. This is valid only before Phase 4 code has created native project data.

### Phase 3 — Permission layer and read paths (dual-read)
5. `PermissionService::isActiveProjectMember` matches `project_members.project_id`. Update the docblock. *(Amendment 2)* It also joins `projects` and requires `projects.org_id = $orgId` (B4).
6. In `RbacAudit`, add `quote_param` resolution, the model-or-scalar `project_param`, the `project_unresolved` reason and the audit `quote_id`. In `config/route_permission_map.php`, rename the **9** `project_param` entries (map lines 121, 122, 123, 128, 131, 132, 133, 136, 137) to `quote_param`, and add `quote_param => quote` on `GET projects/{quote}/workspace` (line 92). **This is one commit.** *(Amendment 4: D3-D5 are the exact spec for steps 5 and 6; D6-D8 for step 7.)*
7. Read-path controllers use **dual-read** visibility: `where(user_id = me OR project visibleTo(me, org))`. Owners keep access to their quotes even if a quote isn't backfilled yet, and project members gain access. This covers `QuoteController` reads (`getEstimatesList` including the total at `:121`, `getEstimateDetails`, `generatePDF`, `previewPDF`), `UserWorkspaceController`, `OrgAdminController::projects()` and `PlanCrosswalkController::index` (which also fixes the query at `:31-33`). The `user_id` OR-clause is removed in Phase 5. *(Amendment 4, D6-D8: the exact list of queries. Writes stay on `user_id = me` until Phase 4 step 9. `ProjectWorkspaceController::show` gets only the minimal fixes in D8.)*
   *Test:* `ProjectMembershipTest` (step 15). *Rollback:* revert the commits; step 6 must be reverted as a unit.

### Phase 4 — Write paths, UI, navigation, creation flows
8. Add `ProjectController` (index, list, store, update, destroy). `store` validates that the name is required, checks status against Q7's list, then **creates the project and calls `ProjectMember::enrol(creator, currentOrg, grantedBy: creator)` in one transaction**. `destroy` returns 422 if any non-trashed quote exists (Q6). Add `ProjectMemberController` (store/destroy through `enrol()`; the target user must be an active member of `project.org_id`). *(Amendment 1)* Callers follow A2: no own catch around `enrol()`; the response message branches on the A1 flags. *(B14.6)* `enrol()`'s `trashed()` guard checks only the in-memory instance, so a stale `Project` loaded before another request deleted it passes. Callers must load the project in the same request (route binding, which excludes trashed rows) or inside the same transaction that enrols, and never keep a `Project` across requests or queued jobs.
9. `QuoteController`: `store(Request, Project)` and `createFromList(Project, $listId)` set `project_id`. The project must pass `visibleTo`, otherwise 404. **No separate membership is created or needed.** `duplicate` copies `project_id`. `update`/`saveEditor`/`updateItem`/`destroyItem`/`destroy` use dual-read scoping. `project_id` is never mass-assignable from the request. In `saveEditor`, the customer rule accepts the quote's existing `customer_id` or one of the editor's own customers (Q10). `getEstimatesList` gets an optional `project_id` filter, validated against visible projects.
10. `PlanCrosswalkController`: *(B14.4)* the project comes only from route binding `{project}`; the legacy `project_id` param (which holds a quote id) is retired. `store/update/destroy(Project, ...)`. Duplicate-code check on `(project_id, plan_line_code)`. Fixes `:62`. Remove the old mutation routes.
11. `ProjectWorkspaceController::show(Project)`: 404 unless `visibleTo`. Sections are gated by display-only `checkPermission` calls, and the hard redirect at `:46-49` is removed. The §4.5 GC–sub check uses `project.org_id`. Add `legacyRedirect($quote)`, a 301 to `projects/{quote.project_id}` that returns 404 if the quote isn't visible. `OrgAdminController::addProjectMember/removeProjectMember` become project-keyed through `enrol()` *(Amendment 1)* (A2 applies here too). `OrgSettingsController::destroy` and `RbacController::destroyOrganization` refuse while projects exist (Q8). *(Amendment 2)* `RbacController::destroyUser` is added to this list. The check counts soft-deleted projects too. See B3 (**HUMAN DECISION**).
12. Views (ui-designer). Add the sidebar item and fix the `_nav` gate. Add `user/projects/index`. Turn `project-workspace/show` into the project page with an "Add estimate" button (links to the quotes create form with `?project_id=`). Add a required project picker in the estimate form and in the list-to-quote SweetAlert (fed by `GET projects/list`). Update `org-admin/projects`, the dashboard and the crosswalk views. Fix the two broken route names.
    *Rollback:* revert the Phase 4 commits. Consequence: projects, members and crosswalk rows created after deploy have `quote_id = NULL` and are invisible to the old code. Quotes keep `project_id`, which the old code ignores.

### Phase 5 — Tightening (LATER RELEASE, NON-REVERSIBLE, separate approval)
13. Only after all of these hold: `unresolved.csv` is empty; `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL` returns 0 (trashed quotes included); crosswalk conflicts are resolved; and the production `rbac_audit_logs` has no `project_unresolved` rows for an agreed period. Then take a full DB backup, run the tightening migration (see Data model, Phase 5), and remove the dual-read clause and legacy relations. *Rollback:* restore from backup only.

### Phase 6 — Tests (written alongside each phase; listed here as the acceptance set)
14. *(Amendment 3)* **The complete test list is C12**; it supersedes the bullets below.
    `ProjectBackfillTest`:
    - grouping by `(org, owner, name)`
    - singleton fallback for empty names and `estimate`
    - multi-org owner resolved through membership, or sent to `unresolved`
    - zero-org owner sent to `unresolved`
    - overrides CSV: org and `project_key` merge/split
    - membership union and widening report
    - explicit owner removal preserved
    - crosswalk move-up with duplicate and org-mismatch reports
    - trashed-only group creates a trashed project
    - `--dry-run` leaves DB counts unchanged
    - **a second run creates 0 projects, updates 0 quotes and inserts 0 members**
15. `ProjectMembershipTest`:
    - one membership row on P allows `checkPermission` through every quote in P
    - a non-member is denied
    - a member of P is denied on a quote in project P2
    - a member row with a different `org_id` is denied (wrong org)
    - an inactive membership is denied
    - `RbacAudit` resolves `quote_param` to a project, and an unknown or NULL-project quote gives `project_unresolved`
    - a bound `{project}` model resolves correctly
    - a delegate is checked against the principal's membership
16. `ProjectRoutesTest`: for **every** row in the permission table, cover allowed, **wrong role** (e.g. `viewer_read_only`, or `superintendent` for estimate routes), **wrong org** (user in org B; project in org A), and for scoped routes **missing membership**. Run under `rbac_mode=enforce` using **JSON requests** (403 per `RbacAudit.php:111-117`, because non-JSON denials give 302 — known open item #4, not fixed here). Additional cases:
    - Audit mode: a non-member gets **404 from the controller** and a `would_block` row is written. This is the production-mode guarantee.
    - Cross-org isolation: org B can't list, open, add a quote to, or edit the crosswalk of an org-A project, even with the id.
    - *(Amendment 2)* A member row whose `org_id` isn't the project's org, inserted directly, grants nothing in either org (B4). All 3 org-deletion paths refuse per B3, including an org whose only project is soft-deleted, and they delete nothing.
    - Creator auto-enrol: after `POST projects`, then `POST projects/{p}/quotes`, then `GET quotes/{id}/details`, the result is 200 with **zero** audit rows. This is the client's reported bug.
    - `PUT quotes/{id}` can't change `project_id`.
    - Project delete with live quotes gives 422.
    - The legacy workspace URL gives a 301 to the project.
    - Re-adding a removed member works.
    - *(Amendment 1)* Re-adding an **active** member through `POST projects/{project}/members` and `org-admin/projects/members` returns success with "already a member", not a 500. The row is unchanged and there is still exactly one row (A2).
    - Removed routes give 404/405.
    - *(B14.4)* A crosswalk request carrying `project_id=<a quote id>` is ignored or rejected, never resolved as a project.
17. `RoutePermissionMapTest` (config lint):
    - no `project_param` on a URI containing `quotes/`
    - every URI containing `{project}` has `project_param => 'project'`
    - every `quote_param` value is a param in that URI
    - every route registered under `projects/*` has a map entry
18. Full RBAC suite. Only the 3 known failures from `TESTING.md` may remain.

### Runbook (staging, then production)
Take a DB backup, then `php artisan down`, `migrate`, `projects:backfill --dry-run --force --no-interaction` (draft the overrides from its reports), then a **second dry-run with the FINAL overrides file** (`--dry-run --overrides=… --force --no-interaction`), whose reports are reviewed and signed off, then the real run `projects:backfill --overrides=… --force --no-interaction` with a **byte-identical** overrides file (the SHA-256 in `summary.txt` equals that of the second dry-run; must exit 0; C14.7, C14.13), deploy the Phase 3+4 code, then the **verification run**: `projects:backfill --dry-run --overrides=… --force --no-interaction` with the same file, still under `down`. Its counts for created, attached, inserted and linked must all be 0 and it must exit 0 (C14.13). If not, do **not** run `up` (C14.13). Only then `php artisan up`. No backfill runs after `up`, and `--allow-live` is not part of the runbook. Keep `rbac_mode` as it is; **do not toggle enforce as part of this release**.

*(Amendment 1)* **Rolling back after a real backfill:** never run `migrate:rollback --step=5` first. Use the A4 order.

*(Amendment 2)* **The deploy order is fixed by B6** and replaces the one-line order above. Before it: the B5 pre-flight checks and the B1 gate. If a `migrate` fails half-way, follow the B5 recovery steps.

## Production pre-flight queries (read-only; the human runs these, and the results size the backfill)

```sql
-- Q0 operative RBAC mode (the DB row, not .env) + DB version
SELECT `key`, value, updated_at FROM rbac_settings WHERE `key` = 'rbac_mode';
SELECT VERSION();
-- Q1 quote volume
SELECT COUNT(*) total, SUM(deleted_at IS NULL) live, SUM(deleted_at IS NOT NULL) trashed FROM quotes;
-- Q2 quotes needing the unnamed fallback
SELECT COUNT(*) FROM quotes
 WHERE COALESCE(TRIM(project_name),'') = '' OR LOWER(TRIM(project_name)) = 'estimate';
-- Q3 owner org resolution (0 / 1 / >1 active orgs)
SELECT org_count, COUNT(*) quotes FROM (
  SELECT q.id, COUNT(DISTINCT u.org_id) org_count
  FROM quotes q LEFT JOIN user_org_roles u ON u.user_id = q.user_id AND u.is_active = 1
  GROUP BY q.id) t GROUP BY org_count;
-- Q4 proposed groups with >1 quote (per owner)
SELECT q.user_id, LOWER(TRIM(q.project_name)) k, COUNT(*) n, GROUP_CONCAT(q.quote_number) nums
FROM quotes q WHERE COALESCE(TRIM(q.project_name),'') <> ''
GROUP BY q.user_id, k HAVING n > 1 ORDER BY n DESC;
-- Q5 same project name used by several owners in one org (decides Q2)
SELECT u.org_id, LOWER(TRIM(q.project_name)) k, COUNT(DISTINCT q.user_id) owners, COUNT(*) quotes
FROM quotes q JOIN user_org_roles u ON u.user_id = q.user_id AND u.is_active = 1
WHERE COALESCE(TRIM(q.project_name),'') <> '' GROUP BY u.org_id, k HAVING owners > 1;
-- Q6 legacy memberships
SELECT is_active, COUNT(*) rows_, COUNT(DISTINCT quote_id) quotes, COUNT(DISTINCT user_id) users
FROM project_members GROUP BY is_active;
SELECT COUNT(*) FROM project_members pm JOIN quotes q ON q.id = pm.quote_id
WHERE NOT EXISTS (SELECT 1 FROM user_org_roles u WHERE u.user_id = q.user_id AND u.org_id = pm.org_id AND u.is_active = 1);
-- Q7 crosswalk (expected 0: the store path cannot succeed today; if >0, investigate the source)
SELECT COUNT(*), COUNT(DISTINCT quote_id) FROM plan_crosswalk;
-- Q8 multi-org users
SELECT COUNT(*) FROM (SELECT user_id FROM user_org_roles WHERE is_active = 1
  GROUP BY user_id HAVING COUNT(DISTINCT org_id) > 1) t;
-- Q9 the client's would-blocks on quote routes, and the "Test 1" example
SELECT matched_pattern, reason, COUNT(*), COUNT(DISTINCT user_id), MIN(created_at), MAX(created_at)
FROM rbac_audit_logs WHERE project_id IS NOT NULL GROUP BY matched_pattern, reason;
SELECT id, user_id, name, project_name, quote_number, deleted_at FROM quotes
WHERE name = 'Test 1' OR project_name = 'Test 1';
```
*(Amendment 3)* Pre-flight Q16-Q17 are in C10. They are needed to finalize H1 and to size Phase 2.
*(Amendment 2)* Pre-flight queries Q10-Q15 are in B5.

## Acceptance criteria

| # | Client requirement | Verifiable check |
|---|---|---|
| 1 | `projects` table with the listed columns | Migration 000001 schema matches the Data model table; `ProjectRoutesTest` creates one through `POST projects` |
| 2 | Every quote has `project_id`; no quote outside a project | Every creation path takes a `{project}` (old `POST quotes`/create-from-list removed → 404/405 test); `duplicate` keeps `project_id`; after backfill, `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL` = 0 (trashed included). **This is enforced by the application until Phase 5 makes it NOT NULL.** |
| 3 | `project_members` keys on `project_id` | Column plus `unique(project_id,user_id)` exist; every new write sets `project_id`; `PermissionService:124-132` reads `project_id` (`ProjectMembershipTest`) |
| 4 | projectId derived from the quote's project; one membership covers all its quotes | `ProjectMembershipTest`: one row on P allows all quote routes for Q1..Qn in P; a quote in P2 is denied; lint test: no quote route uses `project_param` |
| 5 | Crosswalk scoped to project; the project's quotes read from it | Crosswalk rows carry `project_id`; the project page shows one crosswalk for all its quotes; duplicate line code per project rejected; the backfill moves legacy rows up |
| 6 | Creator auto-enrolled; a quote in the project needs no separate membership | `POST projects` inserts an active membership for the creator; create quote then open details gives 200 with **0** `rbac_audit_logs` rows (enforce and audit modes) |
| UI | Projects in the main nav; create project → add quotes → add members | Sidebar item visible with `project_management:R`; project page has add-estimate and member management |
| Migration | Existing quotes and memberships moved up | `ProjectBackfillTest` plus a clean second run on the staging snapshot; `unresolved.csv` empty |

## Regression risks

1. **Map rename atomicity.** If `quote_param` and the `RbacAudit` change ship separately, quote ids get checked as project ids, which can leak across projects. They are one commit (step 6), and there is a lint test (step 17).
2. **Wider visibility goes live whatever the RBAC mode.** *(Amendment 4, D13: this is intended and is confirmed as H7.)* Controller scoping is the only effective guard in audit-mode production. After this ships, teammates who are project members see and edit quotes they couldn't before. That is intended, but it takes effect immediately and isn't gated by enforcement.
3. **Role levels aren't enforced in audit mode.** A member with `project_management:R` but no `estimate_management` grant (e.g. `superintendent`) passes controller scoping on quote endpoints; only a `would_block` is logged. This is the same class of exposure as today's audit mode, but now it applies to shared data.
4. `{project}` is a bound model when `RbacAudit` reads it. A naive `(int)` cast would break. Handled and tested (step 15).
5. Removed URLs (`POST quotes`, create-from-list, crosswalk mutations): stale browser tabs get 404/405.
6. Behaviour changes from fixing the broken route names (`dashboard.blade.php:84`, `ProjectWorkspaceController.php:47`) and the crosswalk page, which currently throws.
7. Org deletion now refuses while projects exist (Q8), so the owner or admin sees a new error message.
8. The existing user-delete cascade (`quotes.user_id` cascadeOnDelete) now deletes quotes **inside shared projects** that teammates rely on. This isn't changed here; it is flagged in Q15.
9. `RbacTestCase` changes must keep the pre-existing 55 tests passing and the 3 known failures the same three, by name (total is now 84 passed / 3 failed with `ProjectSchemaTest`; it grows with each phase).
10. One extra indexed quote lookup per quote-scoped request in `RbacAudit`, and a membership subquery in listings.
11. From Phase 3 onward, `rbac_audit_logs.project_id` holds projects ids; historic rows hold quote ids (Q13).
12. PDF output, quote numbering and the per-quote `project_name`/`project_address` stay the same by design (Q12).
13. *(B14.4)* The crosswalk code and views use a `project_id` input that holds a **quote id**. The new routes use `{project}` binding, and a stale request that sends a quote id as `project_id` must never resolve as a projects.id.

## Risks / open questions (human/client decisions needed before or at Checkpoint 1)

- **Q1 — Owner / F-level bypass of membership.** Per the client's reading of plan §3.5 there is no bypass: `checkPermission` never skips membership. **But** the existing, unscoped `org-admin/projects/members` (project_management:F) remains the recovery path for orphaned projects. Per `role_permission_matrix.php`, that means any PM-F holder can self-enrol into any project in their org: `organization_owner`, `organization_admin`, `project_manager`, `estimator`. This is today's behaviour too. Confirm, or name a narrower gate. Restricting it by inline role checks isn't allowed, and no existing group isolates owners cleanly. For example, `estimator` also holds `user_management:F` and `auditor_read_all` holds `organization_management:F`.
- **Q2 — Grouping scope.** Proposed: one project per `(org, owner, name)`. Two colleagues' "Main St" quotes stay separate. Merging across owners would widen access. Decide using pre-flight Q5.
- **Q3 — Unnamed quotes.** Proposed: a singleton project per quote, and the default label `Estimate` counts as unnamed. Confirm the naming (`Untitled project (QT-…)`).
- **Q4 — Owners in several orgs, or in none.** Handled by manual review. The client or human supplies the overrides CSV. Volume comes from pre-flight Q3 and Q8.
- **Q5 — Moving a quote between projects.** Proposed out of scope for this release. If it's needed: `PUT quotes/{id}/project`, requiring `estimate_management:O` on **both** projects (two checks); the crosswalk does not move.
- **Q6 — Deleting a project that has quotes.** Proposed: soft delete, refused while any non-trashed quote remains. Alternative: cascade soft-delete the quotes.
- **Q7 — Project `status` vocabulary** (proposed `active`, `on_hold`, `awarded`, `lost`, `archived`) and whether `bid_due_at` needs a time and timezone or just a date.
- **Q8 — Org deletion when the org has projects.** *(Amendment 2)* There is a third path, `RbacController::destroyUser`, and soft-deleted projects also block deletion. See B3. Proposed: refuse. Alternative: cascade-delete projects and quotes, which destroys data.
- **Q9 — Production RBAC mode.** The client says audit. Pre-flight Q0 confirms it from `rbac_settings.rbac_mode`, which is the operative switch (`RbacAudit.php:236`). Staging should match production. Nothing in this release toggles the mode. Enforcement must not be switched on anywhere until Phase 4 is deployed and `unresolved` is 0.
- **Q10 — Teammates and customers.** Customers are per-user (`QuoteController.php:297,625`). Proposed: a teammate may keep the quote's existing customer, or pick from their own customers. Org-scoped customers would be a separate task.
- **Q11 — Delegation.** `checkPermission` checks the *principal's* membership (`PermissionService.php:59,79`), but controllers scope by `Auth::id()`, so a delegate would pass the middleware and then get a 404. Recommendation: pass the effective user id (`DelegationService::resolveEffectiveUserId`) into `visibleTo`. Confirm.
- **Q12 — Quote-level `project_name`/`project_address`.** Proposed: keep them as per-quote PDF subtitle and address, prefilled from the project. The PDF doesn't change. The alternative is to derive them from the project, which changes existing PDFs.
- **Q13 — Audit log semantics.** Add `rbac_audit_logs.quote_id` (migration 000005) so post-change rows can still be traced to the quote. Confirm the addition.
- **Q14 — Cross-org project membership** (GC→subcontractor). Out of scope. Membership stays own-org as today; the §4.5 check in the workspace is kept.
- **Q15 — User deletion.** The existing hard cascade on `quotes.user_id` now destroys quotes that teammates share. Fix it in this task or a follow-up?
- **Q16 — Estimate creation UX.** Proposed: pick an existing project, with a link to create one. The alternative is an inline "create project" in the estimate form and list-to-quote dialog; that is more routes and mapping.
- **Q17 — Test framework.** CLAUDE.md says Pest, but the repo uses PHPUnit 11 classes with no Pest dependency (`composer.json:24`). Tests will follow the existing PHPUnit pattern. The CLAUDE.md wording needs human-approved correction.
- **Undocumented premise.** The "project = Quote/Estimate (per client decision)" comment (`2026_06_25_000007:18`) has no written record. This feature supersedes it; record the client's new decision (this request) in the ARCHITECTURE.md change log.

**Cross-check against ARCHITECTURE.md "Known open items":**
1. *Rep-agency seller auth* (`sellerAuthorizationError` slug bug): **not touched.** No seller-authorization path is involved; the workspace only uses `gc_subcontractor`. Nothing here depends on or works around it.
2. *`api_system` phase*: **not touched.** No role or seed changes.
3. *Org type count 19 vs 20*: **not touched.**
4. *Audit middleware 403 vs 302*: **touched indirectly.** Every denied non-JSON request on the new or changed routes (including the legacy workspace redirect) under enforce returns a 302 back, not a 403 (`RbacAudit.php:120-125`). Tests assert denials with JSON requests. This plan does **not** change that behaviour; it stays with that open item's decision.

## Amendment 1 (2026-09-24, from Phase 1 compliance pass)

Source: REVIEW.md "Architect compliance pass — Phase 1", findings F1-F5. These refine the approved scope. None is a scope change. Human acknowledgement is due at Checkpoint 2.

**A1 — `ProjectMember::enrol()` semantics (F1).** The signature stays `enrol(Project $project, int $userId, int $orgId, ?int $grantedBy): self`. The lookup key is `(project_id, user_id)`. Legacy rows (`project_id` NULL) are never matched or modified.

| Existing row for `(project_id, user_id)` | Fields written | How the caller detects it |
|---|---|---|
| none | INSERT `project_id`, `user_id`, `org_id=$orgId`, `granted_by=$grantedBy`, `granted_at=now()`, `is_active=true` (`quote_id` stays NULL) | `wasRecentlyCreated === true` |
| active | **nothing.** No UPDATE, and `updated_at` isn't touched. (B4: an `$orgId` that differs from the project's org throws before this point) | `! wasRecentlyCreated && ! wasChanged()` |
| inactive | UPDATE `is_active=true`, `granted_by=$grantedBy`, `granted_at=now()`, `org_id=$orgId` (and `updated_at`) | `wasChanged('is_active')` |

Why `org_id` is rewritten when a member is reactivated but not when they are already active: reactivation is a new grant made in the current org, and new writes require `project.org_id == current org`. Rewriting an active row would silently change which org's `visibleTo` finds that member. Callers report an existing active row as "already a member". *(Amendment 2, B4 supersedes the earlier "active row with a different org_id" sentence and the table's "even if org_id != $orgId")* `enrol()` now throws `InvalidArgumentException` when `$orgId != project.org_id`, before any lookup, so that case is rejected rather than treated as a no-op, and neither `enrol()` nor the backfill can create such a row. On reactivation, `org_id` is rewritten to the value it must already have.
**This changes the current code.** It is Phase 1 **step 2a**.
**The Phase 2 backfill must NOT call `enrol()`.** It keeps its own upsert rules (step 4, "Membership move-up").

**A2 — The duplicate-key race (F2). Decision: `enrol()` handles it internally; callers don't.** *(Amendment 2)* **Corrected by B2:** the `createOrFirst()` mechanism and the "safe inside a transaction" sentence are superseded. The rule that callers don't catch still stands. The insert goes through Laravel 11's `createOrFirst()`, which catches `UniqueConstraintViolationException` and re-selects. The row it returns then follows the A1 rules. The reason is that every caller gets the same behaviour from one place, with no copied try/catch blocks. The callers are `ProjectController::store`, `ProjectMemberController::store` and `OrgAdminController::addProjectMember`. On MariaDB, a failed INSERT inside `ProjectController::store`'s `DB::transaction` rolls back only that statement, so `createOrFirst` is safe there.
Phase 4 requirement (steps 8 and 11): callers never wrap `enrol()` in their own catch. They choose the message from the A1 flags: added, re-activated, or already a member.
Tests:
- (a) Re-adding an active member leaves `granted_by`, `granted_at`, `org_id` and `updated_at` unchanged (step 3 unit test, and at route level in step 16).
- (b) Re-adding a removed member reactivates it with the new `granted_by`/`granted_at` (step 3, step 16).
- (c) Forced race (step 3): a one-off `ProjectMember::creating` listener inserts the conflicting row via `DB::table()` just before enrol's insert. `enrol()` returns that row without an exception, and exactly one row exists.

**A3 — MariaDB migration round-trip (F3). Part of step 3. BLOCKED ON INPUT** *(Amendment 2)* **The procedure is replaced by B1.** An empty-database `migrate` can't run in this repo. The acceptance criterion and the 1832 correction path below still apply. The gate is BLOCKED ON INPUT until the human supplies the production `SELECT VERSION()` (pre-flight Q0) and a scratch MariaDB of that major.minor version. Never run it on production or on the shared dev DB. `.env` has `APP_ENV=production`, so use `APP_ENV=testing` and a dedicated `DB_DATABASE`.
Procedure:
- Checkout **without** the 5 new files, run `migrate`, capture `SHOW CREATE TABLE` for `quotes`, `project_members`, `plan_crosswalk` and `rbac_audit_logs` (B0).
- With the files: `migrate`, then capture B1.
- `migrate:rollback --step=5`, then capture B0'.
- `migrate`, then capture B2.
- Guard check: insert one `project_members` row with `project_id` set and `quote_id` NULL. `migrate:rollback --step=3` must stop at 000003 with the RuntimeException, and `project_members` must be unchanged.
**Acceptance:**
- Every command exits 0, except the intended guard abort.
- B0' is identical to B0, including `quote_id bigint(20) unsigned NOT NULL`, the `*_quote_id_foreign` FKs with ON DELETE CASCADE, `unique(quote_id,user_id)` and index `(org_id,quote_id)`.
- B2 is identical to B1.
**If MariaDB refuses the NOT NULL `MODIFY` in `down()`** (ER_FK_COLUMN_CANNOT_CHANGE 1832, or any other error): Phase 1 is not done. QA routes the failure to the Architect (issue intake), not the Developer. The correction that will then be written into this plan is: in `down()` of 000003 and 000004, drop the `quote_id` FK, `MODIFY` it NOT NULL, then re-add `foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete()` with the default name, all before dropping `project_id`. The failure itself is safe, because it happens before `project_id` is dropped and the schema stays in the "up" state. **Phase 2 doesn't start until A3 passes.**

**A4 — Rolling back after the backfill (F5). Part of the Runbook.** *(Amendment 2)* B7 adds an audit-data guard to 000005, and step 3a below. After a real (not `--dry-run`) `projects:backfill`, don't make `migrate:rollback --step=5` the first move.
Hazard: the rollback runs 000005 → 000001. 000005 and 000004 revert first, dropping `rbac_audit_logs.quote_id` and `plan_crosswalk.project_id`. Then 000003's guard aborts on the backfilled member rows. That leaves a partial revert: `projects`, `quotes.project_id` and `project_members.project_id` remain, but the crosswalk link is gone.
Safe order (valid only before any Phase 4 code has written native data):
1. Full DB backup.
2. `php artisan down`.
3. Run the step 4 *Rollback* SQL, in its stated order, in one transaction.
4. Verify that `project_members WHERE quote_id IS NULL`, `plan_crosswalk WHERE quote_id IS NULL` and `projects` all count 0.
5. `migrate:rollback --step=5`.
6. `php artisan up`.
If the partial revert has already happened, `migrate` re-adds 000004/000005 as empty columns. *(N13, corrected)* Re-running `projects:backfill` does **not** restore `plan_crosswalk.project_id`: the backfill only scans unattached quotes (C4), and `quotes.project_id` survived the partial revert, so those quotes are already attached and their crosswalk rows are never revisited. Crosswalk rows therefore stay `project_id = NULL`. The recovery, all under `php artisan down`, after a backup, decided by the human: **restore the backup** (preferred), or run the manual repair `UPDATE plan_crosswalk c JOIN quotes q ON q.id = c.quote_id JOIN projects p ON p.id = q.project_id SET c.project_id = q.project_id WHERE c.project_id IS NULL AND c.org_id = p.org_id;` (the org condition mirrors H2), then verify with a `SELECT COUNT(*) FROM plan_crosswalk WHERE project_id IS NULL AND quote_id IS NOT NULL` and review any leftover as H2 rows. The backfill gains no relink option (C11 forbids new options). Any backfill re-run in this recovery also happens under `down` with `--force --no-interaction`. Nothing is written to `rbac_audit_logs.quote_id` before Phase 3, so nothing is lost there. Once Phase 4 native data exists, the only rollback is restoring from backup.

**A5 — Notes, no action.**
- F4: `quotes.project_id` has no explicit `->index()`. On MariaDB/InnoDB the FK creates `quotes_project_id_foreign`, which satisfies "indexed". The sqlite test schema lacks it, which only affects speed.
- `Project` has no `$attributes` default for `status`. `ProjectController::store` (step 8) already validates `status` and must set it explicitly, so no model change is needed.

**A6 — Guard against an unsaved `Project` in `enrol()` (N1, step 2a re-check). Decision: add the guard.** The first statement of `enrol()` throws `InvalidArgumentException` when `! $project->exists`. Without it, a NULL `$project->id` makes the lookup `project_id IS NULL`, which matches the user's legacy quote-only row. If that row is inactive, it would be reactivated and its `org_id` rewritten, silently turning a removed quote membership back on. No planned caller does this today. The guard is one line and makes A1's "legacy rows are never matched" hold unconditionally, not by caller convention, so it isn't accepted as a risk. This is Phase 1 **step 2b**; the test is in step 3. It refines the approved scope and is not a scope change.

## Amendment 2 (2026-09-24, from the Verifier's Phase 1 findings)

Source: REVIEW.md "Verifier findings — Phase 1" (V1, S1-S6, N1-N4). I rechecked the evidence against the code:
- the three org-deletion paths: `OrgSettingsController.php:65`, `RbacController.php:459` and `RbacController.php:506`;
- `Organization` has no SoftDeletes;
- `ProjectMember.php:52`, `Project.php:55-65` and `ProjectSchemaTest.php:341,354`;
- `OrgAdminController.php:626`;
- vendor `Eloquent/Builder.php:607-614,1839-1844`;
- `RbacTestCase.php:14-16`;
- `.ftpquota` in the repo root.

| Item | Finding | Type | Phase 1 change | Human decides? |
|---|---|---|---|---|
| B1 | V1 | refinement (procedure) | none; it is a gate | yes: supply the inputs, and choose the dump type |
| B2 | S1 | correction of A2 | code: step 2c; tests: step 3 | no |
| B3 | S2 | refinement of Q8 | none (Phase 4 step 11, tests in step 16) | **yes**: how `destroyUser` behaves |
| B4 | S3 | enforces an approved rule; changes one approved backfill rule | code: step 2d; tests: step 3 | **yes**: mismatched legacy rows |
| B5 | S4 | refinement (pre-flight checks, recovery) | none | no |
| B6 | S5 | refinement (runbook); optional release split | none | **yes**: whether Phase 1 gets an earlier window |
| B7 | S6 | refinement | code: step 2e; tests: step 3 | only at rollback time |
| B8 | N1 | tests | tests: step 3 | no |
| B9 | N2-N4 | notes | none | no |

**B1 — The executable MariaDB gate. It replaces the A3 procedure (V1). BLOCKED ON INPUT.** The repo's migration set can't run on an empty database, because `2025_01_15_000001-3` alter `saved_lists`/`palletes` before those tables are created. So the gate runs on a copy of production.

The human supplies:
1. Production `SELECT VERSION()` (pre-flight Q0).
2. A restorable copy of the production DB. **Preferred:** a full `mysqldump --single-transaction --quick --routines --triggers`. It gives real ALTER timings and runs the strict-mode table copy against real rows. **Minimum:** `mysqldump --no-data` of every table plus a data dump of the `migrations` table. The full dump contains customer data, so its handling is the client's decision. Restore it only to an access-restricted scratch instance, and wipe it afterwards.
3. A scratch MariaDB of the same major.minor version. It must not be production or the shared dev DB.

Procedure. QA runs it and records every output in PROGRESS.md. Use a dedicated env (`APP_ENV=testing`, scratch `DB_*`), never the checked-in `.env`.
1. Restore the dump.
2. Run `migrate:status`. **Only** the 5 `2026_09_24_*` migrations may be pending. If anything else is pending, STOP and route to the Architect, because it would also run in the production `migrate` (B5 Q10).
3. Capture B0: `SHOW CREATE TABLE` for `quotes`, `project_members`, `plan_crosswalk`, `rbac_audit_logs`, `organizations` and `users`.
4. Run `time php artisan migrate --force` and record each migration's duration. Capture B1.
5. Run the B2 two-session check.
6. Real ON DELETE check, inside a transaction that is then rolled back. All four must hold:
   - Deleting an org that owns a project fails with 1451.
   - Force-deleting a project removes its member and crosswalk rows.
   - Deleting a project that a quote references is blocked.
   - Deleting a creator sets `projects.created_by` to NULL.
7. Guard checks:
   - Set `rbac_audit_logs.quote_id` on one row. `migrate:rollback --step=1` must abort (B7). Then set it back to NULL.
   - Insert one new-format `project_members` row. `migrate:rollback --step=3` must abort at 000003. 000005 and 000004 reverting first is expected on scratch. Delete the row and run `migrate --force`.
8. Run `migrate:rollback --step=5` and capture B0'. Then run `migrate --force` and capture B2.

**Acceptance:**
- B0' is identical to B0, ignoring `AUTO_INCREMENT` counters.
- B2 is identical to B1.
- Step 2 shows only the 5 migrations.
- Steps 5-7 behave as stated.
- The step 4 timings are recorded and given to the human to size the maintenance window (B6).

If `down()` fails with 1832, the A3 correction path applies. If `migrate` fails half-way, follow the B5 recovery steps, then go to the Architect.
**Gate:** Phase 2 and **any production `migrate`** wait for B1, including an early Phase 1 window under B6.

**B1 Phase 2 checklist (C16.6; QA records every result in PROGRESS.md).** On the restored production copy, on MariaDB of the production major.minor version, after the B1 migration steps, the dry-run and real run of `projects:backfill` must show:
1. **Time zone:** `@@session.time_zone` versus `config('app.timezone')`; a DST-ambiguous `created_at` round-trips as the same stored string in the report and in `projects.created_at`.
2. **Strict mode and zero dates:** no failure on copied `created_at`, `granted_at`, `deleted_at` under the production `sql_mode` (the Q14 columns).
3. **Schema:** `SHOW CREATE TABLE` for `quotes`, `project_members`, `plan_crosswalk` shows no `ON UPDATE CURRENT_TIMESTAMP` on `updated_at` (so attaching a quote leaves `quotes.updated_at` unchanged), the FK indexes serve the `whereIn` reads, and `Schema::getColumns()` reports `quote_id` nullable on both tables (the C2 precondition passes).
4. **Collation and case sensitivity:** every SQL comparison in the command is on integer ids, so collation can't matter; confirm by comparing `groups.csv` with the Q4/Q5/Q16 SQL estimates and listing differences (accent and case variants) for the human.
5. **Bindings limit:** with the general log or query log on the scratch instance, no statement exceeds 400 bindings plus the `SET` binding, and inserts stay at 50 rows; no `max_allowed_packet` or placeholder error occurs at the largest owner.
6. **Locks and concurrency:** `FOR UPDATE` behaves as intended with a second session (a concurrent attach of one group quote yields `concurrent_change`, not a partial write); a deliberate deadlock or poison-row case is recorded in `errors.csv`, the group rolls back, and a re-run continues (`DB::transaction` does no retry).
7. **Dry-run cost:** duration, peak memory, lock waits and undo growth of the long outer transaction are recorded (they size the release window, B6), and the `--dry-run` counts are unchanged afterwards (no exit 3).
8. **Real run and verification:** the real run exits 0, and the verification dry-run under `down` reports 0 created/attached/inserted/linked (C14.13).
9. **File modes and location:** on the scratch filesystem the report directory is 0700 and every file 0600, `overrides.csv` is byte-identical to the input, and `storage/app/backfill/` is not web-accessible.
10. **Lost-connection path:** kill the DB connection mid-run (for example `KILL <connection id>` from a second session); the command aborts with exit 1, the current group rolled back, no partial group exists, and a re-run completes.
11. **Reports:** each file has its header and the expected rows, `summary.txt` ends with the exit code, and no client text appears in `storage/logs` or on the console.
12. **Verification of results:** the org invariant query (`project_members.org_id <> projects.org_id`) returns 0 and legacy `project_members` rows are unchanged (row count and checksum before and after).
13. **Failing group under `--dry-run` and real run (poison row), on the COPY only.** Never on production or the shared dev DB.
    - *Create the poison safely:* pick two owners A and B that each have at least one unattached quote. Pick a legacy member user `u` of one of A's quotes (or A itself). On the scratch instance only, create a late-failing trigger: `CREATE TRIGGER backfill_poison BEFORE INSERT ON project_members FOR EACH ROW IF NEW.user_id = <u> THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'poison'; END IF;`. It fails after the project insert and the quote attach, so it proves the rollback of earlier statements in the group. Record the instance name and the trigger in PROGRESS.md.
    - *Dry-run:* `projects:backfill --dry-run --overrides=<final> --force --no-interaction`. Expect **exit 1** (not 3): `errors.csv` has one row for A's group (exception class and message `poison`, no other group), B's groups appear in `groups.csv`, and the summary shows `errors: 1`. Afterwards the four counts (projects, project members, attached quotes, attached crosswalk rows) equal their pre-run values, so **everything, including B's groups, rolled back**.
    - *Real run on the copy:* the same command without `--dry-run`, under `down`. Expect exit 1. A's group has **no** project, no member rows, no attached quotes and no linked crosswalk rows (no partial write); B's groups are committed; the other groups of A (if any) are committed.
    - *Cleanup and recovery:* `DROP TRIGGER backfill_poison`, re-run the real backfill: A's group attaches, and the run exits 0 (or exits 1 only for previously known unresolved quotes). Re-run once more: all zeros. Restore the copy or wipe it afterwards.
    - *What it proves:* savepoints inside the outer dry-run transaction work on MariaDB, a failed group is isolated, a `DB::transaction` failure inside dry-run doesn't break the outer rollback, and the exit-3 check stays quiet when a group fails.

**B1 Phase 3 checklist (D17.9; QA records every result in PROGRESS.md).** On the restored production copy, after the Phase 2 backfill and its verification run:
1. `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL` is 0 (trashed included).
2. The real `rbac_mode` row (Q0) and the Q9 would-block counts on quote routes.
3. Query plans and latency of `quotes.project_id IN (visibleTo subquery)`, index use of `project_members(project_id, user_id)`, and list latency with the in-memory total.
4. `CurrentOrg` and the middleware agree for real multi-org users, including the enforce-mode owner case (D17.4).
5. The route-binding order in the real middleware stack (bound `Quote` model versus scalar at `RbacAudit`).
6. The PDF engine and the public-disk write by a non-owner, and what a member's details JSON exposes (H7).
7. The dashboard for a multi-org owner with legacy rows in a second org (D17.1).


**B2 — The `enrol()` race inside a caller transaction (S1). Corrects A2.**
What A2 got wrong: it's true that a failed INSERT rolls back only itself. But under InnoDB REPEATABLE READ, `enrol()`'s plain `first()` (`ProjectMember.php:52`) opens the read view. `createOrFirst()`'s fallback (`Builder.php:612`) is a non-locking read on that same view. It can't see the row a concurrent transaction committed, so it rethrows `UniqueConstraintViolationException`, which surfaces as a 500. `withSavepointIfNeeded` (`:1839-1844`) only adds a savepoint. It doesn't refresh the read view.
**Decision, step 2c (Developer, `ProjectMember.php` only):**
- Replace `createOrFirst()` with `create()` inside a `try`.
- On `Illuminate\Database\UniqueConstraintViolationException`, re-select with `static::where($key)->sharedLock()->firstOrFail()`.
- Then apply the unchanged A1 active/inactive rule.

A locking read returns the latest committed version, whatever the read view. Use `sharedLock()` rather than `lockForUpdate()`. The failed duplicate INSERT already holds a shared lock on the conflicting index record. A second shared lock is compatible with it. Upgrading to an exclusive lock can deadlock two concurrent losers (1213). No savepoint is needed on MariaDB.

Callers still don't catch, and they **may** call `enrol()` inside a transaction. Residual risk, accepted: the inactive race-lost branch needs an exclusive lock for its UPDATE. It is reachable only if the concurrently inserted row is deactivated before the re-select.
**What each test can prove:**
- The sqlite test in step 3 proves only the branch logic. That is the `creating`-listener race test in its active and inactive forms (B8). `sharedLock()` compiles to nothing on sqlite, and sqlite has no MVCC read views.
- The proof on MariaDB is B1 step 5, run in two sessions (e.g. two `php artisan tinker` processes):
  1. Session 1 runs `DB::beginTransaction()` and then any SELECT on `project_members`.
  2. Session 2 enrols (P,U) and commits.
  3. Session 1 calls `enrol(P,U,…)`. It must return that row with `wasRecentlyCreated=false` and no exception.

  The same sequence throws with the pre-2c code. Record both results if you can.

**B3 — Deleting an org that owns projects (S2).** There are three paths, not two. Each hard-deletes `organizations` rows inside a `DB::transaction`:
- `OrgSettingsController.php:65`;
- `RbacController::destroyOrganization` (`:459`);
- `RbacController::destroyUser` (`:506`), for orgs where the user was the only active member.

Once any project exists, `projects.org_id` restrict raises 1451. The transaction rolls back, so nothing is partly deleted, but the user sees a generic 500.
**New finding:** the FK ignores `deleted_at`, so **soft-deleted** projects block deletion too. Every refusal check must use `Project::withTrashed()->where('org_id', …)->exists()`. It must run **before** the transaction, because each path first deletes audit logs, role logs, delegations and roles.
Exposure: no project rows exist until the first real backfill. The refusals ship in Phase 4, in the same release window. If B6 is followed, no production window exposes these 500s.
**HUMAN DECISION for `destroyUser`:**
- **Option A (recommended):** refuse the whole user deletion and delete nothing. Message: "User X is the only active member of organization(s) Y, which own N project(s) (including archived ones). Add another member or remove the projects first." This matches the approved Q8.
- **Option B:** delete the user but skip the orgs that own projects. That leaves orgs with no members whose projects nobody can open. Not recommended.
- **Option C:** cascade. This destroys data and was rejected under Q8.

Q15 is still open. `$user->delete()` still hard-cascades the user's quotes (`quotes.user_id`), including quotes inside shared projects.
Tasks: step 11 adds `destroyUser`. Step 16 tests all 3 paths:
- It refuses (302 back with the error message; the org, its roles and its logs are all still there) for an org with a live project.
- It refuses for an org whose only project is soft-deleted.
- It succeeds for an org with no projects.

**B4 — Membership is tied to `projects.org_id` (S3).** The invariant: for every row with `project_id` set, `project_members.org_id = projects.org_id`.
- This is **not a scope change**, and it doesn't conflict with Q14; it enforces Q14. Model relations already says "New writes require `project.org_id == current org`", and Q14 puts cross-org membership out of scope. A member row with another org's `org_id` *is* a cross-org membership.
- It is enforced by guards, as with A6, not by caller convention.

Step 2d (Developer):
- `scopeVisibleTo` also requires `projects.org_id = $orgId`.
- `enrol()` throws `InvalidArgumentException` when `(int) $project->org_id !== $orgId`. Cast it: a freshly created model holds whatever value was assigned.

Phase 3 step 5 makes the same check in `isActiveProjectMember`. In A1, the "active row with a different `org_id`" case becomes unreachable through `enrol()` and through the backfill.
**HUMAN DECISION (this changes the approved Phase 2 rule "keeping that row's `org_id`"):** what happens to a legacy row with `org_id != project.org_id`?
- **Option A (recommended):**
  - If the user has an active `user_org_roles` row in `project.org_id`, carry the row over with `org_id = project.org_id` and list it in `membership_rehomed.csv`.
  - Otherwise, don't carry it over. List it in `membership_org_mismatch.csv` for manual review; the overrides CSV can change the project's org.
  - Users in that second group lose access that only worked cross-org, which Q14 excludes.
- **Option B:** never carry a mismatched row; report them all.

Tests:
- *(Amendment 2)* Step 3 rewrites **three** tests, not one. See B10 items R1-R3 (from D1 of the step 2c-2e re-check). The original note:
- Step 3 rewrites `ProjectSchemaTest.php:341-354`:
  - `enrol()` with another org throws and writes nothing.
  - A mismatched row inserted with `DB::table()` doesn't appear in `visibleTo(user, otherOrg)` or in `visibleTo(user, org)`.
- Step 15: `checkPermission` denies on a mismatched row.
- Step 14: the reports for re-homed and mismatched rows.

**B5 — Pre-flight checks and recovery from a half-failed `migrate` (S4).** Each `up()` in 000001-000004 is a series of DDL statements that each commit on their own, and the `migrations` row is written only at the end. Per the Verifier, ADD FOREIGN KEY with `foreign_key_checks=1` copies the whole table: `quotes`, `project_members` and `plan_crosswalk` are rebuilt, writes are blocked, and strict `sql_mode` validates every row. B1 step 4 confirms the real behaviour.
Pre-flight additions. They are read-only; the human runs them on production and records the results in PROGRESS.md:
```sql
-- Q10 applied migrations vs the repo: only the 5 new ones may be pending
SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 15;
-- Q11 definitions (InnoDB, bigint unsigned ids, FK names such as project_members_quote_id_foreign)
SHOW CREATE TABLE quotes; SHOW CREATE TABLE project_members; SHOW CREATE TABLE plan_crosswalk;
SHOW CREATE TABLE rbac_audit_logs; SHOW CREATE TABLE organizations; SHOW CREATE TABLE users;
-- Q12 settings that decide how the ALTERs behave
SELECT @@sql_mode, @@lock_wait_timeout, @@innodb_lock_wait_timeout, @@foreign_key_checks;
-- Q13 size of the tables that will be copied
SELECT table_name, table_rows, ROUND((data_length+index_length)/1048576,1) mb FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name IN ('quotes','project_members','plan_crosswalk','rbac_audit_logs');
-- Q14 zero dates that would fail the strict-mode copy (repeat for every DATE/DATETIME/TIMESTAMP column of the 4 tables, listed by SHOW COLUMNS)
SELECT COUNT(*) FROM quotes WHERE created_at = '0000-00-00 00:00:00' OR updated_at = '0000-00-00 00:00:00' OR deleted_at = '0000-00-00 00:00:00';
-- Q15 long-running statements that would hold metadata locks
SELECT id, user, time, state, info FROM information_schema.processlist WHERE command <> 'Sleep' AND time > 30;
```
If Q10 shows other pending migrations, if Q11 differs from the migration definitions, or if Q14 is > 0, STOP and route to the Architect.
**Decision: `up()` is not made idempotent.** No migration in this repo is, and guards like `hasColumn` would hide schema drift. Recovery is manual:
1. Keep the site in maintenance mode, and don't re-run `migrate` blindly.
2. Run `migrate:status`. The first Pending migration is the one that failed.
3. Run `SHOW CREATE TABLE` on its table. Remove only the objects of that migration that are present, in the order below. Then fix the cause and run `migrate --force`.
4. ~~If every object is present and only the `migrations` row is missing, insert that row instead: `INSERT INTO migrations (migration, batch) VALUES ('<name>', <next batch>)`.~~ *(Amendment 2, B11-B13)* **Replaced by B11.** A manual `migrations` row may be inserted only after B11's per-table verification passes.
5. If the cause isn't clear quickly, restore the B6 backup. The old code runs on the old schema.

| Migration | Objects to remove (only those present) |
|---|---|
| 000001 | `DROP TABLE projects` |
| 000002 | `ALTER TABLE quotes DROP FOREIGN KEY quotes_project_id_foreign`, then `DROP COLUMN project_id` |
| 000003 | `ALTER TABLE project_members DROP FOREIGN KEY project_members_project_id_foreign`, then `DROP INDEX project_members_project_id_user_id_unique`, then `DROP COLUMN project_id` |
| 000004 | `ALTER TABLE plan_crosswalk DROP FOREIGN KEY plan_crosswalk_project_id_foreign`, then `DROP INDEX plan_crosswalk_org_id_project_id_index`, then `DROP COLUMN project_id` |
| 000005 | a single statement, so nothing to clean up |

In 000003 and 000004 the `quote_id` MODIFY is the last statement, so a partial `up()` leaves `quote_id` as it was.

**B6 — Hard deploy order (S5).** This replaces the one-line order in the Runbook. `.ftpquota` points to FTP-style uploads, which aren't atomic. The earlier incident was code going live ahead of its schema. The same thing here would make quote routes return 500, because `projects` and `project_id` wouldn't exist yet.
0. B1 has passed. The B5 pre-flight checks are clean. The window is sized from B1's timings.
1. Take a full DB backup and test-restore it to the scratch instance.
2. `php artisan down`.
3. Upload **only** the 5 `2026_09_24_*` migrations, the Phase 1 models and `app/Console/Commands/BackfillProjects.php`. The old code doesn't use any of them. *(Amendment 2, B11-B13)* **Corrected by B12.** In the Phase 1 window (the human chose the split), the upload set is exactly the 5 migrations, the new `app/Models/Project.php` and the 4 modified models. `BackfillProjects.php` goes up in the release window. The B12 file-diff check runs before any upload, in every window.
4. `migrate:status` (only the 5 pending), then `php artisan migrate --force`.
5. Verify: `migrate:status` shows all 5 as Ran, and `SHOW CREATE TABLE` matches B1's capture.
6. `projects:backfill --dry-run --force --no-interaction` (to draft overrides); then a second `--dry-run --overrides=<final> --force --no-interaction`, whose reports are the ones reviewed and signed off; then the real `projects:backfill --overrides=<final> --force --no-interaction`, which must exit 0 (C14.7) and whose overrides SHA-256 (in `summary.txt`) equals the second dry-run's (C14.13). The review step must also sign off the summary's unlinked-crosswalk and unresolved counts (C14.10).
7. Upload the rest of the Phase 3+4 code while the site is still down. Upload `RbacAudit.php` and `config/route_permission_map.php` together. Then run `php artisan optimize:clear`, and re-cache if production caches config or routes.
8. Run the verification backfill, **still under `down`**, as a dry run: `projects:backfill --dry-run --overrides=<final> --force --no-interaction` with the **same** file as step 6. Created, attached, inserted and linked must all be 0 and the exit code 0; otherwise the gate has failed and `up` is forbidden (C14.13). Then smoke-check with a known member account: project list, a quote's details, the PDF. `down` is issued without `--secret`, so nobody can bypass maintenance mode before step 7; if the smoke check needs a bypass, re-issue `down --secret=…` only after the verification run. Then `php artisan up`. **No backfill runs after `up`**, and `--allow-live` is never used in this runbook.

**Never** upload Phase 3/4 files before step 5 has passed.
**HUMAN DECISION (release split).** Recommended: run steps 0-5 as a separate, earlier window for Phase 1 only (migrations and models; the backfill command goes up in step 3 of the release window).
- Phase 1 alone is safe in either code order (Verifier, confirmed).
- It moves the table-copy ALTERs out of the release window.
- No project rows exist until the backfill, so B3's 500s can't happen in between.

This changes "Phases 1-4 ship together as one release" into "Phase 1 schema first, then Phases 2-4 together". That is a release-plan change, not a scope change.

**B7 — Guard on `000005::down()` (S6).** Step 2e (Developer, only that migration): abort with a `RuntimeException` if any `rbac_audit_logs.quote_id IS NOT NULL` row exists. It uses the same pattern as 000003/000004. Rollback runs 000005 first, so this guard also stops `--step=5` before any partial revert once Phase 3 has written audit rows. Losing that data becomes an explicit human decision. **A4 step 3a:** export `SELECT id, quote_id FROM rbac_audit_logs WHERE quote_id IS NOT NULL` to a CSV kept with the backup. Only after that, run `UPDATE rbac_audit_logs SET quote_id = NULL`. Test (step 3): the guard aborts and keeps the column when such a row exists; `down()` succeeds when every value is NULL.

**B8 — Test tasks (N1). QA, step 3.** *(Amendment 2)* These are listed in B10 as items F1-F4 and N2-N3.
- **FK behaviour.** Replace the tautological `quotes.project_id` restrict assertion with behaviour tests, on sqlite with FK enforcement on:
  - force-deleting a project that a quote references fails;
  - force-deleting a project removes its `project_members` and `plan_crosswalk` rows;
  - deleting an org that owns a project fails;
  - deleting the creator sets `projects.created_by` to NULL.

  Real InnoDB enforcement is covered by B1 step 6.
- **Race test.** Add the **inactive** race-lost case: the listener inserts an inactive row, and `enrol()` reactivates it with the A1 fields and `wasChanged('is_active')`. Keep the active case. Both run against the step 2c code.
- **Other items.** The B2, B4 and B7 tests listed above. The stub `quotes` table has no `user_id` cascade. That doesn't matter for Phase 1. The Phase 4 tests for `destroyUser`/Q15 need a stub with the cascade.
- **Expected result.** The RBAC suite shows only the 3 known failures, by name.

**B9 — Notes, no action.**
- N2 (what sqlite can't prove) is covered by B1 steps 4-7.
- N3: `enrol()` accepts a soft-deleted project. Phase 4 callers either get the project through route binding, which excludes trashed ones, or have just created it. The backfill doesn't call `enrol()`. No guard is needed.
- N4: legacy readers assume `quote_id` is non-null. Native rows only come from Phase 4, which ships with the Phase 3 rewrites. That holds under the B6 split too.

**B10 — The complete step 3 test checklist (D1 from the step 2c-2e re-check; QA only; replaces the scattered notes).** File: `tests/Feature/Rbac/ProjectSchemaTest.php`. Test names are stable; line numbers are historical (B14).
**Precondition.** FK enforcement is on in the sqlite test DB (`config/database.php:39`, `DB_FOREIGN_KEYS` defaults to true). F1-F4 check this with `PRAGMA foreign_keys` = 1 before asserting anything.
**Status:** *(B14)* R1-R3 were done. `ProjectSchemaTest` is fully green (see REVIEW.md for the current count); the whole RBAC suite is 84+ passed / 3 known failures. Test names and line numbers below are from the file *when B10 was written* and have since shifted; use the names.

*Rewrite. These encode the pre-B4 cross-org behaviour.*
- **R1** `test_enrol_active_member_is_unchanged_even_with_different_org` (:231; the failing call is at :244). Split it into two tests:
  - (a) `test_enrol_active_member_is_unchanged`:
    - First enrol with `$this->org` and grantor `g1`. Set `granted_at` and `updated_at` to `2020-01-01`.
    - Re-enrol with **`$this->org`** (the project's org) and grantor `g2`.
    - Expect `wasRecentlyCreated=false` and `wasChanged()=false`.
    - The row is byte-identical to before: `granted_by=g1`, both 2020 dates, `org_id` = the project's org. Exactly 1 row.
  - (b) `test_enrol_mismatched_org_on_existing_row_throws_and_writes_nothing`:
    - Enrolling on the same active row with `$otherOrg` throws `InvalidArgumentException`.
    - The row is byte-identical, and there is still 1 row.
- **R2** `test_enrol_inactive_member_is_reactivated_with_new_grant` (:253; the failing call is at :263, and the old assertion `org_id === otherOrg` is at :269):
  - Re-enrol with **`$this->org`** and grantor `g2`.
  - Expect `wasRecentlyCreated=false` and `wasChanged('is_active')=true`.
  - The row has `is_active=1`, `granted_by=g2`, `granted_at` later than 2020, `org_id` = the project's org, and the same `id`. Exactly 1 row.
  - Also: enrolling on an **inactive** row with `$otherOrg` throws, and the row stays inactive and byte-identical.
- **R3** `test_scope_visible_to` (:326; the failing call is at :341, and the old assertion `['wrong-org']` for `otherOrg` is at :354):
  - Remove `enrol($wrongOrg, …, $otherOrg)`. Insert that mismatched row with `DB::table('project_members')` instead: the project is in `$this->org`, the member has `org_id=$otherOrg` and is active.
  - Expect `visibleTo(user, org) === ['visible']` and `visibleTo(user, otherOrg) === []`.
  - Add a positive control: a project **owned by** `$otherOrg`, with the user enrolled with `$otherOrg`, is visible in `$otherOrg` and not in `$this->org`.
  - Keep the existing inactive, other-user, soft-deleted and legacy-only assertions.

*Add.*
- **N1** `test_enrol_mismatched_org_on_new_member_throws_and_writes_nothing`: the project has no rows. `enrol(p, user, $otherOrg, …)` throws `InvalidArgumentException`, and the row count is still 0.
- **N2 (race, active)** Keep `test_enrol_race_returns_conflicting_row_without_exception` (:276) and rename it `test_enrol_race_lost_active_row_is_returned_unchanged`. Add assertions:
  - `wasChanged()=false`;
  - `is_active` true;
  - `granted_by = racer`, meaning the racing row wasn't overwritten.
- **N3 (race, inactive)** `test_enrol_race_lost_inactive_row_is_reactivated`:
  - Use the same one-off `creating` listener, but insert the racing row with `is_active=false`, `granted_by=racer`, `granted_at='2020-01-01'` and `org_id` = the project's org.
  - `enrol(p, user, $this->org, g2)` returns `wasRecentlyCreated=false` and `wasChanged('is_active')=true`.
  - The row has `is_active=1`, `granted_by=g2`, `granted_at` later than 2020 and `org_id` = the project's org. Exactly 1 row.
- **F1-F4 (FK behaviour, B8).** In `test_quotes_project_id_is_nullable_fk` (:88), delete the tautological restrict assertion at :94 and keep the nullable and FK-exists checks. Then add:
  - F1 `test_force_deleting_project_referenced_by_quote_fails` (QueryException, and the project still exists);
  - F2 `test_force_deleting_project_cascades_members_and_crosswalk`;
  - F3 `test_deleting_org_that_owns_a_project_fails`;
  - F4 `test_deleting_creator_nulls_projects_created_by`.
- **G1-G2 (B7).**
  - `test_rollback_000005_aborts_when_quote_id_set`: a RuntimeException is thrown, and the column still exists.
  - `test_rollback_000005_clean_when_all_null`: the column is dropped.

*Confirm, no change expected.* `RbacTestCase.php` runs the 5 new migrations plus `2026_09_01_000001`, and `PermissionServiceTest` still passes on its stubs.
*Not sqlite; BLOCKED ON INPUT.* The B1 MariaDB gate, including the B2 two-session check (step 5) and real ON DELETE enforcement (step 6).
*(Amendment 2, B11-B13)* The optional hardening tasks O1-O2 are in B13. They aren't blockers.
**Acceptance:** `ProjectSchemaTest` is fully green. `php artisan test --filter=Rbac` shows only the 3 known failures, by name: 403 vs 302, 19 vs 20 org types, 14 vs 15 P2 roles. QA records the counts in PROGRESS.md.

### Amendment 2 addendum (2026-09-24, from the Verifier re-review R1-R3)

These are runbook and test-task wording only. **No code change is needed.** They refine the approved scope; they don't change it. They are part of the Amendment 2 acknowledgement at Checkpoint 2.

**B11 — Verify before recording a migration by hand (R1). Replaces B5 recovery step 4.** 000003 and 000004 end with a separate `MODIFY quote_id … NULL`. If that statement fails or is interrupted, the FK, the unique/index and the column all exist, but `quote_id` is still NOT NULL. Recording the migration as Ran in that state would break every Phase 4 new-format INSERT, and it would mislead the `down()` guards. Rule: **insert a `migrations` row by hand only when that migration's table matches the B1 capture exactly** (`SHOW CREATE TABLE`, ignoring `AUTO_INCREMENT`). If there is no B1 capture for that table, every item in the table below must hold instead. If anything is missing, finish the missing statement by hand (last column) and check again. Never insert the row on a partial match. Also run:
```sql
SELECT table_name, column_name, is_nullable, column_type FROM information_schema.columns
 WHERE table_schema = DATABASE() AND column_name IN ('project_id','quote_id')
   AND table_name IN ('quotes','project_members','plan_crosswalk','rbac_audit_logs');
```
| Migration | Must all be present | Statement to finish it by hand, if only this is missing |
|---|---|---|
| 000001 | table `projects` with `deleted_at`; FKs `projects_org_id_foreign` (RESTRICT) and `projects_created_by_foreign` (SET NULL); indexes `projects_org_id_status_index` and `projects_created_by_index` | none. If anything is missing, use the B5 removal steps and re-run |
| 000002 | `quotes.project_id` `bigint(20) unsigned`, nullable; FK `quotes_project_id_foreign` (RESTRICT) | none. B5 removal steps, then re-run |
| 000003 | `project_members.project_id` nullable; FK `project_members_project_id_foreign` (CASCADE); unique `project_members_project_id_user_id_unique`; **`quote_id` `is_nullable = YES`**, with FK `project_members_quote_id_foreign` and `unique(quote_id,user_id)` still there | `ALTER TABLE project_members MODIFY quote_id BIGINT UNSIGNED NULL` |
| 000004 | `plan_crosswalk.project_id` nullable; FK `plan_crosswalk_project_id_foreign` (CASCADE); index `plan_crosswalk_org_id_project_id_index`; **`quote_id` `is_nullable = YES`**, with FK `plan_crosswalk_quote_id_foreign` still there | `ALTER TABLE plan_crosswalk MODIFY quote_id BIGINT UNSIGNED NULL` |
| 000005 | `rbac_audit_logs.quote_id` nullable, after `project_id` | none (a single statement) |

**Batch:** use the batch already recorded for the other `2026_09_24_*` migrations of the same run (`SELECT MAX(batch) FROM migrations WHERE migration LIKE '2026_09_24_%'`). Use `MAX(batch)+1` over the whole table only if none of them is recorded. Then run `migrate:status` and continue with `migrate --force` for the rest. Record every manual statement in PROGRESS.md.

**B12 — Check production's copies of overwritten files before any FTP upload (R2). Adds B6 step 2a; this is a gate.** The Phase 1 window overwrites four existing production files: `app/Models/Quote.php`, `app/Models/PlanCrosswalk.php`, `app/Models/Rbac/ProjectMember.php` and `app/Models/Rbac/AuditLog.php`. Production can drift from git; that is what the earlier incident was.
Procedure. Run it before the window if possible, and again right before the upload.
1. Download production's copy of every file in the upload set into a scratch folder. Keep it: it is also the file-level rollback.
2. For each file that already exists on production, run `diff --strip-trailing-cr <(git show main:<path>) <scratch copy>`. `main` and `dev` have identical app code (Verifier, `git diff --stat main dev`).
3. The 6 new files (5 migrations and `Project.php`) must **not** already exist on production.
4. After the upload, check that each uploaded file's size and checksum match the local copy. FTP transfers can be truncated.

**If any file differs, or a new file already exists:** STOP, upload nothing, and route it to the Architect (issue intake). **HUMAN DECISION:** the production change is either brought into git first (committed to `dev`, then this branch is rebased, then the Phase 1 compliance check and B10 are re-run), or explicitly discarded with a written reason. Never upload over an unexplained difference. The same check applies in the release window to every file that window overwrites.

**B13 — Optional test hardening (R3), plus the Phase 1 exit criteria.**
*Optional QA tasks. They don't block anything; the same risks are covered at B1 steps 5-6.*
- **O1:** give F1 (`test_force_deleting_project_referenced_by_quote_fails`) and F3 (`test_deleting_org_that_owns_a_project_fails`) a negative control. After the expected failure, remove the one referencing row (detach the quote, or delete the project) and assert that the same delete now succeeds. That proves the intended FK is the one blocking it.
- **O2:** `test_enrol_inside_outer_transaction_stays_usable`. Inside `DB::transaction`, force the race with the `creating` listener, call `enrol()`, then run another write in the same transaction. Assert that the transaction commits and both writes persist. On sqlite this proves only that the transaction stays usable. The REPEATABLE READ behaviour is proven only at B1 step 5.

*Phase 1 exit criteria. **All** must hold before the Phase 1 production window runs `migrate`.* The status of each is recorded in PROGRESS.md.
1. **Code and tests:**
   - The Architect's compliance checks for steps 1-2e are in REVIEW.md and are COMPLIANT.
   - B10's acceptance holds: `ProjectSchemaTest` is fully green, and `--filter=Rbac` shows only the 3 known failures, by name.
   - The Verifier confirmed this at 84 passed / 3 failed.
2. **B1** passes on a **restored production copy**, on MariaDB of the production major.minor version:
   - only the 5 migrations are pending;
   - B0' = B0 and B2 = B1;
   - the B2 two-session check returns the row with no exception;
   - the 4 ON DELETE checks hold;
   - the 000003/000004/000005 guards hold;
   - the timings are recorded, and the human has sized the window from them.
3. **B5 pre-flight Q10-Q15 is clean.** The Q14 zero-date check covers every DATE/DATETIME/TIMESTAMP column of `quotes`, `project_members` and `plan_crosswalk`.
4. **The B12 file diff is clean** for the whole upload set.
5. **Backup:** a full backup has been taken and test-restored (B6 step 1).
6. **During the window:** maintenance mode is on, `migrate:status` shows only the 5 pending, and after the migrate the B6 step 5 check matches the B1 capture.
7. **Human acknowledgement** of Amendments 1 and 2, including B11-B13, at Checkpoint 2.

Criteria 2-5 are inputs from the human or results from the environment. The agents can't satisfy them on their own.

### Amendment 2 addendum 2 — B14 (2026-09-24, from the Verifier re-review "Sonnet, independent" and the Architect re-verification)

Refinement and test tasks only. No new route, table, column, permission group or role.

**B14.1 — The A6 test must actually exercise A6 (Verifier finding 1, should-fix).** Test-only. QA, step 3, in `ProjectSchemaTest.php`; *no code change*; not a human decision.
- Replace the body of `test_enrol_unsaved_project_throws_and_leaves_legacy_row_untouched` so it builds `new Project(['org_id' => $this->org->id])`. With a null `org_id` the B4 guard throws first and the A6 guard is masked.
- Assert: `InvalidArgumentException` with the A6 message ("Cannot enrol a member on an unsaved project."), so the B4 message can't satisfy it; the `project_members` row count is unchanged; and a pre-existing inactive legacy row (`quote_id` set, `project_id` NULL) for the same user is byte-identical (`is_active`, `org_id`, `granted_by`, `granted_at`, `updated_at`).
- **Mutation proof, required.** QA temporarily disables the A6 guard **on a scratch copy** (not the tracked file), confirms this test fails, and records it in PROGRESS.md. Do the same for the B14.2 guard.

**B14.2 — `enrol()` and soft-deleted projects (Verifier note 3; supersedes B9/N3's "no guard is needed").** Decision: **refuse.** `enrol()` throws `InvalidArgumentException` when `$project->trashed()`. Guard order becomes A6, trashed, B4, lookup. Reasons: no caller has a legitimate need; a trashed project is invisible to `visibleTo`, so the enrolment would be a silent dead write; and it makes A1's "no orphan writes" rule unconditional instead of depending on Phase 4 callers using route binding.
- **Code change: yes, Phase 1 step 2f** (Developer; `app/Models/Rbac/ProjectMember.php` only, one guard).
- Test (step 3): a soft-deleted project throws, writes nothing, and leaves an existing member row for it untouched.
- **Human decision: no.** No caller exists and nothing user-visible changes. It is covered by the Checkpoint 2 acknowledgement of B14.

**B14.3 — Legacy `quote_id` FK and unique survive `->change()` (Verifier note 4).** Test-only, step 3, no code change, not a human decision. Add `test_legacy_quote_id_fk_and_unique_survive_change` for `project_members` and `plan_crosswalk`:
- `Schema::getForeignKeys()` still lists a foreign key on `quote_id` referencing `quotes`. The legacy indexes `unique(quote_id,user_id)` (members) and `(org_id,quote_id)` (crosswalk) remain.
- Behaviour, FKs on: inserting a row with a non-existent `quote_id` fails (QueryException), and force-deleting a quote cascades its legacy member and crosswalk rows.
- sqlite proves the schema state after Laravel's table-rebuild `change()`, plus FK enforcement. It can't prove that MariaDB's `CHANGE ... NULL` keeps the FK or the real ON DELETE. Those are covered by B1 steps 4 and 6 and the B0/B1 `SHOW CREATE TABLE` comparison.

**B14.4 — Phase 4 name collision on `project_id` (Verifier note 5).** No Phase 1 change; not a human decision.
- `PlanCrosswalkController` and the plan-crosswalk and project-workspace views already use a request or query parameter called `project_id` that **holds a quote id** (`PlanCrosswalkController.php:31-33,62`).
- **Phase 4 step 10 addition:** the new project-keyed routes take the project only through route-model binding `{project}` and never read a `project_id` input. The old param is retired: the index filter is renamed or dropped and validated against `Project::visibleTo`, and the view selects, labels and hidden fields (`plan-crosswalk/index.blade.php:40,96,211`, `project-workspace/show.blade.php:244`) are rewritten under distinct names. `?project_id=` on the estimate create form (step 12) means a **projects.id** and is the only new use.
- **Regression risk 13:** a stale bookmark or form that sends a quote id as `project_id` must never be resolved as a projects.id. Step 16 test: a crosswalk request carrying `project_id=<quote id>` is ignored or rejected.

**B14.5 — Documentation fixes applied in this pass (no code).** Step 3 moved after 2f, with a run-order note; "55 passed" restated as the pre-Phase-1 baseline (step 3, risk 9); B10 status and line refs marked historical; "one release" replaced by the B6 split; A3's garbled sentence repaired; A1's active-row org wording aligned with B4; the unneeded `PermissionServiceTest` stub note removed from step 3 and the file-list entry marked Phase 3.

**Recorded, no change (Verifier notes 2 and 6):** `sharedLock()` is unproven on sqlite and is covered only by B1 step 5, which is already declared. An active row's `org_id` could differ from the project's only if `projects.org_id` were changed later. Nothing does that, and Phase 4 `ProjectController::update` must not accept `org_id`.

**Tasks added:** step 2f (Developer, B14.2). Step 3 tests (QA): B14.1, the B14.2 test, B14.3. Phase 4: B14.4. Phase 1 exit criterion 1 (B13) additionally requires the B14.1-B14.3 tests green and the mutation proofs recorded in PROGRESS.md.

## Amendment 3 (2026-09-24) — Phase 2 specification: `php artisan projects:backfill`

This replaces the step 4 bullets. It folds in A1 (the backfill never calls `enrol()`), A4 (rollback), B4 with the human's Option A decision, B5, B11 and the committed models (8363ed3). Every assumption below was checked against the live code on 2026-09-24. **It refines the approved scope; it is not a scope change.** It needs the human's acknowledgement at Checkpoint 2 for Phase 2. The human has authorized development and sqlite testing only. Running it on production stays behind B1, B5, B12 and a test-restored backup.

### C1 — Verified facts the spec depends on
| Fact | Where |
|---|---|
| `quotes` columns used: `id`, `user_id` (FK users, **cascade**), `name` (string, nullable), `project_name` (string, nullable), `project_address` (**text**, nullable), `quote_number` (string, unique, not null), `created_at`, `updated_at`, `deleted_at` (SoftDeletes), `project_id` (nullable, FK restrict) | `2026_01_16_122033:15-26`, `2026_05_29_000001:12`, `2026_09_24_000002`; `Quote` uses SoftDeletes |
| `quotes.status` is an enum of `draft/completed/sent`. The backfill doesn't read it | `2026_01_16_122033:21` |
| Both creation paths copy the estimate label into `project_name` when `project_name` is empty, and vice versa. So `project_name == name` is **ambiguous** (see H1). Create-from-list sets `project_name = list.project_name ?? list.name`, and `name = list.name` | `QuoteController.php:307-321` (store), `:631-636` (saveEditor), `:223-228` (createFromList) |
| The literal default label is `Estimate` | `QuoteController.php:311,631` |
| `user_org_roles`: `user_id`, `org_id`, `role_id`, `is_active`. It is unique on `(user_id, org_id, role_id)`, so **one user can have several rows per org**. Orgs must be counted with DISTINCT | `2026_06_25_000005:18-30` |
| `organizations` has no status column and no soft delete | `2026_06_25_000001`, `Organization.php` |
| Legacy `project_members` rows come only from `OrgAdminController::addProjectMember` (`:585-619`). They have `quote_id` set, `project_id` NULL, `org_id` = the admin's current org, `granted_by`, `granted_at` and `is_active`. The legacy `unique(quote_id,user_id)` allows **at most one row per (quote, user)**. Removal sets `is_active=false` (`:623-629`). Nothing else writes the table | grep |
| `plan_crosswalk` columns: `org_id`, `quote_id` (now nullable), `project_id` (nullable), `plan_line_code`, `product_id`, `manufacturer_part_number`, `description`, `notes`, `created_by`, `updated_by`, timestamps. There is no unique on the code | `2026_09_01_000001`, `2026_09_24_000004` |
| `Project` (SoftDeletes; `fillable` has no `created_at`/`deleted_at`); `ProjectMember::enrol()` with its A6/B4 guards | committed 8363ed3 |
| `app/Console/Commands` is auto-discovered (`Application::configure()` → `withCommands()`) | `vendor/.../Foundation/Application.php:245`, `ApplicationBuilder.php:318` |
| The PHP `intl` extension is **not** installed locally, and production is unknown. `mbstring` is installed | `php -m` |
| DB collation defaults to `utf8mb4_unicode_ci`, which is case- and accent-insensitive and PAD SPACE. Production is confirmed by Q11/Q17 | `config/database.php:54-55,74-75` |
| The RBAC test stub `quotes` table has **no** `project_address` column | `RbacTestCase.php:58-68` |

### C2 — Command signature, preconditions, exit codes
`php artisan projects:backfill {--dry-run} {--overrides= : path to the overrides CSV} {--force : skip the production confirmation} {--allow-live : real run without maintenance mode (H3, C14.9)}`

**Preconditions.** The command checks these before any write. If any fails, it exits **2** with nothing written.
1. The schema is complete: table `projects` exists; `quotes.project_id`, `project_members.project_id` and `plan_crosswalk.project_id` exist; `project_members.quote_id` **and** `plan_crosswalk.quote_id` are nullable; and every column the command reads exists (`quotes`: `id, user_id, name, project_name, project_address, quote_number, created_at, updated_at, deleted_at`; `project_members`: `quote_id, project_id, user_id, org_id, granted_by, granted_at, is_active`; `plan_crosswalk`: `org_id, quote_id, project_id, plan_line_code`; `user_org_roles`: `user_id, org_id, is_active`), and every column it writes (`projects`: `org_id, name, status, address, bid_due_at, created_by, created_at, updated_at, deleted_at`; `project_members`: also `created_at, updated_at`; `quotes.project_id`) (N8). Check with `Schema::getColumns()` (C14.4). This is the B11 hazard. The message says "schema incomplete; follow B5/B11". The command never runs migrations and never writes to `migrations`.
2. The overrides file, if given, passes validation in full (C5).
3. If `app()->environment('production')`, the operator confirms, unless `--force` is given. Under `--no-interaction` the confirm returns "no" and the command exits 2, so **every documented production invocation is `--force --no-interaction`** (C14.7). The prompt warns that `--dry-run` holds row locks for the whole run (C8).
4. *(H3, provisional default; C14.9)* A real (non-dry-run) run refuses with exit 2 unless `app()->isDownForMaintenance()` or `--allow-live` is given. `--dry-run` only prints a warning when the app is live.

**Exit codes:**
- **0:** the run completed, `unresolved = 0` and `errors = 0`.
- **1:** the run completed, but `unresolved > 0` or `errors > 0`.
- **2:** refused before any write (a precondition failed, or the operator declined).
- **3:** dry-run verification failed; DB counts changed (C8). This should never happen, and it is critical.

The runbook requires the real run to exit **0**.

### C3 — Normalization (identical in the command and the tests; never done in SQL)
`normalize(?string $s): string`:
1. `null` becomes `''`.
2. Trim Unicode whitespace at both ends: `preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $s)`.
3. Collapse every internal run to one space: `preg_replace('/[\s\p{Z}]+/u', ' ', …)`.
4. Lowercase with `mb_strtolower(…, 'UTF-8')`.

If `preg_replace` returns `null` (invalid UTF-8), the value counts as **unnamed**, and a warning is recorded (`warnings.csv`).
**Deliberately not done:**
- Accent folding: `Café` and `Cafe` are different keys.
- Unicode NFC normalization: it needs `intl`, and results must not depend on an extension.
- SQL `LOWER`/`TRIM`/`GROUP BY` on names. MariaDB `utf8mb4_unicode_ci` treats `é = e` and `ß = ss` as equal and ignores trailing spaces, and sqlite's `LOWER` is ASCII-only. Doing all grouping in PHP makes sqlite tests and MariaDB runs give the same answer.

Consequence: the pre-flight SQL counts (Q4/Q5/Q16) are estimates. The command's `groups.csv` is authoritative. Merging accent variants is a job for the overrides CSV.

**Unnamed** means `normalize(project_name)` is `''` or `'estimate'`, **or** (H1, provisional) equals `normalize(name)`.

### C4 — The algorithm, in exact order
**Scope:** `Quote::withTrashed()->whereNull('project_id')`. Quotes that are already attached are never touched, re-grouped or moved (Q5).
**Iteration:** process owner by owner. Owners are `SELECT DISTINCT user_id FROM quotes WHERE project_id IS NULL ORDER BY user_id`. For each owner:
1. **Load** the owner's unattached quotes (withTrashed, only the C1 columns). Also load:
   - the owner's active distinct orgs (`user_org_roles`, `is_active=1`, DISTINCT `org_id`) and **all** orgs the owner has any row in;
   - the legacy `project_members` rows for those quotes (`quote_id IN …`, `project_id IS NULL`);
   - the `plan_crosswalk` rows for those quotes (`project_id IS NULL`);
   - the owner's **attached** quotes, joined with `projects.org_id` and `deleted_at`, for the sibling lookup in step 4.
2. **Resolve the org for each quote**, first match wins:
   - (i) An override `org_id` (C5).
   - (ii) The owner has exactly one active distinct org.
   - (iii) The owner has more than one active org. If the quote has at least one legacy member row, and **all** of them (active or inactive) have the same `org_id`, and that org is one of the owner's active orgs, use it.
   - (iv) Otherwise the quote is **unresolved**. Reasons: `no_active_org` (zero active orgs and no override) or `multi_org_no_consensus`. Unresolved quotes are skipped, along with their member and crosswalk rows.
3. **Group key** *(C14.2)* = the typed tuple `(org_id, owner user_id, kind, value)`, where `(kind, value)` is, first match wins:
   - `('named', normalize(override project_key))` when the override row has a non-empty `project_key`;
   - `('singleton', quote.id)` if the quote is unnamed (C3);
   - otherwise `('named', normalize(project_name))`.

   The kind is part of the key, so a quote whose name normalizes to `#q5` can never collide with quote 5's singleton, and an override key never produces a singleton. In reports `group_key` is printed as `named:<value>` or `singleton:<id>`. An override key goes through the same normalization, so key `main st` merges into the `Main St` group. Because the owner is part of the key, **overrides can't merge across owners**, which follows the decided Q2.
4. **Sibling lookup (idempotency).** *(C14.1, C13.1, C14.3)* Compute the same typed key for each of the owner's **attached** quotes (trashed ones included), but only those whose project has `projects.created_by = owner`. The org part of the key is `projects.org_id`, never an override `org_id`. The name part follows the same order as step 3, so an override row **that names an attached quote** contributes its `project_key` to that quote's sibling key. That is the only use an override has on an attached quote: it never re-attaches, moves or renames it (Q5). That gives a map from key to the set of `project_id`s. For each new group:
   - no entry: **create** a project;
   - exactly one live project: **reuse** it;
   - more than one project: the group's quotes are unresolved with `ambiguous_existing_project`;
   - exactly one **trashed** project, and the group has at least one live quote: unresolved with `sibling_project_trashed`, so a user's delete is never silently undone. The human resolves it with an override `project_key`.
   - exactly one trashed project, and the group's quotes are all trashed: reuse it.

   **Operator rule:** pass the **same** overrides file on every run, including the verification run and the second dry-run (C14.12, C14.13), whose SHA-256 in `summary.txt` must match the real run's. Without it the sibling keys of override-attached quotes revert to their names, and a later straggler can be reported as `ambiguous_existing_project` (safe, never a wrong join).
5. **Plan the project attributes** (create only; a reused project is never changed):
   - **Ordering rules used below** *(C14.3)*: "most recently updated" orders by `updated_at` desc with NULL last, then `created_at` desc with NULL last, then `id` desc. "Earliest" orders ascending with NULL last, then `id` asc. A NULL is never chosen while a non-NULL exists. `$runAt` is `now()` captured **once** at the start of the run and used wherever this spec says `now()`, so a run is internally consistent.
   - `name`: an override `project_name` wins. If two rows in one group give different names, validation fails (C5). Otherwise, from the most recently updated quote in the group whose `project_name` isn't unnamed, take its trimmed `project_name`, as stored (only trimmed, case kept). For a singleton or a key group with no named quote, use the most recent quote's trimmed `name` if `normalize(name)` isn't `''`/`'estimate'`. Otherwise use `Untitled project (<quote_number of the lowest id>)`. Truncate to 255 characters.
   - `address`: the trimmed `project_address` of the most recently updated quote (ordering rules above) that has a non-empty trimmed one. If there is more than one distinct `normalize(address)`, list it in `address_conflicts.csv`. No address gives `NULL`. *(C15.8, H6 decided)* An address that isn't valid UTF-8 counts as empty for choosing the address and for `address_conflicts.csv`, and is **reported as a warning**: `warnings.csv` row `normalize, <quote id>, project_address_invalid_utf8`. It is emitted for every unattached quote in scope with such an address, before org resolution, so it also covers quotes that end up unresolved.
   - `status='active'`, `bid_due_at=NULL`, `created_by` = the owner.
   - `created_at` = the earliest non-NULL quote `created_at`, or `$runAt` if every one is NULL. `updated_at` = `$runAt`.
   - `deleted_at` = the latest non-NULL quote `deleted_at` if **every** quote in the group is trashed (`deleted_at` non-NULL). Otherwise `NULL`.
   - Groups within one owner are processed in ascending order of their lowest quote id; quotes within a group are ordered by id ascending wherever a list is written.
6. **Plan the memberships** for project P, org O (B4 Option A, and A1: `enrol()` is **not** used). The sources are:
   - the owner: `org_id = O`;
   - every legacy row of the group's quotes.

   Legacy rows are filtered per B4. A row with `org_id = O` is eligible. A row with `org_id ≠ O` is **re-homed** to O if the user has an active `user_org_roles` row in O; it is listed in `membership_rehomed.csv`. Otherwise it is **not carried** and is listed in `membership_org_mismatch.csv`.

   Then, per user:
   - **Active** if any eligible source row is active.
   - The **owner** is active unless they have at least one legacy row for the group's quotes (in any org) and all of them are inactive. Explicit removal wins, and it is reported as `owner_explicitly_removed`.
   - `granted_by`/`granted_at` come from the earliest **active** eligible row (ordering rules above: `granted_at` asc NULL last, then row `id`). If none is active, they come from the earliest eligible row by the same order. For an owner with no row: `granted_by=NULL` and `granted_at` = the group's earliest non-NULL quote `created_at`, or `$runAt` if all are NULL. A row's own NULL `granted_at` is copied as NULL.
   - `org_id = O` **always**. Invariant: every row the backfill writes has `project_members.org_id = projects.org_id`.
   - For a **reused** P, insert only users with no `(P, user)` row. Existing rows are left untouched (`existing_untouched`).
   - *(C15.2)* Members are inserted and reported in ascending order of their lowest source legacy row id; an owner with no source row comes last; ties are ordered by user id.

   Users whose legacy row already has `org_id = O` but who no longer hold an active role in O are carried as they are. The role check still denies them, and they are flagged with `member_active_in_org=no`. Legacy rows are never modified.
7. **Plan the crosswalk:** for each row of the group's quotes where `project_id IS NULL`:
   - If `org_id = O`, **link** it (`project_id = P`).
   - If `org_id ≠ O`: **H2, provisional default:** don't link, and report it as `org_mismatch`.
   - If more than one row in P ends up with the same `plan_line_code`, report it as `duplicate_code`. All of them are linked, and nothing is deleted.
8. **Write each group in its own `DB::transaction`** (C8):
   - `SELECT id FROM quotes WHERE id IN (…) AND project_id IS NULL FOR UPDATE`. If the set differs from the plan, the group fails with `concurrent_change`.
   - Insert the project with `DB::table('projects')->insertGetId([...])`, so the explicit `created_at`/`deleted_at` are kept and no model events fire. Or reuse it.
   - `DB::table('quotes')->whereIn('id', …)->whereNull('project_id')->update(['project_id' => P])`. Query builder only, so **`quotes.updated_at` doesn't change**.
   - Insert members with `DB::table('project_members')->insert` (`created_at`/`updated_at` = now).
   - Update the crosswalk with `DB::table('plan_crosswalk')->…->update(['project_id' => P])`, so `updated_at` doesn't change.

   If a group throws: its transaction rolls back, a row goes to `errors.csv`, and the run continues. A lost connection aborts the run (exit 1), and a re-run is safe (C6).
9. The run never writes `rbac_audit_logs`, `user_org_roles`, `organizations`, or any legacy member row.

**NEW HUMAN DECISIONS. Don't treat them as final.**
- **H1: is `project_name` equal to the quote's own `name` a real project name?** Evidence: `QuoteController.php:307-321,631-636` copy one field into the other when either is empty, so equality can mean "label only" (for example `Base bid`) or "project name only" (for example `Main St`).
  - *Provisional default (recommended): treat it as unnamed (a singleton).* Splitting a real project can be fixed with an override key and never widens access. Merging different real projects that happen to share a label widens membership across them.
  - The command also writes `candidate_merges.csv`: the groups that option B would have formed. The human can turn those into overrides. *(C15.1)* Exactly: per owner and org, every bucket keyed by normalized name that contains at least one quote made unnamed only because `project_name` equals `name`, including a bucket of a single quote. The bucket also lists ordinary named quotes with the same normalized name, so the human sees what option B would merge. Quotes that have an override `project_key` are excluded.
  - *Option B:* treat it as named and group it.
  - **Needs production data: Q16.** If most quotes are like this, option A means many overrides.
- **H2: crosswalk rows whose `org_id` differs from the project's org.**
  - *Provisional default (recommended): don't link, and report,* by analogy with B4.
  - *Alternative:* link them anyway.
  - Expected volume is 0 (Q7: the store path can't succeed today).

### C5 — Overrides CSV
The file is UTF-8, with a leading BOM stripped if present. It is comma-separated with RFC 4180 quoting and a **required** header: `quote_id,org_id,project_key,project_name`.
- `quote_id` is required and must be an integer. The quote must exist (withTrashed). A duplicate `quote_id` is **fatal**. *(C14.1)* If the quote is already attached, its row is **not** used to attach or move it. Its `project_key` (if any) is used only for the sibling key (C4 step 4). Its `org_id` and `project_name` are ignored. *(C14.12, N2)* The row gets a `warnings.csv` line (`source=overrides`, message `attached_quote_org_or_name_ignored`) **only when** its `org_id` is non-empty and differs from `projects.org_id`, or its `project_name` is non-empty and its `normalize()` differs from `normalize(project.name)`. Values that agree with the project produce no warning, so re-passing the same file on a later run is silent. A row with only a `project_key` produces no warning.
- `org_id` is optional. If given for an **unattached** quote, the owner must have **a** `user_org_roles` row in that org, active or not. Otherwise it is **fatal** (`org_without_role`). *(C15.7)* For an **attached** quote the `org_id` is ignored (or warned about, above), so no role check is made.
- `project_key` is optional. It is normalized with C3, and a non-empty key replaces the `named` value of the group key (an override key can never produce a singleton).
- `project_name` is optional. It sets the created project's name. Rows in the same group with different non-empty names are **fatal**.
- A row with all three optional columns empty is **fatal**.
- *(N7/N11, decided: fatal, not "absent")* A `project_key` cell that is not empty but normalizes to `''` (whitespace only, NBSP, tabs) is **fatal**. Treating it as absent would silently group the quote by name against the operator's evident intent, and a `named:` group with an empty value would merge unrelated quotes. An empty cell (zero characters) is absent. The same trim rule applies to `org_id`, `quote_id` and `project_name` cells: they are trimmed with C3 step 2 first, and a non-empty cell that is blank after trimming is fatal.
- Any fatal error → exit 2 with nothing written. Every fatal row is listed on the console by **line number and reason code only**, never with cell values (they are client text). *(C15.3)* `line` is the CSV record number: the header is record 1 and blank lines are counted; it equals the physical line unless a cell contains a newline.
- *(C15.4)* An override cell that isn't valid UTF-8 is **fatal** (`invalid_utf8`). Two override `project_name` values in one group conflict when they differ after trimming, compared **exactly** (case and accents count), and are then fatal (`conflicting_project_name`).
- *(C15.6)* Override-name conflicts are found by a read-only planning **pre-pass over all owners**, before the production confirm and before any write. This doubles the read cost of a run and discards the pre-pass warnings (the real pass reports them). The dry-run on the B1 copy sizes it.
- *(C15.7)* Attached-quote override warnings (above) are emitted while processing the owner of the attached quote, so they appear only for owners that still have unattached quotes.

**Worked example.** Owner 42 has active roles in orgs 7 and 9. Unattached quotes:
- 101 `Main St`
- 102 `main  st `
- 103 `Main St`, which is really phase 2
- 104 project_name `Estimate`
- 105 `Base bid`, with name `Base bid`

```
quote_id,org_id,project_key,project_name
101,7,,
102,7,,
103,7,main st phase 2,Main St – Phase 2
104,7,main st,
```
Result:
- 101, 102 and 104 go into one project in org 7, key `main st`. Its name is the trimmed `project_name` of whichever of 101/102 was updated most recently.
- 103 goes into its own project, `Main St – Phase 2`.
- 105 has no override and owner 42 has 2 active orgs. Its legacy member rows (if any) decide under (iii). Otherwise it goes to `unresolved.csv` as `multi_org_no_consensus`, and the run exits 1.

**Straggler run *(C14.1)*.** Run 1 attaches 101, 102, 104 to P1 (key `named:main st`, org 7) and 103 to P2 (key `named:main st phase 2`). Run 2 uses the **same file**. Sibling keys of the attached quotes are computed as: 101 and 102 by name, `named:main st`; 104 by its override key, `named:main st`; 103 by its override key, `named:main st phase 2`. The map is therefore `named:main st -> {P1}` and `named:main st phase 2 -> {P2}`.
- A new straggler 106, `Main St`, resolves to key `named:main st`, finds exactly P1 and joins it. There is no `ambiguous_existing_project`.
- A new straggler 107 with an override row `107,,main st phase 2,` joins P2.
- A new straggler 108 named `Main St - Phase 2` (with a hyphen or en dash) has key `named:main st - phase 2`, which matches nothing, so it creates a **new** project. Normalization never folds punctuation, so the human must add an override key for it. The report `groups.csv` shows it as `created`.
- If run 2 omits the file, 103's sibling key reverts to `named:main st`, that key maps to {P1, P2}, and 106 is reported `ambiguous_existing_project` and stays unattached (safe, nothing wrong is joined).

### C6 — Idempotency keys
| Object | Key | Second run |
|---|---|---|
| quote attachment | `quotes.project_id IS NULL` (scope, and again in the `UPDATE … WHERE`) | 0 updates |
| project | the sibling lookup (C4 step 4) on `(project.org_id, owner, K)` | 0 creates. Stragglers join the existing project |
| membership | `(project_id, user_id)` exists, so it is untouched | 0 inserts |
| crosswalk | `plan_crosswalk.project_id IS NULL` | 0 updates |

**Acceptance:** with the same overrides, a second run prints zeros for created, attached, inserted and linked, and exits 0.

### C7 — Reports
They go to `storage/app/backfill/projects-entity/<UTC Ymd_His>[-dry-run]/`. That path is private and not under `public`. The files contain user ids, project names and addresses: treat them as client data. Format: UTF-8, a header row, `fputcsv`, `\n` line endings, datetimes as stored (`Y-m-d H:i:s`), and lists as ids separated by spaces.

| File | Columns |
|---|---|
| `groups.csv` | `project_id, action (created/reused), org_id, org_resolution (override/single_org/membership), owner_user_id, key_source (name/override/singleton), group_key (`named:<value>` or `singleton:<id>`), project_name, address, created_at, deleted_at, quote_count, trashed_quote_count, quote_ids, quote_numbers` |
| `unresolved.csv` | `quote_id, quote_number, owner_user_id, reason (no_active_org/multi_org_no_consensus/ambiguous_existing_project/sibling_project_trashed/concurrent_change), owner_active_org_ids, legacy_member_org_ids` |
| `memberships.csv` | `project_id, user_id, org_id, action (inserted_active/inserted_inactive/existing_untouched/owner_explicitly_removed), is_owner, member_active_in_org, source_row_ids, granted_by, granted_at` |
| `membership_widening.csv` | `project_id, user_id, gained_quote_ids` (the project's quotes where the user, not being the owner, had no active legacy row, for each **active** inserted member) |
| `membership_rehomed.csv` | `legacy_row_id, quote_id, user_id, legacy_org_id, legacy_is_active, project_id, project_org_id` |
| `membership_org_mismatch.csv` | `legacy_row_id, quote_id, user_id, legacy_org_id, legacy_is_active, project_id, project_org_id, user_active_org_ids` |
| `crosswalk_conflicts.csv` | `crosswalk_id, quote_id, project_id, plan_line_code, org_id, project_org_id, type (duplicate_code/org_mismatch), linked (yes/no)` |
| `address_conflicts.csv` | `project_id, chosen_address, quote_id, quote_address` |
| `candidate_merges.csv` (H1) | `org_id, owner_user_id, normalized_name, quote_ids` |
| `warnings.csv` | `source (overrides/normalize), line_or_quote_id, message` |
| `errors.csv` | `owner_user_id, group_key, quote_ids, exception_class, message` |
| `overrides.csv` | a **byte-for-byte** copy of the input file, if one was given (C13.8). It is the only file exempt from formula escaping, because its job is to be replayed as the next run's input (a `'` prefix would corrupt it). It is never opened in a spreadsheet by the tooling; the operator must treat it as text only. Mode 0600 |
| `summary.txt` | the console summary, including the SHA-256 of the overrides file, computed once over the raw input bytes (before BOM stripping or parsing) and equal to the SHA-256 of `overrides.csv`. It has no client text, so it needs no escaping |

In a dry run, `project_id` values are the ones assigned inside the rolled-back transaction, so they are discarded. `summary.txt` says so.
**Report file handling (C14.6).**
- **Client data.** The files hold user ids, project names, addresses and override text. `storage/app/backfill/` is already untracked (`storage/app/.gitignore` is `*`). Never `git add -f` them, never attach them to a ticket, and never upload them to a public path.
- **Permissions.** The directory is created with mode `0700` and each file with `0600` (`mkdir(..., 0700)`, `chmod`). The command sets `umask(0077)` only around report writing and **restores the previous umask in a `finally`**, so later code and tests aren't affected.
- **CSV writing.** Use `fputcsv($h, $row, ',', '"', '')`: an **empty escape character**, so a backslash before a quote is not mangled. Row order inside every file except `groups.csv` is undefined, and tests must not assert it; `groups.csv` is ordered by owner id then lowest quote id (C4 step 5).
- **No logging of values.** The command writes names, addresses and exception messages only to the report files, never to `storage/logs` or the console (the console shows counts, ids and file paths). `errors.csv` may contain data in an exception message and is client data too.
- **Formula injection.** In every report cell of the CSV files **except `overrides.csv`** that comes from client text (names, addresses, override text, exception messages, warnings), if the value starts with `=`, `+`, `-`, `@`, a tab or a carriage return, prefix it with a single quote `'`. This applies to report files only, never to DB values. Numeric ids and dates are written as they are. Header names are fixed.
- **Retention (H5, HUMAN/CLIENT DECISION).** Recommendation: keep the run's reports with the pre-migration backup until the Phase 5 gate passes, then delete them, and record who holds them in PROGRESS.md. The retention period and who may read them are not decided here.
**Console summary**, one line each:
- mode (real/dry-run);
- quotes scanned, owners;
- projects created and reused;
- quotes attached;
- members inserted, active and inactive;
- rehomed, org-mismatch, widening users;
- crosswalk linked, duplicate codes, org mismatches;
- address conflicts, candidate merges;
- unresolved, errors, warnings;
- **unlinked crosswalk rows** (`org_mismatch`, H2), printed on their own line even when 0 (C14.10);
- mode gates: whether the app was in maintenance mode, and the SHA-256 of the overrides file;
- report directory;
- duration and peak memory;
- exit code.

### C8 — Transactions, dry-run, locking, memory
- **Real run:** one transaction per group (C4 step 8). There is no run-wide transaction, so a failure part-way leaves only complete groups. A re-run continues from there (C6).
- **`--dry-run`:**
  - The whole run is wrapped in one outer transaction (`DB::beginTransaction()`), and each group's `DB::transaction` becomes a savepoint.
  - Before starting, the command records 4 counts: `projects` (withTrashed), `project_members`, `quotes WHERE project_id IS NOT NULL` and `plan_crosswalk WHERE project_id IS NOT NULL`.
  - At the end it **always** calls `rollBack()` (in a `finally`), re-reads the 4 counts, and exits **3** if any of them changed.
  - The code path is identical to the real run, and there is no DDL (DDL would commit implicitly on MariaDB).
  - The only side effects outside the DB are the report files. InnoDB AUTO_INCREMENT counters for `projects` and `project_members` still advance, which is harmless.
  - Dry-run locks every attached quote row and every inserted row until the end. So **run it on the B1 restored copy**; that is safe, because everything is rolled back and only reports are written. On live production it must run in maintenance mode only.
- **Memory:**
  - It works one owner at a time. The owner list holds integers only. Per owner it loads only the C1 columns.
  - It releases per-owner arrays after each owner, and the query log stays off (don't call `enableQueryLog`).
  - The expected peak is bounded by the largest owner's quote count.
  - Record `memory_get_peak_usage(true)` and the duration in the summary.
  - **The dry-run on the B1 restored copy is the performance test.** Its duration sizes the release window (B6). If the peak goes above 256 MB, route it to the Architect.
- **Time:** roughly 6-8 queries per group plus 5 per owner. Run with `--force --no-interaction` inside `php artisan down` (C14.7, C14.9). If the web front-end has a CLI timeout, use `nohup` or `screen`.

### C9 — Rollback (restates A4 for Phase 2)
This is valid only **before** any Phase 4 code has written native data.
1. Backup.
2. `php artisan down`.
3. In one transaction, in this order:
   1. `DELETE FROM project_members WHERE quote_id IS NULL AND project_id IS NOT NULL;`
   2. `UPDATE plan_crosswalk SET project_id = NULL WHERE quote_id IS NOT NULL;`
   3. `UPDATE quotes SET project_id = NULL;` (raw SQL doesn't touch `updated_at`)
   4. `DELETE FROM projects;` (this removes trashed ones too)
4. Verify that `SELECT COUNT(*) FROM projects` is 0, that `project_members WHERE quote_id IS NULL` counts 0, and *(C14.8, as A4)* that `plan_crosswalk WHERE quote_id IS NULL` counts 0. Also check `SELECT COUNT(*) FROM quotes WHERE project_id IS NOT NULL` is 0 and `plan_crosswalk WHERE project_id IS NOT NULL` is 0.
5. Only if the schema itself must go too, `migrate:rollback --step=5` (A4 and B7 order).
6. `php artisan up`.

After Phase 4 data exists, the only rollback is restoring from backup. The backfill's own reports list every id it created, if a partial manual reversal is ever needed.

### C10 — Production data still needed (read-only; add to pre-flight)
```sql
-- Q16 (for H1) quotes whose project_name equals their own name (named, and not 'estimate')
SELECT COUNT(*) total,
 SUM(COALESCE(TRIM(project_name),'') <> '' AND LOWER(TRIM(project_name)) <> 'estimate'
     AND LOWER(TRIM(project_name)) = LOWER(TRIM(name))) same_as_name
FROM quotes WHERE project_id IS NULL;
-- Q17 collation of the name columns (the PHP-side normalization vs SQL estimates)
SHOW FULL COLUMNS FROM quotes WHERE Field IN ('name','project_name','project_address');
```
The others still apply:
- Q1: volume and memory.
- Q2 and Q16: H1.
- Q3/Q8: override volume.
- Q5: impact of the Q2 decision.
- Q6: B4 re-home/mismatch volume. Its second query is the mismatch estimate.
- Q7: H2.
- Q9: "Test 1".

All of these, plus the dry-run on the B1 copy, come **before** the real run. Phase 2 can be developed and tested now without them.

### C11 — Files the Developer may create or modify (Phase 2)
- **Create** `app/Console/Commands/BackfillProjects.php`. It handles I/O: the options and preconditions (C2), overrides parsing and validation (C5), the per-owner loop, transactions and dry-run (C8), the writes (C4 step 8), the reports and summary (C7), and the exit codes.
- **Create** `app/Support/ProjectBackfillPlanner.php`. It is read-only planning logic: `normalize()` (C3), org resolution, grouping and the sibling lookup, the attribute rules, and the membership and crosswalk decisions (C4 steps 2-7). It follows the existing `app/Support/QuotePdfPresenter.php` pattern and does no writes.
- **Forbidden (C14.11):** any change to the Phase 1 models or migrations; any Eloquent write (`create`, `save`, `update`, `insert` through a model) on `quotes`, `plan_crosswalk`, `project_members` or `projects`; calling `ProjectMember::enrol()`; any legacy `project_members` write; any option beyond the C2 signature; any new config file or config key.
- **Nothing else.** In particular: no migration, model, controller, route, view or config change, and nothing in `PermissionService`, `RbacAudit`, `route_permission_map.php` or `RbacTestCase.php`. The committed models are used as they are, and `ProjectMember::enrol()` is **not** called.
- QA creates `tests/Feature/Rbac/ProjectBackfillTest.php`, which extends `RbacTestCase`. Its `setUp` adds `quotes.project_address` (text, nullable) with `Schema::table`, because the stub lacks it. Temporary CSVs go in a temp directory, and report output goes to a temporary `storage` path.

### C12 — Test cases (QA, sqlite; each asserts the relevant report rows and the exit code)
A shared helper runs after every test that writes. It asserts the **invariant** (`SELECT COUNT(*) FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.org_id <> p.org_id` is 0) and that legacy member rows are byte-identical.
- **Grouping**
  1. `Main St`, ` main   st `, `MAIN ST` from one owner in one org give 1 project. Its name comes from the most recently updated quote, and `created_at` is the earliest.
  2. Two owners with the same name in the same org give 2 projects (Q2).
  3. Unicode: `Café` and `Cafe` give 2 projects. `ÉTÉ` and `été` give 1. An NBSP or a tab collapses. Invalid UTF-8 counts as unnamed and gets a warning.
- **Unnamed and fallbacks**
  4. `NULL`, `''`, `Estimate`, ` estimate ` each give a singleton. The name is `quote.name`, or `Untitled project (<quote_number>)` when the name is empty or `Estimate`.
  5. H1 provisional: `project_name == name` gives a singleton, plus a row in `candidate_merges.csv`.
- **Org resolution**
  6. A single active org, with 2 role rows in the same org, counts once.
  7. A multi-org owner with member consensus resolves to that org. With no rows, or rows split across orgs, the quote goes to `unresolved.csv` with `multi_org_no_consensus`, and the exit code is 1.
  8. A zero-active-org owner (inactive roles only) gives `no_active_org`. An override to an org where the owner has an inactive row is accepted. An override to an org where the owner has no row exits 2 with nothing written.
- **Overrides**
  9. Merge (a key into a named group), split (a new key), name override, and a BOM file all work. A conflicting `project_name` in one group, a duplicate `quote_id`, an unknown quote, an all-empty row, a whitespace-only `project_key`, and a missing header each exit 2 with nothing written (line number and reason code only on the console). An override on an already-attached quote never attaches or moves it; a `project_key` on it feeds the sibling key only, and an ignored `org_id`/`project_name` on it gives `attached_quote_org_or_name_ignored` (C14.1).
- **Attributes**
  10. An all-trashed group gives a trashed project with `deleted_at` = the latest. A mixed group gives a live project. Soft-deleted quotes are included.
  11. Address: the most recent non-empty address is chosen; whitespace-only is ignored; different addresses are listed in `address_conflicts.csv`.
  12. `status=active`, `bid_due_at` NULL, `created_by` = the owner, and **`quotes.updated_at` is unchanged** after attaching.
- **Membership**
  13. Union of the owner and the legacy rows: active if any row is active, with `granted_*` from the earliest active row. An owner with no row gets `granted_by` NULL and `granted_at` = the earliest quote `created_at`.
  14. The widening report: a member of quote A only, where A and B are in one project, is listed with `gained_quote_ids = B`.
  15. Explicit owner removal: an owner with only inactive legacy rows is inserted inactive, as `owner_explicitly_removed`.
  16. B4 re-home: a row in org 9 on a project in org 7, where the user is active in 7, is inserted with `org_id=7` and listed in `membership_rehomed.csv`. If the user isn't active in 7, nothing is inserted and it is listed in `membership_org_mismatch.csv`.
  17. The invariant helper, plus a test that `enrol()` is never called. Register a `ProjectMember::creating` listener that fails if it fires.
- **Crosswalk**
  18. Rows are linked. Duplicate codes are reported, and both are linked. An org mismatch is reported and not linked (H2 provisional). The crosswalk of an unresolved quote stays NULL. `plan_crosswalk.updated_at` is unchanged.
- **Idempotency and stragglers**
  19. A second run with the same overrides reports all zeros and exits 0.
  20. A straggler quote with the same owner, name and org, plus a legacy row, joins the existing project. Only the new member is inserted, and existing rows are untouched.
  21. A key that maps to 2 existing projects gives `ambiguous_existing_project`.
  22. A live straggler whose only sibling project is trashed gives `sibling_project_trashed`, and the quote stays unattached.
- **Modes and failures**
  23. `--dry-run`: the 4 counts are unchanged, the reports go to `-dry-run/`, and the exit code follows the same rules.
  24. Group failure isolation: a sqlite trigger `RAISE(ABORT)` on inserting a project named `boom` leaves every other group committed, lists the failure in `errors.csv`, and exits 1.
  25. With the production environment and no `--force`, the operator declines and the command exits 2 with nothing written.
  26. The report headers match C7 exactly.
  27. *(mandatory, C14.4)* Schema incomplete exits 2 with nothing written, for each of: `project_members.quote_id` NOT NULL, `plan_crosswalk.quote_id` NOT NULL, `quotes.project_address` missing, `plan_crosswalk.project_id` missing.

**What sqlite can't prove.** These are covered by the dry-run and the real run on the **B1 restored production copy** (MariaDB, same version), recorded in PROGRESS.md:
- `FOR UPDATE` locking and concurrency with a live app;
- the long outer transaction in dry-run (savepoints, undo growth, locks held);
- InnoDB AUTO_INCREMENT behaviour on rollback;
- performance and peak memory at production volume;
- TIMESTAMP timezone conversion;
- how the SQL collation estimates (Q4/Q5/Q16) compare with `groups.csv`;
- real data shapes from Q0-Q9 and Q16-Q17.

**Acceptance for Phase 2 development:**
- C12 items 1-27 (27 mandatory), the C13 tests (28-33) and the C14 tests (34-48) and the C15 tests (49-59) and the C16 tests (60-62) pass, and the C13.5 and C14.5 mutation proofs are recorded in PROGRESS.md.
- `php artisan test --filter=Rbac` shows only the 3 known failures, by name.
- The Architect's compliance pass finds the implementation matches C2-C11.
- Before any production run: H1 and H2 are confirmed by the human, the Q16/Q17 data exists, and the dry-run on the B1 copy has been reviewed.

### C13 — Amendment 3 addendum (2026-09-24, Architect review of C1-C12 against live code and the committed models)

Every C1 fact was re-checked against the live code: the quotes migrations, `user_org_roles` columns, `Quote` (SoftDeletes, `project_id` fillable), the stub `quotes` table in `RbacTestCase.php:58-68` (has `name`, `project_name`, `quote_number`, `deleted_at`; **no** `project_address`, as C1/C11 already say), the empty `app/Console` directory (the command creates it), the existing `app/Support/QuotePdfPresenter.php` pattern, and `mbstring` present with `intl` absent. Two facts are unchanged: `enrol()` is not used, and `ProjectMember::enrol()`'s new trashed guard (B14.2) is irrelevant to the backfill, which writes with the query builder. The following refine C4/C8 and are **not** new human decisions.

**C13.1 — Sibling lookup only considers the owner's own projects (C4 step 4; folded into that text by C14).** The lookup uses attached quotes whose project has `projects.created_by = owner`. Without it, a straggler could join a project that another user (or, after Phase 4, a natively created project) owns, and the owner would be enrolled in it, silently widening access. Projects the backfill creates always have `created_by = owner`, so idempotency is unchanged. A straggler whose sibling is a foreign project simply creates its own project.

**C13.2 — Read raw, chunk lists (C4 steps 1 and 8).**
- Read quotes, members and crosswalk rows with the query builder (`DB::table`), not Eloquent models. Timestamps must stay the stored strings, so no timezone conversion occurs and `created_at`/`deleted_at` copy verbatim.
- Every `whereIn` over quote or row ids is chunked at **400** ids *(C15.5: 400, not 500, so that no statement exceeds 500 bindings once the `SET` or other bindings are added; multi-row inserts use 50 rows per statement)*. An owner with thousands of quotes would otherwise hit sqlite's 999/32766 parameter limits and MariaDB's `max_allowed_packet`. The 8-query-per-group estimate then applies per chunk.
- Truncate the project name with `mb_substr($name, 0, 255, 'UTF-8')` (characters, not bytes).

**C13.3 — Owner membership when the resolved org has only inactive roles.** An override may resolve to an org where the owner has only inactive `user_org_roles` (C5). The owner row is still written active (rule C4 step 6) with `member_active_in_org=no` in `memberships.csv`. Membership without an active role grants nothing (`PermissionService` checks the role first), so this is harmless and is reported for review.

**C13.4 — Exit codes in dry-run.** `--dry-run` follows the same rules as C2 (exit 1 for unresolved or errors, exit 3 for a changed count). A dry-run that exits 0 or 1 is the result the human reviews before the real run.

**C13.5 — Mutation-proof requirement (as B14.1).** For every rule below, QA disables or inverts it **on a scratch copy** (never the tracked file), confirms at least one C12/C13 test fails, and records rule, mutation and failing test in PROGRESS.md. A surviving mutant is a test gap and is routed to the Architect.
| Load-bearing rule | Mutation |
|---|---|
| Org invariant (C4 step 6) | write the legacy `org_id` instead of `O` |
| B4 re-home vs mismatch | skip the active-role check (always re-home) |
| Explicit owner removal | force the owner active |
| Second-run idempotency (C6) | drop the `project_id IS NULL` scope or the sibling lookup |
| `sibling_project_trashed` | reuse the trashed project silently |
| Sibling owner filter (C13.1) | drop the `created_by = owner` condition |
| Unnamed singleton (C3) | group unnamed quotes by name |
| Multi-org resolution (iii) | pick the first org |
| No `updated_at` bump | use Eloquent `update()` on quotes |
| Dry-run rollback (C8) | remove the final `rollBack()` (the exit-3 check must then fire) |
| Per-group transaction | remove the per-group `DB::transaction` (test 24 must fail) |
| Chunking (C13.2) | raise the chunk size to 5000 (test 31's binding-count assertion must fail) |
| H2 provisional | link mismatched crosswalk rows |
| Never calls `enrol()` | call `enrol()` (test 17's listener) |

**C13.6 — Additional tests (QA).**
- 28. A quote with the sibling rule: a straggler owned by user A whose name matches an attached quote in a project created by user B does **not** join it (C13.1).
- 29. A reused project with an existing inactive member row is untouched (no reactivation) even when a legacy row for that user is active (`existing_untouched`).
- 30. Timestamps: `projects.created_at` equals the earliest quote `created_at` string exactly, and `deleted_at` equals the latest quote `deleted_at` string exactly.
- 31. An owner with 1,200 unattached quotes in one group attaches all of them, and the test's own query log (enabled only in the test) shows no statement with more than 500 bindings. Modern sqlite's parameter limit is higher than 999, so the binding-count assertion, not a failure, is what proves chunking.
- 32. Names longer than 255 characters (multibyte) are truncated by characters and stored.
- 33. *(optional, and test 23 must already assert the 4 counts and the exit code after the run so the `rollBack()` mutation is killed there)* Dry-run verification: the exit-3 path is exercised by a test double that lets one write escape the outer transaction (for example a sabotaged connection callback), and the command exits 3. If this is impractical on sqlite, QA records why and covers it at the B1 dry-run.

**C13.7 — Human decisions (unchanged, none new).** H1 and H2 (C4) remain open with their provisional defaults. C13.1-C13.3 are refinements of an approved rule, not scope changes. **Needs production data to finalize:** H1 (Q16), the override volume (Q3, Q8), the re-home and mismatch volume (Q6), H2 (Q7), and the collation check (Q17). Nothing else is blocked.

**C13.8 — Open risks.**
- The PHP normalization deliberately doesn't fold accents, so accent variants become separate projects. That is safe (never widens access) but may create more overrides.
- Dry-run on live production holds locks for the whole run; it is restricted to the B1 restored copy or a maintenance window (C8).
- The same overrides file must be passed on every run (C4 step 4); a run without it can split a project that an earlier run merged. The console summary prints the overrides file hash, and the report directory keeps a copy (`overrides.csv`), so an operator can check.
- If the operator changes an override after a real run, existing projects are never re-grouped (Q5). Fixing that is a manual data task.

### C14 — Amendment 3 addendum 2 (2026-09-24, from the Verifier review "Phase 2 specification", F1-F11)

Each finding was checked against PLAN.md and the live code. The contradicting sentences in C2, C4, C5, C7, C8, C9, C11, C12, the Runbook and B6 step 6 were **edited in place**; this section is the index and adds the new rules and tests. **F1 was a real contradiction** (C5 said an override on an attached quote is ignored; C4 step 4 needs it for sibling keys). F2-F7 were valid. F8-F11 are notes.

| Finding | Kind | Where fixed | Human decision? | Developer file list |
|---|---|---|---|---|
| F1 blocking | refinement (resolves a contradiction) | C14.1; C4 steps 3-4; C5 bullets and worked example; C12 test 9 | no | none |
| F2 | refinement | C14.2; C4 step 3; C7 `group_key` | no | none |
| F3 | refinement | C14.3; C4 steps 5-6 | no | none |
| F4 | refinement (test) | C14.4; C2 precondition 1; C12 test 27 | no | none |
| F5 | tests | C14.5 | no | none |
| F6 | refinement (escaping, permissions) plus **H5** retention | C14.6; C7 | **H5 only** (retention/handling; not a code default) | none |
| F7 | refinement (docs) | C14.7; C2 precondition 3; C8; Runbook; B6 step 6 | no | none |
| F8 | refinement | C14.8; C9 step 4 | no | none |
| F9 | **behaviour** | C14.9; C2 precondition 4 and signature | **H3** | adds `--allow-live` to the command only |
| F10 | **behaviour** | C14.10; C7 summary | **H4** | none |
| F11 | note | C14.11 | no | none |

**C14.1 — Overrides and attached quotes (F1).** *Rule:* an override row is applied to every quote it names, attached or not, but for an attached quote it only feeds the **sibling key** (its `project_key`). It never attaches, moves, renames or re-orgs a quote, because Q5 puts moving out of scope and B4 fixes the org from `projects.org_id`. The sibling org is always `projects.org_id`. A warning is emitted only when an attached quote's override `org_id`/`project_name` disagrees with its project (C5). The worked example in C5 now covers the straggler run: 106 joins P1, 107 joins P2 by override, 108 creates a new project, and omitting the file yields `ambiguous_existing_project` rather than a wrong join. **Tests (QA, C14.5 numbers):** 34 (run 1 with a merge and a split override, where every row of the worked-example file carries `org_id` 7; run 2 with the same file, stragglers join the right projects, no ambiguity, and **zero** warnings because the overrides agree with their projects; plus one extra attached row whose `org_id` and key disagree with its project: exactly one `attached_quote_org_or_name_ignored` warning, and that quote is not moved and its sibling key uses `projects.org_id`) and 35 (run 2 without the file reports `ambiguous_existing_project` and joins nothing).

**C14.2 — Typed keys (F2).** The key is `(org_id, owner, kind, value)` with `kind` in `named|singleton`. Test 36: a quote named `#q5` and quote id 5 unnamed, same owner and org, give two projects; an override `project_key` of `#q5` gives a `named` group, not a singleton.

**C14.3 — Ordering, NULLs and clocks (F3).** All ordering rules are in C4 step 5 ("Ordering rules used below") and step 6; the sibling lookup includes trashed attached quotes; one `$runAt`. Test 37 covers NULL `updated_at`/`created_at`/`granted_at` (the non-NULL wins, ties go by id, an all-NULL group uses `$runAt`, and a NULL legacy `granted_at` is copied as NULL when it is the only source).

**C14.4 — Mandatory precondition test (F4).** Test 27 is mandatory (C12) and the precondition also checks every column the command reads.

**C14.5 — More tests and mutation entries (F5).** Tests (QA, sqlite):
- 34-37 as above.
- 38. `concurrent_change`: a listener on `Illuminate\Database\Events\TransactionBeginning` (the first group's transaction, which begins after all planning and before the `FOR UPDATE` select) attaches one of that group's quotes with `DB::table('quotes')->update(...)`. Do **not** hook any planning read: the attached-quote read in C4 step 1 would see the change and alter the plan instead. In `--dry-run` mode use a real (non-dry) run for this test, since the outer transaction fires the event too. The group's transaction then sees a different id set than planned, lists every quote of the group as `concurrent_change`, writes nothing for that group, and the exit code is 1. sqlite ignores `FOR UPDATE`, so this proves only the set comparison; locking is B1.
- 39. All-trashed group whose only sibling project is trashed: the trashed project is reused, no project is created, the quotes are attached, and no member is inserted for existing rows (reuse rule, C4 step 4).
- 40. Rule (iii) when the quote's legacy rows share one org that is **not** among the owner's active orgs: unresolved as `multi_org_no_consensus`.
- 41. Report cells starting with `=`, `+`, `-`, `@`, tab or CR get a leading `'`; the DB value is unchanged; ids are untouched; the directory mode is 0700 and file mode 0600 (skip the mode assertion on a filesystem that can't hold it).
- 42. Nothing names or addresses reach the log: with `Log::spy()` (or the log driver set to a temp file), no name or address string appears in any log record during a run that also fails one group.
- 43. H3: a real run outside maintenance mode exits 2 with nothing written; with `--allow-live` it runs; with maintenance mode active it runs; `--dry-run` outside maintenance mode runs and prints the warning. **The test never runs `artisan down` and never writes `storage/framework/down`.** It binds a fake `Illuminate\Contracts\Foundation\MaintenanceMode` (`Application::isDownForMaintenance()` resolves that contract, `Application.php:1390-1403`) whose `active()` returns a test-controlled flag, and the app is rebuilt per test, so nothing can leave the developer's app down. If a real down file is used anyway, it must be removed in `tearDown` and in a `finally`.
- 44. H4: the unlinked-crosswalk count appears in `summary.txt` and the console even when 0, and a run with only `org_mismatch` rows follows the decided exit code (provisional: 0).
- 45. `existing_untouched` and address choice: covered by tests 29 and 11; add here an assertion that an existing member row is byte-identical after a second run.
- 47. `$runAt`: a listener on each group's `TransactionBeginning` advances `Carbon::setTestNow` by one second. Every `projects.updated_at`, `project_members.created_at/updated_at` and fallback `granted_at`/`created_at` written in the run is identical (one `$runAt`).
- 48. Order: with two owners and several groups, created `projects.id` ascends with (owner id, lowest quote id of the group), and quotes/members within a group are processed in id order (the first-written member `id` follows the source-row order).
- 46. Overrides file copy: `overrides.csv` equals the input byte-for-byte **even when a cell starts with `=`** (not escaped), its SHA-256 equals `hash('sha256', <input bytes>)` including a BOM file, and that hash appears in `summary.txt`. A fatal-override run (exit 2) prints line numbers and reason codes but none of the cell values (assert on captured console output).

Mutation entries added to C13.5 (each must be killed, recorded in PROGRESS.md, run on a scratch copy):
| Rule | Mutation | Killed by |
|---|---|---|
| C2 precondition | remove the schema check | 27 |
| Override on attached quote | ignore `project_key` for sibling keys | 34 |
| Override on attached quote | let an override attach or move an attached quote | 9, 34 |
| Sibling org | use the override `org_id` (not `projects.org_id`) in the sibling key | 34 |
| `$runAt` | call `now()` per write instead of one `$runAt` | 47 |
| Group order | iterate groups or owners in descending order | 48 |
| Warning rule (N2) | warn on every attached override row | 34 |
| Empty key (N7) | accept a whitespace-only `project_key` | 9 (add the fatal case) |
| Escape char | use the default `fputcsv` escape | 41 (a value containing `\"`) |
| umask | don't restore the umask | 41 (assert `umask()` after the run) |
| Typed key | compare only `value` (string keys) | 36 |
| Ordering/NULL | treat NULL `updated_at` as newest | 37 |
| `concurrent_change` | remove the set comparison | 38 |
| Trashed reuse | never reuse a trashed project | 39 |
| Rule (iii) | accept a legacy org that isn't an active org | 40 |
| Escaping | remove the `'` prefix | 41 |
| Log hygiene | log the project name | 42 |
| H3 guard | remove the maintenance check | 43 |
| `existing_untouched` | update existing member rows | 20, 29, 45 |
| Address choice | choose the oldest address | 11 |
| Dry-run rollback | remove the final `rollBack()` | 23 (asserts the 4 counts and the exit code) |

**C14.6 — CSV handling (F6).** Text is in C7 ("Report file handling"). Escaping, `0700/0600`, the no-logging rule and the "never commit, never attach" rule are refinements. **H5 (retention and who may read the reports) is a human/client decision**; recommendation: retain with the backup until the Phase 5 gate, then delete. The files contain client data, and `storage/app/backfill/` is already git-ignored.

**C14.7 — Runbook flags (F7).** Every production invocation is `--force --no-interaction` (C2 precondition 3). The dry run too: precondition 3 applies to `--dry-run`, so its production form is `--dry-run --force --no-interaction`. *New pre-flight, read-only, Q18:* the deployed `.env`'s `APP_ENV` (`php artisan env` prints it) must be exactly `production`. If it isn't `production`, the environment confirm never appears, so **H3 (below) is the effective guard**, and the operator must not rely on the prompt. `.env` in the repo says `APP_ENV=production`; production's own file is unverified.

**C14.8 — Rollback verification (F8).** C9 step 4 now checks all of A4's counts plus the two "project_id IS NOT NULL" counts.

**C14.9 — Live-run guard (F9). NEW HUMAN DECISION H3.**
- *Facts:* the real run only picks up unattached quotes. Rows that old code adds to an already-attached quote afterwards (a crosswalk row, or a legacy member add or removal) are never picked up. Inside the runbook window the site is down, so nothing is lost. Live, it is a silent loss.
- *Recommendation (provisional default for development and tests):* a **real** run refuses with exit 2 unless `app()->isDownForMaintenance()` is true or `--allow-live` is passed; `--dry-run` only warns. The documented runbook (C14.12) runs every real run under `down`, including the verification run, so the guard costs the operator nothing there; it only changes a hypothetical live run, which needs `--allow-live`. It is still a behavioural choice, so it is a human decision.
- *Alternative:* no guard, with the operator rule only. Not recommended.
- *If accepted:* the command gains the `--allow-live` option (C2), and test 43 applies. *If rejected:* remove precondition 4, the option and test 43.

**C14.10 — Unlinked crosswalk rows (F10). NEW HUMAN DECISION H4.**
- *Facts:* an H2 `org_mismatch` row stays with `project_id` NULL, is invisible to Phase 4 code, and is never retried. Its expected volume is 0 (Q7, the store path can't succeed today).
- *Recommendation (provisional default):* exit code stays **0** for `org_mismatch` (it isn't an unresolved quote), but the count is printed on its own summary line, written to `crosswalk_conflicts.csv`, and the runbook step 6 review must sign off "unlinked crosswalk = 0 or accepted in writing". The Phase 5 gate already requires crosswalk conflicts resolved.
- *Alternative:* exit **1** when any row is unlinked, which forces a decision before the runbook's exit-0 check passes. It is stricter but blocks the window on a zero-expected case.
- H4 is moot if Q7 returns 0. Confirm it with H2.

**C14.11 — Notes (F11 and the Verifier's forbidden list).** Normalization is safe: `\p{Z}` covers NBSP; zero-width characters (Cf) aren't collapsed, which can only split, never merge. The forbidden actions the Verifier listed are now in C11.

**Open decisions for the human, with recommendations.** H1 (unnamed rule, C4) and H2 (crosswalk mismatch, C4) are unchanged. **H3** (live-run guard): recommend adopt. **H4** (exit rule for unlinked crosswalk): recommend exit 0 plus a mandatory runbook sign-off. **H5** (report retention): recommend keep until the Phase 5 gate, then delete. Everything else in C14 is a refinement; none changes scope, and the only Developer-visible additions are the `--allow-live` option (pending H3) and the behaviours above, all inside the two files already allowed by C11.

### C14.12 — Amendment 3 addendum 3 (2026-09-24, from the Verifier re-review after C14, N1-N9)

Edits were made in place in: the Runbook, B6 step 8, C2 precondition 1, C4 step 4 (operator rule), C5 (bullets, worked example), C7 (handling, `overrides.csv`, `summary.txt`), C12 (tests 43, 46, new 47-48, mutation table), C14.1, C14.9 and the acceptance line.

- **N1 (blocking) — decision: the "straggler" pass becomes a verification run under `down`, and no backfill runs after `up`.** Reason: while the site is down no old code can create an unattached quote, so a post-`up` pass would find nothing and would only add a live run against the H3 guard. Its purpose is to prove idempotency, which is done before `up`. Order (as amended by C14.13): backup, `down`, migrate, dry-run 1, dry-run 2 with the final overrides, real run (exit 0), upload code, **verification dry-run with the same overrides file (0 created/attached/inserted/linked, exit 0; C14.13)**, smoke-check, `up`. Every real run in the runbook and B6 is `--overrides=<same> --force --no-interaction`. `--allow-live` is documented nowhere in the runbook; it exists only for an operator who consciously accepts H3's risk. C14.9's earlier claim that the runbook "already runs under down" was false for the straggler step and is now true. *Refinement, not a new decision* (it removes a step, and the recommendation to run under `down` is the existing B6 rule).
- **N2 — rule fixed:** warn only when an attached quote's override `org_id` differs from `projects.org_id` or its `project_name` differs (normalized) from the project's. The example file is silent on re-run. `warnings.csv` `source=overrides` covers it. Test 34 is aligned.
- **N3:** mutation entries and tests 47 (one `$runAt`) and 48 (order) added; test 9 is named for the attach/move mutant.
- **N4:** test 38 hooks `TransactionBeginning` (after planning, before `FOR UPDATE`).
- **N5:** test 43 uses a fake `MaintenanceMode` binding; it never writes the real `down` file.
- **N6:** `overrides.csv` is exempt from escaping and byte-identical; the hash is over the raw input bytes; `fputcsv` uses an empty escape character; fatal override messages print no values; umask is restored in a `finally`.
- **N7:** "non-empty key" means after normalization (a whitespace-only key is fatal); report row order is undefined except `groups.csv`.
- **N8:** the precondition also checks every column the command writes.
- **N9:** no change.
- **New human decisions:** none beyond H3, H4 and H5. H3's effect on the runbook is now nil, since every real run is under `down`.
- **Developer file list:** unchanged (C11). The `--allow-live` option remains pending H3.

### C14.13 — Amendment 3 addendum 4 (2026-09-24, from the Verifier third review, N10-N13)

- **N10.** The runbook and B6 step 6 now run: dry-run 1 (no overrides, to draft them), **dry-run 2 with the final overrides file** (its reports are the reviewed and signed-off plan), then the real run with a **byte-identical** file. The operator compares the SHA-256 lines of the two `summary.txt` files; a mismatch means STOP and re-run dry-run 2. No new command option checks this (C11 forbids new options), so it is an operator check, recorded in PROGRESS.md.
- **N11 / N12 — the verification run is a `--dry-run`.** It cannot write, so it can't silently attach anything and hide a problem. It passes only when created, attached, inserted and linked are all 0 and the exit code is 0 (C6 acceptance), with `unresolved` unchanged from the real run's. **If it fails, the gate has failed:** do not run `up`; the site stays down; do not re-run blindly. Read its reports. The **release owner (the human)** decides, with the Architect (issue intake):
  - unexplained unattached quotes or a non-zero exit 3 or errors: stop, and if the cause isn't understood and fixed in the window, **abort: restore the test-restored backup** and keep the old code (the runbook's B6 step 1 backup);
  - an explained cause (for example a corrected override): fix the overrides, run dry-run 2 again, re-run the real backfill under `down` with the new file, then re-verify.
  `down` is issued without `--secret` until the verification run has passed (B6 step 8).
- **N7.** A whitespace-only `project_key` is **fatal** (C5), consistent with the trim/normalization rule; test 9 covers it.
- **N13.** A4's sentence was false and is corrected in place: a backfill re-run does not relink crosswalk rows once their quotes are attached; recovery is restore-from-backup or the manual repair in A4, under `down`.
- **Human decisions:** none new. **Developer file list:** unchanged.

### C15 — Amendment 3 addendum 5 (2026-09-24, from the Architect Phase 2 compliance pass; the plan now states what the code does)

The Phase 2 code was reviewed and found compliant. The six behaviours the Developer chose where the plan was silent are **accepted and written into the plan** (the sentences were edited in place at the places noted). The plan and the code now agree.

**C15.1 `candidate_merges.csv`** (C4, H1 bullet): a bucket per owner, org and normalized name that contains at least one H1-unnamed quote, including single-quote buckets, plus ordinary same-name quotes, excluding quotes with an override key.
**C15.2 Member order** (C4 step 6): ascending lowest source legacy row id, owner with no source row last, then user id. It applies to the insert order and `memberships.csv`.
**C15.3 Line numbers** (C5): CSV record number, header = 1, blank lines counted.
**C15.4 Override text** (C5): invalid UTF-8 in an override cell is fatal (`invalid_utf8`); override-name conflicts compare trimmed strings exactly.
**C15.5 Chunk size** (C13.2): 400 ids (not 500), inserts of 50 rows.
**C15.6 Pre-pass** (C5): read-only pre-pass over all owners for name conflicts, before the confirm.
**C15.7 Scope of override checks** (C5): the `org_id` role check (`org_without_role`) applies to unattached quotes only; the attached-quote override warning appears only for owners that still have unattached quotes. Both are harmless: there is nothing to group for the rest.
**C15.8 Invalid-UTF-8 address. H6 DECIDED by the human (2026-09-24): report it as a warning; the run still succeeds.**
- **Where:** the existing `warnings.csv` (columns `source, line_or_quote_id, message`, C7). The row is `normalize`, the **quote id**, and the fixed message `project_address_invalid_utf8`. No new file and no new column.
- **Which quotes:** every unattached quote in scope whose `project_address` is not valid UTF-8, once per quote, emitted with the other per-quote warnings before org resolution (like `project_name_invalid_utf8`). Only the real pass reports it; the pre-pass discards its warnings (C15.6).
- **The corrupt value is never written.** Not to `warnings.csv`, not to `address_conflicts.csv`, not to the console, not to `summary.txt`, and not to any log. The quote id is enough to find the row; the fixed message needs no formula escaping. A valid address on another quote of the group is still chosen, and if none is valid the address is `NULL`, as before.
- **Counter:** the existing `warnings: N` line in the console and `summary.txt` includes these rows. There is no new counter.
- **Unchanged:** no other behaviour, no exit code (warnings never change the exit code), no change to grouping, membership or crosswalk.
- **Developer change (exactly):** in `app/Support/ProjectBackfillPlanner.php` `plan()`, in the per-quote loop where `project_name_invalid_utf8` is added (`Planner.php:85-87`), also add `$warnings[] = ['normalize', $qid, 'project_address_invalid_utf8']` when `! self::isValidText($q['project_address'] ?? null)`. `BackfillProjects.php` needs **no change**: it already writes every planner warning to `warnings.csv` and counts it (`Command:472-475`). **No other file changes.**
- **Test 51** asserts it (see the checklist); its mutation entry is in the table.

**C15.9 Injection and faking (QA).** The code exposes everything QA needs; **no new seams are required**:
- *Maintenance mode:* `app()->isDownForMaintenance()` resolves `Illuminate\Contracts\Foundation\MaintenanceMode` from the container. QA binds a fake with a flag (C14.5 test 43) and never writes the real `down` file.
- *`$runAt`:* the command uses `now()` once, so `Carbon::setTestNow` controls it. Test 47 advances the clock in a `TransactionBeginning` listener, so a per-write `now()` mutant fails.
- *Storage path:* `storage_path('app/backfill/projects-entity')` is used, so QA calls `$this->app->useStoragePath($tmp)` and removes it in `tearDown`.
- *Events:* the group transactions fire `Illuminate\Database\Events\TransactionBeginning` (test 38).
- *Production confirm:* set the environment to `production` and use `expectsConfirmation`.
- The test class must **not** use `DatabaseTransactions`/an outer test transaction, because dry-run's counts and savepoints assume the command owns the transaction.
- Overrides files are written to a temp directory.

#### C15.10 The complete Phase 2 test checklist (QA, sqlite, `ProjectBackfillTest`)

A shared helper runs after every test that writes: it asserts the org invariant (`project_members.org_id = projects.org_id`) and that legacy member rows are byte-identical (C12 preamble).

| # | Topic (source) |
|---|---|
| 1-3 | Grouping: case/space/NBSP, two owners, unicode (C12) |
| 4-5 | Unnamed fallbacks; H1 provisional (C12) |
| 6-8 | Org resolution; multi-org; zero-org and overrides (C12) |
| 9 | Overrides: merge, split, name, BOM, all fatal cases incl. whitespace-only key, attached-quote override semantics (C12, C14) |
| 10-12 | Attributes; trashed group; address; no `updated_at` bump (C12) |
| 13-17 | Membership union, widening, owner removal, re-home vs mismatch, no `enrol()` (C12) |
| 18 | Crosswalk link, duplicates, org mismatch (C12) |
| 19-22 | Idempotency, stragglers, ambiguous, trashed sibling (C12) |
| 23-27 | Dry-run (asserts the 4 counts **and** the exit code), failure isolation, production confirm, report headers, **mandatory** schema precondition (C12, C14.4) |
| 28-33 | Sibling owner filter, existing member untouched, timestamps, chunking, 255-char name, exit-3 path (C13.6) |
| 34-48 | Override/sibling worked example, no-file ambiguity, typed keys, NULL ordering, `concurrent_change`, trashed reuse, rule (iii), escaping, log hygiene, H3, H4, `existing_untouched`, overrides copy/hash, `$runAt`, order (C14.5) |
| **49** | *(C15.7)* Override warnings: an owner with no unattached quotes gets **no** warning for a disagreeing override on an attached quote; an owner who still has unattached quotes gets exactly one `attached_quote_org_or_name_ignored`. |
| **50** | *(C15.7)* `org_without_role`: an override `org_id` without a role on an **unattached** quote exits 2; the same override on an **attached** quote does not exit 2 (no role check). |
| **51** | *(C15.8, H6)* A group where one quote has an invalid-UTF-8 address and another a valid one takes the valid address. If only the invalid one exists the address is `NULL`. H6 (decided): exactly one `warnings.csv` row `normalize, <quote id>, project_address_invalid_utf8` per such quote, the corrupt bytes appear in **no** report file or console output, the warning counter increments, and the exit code is unchanged (0 if nothing else is wrong). |
| **52** | *(C15.1)* `candidate_merges.csv`: a single H1 quote is listed; the bucket also lists a same-name ordinary quote; a quote with an override key is excluded; a bucket with no H1 quote is not listed. |
| **53** | *(C15.2)* Member order: with legacy rows 12 (user B) and 5 (user C) and an owner with no row, the insert order and `memberships.csv` order are C, B, owner; equal source ids tie by user id. |
| **54** | *(C15.3)* Override line numbers: the header is record 1, a blank line counts, and the console lists the failing record by number and code only (no cell values). |
| **55** | *(C15.4)* An override cell with invalid UTF-8 exits 2 (`invalid_utf8`); two override names `Main St` and `main st` in one group exit 2 (`conflicting_project_name`); identical trimmed names do not. |
| **56** | *(C15.5)* Binding counts: with the test query log enabled, no statement of the run has more than 500 bindings, and inserts have at most 50 rows (extends test 31 to the select, update and insert paths). |
| **57** | *(C15.6)* Pre-pass: a name conflict in the group of a **later** owner exits 2 with nothing written and **no report directory**; pre-pass warnings don't appear twice in `warnings.csv`. |
| **58** | *(QA note 5)* After a failed group (test 24) and after every exit path that opens reports, every report file is complete and closed: each CSV has its header row and `summary.txt` ends with the `exit code: N` line. |
| **59** | *(QA note 6)* `concurrent_change` accounting (extends 38): the group is absent from `groups.csv` and `memberships.csv`, is not counted in `created`/`attached`, and its quotes are `unresolved` with `concurrent_change`. |
| **60** | *(C16.1, Verifier F1)* B4 re-home requires an **active** role in the project org. Fixture: owner has one active role, in org 7, so the project is in org 7. Legacy member rows on the owner's quote, each with `org_id` 9: user U whose **only** role in org 7 is **inactive** (and who has an active role in org 9); user V with an **active** role in org 7 (control); user W with two roles in org 7, one inactive and one active (control). Expect: U gets **no** `project_members` row and appears in `membership_org_mismatch.csv` (`user_active_org_ids` = `9`, project org 7) and **not** in `membership_rehomed.csv`; V and W are inserted with `org_id = 7` and each appears once in `membership_rehomed.csv`; summary `membership org mismatch: 1`, `membership rehomed: 2`; exit code 0; the org-invariant helper passes. |
| **61** | *(C16.2, Verifier F2)* The owner's own active orgs feed membership decisions. Fixture A: the owner is active only in org 7 and has their own legacy row on the quote with `org_id` 9. Expect: the row is **rehomed** (in `membership_rehomed.csv`, not in `membership_org_mismatch.csv`), the owner is inserted with `org_id = 7`, and `memberships.csv` shows `member_active_in_org = yes`. Fixture B: the owner is active in orgs 7 and 9, an override resolves the quote to org 9, and the owner's legacy row is in org 7: expect the same (rehomed, `member_active_in_org = yes`). Fixture C (negative): an override resolves the quote to an org where the owner has only an **inactive** role, and the owner's legacy row is in org 9: expect `membership_org_mismatch.csv` and `member_active_in_org = no`. Exit code 0 in all three. |
| **62** | *(C16.3)* Owner org resolution with an inactive role plus an active one: the owner has an inactive role in org 7 and an active role in org 9, no override. Expect: the quote resolves to org 9 (`org_resolution = single_org`, project org 9), no `unresolved.csv` row, exit 0. (Zero-active-org owners stay covered by test 8.) |

**Mutation proof (QA, on a scratch copy of the repo, never the tracked files; as B14.1).** Each mutation must make **at least the named test fail**; record rule, mutation, and failing test in PROGRESS.md. A surviving mutant is a test gap and goes back to the Architect. The rows of C13.5 and C14.5 remain in force; the complete set is:

| Load-bearing rule | Mutation | Must fail |
|---|---|---|
| Org invariant (C4 step 6) | write the legacy `org_id` | 16, 17 (helper) |
| B4 re-home vs mismatch | always re-home | 16 |
| Explicit owner removal | force the owner active | 15 |
| Idempotency (C6) | drop `project_id IS NULL` scope or the sibling lookup | 19 |
| `sibling_project_trashed` | reuse the trashed project | 22 |
| Sibling owner filter (C13.1) | drop `created_by = owner` | 28 |
| Unnamed singleton | group unnamed quotes by name | 4 |
| Multi-org rule (iii) | pick the first org | 7 |
| No `updated_at` bump | Eloquent `update()` on quotes | 12 |
| Dry-run rollback | remove the final `rollBack()` | 23 |
| Per-group transaction | remove it | 24 |
| Chunking | chunk size 5000 (or 500) | 31, 56 |
| H2 provisional | link mismatched crosswalk rows | 18 |
| Never calls `enrol()` | call `enrol()` | 17 |
| Schema precondition | remove it | 27 |
| Override on attached quote | ignore its key in the sibling map; or let it attach/move | 34; 9, 34 |
| Sibling org | use the override `org_id` | 34 |
| Typed key | compare only `value` | 36 |
| NULL ordering | NULL `updated_at` is newest | 37 |
| `concurrent_change` | remove the set comparison | 38, 59 |
| Trashed reuse | never reuse | 39 |
| Rule (iii) org check | accept a legacy org that isn't active | 40 |
| Escaping | remove the `'` prefix | 41 |
| Default `fputcsv` escape | use the default | 41 |
| umask | don't restore | 41 |
| Log hygiene | log a project name | 42 |
| H3 guard | remove the maintenance check | 43 |
| `existing_untouched` | update existing members | 20, 29, 45 |
| Address choice | choose the oldest address | 11 |
| Address warning (H6) | remove the warning (or write the corrupt value) | 51 |
| `overrides.csv` / hash | escape the copy, or hash the BOM-stripped bytes | 46 |
| `$runAt` | `now()` per write | 47 |
| Group order | descending order | 48 |
| Warning scope (C15.7) | warn for every attached override | 49 |
| Role check scope (C15.7) | role check on attached quotes too | 50 |
| `candidate_merges` rule (C15.1) | list only buckets of two or more | 52 |
| Member order (C15.2) | order by user id only | 53 |
| Record numbers (C15.3) | count only non-blank lines | 54 |
| Override UTF-8 / exact compare (C15.4) | compare normalized names; accept invalid UTF-8 | 55 |
| Pre-pass (C15.6) | remove it | 57 |
| Report closing | skip the summary on error (an *unclosed handle alone* is equivalent, see C16.4) | 58 |
| B4 role must be active (C16.1) | remove the `->where('is_active', true)` filter on the legacy members' roles (`BackfillProjects.php:432-436`) | 60 |
| Owner's own active orgs (C16.2) | delete `$userActive[$ownerId] = $activeOrgs` (`ProjectBackfillPlanner.php:201`) | 61 |
| Owner active-org filter (C16.3) | count inactive roles when resolving the owner's orgs (`BackfillProjects.php:360-366`) | 62 (and 8) |

**What sqlite cannot prove** (belongs to the **B1 dry-run and real run on a restored production copy**, on MariaDB of the production version, recorded in PROGRESS.md): row locking and `FOR UPDATE` under concurrent writers; savepoint, undo and lock behaviour of the long outer dry-run transaction; InnoDB AUTO_INCREMENT behaviour on rollback; timezone/TIMESTAMP conversion of the copied `created_at`/`deleted_at` strings; peak memory and duration at production volume (including the doubled pre-pass reads); the collation-based SQL estimates (Q4/Q5/Q16) vs `groups.csv`; the real data shapes (Q0-Q9, Q16-Q17); and the file modes on the production filesystem.

**Human decisions:** none open in C15; H6 is decided (C15.8). Everything else in C15 is the plan catching up with accepted code. **Developer:** one line in `ProjectBackfillPlanner` (C15.8); no other file.

### C16 — Amendment 3 addendum 6 (2026-09-24, from the Verifier review "Phase 2 code and tests")

**Test coverage only. No code change is needed and nothing behavioural is decided.** The Verifier found no wrong-result edge case in the code and confirmed the 47 mutations except the survivors below. The Developer files are unchanged (C11).

- **C16.1 (F1, should-fix): test 60.** Removing the `is_active` filter on legacy members' roles (`BackfillProjects.php:432-436`) survived because test 16 has no user whose only role in the project org is inactive. Test 60 (C15.10 checklist, edited in place) closes it; the mutation entry is in the table.
- **C16.2 (F2, should-fix): test 61.** Deleting `$userActive[$ownerId] = $activeOrgs` (`ProjectBackfillPlanner.php:201`) survived. Test 61 has three fixtures (single-org owner, multi-org owner with an override, and a negative case) and kills it.
- **C16.3: test 62** is a control for the owner's active-org resolution (inactive plus active role).
- **C16.4 (F6, recorded):** the Verifier judged mutant **M26** (report handles left unclosed) **equivalent**: `$this->handles = []` drops the last reference and PHP closes the stream, and the summary is written before that. Test 58 therefore only needs to kill "skip the summary on error". The mutants **M14, N04, N14** (a redundant `whereNull('project_id')` filter on the quotes update, the legacy member read and the crosswalk update) are **not killable on sqlite**, because the row lock plus the set comparison (tests 38/59) already guard the same window. They are defense in depth, accepted, and their real protection is checked on B1 (item 6 of the B1 Phase 2 checklist below). Neither is a test gap to close.
- **C16.5 (F3-F5, F7, recorded, no action):**
  - F3: two override keys that map to the same existing project in one plan (possible only if the overrides file changed between runs, or after native Phase 4 edits) would insert the same member twice. The second group fails on `unique(project_id, user_id)`, rolls back cleanly, is reported in `errors.csv`, and the next run succeeds. It fails safe.
  - F4: the crosswalk planning cost is O(groups x rows) per owner, and the planner runs twice (pre-pass plus run). The B1 dry-run records duration and peak memory (C8).
  - F5: the pre-pass runs before the production confirm and the H3 guard. It is read-only, so it is harmless, but a refused run spends the planning time first.
  - F7: `--overrides` follows symlinks and reads any readable file. A non-CSV file is rejected by the header check and no cell value is echoed, and the copy is written only after validation, with mode 0600. Acceptable for an operator-run CLI.
- **C16.6 — B1 Phase 2 checklist** (13 items, also added to B1 as its Phase 2 step list; item 13 is the poison-row case). See the B1 section, "B1 Phase 2 checklist".

## Amendment 4 (2026-09-24) — Phase 3 specification: permission layer and read paths (steps 5-7)

Phase 1 (schema and models) and Phase 2 (backfill) are committed. This section is the single implementable spec for Phase 3. It folds in A1-A6, B1-B14 (notably B4, B6, B9/B14.2), C-sections (C14.13 verification run, C9 rollback), and the human decisions in the header (B3, B4, B6, H1-H6). It refines the approved scope; it adds **no new route, table, column, permission group, role, view or controller**. Every fact below was re-checked against the live code on 2026-09-24, and several plan line references were stale and are corrected here.

### D1 — Verified facts the spec depends on (live code)
| Fact | Where |
|---|---|
| `isActiveProjectMember` still queries `project_members.quote_id` and is called at `:79` with the effective (post-delegation) user resolved at `:59` | `app/Services/Rbac/PermissionService.php:59,79,124-132` |
| `RbacAudit` reads the param as `(int) $request->route($projectParam)`, writes `project_id` only, reasons are `no_org`, `no_grant`, `no_grant_or_not_project_member`; denial in enforce mode is 403 JSON only for `expectsJson()`/`ajax()`, otherwise a redirect back (302); audit mode passes through | `app/Http/Middleware/RbacAudit.php:57-75,80-129` |
| Rule parsing returns `[pattern, group, level, batch, projectParam]` from `$value['project_param']` | `RbacAudit.php:137-164` |
| The operative RBAC mode is `RbacSetting::get('rbac_mode')` (a DB row), and `isEnforcing($batch)` decides per batch | `RbacAudit.php:234-244` |
| The middleware is appended to the `web` group, **after** the default group's `SubstituteBindings`, so route-model-bound params (`{quote}`) reach it as models, and a soft-deleted quote already 404s at binding | `bootstrap/app.php:20-24`; `vendor/.../Foundation/Configuration/Middleware.php:490,497` |
| The org the middleware checks is the session org **if the user still has an active role there**, else the user's first active org | `RbacAudit.php:170-194`; the session key is `rbac_current_org_id` (`config/rbac.php:37`) |
| **9** map entries carry `project_param` (`id` or `quoteId`): `GET quotes/{id}/details :121`, `GET quotes/{id}/pdf-preview :122`, `GET quotes/{id}/pdf :123`, `POST quotes/{id}/duplicate :128`, `PUT quotes/{id} :131`, `PUT quotes/{id}/editor :132`, `PUT quotes/{quoteId}/items/{itemId} :133`, `DELETE quotes/{id} :136`, `DELETE quotes/{quoteId}/items/{itemId} :137`. `GET projects/{quote}/workspace :92` has no `project_param`. The header comment at `:13-15` documents `project_param` | `config/route_permission_map.php` |
| Every quote read/write in `QuoteController` is `where('user_id', Auth::id())`. Reads: list `:53`, total `:121`, details `:144`, PDF `:875`, preview `:896`. Writes (Phase 4): `:219,224,297,303,400,445,494,526,549,596,625`. There is no org resolution in this controller | `QuoteController.php` |
| `ProjectWorkspaceController::show(Quote $quote)` calls `checkPermission(..., projectId: $quote->id)` at `:38-44`: it passes a **quote id as the project id**. On denial it redirects to the non-existent route name `org-admin.projects` (`:47`; the real name is `org-admin.projects.index`, `routes/web.php:139`), which is a 500. It reads the raw session org (`:107-110`), legacy member rows by `quote_id` (`:70-74`) and crosswalk rows by `quote_id` (`:77-81`) | `ProjectWorkspaceController.php` |
| `UserWorkspaceController::index` builds "My Projects" from legacy `project_members.quote_id` rows and reads the raw session org | `UserWorkspaceController.php:27-49,74-80,108-112` |
| `OrgAdminController::projects()` lists quotes owned by any active org member and their legacy member rows by `quote_id`; `currentOrg()` is validated (session org if the user still has an active role, else the first active org) | `OrgAdminController.php:553-582,633-647` |
| `PlanCrosswalkController::index` queries `Quote::where('org_id')` (no such column) and selects `title` (no such column), so it throws; it takes a `project_id` request param that holds a **quote id**; it reads the raw session org | `PlanCrosswalkController.php:26-45,115-118` |
| The only callers of `checkPermission` with a project id are `RbacAudit.php:68` and `ProjectWorkspaceController.php:38`; the `Rbac::check` helper documents a quote id (`app/Support/Rbac/Rbac.php:12`) but has no caller passing one. Views only call it without a project id | grep of `app/`, `resources/`, `routes/` |
| `PermissionServiceTest::test_project_scoping_requires_membership` inserts a legacy `quote_id` member row, so it **must be rewritten** in step 5 | `tests/Feature/Rbac/PermissionServiceTest.php:85-111` |
| Committed Phase 1/2 pieces used here: `Project` (SoftDeletes, `scopeVisibleTo` requiring `projects.org_id`, active member row with the same `org_id`), `Quote::project()` and fillable `project_id`, `AuditLog` fillable `quote_id`, `rbac_audit_logs.quote_id` (000005) | `app/Models/`, `database/migrations/2026_09_24_*` |

### D2 — Scope and ordering of Phase 3
- Phase 3 is steps 5, 6 and 7 only. **No new route, controller, view or migration.** Writes stay on `user_id = me` (Phase 4, step 9). The estimate creation paths still create quotes with `project_id` NULL until Phase 4.
- **Phase 3 is never deployed alone (B6).** Phases 2-4 ship as one release. Between the Phase 3 and Phase 4 commits the write path (`OrgAdminController::addProjectMember`) still writes legacy `quote_id` rows, which **no longer grant** permission after step 5. This is acceptable only because Phase 4 rewrites it in the same release. A hot fix that would deploy Phase 3 without Phase 4 needs the human's explicit approval (risk D13.2).
- Commit order for the Developer (each testable on sqlite): (a) D6 helper and `Quote::scopeVisibleTo`; (b) step 5 and the rewritten `PermissionServiceTest`; (c) step 6 (`RbacAudit` plus the 10 map entries, **one commit**) together with the `ProjectWorkspaceController` fix in D8, because that controller's own check would otherwise pass a quote id as a project id; (d) step 7 read paths.

### D3 — Step 5: `PermissionService::isActiveProjectMember`
Exact query (parameters `userId` = the effective user, `orgId`, `projectId` = a **projects.id**):
`project_members` joined to `projects` on `projects.id = project_members.project_id`, where `project_members.project_id = projectId`, `project_members.user_id = userId`, `project_members.org_id = orgId`, `project_members.is_active = true`, `projects.org_id = orgId` (B4), and `projects.deleted_at IS NULL`; `exists()`.
- The `deleted_at` condition mirrors `Project::visibleTo` (the SoftDeletes global scope), because a raw `DB::table` query has no global scope. A soft-deleted project denies.
- **Legacy quote-only rows** (`project_id` NULL, `quote_id` set) **never match**, and so never grant permission. This holds during the whole dual-read window. Fail closed is intended: the Phase 2 backfill has moved every resolvable legacy row up (B4 re-home), the verification run (C14.13) proves no unattached quote remains, and rows the backfill deliberately did not carry (`membership_org_mismatch.csv`, unresolved quotes) lose access, which follows Q14.
- The **id-collision hazard** is real: `quotes.id` and `projects.id` are both small integers. A quote id passed as a project id would silently match another project. That is why every caller passes a resolved projects.id (D4, D8), and why test M7 in D10 uses a fixture where a legacy row's quote id equals a different project's id.
- Signature and semantics of `checkPermission()` are unchanged. `projectId` is now a **projects.id**. Update the docblock at `:42` ("a `projects.id`; membership is `project_members.project_id`") and the class comment where it mentions a quote id. Delegation is unchanged: the effective (principal's) user is checked (`:59,79`); see D13.6.
- **Rewrite** `PermissionServiceTest::test_project_scoping_requires_membership` (`:85-111`, and its `makeProject` helper) on `projects` and `project_members.project_id`. The rewrite keeps the three assertions (org-level grant without a project id, member allowed, non-member denied).

### D4 — Step 6: `RbacAudit`
**Rule parsing.** `ruleForRequest` returns two params: `projectParam` (from `'project_param'`, meaning a **projects.id**) and `quoteParam` (from `'quote_param'`, meaning a **quotes.id**). Having both keys on one entry is a config error, caught by the lint test (D5).

**Resolution**, after the existing "no user / admin / unmapped" pass-throughs and the org resolution (unchanged):
- **`project_param`:** the value is `$request->route($param)`. If it is an Eloquent model use `getKey()`; if it is a positive-integer scalar, cast it with `(int)`; anything else is `project_unresolved` (D15.3). `projectId` = that value, `quoteId` = NULL. A well-formed id of a project that doesn't exist or isn't the user's gives `no_grant_or_not_project_member` (there is no membership row). (No Phase 3 route uses it, but the behaviour is specified and tested through a test-only route, so Phase 4 can rely on it.)
- **`quote_param`:** if the value is an Eloquent model (the bound `{quote}` of the workspace route), `quoteId = getKey()` and `projectId = $model->project_id` with no extra query. Otherwise `quoteId = (int) scalar` and `projectId = Quote::withTrashed()->whereKey($quoteId)->value('project_id')`. *(D15.3)* A scalar that is not a positive integer (digits only, at least 1) is **unresolved**, for both keys, and so is an absent param (a lint-caught config error).
- **`project_unresolved`:** if the quote does not exist or its `project_id` is NULL, `projectId` stays NULL and the request **fails closed**: `checkPermission` is **not** called (not even org-level), and the failure goes through `fail(...)` with reason `project_unresolved`. `withTrashed()` means a trashed quote still resolves to its project, so the membership decision applies and the controller then 404s as today.
- **Order of reasons:** `no_org` (user has no org) comes first and still records `quote_id`. Then `project_unresolved`. Then the `checkPermission` result: `no_grant_or_not_project_member` when a project id was resolved, `no_grant` when the route has no project param.

**Audit row.** `fail()` gains a `?int $quoteId` argument and writes `quote_id` and `project_id`. For `quote_param` routes both are set (`project_id` NULL only for `project_unresolved`); for `project_param` routes `quote_id` is NULL; for unscoped routes both are NULL. Rows written before deploy keep a **quote id in `project_id`** (Q13); from deploy, `project_id` is a projects.id. Distinguish by `created_at` (deploy time) or by `quote_id IS NOT NULL`.

**Modes.** *Audit* (`rbac_mode = audit`, or a non-enabled batch): every failure writes `outcome = would_block` and the request passes through to the controller unchanged, so the controller's own data scoping (D6-D8) is the effective guard (a non-member gets 404 there). *Enforce* (batch enabled): every failure writes `outcome = blocked` and returns the existing response: **403 JSON** for `expectsJson()`/`ajax()`, otherwise a **302 redirect back** with the `rbac_denied` flash. **Do not change this behaviour**: it is Known open item #4 and stays with that item's decision. `project_unresolved` in enforce mode therefore also denies with 403/302, including the owner of a quote whose `project_id` is still NULL (D13.3).
**One extra indexed quote lookup** per `quote_param` request (`Quote::withTrashed()->whereKey`), none for a bound model.

### D5 — Step 6: the route map and the lint test
**Exact entries** in `config/route_permission_map.php`, **one commit with D4**:
| Line | Key | Change |
|---|---|---|
| 121 | `GET quotes/{id}/details` | `project_param => 'id'` becomes `quote_param => 'id'` |
| 122 | `GET quotes/{id}/pdf-preview` | same |
| 123 | `GET quotes/{id}/pdf` | same |
| 128 | `POST quotes/{id}/duplicate` | same |
| 131 | `PUT quotes/{id}` | same |
| 132 | `PUT quotes/{id}/editor` | same |
| 133 | `PUT quotes/{quoteId}/items/{itemId}` | `quote_param => 'quoteId'` |
| 136 | `DELETE quotes/{id}` | `quote_param => 'id'` |
| 137 | `DELETE quotes/{quoteId}/items/{itemId}` | `quote_param => 'quoteId'` |
| 92 | `GET projects/{quote}/workspace` | **add** `'quote_param' => 'quote'` (it was unscoped) |
That is **9 renames + 1 addition = 10 entries** (not 11 or 12). Group, level and batch are unchanged. Update the header comment at `:13-15` to document both keys (`project_param` = projects.id; `quote_param` = quotes.id). Unchanged and not scoped: `GET quotes`, `quotes/list`, `quotes/customers`, `quotes/product-variations`, `quotes/services`, `POST quotes`, `POST quotes/create-from-list/{listId}`, the org-admin project routes and `plan-crosswalk` (they change in Phase 4, or are not project-scoped).
**Atomicity.** If the map shipped without `RbacAudit`, `project_param` would be read as a projects.id while carrying quote ids (leak); if `RbacAudit` shipped first, `quote_param` would be ignored and the quote routes would become unscoped. Both changes are **one commit**, and `RoutePermissionMapTest` guards it.
**`RoutePermissionMapTest` lint rules** (step 17, applied to the real `config('route_permission_map')` and the registered routes):
1. no entry has both `project_param` and `quote_param`;
2. no `project_param` appears on any URI containing `quotes/` or `{quote}`;
3. every `quote_param`/`project_param` value is a route parameter of that entry's URI;
4. every URI under `quotes/` that has `{id}` or `{quoteId}` and is not one of the five unscoped GETs has `quote_param`;
5. every registered route whose URI starts with `projects/` has a map entry, and `projects/{quote}/workspace` has `quote_param => 'quote'`;
6. every `{project}` URI (none in Phase 3, the rule is kept for Phase 4) has `project_param => 'project'`;
7. the exact set of 10 changed entries above is asserted by key, so a silent revert fails.

### D6 — Current org and one visibility rule (used by every read path)
- **New `App\Support\Rbac\CurrentOrg`** with `id(int $userId): ?int`: the session org (`config('rbac.current_org_session_key')`) if the user still has an **active** `user_org_roles` row there, else the user's first active org, else NULL. It reproduces `OrgAdminController::currentOrg()` and `RbacAudit::currentOrgId()`, which are validated; it replaces the raw-session reads in `UserWorkspaceController::currentOrgId`, `PlanCrosswalkController::currentOrgId` and `ProjectWorkspaceController::currentOrgId`. `RbacAudit` and `OrgAdminController` are **not** changed to use it (no refactor of working code). Test T-org (D10) asserts the helper and `RbacAudit` agree in every case, so the org the middleware checked is the org the controller scopes by.
- **New `Quote::scopeVisibleTo($query, int $userId, ?int $orgId)`** (in `app/Models/Quote.php`): `where(user_id = $userId)` **OR** (when `$orgId` is not NULL) `whereIn(quotes.project_id, Project::visibleTo($userId, $orgId)->select('projects.id'))`. It is wrapped in one grouped `where` so it composes with other filters. It is the one dual-read rule for quotes; `Project::visibleTo` is unchanged (Phase 1).
- A quote with a **NULL `project_id`** is visible only through the `user_id` clause (to its owner, in any org). Nobody else ever sees it. A quote in a **soft-deleted** project is visible to its owner only (visibleTo excludes trashed projects). A quote in another org's project is invisible to a non-member even with its id.
- The `user_id` clause is org-independent, exactly as today. The Phase 5 tightening removes it.

### D7 — Step 7: every read-path query
| # | Where | Today | Phase 3 |
|---|---|---|---|
| 1 | `QuoteController::getEstimatesList` `:53` | `Quote::where('user_id', me)` | `Quote::visibleTo(me, CurrentOrg::id(me))`; filters, eager loads and pagination unchanged |
| 2 | same method, total `:121` | `Quote::where('user_id', me)->get()->sum(...)` | the **same** `visibleTo` set (so the total matches the list) |
| 3 | `getEstimateDetails` `:143-145` | `where id, user_id = me, firstOrFail` | `Quote::visibleTo(...)->where('id', $id)->firstOrFail()`; a non-visible quote is a 404 |
| 4 | `generatePDF` `:874-876` | same | same change |
| 5 | `previewPDF` `:895-897` | same | same change |
| 6 | `UserWorkspaceController::index` `:31-49,74-80` | "My Projects" and pending approvals from legacy `project_members.quote_id` for `(me, org)` | *(D17.1)* the id set is **only** the quotes whose `project_id` is in `Project::visibleTo(me, org)`; the legacy `project_members.quote_id` union is **dropped** (it had no org or membership tie); the org comes from `CurrentOrg`; if the org is NULL both sets are empty; the existing `canReadEstimates` gate and the dead `pending_approval` query are left as they are |
| 7 | `OrgAdminController::projects` `:553-582` | quotes owned by any active org member; members from legacy `quote_id` rows | *(D15.2, D15.4)* quotes owned by an active org member **OR** whose `project_id` belongs to a **non-trashed** project with `projects.org_id = org` (a project's quotes stay visible even if their owner left). `project_members_list` per quote: if the quote has a `project_id`, **only project rows** (`project_id = quote.project_id`, `org_id = org`, active); if `project_id` is NULL, **only legacy rows** (`quote_id = quote.id`, `org_id = org`, active), as today. Never both, so a backfilled member appears once. The route stays org-wide (not member-scoped), unchanged |
| 8 | `PlanCrosswalkController::index` | throws (D8) | D8 |
| 9 | `ProjectWorkspaceController::show` | D8 | D8 |
| 10 | `resources/views/user/dashboard.blade.php:15,42,45,66` (inline `Quote::where('user_id', me)`: quote count, status chart, recent quotes), rendered by `UserWorkspaceController::index` | owner-only | **unchanged in Phase 3**; the counters stay the user's own quotes (H8, D15.1). The only edit to this view is the one-line route-name fix at `:84` (D16); no controller change |
*(D18.1, decided H9: in rows 1-5 and 8 the member clause of `visibleTo` applies only to a user holding `estimate_management:R`; the owner clause never needs the role. D18.2 lists each path.)*
**Not changed in Phase 3 (Phase 4):** `QuoteController` `store`, `createFromList`, `duplicate`, `update`, `saveEditor`, `updateItem`, `destroyItem`, `destroy` (`:219,224,297,303,400,445,494,526,549,596,625`), the customer, product and service lookups (`:705,766,875`), `PlanCrosswalkController::store/update/destroy`, `addProjectMember`/`removeProjectMember`, all views. Consequence (D13.4): a project member can now **read** a teammate's quote but every edit route still 404s from the controller, whatever the RBAC mode.
**Effect on deploy.** In audit mode (production) visibility widens the moment the code goes live (risk 2): project members see teammates' quotes in lists, details and PDFs. This is **intended** (H7 confirms it), subject to the role gate on member reads (H9, D18) and the staff-notes and PDF-write limits (H7, D18.4-D18.5).

### D8 — Crosswalk index and workspace: what Phase 3 fixes and what waits
- **`PlanCrosswalkController::index` (Phase 3 fixes the crash and scopes it, and nothing else):**
  - The list of "projects" for the filter and the add form is `Quote::visibleTo(me, org)` selecting `id`, `name as title`, `created_at`, newest first. That removes the non-existent `quotes.org_id` and `title`, and the view needs no change (`$project->title`, `$project->id`).
  - The `project_id` request param **keeps its legacy meaning (a quote id)** in Phase 3, so the unchanged view keeps working. It is only a filter; rows are already restricted, so an invisible id yields an empty list, never data. The param is retired in Phase 4 (B14.4).
  - Rows: `plan_crosswalk.org_id = org` **and** (`quote_id` in the visible quotes **or** `project_id` in `Project::visibleTo(me, org)`), with the existing filter, `with([...])` and ordering. Backfilled rows have both ids; native Phase 4 rows only `project_id`.
  - The org comes from `CurrentOrg` (NULL gives an empty page).
  - **Left for Phase 4:** `store` (it also queries `quotes.org_id` and would still throw), `update`, `destroy`, the `quote_id` form field and the `{project}`-keyed routes.
- **`ProjectWorkspaceController::show` (minimal, because step 5 would otherwise break it):**
  - Pass **`$quote->project_id`** as the `checkPermission` project id. *(D15.6)* A NULL `project_id` is denied **explicitly, before `checkPermission` is called** (with a NULL project id `checkPermission` would skip the membership check and pass on the org-level grant alone). The deny is the controller's existing one: `redirect()->route('org-admin.projects.index')->with('error', 'You are not a member of this project or do not have the required permission.')` (302, the same flash, no 403), in every RBAC mode. This also closes the id-collision hazard in D3.
  - Fix the redirect route name to `org-admin.projects.index` so a denial redirects instead of returning a 500 (the crash is pre-existing; Phase 3 makes the denial path reachable through the newly scoped route).
  - Take the org from `CurrentOrg`.
  - **Unchanged until Phase 4 step 11:** the §4.5 creator-org check, the legacy member list (`quote_id`), the crosswalk list (`quote_id`), the `show(Quote)` signature, the legacy 301 redirect and the view.
  - This controller check is enforced **in every RBAC mode**, so users whose legacy membership was not carried over by the backfill are redirected from the workspace even in audit mode. That is intended (Q14, B4).

### D9 — Files the Developer may create or modify (Phase 3 only)
**Modify:** `app/Services/Rbac/PermissionService.php`; `app/Http/Middleware/RbacAudit.php`; `config/route_permission_map.php`; `app/Models/Quote.php` (the scope only); `app/Http/Controllers/Frontend/QuoteController.php` (the five read queries in D7 rows 1-5 only); `app/Http/Controllers/Frontend/UserWorkspaceController.php` (`index` and `currentOrgId`); `app/Http/Controllers/Frontend/OrgAdminController.php` (`projects()` only); `app/Http/Controllers/Frontend/PlanCrosswalkController.php` (`index` and `currentOrgId` only); `app/Http/Controllers/Frontend/ProjectWorkspaceController.php` (D8 only); `app/Support/Rbac/Rbac.php` (docblock only, D15.5); `tests/Feature/Rbac/PermissionServiceTest.php` (the rewrite in D3); `TESTING.md` (new tests, and a note under known failure #1 that non-JSON denials redirect by design, `RbacAudit.php:120-125`).
**Create:** `app/Support/Rbac/CurrentOrg.php`; `tests/Feature/Rbac/ProjectTestCase.php` (extends `RbacTestCase`, adds only the stub tables the controller tests need); `tests/Feature/Rbac/ProjectMembershipTest.php`; `tests/Feature/Rbac/ProjectReadPathsTest.php`; `tests/Feature/Rbac/RoutePermissionMapTest.php`.
**Forbidden:** routes (`routes/web.php`), **any view except the single route-name fix on `resources/views/user/dashboard.blade.php:84` (D16)**, any migration, `ProjectController`/`ProjectMemberController`, any write path, `BackfillProjects`/`ProjectBackfillPlanner`, `ProjectMember::enrol`, `RbacTestCase.php` (unless a stub is unavoidable, then via the Architect), seeders, `ARCHITECTURE.md` and `CLAUDE.md` (applied at Checkpoint 2 with the human's approval).

### D10 — Tests QA must write (sqlite) and mutation proof
Every permission-sensitive route needs the allowed path and the denied paths (wrong role, wrong org, missing membership) (CLAUDE.md). A shared fixture has two orgs, an estimator (F on `estimate_management`), a `viewer_read_only` user, a `superintendent`, project P1 with quotes Q1, Q2, project P2 with Q3 in org A, project P3 in org B, and a quote Q0 with NULL `project_id`. Ids are chosen so that **`quotes.id` values differ from `projects.id` values and one quote id equals another project's id** (collision fixture).

**`ProjectMembershipTest` (step 15; service and middleware).**
- M1: one member row on P1 allows `checkPermission(estimate_management, R, P1)`; M2: a non-member is denied; M3: a member of P1 is denied on P2; M4: a member row with a different `org_id` from the check org is denied (wrong org); M5: a row whose `org_id` differs from `projects.org_id`, inserted directly, grants nothing in either org (B4); M6: an inactive row is denied; M7: a **legacy quote-only row** grants nothing, including when its `quote_id` numerically equals the id of a project the user is checked against (the collision fixture); M8: a soft-deleted project denies a member; M9: wrong role (`viewer_read_only` for a write level, `superintendent` for estimate routes) is denied even for a member; M10: a delegate is checked against the principal's membership; M11: no project id keeps the org-level check unchanged.
- A1: a `quote_param` scalar route resolves quote to project and a member is allowed with **zero** audit rows; A2: a non-member gives `would_block` in audit mode (request passes through, row has `project_id` = the projects.id, `quote_id` = the quote id, reason `no_grant_or_not_project_member`), and `blocked` in enforce mode as **JSON 403** and as **302 back** for a non-JSON request (asserting open item #4 as it is); A3: a quote with NULL `project_id` gives `project_unresolved` (both modes; the request never reaches `checkPermission`, shown with a user who would otherwise pass); A4: a non-existent quote id gives `project_unresolved`; A5: a soft-deleted quote still resolves to its project (`withTrashed`); A6: a bound `Quote` model resolves through `->project_id` without a second quote query; A7: `project_param` resolves a scalar and a bound `Project` model (through a test-only route with a test map entry); A8: a non-numeric or absent id gives `project_unresolved` for both `quote_param` and `project_param`, and in both modes (audit: `would_block` row and pass-through; enforce: 403 JSON / 302 back, no `checkPermission` call); A9: `no_org` takes precedence and still records `quote_id`; A10: the audit row has `quote_id` for `quote_param` routes, NULL for `project_param` and unscoped routes; A11: `RbacAudit` and `CurrentOrg` agree on the org for a valid session org, a stale session org (user has no active role there), no session, and no orgs.

**`ProjectReadPathsTest` (controller level; audit mode unless stated).** Per route in D5 (the 9 renamed routes plus the workspace): allowed (member), wrong role, wrong org, missing membership, via a data provider. The read routes are also exercised through the controller:
- R1: the list shows own quotes (including NULL-project ones), teammates' quotes in a project the user is a member of, and nothing from another org's project or from a project with a mismatched member row; the total (`:121`) equals the list set; R2: details, PDF and preview: owner 200, member 200, non-member 404, another user's NULL-project quote 404, a quote in another org's project 404 (the PDF tests use `Storage::fake` and assert only visible vs 404); R3: the dashboard "My Projects" is the union of legacy ids and visible-project quotes without duplicates, and a stale session org falls back to the first active org; R4: `OrgAdminController::projects` shows quotes of members and of the org's projects, the member list is the legacy plus project rows without inactive ones, and another org's data is absent; R5: `PlanCrosswalkController::index` **no longer throws**, shows only rows of visible quotes or projects, filters by a legacy quote id, and never shows another org's rows; R6: the workspace: a project member gets 200, a legacy-only member is **redirected with the error (not a 500)**, a NULL-project quote is **redirected (302 to `org-admin.projects.index`, flash `error`) even for a user holding the org-level grant** (D15.6), and the collision fixture proves the project id (not the quote id) is checked; R7: **write routes unchanged**: `PUT`/`DELETE` on a teammate's quote by a project member is still 404 from the controller in audit mode (Phase 3 must not open writes); R8: the audit-mode guarantee: a non-member calling `GET quotes/{id}/details` gets **404 from the controller and a `would_block` row**; R9: enforce mode with JSON: denied with 403 and the controller never runs; R10: an owner of a NULL-project quote in audit mode gets 200 with a `would_block` `project_unresolved` row (the documented D13.3 consequence); R11 *(D15.1)*: the dashboard counters (`quotesCount`, status chart, recent quotes) still count only the user's own quotes, while the "My Projects" panel shows a teammate's project quote; R12 *(D15.2)*: `OrgAdminController::projects` shows a backfilled member **once** (project row only) for a quote with a `project_id`, shows legacy rows only for a quote with NULL `project_id`, and removing the project-row chip makes that user lose `Project::visibleTo` access; R13 *(D15.4)*: a live quote in a soft-deleted project is not visible to a member (only to its owner through the `user_id` clause) and does not appear through the org's project clause in row 7.

**Mutation proof (QA, on a scratch copy of the repo, never the tracked files; as B14.1 and C13.5).** Each mutation must make at least the named test fail; record rule, mutation, failing test in PROGRESS.md; a surviving mutant goes back to the Architect.
| Load-bearing rule | Mutation | Must fail |
|---|---|---|
| Membership reads `project_id` | revert the query to `quote_id` | M1, M7 |
| B4 org tie | drop `projects.org_id = orgId` | M5 |
| Active membership | drop `is_active` | M6 |
| Trashed project denies | drop `deleted_at IS NULL` | M8 |
| Org on the member row | drop `project_members.org_id` | M4 |
| `quote_param` resolves to a project | pass the quote id straight to `checkPermission` | A1, M7 (collision) |
| Fail closed | treat a NULL `project_id` as "no project" (org-level check only) | A3, A4, R10 |
| `withTrashed` | drop it | A5 |
| Model or scalar | `(int)` a model | A6, A7 |
| Audit row | omit `quote_id`, or write the quote id in `project_id` | A2, A10 |
| 302/403 unchanged | always return 403 | A2 |
| `no_org` first | check `project_unresolved` first | A9 |
| Map atomicity | keep one `project_param` on a quote route | lint 2, 4, 7 |
| Workspace scoped | remove `quote_param` from the workspace entry | lint 5, R6 |
| Dual-read owner clause | remove the `user_id` clause | R1, R2, R10 |
| Dual-read member clause | remove the `visibleTo` clause | R1, R2 |
| Visibility org tie | use `visibleTo` without the org | R1 (another org) |
| Validated org | use the raw session org | A11, R3 |
| NULL-project quote hidden from others | let another user's NULL-project quote through | R2 |
| Crosswalk fix | restore the `quotes.org_id` query | R5 |
| Crosswalk scoping | drop the org or visibility restriction | R5 |
| Workspace checks the project id | pass `$quote->id` | R6 (collision) |
| Route name fix | restore `org-admin.projects` | R6 |
| No write opened | scope a write route with `visibleTo` | R7 |
| Dashboard counters owner-only (H8) | move a counter to `visibleTo` | R11 |
| Dashboard has no legacy union (D17.1) | re-introduce the legacy `quote_id` set (with or without the org or `is_active` filter) | T-dash1 |
| Crosswalk validated org (D17.5) | `currentOrgId()` reads the raw session org | T-xw1 |
| Workspace NULL deny (D15.6) | drop the explicit NULL check | R6 |
| Chip source (D15.2) | show legacy and project rows together | R12 |
| Trashed project (D15.4) | let a trashed project's quotes through | R13 |
| Non-numeric id (D15.3) | cast it to 0 and check membership | A8 |
| Audit-mode guarantee | let the middleware short-circuit in audit mode | R8 |
| Enforce JSON | drop the JSON branch | R9, A2 |

**What sqlite cannot prove** (checked on the restored production copy or staging, recorded in PROGRESS.md): query plans and cost of `whereIn(project_id, visibleTo subquery)` on the real `quotes` size, and the index use of `project_members(project_id, user_id)`; the real RBAC mode row (Q0); real multi-org session behaviour; real data shapes, notably the count of quotes with NULL `project_id` after the backfill (must be 0 before Phase 3 goes live: `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL`, trashed included); the PDF engine and public-disk storage; the FTP deploy order.

### D11 — Rollback and deploy order
- **Rollback of Phase 3:** revert the Phase 3 commits **as a unit** (D2 commits (a)-(d)). Step 6 (the `RbacAudit` change, the map and the `ProjectWorkspaceController` fix) must be reverted together; reverting only the map or only `RbacAudit` reopens the id-collision leak (risk 1). After a revert the old code reads `quote_id` rows, which the backfill left untouched, and `project_id` values are ignored. Audit rows written meanwhile keep their `quote_id`/`project_id`; nothing needs cleanup.
- **Deploy order (B6):** Phase 3 goes up at B6 step 7, **while the site is still down**, after the real backfill and the verification dry-run (C14.13) passed. Upload `RbacAudit.php` and `config/route_permission_map.php` **together**, then `php artisan optimize:clear` (and re-cache config/routes if production caches them). Never upload Phase 3 files before the B6 step 5 schema check has passed. The Phase 3 files are all in this D9 list, and the B12 file diff applies to each of them before upload.
- **Runbook precondition (new):** `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL` is 0, and the verification run reported zero unresolved quotes. Otherwise the owners of the remaining quotes get `project_unresolved` denials in enforce mode.

### D12 — Acceptance for Phase 3 development
D10 tests pass; `php artisan test --filter=Rbac` shows only the 3 known failures by name; the D10 mutation proofs are recorded in PROGRESS.md; the Architect's compliance pass finds the diff matches D3-D9 (in particular: exactly the 10 map entries, the query in D3, `fail()` writing both ids, no Phase 4 file touched); the Verifier confirms no write path was opened.

### D13 — Risks, human decisions and production data
**HUMAN DECISION H7 (rewritten by D17.3; DECIDED 2026-09-24: hide `staff_notes` from non-owners and stop the `pdf_path` write by non-owners; attachments and customer data stay visible; the exact behaviour is D18.4-D18.5).** From the Phase 3 deploy, an active member of a project (matching org) can, for **every quote in that project, whoever owns it**, and in audit mode whatever their role (see H9):
- **See** in the estimates list: the quote name, number, status and totals (the total at `:121` sums every visible quote). In the details JSON: all items with quantities, rates and item notes, the saved-list name, `customer_id`, `customer_name` and `customer_address`, `notes`, `terms_and_conditions`, **`staff_notes` (the owner's internal notes)**, and **the attachment names and their public-disk URLs**. In the PDF and preview: the customer details and the line items.
- **Do:** `GET quotes/{id}/pdf` by a member holding only R **writes** the owner's quote (`pdf_path` and `updated_at`) and overwrites the shared public-disk file `quotes/<quote number>.pdf`. The output is idempotent, but a read role triggers a write on someone else's row.
- **Cannot** (unchanged in Phase 3): edit, duplicate, delete or add items (writes stay `user_id = me` until Phase 4).
This is what "membership is managed once per project and covers every quote in it" implies, and it goes live immediately in audit mode. **Options:**
- **1 (accept all):** no change. Every item above is intended shared project content.
- **2 (hide internal fields from non-owners):** `getEstimateDetails` returns `staff_notes` (and optionally the attachments) empty for a viewer who isn't the quote owner. Code: `QuoteController::getEstimateDetails` only. Test: a member's details JSON has no `staff_notes`, the owner's has it.
- **3 (no write by non-owners):** `generatePDF` still renders and streams the PDF for a non-owner but skips the `Storage::put` and the `$quote->update(['pdf_path'])`. Code: `QuoteController::generatePDF` only. Test: after a member downloads the PDF, `quotes.pdf_path` and `updated_at` are unchanged.
- Options 2 and 3 combine. *Recommendation:* **2 (hide `staff_notes` only) plus 3**, and accept the rest: customer data, notes, terms, items and attachments are the estimate a project team is meant to share, but `staff_notes` is internal by name and a read role should not write someone else's row. Attachments are a judgment call the human should confirm. Whichever is chosen, it is a one-file Developer change (step 7, `QuoteController`) and QA adds the tests above with mutations (restore the field, restore the write).
1. **Visibility widens on deploy, in audit mode too (risk 2).** Intended. It is the only effective guard in audit mode.
2. **Phase 3 alone breaks the legacy member write path's effect** (D2): it must not be deployed without Phase 4. A hot-fix deploy of Phase 3 alone needs the human's approval.
3. **Enforce mode and NULL-project quotes:** a quote with NULL `project_id` (any quote created by the old creation paths after the backfill, or created between Phase 3 and Phase 4) gives `project_unresolved` and blocks even its owner **when enforce is on**. The release does not toggle enforce (Q9) and requires zero NULL quotes.
4. **Members can read but not edit** until Phase 4, because writes are still `user_id = me`.
5. **Audit log semantics (Q13):** `rbac_audit_logs.project_id` changes meaning at deploy; reports and pre-flight Q9 must treat older rows as quote ids.
6. **Delegation (Q11) stays open.** Middleware checks the principal's membership; controllers scope by `Auth::id()`, as today, so a delegate can pass the middleware and get a 404. Phase 3 does not change this. *Recommendation (unchanged):* pass the effective user id into `visibleTo` in Phase 4. This is Q11, not a new decision.
7. **Cross-org membership (Q14)** stays out of scope: a member's `org_id` must equal `projects.org_id`, so the GC-subcontractor workspace case no longer passes `checkPermission`. *(D17.6, corrected)* The §4.5 check in the workspace (`ProjectWorkspaceController.php:53-64`) compares the **quote owner's first active org** with the current org, so a multi-org owner whose first active org differs from the project's org can make a legitimate member get a 403 there. It is pre-existing and not a leak; Phase 4 step 11 replaces it with `project.org_id`.
8. **Cost:** one quote lookup per `quote_param` request and a membership subquery in every list. The B1/staging run records list latency.
9. **Known open items:** rep-agency authorization, `api_system` phase and the org type count are untouched. Open item #4 (audit middleware 403 vs 302) is touched indirectly (all new denials follow it) and is **not** changed; tests assert the current behaviour.
**Needs production data:** Q0 (the operative RBAC mode), the post-backfill count of quotes with NULL `project_id` (must be 0), Q9 (would-blocks on quote routes, to size the widening), and the number of users in more than one org (Q8) for the org-resolution behaviour.

### D14 — Older plan text corrected by this amendment
The count of 11 `project_param` entries (Verified-facts table, "Resolving a project", step 6) is corrected to 9 + 1; step 7's "user_id OR-clause" text now points to D6-D8; the data-scoping paragraph now points to the shared `CurrentOrg` helper; risk 2 points to H7. Nothing else in the older text is changed.

### D15 — Amendment 4 addendum (2026-09-24, from the Developer's stop report and a fresh read-path inventory)

The Developer stopped before editing anything. Each claim was verified against the live code, the contradicting sentences in D4, D7, D8, D9, D10 and the header were edited in place, and this section is the index.

**D15.1 — The missed read path: dashboard counters (`resources/views/user/dashboard.blade.php:15,42,45,66`). NEW HUMAN DECISION H8 (behavioural).**
Verified: the view runs `Quote::where('user_id', me)` three times (quote count `:15,42`, status chart `:45`, recent quotes `:66`), next to the same per-user queries for orders and lists. It is rendered by `UserWorkspaceController::index`, whose own "My Projects" panel already covers shared quotes.
- *Provisional default (recommended): leave the counters owner-only.* They are personal activity counters (the same page counts the user's own orders and lists), so no data is widened and the counters (lines 15, 42, 45, 66) are not touched. The only Phase 3 edit to this view is the crash fix at `:84` (D16). Shared quotes are already listed in "My Projects". Phase 4's Projects page is the place for project-level totals.
- *Option B:* count everything the user can see (`Quote::visibleTo`). It needs a controller change to compute the three values and a view change to use them (or an inline `visibleTo` in the view), so it would break D9 and touches a view outside Phase 3.
- Either way the decision changes what users see on their dashboard, so it is the human's. Test R11 asserts the default; the mutation entry is in D10.

**D15.1a — Inventory: every `Quote`, `quotes` and `user_id` read path in `app/`, `resources/views` and `routes/`** (fresh grep on 2026-09-24; there is no `routes/api.php`, no Livewire, no export class touching quotes; `app/Http/Controllers/Admin/*`, the DataTables and the Imports contain no quote query, and the admin dashboard has none):
| Location | What | Decision |
|---|---|---|
| `QuoteController.php:53` list, `:121` total | reads | D7 rows 1-2 (dual-read) |
| `:142-146` details | read | D7 row 3 |
| `:873-876` PDF, `:894-897` preview | reads (the PDF also writes `pdf_path`, H7) | D7 rows 4-5 |
| `:29` `index` | returns the view only | none |
| `:219` (saved list), `:224`, `:297`, `:303` (customer and list lookups), `:399`, `:444-448`, `:493-503`, `:525`, `:547`, `:595`, `:625`, `:705` (customers), `:766` (products), `:830` (services) | writes, creation, and per-user lookups | Phase 4 (unchanged, `user_id = me`); they don't expose other users' quotes |
| `app/Support/QuotePdfPresenter.php` | pure presenter of an already-authorized quote; no query | none |
| `UserWorkspaceController.php:42` My Projects, `:75` pending approvals | reads | D7 row 6 (the dead `pending_approval` status query gets the same id set) |
| `resources/views/user/dashboard.blade.php:15,42,45,66` | inline owner-only reads | H8, unchanged |
| `OrgAdminController.php:564` list | read | D7 row 7 |
| `OrgAdminController.php:597` (`addProjectMember` ownership check), `PlanCrosswalkController.php:52,62` (`store`) | writes | Phase 4 |
| `PlanCrosswalkController.php:31` | read (throws today) | D8 |
| `ProjectWorkspaceController.php:31-81` | bound quote, legacy member and crosswalk lists by `quote_id` | D8 (minimal fix only) |
| `resources/views/user/layouts/sidebar.blade.php:81-82`, `frontend/workspace.blade.php:106`, `user/project-workspace/show.blade.php:39,177,222` | links only, no queries; the workspace view passes `project_id => $quote->id` as the legacy quote-id param | none (Phase 4 retires the param, B14.4) |
| `frontend/quotes/index.blade.php`, `frontend/lists/listDetail.blade.php:1829-1845` | front-end calls to the routes above | none |
| `Project::quotes`, `ProjectMember::quote`, `PlanCrosswalk::quote`, `QuoteItem::quote` | relations only | none |

**D15.2 — Member chips in `OrgAdminController::projects` (D7 row 7).** Verified: the backfill leaves legacy rows untouched and inserts project rows, so showing both would list a backfilled member twice, and the legacy chip's remove button (`removeProjectMember`, `:623-631`) would deactivate only the legacy row, which grants nothing after step 5, while the project row keeps the access. Rule (now in row 7): for a quote **with** a `project_id` the page shows **project rows only**; for a quote with a **NULL** `project_id` it shows **legacy rows only**. Removal is unchanged: it deactivates the row whose chip was clicked. Because a project-row chip is the only chip shown for a project quote, the removal takes the access away. Members of one project are shown under each of its quotes (project-level membership); Phase 4 replaces this page. The add form still writes a legacy row, which for a project quote is not shown and grants nothing; that is the D13.2 consequence, and the reason Phase 3 is never deployed alone.

**D15.3 — Non-numeric or absent `project_param` (D4).** A scalar that is not a positive integer, or an absent param, is `project_unresolved` for both `quote_param` and `project_param`: `projectId` NULL, no `checkPermission` call. *Audit mode:* a `would_block` row (`reason = project_unresolved`, `quote_id`/`project_id` NULL) and the request passes to the controller. *Enforce mode:* a `blocked` row, and the existing 403 JSON / 302 back response (open item #4 unchanged). A well-formed id with no matching project or membership gives `no_grant_or_not_project_member`.

**D15.4 — Quotes in a trashed project.** They are **not** visible through membership: `Project::visibleTo` (and the D3 query) exclude trashed projects, and D7 row 7 counts only non-trashed projects. Their owner still sees them through the `user_id` clause. This is consistent with D3 and Q6 (a project can't be deleted while live quotes exist, so this only arises from manual data or the backfill's all-trashed reuse) and needs no decision.

**D15.5 — `app/Support/Rbac/Rbac.php:12-13` (stale docblock).** It shows `Rbac::check(..., 'estimate_management', 'F', $quoteId)`; after step 5 a quote id there would be read as a project id (the collision hazard in D3). **Fix, doc only:** the example and the `@param` use `$projectId` (a `projects.id`). The file is added to D9. No code or behaviour change, so it is not a human decision. No caller passes a project id.

**D15.6 — Workspace with NULL `project_id`.** Explicit deny before `checkPermission`, as written in D8 (302 to `org-admin.projects.index` with the existing `error` flash, all modes, no 500). Test R6 covers it with a user holding the org-level grant.

**D15.7 — Tests that change.** Only `PermissionServiceTest::test_project_scoping_requires_membership` (`:85-111`, with its `makeProject` helper) asserts legacy `quote_id` membership and must be rewritten (D3). A grep of `tests/` for `project_members`, `project_param`, `quote_param` and `checkPermission` calls with a project id finds no other affected test: `ProjectSchemaTest` and `ProjectBackfillTest` only exercise the schema and the command, and `RbacTestCase` only lists migrations.

**D15.8 — Final Developer file list (Phase 3).** *Modify:* `app/Services/Rbac/PermissionService.php`, `app/Http/Middleware/RbacAudit.php`, `config/route_permission_map.php`, `app/Models/Quote.php` (scope), `app/Http/Controllers/Frontend/QuoteController.php` (read queries only), `UserWorkspaceController.php`, `OrgAdminController.php` (`projects()`), `PlanCrosswalkController.php` (`index`), `ProjectWorkspaceController.php` (D8), `app/Support/Rbac/Rbac.php` (docblock), `resources/views/user/dashboard.blade.php` (line 84 only, D16), `tests/Feature/Rbac/PermissionServiceTest.php`, `TESTING.md`. *Create:* `app/Support/Rbac/CurrentOrg.php`, `tests/Feature/Rbac/ProjectTestCase.php`, `ProjectMembershipTest.php`, `ProjectReadPathsTest.php`, `RoutePermissionMapTest.php`. **No route, migration or Phase 4 file, and no view except the one-line fix at `dashboard.blade.php:84` (D16).**

### D16 — Amendment 4 addendum 2 (2026-09-24, from QA's Phase 3 defect: dashboard 500 for a project member)

QA found that the dashboard returns 500 for a project member whose role holds F on `project_management` (for example an estimator). Verified against the live code: `resources/views/user/dashboard.blade.php:84` calls `route('org-admin.projects')`, which does not exist. The real names are `org-admin.projects.index`, `org-admin.projects.members.store` and `org-admin.projects.members.destroy` (`routes/web.php:139-141`). The defect is pre-existing, but Phase 3 makes the "My Projects" panel (and so that link) reachable for every project member, because visibility now comes from projects membership.

**Decision (a): fix it now, in Phase 3.** It is a crash fix with no other user-visible change: the link points to the page it always meant to open, so it is **not a human decision**.
- **Exact Developer step (one line):** in `resources/views/user/dashboard.blade.php:84` change `route('org-admin.projects')` to `route('org-admin.projects.index')`. Nothing else in the file changes. The counters at `:15,42,45,66` stay owner-only (H8, D15.1). D9 and D15.8 are updated in place to allow exactly this line.
- **Why not defer (option b):** Phase 3 is never deployed alone (B6) and no production window exists between Phase 3 and Phase 4, so deferring would be safe in production. But the dev branch, QA and any staging run would still hit the 500, Phase 3's own tests could not exercise the dashboard, and a one-line fix removes a dependency on ordering. Deferring buys nothing.
- **Test (QA, `ProjectReadPathsTest`):** R14: as a project member with F on `project_management` (estimator), `GET` the dashboard with a visible project in "My Projects": status 200, and the rendered page contains the URL of `route('org-admin.projects.index')`. **Mutation:** restore `route('org-admin.projects')`; R14 must fail. R11 (counters owner-only) stays.
- **Other references to non-existent route names:** a fresh scan of every `route('...')` and `->route('...')` in `app/` and `resources/` against `php artisan route:list` (265 named routes) finds only:
  - `resources/views/user/dashboard.blade.php:84` `org-admin.projects` (fixed here);
  - `resources/views/user/rfq/create.blade.php:70` `route('org-admin.connections')`; the real name is `org-admin.connections.index` (`routes/web.php:135`). This is pre-existing, is on the RFQ create page (shown when the org has no trading partners), and is **not** on a panel Phase 3 makes reachable, so it is **out of scope** here. Recommendation: fix it in a separate one-line follow-up; it is a crash fix and needs no decision;
  - `app/Http/Controllers/Auth/VerificationController.php:39,41` are `$request->route('id'|'hash')` parameter reads, not route names (false positives).
  The dashboard's other links, `:107` and `:164` (`project.workspace`), use an existing name. The earlier plan note that Phase 4 fixes route names at `dashboard.blade.php:84,107,164` is corrected: only `:84` is broken; `:107` and `:164` only change their target in Phase 4. `ProjectWorkspaceController` already got the same fix in D8.
- **Scope check for the Architect's compliance pass:** `git diff` of `dashboard.blade.php` must show exactly that one line.

### D17 — Amendment 4 addendum 3 (2026-09-24, from the Verifier review "Phase 3 code and tests", P3-01 to P3-08)

Each claim was verified against the live code: `UserWorkspaceController.php:34-47` (the legacy union with no org or membership tie), `QuoteController.php:143-147,874-878,895-899` (membership is the only test in the reads), `getEstimateDetails` returning `staff_notes`, `notes`, `terms_and_conditions`, `customer_*` and attachment URLs, and `generatePDF` writing `pdf_path`. The contradicting sentences in D7 row 6, D13 (H7, item 7) and the header were edited in place.

**D17.1 — P3-01 (defect, should-fix): the dashboard's legacy union. Not a human decision** (it enforces D3, Q14 and the decided B4 Option A).
- *Fix (Developer, step 7, `app/Http/Controllers/Frontend/UserWorkspaceController.php` only):* "My Projects" and the pending-approval id set are **only** the quotes whose `project_id` is in `Project::visibleTo(me, org)`. Remove the legacy `ProjectMember` `quote_id` set. Rationale: after the Phase 2 verification run no unattached quote remains (D11), legacy rows never grant (D3), and rows the backfill deliberately did not carry (`membership_org_mismatch.csv`) must not leak a quote's name and item count. The `canReadEstimates` gate and the dead `pending_approval` query stay as they are.
- *Tests (QA):* T-dash1: with only legacy rows, none of these puts a quote on the dashboard: an **active legacy row in the current org on a quote whose project is in another org**, an **inactive legacy row**, a legacy row in **another org**, and a legacy row on a **NULL-project** quote; and a project member still sees the project's quotes once, no duplicates. *Mutation:* re-introduce the legacy union (with or without the org or `is_active` filter, which retires the former N5 and N6) must fail T-dash1.

**D17.2 — P3-02 (behaviour): member reads and roles in audit mode. NEW HUMAN DECISION H9.**
Verified (scratch evidence in the review): the controller reads gate on project membership only (`Quote::visibleTo`, D6); the role (`estimate_management:R`) is checked only by `RbacAudit`, which in audit mode (production) logs a `would_block` and passes through. So a project member with **no** `estimate_management` grant (for example `superintendent`, or a role added to a project by mistake) gets 200 on a teammate's quote and sees it in the list. Before Phase 3 such a user could read only their own quotes. Owners are unaffected. The same holds for the crosswalk index's project clause. The workspace already checks the role (its own `checkPermission` with a project id) and the dashboard already gates on `canReadEstimates`.
| Option | What changes for users | Cost and risk | Phase 4 and enforce |
|---|---|---|---|
| **A: accept until enforce is on** (the current code) | Any project member, whatever their role, reads every quote in the project. The `would_block` rows are the record | none; the widest exposure, in production, immediately | With enforce on, the middleware closes it; audit mode never does |
| **B: gate the member clause in the controllers on `checkPermission(estimate_management, R)`** | A member without R on `estimate_management` sees only their own quotes, as before; owners and members with the role are unchanged | one extra permission check per request (cached matrix plus one role query); `Quote::visibleTo` gets a flag or the controller decides whether to add the member clause; `PlanCrosswalkController::index` gets the same gate | Phase 4 write routes then gate on the write level in the controller too (O/S/F), a consistent pattern; redundant but harmless once enforce is on |
| **C: require the role inside `Quote::visibleTo`** | same as B | couples the scope to `PermissionService`; the scope is also used in subqueries; harder to test | same |
**DECIDED 2026-09-24: Option B (D18).** The earlier provisional default (A) is void. *The original recommendation was **B**,* since production runs in audit mode and controllers are the only effective guard (risk 2), and the check mirrors the dashboard. *(Decided; D18 is the implementable spec.)* Developer step 7, `QuoteController` reads (list, total, details, PDF, preview) and `PlanCrosswalkController::index`, no other file (D9 is unchanged); tests (QA): a member with a wrong role (`superintendent`, `viewer_read_only` where it has no estimate grant) in **audit mode** gets 404 on details, PDF and preview, does not see the quote in the list or the total, and the crosswalk index shows none of the project's rows, while the owner is unaffected and a member with the role is unchanged; *mutation:* remove the role gate, and those tests fail. D4 "Modes" and D7 are amended by D18 (the controller reads gate the member clause on the role).

**D17.3 — P3-03 (privacy, H7 concretely): H7 is rewritten in place (D13)** with the exact list of what a member can now see and do, including `staff_notes`, attachment names and public URLs, and the fact that a read role writes the owner's `pdf_path`, `updated_at` and the shared PDF file. Options: accept all, hide `staff_notes` (and optionally attachments) from non-owners, no `pdf_path` write by non-owners. *Decided (2026-09-24): hide `staff_notes` and stop the non-owner write; accept the rest (D18.4-D18.5).* Effect on the Developer list: none for the default; options 2/3 change `QuoteController` only (already on the list).

**D17.4 — P3-04 (note).** With the session org set to org B, the owner of a quote in a project of org A gets a 403 in **enforce** mode on their own quote (the B4 org tie), while audit mode logs `would_block` and the controller allows it through `user_id`. Recorded: under enforce, an owner must act in the project's org context. Enforce isn't on in production (Q9). No change.

**D17.5 — P3-05 (test gaps).** *Tests (QA):*
- **N5, N6:** covered by T-dash1 (D17.1). The mutants disappear with the legacy union; the test proves an inactive or other-org legacy row stays off the dashboard, and the mutation "re-introduce the union" is the entry.
- **N2:** T-xw1: a user with an active role in org A and a **stale session org** (org B, no active role there) calls `PUT plan-crosswalk/{row}` and `DELETE plan-crosswalk/{row}` on a row of org A: both succeed (the validated org falls back to A); the same user cannot update or delete a row of org B. *Mutation:* `PlanCrosswalkController::currentOrgId()` reverts to the raw session org; T-xw1 must fail.
The mutation table in D10 gains these rows.

**D17.6 — P3-07 (note): D13.7 corrected in place** (the workspace §4.5 check is not "only reachable for the project's own org"; see D13 item 7).

**D17.7 — P3-08 (regressions noted, no action):** the org-admin add form still writes legacy rows, which grant nothing and are not shown for a project quote (D15.2, D13.2); members read but can't edit (D13.4); owners of NULL-project quotes are blocked in enforce mode (D13.3); `getEstimatesList` loads every visible quote in memory for the total (`calculateTotal()` per quote), so the cost grows with the visible set (D13.8); `GET org-admin/projects` stays org-wide in audit mode and now also lists quotes of ex-members.

**D17.8 — Checkpoint 2 documentation task: `TESTING.md` (D9 required it; the Verifier found it missing).** Owner: the Developer (doc task, at Checkpoint 2, after the final test run). Record exactly:
1. the **new test files** and their purpose: `ProjectSchemaTest`, `ProjectBackfillTest`, `ProjectMembershipTest`, `ProjectReadPathsTest`, `RoutePermissionMapTest`, and the `ProjectTestCase` base, plus the rewrite of `PermissionServiceTest::test_project_scoping_requires_membership`;
2. the **counts from the final `php artisan test --filter=Rbac` run** (passed, failed, assertions, dated), plus the per-file test-method counts (data-provider cases add to the run total);
3. that the **known-failing baseline is unchanged**: the same 3 failures by name (`AuditMiddlewareTest` enforce mode, the two `SeedMatrixTest` cases), with the reworded history line (the 55 passed / 3 failed figure is the pre-project baseline);
4. the note under known failure #1 that **non-JSON denials redirect back (302) by design** in `RbacAudit::fail()`, and that new tests assert denials with JSON requests;
5. the RBAC test patterns: the collision fixture (a quote id equal to another project's id) and the mutation-proof practice (scratch copy, never the tracked files).
No other `TESTING.md` change.

**D17.9 — B1 restored-copy checklist for Phase 3 (from the Verifier; QA records every result in PROGRESS.md).** Also added to the B1 section as "B1 Phase 3 checklist".
1. `SELECT COUNT(*) FROM quotes WHERE project_id IS NULL` is 0 (trashed included) before release.
2. The real `rbac_mode` row (Q0), and Q9 would-block counts on quote routes to size the widening.
3. Query plans and latency of `quotes.project_id IN (visibleTo subquery)` at the real `quotes` size, index use of `project_members(project_id, user_id)`, and list latency with the in-memory total.
4. Multi-org users' real session behaviour: `CurrentOrg` and the middleware agree, and the enforce-mode owner case (D17.4).
5. The route-binding order in the real middleware stack: a bound `Quote` model versus a scalar reaching `RbacAudit`.
6. The PDF engine and the public-disk write by a non-owner, and what a member's details JSON exposes (H7).
7. The dashboard for a multi-org owner with legacy rows in a second org (D17.1).

**Summary of decisions and code changes**
| Finding | Code change | Step, file | Human decision |
|---|---|---|---|
| P3-01 | yes | step 7, `UserWorkspaceController.php` | no |
| P3-02 | none by default (A); B needs `QuoteController` reads and `PlanCrosswalkController::index` | step 7 | **H9** |
| P3-03 | none by default; options 2/3 need `QuoteController` (`getEstimateDetails`, `generatePDF`) | step 7 | **H7 (rewritten)** |
| P3-04, P3-07, P3-08 | none (plan text) | | no |
| P3-05 | tests only | QA | no |
| TESTING.md | doc | Checkpoint 2 | no |
The Developer file list (D9, D15.8) is unchanged: every file above is already on it.

### D18 — Amendment 4 addendum 4 (2026-09-24): H9 = Option B and H7 decided; the implementable spec

The human decided H9 (project-member reads also require `estimate_management:R`, so audit mode denies role-less members too) and H7 (hide `staff_notes` from non-owners; no `pdf_path` write by non-owners; attachment URLs and customer data stay visible). H8 is unchanged (dashboard counters owner-only). D17.2/D17.3 wording that called these provisional was edited in place. Facts re-verified in the live code: `staff_notes` appears in exactly one output, the details JSON (`QuoteController.php:199`); the front end reads it null-safely (`frontend/quotes/index.blade.php:1344`, `quote && quote.staff_notes ? ... : ''`); `QuotePdfPresenter` and the PDF template contain **no** `staff_notes` (the PDF shows `notes`, which stays visible); `generatePDF` is the only place that writes `pdf_path` for a read (`:887`), `previewPDF` streams without writing (`:904`), and `pdf_path` is otherwise used only to delete the file when the quote is destroyed (`:533`).

**D18.1 — The rule and where it is applied.** *Rule:* the **member clause** of `Quote::visibleTo` (the `project_id IN Project::visibleTo(me, org)` part) applies only when the acting user holds `estimate_management` **R** in the current org: `checkPermission(userId, orgId, 'estimate_management', 'R')`, called through `PermissionService` with the validated `orgId` (D6) and **no project id** (project membership is already what `visibleTo` tests; passing one would repeat it). The **owner clause** (`user_id = me`) is unchanged and needs **no role**: owners keep exactly today's audit-mode access, so H9 only removes the widening. A user with no org (`orgId` NULL) has only the owner clause. Delegation is unchanged (the check resolves the effective user, Q11 stays open).
*Implementation:* `Quote::scopeVisibleTo($query, int $userId, ?int $orgId, bool $members = true)`: with `$members = false` only the owner clause is applied. Each controller computes the flag with the same call and passes it in. Legacy-row (`project_members.quote_id`) access grants nothing anywhere (D3), so it is never a path to a quote.

**D18.2 — Every read path, exact behaviour.**
| Read path | Behaviour |
|---|---|
| `QuoteController::getEstimatesList` (list, `:53`) | **Filter, not deny.** A user without the role sees only their own quotes; the response shape and status are unchanged. |
| same method, total (`:121`) | The **same** flagged scope as the list, so the total always matches the list. |
| `getEstimateDetails` | Single quote: flagged scope + `where id` + `firstOrFail`. A role-less member's request for a teammate's quote is a **404** (the existing "not visible" response, no redirect or flash: this is a JSON endpoint). A member with the role gets 200 with the D18.4 `staff_notes` rule. |
| `previewPDF` | Same as details (404 for a role-less member; 200 stream for a member with the role; never writes). |
| `generatePDF` | Same as details for visibility; the persistence rule is D18.5. |
| `index()` (view), `getCustomersForEstimate`, `getProductVariationsForEstimate`, `getServicesForEstimate` | Not quote reads of other users (view only, or per-user `user_id` lookups); **unchanged**. |
| `duplicate`, `update`, `saveEditor`, `updateItem`, `destroyItem`, `destroy`, `store`, `createFromList` | Writes and per-user lookups: **unchanged** (`user_id = me`, Phase 4). `duplicate` reads only the user's own quote, so it never reads a teammate's. |
| `PlanCrosswalkController::index` | Both clauses that come from membership are gated by the flag: the visible-quote list and rows use `Quote::visibleTo(..., $flag)`, and the `project_id IN Project::visibleTo` row clause is added **only when `$flag`** is true. A role-less member sees rows only for their own quotes. The `projects` filter list is the same flagged set. The page stays 200 (filter, not deny). |
| `ProjectWorkspaceController::show` | **Unchanged and already role-gated:** its own `checkPermission(estimate_management, R, project_id)` (role plus membership) runs in every RBAC mode. A role-less member, a non-member and a NULL-project quote get the D8 redirect (302 to `org-admin.projects.index`, flash `error`). Owners are not exempt here (membership is required), as today. |
| `UserWorkspaceController::index` "My Projects" | Already gated by `canReadEstimates` (`estimate_management:R`); **the only change is P3-01 (D18.6)**. |
| `OrgAdminController::projects` | An org-admin surface guarded by `project_management:R` and org-wide by design; **unchanged** (not a member read). |
| `resources/views/user/dashboard.blade.php` counters | Owner-only (H8), unchanged. |

**D18.3 — Modes.** *Audit* (production): a role-less member's request to `quotes/{id}/details`, `pdf-preview` or `pdf` for a teammate's quote writes the middleware's `would_block` row (reason `no_grant_or_not_project_member`, because the project id is resolved but the role check fails) and **the controller then returns 404**. The list and the crosswalk page return only the user's own data. *Enforce:* the middleware blocks first (403 JSON / 302, open item #4 unchanged) and the controller is never reached. An **owner** without the role keeps controller access in audit mode (as today) and is blocked by the middleware in enforce mode, exactly as before. **No new human decision:** this is the decided H9 applied uniformly.

**D18.4 — `staff_notes` (H7, decided).** In `getEstimateDetails` the `staff_notes` entry of the JSON response is the stored value **only when `$quote->user_id === Auth::id()`**; for anyone else it is **`null`** (the key stays present, so the response shape and the null-safe front end are unchanged; no view change). Nothing else changes: `notes`, `terms_and_conditions`, `customer_id`, `customer_name`, `customer_address`, the items with rates, and `attachments[].name/url` stay visible to members (decided). The PDF and preview do not contain `staff_notes` today (presenter and template verified), so **no PDF change** is needed, and `QuotePdfPresenter` and the template are not touched. The duplicate path copies `staff_notes` only from the user's own quote (`:570`), so it is unaffected.

**D18.5 — `pdf_path` (H7, decided).** In `generatePDF` the PDF is rendered and returned as a download for every visible viewer, but **the two persisting statements run only when the viewer is the quote's owner (`$quote->user_id === Auth::id()`)**: `Storage::disk('public')->put($pdfPath, ...)` and `$quote->update(['pdf_path' => $pdfPath])`. For a non-owner nothing is stored: no file is written, `pdf_path` and `updated_at` are unchanged. The download is generated on the fly (`$pdf->download(...)` streams the rendered output), so the member's experience is unchanged. **Effect on the owner:** none. The owner's own PDF generation still writes the file and `pdf_path` exactly as today; a member's download never changes the owner's row or the shared public-disk file, so the owner's later view of the quote (including `pdf_path` and `updated_at`) is unaffected by teammates. `previewPDF` already writes nothing.

**D18.6 — P3-01 dashboard fix (D17.1 restated).** `UserWorkspaceController::index`: the "My Projects" and pending-approval id set is only `Quote::whereIn('project_id', Project::visibleTo(me, org)->select('projects.id'))->pluck('id')`. The legacy `ProjectMember` `quote_id` set is removed. The `canReadEstimates` gate and the dead `pending_approval` query stay. No other file.

**D18.7 — Developer steps, in order** (all files are on the approved D9/D15.8 list; **no view, PDF presenter, route, migration or Phase 4 file changes**):
1. `app/Models/Quote.php`: add the `$members` argument to `scopeVisibleTo` (D18.1).
2. `app/Http/Controllers/Frontend/QuoteController.php`: a private helper that returns the flag (`orgId !== null && checkPermission(userId, orgId, 'estimate_management', 'R')`); use it in the list, the total, details, `generatePDF` and `previewPDF` (D18.2); the `staff_notes` rule in details (D18.4); the owner-only persistence in `generatePDF` (D18.5). Add the `PermissionService` import.
3. `app/Http/Controllers/Frontend/PlanCrosswalkController.php`: the flag in `index` (D18.2).
4. `app/Http/Controllers/Frontend/UserWorkspaceController.php`: drop the legacy union (D18.6).
5. Tests and `TESTING.md` (D17.8).

**D18.8 — QA test list and mutation proof** (sqlite; each in **audit and enforce** mode where a request goes through the middleware; enforce tests use JSON requests, per open item #4). Fixture: an owner with a quote in project P1 (with `staff_notes`, `notes`, an attachment, a customer), a teammate member with the role (estimator) and a role-less member (`superintendent`, an estimating role missing) of P1, a non-member, and the collision fixture (D10).
| Test | Assertions | Mutation (scratch copy) that must fail it |
|---|---|---|
| **H9-1 list** | A member with the role sees teammates' P1 quotes plus their own. A role-less member sees **only their own**, and the total equals the list set. Owner without the role still sees their own (audit mode). | remove the flag in the list or total |
| **H9-2 details** | Role-less member: audit mode 404 **and** a `would_block` row; enforce mode 403 and the controller does not run. Member with the role: 200. Owner: 200 (audit). Non-member: 404. | remove the flag in details |
| **H9-3 pdf and preview** | Same matrix as H9-2 for `pdf` and `pdf-preview` (with `Storage::fake`). | remove the flag in `generatePDF` or `previewPDF` (each separately) |
| **H9-4 crosswalk index** | Role-less member: 200, none of the project's crosswalk rows and none of the project's quotes in the filter list, but their own quotes' rows; member with the role sees all. | drop the flag on the row clause, or on the quote list |
| **H9-5 owner needs no role** | An owner without `estimate_management` still gets 200 on their own quote in audit mode (no widening or narrowing of today's behaviour). | require the role for owners |
| **H9-6 workspace and dashboard unchanged** | Role-less member: workspace 302 with the flash; dashboard "My Projects" empty. | remove the workspace check or the `canReadEstimates` gate |
| **H9-7 group separation (P3F-01)** *(D19)* | A member with **`procurement:R` but no `estimate_management`** is denied: single reads (details, pdf, preview) give 404 in audit mode, the list and total exclude the teammates' quotes, and the crosswalk index shows only own-quote rows. | swap the group in `canReadTeamQuotes` (`estimate_management` to `procurement`) |
| **H9-8 group separation, allowed side** *(D19)* | A member with **`estimate_management:R` but no `procurement`** is allowed on the same paths: 200 on details, pdf and preview, teammates' quotes in the list and total, the project's rows on the crosswalk index. | the same swap; also raise the required level to `F` or `S` (this member holds only R, so H9-8 must fail) |
| **H7-1 staff_notes** | Member's details JSON: `staff_notes` key present and `null`; owner's has the stored text; `notes`, `terms_and_conditions`, `customer_name`, `customer_address` and `attachments[].url` still returned to the member. The PDF view data (`QuotePdfPresenter::present`) has no `staff_notes` key. | return the real value to non-owners; or hide it from the owner |
| **H7-2 pdf write** | A member (with the role) downloading the PDF gets 200 and `quotes.pdf_path`, `updated_at` and the fake public disk are **unchanged**; the owner's download writes the file and sets `pdf_path`. | remove the owner check (non-owner writes) or invert it (owner doesn't write) |
| **T-dash1** (D17.1) | Legacy rows (active in the current org on another org's project quote, inactive, other org, NULL-project quote) put nothing on the dashboard; project members see the project's quotes once. | re-introduce the legacy union (with or without the org or `is_active` filter) |
| **T-xw1** (D17.5) | `PUT`/`DELETE plan-crosswalk/{row}` with a stale session org act on the user's real org's row and never on another org's row. | `currentOrgId()` reads the raw session org |
The D10 mutation table gains these rows. The earlier rows for `visibleTo` stay.

**D18.9 — What sqlite cannot prove** (B1 checklist, D17.9 items 3, 4 and 6): the real role matrix and its cache under production data, the cost of the extra `checkPermission` per read, the PDF engine's real output and the public-disk write on the production filesystem, and multi-org real sessions.

**D18.10 — Summary.** No new human decision: H7, H8 and H9 are decided, and D18 applies them uniformly. Code changes: `Quote.php` (scope), `QuoteController.php` (list, total, details, PDF, preview, `staff_notes`, PDF persistence), `PlanCrosswalkController.php` (`index`), `UserWorkspaceController.php` (dashboard union). **The Developer file list is unchanged: the D15.8 list, with no view, presenter or route change.**

### D19 — Amendment 4 addendum 5 (2026-09-24, from the Verifier final pass, survivor P3F-01)

**Test coverage only. No code change is needed.** The mutation "swap the permission group inside `QuoteController::canReadTeamQuotes` from `estimate_management` to `procurement`" survived because every fixture member holds both groups or neither. Tests H9-7 and H9-8 (added to the D18.8 table in place) separate the two groups.
- **Fixture roles (verified in `database/seeders/Rbac/data/role_permission_matrix.php`, with levels):** for **procurement but no estimate_management**, use `order_fulfillment_csr` (`procurement` R) (`subcontractor_pm` is the alternative, `procurement` O); for **estimate_management but no procurement**, use `architect` (`estimate_management` R) (alternatives: `engineer` R, `contract_manager` R). The control roles stay `estimator` (both, F) and `superintendent` (neither). The Developer and QA must re-read the matrix if a seed changes; a fixture assertion `levelFor(role, group)` guards the assumption so a re-seed can't silently make the test meaningless.
- **Each member is enrolled in project P1** (active `project_members` row, org tie) and the owner's teammate quotes are in P1, so the only difference between the two members is the role.
- **Paths, in audit mode** (this is where the controller check is the deciding one): `GET quotes/list` (list and total), `GET quotes/{id}/details`, `GET quotes/{id}/pdf-preview`, `GET quotes/{id}/pdf`, and `GET plan-crosswalk`.
  - *procurement-only member (`order_fulfillment_csr`):* 404 on the three single reads, the list and total exclude teammates' quotes, the crosswalk index shows only own-quote rows, and each request writes a `would_block` row.
  - *estimate-only member (`architect`):* 200 on the three single reads, teammates' quotes present in the list and total, the project's crosswalk rows present, and no audit row.
- **Enforce mode** (JSON requests, open item #4 unchanged): the procurement-only member is blocked by the middleware (403) on every path, the estimate-only member reaches the controller (200). This half does **not** discriminate the controller's group (the middleware decides first); it is kept to prove the two modes agree.
- **Mutations (scratch copy) and the test that must fail:** swap the group in `canReadTeamQuotes` (H9-7 and H9-8 fail); the same swap in the `PlanCrosswalkController::index` call (H9-7 and H9-8 fail, crosswalk part); raise the required level to `F` or `S` (H9-8 fails, since `architect` holds only R); lower it is impossible (R is the weakest level).

**Attachments (H7).** The human's H7 decision already leaves **attachment names and public URLs visible to members** (and customer data, `notes`, `terms_and_conditions`, items and rates). Checkpoint 2's acknowledgement of H7 is therefore one line: "members see everything on a teammate's quote except `staff_notes`; only the owner writes `pdf_path`."

**TESTING.md update required by D9 (exact checklist for Checkpoint 2; owner: the Developer, after the final suite run).** Record, in this order:
1. Update the header line under "Known-failing baseline" to say the RBAC suite is `<N> passed / 3 failed, <A> assertions` **as of the Checkpoint 2 run (date)**, with `<N>` and `<A>` copied from that run; keep the sentence that the 3 failures are pre-existing, and keep the three failures listed by name and test file unchanged.
2. Add a line stating that "55 passed / 3 failed, 113 assertions" was the baseline **before** the projects work.
3. Under known failure #1 (`AuditMiddlewareTest::enforce mode blocks and logs`) add: "Non-JSON denials redirect back (302) by design in `RbacAudit::fail()`; new tests assert denials with JSON requests; this is Known open item #4 and is unchanged."
4. Add a subsection "Projects tests" listing the files and one line each: `ProjectSchemaTest` (schema, guards, `enrol()`), `ProjectBackfillTest` (`projects:backfill`), `ProjectMembershipTest` (membership and `RbacAudit` resolution), `ProjectReadPathsTest` (controller read paths, dual-read, H7/H9), `RoutePermissionMapTest` (map lint), the `ProjectTestCase` base, and the rewritten `PermissionServiceTest::test_project_scoping_requires_membership`.
5. Under "RBAC test patterns" add: the id-collision fixture (a quote id equal to another project's id), the group-separation fixture (D19), that enforce tests use JSON requests, and the mutation-proof practice (a scratch copy of the repo, never the tracked files, each surviving mutant routed back to the Architect).
6. State that tests run on in-memory sqlite and never touch the real DB, and list what sqlite cannot prove by pointing at the B1 checklists (Phase 1, 2 and 3).
No other `TESTING.md` change.


---

# Phase 5 amendment (2026-09-25): tightening, STRICT mode, DRAFT for Checkpoint 1

Status: DRAFT. Nothing here is implemented. Written from the live code at commit a5395e7 (branch `feature/projects-entity`). It supersedes the Phase 5 bullets at PLAN.md lines 102-107 and step 13 (:255-256); those remain the origin of the gate. No new permission group or role (25 groups / 49 roles baseline unchanged). None of the four "Known open items" (rep-agency, api_system phase, org type count, audit 302) is touched. The production audit-mode caveat is (see section 6).

## 1. Scope

**In**
1. Remove the `quotes.user_id` scope clause from `Quote::visibleTo` and every reader of it; remove the quote-keyed clause of the crosswalk index.
2. `quotes.project_id` NOT NULL.
3. `project_members`: delete legacy quote-only rows, drop `quote_id` (unique, FK, column), `project_id` NOT NULL.
4. `plan_crosswalk`: drop `quote_id` (FK, index, column), `project_id` NOT NULL, add `unique(project_id, plan_line_code)`.
5. Remove dead legacy code: `ProjectMember::quote()`, `PlanCrosswalk::quote()`, `quote_id` in both `$fillable`, NULL-project branches, and (after H5) `projects:backfill`.
6. Rewrite the tests that pin the transition.

**Out (explicitly)**
- `quotes.user_id` itself stays: it is the author, drives owner-only rules (staff_notes `QuoteController.php:255`, PDF write `:947`), `destroyUser` (`RbacController.php:501`) and the FK cascade. Only its use as a visibility scope goes.
- `rbac_audit_logs.quote_id` and `RbacAudit` `quote_param` resolution stay (audit trail; quote-keyed routes remain).
- Q5 (moving quotes: still no), Q10 (decided in Phase 4: keep the quote's customer or pick own), Q11 (delegation, still open, separate feature), Q14 (cross-org membership, still out). PLAN.md assigns none of them to Phase 5 as work; Phase 4 already listed them as "not Phase 4", so this amendment states plainly they are NOT Phase 5 either unless the human says so (decision 7).
- Removing `GET projects/{quote}/workspace` 301 (kept one more release, decision 6). No new features.

### Dual-read inventory (live code, file:line)
| Place | What it does today | Phase 5 change |
|---|---|---|
| `app/Models/Quote.php:52-61` `scopeVisibleTo` (`:55` `quotes.user_id = me`, `:58` project OR) | owner OR visible project | new shape, see step A1 |
| Callers: `QuoteController.php:59` (list), `:131` (total), `:177` (`writableQuote`), `:202` (details), `:941`, `:964` (PDF download/preview) | all go through the scope | inherit A1; no per-site edit except tests |
| `PlanCrosswalkController.php:53` `whereIn('quote_id', Quote::visibleTo(...))` with `:56` project OR | legacy quote-scoped rows plus project rows | keep only the `project_id IN Project::visibleTo` branch (still gated on `$members`); the `$members` false case returns no rows |
| `PlanCrosswalkController.php:146` `abort_if($row->project_id === null, 404)` | NULL-project rows | delete (column NOT NULL) |
| `ProjectWorkspaceController.php:90` `legacyRedirect` NULL check | NULL-project quote | delete the NULL half |
| `QuoteController.php:181` `$quote->project_id !== null ? ... : null`, `:612` duplicate 422 on NULL | NULL-project fallbacks | always pass `(int) $quote->project_id`; delete the 422 |
| `RbacAudit.php:106-116` `project_unresolved` on NULL project | fail closed | keep the branch for a missing quote; the NULL-project reason becomes unreachable for existing rows (do not remove: fail-closed for deleted quote ids) |
| `RbacController.php:501` `->whereNotNull('project_id')` | refusal counts only project quotes | drop `whereNotNull` (every quote is in a project); keep `withTrashed()` |
| `app/Models/Rbac/ProjectMember.php:15,34-36`, `app/Models/PlanCrosswalk.php:20,41-44` | legacy `quote_id`, `quote()` | remove |
| `app/Console/Commands/BackfillProjects.php`, `app/Support/ProjectBackfillPlanner.php` | read `quote_id`, `quotes.project_id IS NULL` | delete after H5 (step B4) |
| Already project-only, no change: `PermissionService.php:124-135`, `Project::visibleTo`, `ProjectController`, `ProjectMemberController`, `UserWorkspaceController.php:35` | | none |
Not dual-read (do not touch): `QuoteController.php:280,285,361,367,381,615,687,771,832,901` (customer, saved-list and user-product ownership).

## 2. Ordered steps

Split into **5a (code, reversible by redeploy)** and **5b (schema, irreversible)**, in this order, so the running code never depends on a column that is about to disappear.

### 5a. Code tightening (deploys on the OLD schema)
- **A1. New `Quote::visibleTo`.** Body: when `$orgId === null` return `whereRaw('0 = 1')`; else `quotes.project_id IN (Project::visibleTo($userId,$orgId)->select('projects.id'))` AND (`$members` OR `quotes.user_id = $userId`). `user_id` remains only as the author check for callers without `estimate_management:R` (H9 role gate preserved), never as scope. Why irreversible in effect: users who own a quote but are not (or no longer) members of its project, or who act in another org, lose access; there is no owner bypass (Q1). Verify: pre-flight P2 (section 3), rewritten Phase 3 tests (section 5).
- **A2. Crosswalk index** narrowed to the project branch (table above). Verify: read-path tests.
- **A3. Delete NULL-project branches** (`:146`, `:90`, `:181`, `:612`, `RbacController :501`). Behaviour identical once P1 = 0.
- **A4. Remove `quote()` relations and `quote_id` from both `$fillable`** only in 5b (they exist for the still-present columns); in 5a leave them.
- Verification: full Rbac suite in audit and enforce; suite baseline 566 passed / 3 known failures (TESTING.md) must be unchanged except the intentionally rewritten tests. Rollback: redeploy the previous code (FTP, B12 file diff); no data touched.
- Soak: at least the agreed period (decision 1) with `rbac_audit_logs` watched for `project_unresolved` and `would_block` on quote routes.

### 5b. Schema tightening (one maintenance window, one backup, three migrations, each one table, guarded)
Names `database/migrations/<date>_tighten_quotes_project_id.php`, `..._tighten_project_members.php`, `..._tighten_plan_crosswalk.php`. MariaDB DDL commits implicitly, so each is written to be resumable (check `Schema::hasColumn`/index existence before each statement) per the B5 pattern.
- **B1. quotes.** Guard, then `project_id` `->nullable(false)->change()` keeping the FK `restrictOnDelete`. Not irreversible by itself (`down()` can re-nullable), but treated as one gate. Verify: `SHOW CREATE TABLE quotes` keeps the FK; insert with NULL fails.
- **B2. project_members.** Guard, then in this order: `DELETE FROM project_members WHERE project_id IS NULL` (irreversible data loss; the quote-only rows are the superseded model), `dropForeign(['quote_id'])` (FK `project_members_quote_id_foreign` uses the composite unique index, so the FK must go first on MariaDB), `dropUnique(['quote_id','user_id'])`, `dropColumn('quote_id')`, `project_id` NOT NULL (FK cascadeOnDelete kept). Irreversible: the rows and the column. Rollback = backup restore.
- **B3. plan_crosswalk.** Guard, then `dropForeign(['quote_id'])`, `dropIndex(['org_id','quote_id'])`, `dropColumn('quote_id')`, `project_id` NOT NULL, `unique(['project_id','plan_line_code'])`. Irreversible column drop. The existing `(org_id, project_id)` index stays.
- **B4. Retire transition code (same PR as B2/B3, deployed after they pass).** Remove `BackfillProjects.php`, `ProjectBackfillPlanner.php`, its registration if any, `tests/Feature/Rbac/ProjectBackfillTest.php`, `ProjectSchemaTest` legacy/nullable cases, the `legacyMember` helper in `ProjectTestCase`, `quote()` relations and `quote_id` fillables, TESTING.md entries. The command cannot run after B2/B3 anyway (it asserts `quote_id must be nullable`, `BackfillProjects.php:191`). H5: reports are kept until this step, then deleted per the human's retention decision. Verify: `php artisan route:list`/`list` show no command; grep for `quote_id` on `project_members`/`plan_crosswalk` returns nothing.
- **B5. Docs.** ARCHITECTURE.md (`checkPermission` projectId is a `projects.id`; crosswalk project-scoped; change log), CLAUDE.md wording ("nullable until Phase 5" becomes "NOT NULL"; drop the legacy-row sentences), TESTING.md. Proposed here, applied only after human approval per ARCHITECTURE.md.

## 3. Release gating

Phases 1-4 must already be on production and the Phase 2 backfill run and verified. Phase 3/4 code must NEVER be deployed without that backfill (Phase 3 alone never; ship rule B6). All of the following must be green and recorded in PROGRESS.md before 5a deploys; items marked (5b) additionally before 5b.

1. Backfill run with H3 guard, `unresolved.csv` empty, crosswalk conflicts resolved, reports archived (H5).
2. **Pre-flight SQL** (read-only, run by the human on production or the restored copy; any non-zero "must be 0" stops the release):
```sql
-- P0 mode and version
SELECT VERSION(); SELECT rbac_mode FROM rbac_settings;              -- confirm the DB row, not .env
-- P1 must be 0 (trashed included)
SELECT COUNT(*) FROM quotes WHERE project_id IS NULL;
-- P2 informational, human signs off: live quotes whose author has no active membership on the quote's project (these become invisible to the author)
SELECT COUNT(*) FROM quotes q LEFT JOIN project_members pm ON pm.project_id=q.project_id AND pm.user_id=q.user_id AND pm.is_active=1
 WHERE q.deleted_at IS NULL AND pm.id IS NULL;
-- P3 (5b) legacy rows: total, active, and active rows NOT covered by a project row for the same user
SELECT COUNT(*), SUM(is_active) FROM project_members WHERE project_id IS NULL;
SELECT COUNT(*) FROM project_members pm JOIN quotes q ON q.id=pm.quote_id
 LEFT JOIN project_members p2 ON p2.project_id=q.project_id AND p2.user_id=pm.user_id
 WHERE pm.project_id IS NULL AND pm.is_active=1 AND p2.id IS NULL;   -- must be 0 or reviewed in membership_org_mismatch.csv
-- P4 (5b) crosswalk: must all be 0
SELECT COUNT(*) FROM plan_crosswalk WHERE project_id IS NULL;
SELECT COUNT(*) FROM plan_crosswalk c JOIN projects p ON p.id=c.project_id WHERE c.org_id<>p.org_id;
SELECT COUNT(*) FROM (SELECT project_id, plan_line_code FROM plan_crosswalk GROUP BY 1,2 HAVING COUNT(*)>1) d;
-- P5 must be 0: live quotes inside soft-deleted projects, and members whose org differs from the project's
SELECT COUNT(*) FROM quotes q JOIN projects p ON p.id=q.project_id WHERE p.deleted_at IS NOT NULL AND q.deleted_at IS NULL;
SELECT COUNT(*) FROM project_members m JOIN projects p ON p.id=m.project_id WHERE m.org_id<>p.org_id;
-- P6 no unresolved scope during the soak (agreed period after Phase 4 went live)
SELECT COUNT(*), MAX(created_at) FROM rbac_audit_logs WHERE reason='project_unresolved' AND created_at >= '<phase-4 go-live>';
```
3. **B1 (MariaDB gate) extended for Phase 5**, on a restored production copy of the production major.minor: the three 5b migrations round trip; timings (ALTER on `quotes` locks); FK names as assumed; drop-FK-before-unique ordering works; NOT NULL change keeps `restrictOnDelete`. Plus the three items sqlite cannot prove and that Phase 4 left untested: (a) the `ProjectMember::enrol()` unique-violation race (`ProjectMember.php:53-70`, two sessions, REPEATABLE READ re-select with `sharedLock`), (b) `lockForUpdate` in `ProjectController::destroy`, (c) the `quotes.user_id` ON DELETE CASCADE versus the `quotes.project_id` RESTRICT interplay in `destroyUser` (`RbacController.php:501-508`): user delete must be refused before the cascade, and `project_members.user_id` cascade makes the explicit delete redundant.
4. Full backup taken and **test-restored** (row counts for the four tables match), B5 pre-flight `migrate:status` shows only the Phase 5 migrations pending, B12 production-vs-branch file diff before the FTP upload.
5. (5b) Maintenance mode (`artisan down`) for the whole window, sized from the B1 timings; the human present.

**Rollback per step**
| Step | Reversible? | Rollback |
|---|---|---|
| 5a A1-A3 | yes | redeploy previous code |
| B1 quotes NOT NULL | yes | `down()` re-nullable; or backup |
| B2 legacy rows delete and `quote_id` drop | no | backup restore only (and any writes since are lost; hence maintenance mode) |
| B3 crosswalk column drop, unique | no for the column; unique can be dropped | backup restore |
| B4 code removal | yes as code, but the command is useless after B2/B3 | redeploy + backup restore if a rerun is ever needed |

## 4. Migration design (refuse when pre-conditions fail)
- Each migration starts with a `guard()` that runs BEFORE any DDL/DML and throws `RuntimeException` with the counts and the SQL from section 3 (never silently skips, never "fixes"): P1 for quotes; P3 (uncovered active legacy rows) and P8 (`project_id IS NULL AND quote_id IS NULL`) for project_members; P4 for plan_crosswalk (NULL, org mismatch, duplicates).
- Additional refusals in all three: `app()->isDownForMaintenance()` must be true (same precedent as the H3 live-run guard), and `env PHASE5_BACKUP_CONFIRMED=1` must be set (a cheap human acknowledgement; the human sets it after the test-restore). Drivers: on sqlite (tests) the maintenance/confirm guard is skipped only when `app()->environment('testing')`.
- `down()` on B2 and B3 throws "irreversible, restore the backup"; B1's `down()` re-nullables.
- Order guarantee: B1, B2, B3 file dates ascending; a failed guard leaves the earlier ones applied and the DB consistent (each is self-contained). `migrate` is never run with `--force` by CI.

## 5. Test plan
Conventions as CLAUDE.md: PHPUnit classes (Pest not installed, Q17), audit AND enforce mode, JSON requests for 403.

**New / extended**
- `ProjectSchemaTest`: NOT NULL on quotes, project_members, plan_crosswalk `project_id`; the unique `(project_id, plan_line_code)`; `quote_id` columns absent; migration guards refuse each violated precondition (one test per guard: NULL quote, uncovered legacy row, crosswalk NULL/mismatch/duplicate) and pass when clean; `down()` on B2/B3 throws.
- `Quote::visibleTo`: member with `estimate_management:R` sees the project's quotes; author who was removed from the project sees nothing; author without R sees own quotes only in projects they are a member of; other org context sees nothing; `orgId null` sees nothing; project of another org, inactive member, trashed project all absent. Id-collision fixture (quote id equal to a project id) retained.
- Denied paths per changed route (wrong role, wrong org, non-member) for `quotes/*` reads and writes, crosswalk index/store/update/destroy, `projects/{quote}/workspace` redirect.
- Crosswalk: duplicate `(project_id, plan_line_code)` 422 at validation and DB-level rejection.

**Existing tests that pin the dual-read and must be rewritten or deleted** (ProjectReadPathsTest unless stated): r1 `:240` owner sees own quotes in any org context and NULL-project quotes; r2 `:284` owner reads a NULL-project quote; `:188` route fails closed for a NULL-project quote even for its owner; `:179, :255, :300, :385-427, :506, :514` legacy quote-only row tests (rows cannot exist; delete); r10 `:684` owner of a NULL-project quote in audit mode; r13 `:705-712` "owner still sees a quote in a trashed project through the user_id clause". ProjectWritePathsTest: NULL-project duplicate 422, `writableRow` NULL-project 404 (already an equivalent survivor mutant), quote-resolution cases with NULL project. ProjectCheckpoint2Test: "NULL project_id allowed" destroyUser cases (`RbacController.php:501` loses `whereNotNull`). ProjectMembershipTest and `ProjectTestCase` (`legacyMember`, `mkQuote` with a NULL project, sqlite schema stubs must add NOT NULL and drop `quote_id`). `ProjectBackfillTest` (1,963 lines) and `ProjectSchemaTest` backfill/nullability cases: delete with B4. `RoutePermissionMapTest` unchanged (map entries are not removed by this phase except none; the legacy-workspace entry stays).

**Mutation-proof targets (scratch copy only, routed to the Architect if a mutant survives)**
1. `visibleTo`: re-add the `user_id` OR clause (an unmembered author must fail to see), drop the project-org filter, drop the `$members`/author gate, drop `orgId null` guard.
2. Crosswalk index: re-add the quote branch; drop `$members` gate.
3. Migration guards: remove each guard (the corresponding refusal test must fail), remove the maintenance check.
4. `RbacController :501` refusal after dropping `whereNotNull`: still killed by the trashed-quote test.
5. The drop-FK-before-unique order is proven only on MariaDB (B1), not on sqlite.

## 6. Risks
1. **Audit mode is production.** The DB row `rbac_settings.rbac_mode` (client says audit; P0 confirms) means the middleware only logs; controller scoping is the guard. That is exactly why A1 must land in `Quote::visibleTo` and the controllers, not only the map, and why the 5a soak reads `rbac_audit_logs`. Enforce is not a Phase 5 prerequisite; it stays a separate client decision.
2. **Access loss is the intended effect**: authors not in their project's membership (P2), and authors reading across org contexts, lose the quotes. Decision 2.
3. **5a on old schema, 5b later** avoids a deployed build reading a dropped column; a single-release variant is possible but has no rollback for code and schema together.
4. **Phase 3/4 code without the backfill** is dangerous now (NULL project quotes invisible under enforce, `project_unresolved`). It is already forbidden; Phase 5 makes it worse (5a hides NULL quotes even in audit mode). Gate 1 enforces it; the guard in B1 refuses if not.
5. **sqlite versus MariaDB**: FK/index drop ordering, `change()` retaining the FK, table locks, the enrol race, `FOR UPDATE`, cascade, zero dates. B1 only.
6. **Dropping `BackfillProjects`** removes the only tool to re-home rows; after B2/B3 there is nothing left to re-home, but a restore from backup would need the pre-5b code. Keep the tag/branch of the last pre-5b commit (`a5395e7` lineage) and the archived reports.
7. Test-suite edits are large (rewriting about 25 tests, deleting about 2,000 lines); a rewritten test must not weaken the denied path. The compliance pass diffs the assertions.
8. Trashed quotes remain in the NOT NULL check (P1 has no `deleted_at` filter) and in `Project` deletion (`quotes.project_id` RESTRICT on soft-deleted rows is fine, hard delete of a project is not used).
9. `PLAN.md` earlier Phase 3 note at `:1521` ("the `user_id` clause is org-independent; Phase 5 removes it") is the only spec statement for A1; A1's exact shape (author retained for callers without R) is new here and needs the decision below.
10. Known open items: none touched (rep-agency, api_system, org types, 302 redirect). The 3 baseline failures stay in TESTING.md; do not bundle their fix.

## 7. OPEN decisions for Checkpoint 1 (with recommendations)
1. **One release or two (5a code then 5b schema)?** Recommend two, 5a soaking at least 14 days with zero `project_unresolved` rows.
2. **Author who is not a project member (P2) after A1.** Options: accept loss (consistent with Q1, no owner bypass), or the human re-enrols them per project before 5a using the UI. Recommend re-enrol the P2 list first, then accept loss; no code bypass.
3. **`visibleTo` shape:** project-visible AND (role R OR author). Recommend yes (keeps H9). Alternative "project-visible only" would hide own quotes from role-less authors.
4. **Legacy `project_members` rows:** migration deletes them only when no active row is uncovered (P3 = 0), otherwise refuses. Recommend yes; never auto-re-home in Phase 5.
5. **`plan_crosswalk` NULL-project or org-mismatched rows:** refuse and let the human relink or delete manually. Recommend refuse; no automatic deletion.
6. **`projects/{quote}/workspace` 301 and `project_unresolved` handling:** keep the 301 one more release then remove. Recommend keep.
7. **Q5/Q10/Q11/Q14:** confirm none is Phase 5 work (Q11 delegation stays a separate feature). Recommend confirm.
8. **Retire `projects:backfill`, planner and their 1,963-line test in the same PR as the column drop, and delete the reports (H5)** only after 5b is verified plus a retention period the client sets. Recommend 30 days after 5b, reports held by the human with the backup.
9. **PHASE5_BACKUP_CONFIRMED env guard and maintenance-mode requirement** for 5b. Recommend yes; window sized from B1 timings.

## Phase 5 amendment 2 (2026-09-25, Architect compliance pass): required changes
Approved decisions unchanged. Developer implements, then Architect re-checks A5/A6 only.
- **A5. Dashboard dual-read.** `resources/views/user/dashboard.blade.php:15,45,66`: replace the three `Quote::where('user_id', $userId)` reads with `Quote::visibleTo($userId, $_dashOrgId, <canRead>)`. Compute `$_dashOrgId` before line 15 (currently defined after), and `<canRead>` = `checkPermission(userId, orgId, 'estimate_management', 'R')` (org null -> false). Preferably move the three queries into `UserWorkspaceController` and pass them as vars (the view already prefers injected vars). `$quotesCount`, `$quoteStatusCounts`, `$recentQuotes` must then agree with the quotes index. Orders/lists stay author-scoped (not in scope). Add a test: author removed from project sees no quote on the dashboard; in another org context sees none.
- **A6. Guard config, not env().** In all three migrations replace `env('PHASE5_BACKUP_CONFIRMED')` with `config('rbac.phase5_backup_confirmed')`, adding that key to `config/rbac.php` as `env('PHASE5_BACKUP_CONFIRMED', false)`. Refusal text unchanged. Test the refusal by setting the config key.
- **A7. Release cut.** Keep one branch, two deploys. 5a commit(s): everything under `app/`, `resources/`, config, and the rewritten dual-read tests; 5b commit(s): the three migrations and their guard/schema tests, plus the B4 test deletions of A8. 5a upload must not include the three `2026_09_25_*` files and no `migrate` runs in that window. The model removals stay in 5a (verified safe).
- **A8. Backfill retirement.** With 5b: delete `ProjectBackfillTest`, the `ProjectSchemaTest` backfill/nullability cases and `legacyMember`; keep `BackfillProjects`/`ProjectBackfillPlanner` inert for 30 days (decision 8), then delete. TESTING.md updated at that point.
- **A9. Gate note.** PROGRESS must record P1 = 0 and P5 (both queries) = 0 immediately before 5a and again before 5b.

## Phase 5 amendment 3 (2026-09-25, Architect, Verifier findings)
Approved decisions unchanged. Developer implements D1-D6, QA T1-T5, then a Verifier re-check limited to D1-D3 (migrations and gate). Nothing else reopens.

**Developer**
- **D1 (F1).** `2026_09_25_000003_tighten_plan_crosswalk.php`: before dropping `['org_id','quote_id']`, check `Schema::getIndexes` for another index whose first column is `org_id`; if none exists, add `$table->index(['org_id','project_id'])` first. Do not touch the `change()` beyond that, but keep `restrict/cascade` FK untouched (do not re-declare the FK). No other change.
- **D2 (F2).** `config/rbac.php:39`: `'phase5_backup_confirmed' => filter_var(env('PHASE5_BACKUP_CONFIRMED', false), FILTER_VALIDATE_BOOLEAN)`. Migrations keep `! config(...)`.
- **D3 (F4).** `2026_09_25_000002_tighten_project_members.php` uncovered query: the join to `p2` also requires `p2.is_active = 1` and `p2.org_id = pm.org_id`; update the refusal check SQL text and the message to say "active same-organization project membership". Nothing else in the migration changes.
- **D4 (QA obs).** `RbacController::destroyUser` (~:502) message becomes: 'This user owns quotes and cannot be deleted. Reassign or delete those quotes first.' Logic untouched.
- **D5 (QA obs).** `UserWorkspaceController` recent list (:98): `->orderByDesc('quotes.created_at')->orderByDesc('quotes.id')`.
- **D6 (F3 docs, text only).** Add to the Phase 5 runbook section of PLAN.md/TESTING.md (B5 docs; ARCHITECTURE.md/CLAUDE.md wording changes are applied only after human approval at Checkpoint 2): (1) 5a upload is built from the A7 split commit and contains none of the three `2026_09_25_*` files; no `migrate` in that window. (2) Immediately before 5a and again before 5b, run P1 (`SELECT COUNT(*) FROM quotes WHERE project_id IS NULL`, trashed included) and both P5 queries; all must be 0; record the values and time in PROGRESS.md (A9). (3) Deploying 5a with any NULL-project quote hides it from everyone, including its author, even in audit mode. Perform the A7 split (commits) before Checkpoint 2 if the human wants it; otherwise it is a release-time step.
- **Not changed:** F5, F6 code, F7, `pendingApprovals`.

**QA**
- **T1 (F2).** Phase5MigrationsTest: for each migration, config `phase5_backup_confirmed` = false/null/0 refuses (outside testing env, as existing guard tests do). Add a config-level test that `filter_var` mapping yields false for 'off','no','n','0','false','disabled'... and true for '1','true','on'; set `PHASE5_BACKUP_CONFIRMED` via `putenv`/`$_ENV`, re-require the config file (`require config_path('rbac.php')`) and assert the key.
- **T2 (F4).** Migration 2 refuses when the only project row for the user/project is inactive, and when it belongs to a different org; passes when an active same-org row exists.
- **T3 (F1).** sqlite: with the `(org_id, project_id)` index removed beforehand, migration 3 recreates it; with it present, quote_id index and column are gone and the unique exists.
- **T4.** destroyUser refusal asserts the new wording; dashboard recent list ordering with equal created_at is by id desc.
- **T5.** Run Rbac suite; expect 651+new passed, exactly the 3 known failures.
- Mutation proofs: D2 (revert to raw env, T1 dies) and D3 (drop is_active or org_id, T2 dies).

**B1 MariaDB checklist additions (human/ops, on the restored copy, production major.minor)**
1. `SHOW CREATE TABLE plan_crosswalk` before and after: FKs on org_id, project_id (ON DELETE CASCADE), created_by, updated_by remain; project_id NOT NULL; unique (project_id, plan_line_code) present; `(org_id, quote_id)` index and `quote_id` gone; some index still starts with org_id.
2. `dropIndex(['org_id','quote_id'])` succeeds without errno 1553/1451, on the restored copy, before any production run.
3. Same SHOW CREATE TABLE diff for `quotes` (project_id NOT NULL, RESTRICT FK kept) and `project_members`.

**Open human decisions:** (a) confirm F5, r11 supersedes H8, at Checkpoint 2; (b) do the A7 commit split before Checkpoint 2 or at release time; (c) confirm `pendingApprovals` stays a follow-up. Known open items (rep-agency, api_system, org count, audit 302) untouched.

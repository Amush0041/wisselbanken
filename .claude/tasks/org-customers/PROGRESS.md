# PROGRESS: org-customers (sections A and B only; C-extras and D not done)

- D1 A: migration database/migrations/2026_10_14_000001_add_org_id_to_customers_table.php (nullable org_id, FK nullOnDelete, index (org_id,is_active), hasColumn guard); app/Models/Customer.php (fillable org_id, organization(), scopeForOrg replaces scopeForUser). Not run against MySQL. Deviation: none.
- D2 B: Frontend/CustomerController to org scope (requireOrgLevel returns org id; resolveOrgCustomer; store sets org_id from CurrentOrg, user_id stays creator). Deviation: none.
- D3 B: Frontend/QuoteController store (project org), saveEditor (quote's project org, or own current customer only if NULL-org), getCustomersForEstimate (CurrentOrg; null org -> empty); ProjectWorkspaceController dropdown (project org). Views grep: no user_id-based customer logic found in the touched paths. Deviation: none.
- D4 A: app/Console/Commands/BackfillCustomerOrg.php (customers:backfill-org; rules, overrides, dry-run, --force in production, exit 0/1/2, CSV reports). Planner class not created (inline). Idempotent via WHERE org_id IS NULL.
- D5 tests fixtures (org_id added only): ProjectTestCase customers table; customer inserts in ProjectWritePathsTest, ProjectCheckpoint2Test, ProjectReadPathsTest (2 places), AuditFindingsRound2Test (org = creator's first active user_org_roles org; ReadPaths d18 uses orgA).
  Suite: 1319 passed / 9 failed = 4 known + 5 that assert the OLD per-user rule (same-org other-user customer is now allowed): ProjectCheckpoint2Test 'show passes only the users own active customers', 'posting another users customer through the form is refused'; ProjectWritePathsTest 'quote store refuses a customer that is not the callers' (x2 data sets, enforce+audit). Not fixed by Developer; QA to rewrite them for org scope.
- D6 docs: ARCHITECTURE.md (10c, 10b list), HANDOVER-CLIENT.md (section 9 note, section 10 command).
- D7 view:cache + view:clear OK.

## QA (test engineer) - org-customers

Rewritten (old per-user rule -> org rule): ProjectCheckpoint2Test 'show passes the orgs active customers to a creator including teammates' (x2 modes), 'posting a teammates customer of the same org ... is allowed' and 'posting another orgs customer ... is refused' (x2 modes each, replacing the single per-user test); ProjectWritePathsTest 'quote store refuses a customer of another org and accepts a teammates' (x2 modes).
Count reconciliation: the Developer's 9 failures = 4 known + 5 old-rule tests (Checkpoint2 x1 'show...' x2 datasets counted as 2, 'posting another' x1, WritePaths x2 datasets). Any "8" is the same set before counting the data sets; after the rewrite all 5 pass.
New: tests/Feature/Rbac/OrgCustomersTest.php (46 cases), CustomerOrgBackfillTest.php (20), CustomerOrgMigrationTest.php (5).
Mutation proofs (scratch copy with real vendor): 35 mutants, 33 killed, 2 survivors that are equivalent/unreachable: M9 (QuoteController::store uses the user's current org instead of the project's org; a project outside the current org is already 404/403 before the customer lookup) and M15 (getCustomersForEstimate with null org; a user without an org is 403 from requireEstimateLevel first, universal admin included). M23 (backfill UPDATE without org_id IS NULL) first survived; killed by a new race test (org set between plan and write).
Full suite (real repo, sqlite): 1398 passed / 4 failed = exactly the known 4 (ExampleTest, AuditMiddlewareTest 'enforce mode blocks and records blocked', SeedMatrixTest 'seeds expected counts' and 'phase distribution'). No application code changed.

## Developer - user deletion guard (Verifier finding 1, human Option 1)
Admin/RbacController::destroyUser (only code path deleting a users row; grep of app/routes/commands found no other) now refuses with an error flash when the user created customers referenced by quotes.customer_id (withTrashed). Added Customer import. No schema/migration change; project_members log behaviour untouched. Docs: ARCHITECTURE.md 10c, HANDOVER-CLIENT.md. Deviation: none.

## QA - user deletion customers guard
New tests/Feature/Rbac/UserDeleteCustomersGuardTest.php (11 cases): teammate's quote uses the customer (audit+enforce; user, customer, quote.customer_id, memberships, no new logs), soft-deleted-quote-only, creator filter, unreferenced customers + 'removed' project_member_logs rows, no customers, non-admin 403, plural count (2 of 3 customers), 'owns quotes' and 'created projects' precedence. Cascade removal of unused customers is not asserted (sqlite fixture customers table has no FK).
Mutation proofs (scratch copy, real vendor): guard removed -> 4 fail; withTrashed dropped -> 2 fail; user_id filter dropped -> 1 fail. All killed.
Full suite: 1409 passed / 4 failed = exactly the known 4. No app code changed.

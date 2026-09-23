---
description: Run the RBAC test suite and report results against the known-failing baseline in TESTING.md.
---

Run `php artisan test --filter=Rbac`. Compare the results against the
"Known-failing baseline" section of `TESTING.md`:

- Report any failure NOT in that list as a new, real failure needing
  investigation.
- Report any of the 3 known baseline failures separately, by name, as
  pre-existing — not as something this change broke.
- If a previously-known failure now passes, note that too — it may mean
  something else fixed it as a side effect, worth a mention even if
  unplanned.

Give a one-line summary (X passed / Y failed, Z known pre-existing) followed
by specifics only for anything unexpected.

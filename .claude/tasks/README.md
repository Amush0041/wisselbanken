# Task folders

Each feature/fix gets `.claude/tasks/<slug>/` (kebab-case slug), created by
`/new-feature <name>`. Contains three files, filled in as the feature moves
through the lifecycle:

- **`PLAN.md`** — written by the `architect` agent. Data model, permission
  mapping, file list, task breakdown, risks. **Checkpoint 1**: a human
  approves this before any code is written.
- **`PROGRESS.md`** — appended to by the `developer` (and `ui-designer`)
  agent after each completed step. This is what survives a context
  compaction or a resumed session tomorrow — read it back before assuming
  you know the current state of a task.
- **`REVIEW.md`** — QA results (`qa-tester` agent) and independent findings
  (`reviewer` agent), combined. **Checkpoint 2**: a human approves this,
  alongside the final diff, before a PR is opened.

## Lifecycle — STRICT MODE (no skipped roles, no direct dev-fixes)

Every task or bug report, without exception, passes through all six stages
below in order. There is no shortcut where the Developer patches an issue
found by QA/Verifier/human directly — every issue found at any stage routes
back to the **Architect**, who updates `PLAN.md` before any code changes.

```
1. Manager/Orchestrator (this session) receives the task or bug report.
2. Architect → writes/updates PLAN.md.
   ══════════════════════════════════════════════════════
   ✋ CHECKPOINT 1 — Human approves the plan.
   ══════════════════════════════════════════════════════
3. Developer (+ ui-designer if PLAN.md has a UI section) implements
   exactly what PLAN.md specifies → logs to PROGRESS.md.
4. Architect (compliance pass) — reviews the diff against PLAN.md and
   confirms the implementation matches the approved spec. Logs a verdict
   (COMPLIANT / DEVIATIONS FOUND, with specifics) to REVIEW.md.
5. QA/Tester — validates code quality, writes/runs tests → logs results
   to REVIEW.md.
6. Verifier/Security — confirms no missing edge cases and no regression
   risk (security + RBAC checklist) → logs findings to REVIEW.md.
   ══════════════════════════════════════════════════════
   ✋ CHECKPOINT 2 — Human gives final approval.
   ══════════════════════════════════════════════════════
7. PR opened (feature → dev); merge stays a manual human action.
   ARCHITECTURE.md updated with the change; task folder archived.

ISSUE ROUTING: if step 4, 5, 6, or the human finds a bug, missing edge
case, or spec deviation — STOP. Do not patch it inline. Route back to
step 2 (Architect updates PLAN.md to address it), then re-run steps 3-6
in full. This applies even to trivial-looking fixes found during "final"
testing.
```

**Chain of custody**: every handoff between agents must be stated
explicitly in chat — which agent just finished, their findings, and the
exact output (file + section) they produced — and mirrored as a dated
entry in that task's `PROGRESS.md` or `REVIEW.md`. No silent handoffs.

**Completion criteria ("Zero Defect & Completeness")**: a task is not
"Complete" until all four are true and visible in `REVIEW.md`:
(a) Architect's compliance-pass verdict is COMPLIANT,
(b) QA's test results show no unexplained new failures,
(c) Verifier confirms no missing edge cases / regression risk,
(d) the human has given explicit final approval at Checkpoint 2.

On completion, move the folder to `.claude/tasks/_archive/<slug>/` rather
than deleting it — it's the record of what was decided and why.

## Starting one

```
/new-feature <short-kebab-case-name>
```

This scaffolds the folder from `.claude/tasks/_template/`, creates a
feature branch off `dev`, and hands the request to the `architect` agent.

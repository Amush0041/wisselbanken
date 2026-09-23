---
description: Scaffold a new feature task folder, create its branch, and kick off the architect agent.
---

Feature request: $ARGUMENTS

Do the following, in order:

1. Derive a short kebab-case slug from the request.
2. Create `.claude/tasks/<slug>/` by copying `PLAN.md`, `PROGRESS.md`, and
   `REVIEW.md` from `.claude/tasks/_template/`.
3. Create a feature branch off `dev` named `feature/<slug>` (do not push it
   yet).
4. Invoke the `architect` subagent with the full feature request and the
   new `PLAN.md` path, per `.claude/agents/architect.md` and
   `.claude/tasks/README.md`.
5. When the architect finishes, present the plan's Goal, Permission
   mapping, and Risks sections to the user for Checkpoint 1 approval. Do
   not proceed to implementation until they explicitly approve.

# 2026-07-03 — Docs-and-journal policy made mandatory

Tightened the "Workflow notes" section of [CLAUDE.md](../../CLAUDE.md) so documentation is
treated as part of "done":

- **Docs update with the behaviour, same commit.** Any change to structure, schema, data
  flow, config keys, services/classes, or module behaviour must keep the four planning docs
  (CLAUDE.md, README.md, ARCHITECTURE.md, SETUP.md) in sync — no deferring.
- **A journal entry is mandatory**, especially for big changes (features, refactors,
  schema/flow/auth/dependency changes): record what changed, why, and anything deliberately
  left undone. Journals stay append-only history; never rewrite past entries.

Why: recent work showed docs drifting from the code (dead references, an unwired setting).
Making the docs+journal step non-optional keeps the tracked history trustworthy.

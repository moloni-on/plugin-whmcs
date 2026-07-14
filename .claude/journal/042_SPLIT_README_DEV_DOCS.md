# 2026-07-14 — Split README into a customer overview + DEV.md

## What changed

`README.md` had grown into developer documentation (project structure, Docker tooling, the
optional full WHMCS runtime, build/release mechanics). That's the wrong audience for the
repo's front page, which customers and evaluators land on first.

- **`README.md`** is now a customer-facing plugin overview: what the module does (in
  benefit-oriented terms), requirements, a three-step quick install pointing at
  [SETUP.md](../../SETUP.md), a configuration summary, and links to SETUP.md / DEV.md.
- **`DEV.md`** (new) holds everything developer-facing lifted verbatim out of the old README:
  the project-structure tree, the Docker `tools` workflow, the optional WHMCS runtime profile,
  and the build/release + CI description. It links back to README/SETUP and forward to
  ARCHITECTURE/CLAUDE/PROJECT_PLAN/journal.
- **`CLAUDE.md`** documentation map updated: README row re-described as the customer overview
  and a new DEV.md row added.

## Why

Keep the two audiences separated — a prospective user reading the README shouldn't have to
wade through Docker commands and CI internals, and a contributor gets a single dedicated
DEV.md. No behaviour change; docs only.

## Not done / left alone

- SETUP.md's deployment-checklist file tree still lists only `README.md`; left as-is since it
  is a "files present" checklist, not a doc index.

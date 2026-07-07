# 2026-07-07 — Gate the release workflow on lint + tests

## Problem

`release.yml` triggered purely on `v*` tags and ran only `install → build →
publish`. It never ran phpcs or PHPUnit, and nothing checked whether CI had
passed on the tagged commit. A tag on a red (or untested) commit would still
build and publish `dist/moloni_on.zip` to a GitHub Release. The only safety net
was the human remembering to check CI before tagging.

## Change

Added lint + test steps to the `release` job, before the build:

1. Install **with** dev deps (`composer install`, no `--no-dev`) so phpcs and
   PHPUnit are available.
2. `composer lint` then `composer test` — same checks CI runs. A failure aborts
   the release before anything is published.
3. Build with plain `bash build.sh` (dropped `--skip-install`) so build.sh does
   its own fresh `--no-dev` install for packaging — the dev tooling installed in
   step 1 never leaks into the shipped zip.

Also added the `curl, json, mbstring` extensions + `coverage: none` to the
release job's PHP setup to match CI.

## Decisions / alternatives

- Chose an **inline re-run** of the checks over a `workflow_run`/reusable-workflow
  gate or querying "did CI pass for this SHA". Self-contained, no dependence on
  CI-run timing or existence, and robust if someone tags a commit that never got
  a CI run.
- Docs updated in the same change: [CLAUDE.md](../../CLAUDE.md),
  [README.md](../../README.md), and the header comment in
  [ci.yml](../../.github/workflows/ci.yml) (which had claimed the release
  workflow does neither lint nor test — no longer true).

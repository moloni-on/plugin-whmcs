# 2026-07-03 — Rename orders template document.php → orders.php

## Why

The Orders page template was named `templates/document.php` — a leftover from
the original spec (`PROJECT_PLAN.md` line 220). It was the only page whose
template filename didn't match its page name, which forced a special case in the
router (`pageTemplate()` mapped `orders → document`) and was confusing next to
the separate `documents.php` (created-documents list).

## Change

- `git mv templates/document.php templates/orders.php`.
- `Dispatcher::renderPage()` now renders `$page` directly; removed the
  `pageTemplate()` method (every page name now equals its template filename).
- Updated the `PAGES` comment and the CLAUDE.md / ARCHITECTURE.md page maps.

No behaviour change — pure rename + dead-code removal.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 48 tests, 134 assertions, green.
- `php -l templates/orders.php` clean; no remaining references to the old name.

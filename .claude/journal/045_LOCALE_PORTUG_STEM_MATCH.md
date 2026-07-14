# 2026-07-14 — UI locale: match the "portug" stem, drop the marker files

Third and final pass on the "admin UI renders in English on a PT install" bug
(after [043](043_FIX_ADMIN_LANGUAGE_DETECTION.md) and
[044](044_ADMIN_LOCALE_VIA_WHMCS_LANG.md)). This one was diagnosed from live
runtime data, not guesswork.

## What the on-prod debug panel showed

A temporary diagnostics panel (added to `Dispatcher::dispatch()`) printed, on the
user's production install:

```
vars keys: module, modulelink, version, access
_lang type: NULL
SESSION adminid: 1
tbladmins.language raw: 'portugues'
Whmcs::adminLanguage(): 'portugues'
Lang::language(): en
lang dir contents: en.php, pt.php
```

Two facts settled it:

1. **The admin's language slug is `portugues`** — no trailing "e". `isPortuguese()`
   tested `strpos($lang, 'portuguese') !== false`, and `'portugues'` does **not**
   contain `'portuguese'` (it's one char shorter), so it fell through to English.
   This was the actual bug.
2. **`$vars['_lang']` is `NULL`** — WHMCS did not pass the marker mechanism at all,
   and the 044 marker files weren't even on prod. More importantly, even deployed
   they'd never help: WHMCS matches a file named for the admin slug (`portugues.php`),
   which we'd never anticipate. The whole marker strategy is wrong for custom slugs.

## Changes

- **`Support\Lang::isPortuguese()`**: match the stem `portug` (plus the `pt`
  prefix) instead of the full word `portuguese`. Now resolves `portugues`,
  `português`, `portuguese`, `portuguese-pt`, `portuguese-br`, `pt`, `pt-PT`.
- **`Models\Whmcs::adminLanguage()`**: when `tbladmins.language` is empty (admin
  on "Default"), fall back to the system default language from
  `tblconfiguration` (`setting = 'Language'`) — the language such an admin
  actually sees. This replaces 044's reliance on WHMCS `$vars['_lang']`.
- **`Admin\Dispatcher`**: removed `resolveAdminLocale()` and its `$vars['_lang']`
  reading; `dispatch()` is now simply `Lang::boot(Whmcs::adminLanguage())`.
- **Removed the marker files** `lang/{english,portuguese-pt,portuguese-br,portuguese}.php`
  added in 044. `lang/en.php` / `lang/pt.php` (the real translation tables) stay.
- **Tests**: `LangTest` now also asserts `portugues`, `Portugues`, `português`
  resolve to `pt` (70 tests green).

## Still in place (to be removed next)

`Dispatcher::debugLocalePanel()` + `langDirListing()` + the `echo` in `dispatch()`
are **temporary** and clearly marked `TEMP DEBUG`. Leave them for one more deploy
so the user can confirm `Lang::language() (final): pt`; remove once confirmed.

## Lessons

- Don't infer WHMCS internals; a 6-line runtime debug panel found in one round
  what two docs-driven guesses (043, 044) missed.
- WHMCS language slugs are install-defined and not guaranteed to match the
  documented `english`/`portuguese-pt` names — match loosely on a stem, never on
  an exact string, and never key files off the slug.

# 2026-07-14 — Fix: admin UI always rendered in English

## Problem

A user with a PT-pt WHMCS admin locale reported the addon UI always showing in
English. Root cause in `Admin\Dispatcher::dispatch()`:

```php
Lang::boot((string) ($this->vars['adminLanguage'] ?? 'english') === 'portuguese' ? 'pt' : 'en');
```

WHMCS does **not** pass an `adminLanguage` key in the `$vars` array given to an
addon module's `_output()` function, so `?? 'english'` always won → the
expression always evaluated to `'en'`. The UI was hard-stuck on English
regardless of the admin's locale.

Two further latent bugs sat behind it:

1. Even had the key existed, WHMCS's language name for European Portuguese is
   `portuguese-pt`, so the exact `=== 'portuguese'` match would have failed.
2. `Lang::boot()`'s own `substr($language, 0, 2) === 'pt'` guard also fails on
   `portuguese-pt` (that starts with "po"), so it only ever worked for a plain
   `pt` code that nothing was actually supplying.

## Fix

- **`Models\Whmcs::adminLanguage()`** (new): reads the logged-in admin's
  language from `tbladmins` via `$_SESSION['adminid']`, returning `""` when it
  can't be resolved. This is the reliable source since WHMCS won't hand it to us
  in the output vars.
- **`Admin\Dispatcher::dispatch()`**: now `Lang::boot(Whmcs::adminLanguage())`,
  passing the raw WHMCS language name straight through.
- **`Support\Lang::boot()`**: detection extracted to `isPortuguese()`, which
  matches both ISO-ish codes (`pt`, `pt-PT`) and WHMCS names (`portuguese`,
  `portuguese-pt`, `portuguese-br`, any case). Everything else → `en`.

## Tests

`tests/Unit/LangTest.php`: added a data-provider case asserting all the
Portuguese identifier forms resolve to `pt`, plus an empty-string → `en` case.
phcs clean; PHPUnit green (67 tests).

## Notes / left undone

- `Whmcs::adminLanguage()` is not unit-tested — it needs `Capsule` +
  `$_SESSION`, which only exist inside a real WHMCS install (same reason the rest
  of the `Whmcs` model is untested). The locale-string matching that carries the
  actual logic lives in `Lang` and *is* covered.
- Assumes `tbladmins.language` holds the admin's preferred language name, which
  is the historical WHMCS schema.

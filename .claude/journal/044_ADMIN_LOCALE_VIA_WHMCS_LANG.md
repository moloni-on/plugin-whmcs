# 2026-07-14 — UI locale via WHMCS's own admin-language resolution

Follow-up to [043](043_FIX_ADMIN_LANGUAGE_DETECTION.md). That fix still didn't
render the UI in Portuguese on the user's live PT-pt install.

## Why 043 was insufficient

043 read the admin language from `tbladmins.language` via `$_SESSION['adminid']`.
That column is only populated when an admin has *explicitly* chosen a language;
an admin who inherits the system default language leaves it **empty**, so
`Whmcs::adminLanguage()` returned `""` → English. That is the common case, hence
"still not working".

## The authoritative WHMCS mechanism

Per WHMCS developer docs (addon-modules/multi-language), an addon ships language
files inside its own `lang/` folder **named after WHMCS admin language files**
(`english.php`, `portuguese-pt.php`, …) that define `$_ADDONLANG`. WHMCS resolves
the admin's *effective* language itself, loads the matching file, and passes its
contents to `_output()` as `$vars['_lang']`, falling back to the config
`language` default (`english`) when no match exists. This is correct even when
`tbladmins.language` is empty.

## Change

- Added WHMCS-format marker files carrying a single `locale` key:
  - `lang/english.php` → `en`
  - `lang/portuguese-pt.php` → `pt`
  - `lang/portuguese-br.php` → `pt` (module only ships European PT strings, but
    that's far closer than English for a BR admin)
  - `lang/portuguese.php` → `pt` (safety net for the unqualified name)
  These are separate from the module's own `lang/en.php` / `lang/pt.php`
  translation tables (keyed arrays read by `Support\Lang`); WHMCS never loads
  those (wrong names), and `Support\Lang` never loads the markers.
- `Dispatcher::resolveAdminLocale()` (new): reads `$vars['_lang']['locale']`;
  falls back to `Whmcs::adminLanguage()` (kept from 043), then English.
- `Dispatcher::dispatch()` now `Lang::boot($this->resolveAdminLocale())`.
- `build.sh` already copies the whole `lang/` dir, so the markers ship.

`Support\Lang::boot()`/`isPortuguese()` from 043 are unchanged and still handle
both the marker values (`pt`/`en`) and any raw WHMCS name from the fallback.

## Tests / verification

- `php -l` clean on all four marker files; phcs clean; PHPUnit green (67 tests).
- Marker files aren't unit-testable (they only matter inside WHMCS's loader), and
  `resolveAdminLocale()` needs `$vars`/`Capsule`; the locale-string logic they
  feed lives in `Lang` and is covered.

## Deploy note

The live WHMCS runs the **installed** copy, not this repo. The user must rebuild
(`composer build`) and re-upload the module for either 043 or 044 to take effect
— a likely contributor to "still not working" after 043.

## Left undone

- Didn't migrate the module's whole i18n onto WHMCS's `$_ADDONLANG` system; kept
  the existing `Support\Lang` + keyed-array tables and only borrowed WHMCS for
  *detection*. A fuller migration is possible later but was out of scope.

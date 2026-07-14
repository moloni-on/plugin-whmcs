# 2026-07-14 — Locale fix confirmed on prod; debug panel removed

Closes the locale thread ([043](043_FIX_ADMIN_LANGUAGE_DETECTION.md) →
[044](044_ADMIN_LOCALE_VIA_WHMCS_LANG.md) →
[045](045_LOCALE_PORTUG_STEM_MATCH.md)).

- The 045 fix (`isPortuguese()` matching the `portug` stem + `Whmcs::adminLanguage()`
  system-default fallback) was deployed to the user's production WHMCS and
  **confirmed working** — the admin UI now renders in Portuguese for the
  `portugues`-slug admin.
- Removed the temporary diagnostics: `Dispatcher::debugLocalePanel()`,
  `Dispatcher::langDirListing()`, and the `echo` in `dispatch()`. No `TEMP DEBUG`
  traces remain in `src/`.
- No behaviour change from 045; phcs clean, PHPUnit green (70 tests). Rebuilt
  `dist/moloni_on.zip` as the clean release artifact.

# 2026-07-03 — Cache-bust CSS/JS on module version

Follow-up to the "left undone" note in journal 013.

## Change

`Template::asset()` now appends `?v=<version>` (e.g.
`…/public/css/style.css?v=1.0.0`) so browsers refetch CSS/JS after a module
upgrade instead of serving a stale cached copy. The separator is chosen
defensively (`?` vs `&`) in case an asset path ever carries a query already.

The version is centralised as `Platform::VERSION` (new constant, single source
of truth). `moloni_on_config()` now reads its `version` from that same constant
instead of a duplicated `'1.0.0'` literal.

## Notes

- `Template` stays framework-independent (`Platform` is a plain constants class,
  same namespace, no WHMCS dependency), so unit tests are unaffected.
- Bump `Platform::VERSION` on each release to invalidate cached assets.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 35 tests, 90 assertions, green.

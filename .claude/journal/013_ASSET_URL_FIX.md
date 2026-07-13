# 2026-07-03 — Fix admin asset 404 (CSS/JS not loading)

## Problem

The admin UI loaded without styling. Browser requests for
`https://<host>/admin/modules/addons/moloni_on/public/css/style.css` returned
**404**.

`Template::asset()` returned a **relative** path
(`modules/addons/moloni_on/public/...`). Admin pages are served from `/admin/`,
so the browser resolved the relative URL against that directory, producing
`/admin/modules/addons/...`. WHMCS addon files are web-served from the **site
root** (`/modules/addons/...`), never under the admin folder, so the request
missed. This affected both the stylesheet (`Blocks/header.php`) and the script
(`Blocks/footer.php`).

## Fix

Build an absolute asset URL from WHMCS's `SystemURL`, the same approach the
legacy `whmcs_moloni_pt` plugin used in `Tools::getPublicUrl()`.

- `Template` gained an optional `$assetBase` constructor arg; `asset()` prepends
  it when set, otherwise falls back to the previous relative path. Keeping it
  optional preserves `Template`'s framework-independence (unit tests construct it
  without WHMCS).
- `Container` threads `$assetBase` through to `Template`.
- `moloni_on.php` resolves it via `WHMCS\Config\Setting::getValue('SystemURL')`
  and passes it into the `Container`. This is the only place that touches a WHMCS
  class, keeping the injected value out of the testable classes.

`url()` (module page links / forms) was left relative — those targets *are*
under `/admin/`, so relative resolution is correct there. Only assets needed the
root-absolute URL.

## Verification

- `php -l` clean on all touched files.
- phpcs (PSR-12): 0 errors / 0 warnings.
- PHPUnit: 34 tests, 88 assertions, green (no constructor signature broke since
  the new arg is optional).

## Left undone

- No cache-busting `?v=` query added (the legacy plugin appended the version).
  Can revisit if browser caching of assets becomes an issue after upgrades.

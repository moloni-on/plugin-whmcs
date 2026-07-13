# 2026-07-03 — Render logout as a real button

## Change

The logout control in the navbar was styled to look like the sibling nav-link
tabs (`nav-link moloni-on__logout` with a CSS override stripping the button's
background/border). It blended into the tab row and was hard to spot.

It now renders as a proper Bootstrap button:

- `templates/Blocks/navbar.php` — the `postForm()` class changed from
  `nav-link moloni-on__logout` to `btn btn-sm btn-outline-danger
  moloni-on__logout`, so it reads as a distinct, clickable logout action.
- `public/css/style.css` — removed the `.moloni-on__logout { background:none;
  border:0; … }` override that was suppressing the button styling. The
  `.moloni-on__nav-right .moloni-on__inline-form` flex rule is kept so the
  button still lines up on the tab baseline regardless of the WHMCS admin
  theme's Bootstrap version.

## Notes

- `Platform::VERSION` (the asset cache-bust key) was **not** bumped: per
  [journal 016](016_ASSET_CACHE_BUST.md) it's bumped per release, and we're
  still pre-release on `create-plugin` assembling 1.0.0. A hard reload picks up
  the CSS during dev; the release bump covers end users.

## Verification

- `php -l` on the template: clean.
- PHPUnit green (templates/CSS are outside the phpcs scope).
- CSS-only visual change; not rendered in a real WHMCS browser here.

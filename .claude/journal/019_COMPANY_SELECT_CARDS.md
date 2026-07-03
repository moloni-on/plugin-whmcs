# 2026-07-03 — Company selection as logo cards

## What changed

- `templates/company.php`: the company-selection screen now renders each company
  as a card in a responsive grid instead of a stacked radio list. Each card shows
  the company logo, name, VAT and city, with a radio circle indicator in the
  corner. The native radio is visually hidden but stays focusable; the selected
  state is driven by CSS `:has(input:checked)`.
- Logo source: `Platform::MEDIA_API_URL` + `/` + `company.img1`. The full `logo`
  URL is computed in `Dispatcher::filterSelectableCompanies()` and passed into the
  template so no host string is hardcoded in the view. When a company has no
  `img1`, the card falls back to a tile with the company name's initial.
- `src/Moloni/GraphQL/Queries/GetCompanies.php`: added `img1` to the `companies`
  `data` selection so the logo path is available. Flows through via
  `Company::getAll()` on the selection screen.
- `public/css/style.css`: replaced `.moloni-on__company-list/-item` styles with
  `.moloni-on__company-grid/-card/-logo/-initial/-body/-check`. Login panel keeps
  its narrow width; the company screen widened to fit the grid.

Mirrors how companies are presented in the WordPress module.

## Notes

- Templates are not part of the phpcs scan (`phpcs.xml` only covers `src`, `tests`
  and the entry files), so the card markup uses the existing alternative-syntax
  style used across the other templates.

# 2026-07-06 — Custom hooks + generic product-creation names

## Why

Moloni ON products **cannot be renamed after creation**, but the module was
creating each product under the order-specific line description (`$line->name()`).
Since products are shared by every order line with the same reference (all `.com`
registrations → `REG-COM`, all hosting → `Alojamento`, …), the first order to
create a product permanently fixed a name that was wrong for every later order.

The fix: create products under a **generic, action-describing name**, while the
order-specific name still travels on the document line (Moloni shows both). The
user also asked for a customisation mechanism, and for other useful extension
points — implemented as WHMCS custom hooks.

## Generic product names

- `LineMapper::genericProductName($type, $displayName)` maps each WHMCS line type
  to a generic PT label (`Registo de Domínio`, `Renovação de Domínio`,
  `Transferência de Domínio`, `Alojamento`, `Addon`, `Upgrade/Downgrade`,
  `Taxa de Instalação`, `Adição de Fundos`, `Taxa de Atraso`), falling back to the
  display name then `Artigo`. These are product data sent to the API (like the
  pre-existing `Alojamento` reference), not UI strings, so they stay hardcoded and
  in Portuguese rather than going through `lang/` — consistent with the existing
  `HOSTING_REFERENCE`.
- `map()` now returns a `productName` key (extracted the old switch into
  `mapByType()`); `LineInput` carries it; `DocumentService::buildLine()` passes it
  to `ProductResolver::resolveId()` as a new `$createName` argument.
- `ProductResolver` uses `$createName` **only** as the created product's name.
  Reference derivation and product matching still use the display name, so lines
  with no explicit reference are unaffected (no accidental merging).

## Custom hooks — `Support\Hooks`

Wraps WHMCS `run_hook()`; a no-op when `run_hook` is undefined (unit tests /
non-WHMCS), so it is always safe to call. Three shapes: `filter()` (replace a
value, last non-empty wins), `doAction()` (notify), `allows()` (veto on `false`).

Hook points wired in:

- `MoloniOnProductName` (filter) — `LineMapper::map()`, full context (type,
  reference, item, displayName).
- `MoloniOnBeforeCreateDocument` (filter over the `<Type>Insert` payload) —
  `createDocumentFromOrder`, before submit.
- `MoloniOnAfterCreateDocument` (action) — after create + persist.
- `MoloniOnBeforeCloseDocument` (veto) — in `closeIfRequested`, before closing.
- `MoloniOnAfterCloseDocument` (action) — after closing.
- `MoloniOnDocumentFailed` (action) — in the failure catch.

Integrators subscribe from `/includes/hooks/` with `add_hook()`; example in
[SETUP.md](../../SETUP.md). Full payload table in [CLAUDE.md](../../CLAUDE.md).

## Notes / left undone

- Could not inspect the Moloni **WooCommerce** plugin locally (not in this
  environment; only Akismet test fixtures under `molonion/test`). The hook set is
  modelled on this plugin's own seams; the WC plugin's `moloni_before/after_insert_document`
  filters map to `MoloniOnBefore/AfterCreateDocument`.
- WHMCS `run_hook()` does not chain filters (unlike WordPress `apply_filters`);
  our `filter()` takes the last non-empty response. Documented as such. If we ever
  need true chaining we'd have to fold responses ourselves.
- Considered a config-UI mapping and a product-custom-field for names too; went
  with hooks per the user's choice (lowest surface, most flexible).

## Verification

- New `tests/Unit/HooksTest.php` stubs a global `run_hook` and covers
  filter/veto/action behaviour; `LineInputTest` extended for `productName`.
- `php -l` clean, phpcs PSR-12 clean, PHPUnit green (56 tests, 147 assertions).

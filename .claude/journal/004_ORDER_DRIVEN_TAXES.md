# 2026-07-02 — Order-driven taxes + product schema pinned to reference

The reference plugins were re-added (in `~/`, one level above the project), so the
best-effort product/tax code from `003` was corrected against the real Moloni ON schema
(`[wordpress_moloni_on]/src/API/*` and `Controllers/OrderProduct.php`, `Tools::getTaxFromRate`).

## Taxes now come from the order, not a setting (the key fix)
Previously a single configured `tax_id` was attached to every product — wrong whenever an
order's VAT rate differs (reduced rates, exemptions, per-zone rates). Now:
- **`TaxResolver`** mirrors `Tools::getTaxFromRate`: looks up a VAT tax by `value` (rate) +
  `fiscalZone`, filtered on `flags=0`, `type=PERCENTAGE(1)`, `fiscalZoneFinanceType=VAT(1)`;
  creates the tax (`taxCreate`) when none exists.
- **`DocumentService`** reads the WHMCS invoice's own `taxrate`/`taxrate2` and applies them
  only to `taxed` line items; each line carries `taxes: [{taxId, value, ordering, cumulative:false}]`.
- A 0% / untaxed line gets `exemptionReason` from the new `exemption_reason` setting
  (replaces the removed `tax_id` setting).
- The document payload now also sets the top-level `fiscalZone` (from the company), which
  the earlier version omitted.

## Product / customer schema corrected to the real API
- `ProductInsert` fields fixed to the reference: `visible, name, reference, summary, price,
  type, productAT{productType}, hasStock, measurementUnitId, productCategoryId` (was wrongly
  `categoryId`), and product `taxes: [{taxId,value,ordering}]` (no `cumulative`).
- `products` search now uses `options.filter` (`reference eq`, `visible in [0,1]`) — the
  previous `options.search` shape was wrong.
- Document product line field is **`qty`** (not `quantity`), and includes `discount`,
  `ordering`, `taxes`, `exemptionReason` — matching `OrderProduct::mapPropsToValues()`.
- New enums: `TaxType`, `TaxFiscalZoneType`, `ProductTypeAT`.

## Not ported (documented scope)
`createTaxFromRateAndCode` in the reference also queries `fiscalZoneTaxSettings` to set
`fiscalZoneFinanceTypeMode` for certain zones; omitted here (standard PT VAT works without it).
Add it if tax creation is rejected in a specific zone.

## Verification (Docker)
- `php -l`: 72 files OK · phpcs PSR-12: 0/0 · phpunit: 15 tests / 31 assertions green

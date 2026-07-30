# 2026-07-30 — Document header fiscal zone always the company's; taxes still follow the setting

## What changed

`DocumentService::buildDocumentPayload()` now resolves **two** fiscal zones
instead of one:

- **Document header** (`fiscalZone` field of the `<Type>Insert`) → **always** the
  company's own fiscal zone (`companyFiscalZone()`), regardless of config.
- **Line VAT** → still honours the `fiscal_zone_based_on` setting
  (`company` | `billing`) via the renamed `resolveTaxFiscalZone()` (was
  `resolveFiscalZone()`), which is passed down through `resolveProductLines()` →
  `buildLine()` → `TaxResolver::resolve()`.

Previously a single zone from `resolveFiscalZone()` (setting-driven) was used for
both the header and the taxes, so choosing "Client billing country" put the
client's zone on the document header too.

## Why

Requested behaviour: the document must always carry the **company** fiscal zone
at the header level (that is the issuer's zone, which is what Moloni expects on
the document), while the **tax** resolution can still look at the client's
billing zone when the operator configures it that way.

## Files touched

- `src/MoloniOn/Services/DocumentService.php` — split the two zones in
  `buildDocumentPayload()`; renamed `resolveFiscalZone()` →
  `resolveTaxFiscalZone()` and narrowed its doc comment to "line taxes only".
- `lang/en.php` / `lang/pt.php` — relabelled the setting ("Tax fiscal zone based
  on" / "Zona fiscal do IVA baseada em") and rewrote the help text: the setting
  drives line VAT only; the document always uses the company zone.
- `CLAUDE.md` — updated the `TaxResolver` bullet and the `fiscal_zone_based_on`
  config-key description to record the header/tax split.

## Notes / left undone

- No stored-config migration needed — the key, its values and the config UI
  control are unchanged; only its scope (taxes, not header) narrowed.
- `resolveTaxFiscalZone()` keeps the existing fallback to the company zone when
  the client has no usable country, so a billing-based config with a countryless
  client is unchanged.
- Verified locally via Docker: `php -l` clean, phpcs (PSR-12) clean, PHPUnit
  green (70 tests). The affected methods are private and require WHMCS, so they
  remain outside unit-test coverage.

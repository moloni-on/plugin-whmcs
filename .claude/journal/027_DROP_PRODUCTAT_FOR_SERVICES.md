# 2026-07-03 — Drop productAT from product creation

The module creates every product as a non-stock **service** (see
[journal (ProductResolver)]). `productAT` is the SAF-T goods classification and
is not required for a service, so it was removed from the `ProductInsert`.

## Change

- `ProductResolver::buildInsert()` no longer sends
  `productAT => ['productType' => ProductTypeAT::GOODS]`. `type => SERVICE` and
  `hasStock => false` stay.
- Removed the now-unused `ProductTypeAT` enum (`src/Moloni/Enums/ProductTypeAT.php`)
  and its import. It had no other references.
- CLAUDE.md enum list updated.

The WooCommerce reference plugin always sends `productAT` because it syncs real
goods with stock; this module only ever bills services, so the field is dropped.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 47 tests, 131 assertions, green.

## Note

- Not exercised against the live API here. If Moloni ON turns out to require
  `productAT` even for services, re-add it with a service-appropriate code.

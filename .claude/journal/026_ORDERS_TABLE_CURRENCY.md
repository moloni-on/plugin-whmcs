# 2026-07-03 — Show currency in the orders table

The orders table showed a bare amount (`number_format`) with no currency, so a
€10 order and a $10 order looked identical.

## Change

WHMCS stores an order's total in the **client's** currency, so — mirroring the
old WHMCS plugin (`[whmcs_moloni_pt]` `WhmcsDB::getCustomerCurrency()`, which
joins `tblcurrencies` on the client's `currency` id for `code`/`prefix`/`suffix`)
— the amount is now rendered in that currency.

- `Whmcs::ordersWithClients()` left-joins `tblcurrencies` on
  `tblclients.currency = tblcurrencies.id` and selects `code`, `prefix`, `suffix`
  as `currency_code` / `currency_prefix` / `currency_suffix`.
- `templates/document.php` formats the Amount column with a small closure:
  `prefix + amount + suffix` (WHMCS style, e.g. `€10.00`, `$10.00 USD`), falling
  back to `amount + code` when the currency has neither prefix nor suffix, and to
  the bare amount when no currency row is found.

Folded into the existing Amount column rather than a new column, since the point
is to show each order's amount in its own currency.

## Verification

- `php -l` on the template: clean.
- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 47 tests, 131 assertions, green (Whmcs is DB-backed / not unit-tested;
  the added columns are additive and don't affect existing callers).

## Notes

- Not rendered against a real WHMCS DB here; the join/columns follow the old
  plugin's proven approach.
- Only the pending-orders table was in scope. The created-documents and
  discarded tables were left unchanged.

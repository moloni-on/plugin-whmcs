# 2026-07-03 — Code-review fixes for the currency-exchange commit

Follow-up to commit `bcac7dc` (currency exchange + old-plugin parity). A high-effort
review surfaced findings; the ones that were real are fixed here. Two candidates were
investigated against the Moloni ON backend source (`/home/fabio/molonion`) and turned
out **not** to be bugs — recorded below so we don't re-flag them.

## Fixed

- **Documents page showed the base-currency total under the client's currency symbol.**
  `persist()` stored `invoice_total` as Moloni's `documentTotal`, which is always in the
  company **base** currency, while `templates/documents.php` renders it with the client's
  currency prefix/suffix. For a foreign-currency order that meant e.g. a €100 document
  shown as "$100.00". `DocumentService::submitAndReconcile()` now reconciles against and
  returns `currencyExchangeTotalValue` (the total converted back to the client currency;
  Moloni returns it as `exchange * totalValue`, and `0` for same-currency documents),
  falling back to `documentTotal` when it's `0`. The stored/displayed total is now in the
  client currency, matching `order_total`.

- **`yourReference` could be an empty string.** It was `trim(invoice->invoicenum)`, and
  WHMCS default numbering leaves `invoicenum` empty. Added `yourReference()` which falls
  back to `'#'.ordernum` so the document always carries a human reference.

- **`CustomerResolver::validateZip()` silently defaulted and had dead validation.** Empty/
  unparseable PT postcodes fell back to `1000-100` with no log (a silent wrong-data path,
  against the "no silent failures" rule) and the trailing `preg_match` on a
  self-constructed `NNNN-NNN` string could never fail. Now logs a warning when defaulting
  an empty code and the dead regex is gone; partial codes are still zero-padded into shape.

- **N+1 custom-field lookup.** `LineMapper::hostingReference()` queried `tblcustomfields`
  once per hosting line. Added a per-instance `(field|package)` memo (`customReferenceFor`)
  so a bulk run over many orders sharing a package hits the DB once.

## Investigated, not a bug (verified against the backend)

- **Foreign-currency reconciliation "leaving documents as drafts."** The `> 0.0` selector
  for `currencyExchangeTotalValue` is safe: the backend (`api/.../mutationCreate.js`)
  computes it as `exchange * totalValue` (always `> 0`) for exchange documents and defaults
  it to `0` for same-currency ones, so the heuristic is equivalent to keying off whether an
  exchange applied — the same pattern the backend itself uses in `sendDocumentMail.ts`.

- **`CurrencyExchange::toBase()` conversion direction.** Dividing by the rate is correct:
  the backend defines `exchange` as foreign units per one base unit (see
  `getConversionRateValues.js`), and `CurrencyResolver` resolves the pair as
  `from = base, to = order`, so `orderAmount / exchange = baseAmount`.

Tests + phpcs still green (49 tests, PSR-12 clean).

# 2026-07-03 — Exchange rates + classic-plugin parity fixes

Scanned document creation in the new plugin against the classic Moloni WHMCS
plugin (`[whmcs_moloni_pt]`) and closed the gaps that mattered.

## 1. Multi-currency exchange (the big one)

The classic plugin (and the WooCommerce/Moloni ON plugin) convert order amounts
to the company's base currency and stamp the document with a currency exchange;
the new plugin did none of this — it sent client-currency amounts as-is, so any
non-base-currency order produced wrong document totals.

Ported the WooCommerce approach:

- **`GraphQL/Queries/GetCurrencyExchanges`** — `currencyExchanges` list query,
  matched by the `pair` search field ("FROM TO", e.g. `EUR USD`). Always sends
  `pagination` (list queries are API-rejected without it).
- **`MoloniClient::findCurrencyExchange($from, $to)`** — returns the matching
  `{currencyExchangeId, exchange}` or null.
- **`Support/CurrencyExchange`** — value object (id + rate) with
  `toBase($amount)` = `$amount / rate`.
- **`Services/CurrencyResolver`** — compares the company base currency
  (`Company::getCurrencyCode()`, new accessor over `company.currency.iso4217`)
  with the WHMCS client's currency (`tblcurrencies.code`). Same/unknown → null
  (no conversion). Different but no Moloni exchange found → throws
  `DocumentException` (matches the classic plugin refusing the document).
- **`DocumentService`** resolves the exchange once, converts every product-line
  price and the payment value to the base currency, and adds
  `currencyExchangeId` / `currencyExchangeExchange` to the `<Type>Insert`.
  Direction: `getCurrencyExchange(base, order)` gives base→order, so
  order-currency ÷ rate = base currency (same as WooCommerce). Tax `value` is a
  percentage, so it is unaffected.
- **Draft-then-close reconciliation** now accounts for the exchange: the WHMCS
  order total is in the client currency, so `submitAndReconcile` compares it
  against the mutation's `currencyExchangeTotalValue` (added to the
  `CreateDocument` response) when present, and against the base-currency
  `documentTotal` otherwise. Without this an exchanged document would never
  match and would be left as a draft.

## 2. Orders with no invoice items are refused

Was: synthesised a single "Order total" line. Now: `guardAgainstNoItems()`
throws `DocumentException` (order marked failed + logged), matching the classic
plugin which required invoice items. Removed the now-dead `singleOrderTotalLine`,
`netAmount`, and the `ORDER_TOTAL_*` constants.

## 3. Reference fields match the classic plugin

Was: `ourReference` and `yourReference` both `#<ordernum|id>`. Now:
`ourReference` = WHMCS invoice id, `yourReference` = WHMCS invoice number
(`tblinvoices.invoicenum`, empty string when unset).

## 4. PT postcode normalization

`CustomerResolver::validateZip()` coerces Portuguese postcodes to `NNNN-NNN`
(default `1000-100` when unusable); non-PT zips pass through. Ported from the
classic plugin's `getZipValidated()`, condensed (pad digits to 7, split 4-3).

## Deliberately NOT changed (per review decisions)

- **Duplicate-document dedupe** against Moloni by reference — skipped; local
  `mod_moloni_on_orders` tracking is considered enough here.
- **Final-consumer (9990) fallback** — kept the new behaviour (create/update a
  real customer) over the classic "Consumidor Final" fallback.
- **Tax value semantics** (percentage vs monetary) and **country/language
  disambiguation** — left as the new plugin already has them.

## Verification

`php -l` clean, phpcs (PSR-12) 0/0, phpunit green (49 tests) with a new
`GetCurrencyExchanges` operation test. The exchange path itself needs a live
Moloni ON company on a non-base currency order to fully exercise end-to-end.

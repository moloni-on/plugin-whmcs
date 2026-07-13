# 2026-07-03 — Default payment method setting + payment gating confirmed

New Settings dropdown for the default Moloni ON payment method, used as the
fallback when a WHMCS gateway can't be matched by name.

## Setting

- New `payment_method_id` config key + `SettingsService::paymentMethodId()`.
- Config page renders it as a `<select>` (with a `— None —` option, value `0`)
  populated live from Moloni ON via the `paymentMethods` query.
- `GetPaymentMethods` now **always** sends pagination (the API rejects
  unpaginated list queries — see [[moloni-list-queries-need-pagination]]) and
  only adds the name `search` when one is provided. New
  `MoloniClient::getPaymentMethods()` lists them all for the dropdown.
- Dispatcher: `configPageData()` fetches them via `safeList()`; `saveSettings()`
  persists the id alongside the other int settings.

## Resolution fallback

`PaymentResolver` now takes `SettingsService`. `resolvePaymentMethodId()` order:

1. an existing Moloni method whose name matches the WHMCS gateway,
2. otherwise the configured **default** `payment_method_id`,
3. otherwise create one named after the gateway (last resort, only when no
   default is set).

Previously step 2 didn't exist — an unmatched gateway always created a new
Moloni payment method, accumulating duplicates. The default now short-circuits
that. Create is kept only as the no-default last resort (see note).

## Payment gating (already in place, confirmed)

The requirement "only send the payment method when the document type supports
it" was already enforced: `DocumentService::resolvePayments()` returns `[]`
unless `DocumentType::hasPayments($documentType)`, and the payload only sets
`payments` when non-empty. Matches the WordPress plugin's `shouldAddPayment()`.
Left as-is; no change needed.

## Docs / tests

- CLAUDE.md config-keys paragraph updated.
- `GraphQLOperationsTest`: updated the payment-methods case for the new
  always-paginate variables shape (list-all + name-search).

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 47 tests, 131 assertions, green.

## Note / decision

- Kept the create-on-missing path as the last resort (only fires when no default
  is configured) so behaviour doesn't regress for un-configured installs. If the
  intent is to *never* auto-create, drop step 3 and return no payment line — a
  one-line change. Flagged to the user.
- PaymentResolver depends on the live API, so it has no unit test; the fallback
  order was verified by reading, not execution.

# 2026-07-03 — Remove receipt entirely

Follow-up to [journal 025](025_REMOVE_BILLS_OF_LADING.md) (bills of lading).
`receipt` was still only excluded from `DocumentType::all()` but kept as a
constant; it is now removed from the plugin outright.

## Change

- `DocumentType::RECEIPT` constant deleted.
- `hasPayments()` no longer lists `RECEIPT` (now: invoice-receipt, pro-forma,
  simplified invoice) and its docblock updated; the `all()` docblock's
  receipts-excluded note removed.
- `doctype_receipt` locale strings removed (en + pt).
- Tests: `DocumentTypeTest` no longer references the removed constant (asserts the
  `'receipt'` and `'billsOfLading'` string literals are absent from `all()`, and
  dropped the receipt `hasPayments` assertion). `GraphQLOperationsTest`'s
  type-parametrization case now uses `invoiceReceipt` instead of `receipt`.
- CLAUDE.md wording updated.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 48 tests, 133 assertions, green.

# 2026-07-03 — Remove bills of lading entirely

Follow-up to [journal 022](022_SETTINGS_DROPDOWNS_AND_AUTO_EXEMPTION.md), which
only dropped `billsOfLading` from `DocumentType::all()` but kept the constant.

The document type is now removed from the plugin outright:

- `DocumentType::BILLS_OF_LADING` constant deleted (and the `all()` docblock
  reworded — only receipts remain "excluded but defined").
- `doctype_billsOfLading` locale strings removed from `lang/en.php` and
  `lang/pt.php`.
- `DocumentTypeTest`: the assertion no longer references the removed constant
  (asserts the `'billsOfLading'` string literal is absent from `all()`).
- CLAUDE.md wording updated.

`hasPayments()` never referenced it, so nothing else changed.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 47 tests, 131 assertions, green.

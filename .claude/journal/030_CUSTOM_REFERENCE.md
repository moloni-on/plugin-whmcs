# 2026-07-03 — CUSTOM_REFERENCE for hosting lines (classic-plugin parity)

Ported the classic Moloni WHMCS plugin's `CUSTOM_REFERENCE` feature: let each
hosting product define its own Moloni reference via a WHMCS product custom field.

## How the classic plugin did it

`Settings::buildProduct()` `case "Hosting"` called
`WhmcsDB::getCustomFieldDescriptionProduct($hostingInfo->packageid)`, which — when
the `CUSTOM_REFERENCE` setting names a product custom field — returned that
field's **description** (`tblcustomfields.description` where `type='product'`,
`fieldname=CUSTOM_REFERENCE`, `relid=packageId`). The reference was that value,
else the default `"Alojamento"`. Only hosting lines used it.

## Implementation here

- `SettingsService::CUSTOM_REFERENCE` (`custom_reference`) + `customReference()`.
- `Whmcs::productCustomFieldDescription(packageId, fieldName)` — same query as the
  classic plugin (reads the field's `description`). Plus
  `Whmcs::productCustomFieldNames()` (distinct `tblcustomfields.fieldname` for
  `type='product'`) to populate the settings dropdown.
- `LineMapper` now takes `SettingsService`; `hosting()` resolves the reference via
  a new `hostingReference()` (custom field description → default `Alojamento`).
  `DocumentService` passes `$settings` into `new LineMapper(...)`.
- Config page: new "Product reference custom field" dropdown listing the product
  custom-field names (value = field name), stored in `custom_reference`;
  `configPageData()` provides `productCustomFields`, `saveSettings()` persists it.
- Locale strings `setting_custom_reference` / `_help` (en + pt).

Kept it faithful to the classic plugin: hosting-only, and it reads the custom
field's **description** column (the classic plugin's convention), not a
per-service value.

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 48 tests, 134 assertions, green.
- Template `php -l` clean.

## Notes

- LineMapper depends on the WHMCS DB (static `Whmcs` calls), so it stays outside
  unit tests; the mapping was verified by reading, not execution.
- Not run against a live WHMCS DB here.

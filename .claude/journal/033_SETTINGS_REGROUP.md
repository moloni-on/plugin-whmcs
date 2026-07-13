# 2026-07-03 — Settings page regrouped into four labelled sections

## What changed

Reorganised the Settings page (`templates/config.php`) for clarity. No config
keys, services, or schema changed — purely presentation + a few lang keys.

Before: one **unnamed** top block mixing document format, payment, and the two
automation toggles; then "Product mapping defaults" (which also held the tax
exemption reason); then "Customer & fiscal zone".

After — four named groups, each with a `<h5>` + intro:

1. **Document defaults** — document type, status, document set, payment method.
2. **Automation** — auto-create on paid, e-mail to customer.
3. **Product mapping defaults** — measurement unit, product category, reference
   custom field.
4. **Tax & fiscal zone** (was "Customer & fiscal zone") — fiscal zone based on,
   VAT field, and the **tax exemption reason moved here** from product mapping.

## Why

- The primary (largest) group had no heading while the two lesser groups did.
- That group mixed three mental models: what document to create (format),
  payment, and behaviour/automation. Split format vs automation.
- Exemption reason is a tax concept, not a product-creation default; it now sits
  with the other tax/fiscal settings.

## Lang keys

Added `settings_document_defaults`(_help) and `settings_automation`(_help) in
`lang/en.php` + `lang/pt.php`. Retitled `settings_customer_mapping` to
"Tax & fiscal zone" / "Impostos e zona fiscal".

## Notes

`templates/` is outside the phpcs scope (phpcs.xml lints only `src`, `tests`,
`moloni_on.php`, `hooks.php`), so the long-line warnings on the template are
pre-existing template style, not new. `php -l` clean on all three files.

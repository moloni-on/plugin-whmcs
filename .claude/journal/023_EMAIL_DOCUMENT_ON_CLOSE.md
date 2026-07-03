# 2026-07-03 — Optionally e-mail the document to the customer on close

New Settings toggle: e-mail the created document to the order's customer.
Mirrors the WordPress plugin's `SendDocumentMail` service and `<type>SendMail`
mutation.

## Behaviour

- New `send_email` config key + `SettingsService::sendEmail()`.
- Only sends when the document actually ends up **closed** — i.e. the
  `document_status` setting is Closed *and* the totals reconciled. A draft has no
  final number to send, so it is never e-mailed (guarded in
  `DocumentService::submitAndReconcile()` on `status === DocumentStatus::CLOSED`).
- Recipient: the WHMCS client's `email`; name is company name → contact name →
  "Customer" (`DocumentService::customerName()`).
- Non-fatal: the document already exists and is closed, so a mail API failure or
  a customer with no e-mail is logged (`warning`/`error`) and swallowed — it must
  not mark the order failed. (`sendEmailIfRequested()` catches `ApiException`.)
- Applies to both manual single-order creation and bulk, since both route
  through `createDocumentFromOrder()`.

## Plumbing

- New `GraphQL/Mutations/SendDocumentMail` — parametrized by document type like
  `CreateDocument`/`UpdateDocumentStatus`: operation `<type>SendMail`, variables
  `documents: [Int]!` and `mailData: MailData { to {name,email}, message,
  attachment:true }`. The mutation resolves to a bare scalar (no data/errors
  sub-selection); `ApiClient::assertNoErrors()` still catches transport/GraphQL
  errors, and `MoloniClient::run()` tolerates the non-array `data`.
- `MoloniClient::sendDocumentMail($documentId, $name, $email, $documentType)`.
- Dispatcher `saveSettings()` persists the checkbox; config template adds it
  under "auto create" with help text; `setting_send_email` /
  `setting_send_email_help` locale strings (en + pt, informal second person).

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 47 tests, 130 assertions, green (added `SendDocumentMail` operation +
  variables coverage).

## Notes / left undone

- Message body is sent empty (Moloni uses its own template), matching the
  WordPress plugin. No per-order override.
- Not exercised against the live API / a real WHMCS install here.

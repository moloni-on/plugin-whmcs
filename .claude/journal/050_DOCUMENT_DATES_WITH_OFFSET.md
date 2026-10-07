# 2026-10-07 — Document and payment dates sent with an explicit timezone offset

## What changed

- New `Support\DocumentDate`:
  - `now()` — the current instant as ISO-8601 in UTC (`2026-10-03T23:40:00+00:00`).
  - `startOfDay($ymd, $timezone)` — a WHMCS calendar day (invoice `duedate`) as
    midnight in the company's timezone, with offset (`2026-10-04T00:00:00+01:00`);
    `null` for empty / `0000-00-00` / invalid dates; unknown or invalid timezone
    falls back to UTC.
- `DocumentService::buildDocumentPayload()` sends `date` = `DocumentDate::now()`;
  `expirationDate()` uses `DocumentDate::startOfDay()` with the company timezone.
- `PaymentResolver` payment line `date` = `DocumentDate::now()`.
- `GetCompany` now requests `timezone { name }`; `Company::getTimezone()` reads it.

## Why

Moloni ON stores a document date as an absolute instant (UTC) and renders it in
the company's timezone (app, PDF, SAF-T, reports). A date without an offset is
read as UTC. The module sent `date('Y-m-d H:i:s')` — the WHMCS server's local
wall-clock time with no offset — so it was only correct when PHP ran in UTC.
With `date.timezone = Europe/Lisbon`, documents were rendered one hour late in
summer, and a document issued between 23:00 and midnight landed on the next
day (wrong fiscal date). Sending the instant with its offset is correct
whatever the server timezone.

Triggered by a support case: document shown at 04/10 00:40, its Moloni ON log at
03/10 23:40. That pair is the same instant (the log shows UTC) — that server ran
PHP in UTC, so it was right by coincidence.

## Decisions

- The due date is a day, not an instant: midnight is anchored in the company
  timezone so it renders on the same day. The fallback is UTC (not the
  platform's own default timezone): UTC midnight renders at 00:00–02:00 in every
  European zone, so the day never shifts — the previous behaviour.
- The local `mod_moloni_on_documents.invoice_date` (a `date` column) and the
  module's own log timestamps are untouched — they never reach Moloni ON.

# 2026-07-03 — Orders page: show Paid orders only

## What changed

The Orders (pending) page now lists only WHMCS orders whose invoice status is
`Paid`, matching the classic `whmcs_moloni_pt` plugin.

- `Whmcs::ordersWithClients()` gained a `bool $paidOnly = false` parameter. When
  true it `join`s `tblinvoices` on `tblorders.invoiceid` and filters
  `tblinvoices.status = 'Paid'`.
- `OrderService::getPendingOrders()` calls `ordersWithClients(500, true)`.

## Why

The new plugin queried `tblorders` with **no** WHMCS-status filter, so it
surfaced every order (Pending/Active/Cancelled/Fraud) that wasn't already
synced/discarded — e.g. showing 3 orders (with a failed *sync* status) where the
old plugin showed an empty list. The classic plugin only ever listed **Paid
invoices** (`tblinvoices.whereIn('status', ['Paid'])`). This restores parity.

Note the two distinct "status" notions: WHMCS invoice/order status (Paid, …) vs
the Moloni sync-tracking status (pending/synced/discarded/failed). The "3 failed"
seen was the sync status, not the invoice status.

## Deliberately left as-is

- The **Discarded** page still uses `ordersWithClients()` without the paid
  filter, so a discarded order stays visible/revertable regardless of its
  current invoice status.
- The Paid filter is hardcoded (not configurable), like the classic plugin's
  active `$orderStatusToShow = ['Paid']` (Unpaid/Payment Pending were commented
  out there too).

Verified: `php -l` clean, phpcs (PSR-12) 0/0 on both changed files.

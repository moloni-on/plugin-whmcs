# 2026-07-13 — Make the InvoicePaid auto-create hook observable

## Problem

Reported: paid orders were not turning into Moloni documents automatically.
Confirmed with the user that `auto_create` was enabled and payments were being
applied via the WHMCS admin *Add Payment* flow, yet **no log line appeared at
all** in the Logs tab.

The `InvoicePaid` hook in `hooks.php` had three silent `return` paths before any
logging:

1. `auto_create` disabled,
2. no WHMCS order for the paid invoice (`getOrderByInvoice()` → null),
3. the order already `synced`/`discarded`.

So from the outside the hook was a black box: "nothing happened, no log" could
mean the hook never fired, or it fired and bailed at (2) or (3). No way to tell.

## Change

Instrumented the hook so every exit path past the `auto_create` check leaves a
log line (`hooks.php`):

- entry trace once an order is resolved (`Auto-create triggered by paid invoice`),
- `info` when the paid invoice maps to no order,
- `info` when the order is already synced/discarded (states the status),
- existing `warning` when not authenticated,
- `SkippedException` now caught separately and logged at `info` (intentional
  non-billing: no items / mass-payment), distinct from real failures which stay
  at `error`.

All lines now carry `invoice_id` (and `order_id` where known). The
`auto_create`-disabled path is deliberately left silent — the hook fires on
every paid invoice and logging there would be noise for installs that don't want
automatic documents.

## Diagnostic value

After deploying this, one more test payment is decisive:

- **Still nothing in logs** → the hook is not being loaded (stale/missing
  `hooks.php` in `modules/addons/moloni_on/` on the server, or a load-time
  fatal). Not a logic bug.
- **"not linked to a WHMCS order"** → the paid invoice isn't in `tblorders`
  (manually-created test invoice, or a recurring/renewal invoice — those aren't
  order rows).
- **"already synced/discarded"** → idempotency guard working as intended.
- **"not authenticated"** → the OAuth session needs re-login.
- **"Automatic document creation failed"** → real error, message included.

## Not done

- No change to *which* hook is used (`InvoicePaid`, priority 1) — this matches
  the classic PT plugin. Renewal/recurring invoices (no order row) remain out of
  scope by design.
- Did not add a DB-level claim/lock for the create race (still tracked
  elsewhere).

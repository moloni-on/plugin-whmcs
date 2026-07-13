# 2026-07-03 — Discarded orders moved to their own nav tab

## What changed

The "Discarded orders" list, previously a second table on the Documents page, now
lives on its own dashboard tab.

- **New nav tab** `discarded` in `Blocks/navbar.php`, between Documents and Settings
  (lang key `nav_discarded`, en + pt).
- **New template** `templates/discarded.php` — the discarded-orders panel moved out of
  `documents.php` verbatim, now paginated with the plain `page` query param.
- **`documents.php`** is now documents-only: a single table + single paginator
  (`['action' => 'documents']`). Dropped the `$docPage`/`$discPage` cross-page bookkeeping.
- **`Dispatcher`**: added `discarded` to `PAGES`; split the old `documents` pageData case
  into two — `documents` (created documents) and `discarded` (discarded orders) — each
  reading its own `page` param. `pageTemplate()` needs no change (`discarded` maps to
  `discarded.php` by default).

No service/model changes: `OrderService::getCreatedDocuments()` and
`getDiscardedOrders()` already returned independent `Paginator`s.

## Why

Follow-up to [021](021_LIST_PAGINATION.md). Putting two lists on one page forced a
dual-paginator scheme (`page` + `dpage`, each carrying the other's page number). The two
lists are different entities — created documents (local `mod_moloni_on_documents`, with
PDF download) vs. discarded WHMCS orders (order state in `mod_moloni_on_orders`, with a
revert action) — with different columns, actions and data sources. Merging them into one
table (or backing discards with a documents-table row) would mean a type column plus
empty cells and would split the "is this order pending?" check across two tables. Giving
each list its own tab is the clean fix: one list, one `page` param, no `dpage`.

## Left undone

- Nothing outstanding for this change. The 15/page size and the upstream 500-row
  `ordersWithClients()` cap noted in [021](021_LIST_PAGINATION.md) are unchanged.

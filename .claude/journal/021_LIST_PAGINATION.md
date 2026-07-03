# 2026-07-03 — Server-side pagination for the list pages

## What changed

The Orders, Documents (created + discarded) and Logs pages now paginate at 15 rows
per page instead of dumping a single capped result set.

- **`Support\Paginator`** (new): immutable page state holding the current page's
  items plus the totals the UI needs (`total`, `page`, `pageCount`, `from`/`to`,
  `hasPrev`/`hasNext`, `pages()` with a `0` gap marker for elision). Two constructors:
  - `fromSlice(array $all, $page, $perPage)` — for lists assembled/filtered in PHP.
  - `paginate($page, $total, $perPage, callable $fetch)` — clamps the page against
    `$total`, then calls `$fetch($offset, $limit)` to pull only that page's rows.
    `PER_PAGE` = 15.
- **`Models\Log`**: `fetch()` gained an `$offset` param; new `countFiltered()`; the
  shared where-clause building extracted into a private `applyFilters()`.
- **`Services\LogService::getLogs()`** and **`Services\OrderService`**'s
  `getPendingOrders()` / `getCreatedDocuments()` / `getDiscardedOrders()` now take
  `(page, perPage)` and return a `Paginator` instead of a raw array.
- **`Admin\Dispatcher::pageData()`**: reads the page from the query string via a new
  `pageParam()` helper, unwraps `->items()` for the existing template var and passes the
  `Paginator` alongside for the controls. Logs also pass `logFilters` so page links keep
  the active level filter.
- **`Support\Template`**: new `$paginate($paginator, $baseParams, $pageParam)` closure
  exposed to every template, rendering the new **`templates/Blocks/pagination.php`**
  partial (Bootstrap `.pagination`; renders nothing for single-page lists).
- Templates `document.php`, `documents.php`, `logs.php` render the controls after their
  tables. New lang keys `pagination_*` (en + pt). New `.moloni-on__pagination` CSS.
- **`tests/Unit/PaginatorTest.php`**: covers slicing, page clamping (above range / below
  1), empty lists, `paginate()` offset resolution + empty-skip, and `pages()` elision.

## Decisions

- **Server-side page links, not DataTables.** Matches the existing server-rendered
  template approach and adds no JS dependency.
- **Two paginated lists on the Documents page** (created + discarded) use distinct page
  params (`page` / `dpage`); each control carries the other's current page in
  `$baseParams` so paging one list never resets the other.
- Pending/discarded orders are still filtered in PHP over the existing recent-orders
  window (`Whmcs::ordersWithClients()`, capped at 500) and then sliced — pagination did
  not change that upstream cap. Documents and Logs paginate at the SQL level
  (COUNT + OFFSET/LIMIT).

## Left undone

- The 500-row source cap on `ordersWithClients()` is unchanged; very large WHMCS
  installs still only see the 500 most recent orders across all order pages.
- Per-page size is fixed at 15 (no user-selectable page length).

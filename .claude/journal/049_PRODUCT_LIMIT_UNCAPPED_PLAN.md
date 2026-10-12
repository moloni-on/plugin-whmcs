# 2026-10-01 — Don't block product creation on an uncapped plan

## What changed

Code review on the product-limit pre-check (journal 048) found that Moloni ON
reports `limit: 0, remaining: 0` for a resource with **no cap** (an unlimited
plan), not just for a genuinely full one. `Company::canCreateProducts()` only
looked at `remaining`, so it would refuse every product create on an uncapped
plan.

- `GetCompany` / `GetCompanies` also select `limit` (alongside `resource` /
  `remaining`) in `limits`.
- `Support\Company::canCreateProducts()` now blocks only when the entry has a
  positive `limit` **and** `remaining` is at 0 (`limit > 0 && remaining <= 0`).
  `limit: 0` (or no entry, or the company not loaded) doesn't block.

## Why

A plan with no product cap still needs to create products; reading `remaining:
0` alone as "full" broke that entirely.

## Tests

`CompanyTest::testCanCreateProductsAllowsAnUncappedPlan` /
`testCanCreateProductsBlocksACappedPlanWithNothingLeft` /
`testCanCreateProductsAllowsACappedPlanWithRoomLeft` and
`ProductResolverTest::testMissingProductOnAnUncappedPlanIsCreated` (plus the
existing full/room-left cases, now built with an explicit `limit`). PHPUnit
green on PHP 7.4 and 8.2; phpcs clean.

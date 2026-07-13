# 2026-07-13 — currencyExchanges query rejected companyId

## What changed

`GetCurrencyExchanges` declared and passed a `companyId` argument to the
`currencyExchanges` root query. The Moloni ON API rejects this with a
`GRAPHQL_VALIDATION_FAILED`:

```
Unknown argument "companyId" on field "Query.currencyExchanges".
```

`currencyExchanges` is a **global** query — it is not scoped by company, so it
takes no `companyId` argument. Removed `$companyId: Int!` from the operation
signature and `companyId: $companyId` from the field call in
[GetCurrencyExchanges.php](../../src/Moloni/GraphQL/Queries/GetCurrencyExchanges.php).

## Why the injected variable is harmless

`ApiClient::request()` still injects `companyId` into the variables map for
every request. That is fine: GraphQL servers ignore variables sent in the
`variables` map that the operation document does not declare. This is the same
reason `GetCountries` and `GetDocumentPdfToken` — which also take no
`companyId` — already worked despite the injection. Only *passing companyId as
a field argument* in the query document is a validation error, which is what
this query was doing.

## Impact

Every foreign-currency document sync failed at currency-exchange resolution
(`CurrencyResolver`), so orders billed in a client currency other than the
company base currency could not be created. Now resolves correctly.

## Test

Added an assertion in `GraphQLOperationsTest::testGetCurrencyExchangesSearchesByPair`
that the query document contains no `companyId`, locking in the constraint.
Full suite green (60 tests), phpcs clean.

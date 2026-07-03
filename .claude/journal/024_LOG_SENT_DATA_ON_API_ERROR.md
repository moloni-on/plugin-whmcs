# 2026-07-03 — Include sent request data in API error log context

## What changed

`ApiClient::assertNoErrors()` now receives the request `$variables` and includes
them under a `sent` key in the `ApiException` context, alongside the existing
`errors`. This applies both to the per-operation rejection
("Moloni ON API rejected the request.") and the top-level GraphQL error
("Moloni ON API error.").

The context flows through `MoloniException::getData()` into the `data` field of
the `Document creation failed.` log entry, so a rejected sync now records the
exact payload that was sent, not just the API's error list.

Before:

```json
{
  "order_id": 3,
  "error": "Moloni ON API rejected the request.",
  "data": { "errors": [ { "field": "zipCode", "msg": "Invalid zip code format" } ] }
}
```

After: the same entry, with `data.sent` holding the operation's variables (the
document payload), making field-level errors like `zipCode` directly traceable
to the value that caused them.

## Why

Errors like "Invalid zip code format" were unactionable without knowing what
value was actually sent. Logging the payload closes that gap.

## Notes

- Safe re: secrets — OAuth tokens are sent in the `Authorization` header, never
  in GraphQL variables, so nothing sensitive is added to the log.

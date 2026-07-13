# 2026-07-03 — Kill the OAuth session when a token refresh fails

## What changed

`AuthService::ensureAuthenticated()` now tears down the session when a token
refresh cannot recover it. Previously, when `refresh()` returned `false`
(refresh token missing/expired, or the grant call errored/returned null), the
method just returned `false` and left the stale tokens sitting in
`mod_moloni_on_auth`. The Dispatcher would show the login page, but the dead
session lingered in the DB and every subsequent request re-attempted the same
doomed refresh.

Now, on any refresh failure, `ensureAuthenticated()` calls `logout()`
(`Auth::clearTokens()` + `Context::reset()`), which wipes the access/refresh
tokens and the selected company while keeping the developer credentials
(client id/secret). The admin must complete the OAuth login flow again.

Also added a `LoggerFacade::warning()` to the "refresh token missing/expired"
branch of `refresh()`, which was previously a silent failure (CLAUDE.md
mandates no silent failures; the grant-error branch already logged).

## Why

Requested behaviour: if the Moloni refresh token expires, or an error occurs
while fetching a new access token, the Moloni "session" must be killed so the
user is forced to log in from scratch — rather than being stuck with a broken,
half-authenticated session.

## Decisions / notes

- **Any refresh failure kills the session**, including a transient network
  error during the grant call (both surface as `refresh()` returning `false`).
  This matches the explicit requirement. Worst case for a transient blip is one
  extra re-authorization, which is acceptable.
- **Soft logout, not full delete.** `clearTokens()` keeps the developer
  credentials so re-login is just the authorize→grant round trip; it does not
  truncate the row. This is the existing `logout()` semantics ("kill session,
  keep app registration").
- No new tests: `AuthService`/`Auth` depend on the WHMCS DB and are not covered
  by the framework-independent unit suite. `php -l` clean, phpcs (PSR-12) green.
- ARCHITECTURE.md security section updated to describe the clear-on-failure
  behaviour accurately (it previously claimed clearing happened, but the code
  did not actually do it).

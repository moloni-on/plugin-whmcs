# 2026-11-24 — Public-client OAuth + PKCE connect flow

## What changed

Connecting no longer needs an API Client ID / Client Secret pasted in: the
login page is a single "Connect" button.

- `ApiClient::register(string $redirectUri): string` — RFC 7591 dynamic client
  registration (`POST /auth/register`, JSON `{client_name, redirect_uris}`).
  `client_name` includes the redirect host (`Moloni ON WHMCS (<host>)`) so the
  server's name-based dedup finds this install's own client again on a repeat
  call. Maps the known failure shapes to a user-facing message: `400
  invalid_redirect_uri` → HTTPS required, `429` → rate limited, anything else →
  generic. Nothing is cached beyond the returned `client_id`: registration runs
  again on every Connect click.
- `ApiClient::authorizeUrl()` now takes `$codeChallenge` + `$state` and builds a
  PKCE authorization request (`response_type=code`, `client_id`, `redirect_uri`,
  `code_challenge`, `code_challenge_method=S256`, `state`) — all snake_case, the
  authorize endpoint's only accepted casing (unlike grant/refresh).
- `ApiClient::grant()`/`refresh()` take the stored `client_secret` as before,
  but now omit `clientSecret` from the request entirely when it is empty
  (public client), and `grant()` gains optional `$codeVerifier`/`$redirectUri`
  (snake_case), only sent for a public client. A non-empty secret (legacy
  client) still gets exactly the pre-PKCE request — same fields, same
  values — nothing about that path changed. The field-building was pulled out
  into `ApiClient::grantFields()`/`refreshFields()` (pure, static) so this is
  unit-testable without a network call.
- New `Support\Pkce`: `verifier()` (32 random bytes, base64url) and
  `challenge()` (S256 of the verifier). Framework-free on purpose, so the PKCE
  crypto has its own unit tests.
- `AuthService::startConnect()` replaces `setClient()` + `authorizeUrl()`:
  registers the public client, stores it with an empty secret
  (`Auth::setClient($clientId, '')`), generates the verifier + `state`, stores
  both together in one session entry, and returns the authorize URL. Only
  reachable while no session is authenticated (see below), so it can never
  overwrite a connected store's credentials.
- `AuthService::verifyState()` now returns the stored verifier (or `null`)
  instead of a bool — still timing-safe (`hash_equals`) and single-use (the
  session entry is unset either way), but now also hands back what
  `exchangeCode()` needs to complete the PKCE proof.
- `Dispatcher`: the login form posts `op=connect` with no fields (just CSRF).
  `connect()` is only reached while `ensureAuthenticated()` is false — a stale
  Connect click (second tab, double submit) on an already-connected store now
  falls through to the normal dashboard flow instead of truncating
  `mod_moloni_on_auth`. The callback handler (`completeCallback()`, replacing
  `resolveAuthenticatedState()`) is likewise only run while not authenticated,
  so a stray `?code`/`?error`/stale `state` can't touch a connected store; a
  `?error=` from Moloni ON shows a message and stores nothing.

## Why

Creating an API client (and copying a Client ID + Secret) was friction a
merchant shouldn't need just to connect this module. Moloni ON's public API
now supports public OAuth clients: registering one dynamically with PKCE gets
the same tokens without a secret ever existing on this side.

## Decisions

- **Legacy compatibility is structural, not a feature flag.** Every grant/
  refresh/authorize call branches on "is the stored secret empty", not on a
  module version or a setting — so a pre-existing connection with a
  developer-made client (non-empty secret) is byte-for-byte the old request
  forever, with no migration step and no forced reconnect.
- **No new storage.** The PKCE verifier lives in the same PHP session entry as
  the CSRF `state` (one array, one key) — no new table/column, and the client
  id still goes into the existing `mod_moloni_on_auth.client_id` column with
  an empty `client_secret`.
- **`ensureAuthenticated()` already protected a pending connect.** Checked
  whether the `InvoicePaid` hook's background `ensureAuthenticated()` call
  could wipe a pending connect (a row with a `client_id` but no tokens yet,
  mid-flow). It already returns `false` immediately when `access_token` is
  empty, before touching `refresh()`/`logout()` — so a pending connect was
  already safe; no change was needed there.
- Removed the now-dead `developer_id`/`client_secret`/`credentials_required`
  lang strings and the matching login-form fields; nothing else referenced
  them.

## Tests

New `PkceTest` (verifier length/charset, challenge = base64url(sha256(verifier)))
and `ApiClientTest` (`authorizeUrl()` snake_case + PKCE fields, `grantFields()`/
`refreshFields()` for a legacy vs. a public client). PHPUnit green (PHP 8.3
locally and via the `composer:2` Docker image); phpcs clean.

## Left undone

- The pre-existing `expiresIn` vs `accessTokenExpiracy` token-lifetime parsing
  mismatch (`AuthService::storeTokens()`) is untouched — out of scope here.
- No runnable local WHMCS (licensed install) in this environment, so the
  connect/callback/legacy-refresh flows are verified by reading the code and
  the new unit tests, not by driving the admin UI end to end.

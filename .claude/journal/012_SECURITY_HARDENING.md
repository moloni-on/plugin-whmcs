# 2026-07-03 — Security hardening: CSRF-safe logout & auth-context redaction

A read-only security review (secure-reviewer) found no Critical/High confirmed
vulnerabilities. All DB access is parameterised, template output is escaped, GraphQL
uses variables, OAuth uses a `state` nonce validated with `hash_equals`, tokens travel
in the `Authorization` header, and POST actions already go through WHMCS `check_token()`.
Two low-risk items were fixed here; a third (OAuth `redirectUri` built from
`HTTP_HOST`/`PHP_SELF`) is deferred pending confirmation of Moloni's redirect-URI
matching.

## Changes

1. **Logout is now a CSRF-protected POST.** Previously `action=logout` fired as a GET
   link in `Blocks/navbar.php`, which the CSRF check skips (it only runs for POSTs), so a
   forged GET (e.g. an `<img>` tag) could tear down an admin's connected session.
   - `Dispatcher::dispatch()` now keys logout on the POST `op === 'logout'` instead of
     `action === 'logout'`, so it inherits the existing `verifyCsrf()` gate. Preconditions
     are unchanged (authenticated + company selected).
   - `Blocks/navbar.php` renders the logout control via the existing `$postForm` helper
     (which embeds the WHMCS token) instead of a GET anchor. Since the logout is now a
     `<button>` inside a `<form>` rather than an `<a class="nav-link">`, a small
     `public/css/style.css` rule (`.moloni-on__logout` + a flex `.moloni-on__inline-form`
     in the navbar) resets the button chrome so it stays visually identical to the tab links.

2. **Auth-endpoint responses are redacted before entering exception context.** Grant/refresh
   failures in `ApiClient` threw exceptions carrying the raw/parsed auth response. Nothing
   logs `$e->getData()` today (only messages are logged), so no secret was persisted — this
   is defence-in-depth against future logging/display. Added `ApiClient::redactSecrets()`,
   which masks `accessToken`, `refreshToken`, `clientSecret`, `apiClientId`, and `code`; it
   is applied to the two `AuthException`/`ApiException` sites that include the decoded
   response.

## Deliberately left undone

- OAuth `redirectUri` derivation from `HTTP_HOST`/`PHP_SELF` (`absoluteModuleUrl()`). A
  spoofed `Host` could redirect the auth `code` if Moloni does not enforce exact
  redirect-URI matching. Fix would be to source the base URL from a trusted WHMCS system
  setting; deferred pending confirmation of the server-side allow-listing behaviour.

## Verification

`php -l` clean; phpcs (PSR-12) 0 errors/0 warnings on the changed in-scope files
(`templates/` is outside the phpcs scope); PHPUnit green (34 tests, 88 assertions).

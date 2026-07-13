# 2026-07-13 — Re-namespace `Moloni\` → `MoloniOn\` so both plugins can coexist

## Real root cause of "auto-create never fired"

Journals 039/040 chased the auto-create hook: first added decision-point
logging (039), then—reading a probe that showed only `AddInvoicePayment`—added
that hook (040). **That theory was wrong.** The decisive clue came next: the
user runs the **legacy Moloni PT plugin and this new Moloni ON plugin together**,
and on the same paid invoice the *old* plugin's hook fired while the new one did
nothing and logged nothing — even though the old plugin only registers
`InvoicePaid`. So `InvoicePaid` clearly fires; the new plugin was the problem.

Cause: **both plugins declare the same PSR-4 root** `Moloni\ → src/Moloni/`, with
overlapping but **incompatible** classes, e.g.:

- `Moloni\Enums\DocumentType` — new: `const INVOICE = 'invoice'`; old: only
  `const INVOICES = 'invoices'` (no `INVOICE`).
- `Moloni\Facades\LoggerFacade::error()` — new: `(string,array,?int)` writing to
  `mod_moloni_on_logs`; old: `(string,$context=[])` writing to *its own* table.

When both addons are active in one request, each `hooks.php` requires its own
`vendor/autoload.php`, so **two Composer autoloaders both claim `Moloni\`**. For
any shared FQCN, PHP loads whichever file resolves first and caches it
process-wide. The new hook then runs against the *old* plugin's classes:
`DocumentType::INVOICE` is undefined → fatal → caught → logged through the *old*
`LoggerFacade` → the entry lands in the old plugin's table. Net effect: the new
plugin "does nothing" and its Logs tab stays empty. The old plugin works because
it only ever touches its own, self-consistent classes.

## Fix

Gave this module a distinct root namespace so there is zero overlap with the
legacy plugin:

- `git mv src/Moloni src/MoloniOn`.
- Replaced every `Moloni\` token with `MoloniOn\` across `src/`, `tests/`,
  `templates/`, `moloni_on.php`, `hooks.php` (213+42+7+4+9 refs). The bare
  product name "Moloni"/"Moloni ON", `mod_moloni_on_*` tables and the
  `moloni_on_*` WHMCS entry functions are unchanged — only the `Moloni\`
  namespace token moved.
- `composer.json`: `MoloniOn\ → src/MoloniOn/`, `MoloniOn\Tests\ → tests/`.
- Regenerated the optimized autoloader.
- Docs updated (CLAUDE.md conventions now explains *why* the namespace is
  `MoloniOn\` and warns never to reintroduce a `Moloni\` symbol; README /
  ARCHITECTURE / SETUP paths + code samples updated).

Verified: phpcs (PSR-12) clean over 84 files, PHPUnit 60/60 green, PSR-4 map
resolves `MoloniOn\` → `src/MoloniOn`.

## Why this is sufficient

The only shared surface between the two plugins was the PHP namespace. Other
identifiers are already distinct: WHMCS entry functions (`moloni_on_*` vs the
old prefix), DB tables (`mod_moloni_on_*`), and Composer's own
`ComposerAutoloaderInit<hash>` classes (unique per build). The one remaining
shared third-party package is `psr/log` (stable, identical 1.x interfaces), so
its definitions are interchangeable regardless of load order.

## Status of the 039/040 changes

- 039's decision-point logging in `hooks.php`: **kept** (genuinely useful).
- 040's `AddInvoicePayment` hook: the user reverted it; not needed —
  `InvoicePaid` fires. Left out. `hooks.php` currently listens on `InvoicePaid`
  only, matching the legacy plugin.

## Follow-ups

- Deployment: rebuild (`composer build`) and redeploy. With the namespace split,
  the new plugin's `InvoicePaid` hook should now run and log even with the old
  plugin still installed.
- Remove the temporary `includes/hooks/moloni_debug_probe.php` from the server.

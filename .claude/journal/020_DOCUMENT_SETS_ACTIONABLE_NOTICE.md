# 2026-07-03 — Surface an actionable notice when document sets can't load

## Symptom

Opening **Settings** produced (in the logs, and effectively a dead dropdown):

```json
{
  "error": "Moloni ON API rejected the request.",
  "errors": [
    {
      "field": "documentSetId",
      "msg": "There is no default document set defined for your company, please add one"
    }
  ]
}
```

## Root cause

This is not a plugin bug — the Moloni ON `documentSets` query is genuinely
rejected when the connected company has no document set (série) defined. Since
[journal 015](015_UI_FIXES_AND_DOCUMENTSETS_AUTH.md) fixed the missing
pagination, the request is well-formed; the API is reporting a real company
configuration gap.

Previously `Dispatcher::safeDocumentSets()` swallowed this into a log-only
WARNING and returned `[]`, so `config.php` rendered only the vague
`setting_document_set_unavailable` dropdown option ("Document sets unavailable
(check connection)"). The admin never learned the actionable reason.

## Fix

- `safeDocumentSets()` now also queues a user-facing flash message
  (`document_sets_unavailable`) explaining to define a document set in Moloni ON
  and reload. New `Dispatcher::warning()` helper pushes a Bootstrap
  `alert-warning` (the messages block already renders arbitrary alert types).
- `renderPage()` now evaluates `pageData()` **before** `sharedData()`. Flash
  messages queued during page-data assembly (like the one above) are collected
  into `$this->messages` before `sharedData()` snapshots them for the template —
  previously the left-operand `sharedData()` snapshotted an empty list.
- New locale strings `document_sets_unavailable` in `lang/en.php` and
  `lang/pt.php` (pt uses the informal second person per project convention).

## Verification

- phpcs (standard scope): 0 errors / 0 warnings.
- PHPUnit: 35 tests, 90 assertions, green.
- `lang/` is outside the phpcs scope; its long-line warnings are pre-existing and
  match the surrounding string style.

## Left undone

- Not rendered in a real WHMCS browser here; the alert wiring is CSS/markup that
  already exists for other flash messages.

# 2026-07-03 — Footer help/guides link; drop login credentials-help link

## Changes

- **Removed** the "Where do I find my API credentials?" link from the login
  page (`templates/login.php`) — it pointed at the AC host and duplicated
  guidance. Dropped the now-unused `login_help` string from `lang/en.php` and
  `lang/pt.php`, and the orphaned `.moloni-on__help` CSS rule.
- **Reworked** the shared footer (`templates/Blocks/footer.php`) to read
  "Need help? Check out our guides." (pt: "Precisas de ajuda? Consulta os
  nossos guias."), where the second sentence links to the help page. Dropped
  the old "Moloni ON for WHMCS · v1.0.0 · " note and the standalone FAQ link.
  Because `renderLayout()` always renders `Blocks/footer`, the link shows on
  every page (login, company select, and all dashboard pages).
- i18n keys `footer_help_lead` + `footer_help_link` replace the old
  `footer_note`/`footer_faq`.
- **Centralised the URL**: the help page is now `Platform::HELP_URL`
  (`https://www.molonion.pt/help`), referenced from the template by FQN, so all
  external endpoints live alongside the other Platform constants instead of
  being hardcoded in a template.
- Simplified the footer CSS (removed the orphaned `.moloni-on__footer-sep`
  separator rule; the link now carries its own left margin).

## Notes

- No schema/service/flow changes; docs unaffected (CLAUDE.md's UI-pages section
  doesn't enumerate footer/login link contents).

## Verification

- phpcs (standard scope: src/tests/entry points): 0 errors / 0 warnings.
- PHPUnit: 34 tests, 88 assertions, green.

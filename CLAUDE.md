# CLAUDE.md

Guidance for working in this repository.

## What this is

A **WHMCS addon module for Moloni ON** — syncs WHMCS orders into [Moloni ON](https://www.molonion.pt/) (a Portuguese invoicing platform) as invoices/documents via its GraphQL API (`https://api.molonion.pt/graphql`).

**Current status: planning/documentation phase.** No source code exists yet — the repo currently holds only specification and planning docs. When implementing, follow the structure and conventions defined below and in the planning docs.

## Documentation map

| Doc | Purpose |
|-----|---------|
| [MOLONI_ON_WHMCS_PROMPT.md](MOLONI_ON_WHMCS_PROMPT.md) | Full project spec: requirements, features, acceptance criteria |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Layered system design, services, models, data flow, DB schema |
| [SETUP.md](SETUP.md) | Install/config guide, DB table DDL, deployment checklist |
| [.claude/PROJECT_PLAN.md](.claude/PROJECT_PLAN.md) | 11-phase implementation checklist with acceptance criteria |
| [.claude/journal/](.claude/journal/) | Progress notes & decision log (`NNN_DESCRIPTION.md`) |
| [README.md](README.md) | Documentation index |

## Architecture (layered)

```
Templates (UI)  →  moloni_on.php (router) / hooks.php
                →  Services (business logic)
                →  Api (MoloniClient / ApiClient)  →  Moloni ON GraphQL API
                →  Models  →  WHMCS DB (tblorders, tblclients, mod_moloni_on_*)
```

- **Services** (`src/Moloni/Services/`): `DocumentService`, `OrderService`, `LogService`, `SettingsService`
- **Api** (`src/Moloni/Api/`): `ApiClient` (HTTP/GraphQL base), `MoloniClient` (domain wrapper)
- **GraphQL** (`src/Moloni/GraphQL/`): one class per query/mutation, GraphQL string as a class constant, `query()` + `variables($data)` methods
- **Models** (`src/Moloni/Models/`): `Order`, `Document`, `Config`, `Log`, extending `AbstractModel`
- **Exceptions** (`src/Moloni/Exceptions/`): `MoloniException` base → `ApiException`, `DocumentException`, `AuthException`, `ValidationException`

## Database tables (custom, `mod_moloni_on_*`)

Created by `src/Moloni/Database/Installer.php` on module activation.

- `mod_moloni_on_config` — key-value settings (api_key, selected_company_id, document_type, …)
- `mod_moloni_on_orders` — order sync tracking (order_id, moloni_document_id, status, …)
- `mod_moloni_on_logs` — application logs (level, message, context, order_id, timestamp)
- `mod_moloni_on_documents` — created documents (order_id, order_total, invoice_id, invoice_date, invoice_status, invoice_total, value)

## UI pages (`templates/`)

`login.php` → `company.php` → dashboard: Orders (`document.php`), Documents (`documents.php`), Settings (`config.php`), Tools (`tools.php`), Logs (`logs.php`). Reusable parts in `Blocks/`, Bootstrap modals in `Modals/`.

## Conventions

- **PHP 7.4+**, **PSR-12**, PSR-4 autoload for `Moloni\` → `src/Moloni/`.
- **i18n**: no hardcoded UI strings — use `lang/en.php` and `lang/pt.php`. Portuguese uses first-person perspective.
- **Logging**: log all errors via `LogService`; no silent failures. Bulk operations continue past individual failures.
- **Security**: API key in Authorization header (never query string); HTTPS only; validate order IDs and document types; rely on WHMCS CSRF tokens.

## Commands (once composer.json exists)

```bash
composer install      # dependencies
composer test         # PHPUnit (tests/Unit, tests/Feature)
composer lint         # PHP CodeSniffer (PSR-12)
composer lint:fix     # auto-fix style
```

## Workflow notes

- Track progress against phases in [.claude/PROJECT_PLAN.md](.claude/PROJECT_PLAN.md).
- Record decisions/progress as new `.claude/journal/NNN_*.md` entries.
- Keep the four planning docs in sync when schema, structure, or flow changes.

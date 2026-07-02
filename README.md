# Moloni ON for WHMCS

A WHMCS addon module that syncs WHMCS orders into [Moloni ON](https://www.molonion.pt/)
(a Portuguese invoicing platform) as invoices/documents, via its GraphQL API.

- Authenticate with Moloni ON (OAuth2) and pick a company
- Turn WHMCS orders into Moloni ON documents — individually, in bulk, or automatically when an invoice is paid
- Taxes, customer and products are derived from the order and resolved/created in Moloni ON
- Download document PDFs, discard/revert orders, and review an activity log

## Requirements

- WHMCS 7.0+
- PHP 7.4+ (with `curl`, `json`, `mbstring`)
- Composer
- A Moloni ON account with API credentials (API Client ID + Client Secret)

## Install

```bash
composer install --no-dev --optimize-autoloader
# copy this directory to your WHMCS install as modules/addons/moloni_on
```

Then activate **Moloni ON** under *Setup → Addon Modules* in the WHMCS admin and open it
from *Addons → Moloni ON*. Full step-by-step (DB tables, OAuth, settings) is in
[SETUP.md](SETUP.md).

## Configuration

Settings live under the module's *Settings* tab: default document type & status, document set,
tax-exemption reason, automatic creation on payment, and product-mapping ids (measurement
unit, category). Document VAT is taken from each order's own tax rate — not a fixed setting.

## Project structure

```
moloni_on.php          WHMCS addon entry (config/activate/deactivate/upgrade/output hooks)
hooks.php              WHMCS hooks (auto-create document on InvoicePaid)
src/Moloni/
  Admin/               Dispatcher (router) + Container (service factory)
  Api/                 ApiClient (OAuth + GraphQL over cURL), MoloniClient (domain wrapper)
  GraphQL/             One class per query/mutation
  Services/            DocumentService, OrderService, AuthService, SettingsService, LogService,
                       CountryResolver, ProductResolver, TaxResolver
  Models/              Config, Auth, Order, Document, Log + Whmcs (native-table reads)
  Support/             Platform, Context, Lang, Template
  Enums/ Exceptions/ Facades/
templates/             UI pages + Blocks/ (shared layout)
lang/                  en.php, pt.php
public/                css/js
tests/                 PHPUnit (Unit, Feature)
```

## Development

No PHP is required locally if you use Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 bash -c '\
  composer install && vendor/bin/phpunit && vendor/bin/phpcs --standard=phpcs.xml'
```

Composer scripts (inside a PHP environment): `composer test`, `composer lint`, `composer lint:fix`.

## Build / release

`composer build` (or `./build.sh`) produces `dist/moloni_on.zip` (module + prod dependencies)
ready to install; `composer build:install` first refreshes prod-only dependencies. Pushing a
`v*` tag runs [.github/workflows/release.yml](.github/workflows/release.yml), which builds that
zip and attaches it to a GitHub Release.

## Documentation

- [SETUP.md](SETUP.md) — installation, OAuth, database, deployment checklist
- [ARCHITECTURE.md](ARCHITECTURE.md) — layered design, data flow, DB schema
- [CLAUDE.md](CLAUDE.md) — repository guide and conventions
- [.claude/PROJECT_PLAN.md](.claude/PROJECT_PLAN.md) — phased implementation checklist
- [.claude/journal/](.claude/journal/) — decisions & progress notes

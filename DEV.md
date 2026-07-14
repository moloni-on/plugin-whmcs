# Developing Moloni ON for WHMCS

Developer, build and release documentation for the Moloni ON WHMCS addon module.

For what the plugin does see [README.md](README.md); for installing and configuring it in a
WHMCS install see [SETUP.md](SETUP.md).

## Project structure

```
moloni_on.php          WHMCS addon entry (config/activate/deactivate/upgrade/output hooks)
hooks.php              WHMCS hooks (auto-create document on InvoicePaid)
src/MoloniOn/
  Admin/               Dispatcher (router) + Container (service factory)
  Api/                 ApiClient (OAuth + GraphQL over cURL), MoloniClient (domain wrapper)
  GraphQL/             One class per query/mutation
  Services/            DocumentService, OrderService, AuthService, SettingsService, LogService,
                       CountryResolver, CustomerResolver, ProductResolver, TaxResolver,
                       LineMapper, PaymentResolver
  Models/              Config, Auth, Order, Document, Log + Whmcs (native-table reads)
  Support/             Platform, Context, Company, Lang, Template, Request, FiscalZone, LineInput
  Enums/ Exceptions/ Facades/
templates/             UI pages + Blocks/ (shared layout)
lang/                  en.php, pt.php
public/                css/js
tests/                 PHPUnit (Unit, Feature)
```

## Development

No PHP is required locally — use the Docker Compose `tools` service:

```bash
docker compose run --rm tools install   # composer install
docker compose run --rm tools test      # PHPUnit
docker compose run --rm tools lint      # PHP CodeSniffer (PSR-12)
docker compose run --rm tools build     # package -> dist/moloni_on.zip
```

Inside a PHP environment the same runs as `composer test` / `lint` / `lint:fix` / `build`.

### Full WHMCS runtime (optional)

WHMCS is proprietary and can't be pulled from a registry, so download your licensed release
into `./whmcs`, then start the `whmcs` profile (php-apache + MariaDB, addon mounted live):

```bash
docker compose --profile whmcs up -d      # WHMCS at http://localhost:8080
```

WHMCS needs the ionCube Loader — see [docker/whmcs.Dockerfile](docker/whmcs.Dockerfile).

## Build / release

`composer build` (or `./build.sh`) produces `dist/moloni_on.zip` (module + prod dependencies)
ready to install. It always runs a fresh `--no-dev` install first, so dev tooling (PHPUnit,
phpcs) can never leak into the shipped zip; pass `./build.sh --skip-install` to reuse a
vendor/ you already know is prod-only. Every push and pull request runs
[.github/workflows/ci.yml](.github/workflows/ci.yml) (phpcs + PHPUnit on PHP 7.4/8.1/8.2);
pushing a `v*` tag runs [.github/workflows/release.yml](.github/workflows/release.yml), which
re-runs those same phpcs + PHPUnit checks before building (a tag on a red commit fails the
release rather than publishing), then builds that zip and attaches it to a GitHub Release.

## Further documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) — layered design, data flow, DB schema
- [SETUP.md](SETUP.md) — installation, OAuth, database, deployment checklist
- [CLAUDE.md](CLAUDE.md) — repository guide and conventions
- [.claude/PROJECT_PLAN.md](.claude/PROJECT_PLAN.md) — phased implementation checklist
- [.claude/journal/](.claude/journal/) — decisions & progress notes

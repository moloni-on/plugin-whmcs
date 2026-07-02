# 2026-07-02 — Docker Compose for dev/test

Added `docker-compose.yml` + `docker/whmcs.Dockerfile`.

- **`tools`** service (default, `composer:2` image) — runs the composer scripts with no local
  PHP: `docker compose run --rm tools <install|test|lint|build>`. Verified: `tools test` → 15
  tests green, `docker compose config` valid.
- **`db` + `whmcs`** services gated behind the `whmcs` compose profile (don't start by default).
  `whmcs` builds a php:8.1-apache image with the extensions WHMCS needs and mounts the addon
  live at `modules/addons/moloni_on`.

## Key constraint
WHMCS is **proprietary — no public image**, and it requires the **ionCube Loader**. So the full
runtime can't work out of the box: the developer must download their licensed WHMCS release into
`./whmcs` (gitignored) and enable ionCube in the Dockerfile (left as a documented opt-in, since
it's arch/PHP-version specific). The `tools` service is the part that "tests the plugin" without
any of that.

# 2026-07-02 — Release-on-tag build, doc cleanup, GraphQL decision

## Build on tag create
Added packaging + CI (equivalent of the PT plugin's local `build.bat`, not a copy):
- **`build.sh`** — portable: prod (`--no-dev`) composer install, assembles a `moloni_on/`
  dir (moloni_on.php, hooks.php, composer.json, LICENSE, src, templates, public, lang, vendor)
  and zips to `dist/moloni_on.zip`. Verified in Docker: zip has `moloni_on/` at root with
  `vendor/autoload.php`.
- **`.github/workflows/release.yml`** — on `push` of a `v*` tag: setup PHP 7.4, install prod
  deps, run `build.sh`, publish a GitHub Release with the zip attached.
- `dist/` and `moloni_on/` added to `.gitignore`.

## GraphQL schema autocomplete — decision: keep queries in PHP
Autocomplete is NOT active because operations embed the query as a PHP string constant.
The reference gets it via separate `.graphql` files + `.graphqlrc.yaml`. Offered the refactor;
**user chose to keep queries in PHP** (simpler, queries are stable; and IDE schema
introspection would likely need auth/an SDL export anyway). Documented the trade-off in SETUP.md.

## Documentation cleanup
- **Removed** `MOLONI_ON_WHMCS_PROMPT.md` (the pre-implementation "Blueprint Phase" prompt,
  superseded by the code + ARCHITECTURE + PROJECT_PLAN). Updated the CLAUDE.md doc map.
- **Rewrote README.md** — was a bloated doc-index with stakeholder/role reading guides,
  version tables and "how to use these documents". Now a concise real README (overview,
  requirements, install, structure, dev, build/release, doc links).
- **ARCHITECTURE.md** — fixed stale code sketches: `ApiClient` now shows the OAuth/GraphQL
  surface (no `apiKey` ctor), `MoloniClient` lists the real methods, removed the non-existent
  "Company Model", noted company selection is via `AuthService`.
- **SETUP.md** — Step 7 now describes the real `AbstractOperation` class pattern (not
  `.graphql` files) and notes autocomplete is intentionally absent; removed the unused
  `.env`/Dotenv step; renumbered following steps; fixed the "API key" checklist item.

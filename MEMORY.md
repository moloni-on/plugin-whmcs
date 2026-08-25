# MEMORY.md

Durable, non-obvious facts about this repository. Self-contained notes — no external/local paths.

- **Moloni ON API auth is OAuth2**, not a static API key: authorization-code flow — API client id + secret → authorize redirect → grant → access/refresh tokens; API base is `/v1`. The original planning docs described an API-key model, which was wrong. See the authentication notes in `AGENTS.md` and `src/MoloniOn/Services/AuthService.php` / `src/MoloniOn/Api/ApiClient.php`.

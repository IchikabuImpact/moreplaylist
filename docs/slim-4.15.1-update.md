# Slim 4.15.1 update memo

## Dependency updates
- Updated `slim/slim` from `^4.8` (locked `4.14.0`) to fixed `4.15.1`.
- `composer.lock` was regenerated via:
  - `php composer.phar update slim/slim --with-all-dependencies`
- No OAuth code/redirect URI/scope changes were made.

## Compatibility checks performed
- Bootstrapping via `public/index.php` (Slim app startup) works.
- Static pages `/privacy.html`, `/about.html`, `/term-of-use.html` returned HTTP 200.
- OAuth entry `/Index/oauth` route was reached; in this environment it returns 500 because `client_secret.json` is not present.
- JSON-RPC `/api/rpc` invalid request case keeps the same JSON-RPC error shape (`invalid_request`).

## Notes
- No framework compatibility code changes were required for this bump in the current codebase.

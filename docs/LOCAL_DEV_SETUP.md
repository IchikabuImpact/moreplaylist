# Local dev environment (WSL2 / Ubuntu)

Mirrors prod (Apache + PHP 8.3, Slim 4) so `git pull && refresh browser` behaves
the same locally as on the Sakura VPS. Already set up once on this machine by
`scripts/setup-local-wsl.sh`; this doc is for re-running it elsewhere (a new
machine, a teammate's WSL) and for the manual GCP Console step that only a
human with console access can do.

## What the script installs

- `apache2` + `php8.3` (+ `curl`, `mbstring`, `xml`, `sqlite3`, `zip` extensions)
- A self-signed TLS cert for `localhost` under `local/certs/` (gitignored)
- An apache vhost (`local/apache/moreplaylist-local.conf`, gitignored — it
  embeds your `GOOGLE_DEVELOPER_KEY`) serving `public/` over HTTPS on its own
  port (default `8443`, override with `MOREPLAYLIST_PORT`) — deliberately
  *not* `:80`/`:443`, so those stay free for other projects on this machine
- Apache configured to run as your own WSL user (not `www-data`) so file
  permissions on `logs/`, `storage/` just work without `chmod` fights
- `composer.phar install` and `npm install`

Why HTTPS locally at all: `GoogleClientFactory::create()` hardcodes
`'https://' . $_SERVER['HTTP_HOST'] . '/Index/oauth'` as the redirect URI
regardless of how the page was actually requested, so the OAuth round-trip
only works if the app is served over HTTPS.

Why `https://localhost:8443` specifically (not a custom `*.local` hostname): WSL2
auto-forwards `localhost` from Windows into the WSL2 network namespace, so
nothing needs to be added to `C:\Windows\System32\drivers\etc\hosts`. A custom
hostname would need that extra step for the Windows-side browser to resolve
it.

## Run it

```bash
GOOGLE_DEVELOPER_KEY="<your YouTube Data API key>" ./scripts/setup-local-wsl.sh
```

Then:

```bash
cp moreplaylist_client_secret_prd.json client_secret.json   # or your own dev-only download
```

`GoogleClientFactory` reads `client_secret.json` from the project root by
default — it's fine to reuse the same prod OAuth client's credentials file
locally (see the GCP Console step below for why this doesn't require a new
credentials download, just an extra redirect URI on the existing client).
Both filenames match the `*client_secret*.json` gitignore rule, so neither
gets committed.

Visit `https://localhost:8443/` and click through the one-time self-signed
certificate warning (Chrome/Edge: "Advanced" → "Proceed to localhost").

The script is idempotent — re-run it any time after `git pull` if
`composer.json`/`package.json` changed.

### Known noisy failure

On some WSL images, `apt-get install` exits non-zero because an unrelated
pre-existing package (`openssh-server`, in one observed case) fails its
post-install systemd hook under WSL's systemd shim — nothing to do with
apache/php. Check with `dpkg -l apache2 php8.3` — if those show `ii`, the
packages we actually need installed fine; run `sudo dpkg --configure -a` to
clear the broken package and re-run the script (it skips already-done steps).

## GCP Console: adding localhost for local dev

**This does not require re-submitting the app for Google's OAuth
verification review.** Verification is tied to the scopes you request and the
consent-screen branding (app name, logo, homepage, privacy policy URL) — it
is not re-triggered by adding redirect URIs or JS origins to an *existing*
OAuth client. You'd only risk a re-review by adding new scopes or changing
consent-screen branding, neither of which this local-dev setup touches.

Go to **GCP Console → APIs & Services → Credentials → ウェブ クライアント 1**,
and add to the *same* client (don't create a new one — `client_secret.json`'s
`client_id`/`client_secret` must stay the ones already verified):

**承認済みの JavaScript 生成元 (Authorized JavaScript origins)** — add:
```
https://localhost:8443
```

**承認済みのリダイレクト URI (Authorized redirect URIs)** — add:
```
https://localhost:8443/Index/oauth
https://localhost:8443/oauth.php
```

Leave the existing `https://moreplaylist.appstarrocks.com` entries as-is —
you're adding to the list, not replacing it.

### Multiple local projects on one machine

`8443` is just this project's default, set via `Listen __HTTPS_PORT__` +
`<VirtualHost *:__HTTPS_PORT__>` in `local/apache/moreplaylist-local.conf.example`.
If another local project (also under this Apache, or a different dev server
entirely) wants `8443`, run `MOREPLAYLIST_PORT=<other port> ./scripts/setup-local-wsl.sh`
to move moreplaylist instead of fighting over the port — then update the two
GCP Console entries above to match the new port (both must always agree with
whatever port Apache is actually listening on, since Google matches the
redirect URI string exactly, port included). `:80`/`:443` are intentionally
left alone by this vhost for that reason.

### The YouTube Data API key (`GOOGLE_DEVELOPER_KEY`)

`VideoController` calls the YouTube Data API server-side with this key (no
OAuth involved for the plain keyword search). If the key has an **Application
restrictions → IP addresses** restriction in the console (Credentials → the
API key, not the OAuth client), calls from your home/WSL IP will fail with
`API_KEY_IP_ADDRESS_BLOCKED` — this happened during initial setup here. Two
options:
- Add your current public IP to the key's allowlist (breaks again if your ISP
  rotates it), or
- Create a **separate, dev-only API key** (Credentials → Create credentials →
  API key) restricted to the YouTube Data API v3 only, with no IP restriction
  or a looser one, and pass that to `setup-local-wsl.sh` instead of the prod
  key. This is the safer default — it keeps the prod key's restrictions
  untouched.

## Env vars set by the local vhost

| Var | Local value | Prod equivalent |
|---|---|---|
| `GOOGLE_DEVELOPER_KEY` | whatever you pass to the setup script | same var, prod key |
| `SHORTURL_DB_PATH` | `<repo>/storage/shorturl.sqlite` | `/var/lib/moreplaylist/shorturl.sqlite` |
| `SHORTURL_BASE_URL` | `https://localhost:8443` | unset (derived from Host header) |
| `APPLICATION_ENV` | `local` | `production` |

`GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` are set in the prod vhost via
`SetEnv` but the app code never reads them (`GoogleClientFactory` only reads
`client_secret.json`) — they're set for reference/other tooling only, so the
local vhost doesn't bother setting them either.

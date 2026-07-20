#!/usr/bin/env bash
#
# Sets up a local dev environment for moreplaylist on WSL2 (Ubuntu), mirroring
# the prod stack (Apache + PHP 8.3) as closely as practical:
#   - apache2 + PHP 8.3 (+ extensions used by google/apiclient etc.)
#   - a self-signed TLS cert for https://localhost:<port> (OAuth requires
#     HTTPS, see GoogleClientFactory::create() which hardcodes the https
#     scheme)
#   - an apache vhost serving public/ on its own port (default 8443, NOT
#     :80/:443 — this machine hosts other local projects too, so moreplaylist
#     stays off the ports everything else defaults to)
#   - composer + npm dependencies installed
#
# Usage:
#   GOOGLE_DEVELOPER_KEY=AIza... ./scripts/setup-local-wsl.sh
#   GOOGLE_DEVELOPER_KEY=AIza... MOREPLAYLIST_PORT=9443 ./scripts/setup-local-wsl.sh   # custom port
#
# GOOGLE_DEVELOPER_KEY is optional but recommended (ideally a separate
# dev-only YouTube Data API key with no IP restriction — see
# docs/LOCAL_DEV_SETUP.md). If omitted, a placeholder is written and video
# search calls will fail until you edit local/apache/moreplaylist-local.conf
# by hand.
#
# Re-running this script is safe (idempotent): it skips steps whose output
# already exists. Re-run after changing MOREPLAYLIST_PORT to move ports.

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_VERSION="8.3"
DEV_KEY="${GOOGLE_DEVELOPER_KEY:-REPLACE_WITH_YOUR_YOUTUBE_DATA_API_KEY}"
HTTPS_PORT="${MOREPLAYLIST_PORT:-8443}"

echo "==> [1/8] apt-get update + installing apache2/php${PHP_VERSION}"
sudo apt-get update -qq
sudo apt-get install -y \
    apache2 \
    "php${PHP_VERSION}" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-common" \
    "libapache2-mod-php${PHP_VERSION}" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-zip" \
    openssl

echo "==> [2/8] enabling apache modules (rewrite, ssl, php${PHP_VERSION})"
sudo a2enmod rewrite ssl "php${PHP_VERSION}" >/dev/null

echo "==> [3/8] running apache as $(whoami) (avoids www-data perms fights on WSL)"
sudo sed -i "s/^export APACHE_RUN_USER=.*/export APACHE_RUN_USER=$(whoami)/" /etc/apache2/envvars
sudo sed -i "s/^export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=$(whoami)/" /etc/apache2/envvars

echo "==> [4/8] generating self-signed TLS cert for localhost (if missing)"
mkdir -p "$PROJECT_DIR/local/certs"
if [ ! -f "$PROJECT_DIR/local/certs/localhost.pem" ]; then
    openssl req -x509 -newkey rsa:2048 -nodes -days 825 \
        -keyout "$PROJECT_DIR/local/certs/localhost-key.pem" \
        -out "$PROJECT_DIR/local/certs/localhost.pem" \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
        -addext "basicConstraints=critical,CA:FALSE" \
        -addext "extendedKeyUsage=serverAuth" \
        -addext "keyUsage=critical,digitalSignature,keyEncipherment" 2>/dev/null
    echo "    generated local/certs/localhost.pem"
else
    echo "    already exists, skipping"
fi

echo "==> [5/8] writing local/apache/moreplaylist-local.conf from template (port ${HTTPS_PORT})"
sed \
    -e "s#__PROJECT_DIR__#${PROJECT_DIR}#g" \
    -e "s#__GOOGLE_DEVELOPER_KEY__#${DEV_KEY}#g" \
    -e "s#__HTTPS_PORT__#${HTTPS_PORT}#g" \
    "$PROJECT_DIR/local/apache/moreplaylist-local.conf.example" \
    > "$PROJECT_DIR/local/apache/moreplaylist-local.conf"

sudo cp "$PROJECT_DIR/local/apache/moreplaylist-local.conf" /etc/apache2/sites-available/moreplaylist-local.conf
sudo a2ensite moreplaylist-local.conf >/dev/null

echo "==> [6/8] creating writable dirs (logs/, storage/)"
mkdir -p "$PROJECT_DIR/logs" "$PROJECT_DIR/storage"

echo "==> [7/8] installing PHP + JS dependencies"
cd "$PROJECT_DIR"
php composer.phar install --no-interaction
if command -v npm >/dev/null 2>&1; then
    npm install
else
    echo "    npm not found, skipping JS test deps (see README JS Test Setup)"
fi

echo "==> [8/8] restarting apache2"
sudo systemctl restart apache2

cat <<EOF

Done.

Next steps:
  1. If you haven't already, copy your OAuth client secret into place:
       cp moreplaylist_client_secret_prd.json client_secret.json
  2. Add https://localhost:${HTTPS_PORT} as an authorized JS origin and
     https://localhost:${HTTPS_PORT}/Index/oauth (+ /oauth.php if used) as
     authorized redirect URIs on the "ウェブ クライアント 1" OAuth client in
     the GCP console (see docs/LOCAL_DEV_SETUP.md).
  3. Open https://localhost:${HTTPS_PORT}/ in your browser. It's self-signed,
     so accept the one-time "not secure" warning.
  4. If GOOGLE_DEVELOPER_KEY wasn't passed to this script, edit
     local/apache/moreplaylist-local.conf and rerun:
       sudo cp local/apache/moreplaylist-local.conf /etc/apache2/sites-available/moreplaylist-local.conf
       sudo systemctl restart apache2

EOF

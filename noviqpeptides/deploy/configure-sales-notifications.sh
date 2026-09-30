#!/usr/bin/env bash
# Push paid-order notification recipient to the production secrets file.
# Never commit the address.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Create deploy/.env from .env.example first."
  exit 1
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

: "${SSH_HOST:?}"
: "${SSH_USER:?}"
: "${REMOTE_WP_PATH:?}"
: "${SALES_NOTIFICATION_EMAIL:?Set SALES_NOTIFICATION_EMAIL in deploy/.env}"

SSH_PORT="${SSH_PORT:-22}"
SECRETS_DIR="${REMOTE_SECRETS_DIR:-/var/www/noviq-secrets}"

SSH_IDENTITY="${SSH_IDENTITY:-}"
SSH_IDENTITY_ARGS=()
if [[ -n "${SSH_IDENTITY}" ]]; then
  SSH_IDENTITY_ARGS=(-i "${SSH_IDENTITY}")
fi

SECRET_FILE="$(mktemp)"
trap 'rm -f "${SECRET_FILE}"' EXIT

SALES_NOTIFICATION_EMAIL="${SALES_NOTIFICATION_EMAIL}" \
python3 <<'PY' > "${SECRET_FILE}"
import os

def php_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"

email = os.environ["SALES_NOTIFICATION_EMAIL"].strip()
if "@" not in email:
    raise SystemExit("SALES_NOTIFICATION_EMAIL does not look like an email address")

print("<?php")
print(f"define( 'NOVIQ_SALES_NOTIFICATION_EMAIL', {php_string(email)} );")
PY

ssh -p "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" \
  "mkdir -p '${SECRETS_DIR}' && chown root:www-data '${SECRETS_DIR}' && chmod 750 '${SECRETS_DIR}'"

scp -P "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" \
  "${SECRET_FILE}" \
  "${SSH_USER}@${SSH_HOST}:${SECRETS_DIR}/sales-notifications.php"

ssh -p "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" \
  "chown root:www-data '${SECRETS_DIR}/sales-notifications.php' && chmod 640 '${SECRETS_DIR}/sales-notifications.php'"

ssh -p "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" \
  "grep -q 'noviq-secrets/sales-notifications.php' '${REMOTE_WP_PATH}/wp-config.php' || sed -i \"/That's all, stop editing!/i if ( file_exists( '${SECRETS_DIR}/sales-notifications.php' ) ) { require '${SECRETS_DIR}/sales-notifications.php'; }\" '${REMOTE_WP_PATH}/wp-config.php'"

echo "Sales notification email configured on host (NOVIQ_SALES_NOTIFICATION_EMAIL)."
echo "Verify WooCommerce → Settings → Emails → New order recipient after the next production seed, or set it in Admin to match."

#!/usr/bin/env bash
# Push Resend API key to the production server secrets file. Never commit the key.
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
: "${RESEND_API_KEY:?Set RESEND_API_KEY in deploy/.env}"

SSH_PORT="${SSH_PORT:-22}"
RESEND_FROM="${RESEND_FROM:-support@noviqpeptides.com}"
RESEND_FROM_NAME="${RESEND_FROM_NAME:-Noviq Peptides}"
SECRETS_DIR="${REMOTE_SECRETS_DIR:-/var/www/noviq-secrets}"

SSH_IDENTITY="${SSH_IDENTITY:-}"
SSH_IDENTITY_ARGS=()
if [[ -n "${SSH_IDENTITY}" ]]; then
  SSH_IDENTITY_ARGS=(-i "${SSH_IDENTITY}")
fi

RSYNC_SSH="ssh -p ${SSH_PORT} ${SSH_IDENTITY_ARGS[*]}"

SECRET_FILE="$(mktemp)"
trap 'rm -f "${SECRET_FILE}"' EXIT

RESEND_API_KEY="${RESEND_API_KEY}" \
RESEND_FROM="${RESEND_FROM}" \
RESEND_FROM_NAME="${RESEND_FROM_NAME}" \
python3 <<'PY' > "${SECRET_FILE}"
import os

def php_string(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"

api = os.environ["RESEND_API_KEY"]
from_addr = os.environ["RESEND_FROM"]
from_name = os.environ["RESEND_FROM_NAME"]

print("<?php")
print(f"define( 'NOVIQ_RESEND_API_KEY', {php_string(api)} );")
print(f"define( 'NOVIQ_RESEND_FROM', {php_string(from_addr)} );")
print(f"define( 'NOVIQ_RESEND_FROM_NAME', {php_string(from_name)} );")
PY

ssh -p "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" \
  "mkdir -p '${SECRETS_DIR}' && chown root:www-data '${SECRETS_DIR}' && chmod 750 '${SECRETS_DIR}'"

scp -P "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" \
  "${SECRET_FILE}" \
  "${SSH_USER}@${SSH_HOST}:${SECRETS_DIR}/resend.php"

ssh -p "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" \
  "chown root:www-data '${SECRETS_DIR}/resend.php' && chmod 640 '${SECRETS_DIR}/resend.php'"

ssh -p "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" \
  "grep -q 'noviq-secrets/resend.php' '${REMOTE_WP_PATH}/wp-config.php' || sed -i \"/That's all, stop editing!/i if ( file_exists( '${SECRETS_DIR}/resend.php' ) ) { require '${SECRETS_DIR}/resend.php'; }\" '${REMOTE_WP_PATH}/wp-config.php'"

echo "Syncing mu-plugins..."
rsync -az -e "$RSYNC_SSH" \
  "${ROOT}/mu-plugins/" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_WP_PATH}/wp-content/mu-plugins/"

ssh -p "${SSH_PORT}" "${SSH_IDENTITY_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" \
  "chown -R www-data:www-data '${REMOTE_WP_PATH}/wp-content/mu-plugins' && chmod -R a+rX '${REMOTE_WP_PATH}/wp-content/mu-plugins'"

echo "Resend configured."
echo "Test: ssh ${SSH_USER}@${SSH_HOST} \"wp --allow-root --path=${REMOTE_WP_PATH} eval \\\"wp_mail(get_option('admin_email'), 'Noviq Resend test', 'Resend SMTP is working.');\\\"\""

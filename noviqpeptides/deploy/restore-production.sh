#!/usr/bin/env bash
# Roll the live store back to a ./backup-production.sh snapshot: database,
# theme, plugin, mu-plugins. Takes a fresh pre-restore backup first.
# The database import replaces everything, including orders, customers and
# settings changed after the snapshot was taken.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

BACKUP_DIR="${1:-}"
if [[ -z "${BACKUP_DIR}" || ! -d "${BACKUP_DIR}" ]]; then
  echo "Usage: ./restore-production.sh backups/<timestamp>"
  exit 1
fi
BACKUP_DIR="$(cd "${BACKUP_DIR}" && pwd)"

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
SSH_PORT="${SSH_PORT:-22}"
WP_ALLOW_ROOT="${WP_ALLOW_ROOT:-0}"

SSH_IDENTITY="${SSH_IDENTITY:-}"
SSH_IDENTITY_ARGS=()
if [[ -n "${SSH_IDENTITY}" ]]; then
  SSH_IDENTITY_ARGS=(-i "${SSH_IDENTITY}")
fi

WP_REMOTE_FLAGS=()
if [[ "${WP_ALLOW_ROOT}" == "1" ]]; then
  WP_REMOTE_FLAGS=(--allow-root)
fi
WP="wp ${WP_REMOTE_FLAGS[*]} --path='${REMOTE_WP_PATH}'"

NOVIQ_SSH_CONTROL="${NOVIQ_SSH_CONTROL:-/tmp/noviq-deploy-${USER}-%r@%h:%p}"
SSH_COMMON=(
  -p "${SSH_PORT}"
  "${SSH_IDENTITY_ARGS[@]}"
  -o ControlMaster=auto
  -o "ControlPath=${NOVIQ_SSH_CONTROL}"
  -o ControlPersist=120
  -o ConnectTimeout=20
)
remote() {
  ssh "${SSH_COMMON[@]}" "${SSH_USER}@${SSH_HOST}" "$@"
}

(cd "${BACKUP_DIR}" && sha256sum -c --quiet SHA256SUMS)
grep -qx "host=${SSH_HOST}" "${BACKUP_DIR}/manifest.txt" || {
  echo "Backup was taken from a different host than SSH_HOST=${SSH_HOST}."
  exit 1
}

cat "${BACKUP_DIR}/manifest.txt"
echo
echo "This replaces the live database and theme/plugin on ${SSH_HOST} with the snapshot above."
echo "Orders, customers and settings changed after the snapshot will be lost."
read -r -p "Type 'restore' to continue: " answer
[[ "${answer}" == "restore" ]] || { echo "Aborted."; exit 1; }

echo "Taking pre-restore backup..."
"${ROOT}/backup-production.sh" "${ROOT}/backups/$(date +%Y%m%d-%H%M%S)-pre-restore"

echo "Restoring theme, plugin, mu-plugins..."
STAGE="/tmp/noviq-restore-$$"
remote "mkdir -p '${STAGE}' && tar -xzf - -C '${STAGE}'" < "${BACKUP_DIR}/wp-content.tar.gz"
remote "set -e
  rsync -a --delete '${STAGE}/themes/noviq-peptides/' '${REMOTE_WP_PATH}/wp-content/themes/noviq-peptides/'
  rsync -a --delete '${STAGE}/plugins/noviq-peptides/' '${REMOTE_WP_PATH}/wp-content/plugins/noviq-peptides/'
  if [[ -d '${STAGE}/mu-plugins' ]]; then rsync -a '${STAGE}/mu-plugins/' '${REMOTE_WP_PATH}/wp-content/mu-plugins/'; fi
  chmod -R a+rX '${REMOTE_WP_PATH}/wp-content/themes/noviq-peptides' '${REMOTE_WP_PATH}/wp-content/plugins/noviq-peptides'
  rm -rf '${STAGE}'"

echo "Importing database..."
gunzip -c "${BACKUP_DIR}/db.sql.gz" | remote "${WP} db import -"

remote "${WP} cache flush && ${WP} rewrite flush --hard && ${WP} wc tool run clear_transients --user=1" >/dev/null || true

echo "Restore complete from ${BACKUP_DIR}"

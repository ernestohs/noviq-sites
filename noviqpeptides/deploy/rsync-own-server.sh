#!/usr/bin/env bash
# Rsync Noviq Peptides theme + plugin to an own server. Does not touch WP core.
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
SSH_PORT="${SSH_PORT:-22}"
WP_CLI="${WP_CLI:-1}"

RSYNC_SSH="ssh -p ${SSH_PORT}"

echo "Syncing theme..."
rsync -az --delete -e "$RSYNC_SSH" \
  ../theme/ \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_WP_PATH}/wp-content/themes/noviq-peptides/"

echo "Syncing plugin..."
rsync -az --delete -e "$RSYNC_SSH" \
  ../plugin/ \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_WP_PATH}/wp-content/plugins/noviq-peptides/"

if [[ "$WP_CLI" == "1" ]]; then
  echo "Activating on remote..."
  ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
    "cd '${REMOTE_WP_PATH}' && wp theme activate noviq-peptides && wp plugin activate noviq-peptides && wp rewrite flush --hard"
fi

echo "Deploy complete."

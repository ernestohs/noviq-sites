#!/usr/bin/env bash
# Rsync Noviq Peptides theme + plugin to an own server. Does not touch WP core.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Create deploy/.env from .env.example first."
  exit 1
fi

DEPLOY_LAYOUT_ARG="${DEPLOY_LAYOUT:-}"
REMOTE_WP_PATH_ARG="${REMOTE_WP_PATH:-}"
REMOTE_COMPOSE_PATH_ARG="${REMOTE_COMPOSE_PATH:-}"

# shellcheck disable=SC1091
set -a
source .env
set +a

: "${DEPLOY_LAYOUT_ARG:=}"
: "${REMOTE_WP_PATH_ARG:=}"
: "${REMOTE_COMPOSE_PATH_ARG:=}"
[[ -n "$DEPLOY_LAYOUT_ARG" ]] && DEPLOY_LAYOUT="$DEPLOY_LAYOUT_ARG"
[[ -n "$REMOTE_WP_PATH_ARG" ]] && REMOTE_WP_PATH="$REMOTE_WP_PATH_ARG"
[[ -n "$REMOTE_COMPOSE_PATH_ARG" ]] && REMOTE_COMPOSE_PATH="$REMOTE_COMPOSE_PATH_ARG"

: "${SSH_HOST:?}"
: "${SSH_USER:?}"
: "${REMOTE_WP_PATH:?}"
SSH_PORT="${SSH_PORT:-22}"
WP_CLI="${WP_CLI:-1}"
DEPLOY_LAYOUT="${DEPLOY_LAYOUT:-wordpress}"
REMOTE_COMPOSE_PATH="${REMOTE_COMPOSE_PATH:-${REMOTE_WP_PATH}/local}"

RSYNC_SSH="ssh -p ${SSH_PORT}"

case "$DEPLOY_LAYOUT" in
  wordpress)
    THEME_DEST="${REMOTE_WP_PATH}/wp-content/themes/noviq-peptides/"
    PLUGIN_DEST="${REMOTE_WP_PATH}/wp-content/plugins/noviq-peptides/"
    ;;
  preview)
    THEME_DEST="${REMOTE_WP_PATH}/theme/"
    PLUGIN_DEST="${REMOTE_WP_PATH}/plugin/"
    ;;
  *)
    echo "DEPLOY_LAYOUT must be wordpress or preview."
    exit 1
    ;;
esac

echo "Syncing theme..."
rsync -az --delete -e "$RSYNC_SSH" \
  ../theme/ \
  "${SSH_USER}@${SSH_HOST}:${THEME_DEST}"

echo "Syncing plugin..."
rsync -az --delete -e "$RSYNC_SSH" \
  ../plugin/ \
  "${SSH_USER}@${SSH_HOST}:${PLUGIN_DEST}"

ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
  "chmod -R a+rX '${THEME_DEST}' '${PLUGIN_DEST}'"

if [[ "$WP_CLI" == "1" ]]; then
  echo "Activating on remote..."
  ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
    "cd '${REMOTE_WP_PATH}' && wp theme activate noviq-peptides && wp plugin activate noviq-peptides && wp rewrite flush --hard"
fi

echo "Deploy complete."

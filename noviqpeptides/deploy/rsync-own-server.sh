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
REVIEW_COAS="${REVIEW_COAS:-0}"
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

if [[ "$REVIEW_COAS" == "1" && "$DEPLOY_LAYOUT" != "preview" ]]; then
  echo "REVIEW_COAS=1 requires DEPLOY_LAYOUT=preview."
  exit 1
fi

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

if [[ "$REVIEW_COAS" == "1" ]]; then
  echo "Syncing local-only COA review samples..."
  rsync -az --delete -e "$RSYNC_SSH" \
    ../local/seed-coa/ \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_COMPOSE_PATH}/seed-coa/"

  echo "Syncing local Compose review mount..."
  rsync -az -e "$RSYNC_SSH" \
    ../local/docker-compose.yml \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_COMPOSE_PATH}/docker-compose.yml"
  rsync -az -e "$RSYNC_SSH" \
    ../deploy/preview/compose.preview.yml \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_WP_PATH}/deploy/preview/compose.preview.yml"

  echo "Importing COA samples on the cloud preview..."
  ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
    "chmod -R a+rX '${REMOTE_COMPOSE_PATH}/seed-coa'"
  ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
    "cd '${REMOTE_COMPOSE_PATH}' && docker compose -f docker-compose.yml -f ../deploy/preview/compose.preview.yml --env-file ../deploy/preview/.env --env-file .env up -d wpcli && docker compose -f docker-compose.yml -f ../deploy/preview/compose.preview.yml --env-file ../deploy/preview/.env --env-file .env exec -T wpcli wp --user=1 plugin activate noviq-peptides && docker compose -f docker-compose.yml -f ../deploy/preview/compose.preview.yml --env-file ../deploy/preview/.env --env-file .env exec -T wpcli wp option update home 'https://noviqpeptides.demo-purposes-only.com' && docker compose -f docker-compose.yml -f ../deploy/preview/compose.preview.yml --env-file ../deploy/preview/.env --env-file .env exec -T wpcli wp option update siteurl 'https://noviqpeptides.demo-purposes-only.com' && docker compose -f docker-compose.yml -f ../deploy/preview/compose.preview.yml --env-file ../deploy/preview/.env --env-file .env exec -T wpcli wp --user=1 noviq review_coas"
fi

if [[ "$WP_CLI" == "1" ]]; then
  echo "Activating on remote..."
  ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
    "cd '${REMOTE_WP_PATH}' && wp theme activate noviq-peptides && wp plugin activate noviq-peptides && wp rewrite flush --hard"
fi

echo "Deploy complete."

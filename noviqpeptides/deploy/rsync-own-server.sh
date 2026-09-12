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
WP_ALLOW_ROOT="${WP_ALLOW_ROOT:-0}"
DEPLOY_LAYOUT="${DEPLOY_LAYOUT:-wordpress}"
REMOTE_COMPOSE_PATH="${REMOTE_COMPOSE_PATH:-${REMOTE_WP_PATH}/local}"

SSH_IDENTITY="${SSH_IDENTITY:-}"
SSH_IDENTITY_ARGS=()
if [[ -n "${SSH_IDENTITY}" ]]; then
  SSH_IDENTITY_ARGS=(-i "${SSH_IDENTITY}")
fi

WP_REMOTE_FLAGS=()
if [[ "${WP_ALLOW_ROOT}" == "1" ]]; then
  WP_REMOTE_FLAGS=(--allow-root)
fi

# Reuse one SSH session — the droplet drops rapid new handshakes (MaxStartups).
NOVIQ_SSH_CONTROL="${NOVIQ_SSH_CONTROL:-/tmp/noviq-deploy-${USER}-%r@%h:%p}"
SSH_COMMON=(
  -p "${SSH_PORT}"
  "${SSH_IDENTITY_ARGS[@]}"
  -o ControlMaster=auto
  -o "ControlPath=${NOVIQ_SSH_CONTROL}"
  -o ControlPersist=120
  -o ConnectTimeout=20
)

ssh_with_retry() {
  local attempt=1
  until ssh "${SSH_COMMON[@]}" "$@"; do
    if (( attempt >= 6 )); then
      return 1
    fi
    echo "SSH failed (attempt ${attempt}/6), retrying in 12s..."
    sleep 12
    attempt=$(( attempt + 1 ))
  done
}

RSYNC_SSH="ssh ${SSH_COMMON[*]}"
ssh_with_retry "${SSH_USER}@${SSH_HOST}" "echo deploy connected" >/dev/null

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

if [[ -d "${ROOT}/mu-plugins" ]]; then
  echo "Syncing mu-plugins..."
  rsync -az -e "$RSYNC_SSH" \
    "${ROOT}/mu-plugins/" \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_WP_PATH}/wp-content/mu-plugins/"
fi

ssh_with_retry "${SSH_USER}@${SSH_HOST}" \
  "chmod -R a+rX '${THEME_DEST}' '${PLUGIN_DEST}'; if [[ -d '${REMOTE_WP_PATH}/wp-content/mu-plugins' ]]; then chown -R www-data:www-data '${REMOTE_WP_PATH}/wp-content/mu-plugins' && chmod -R a+rX '${REMOTE_WP_PATH}/wp-content/mu-plugins'; fi"

if [[ "$WP_CLI" == "1" ]]; then
  echo "Activating on remote..."
  ssh_with_retry "${SSH_USER}@${SSH_HOST}" \
    "cd '${REMOTE_WP_PATH}' && wp ${WP_REMOTE_FLAGS[*]} theme activate noviq-peptides && wp ${WP_REMOTE_FLAGS[*]} plugin activate noviq-peptides && wp ${WP_REMOTE_FLAGS[*]} rewrite flush --hard"
fi

echo "Deploy complete."

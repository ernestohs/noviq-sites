#!/usr/bin/env bash
# Apply products.production.json on the live host: PSP vial prices (ceil to whole
# dollars), PSP variant sizes, 100-unit stock, $55 display (0 decimals).
# Local Docker is unchanged — it keeps products.json dev placeholders.
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

SKIP_STORE=""
if [[ "${1:-}" == "--skip-store" ]]; then
  SKIP_STORE="--skip-store"
fi

echo "Rsync theme + plugin (production catalog lives in the plugin)..."
"${ROOT}/rsync-own-server.sh"

echo "Seeding production catalog on ${SSH_HOST}..."
# rsync-own-server.sh opens a ControlMaster; reuse it if still alive.
NOVIQ_SSH_CONTROL="${NOVIQ_SSH_CONTROL:-/tmp/noviq-deploy-${USER}-%r@%h:%p}"
SSH_COMMON=(
  -p "${SSH_PORT}"
  "${SSH_IDENTITY_ARGS[@]}"
  -o ControlMaster=auto
  -o "ControlPath=${NOVIQ_SSH_CONTROL}"
  -o ControlPersist=120
  -o ConnectTimeout=20
)
attempt=1
until ssh "${SSH_COMMON[@]}" "${SSH_USER}@${SSH_HOST}" \
  "cd '${REMOTE_WP_PATH}' && wp ${WP_REMOTE_FLAGS[*]} noviq seed --production ${SKIP_STORE} --user=1"; do
  if (( attempt >= 6 )); then
    echo "Production seed failed after 6 SSH attempts."
    exit 1
  fi
  echo "SSH seed failed (attempt ${attempt}/6), retrying in 12s..."
  sleep 12
  attempt=$(( attempt + 1 ))
done

echo "Production catalog applied."

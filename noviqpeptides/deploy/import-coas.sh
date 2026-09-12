#!/usr/bin/env bash
# Import Certificates of Analysis on the live host from docs/COAs + coas.json.
# Requires production catalog already seeded (variant SKUs must exist).
# Local Docker is unchanged — empty /coa by design.
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
REMOTE_COA_DIR="${REMOTE_COA_DIR:-/var/www/noviq-coa-pdfs}"
LOCAL_COA_DIR="${ROOT}/../docs/COAs"

SSH_IDENTITY="${SSH_IDENTITY:-}"
SSH_IDENTITY_ARGS=()
if [[ -n "${SSH_IDENTITY}" ]]; then
  SSH_IDENTITY_ARGS=(-i "${SSH_IDENTITY}")
fi

WP_REMOTE_FLAGS=()
if [[ "${WP_ALLOW_ROOT}" == "1" ]]; then
  WP_REMOTE_FLAGS=(--allow-root)
fi

DRY_RUN_FLAG=""
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN_FLAG="--dry-run"
fi

if [[ ! -d "${LOCAL_COA_DIR}" ]]; then
  echo "Missing COA PDF directory: ${LOCAL_COA_DIR}"
  exit 1
fi

echo "Rsync theme + plugin (coas.json + import_coas CLI live in the plugin)..."
"${ROOT}/rsync-own-server.sh"

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

echo "Ensuring remote COA directory ${REMOTE_COA_DIR}..."
ssh_with_retry "${SSH_USER}@${SSH_HOST}" \
  "mkdir -p '${REMOTE_COA_DIR}' && chmod a+rX '${REMOTE_COA_DIR}'"

echo "Rsync COA PDFs to ${SSH_HOST}:${REMOTE_COA_DIR}/..."
RSYNC_SSH="ssh ${SSH_COMMON[*]}"
rsync -avz -e "${RSYNC_SSH}" \
  --include='*.pdf' --exclude='*' \
  "${LOCAL_COA_DIR}/" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_COA_DIR}/"

ssh_with_retry "${SSH_USER}@${SSH_HOST}" \
  "chmod -R a+rX '${REMOTE_COA_DIR}'"

echo "Importing COAs on ${SSH_HOST}${DRY_RUN_FLAG:+ (dry-run)}..."
if ! ssh_with_retry "${SSH_USER}@${SSH_HOST}" \
  "cd '${REMOTE_WP_PATH}' && NOVIQ_COA_DIR='${REMOTE_COA_DIR}' wp ${WP_REMOTE_FLAGS[*]} noviq import_coas ${DRY_RUN_FLAG} --user=1"; then
  echo "COA import failed after SSH retries."
  exit 1
fi

echo "COA import finished."

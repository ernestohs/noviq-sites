#!/usr/bin/env bash
# Snapshot the live store before a catalog deploy: full database, deployed
# theme + plugin + mu-plugins, and a readable catalog listing (SKU, price, stock).
# Read-only on the server. Restore with ./restore-production.sh <backup-dir>.
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
WP="wp ${WP_REMOTE_FLAGS[*]} --path='${REMOTE_WP_PATH}'"

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
remote() {
  ssh "${SSH_COMMON[@]}" "${SSH_USER}@${SSH_HOST}" "$@"
}

BACKUP_DIR="${1:-${ROOT}/backups/$(date +%Y%m%d-%H%M%S)}"
umask 077
mkdir -p "${BACKUP_DIR}"
echo "Backing up ${SSH_HOST} to ${BACKUP_DIR}"

remote "echo connected" >/dev/null

echo "Exporting database..."
remote "${WP} db export - --single-transaction --quiet | gzip -c" > "${BACKUP_DIR}/db.sql.gz"
gzip -t "${BACKUP_DIR}/db.sql.gz"

echo "Archiving theme, plugin, mu-plugins..."
remote "cd '${REMOTE_WP_PATH}/wp-content' && tar -czf - themes/noviq-peptides plugins/noviq-peptides \$( [[ -d mu-plugins ]] && echo mu-plugins )" \
  > "${BACKUP_DIR}/wp-content.tar.gz"
tar -tzf "${BACKUP_DIR}/wp-content.tar.gz" >/dev/null

echo "Writing catalog listing..."
remote "${WP} eval-file -" > "${BACKUP_DIR}/catalog.json" <<'PHP'
<?php
$rows = array();
foreach ( wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'draft', 'private', 'pending' ) ) ) as $product ) {
	$row = array(
		'id'         => $product->get_id(),
		'name'       => $product->get_name(),
		'slug'       => $product->get_slug(),
		'status'     => $product->get_status(),
		'type'       => $product->get_type(),
		'sku'        => $product->get_sku(),
		'price'      => $product->get_regular_price(),
		'sale_price' => $product->get_sale_price(),
		'stock'      => $product->get_stock_quantity(),
		'categories' => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
		'variations' => array(),
	);
	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}
			$row['variations'][] = array(
				'id'         => $child_id,
				'sku'        => $variation->get_sku(),
				'attributes' => $variation->get_attributes(),
				'price'      => $variation->get_regular_price(),
				'sale_price' => $variation->get_sale_price(),
				'stock'      => $variation->get_stock_quantity(),
				'status'     => $variation->get_status(),
			);
		}
	}
	$rows[] = $row;
}
echo wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
PHP

{
  echo "host=${SSH_HOST}"
  echo "path=${REMOTE_WP_PATH}"
  echo "created=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "repo_head=$(git -C "${ROOT}" rev-parse HEAD 2>/dev/null || echo unknown)"
  echo "products=$(python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); print(len(d))' "${BACKUP_DIR}/catalog.json")"
  echo "variations=$(python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); print(sum(len(p["variations"]) for p in d))' "${BACKUP_DIR}/catalog.json")"
} > "${BACKUP_DIR}/manifest.txt"
(cd "${BACKUP_DIR}" && sha256sum db.sql.gz wp-content.tar.gz catalog.json > SHA256SUMS)

cat "${BACKUP_DIR}/manifest.txt"
du -sh "${BACKUP_DIR}"
echo "Backup complete: ${BACKUP_DIR}"

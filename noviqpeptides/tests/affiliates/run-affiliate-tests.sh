#!/usr/bin/env bash
# Syntax-check the plugin and run the affiliate suites inside the wpcli container.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}/local"

chmod -R a+rX "${ROOT}/tests" "${ROOT}/plugin"

docker compose up -d db wordpress wpcli

echo "PHP syntax"
docker compose exec -T wpcli sh -c 'find /var/www/html/wp-content/plugins/noviq-peptides -name "*.php" -print0 | xargs -0 -n1 php -l' >/tmp/noviq-aff-lint.txt
if grep -v '^No syntax errors' /tmp/noviq-aff-lint.txt; then
  echo "php -l reported errors"
  exit 1
fi
echo "php -l ok"

echo "Unit"
docker compose exec -T wpcli php /tests/affiliates/unit.php

run_integration() {
  local label="$1"
  echo "Integration (${label})"
  docker compose exec -T wpcli wp eval 'require "/tests/affiliates/run.php";'
}

run_integration "current order storage"

if docker compose exec -T wpcli wp wc hpos status --user=1 >/tmp/noviq-aff-hpos.txt 2>/tmp/noviq-aff-hpos.err; then
  echo "HPOS status:"
  cat /tmp/noviq-aff-hpos.txt
  hpos_was_on=0
  compat_was_on=0
  if grep -q 'HPOS enabled?: yes' /tmp/noviq-aff-hpos.txt; then
    hpos_was_on=1
  fi
  if grep -q 'Compatibility mode enabled?: yes' /tmp/noviq-aff-hpos.txt; then
    compat_was_on=1
  fi
  restore_hpos() {
    if [[ "${hpos_was_on}" -eq 1 ]]; then
      docker compose exec -T wpcli wp wc hpos enable --user=1
    else
      docker compose exec -T wpcli wp wc hpos disable --user=1
    fi
    if [[ "${compat_was_on}" -eq 0 ]]; then
      docker compose exec -T wpcli wp wc hpos compatibility-mode disable --user=1 || true
    fi
  }
  trap restore_hpos EXIT
  if [[ "${hpos_was_on}" -eq 1 ]]; then
    echo "Repeating integration with HPOS disabled"
    docker compose exec -T wpcli wp wc hpos disable --user=1
    run_integration "HPOS off"
  else
    echo "Repeating integration with HPOS enabled"
    docker compose exec -T wpcli wp wc hpos compatibility-mode enable --user=1
    docker compose exec -T wpcli wp wc hpos sync --user=1
    docker compose exec -T wpcli wp wc hpos enable --user=1
    run_integration "HPOS on"
  fi
  restore_hpos
  trap - EXIT
else
  echo "wp wc hpos is not available; integration ran once on the current storage."
  cat /tmp/noviq-aff-hpos.err || true
fi

echo "Concurrency"
docker compose exec -T wpcli wp eval 'require "/tests/affiliates/concurrency-setup.php";' >/tmp/noviq-aff-order.txt
docker compose exec -T wpcli wp eval 'Noviq\Core\Affiliates\Commissions::on_paid( (int) json_decode( (string) file_get_contents( "/tmp/noviq-aff-concurrency.json" ), true )["order_id"] );' &
pid_a=$!
docker compose exec -T wpcli wp eval 'Noviq\Core\Affiliates\Commissions::on_paid( (int) json_decode( (string) file_get_contents( "/tmp/noviq-aff-concurrency.json" ), true )["order_id"] );' &
pid_b=$!
wait "${pid_a}"
wait "${pid_b}"
docker compose exec -T wpcli wp eval 'require "/tests/affiliates/concurrency-check.php";'

echo "Affiliate tests passed."

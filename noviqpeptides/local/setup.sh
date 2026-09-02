#!/usr/bin/env bash
# Install WordPress + WooCommerce and activate Noviq Peptides theme/plugin.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required. Install Docker, then re-run ./setup.sh"
  exit 1
fi

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example"
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

echo "Starting containers..."
docker compose up -d

echo "Waiting for WordPress..."
for i in $(seq 1 60); do
  if docker compose exec -T wordpress test -f /var/www/html/wp-config.php 2>/dev/null; then
    break
  fi
  sleep 2
done

wp() {
  docker compose exec -T wpcli wp "$@"
}

# Wait until DB is reachable via WP-CLI
for i in $(seq 1 60); do
  if wp core is-installed >/dev/null 2>&1 || wp db check >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

if ! wp core is-installed >/dev/null 2>&1; then
  echo "Installing WordPress..."
  wp core install \
    --url="${WORDPRESS_URL}" \
    --title="${WORDPRESS_TITLE}" \
    --admin_user="${WORDPRESS_ADMIN_USER}" \
    --admin_password="${WORDPRESS_ADMIN_PASSWORD}" \
    --admin_email="${WORDPRESS_ADMIN_EMAIL}" \
    --skip-email
fi

echo "Installing WooCommerce..."
wp plugin install woocommerce --activate --force

# Complete deferred installer pieces Woo leaves for first admin hit
wp option update woocommerce_coming_soon no || true
wp option update woocommerce_show_marketplace_suggestions no || true
wp option update woocommerce_task_list_hidden yes || true
wp option update woocommerce_allow_tracking no || true
wp wc tool run install_pages --user=1 2>/dev/null || true

# Create placeholder image attachment if missing (Woo deferred installer)
wp eval '
if ( ! get_option( "woocommerce_placeholder_image" ) ) {
  echo "placeholder skipped\n";
}
' || true

# Host bind mounts are often 770; www-data (33) must read them.
chmod -R a+rX ../theme ../plugin ./seed-images ./seed-coa 2>/dev/null || true

echo "Activating Noviq Peptides theme and plugin..."
wp theme activate noviq-peptides
wp plugin activate noviq-peptides

echo "Seeding catalog, pages, and product images..."
wp --user=1 noviq seed

echo "Seeding local-only COA samples for processor review..."
wp --user=1 noviq review_coas

# Use front-page.php as the site home
wp option update show_on_front page
FRONT_ID=$(wp post list --post_type=page --name=home --field=ID 2>/dev/null | head -1 || true)
if [[ -z "${FRONT_ID}" ]]; then
  FRONT_ID=$(wp post create --post_type=page --post_title="Home" --post_name="home" --post_status=publish --porcelain)
fi
wp option update page_on_front "${FRONT_ID}"

# Create locked pages if missing
create_page() {
  local slug="$1"
  local title="$2"
  local template="${3:-}"
  if ! wp post list --post_type=page --name="$slug" --field=ID | grep -q '[0-9]'; then
    local id
    id=$(wp post create --post_type=page --post_title="$title" --post_name="$slug" --post_status=publish --porcelain)
    if [[ -n "$template" ]]; then
      wp post meta update "$id" _wp_page_template "$template"
    fi
    echo "Created page /$slug/ ($id)"
  else
    echo "Page /$slug/ exists"
  fi
}

create_page about "About"
create_page why-noviq "Why Noviq"
create_page contact "Contact"
create_page wholesale "Wholesale"
create_page quality-standard "Quality Standard"
create_page research-hub "Research Hub"
create_page coa "Certificates of Analysis"
create_page verify "Verify Lot"
create_page blog "Journal"

# Policy pages under /policies/{slug}/
POLICIES_ID=$(wp post list --post_type=page --name=policies --field=ID 2>/dev/null | head -1 || true)
if [[ -z "${POLICIES_ID}" ]]; then
  POLICIES_ID=$(wp post create --post_type=page --post_title="Policies" --post_name="policies" --post_status=publish --porcelain)
  echo "Created page /policies/ ($POLICIES_ID)"
fi
for pair in "shipping-returns:Shipping and Returns" "terms:Terms" "privacy:Privacy" "cancellation:Cancellation" "accessibility:Accessibility"; do
  slug="${pair%%:*}"
  title="${pair##*:}"
  existing=$(wp post list --post_type=page --name="$slug" --field=ID 2>/dev/null | head -1 || true)
  if [[ -n "$existing" ]]; then
    wp post update "$existing" --post_parent="$POLICIES_ID" >/dev/null
    echo "Linked /$slug/ under /policies/ ($existing)"
  else
    id=$(wp post create --post_type=page --post_title="$title" --post_name="$slug" --post_status=publish --post_parent="$POLICIES_ID" --porcelain)
    echo "Created policy page /policies/$slug/ ($id)"
  fi
done

# US shipping zone structure (amounts from plugin options when set; TBD empty)
wp eval '
if ( ! class_exists( "WC_Shipping_Zones" ) ) { return; }
$zones = WC_Shipping_Zones::get_zones();
$has_us = false;
foreach ( $zones as $z ) {
  if ( isset( $z["zone_name"] ) && "United States" === $z["zone_name"] ) { $has_us = true; break; }
}
if ( ! $has_us ) {
  $zone = new WC_Shipping_Zone();
  $zone->set_zone_name( "United States" );
  $zone->add_location( "US", "country" );
  $zone->save();
  $zone->add_shipping_method( "flat_rate" );
  $zone->add_shipping_method( "free_shipping" );
  echo "Created United States shipping zone\n";
} else {
  echo "United States shipping zone exists\n";
}
' || true

# Permalinks
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

# Enable cheque/manual payments for local test orders
wp option update woocommerce_cheque_settings '{"enabled":"yes","title":"Manual payment (local test)","description":"Local development only.","instructions":"Local test order."}' --format=json 2>/dev/null || true

echo ""
echo "Store is ready."
echo "  Front:  ${WORDPRESS_URL}"
echo "  Admin:  ${WORDPRESS_URL}/wp-admin"
echo "  User:   ${WORDPRESS_ADMIN_USER}"
echo "  Pass:   ${WORDPRESS_ADMIN_PASSWORD}"

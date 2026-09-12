#!/usr/bin/env bash
# Point WordPress at the production domain and enable HTTPS via Certbot.
# Run on the droplet as root after DNS A record points at this host.
set -euo pipefail

WP_ROOT="${WP_ROOT:-/var/www/noviqpeptides}"
DOMAIN="${DOMAIN:-noviqpeptides.com}"
OLD_URL="${OLD_URL:-http://159.89.156.142}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@noviqpeptides.com}"

if [[ $EUID -ne 0 ]]; then
  echo "Run as root on the server."
  exit 1
fi

if ! wp core is-installed --allow-root --path="${WP_ROOT}" >/dev/null 2>&1; then
  echo "WordPress not found at ${WP_ROOT}"
  exit 1
fi

echo "Updating nginx server_name..."
cat > /etc/nginx/sites-available/noviqpeptides <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${WP_ROOT};
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~* /(?:uploads|files)/.*\.php\$ {
        deny all;
    }
}
NGINX

nginx -t
systemctl reload nginx

echo "Setting WordPress URLs to http://${DOMAIN}..."
wp --allow-root option update siteurl "http://${DOMAIN}" --path="${WP_ROOT}"
wp --allow-root option update home "http://${DOMAIN}" --path="${WP_ROOT}"
wp --allow-root search-replace "${OLD_URL}" "http://${DOMAIN}" --path="${WP_ROOT}" --all-tables --skip-columns=guid

if ! command -v certbot >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y -qq certbot python3-certbot-nginx
fi

echo "Requesting TLS certificate..."
certbot --nginx \
  -d "${DOMAIN}" \
  -d "www.${DOMAIN}" \
  --non-interactive \
  --agree-tos \
  -m "${ADMIN_EMAIL}" \
  --redirect

echo "Updating WordPress URLs to https://${DOMAIN}..."
wp --allow-root option update siteurl "https://${DOMAIN}" --path="${WP_ROOT}"
wp --allow-root option update home "https://${DOMAIN}" --path="${WP_ROOT}"
wp --allow-root search-replace "http://${DOMAIN}" "https://${DOMAIN}" --path="${WP_ROOT}" --all-tables --skip-columns=guid
wp --allow-root rewrite flush --hard --path="${WP_ROOT}"

echo ""
echo "Go-live complete."
echo "  https://${DOMAIN}"
echo "  https://www.${DOMAIN}"

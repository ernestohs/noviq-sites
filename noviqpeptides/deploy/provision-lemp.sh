#!/usr/bin/env bash
# One-time LEMP bootstrap for Noviq Peptides on Ubuntu 24.04.
# Run on the droplet as root: bash provision-lemp.sh
set -euo pipefail

WP_ROOT="/var/www/noviqpeptides"
SEED_DIR="/var/www/noviq-seed-images"
WP=(wp --allow-root --path="${WP_ROOT}")
SITE_URL="${SITE_URL:-http://159.89.156.142}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@noviqpeptides.com}"
DB_NAME="${DB_NAME:-noviqpeptides}"
DB_USER="${DB_USER:-wordpress}"

if [[ $EUID -ne 0 ]]; then
  echo "Run as root."
  exit 1
fi

if [[ ! -f /root/.noviq-provision-secrets ]]; then
  DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
  WP_ADMIN_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 18)"
  cat > /root/.noviq-provision-secrets <<EOF
DB_PASS=${DB_PASS}
WP_ADMIN_PASS=${WP_ADMIN_PASS}
EOF
  chmod 600 /root/.noviq-provision-secrets
fi
# shellcheck disable=SC1091
source /root/.noviq-provision-secrets

export DEBIAN_FRONTEND=noninteractive

if ! grep -q '^MaxStartups' /etc/ssh/sshd_config; then
  echo 'MaxStartups 30:60:100' >> /etc/ssh/sshd_config
  systemctl reload ssh
fi

ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

apt-get update -qq
# Ubuntu 24.04 ships PHP 8.3 (meets plugin Requires PHP: 8.2).
apt-get install -y -qq nginx mysql-server \
  php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl php8.3-mbstring \
  php8.3-zip php8.3-gd php8.3-intl unzip curl ca-certificates rsync

mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

mkdir -p "${WP_ROOT}" "${SEED_DIR}"
if [[ ! -f "${WP_ROOT}/wp-config.php" ]]; then
  tmpdir="$(mktemp -d)"
  curl -fsSL https://wordpress.org/latest.tar.gz -o "${tmpdir}/wordpress.tgz"
  tar -xzf "${tmpdir}/wordpress.tgz" -C "${tmpdir}"
  rsync -a "${tmpdir}/wordpress/" "${WP_ROOT}/"
  rm -rf "${tmpdir}"
fi

if [[ ! -x /usr/local/bin/wp ]]; then
  curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp
  chmod +x /usr/local/bin/wp
fi

if [[ ! -f "${WP_ROOT}/wp-config.php" ]]; then
  "${WP[@]}" config create \
    --dbname="${DB_NAME}" \
    --dbuser="${DB_USER}" \
    --dbpass="${DB_PASS}" \
    --dbhost=localhost \
    --skip-check \
    --force
fi

chown -R www-data:www-data "${WP_ROOT}"
chmod -R a+rX "${WP_ROOT}"

cat > /etc/nginx/sites-available/noviqpeptides <<'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/noviqpeptides;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~* /(?:uploads|files)/.*\.php$ {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/noviqpeptides /etc/nginx/sites-enabled/noviqpeptides
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx php8.3-fpm mysql

if ! "${WP[@]}" core is-installed >/dev/null 2>&1; then
  "${WP[@]}" core install \
    --url="${SITE_URL}" \
    --title="Noviq Peptides" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASS}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email
fi

"${WP[@]}" plugin install woocommerce --activate --force >/dev/null
"${WP[@]}" option update woocommerce_coming_soon no || true
"${WP[@]}" option update woocommerce_show_marketplace_suggestions no || true
"${WP[@]}" option update woocommerce_task_list_hidden yes || true
"${WP[@]}" option update woocommerce_allow_tracking no || true
"${WP[@]}" wc tool run install_pages --user=1 2>/dev/null || true
"${WP[@]}" rewrite structure '/%postname%/' --hard
"${WP[@]}" rewrite flush --hard

echo ""
echo "LEMP bootstrap complete."
echo "  URL:   ${SITE_URL}"
echo "  Admin: ${SITE_URL}/wp-admin"
echo "  User:  ${WP_ADMIN_USER}"
echo "  Pass:  ${WP_ADMIN_PASS}  (also in /root/.noviq-provision-secrets)"
echo "  WP:    ${WP_ROOT}"
echo "  Seed:  ${SEED_DIR} (set NOVIQ_SEED_IMAGES=${SEED_DIR} for wp noviq seed)"

# Deploy Noviq Peptides theme + plugin

Git does not push the store live. You copy **theme** and **plugin** onto a host that already has WordPress + WooCommerce + HTTPS.

## Own server (SSH + rsync)

**DNS vs SSH:** GoDaddy (or Cloudflare) **A** records should point at the droplet’s **reserved / floating IP** so you can reattach it after rebuilds. `deploy/.env` **`SSH_HOST`** is the droplet’s **primary IP** used for SSH and rsync. Both addresses hit the same machine once the floating IP is assigned in DigitalOcean.

### First-time LEMP bootstrap (Ubuntu 24.04)

On a fresh droplet as root:

```bash
# From laptop: copy script, then on server:
bash provision-lemp.sh
```

Installs Nginx, MySQL 8, PHP 8.3-FPM, WordPress, WooCommerce, and WP-CLI under `/var/www/noviqpeptides`. Admin password is written to `/root/.noviq-provision-secrets` on the server only.

After bootstrap, rsync theme + plugin (below), then seed if needed:

```bash
NOVIQ_SEED_IMAGES=/var/www/noviq-seed-images wp --allow-root --path=/var/www/noviqpeptides --user=1 noviq seed
```

Upload seed images to `/var/www/noviq-seed-images/` first. When DNS points at the droplet, run go-live on the server:

```bash
bash go-live.sh
```

That script sets nginx `server_name`, updates WordPress URLs, installs Certbot, and enables HTTPS redirect for `noviqpeptides.com` and `www`.

### Transactional email (Resend)

1. In [Resend](https://resend.com/domains), verify **noviqpeptides.com** (SPF + DKIM DNS records at GoDaddy).
2. Create an API key with send access for that domain.
3. Add to `deploy/.env` (never commit):

```bash
RESEND_API_KEY=re_...
RESEND_FROM=support@noviqpeptides.com
RESEND_FROM_NAME=Noviq Peptides
```

4. Push config + mu-plugin:

```bash
cd noviqpeptides/deploy
./configure-resend.sh
```

Uses the [Resend HTTP API](https://resend.com/docs/api-reference/emails/send-email) on port 443 (SMTP ports are blocked on many VPS hosts). From address must match the verified domain. WooCommerce order and PayPal invoice emails go through the same `wp_mail()` path.

Test from the server:

```bash
wp --allow-root --path=/var/www/noviqpeptides eval "wp_mail(get_option('admin_email'), 'Noviq Resend test', 'OK');"
```

### Ongoing deploys

1. Copy `deploy/.env.example` to `deploy/.env` and fill SSH values. Never commit `.env`.
2. Host must already have WordPress, WooCommerce, PHP 8.2+, and HTTPS.
3. From repo root:

```bash
cd noviqpeptides/deploy
cp .env.example .env   # edit values
./rsync-own-server.sh
```

The script syncs:

- `../theme/` → `$REMOTE_WP_PATH/wp-content/themes/noviq-peptides`
- `../plugin/` → `$REMOTE_WP_PATH/wp-content/plugins/noviq-peptides`

Then runs `wp theme activate`, `wp plugin activate`, and `wp rewrite flush` over SSH when `WP_CLI=1`.

## GoDaddy

Confirm the site uses GoDaddy **hosting** (Managed WordPress or cPanel), not only the domain registrar.

### Managed WordPress

1. Zip the theme folder as `noviq-peptides-theme.zip` (contents of `theme/`, zip root should include `style.css`).
2. Zip the plugin folder as `noviq-peptides-plugin.zip` (contents of `plugin/`, zip root should include `noviq-peptides.php`).
3. WP Admin → Appearance → Themes → Add New → Upload.
4. WP Admin → Plugins → Add New → Upload.
5. Activate both. Visit Settings → Permalinks → Save to flush rewrites.

### cPanel / SFTP

1. Upload `theme/` into `wp-content/themes/noviq-peptides`.
2. Upload `plugin/` into `wp-content/plugins/noviq-peptides`.
3. Activate in WP Admin.
4. Flush permalinks.

### Empty host

Install WordPress + WooCommerce first, then upload theme and plugin as above.

## After deploy

1. HTTPS must be on before real card data.
2. Client configures the high-risk payment gateway. Do not put credentials in this repo.
3. Confirm age gate, attestation, RUO notice, and empty `/coa` on production.
4. Do not attach noviqpeptides.com until the store is signed off.

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

### Production catalog (PSP vial prices + stock)

Dev seed (`products.json`) keeps placeholder prices for localhost. Production uses **`products.production.json`** in the plugin:

- Lyophilized **vial peptides only** (no PSP sprays, oral, bundles, or supplies)
- Prices match [PSPeptides](https://pspeptides.com/shop/) vial PDPs, **rounded up** to whole dollars (`$54.99` → `$55`)
- Variant sizes match PSP per product (not the dev 5/10/15 mg grid)
- **100 units** stock per variant
- Store displays **`$55`** (0 decimal places)

Regenerate the production JSON after PSP price changes:

```bash
cd noviqpeptides/deploy
python3 build-production-catalog.py
```

Deploy and apply on the server (rsync + production seed):

```bash
cd noviqpeptides/deploy
./seed-production.sh
```

Catalog-only refresh without touching store pages/settings:

```bash
./seed-production.sh --skip-store
```

Or on the server directly after rsync:

```bash
NOVIQ_SEED_IMAGES=/var/www/noviq-seed-images \
  wp --allow-root --path=/var/www/noviqpeptides --user=1 noviq seed --production
```

**Do not** run plain `wp noviq seed` on production after go-live — it reloads dev placeholder prices from `products.json`.

### Production COA import

Local Docker keeps an empty `/coa` by design. Production lots come from real certificates in `docs/COAs/` plus the manifest `plugin/data/noviq/coas.json` (lot number, purity, release date transcribed from each PDF — never invented).

**Prerequisite:** production catalog already applied (`./seed-production.sh`) so variation SKUs exist.

```bash
cd noviqpeptides/deploy
./import-coas.sh --dry-run   # confirm SKU resolution + row counts
./import-coas.sh             # rsync plugin + PDFs, then wp noviq import_coas
```

The script:

1. Rsyncs theme + plugin (ships `coas.json` and `wp noviq import_coas`)
2. Rsyncs `docs/COAs/*.pdf` to `/var/www/noviq-coa-pdfs/` on the droplet
3. Runs `NOVIQ_COA_DIR=/var/www/noviq-coa-pdfs wp noviq import_coas` over SSH

Idempotent on lot number and PDF filename. Rows marked `"skip": true` in the manifest are logged and not imported (size mismatches and products not in the catalog).

Do **not** run `wp noviq import_coas` against local Docker as part of normal workflow — empty `/coa` on localhost is intentional.

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

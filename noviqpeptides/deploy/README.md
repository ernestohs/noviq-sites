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

Uses the [Resend HTTP API](https://resend.com/docs/api-reference/emails/send-email) on port 443 (SMTP ports are blocked on many VPS hosts). From address must match the verified domain. WooCommerce order emails go through the same `wp_mail()` path.

### Tagada payment webhooks

Order confirmation for the Tagada hosted gateway is server-side. The production
(and staging) host must expose a reachable HTTPS endpoint so Tagada can mark
Woo orders paid. Local Docker (`localhost:8080`) cannot receive those deliveries;
confirm payment status on staging, not on local. Install and webhook steps:
`docs/tagada-gateway-runbook.md`. After deploying the PayPal removal, run once:

```bash
wp --allow-root --path=/var/www/noviqpeptides noviq cancel_payment_reminders --dry-run
wp --allow-root --path=/var/www/noviqpeptides noviq cancel_payment_reminders
```

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

- Same 76 products as the dev seed (original Noviq catalog plus the American Peptides catalog, minus bac water and AP merch)
- **10 mg and larger** only (`catalog_rules.py`). Exceptions kept at their existing sizes: AOD-9604, DSIP, GHRP-2, IGF-1 LR3. Blends count the vial total (`5mg + 5mg` stays). Products only sold under 10 mg are retired (Dihexa capsules, Selank 5 mg spray, the Tirzepatide / AOD-9604 / Tesamorelin set)
- Research sets with a 5 mg vial ship it at 10 mg; the set price adds the higher of the AP and PSP 5-to-10 mg vial price step (BPC-157 +$40, Ipamorelin +$20, Sermorelin +$20)
- Lyophilized vials that PSP also sells: prices match [PSPeptides](https://pspeptides.com/shop/) vial PDPs, **rounded up** to whole dollars (`$54.99` → `$55`), and variant sizes match PSP
- Everything else (Noviq-only vials, AP vials PSP lacks, sprays, strips, research sets): dev variants and prices, rounded up to whole dollars (`$292.50` → `$293`)
- **100 units** stock per variant
- Store displays **`$55`** (0 decimal places)

Regenerate after AP or PSP price changes (AP first, because the production build reads the dev seed):

```bash
cd noviqpeptides/deploy
python3 build-ap-catalog.py
python3 build-production-catalog.py
```

`build-production-catalog.py` rewrites every PSP-matched row from live PSP data, so review the diff for price changes on existing products before deploying. To re-apply catalog changes without picking up PSP price drift, run `python3 build-production-catalog.py --keep-psp-prices` (reuses the PSP sizes and prices already in `products.production.json`, no network).

The seed removes dropped sizes (obsolete variations) but never deletes products. After retiring a product, delete it in WP admin on any store that already has it.

Back up the live store first. The seed hard-deletes dropped variations and rewrites store settings, so rollback needs a full snapshot:

```bash
cd noviqpeptides/deploy
./backup-production.sh
```

Writes `backups/<timestamp>/` (gitignored, owner-only permissions): `db.sql.gz` (full database), `wp-content.tar.gz` (deployed theme, plugin, mu-plugins), `catalog.json` (products and variations with SKU, price, stock for diffing), `manifest.txt`, `SHA256SUMS`. The dump holds customer and order data; keep it off shared drives and out of git.

Roll back to a snapshot:

```bash
./restore-production.sh backups/<timestamp>
```

It verifies checksums and host, asks for confirmation, takes a `-pre-restore` backup, then restores files and imports the database. The import replaces everything, so orders placed after the snapshot are lost; export them first if the store has taken orders since.

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

### Affiliate program

Ship the plugin first. Tables are created on the next request (`noviq_affiliates_db_version`). Then seed the roster. This is separate from catalog seed.

```bash
cd noviqpeptides/deploy
./backup-production.sh
./rsync-own-server.sh
```

On the server, after the plugin is loaded:

```bash
wp --allow-root --path=/var/www/noviqpeptides --user=1 noviq affiliates seed --dry-run
wp --allow-root --path=/var/www/noviqpeptides --user=1 noviq affiliates seed
wp --allow-root --path=/var/www/noviqpeptides --user=1 noviq affiliates attach-user LELE10 person@example.com --recruiter
```

`attach-user` waits on real login emails from the client. Optional history, dry-run first:

```bash
wp --allow-root --path=/var/www/noviqpeptides --user=1 noviq affiliates backfill --since=YYYY-MM-DD --dry-run
wp --allow-root --path=/var/www/noviqpeptides --user=1 noviq affiliates backfill --since=YYYY-MM-DD
```

Confirm a paid order on staging with the Tagada webhook. Local Docker cannot receive that webhook. Full rules: `noviqpeptides/docs/affiliates.md`.

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

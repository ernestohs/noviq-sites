# Deploy Noviq Peptides theme + plugin

Git does not push the store live. You copy **theme** and **plugin** onto a host that already has WordPress + WooCommerce + HTTPS.

## Own server (SSH + rsync)

1. Copy `deploy/.env.example` to `deploy/.env` and fill SSH values. Never commit `.env`.
2. Host must already have WordPress, WooCommerce, PHP, and HTTPS.
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

## Cloud preview COA samples

The processor-review PDFs are local-only by default. To publish them on the
configured cloud preview host, run:

```bash
DEPLOY_LAYOUT=preview \
REMOTE_WP_PATH=/opt/noviq-sites/noviqpeptides \
REVIEW_COAS=1 ./rsync-own-server.sh
```

This opt-in syncs `local/seed-coa/` and the local Compose file, then runs
`wp noviq review_coas` in the cloud `wpcli` container. It creates the
`/coa-review-samples/` page and media attachments only. It does not create lot
records or populate `/coa` and `/verify`. Remove the review page and
attachments before any production launch.

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

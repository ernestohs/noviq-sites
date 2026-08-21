# March Analytics catalog seed

Demo catalog for the Fast Peptide Testing Shopify preview. Source prices come from [peptidetest.com](https://peptidetest.com/) public JSON. Copy is rewritten for March Analytics. Lab supplies are excluded.

## Generate catalog.json

```bash
cd fastpeptidetesting/seed
node extract.mjs
```

Writes `catalog.json` (30 testing products, pages, menus). Re-run when the source catalog changes.

## Import into the preview store

Store default: `srgkrj-ij.myshopify.com` (from `shopify.theme.toml`).

```bash
cd fastpeptidetesting/seed
cp .env.example .env   # once; add SHOPIFY_ADMIN_TOKEN
node import.mjs
```

`import.mjs` upserts by handle:

- 30 non-physical testing products (shipping and inventory tracking off), including Admin `seo.title` / `seo.description`
- collection `order-testing`
- pages: how-it-works, methods, turnaround, contact-us, about, attestation, terms, privacy, refund-policy (with SEO fields)
- menus: `main-menu`, `footer`

Prefer `npx shopify theme push --unpublished --theme fpt-preview` from `fastpeptidetesting/`.

`push-theme.mjs` is a fallback that upserts files via Admin API. It reads `SHOPIFY_STORE` and looks up theme `fpt-preview` by name. Do not hardcode a theme GID.

Required Admin scopes: `write_products`, `write_content`, `write_online_store_pages`, `write_online_store_navigation`, `write_publications`. The importer publishes products to the Online Store sales channel. Without that step the theme shows Dawn apparel placeholders.

## Demo vs launch

Prices and turnaround add-ons are placeholders pending intake C8-C13. Do not copy them to production without client confirmation. Page legal stubs are preview-only (intake D3).

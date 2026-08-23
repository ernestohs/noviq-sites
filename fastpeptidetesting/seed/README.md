# March Analytics catalog seed

Demo catalog for the Fast Peptide Testing Shopify preview. Source prices come from [peptidetest.com](https://peptidetest.com/) public JSON. Copy is rewritten for March Analytics. Lab supplies are excluded.

## Generate catalog.json

```bash
cd fastpeptidetesting/seed
node extract.mjs
```

Writes `catalog.json` (30 testing products, pages, menus, SEO fields). Re-run when the source catalog changes.

## Metaobjects (certificates + compounds)

Creates Online Store metaobject definitions used for indexable COA and compound pages.

```bash
cd fastpeptidetesting/seed
node setup-metaobjects.mjs
```

Requires Admin scopes `write_metaobject_definitions` and `read_metaobject_definitions` (token in `.env`).

To create **entries** (sample compound + certificate), the app also needs `write_metaobjects` and `read_metaobjects`. Without those, run entries by hand in Admin, or add the scopes, Release, re-approve the app, mint a new client-credentials token, then:

```bash
cd fastpeptidetesting/seed
node finish-seo-pages.mjs
```

That script also upserts pages `verify` and `sample-coa` (already created on the preview store).

Public URLs: `/pages/certificates/{handle}`, `/pages/compounds/{handle}`

Vendor embed badge asset: `assets/vendor-coa-badge.svg`

## Audit SEO fields

From the repo root:

```bash
npm run seo-audit:fpt
```

Or:

```bash
cd fastpeptidetesting/seed
node audit-seo.mjs
```

Fails if any product, page, or the collection is missing meta, exceeds title/description length limits, truncates with an ellipsis, embeds demo pricing or intake codes, or contains cross-brand terms.

## Import into the preview store

Store default: `srgkrj-ij.myshopify.com` (from `shopify.theme.toml`).

```bash
cd fastpeptidetesting/seed
cp .env.example .env   # once; add SHOPIFY_ADMIN_TOKEN
node import.mjs
```

`import.mjs` upserts by handle:

- 30 non-physical testing products (shipping and inventory tracking off). Search engine listing uses Admin `seo.title` / `seo.description` on `ProductSetInput`.
- collection `order-testing` with Admin `seo.title` / `seo.description` on `CollectionInput`
- pages: how-it-works, methods, turnaround, contact-us, about, attestation, terms, privacy, refund-policy. Page Search engine listing uses Shopify SEO metafields `global.title_tag` and `global.description_tag` (`PageCreateInput` / `PageUpdateInput` have no `seo` field)
- menus: `main-menu`, `footer`

Prefer `npx shopify theme push --unpublished --theme fpt-preview` from `fastpeptidetesting/`.

`push-theme.mjs` is a fallback that upserts files via Admin API. It reads `SHOPIFY_STORE` and looks up theme `fpt-preview` by name. Do not hardcode a theme GID.

Required Admin scopes: `write_products`, `write_content`, `write_online_store_pages`, `write_online_store_navigation`, `write_publications`. The importer publishes products to the Online Store sales channel. Without that step the theme shows Dawn apparel placeholders.

## Demo vs launch

Prices and turnaround add-ons are placeholders pending intake C8-C13. Do not copy them to production without client confirmation. Legal policies are uploaded in store admin (Mar 2026); do not overwrite them from `catalog.json`. Product body HTML may still mention identity confirmation; Search engine listing meta deliberately does not until the lab confirms that claim.

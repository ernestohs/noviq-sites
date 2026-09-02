# March Analytics catalog seed

Demo catalog for the Fast Peptide Testing Shopify preview. Compound names originate from [peptidetest.com](https://peptidetest.com/) public JSON. Commerce is a single configurable **Peptide Test** product. Copy is rewritten for March Analytics. Lab supplies are excluded.

## Generate / refresh catalog.json

```bash
cd fastpeptidetesting/seed
node extract.mjs                  # optional: refresh from source (legacy multi-product shape)
node apply-configurable-catalog.mjs   # required: Peptide Test model + peptide-option-list.liquid
```

`apply-configurable-catalog.mjs` writes:

- Primary product `peptide-testing` ($250 × 1–5 vials)
- Checkout helpers: rush next/same day; endotoxin, sterility, heavy metals, Karl Fischer, vial vacuum (unit prices per vial)
- Compound products retained for SEO (not in `order-testing` collection membership list)
- `custom-analytical-service` as DRAFT / quote-only
- Page `custom-analytical` and menus pointing at Peptide Test
- `snippets/peptide-option-list.liquid` for the storefront dropdown

## Metaobjects (certificates + compounds)

Creates Online Store metaobject definitions used for indexable COA and compound pages.

```bash
cd fastpeptidetesting/seed
node setup-metaobjects.mjs
```

Requires Admin scopes `write_metaobject_definitions` and `read_metaobject_definitions` (token in `.env`).

To create **entries** (all compounds from `catalog.compounds` linked to Peptide Test, plus sample certificate):

```bash
cd fastpeptidetesting/seed
node finish-seo-pages.mjs
```

Public URLs: `/pages/certificates/{handle}`, `/pages/compounds/{handle}`  
Purchase CTA: `/products/peptide-testing?compound={handle}`

## Audit SEO fields

From the repo root:

```bash
npm run seo-audit:fpt
```

## Import into the preview store

Store default: `srgkrj-ij.myshopify.com` (from `shopify.theme.toml`).

```bash
cd fastpeptidetesting/seed
cp .env.example .env   # once; add SHOPIFY_ADMIN_TOKEN
node apply-configurable-catalog.mjs
node import.mjs
```

`import.mjs` upserts by handle. Collection `order-testing` membership uses `collection.product_handles` (Peptide Test only). Menus may link `PRODUCT` resources.

Prefer `npx shopify theme push --theme fpt-preview` from `fastpeptidetesting/` when asked.

## Manual QA (configurable Peptide Test)

1. `/products/peptide-testing`: change vial count 1–5; peptide selects appear/hide; estimated total updates
2. Select add-ons; totals scale by vial count (e.g. 2 vials + endotoxin = $398 + $150)
3. Select Next-Day / Same-Day; rush fee appears in estimate
4. Add to cart: Peptide Test line has properties for peptides, add-ons, turnaround, fees; helper lines match quantity
5. `/pages/compounds/bpc-157` CTA opens Peptide Test with Vial 1 prefilled
6. `/pages/custom-analytical` quote form has no add-to-cart

## Demo vs launch

Prices above are client-confirmed for preview (Mar 2026 model). Turnaround clock start remains intake C10. Legal policies are uploaded in store admin; do not overwrite them from `catalog.json`.

## Retire placeholder policy pages

If `/pages/terms`, `/pages/privacy`, or `/pages/refund-policy` still exist and duplicate Settings → Policies, remove them and redirect:

```bash
cd fastpeptidetesting/seed
node retire-policy-pages.mjs --cli
```

Footer "Information" should link only to How it works, Methods, Custom analytical, Attestation, and Contact. Policy URLs stay in the theme's `show_policy` bar.

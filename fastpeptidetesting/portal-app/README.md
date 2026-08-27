# March Analytics client portal

Custom Shopify app exposed through an App Proxy at `/apps/portal` on the March Analytics storefront.

## Features

- Order list and detail with 5-stage lab pipeline
- Packing slip (print CSS)
- COA branding profile create/edit (customer metafield JSON)
- AccuVerify-style QR pointing at public certificate pages
- Additional COA reorder deep-link back to Peptide Test
- Webhook handler to set `lab_stage` to `Order Submitted` on paid orders

## Setup (preview store)

1. Create a custom app in the Partner Dashboard (or Dev Dashboard) for the March Analytics development store.
2. Copy `.env.example` to `.env` and fill:
   - `SHOPIFY_SHOP` (e.g. `srgkrj-ij.myshopify.com`)
   - `SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET`
   - `SHOPIFY_ADMIN_TOKEN` (Admin API access token with `read_orders`, `write_orders`, `read_customers`, `write_customers`, `read_metaobjects`, `write_metaobjects`)
3. Run metafield setup:
   ```bash
   cd fastpeptidetesting/seed && node setup-portal-metafields.mjs
   ```
4. Install deps and start:
   ```bash
   cd fastpeptidetesting/portal-app
   npm install
   npm run dev
   ```
5. In the app Admin config, set App Proxy:
   - Subpath prefix: `apps`
   - Subpath: `portal`
   - Proxy URL: `https://YOUR_TUNNEL_HOST/proxy`
6. Theme links to `/apps/portal` (header + account menu). Password-protect the storefront for preview; do not attach production DNS.

## Stage advancement

Default: paid orders start at **Order Submitted**.

Advance stages with:

- Portal admin action `POST /proxy/admin/orders/:id/stage` (authenticated via shared secret), or
- Shopify Flow updating metafield `march_analytics.lab_stage`, or
- Manual Admin metafield edit

Stages: `Order Submitted` → `Sample Received` → `Analyzing` → `Under Review` → `Complete`

## Branding

CSS matches March Analytics tokens (white, `#ECECEC`, `#121212`, muted `#6B6B6B`, green accent on icons only). No Accumark or Noviq branding.

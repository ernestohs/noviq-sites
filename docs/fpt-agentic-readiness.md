# FPT Agentic Readiness — Brand SEO (Phase 3)

Engineering work for agent discovery is in the theme and `fastpeptidetesting/edge/`. Brand-name discoverability for "March Analytics" still needs business and marketing steps that cannot be completed from the repo alone.

## Already done in theme

- Organization + WebSite + Service JSON-LD on the homepage (`snippets/json-ld-organization.liquid`)
- `brand_name` set to March Analytics in theme settings
- Canonical agent files at `/llms.txt` and `/agents.md`
- OpenAPI at `/openapi.json` (redirect → `/pages/openapi`)

## Remaining (product / credentials)

1. Confirm the storefront password stays off so crawlers can index.
2. Google Search Console: verify `fastpeptidetesting.com`, submit `https://fastpeptidetesting.com/sitemap.xml`.
3. Google Business Profile for March Analytics (needs real NAP from intake: legal name, lab address, phone).
4. Consistent Name / Address / Phone across directories and any press mentions that link to the apex domain (not a redirect chain through a temporary myshopify hostname).
5. Optional: Google Analytics 4 and Search Console linkage per [docs/fpt-analytics.md](fpt-analytics.md).

## Cloudflare worker deploy (for OpenAPI Content-Type)

Shopify returns `/pages/openapi` as `text/html` even with `{% layout none %}`. Worker `fpt-agentic-headers` is live on **www** (`www.fastpeptidetesting.com/*`). Apex often does not invoke customer Workers when Shopify and Cloudflare both sit on the hostname; prefer www for OpenAPI Content-Type checks, or follow apex→www redirects when configured.

```bash
cd fastpeptidetesting/edge
npx wrangler login
npx wrangler deploy
```

See `fastpeptidetesting/edge/README.md`.

## Re-verify after deploy

```bash
curl -sI https://www.fastpeptidetesting.com/openapi.json | grep -iE 'content-type|x-fpt|vary'
npm run verify-agentic:fpt
npm run test:agentic-edge
```

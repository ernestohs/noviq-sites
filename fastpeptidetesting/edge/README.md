# FPT Agentic Edge Worker

Cloudflare Worker that fixes Content-Type and `Vary: Accept` for agent-facing paths on fastpeptidetesting.com.

## Why this exists

Shopify already serves `/llms.txt` and `/agents.md` as `text/markdown` with `Vary: Accept`.

Page templates with `{% layout none %}` still return `Content-Type: text/html` for `/pages/openapi`. URL redirects from `/openapi.json` land on that HTML content type. This worker rewrites those responses to `application/json` and sets `Vary: Accept, Accept-Encoding`.

## Live routing

| Hostname | Worker | Notes |
| --- | --- | --- |
| `www.fastpeptidetesting.com` | Runs (`fpt-agentic-headers`) | OpenAPI Content-Type fixed here |
| `fastpeptidetesting.com` (apex) | Usually no Worker | Shopify primary is apex; set **www as primary** in Admin so apex 301s to www |

Worker name: `fpt-agentic-headers`  
OpenAPI: same-host fetch of `/pages/openapi` (do not chase myshopify→primary redirects; that loops).

## Prerequisites

1. `www.fastpeptidetesting.com` DNS proxied through Cloudflare (orange cloud) to `shops.myshopify.com`.
2. Cloudflare account with Workers enabled (free tier is enough).
3. Node.js + `npx wrangler`, or deploy via Cloudflare API / MCP.

## Deploy

```bash
cd fastpeptidetesting/edge
npx wrangler login
npx wrangler deploy
```

Routes (dashboard or API):

- `www.fastpeptidetesting.com/*` → `fpt-agentic-headers`
- `fastpeptidetesting.com/*` → `fpt-agentic-headers` (attach if apex starts receiving Worker traffic)

Optional: Cloudflare Dynamic Redirect from apex `/openapi.json` (and related paths) to the same path on `www`, so agents that follow redirects get JSON.

## Verify

```bash
curl -sI https://www.fastpeptidetesting.com/__fpt-worker-ping | grep -iE 'content-type|x-fpt'
# ok + X-FPT-Worker: fpt-agentic-headers

curl -sI https://www.fastpeptidetesting.com/openapi.json | grep -iE 'content-type|vary|x-fpt'
# Content-Type: application/json
# Vary: Accept, Accept-Encoding
# X-FPT-Worker: fpt-agentic-headers

curl -s https://www.fastpeptidetesting.com/openapi.json | python3 -m json.tool | head -5
```

## Local dry-run

```bash
cd fastpeptidetesting/edge
npx wrangler dev
```

## Tests

```bash
npm run test:agentic-edge
```

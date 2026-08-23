#!/usr/bin/env bash
set -euo pipefail
export PATH="/home/ernestohs/.nvm/versions/node/v25.2.1/bin:/usr/bin:/bin:/usr/local/bin:$PATH"
cd /home/ernestohs/r/noviq-sites/fastpeptidetesting

create_redirect() {
  local path="$1"
  local target="$2"
  local vars
  vars=$(python3 -c "import json; print(json.dumps({'urlRedirect': {'path': '$path', 'target': '$target'}}))")
  echo "===== Redirect $path -> $target ====="
  SHOPIFY_CLI_AGENT_INFO="n:Composer|v:1|p:Cursor" SHOPIFY_CLI_AGENT_IDS="s:fpt-agentic|r:redir|i:1" \
    npx shopify store execute --store srgkrj-ij.myshopify.com --allow-mutations \
    --query 'mutation urlRedirectCreate($urlRedirect: UrlRedirectInput!) { urlRedirectCreate(urlRedirect: $urlRedirect) { urlRedirect { id path target } userErrors { field message } } }' \
    --variables "$vars" --json
}

create_redirect "/openapi.json" "/pages/openapi"
create_redirect "/api/openapi.yaml" "/pages/openapi"
create_redirect "/api/openapi.json" "/pages/openapi"

echo "===== List openapi redirects ====="
SHOPIFY_CLI_AGENT_INFO="n:Composer|v:1|p:Cursor" SHOPIFY_CLI_AGENT_IDS="s:fpt-agentic|r:redir|i:1" \
  npx shopify store execute --store srgkrj-ij.myshopify.com \
  --query 'query { urlRedirects(first: 20, query: "openapi") { nodes { id path target } } }' --json

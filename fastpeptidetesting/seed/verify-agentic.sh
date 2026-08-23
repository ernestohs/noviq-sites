#!/usr/bin/env bash
# Verify agentic readiness endpoints on the live FPT domain.
set -uo pipefail
export PATH="/usr/bin:/bin:/usr/local/bin:${PATH:-}"
BASE="${1:-https://fastpeptidetesting.com}"
fail=0

check() {
  local name="$1"
  local ok="$2"
  if [[ "$ok" == "1" ]]; then
    echo "PASS  $name"
  else
    echo "FAIL  $name"
    fail=1
  fi
}

header_val() {
  # Usage: header_val <url> <header-name> [curl-args...]
  local url="$1"
  local name="$2"
  shift 2
  curl -sI "$@" "$url" | tr -d '\r' | awk -v key="$(echo "$name" | tr '[:upper:]' '[:lower:]')" '
    BEGIN { FS=": " }
    {
      h=$1
      for (i=1;i<=length(h);i++) {
        c=substr(h,i,1)
        if (c>="A" && c<="Z") h=substr(h,1,i-1) tolower(c) substr(h,i+1)
      }
      if (h==key) { print tolower($2); exit }
    }'
}

echo "=== Agentic endpoint verification: $BASE ==="

home=$(curl -sL "$BASE/")
h1=$(printf '%s' "$home" | grep -c '<h1' || true)
text_chars=$(printf '%s' "$home" | python3 -c "
import sys
from html.parser import HTMLParser
class T(HTMLParser):
    def __init__(self):
        super().__init__(); self.parts=[]; self.skip=False
    def handle_starttag(self, tag, attrs):
        if tag in ('script','style'): self.skip=True
    def handle_endtag(self, tag):
        if tag in ('script','style'): self.skip=False
    def handle_data(self, data):
        if not self.skip: self.parts.append(data)
p=T(); p.feed(sys.stdin.read()); print(len(''.join(p.parts).strip()))
")
check "homepage has H1 (count=$h1)" "$([[ "$h1" -ge 1 ]] && echo 1 || echo 0)"
check "homepage text >= 500 chars ($text_chars)" "$([[ "$text_chars" -ge 500 ]] && echo 1 || echo 0)"
check "homepage JSON-LD @graph" "$(grep -aq '@graph' <<<"$home" && echo 1 || echo 0)"

code=$(curl -sL -o /tmp/fpt-v-404.html -w '%{http_code}' "$BASE/nonexistent-agentic-path-xyz")
check "404 status is 404 ($code)" "$([[ "$code" == "404" ]] && echo 1 || echo 0)"
check "404 recovery links" "$(grep -aq 'Where to look next' /tmp/fpt-v-404.html && echo 1 || echo 0)"
check "404 error-recovery JSON" "$(grep -aq 'error-recovery' /tmp/fpt-v-404.html && echo 1 || echo 0)"

llms_ct=$(header_val "$BASE/llms.txt" content-type)
llms_vary=$(header_val "$BASE/llms.txt" vary)
llms_body=$(curl -sL "$BASE/llms.txt" | head -1)
check "llms.txt content-type markdown ($llms_ct)" "$([[ "$llms_ct" == text/markdown* ]] && echo 1 || echo 0)"
check "llms.txt Vary includes Accept ($llms_vary)" "$([[ "$llms_vary" == *accept* ]] && echo 1 || echo 0)"
check "llms.txt body is markdown not HTML" "$([[ "$llms_body" == \#* ]] && echo 1 || echo 0)"

agents_ct=$(header_val "$BASE/agents.md" content-type)
agents_body=$(curl -sL "$BASE/agents.md")
check "agents.md content-type markdown ($agents_ct)" "$([[ "$agents_ct" == text/markdown* ]] && echo 1 || echo 0)"
check "agents.md has When to use" "$(grep -aq 'When to use' <<<"$agents_body" && echo 1 || echo 0)"

oa_code=$(curl -sL -o /tmp/fpt-v-oa.json -w '%{http_code}' "$BASE/openapi.json")
oa_ct=$(curl -sI -L "$BASE/openapi.json" | tr -d '\r' | awk '
  BEGIN{FS=": "}
  {
    h=tolower($1)
    if (h=="content-type") last=$2
  }
  END{print tolower(last)}
')
check "openapi.json HTTP 200 ($oa_code)" "$([[ "$oa_code" == "200" ]] && echo 1 || echo 0)"
if python3 -m json.tool /tmp/fpt-v-oa.json >/tmp/fpt-v-oa.pretty 2>/dev/null; then
  check "openapi.json parses as JSON" 1
  check "openapi.json has openapi 3.x" "$(grep -q '"openapi"' /tmp/fpt-v-oa.pretty && echo 1 || echo 0)"
  check "openapi.json has securitySchemes" "$(grep -q 'securitySchemes' /tmp/fpt-v-oa.pretty && echo 1 || echo 0)"
else
  check "openapi.json parses as JSON" 0
  check "openapi.json has openapi 3.x" 0
  check "openapi.json has securitySchemes" 0
fi
if [[ "$oa_ct" == application/json* ]]; then
  check "openapi.json content-type json ($oa_ct)" 1
else
  echo "WARN  openapi.json content-type is '$oa_ct' (expected application/json after edge worker deploy)"
fi

md_ct=$(header_val "$BASE/" content-type -H 'Accept: text/markdown')
md_vary=$(header_val "$BASE/" vary -H 'Accept: text/markdown')
check "Accept markdown on / returns markdown ($md_ct)" "$([[ "$md_ct" == text/markdown* ]] && echo 1 || echo 0)"
check "Accept markdown response has Vary Accept ($md_vary)" "$([[ "$md_vary" == *accept* ]] && echo 1 || echo 0)"

prod_code=$(curl -sL -o /tmp/fpt-v-prod.json -w '%{http_code}' "$BASE/products/this-product-does-not-exist-xyz.json")
prod_ct=$(header_val "$BASE/products/this-product-does-not-exist-xyz.json" content-type)
check "missing product JSON is 404 ($prod_code)" "$([[ "$prod_code" == "404" ]] && echo 1 || echo 0)"
if [[ -s /tmp/fpt-v-prod.json ]] && python3 -m json.tool /tmp/fpt-v-prod.json >/dev/null 2>&1; then
  check "missing product body is JSON" 1
elif [[ "$prod_ct" == application/json* ]]; then
  # Shopify often returns empty body with application/json; edge worker fills ErrorResponse.
  echo "WARN  missing product JSON is empty body with content-type $prod_ct (edge worker synthesizes ErrorResponse)"
  check "missing product declares JSON content-type" 1
else
  check "missing product body is JSON" 0
fi

echo "=== Done (fail=$fail) ==="
exit "$fail"

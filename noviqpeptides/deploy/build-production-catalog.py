#!/usr/bin/env python3
"""
Build plugin/data/noviq/products.production.json from PSP vial PDPs.

Rules (production only — products.json stays dev placeholders):
  - PSP pricing applies to lyophilized vial PDPs only (never PSP sprays, oral, bundles, supplies)
  - Prices: PSP dollars → price_cents, ceil to whole dollars ($54.99 → $55)
  - Variants: match PSP vial sizes per product, minus sizes under 10 mg (catalog_rules.py)
  - stock_qty: 100 per variant
  - No PSP match (Noviq-only, AP sprays / strips / sets / vials PSP lacks): keep dev
    variants, ceil dev prices to whole dollars

Requires network. Re-run when PSP changes pricing (after build-ap-catalog.py).
--keep-psp-prices reuses the PSP sizes and prices already in products.production.json
(no network), to re-apply catalog changes without picking up PSP price drift.
"""
from __future__ import annotations

import argparse
import copy
import html
import json
import math
import re
import sys
import urllib.request
from pathlib import Path

from catalog_rules import apply_min_mg

REPO = Path(__file__).resolve().parents[1]
DEV_PRODUCTS = REPO / "plugin/data/noviq/products.json"
OUT = REPO / "plugin/data/noviq/products.production.json"
PSP_BASE = "https://pspeptides.com/product/"

# noviq handle → PSP product slug (vial / blend PDPs only)
PSP_SLUGS: dict[str, str] = {
    "aod-9604": "aod-9604-1-vial",
    "bpc-157": "bpc-157-1-vial",
    "cjc-1295": "cjc-1295-no-dac-1-vial",
    "cjc-1295-ipamorelin": "buy-cjc-1295-ipamorelin-blend",
    "dsip": "dsip-1-vial",
    "epithalon": "epitalon-1-vial",
    "ghk-cu": "ghk-cu-1-vial",
    "ghrp-2": "ghrp-2-1-vial",
    "glow": "glow-1-vial",
    "glutathione": "buy-glutathione",
    "igf-1-lr3": "buy-igf-1-lr3",
    "ipamorelin": "ipamorelin-1-vial",
    "kisspeptin-10": "buy-kisspeptin",
    "klow": "klow-1-vial",
    "mot-c": "mots-c-1-vial",
    "oxytocin-acetate": "buy-oxytocin",
    "retatrutide": "retatrutide-1-vial",
    "semaglutide": "buy-semaglutide",
    "semax": "semax-1-vial",
    "sermorelin": "sermorelin-1-vial",
    "snap-8": "buy-snap-8",
    "tb-500": "tb-500-1-vial",
    "tesamorelin": "tesamorelin-1-vial",
    "tirzepatide": "buy-tirzepatide",
    "wolverine": "bpc-157-tb-500-blend",
    "5-amino-1mq": "buy-5-amino-1mq",
    "adamax": "buy-adamax-peptide",
    "ara-290": "buy-ara-290",
    "cagrilintide": "buy-cagrilintide",
    "kpv": "buy-kpv-peptide-1-vial",
    "melanotan-1": "buy-melanotan-1",
    "melanotan-2": "buy-melanotan-ii",
    "nad": "buy-nad",
    "p-21": "buy-p21-peptide",
    "pe-22-28": "buy-pe-22-28",
    "pt-141": "buy-pt-141",
    "selank": "buy-selank",
    "selank-semax": "buy-selank-semax-blend",
    "ss-31": "buy-ss-31-10mg",
    "thymosin-alpha-1": "buy-thymosin-alpha-1",
}

STOCK_QTY = 100


def fetch_variations(slug: str) -> list[dict]:
    url = PSP_BASE + slug + "/"
    req = urllib.request.Request(url, headers={"User-Agent": "noviq-build-production-catalog/1.0"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        body = resp.read().decode("utf-8", errors="replace")
    match = re.search(r'data-product_variations="([^"]+)"', body)
    if not match:
        raise RuntimeError(f"No variation JSON at {url}")
    return json.loads(html.unescape(match.group(1)))


def mg_label(attr_mg: str) -> str:
    s = attr_mg.strip().upper().replace(" ", "")
    if s.endswith("MG"):
        num = s[:-2]
        if re.match(r"^[\d.]+$", num):
            f = float(num)
            return f"{int(f)}mg" if f == int(f) else f"{f}mg"
    return s.lower()


def ceil_cents(dollars: float) -> int:
    return math.ceil(float(dollars) * 100)


def ceil_whole_dollar_cents(cents: int) -> int:
    return math.ceil(cents / 100) * 100


def sku_suffix(label: str) -> str:
    return re.sub(r"[^A-Z0-9.]", "", label.upper())


def psp_variants(slug: str) -> list[dict]:
    raw = fetch_variations(slug)
    out = []
    for v in sorted(
        raw,
        key=lambda x: float(re.sub(r"[^0-9.]", "", x.get("attributes", {}).get("attribute_mg", "0") or "0")),
    ):
        mg = v.get("attributes", {}).get("attribute_mg", "")
        label = mg_label(mg)
        price = v.get("display_price") or v.get("display_regular_price")
        if price is None:
            raise RuntimeError(f"Missing display_price/display_regular_price for {slug} variant {mg!r}")
        amt = re.fullmatch(r"\s*([\d.]+)\s*MG\s*", mg, re.I)
        out.append(
            {
                "label": label,
                "amount_mg": float(amt.group(1)) if amt else None,
                "price_cents": ceil_whole_dollar_cents(ceil_cents(price)),
            }
        )
    return out


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--keep-psp-prices", action="store_true")
    args = parser.parse_args()

    base = json.loads(DEV_PRODUCTS.read_text())
    psp_data: dict[str, list[dict]] = {}
    current = {p["handle"]: p for p in json.loads(OUT.read_text())} if args.keep_psp_prices else {}

    for handle, slug in PSP_SLUGS.items():
        try:
            if args.keep_psp_prices:
                if handle not in current:
                    raise RuntimeError(f"not in {OUT.name}; run without --keep-psp-prices")
                variants = current[handle]["variants"]
            else:
                variants = psp_variants(slug)
            psp_data[handle] = apply_min_mg({"handle": handle, "variants": variants})["variants"]
            print(f"  {handle}: {len(psp_data[handle])} variant(s) from {slug}", file=sys.stderr)
        except Exception as exc:
            print(f"ERROR {handle} ({slug}): {exc}", file=sys.stderr)
            return 1

    out = []
    for product in base:
        handle = product["handle"]
        row = copy.deepcopy(product)
        row["tiers"] = []
        family = product["sku"]

        if handle in psp_data:
            variants = []
            for v in psp_data[handle]:
                variants.append(
                    {
                        "sku": f"{family}-{sku_suffix(v['label'])}",
                        "label": v["label"],
                        "amount_mg": v["amount_mg"],
                        "price_cents": v["price_cents"],
                        "stock_qty": STOCK_QTY,
                    }
                )
            row["variants"] = variants
            if len(variants) > 1:
                row["type"] = "variable"
        else:
            variants = []
            for v in product["variants"]:
                variants.append(
                    {
                        **v,
                        "price_cents": ceil_whole_dollar_cents(v["price_cents"]),
                        "stock_qty": STOCK_QTY,
                    }
                )
            row["variants"] = variants

        out.append(row)

    OUT.write_text(json.dumps(out, indent=2) + "\n")
    print(f"Wrote {len(out)} products, {sum(len(p['variants']) for p in out)} variants → {OUT}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())

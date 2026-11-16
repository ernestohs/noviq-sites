#!/usr/bin/env python3
"""
Merge the American Peptides catalog into plugin/data/noviq/products.json.

Rules (dev seed — products.production.json is derived by build-production-catalog.py):
  - Every americanpeptides.us listing except bacteriostatic water ("Reconstitution
    Solution", catalog guard), American Peptides branded merch, and the listings in RETIRED
  - Variant sizes and prices: schema.org Offer data on each AP product page, exact cents
  - Sizes under 10 mg are dropped across the whole catalog (catalog_rules.py); 5 mg vials
    inside a set become 10 mg at the set price plus SET_UPGRADE_CENTS
  - Titles use generic compound names, never AP marks (AP-RTA-3, AP-TRZ-2, Melts)
  - Copy is ours: identity and format only, no AP marketing or research claims
  - Other products already in products.json keep their data, minus sizes under 10 mg
  - No images: the theme renders generated vial / spray / strip art

Requires network. Re-run when AP changes pricing, then build-production-catalog.py.
"""
from __future__ import annotations

import json
import re
import sys
import urllib.request
from pathlib import Path

from catalog_rules import MIN_MG, apply_min_mg

REPO = Path(__file__).resolve().parents[1]
PRODUCTS = REPO / "plugin/data/noviq/products.json"
AP_BASE = "https://www.americanpeptides.us/products/"

# AP listings already covered by an existing Noviq product (no new row):
# retatrutide, tirzepatide, semaglutide, aod-9604, bpc-157, cjc-1295-no-dac,
# cjc-1295-no-dac-ipamorelin, dsip, copper-binding-peptide-ghk-cu, glow-mix,
# l-glutathione, igf-1-lr3, ipamorelin, kisspeptin-10, klow-blend, mots-c, semax,
# sermorelin, snap-8, tb-500, tesamorelin, bpc-157-tb-500, epi.
# Excluded: reconstitution-solution-30ml, bacteriostatic-water-30ml-bottle,
# american-peptides-*, ap-performance-hat-*.
# Retired (only sold under 10 mg): dihexa (60 x 5 mg capsules), selank-nasal-spray
# (5 mg / 10 mL), bundle-metabolix (AOD-9604 5 mg has no 10 mg size).
RETIRED = {"dihexa", "selank-liquid-spray", "set-tirzepatide-aod-9604-tesamorelin"}

# Price added per 5 mg vial raised to 10 mg inside a set: the higher of the AP and PSP
# 5-to-10 mg vial price steps (BPC-157 PSP $50 -> $90, Ipamorelin AP $40 -> $60,
# Sermorelin AP 5 mg $60 -> PSP 10 mg $80).
SET_UPGRADE_CENTS = {"bpc-157": 4000, "ipamorelin": 2000, "sermorelin": 2000}


def vial(handle, ap, title, kicker, category, areas, compounds, summary, attribute="vial-size", extra=None):
    return {
        "handle": handle,
        "ap": ap,
        "title": title,
        "kicker": kicker,
        "category": category,
        "research_areas": areas,
        "compounds": compounds,
        "summary": summary,
        "attribute": attribute,
        "extra": extra or [],
        "bundle_of": [],
        "surcharge_cents": 0,
    }


def spray(handle, ap, title, areas, compounds, summary):
    return vial(handle, ap, title, "Liquid spray", "sprays", areas, compounds, summary, attribute="size")


def strips(handle, ap, title, areas, compounds, summary):
    return vial(handle, ap, title, "Dissolving strips", "strips", areas, compounds, summary, attribute="size")


def research_set(handle, ap, areas, items):
    surcharge = 0
    resized = []
    for name, amount, h in items:
        if float(amount.split()[0]) < MIN_MG:
            if h not in SET_UPGRADE_CENTS:
                raise RuntimeError(f"{handle}: {name} {amount} has no {MIN_MG} mg upgrade price")
            surcharge += SET_UPGRADE_CENTS[h]
            amount = f"{MIN_MG} mg"
        resized.append((name, amount, h))
    items = resized
    labels = [f"{name} {amount}" for name, amount, _ in items]
    names = [name for name, _, _ in items]
    row = vial(
        handle,
        ap,
        ", ".join(names[:-1]) + " & " + names[-1] + " Set",
        "Research set",
        "bundles",
        areas,
        [],
        f"{len(items)}-vial research set: " + ", ".join(labels[:-1]) + " and " + labels[-1] + ".",
        attribute=None,
    )
    row["bundle_of"] = [{"label": label, "handle": h} for label, (_, _, h) in zip(labels, items)]
    row["surcharge_cents"] = surcharge
    return row


CATALOG = [
    # Single-compound vials
    vial("5-amino-1mq", "5-amino-1mq", "5-Amino-1MQ", "Research compound", "peptides", ["metabolic"], [],
         "Methylquinolinium small molecule studied as a nicotinamide N-methyltransferase (NNMT) inhibitor."),
    vial("adamax", "adamax", "Adamax", "Regulatory peptide", "peptides", ["neuro"], [],
         "Semax-related heptapeptide analogue carrying an adamantane modification."),
    vial("ara-290", "ara-290", "ARA-290", "Research peptide", "peptides", ["tissue"], [],
         "Peptide derived from the helix B surface of erythropoietin, also referred to as cibinetide."),
    vial("cagrilintide", "cagrilintide", "Cagrilintide", "Amylin analogue", "metabolic", ["metabolic"], [],
         "Long-acting acylated amylin analogue used as a reference standard in amylin and calcitonin receptor assays."),
    vial("humanin", "humanin", "Humanin", "Mitochondrial-derived peptide", "peptides", ["longevity"], [],
         "Mitochondrial-derived peptide encoded within the 16S rRNA region, studied in cellular stress models."),
    vial("kpv", "kpv-10-mg-1", "KPV", "Tripeptide", "peptides", ["immune"], ["kpv"],
         "Lys-Pro-Val, the C-terminal tripeptide of alpha-melanocyte-stimulating hormone."),
    vial("melanotan-1", "melanotan-1", "Melanotan-1", "Melanocortin analogue", "peptides", ["cosmetic"], [],
         "Linear alpha-MSH analogue, also known as afamelanotide, used in melanocortin receptor research."),
    vial("melanotan-2", "melanotan-2", "Melanotan-2", "Melanocortin analogue", "peptides", ["cosmetic"], [],
         "Cyclic lactam alpha-MSH analogue used as a non-selective melanocortin receptor reference ligand."),
    vial("nad", "nad", "NAD+", "Coenzyme", "peptides", ["longevity"], [],
         "Nicotinamide adenine dinucleotide, oxidised form, supplied lyophilized for redox and sirtuin pathway assays."),
    vial("p-21", "p-21", "P-21", "Research peptide", "peptides", ["neuro"], [],
         "Ciliary neurotrophic factor-derived peptide (P021) studied in neurotrophic signalling models.",
         extra=[("10mg", 10, 9000)]),  # AP sells 5 mg only; 10 mg at the PSP price
    vial("pe-22-28", "pe-22-28", "PE-22-28", "Research peptide", "peptides", ["neuro"], [],
         "Seven-residue peptide derived from spadin, studied as a TREK-1 channel ligand."),
    vial("pt-141", "pt-141", "PT-141", "Melanocortin analogue", "peptides", ["hormone"], [],
         "Cyclic heptapeptide melanocortin receptor agonist, also known as bremelanotide."),
    vial("selank", "selank", "Selank", "Regulatory peptide", "peptides", ["neuro"], [],
         "Synthetic heptapeptide analogue of tuftsin extended with Pro-Gly-Pro."),
    vial("ss-31", "ss-31", "SS-31", "Mitochondria-targeted peptide", "peptides", ["longevity"], [],
         "Aromatic-cationic tetrapeptide, also known as elamipretide, that localises to the inner mitochondrial membrane."),
    vial("thymosin-alpha-1", "thymosin-alpha-1", "Thymosin Alpha-1", "Research peptide", "peptides", ["immune"], [],
         "28-residue N-acetylated peptide originally isolated from thymic tissue."),
    # Blends
    vial("cjc-1295-ghrp-6", "cjc-1295-no-dac-ghrp-6", "CJC-1295 (No DAC) + GHRP-6", "Blend", "blends", ["growth"], ["cjc-1295"],
         "Co-lyophilized blend of CJC-1295 (No DAC) and GHRP-6, 5 mg each."),
    vial("retatrutide-cagrilintide", "retatrutide-cagrilintide", "Retatrutide + Cagrilintide", "Blend", "blends", ["metabolic"], ["retatrutide"],
         "Co-lyophilized blend of Retatrutide (12.5 mg) and Cagrilintide (2.5 mg)."),
    vial("selank-semax", "selank-semax", "Selank + Semax", "Blend", "blends", ["neuro"], ["semax"],
         "Co-lyophilized blend of Selank and Semax, 5 mg each."),
    vial("tesamorelin-ipamorelin", "tesamorelin-ipamorelin", "Tesamorelin + Ipamorelin", "Blend", "blends", ["growth"], ["tesamorelin", "ipamorelin"],
         "Co-lyophilized blend of Tesamorelin (10 mg) and Ipamorelin (5 mg)."),
    # Bioregulators
    vial("pinealon", "pinealon", "Pinealon", "Bioregulator", "bioregulators", ["bioregulator"], [],
         "Glu-Asp-Arg tripeptide from the Khavinson bioregulator series."),
    vial("prostamax", "prostamax", "Prostamax", "Bioregulator", "bioregulators", ["bioregulator"], [],
         "Short peptide from the Khavinson bioregulator series, studied in prostate tissue models."),
    vial("testagen", "testagen", "Testagen", "Bioregulator", "bioregulators", ["bioregulator"], [],
         "Short peptide from the Khavinson bioregulator series, studied in testicular tissue models."),
    vial("thymalin", "thymalin", "Thymalin", "Bioregulator", "bioregulators", ["bioregulator", "immune"], [],
         "Polypeptide complex from the Khavinson bioregulator series, originally derived from thymic tissue."),
    vial("vesugen", "vesugen", "Vesugen", "Bioregulator", "bioregulators", ["bioregulator"], [],
         "Short peptide from the Khavinson bioregulator series, studied in vascular endothelium models."),
    vial("vilon", "vilon", "Vilon", "Bioregulator", "bioregulators", ["bioregulator"], [],
         "Lys-Glu dipeptide from the Khavinson bioregulator series."),
    # Liquid sprays (every AP spray is a 10 mL metered bottle)
    spray("adamax-liquid-spray", "adamax-liquid-spray", "Adamax Liquid Spray", ["neuro"], [],
          "Adamax pre-dissolved in a 10 mL metered spray bottle, 10 mg per bottle (1 mg/mL)."),
    spray("dsip-blend-liquid-spray", "sleep-research-blend-liquid-spray", "DSIP Blend Liquid Spray", ["neuro"], ["dsip"],
          "DSIP 10 mg, Pinealon 10 mg, melatonin 50 mg and GABA 250 mg pre-dissolved in a 10 mL metered spray bottle."),
    spray("glutathione-liquid-spray", "glutathione-liquid-spray", "Glutathione Liquid Spray", ["longevity"], ["glutathione"],
          "Reduced L-glutathione pre-dissolved in a 10 mL metered spray bottle, 500 mg per bottle (50 mg/mL)."),
    spray("na-semax-amidate-liquid-spray", "semax-nasal-spray", "NA-Semax Amidate Liquid Spray", ["neuro"], [],
          "N-acetyl Semax amide pre-dissolved in a 10 mL metered spray bottle, 10 mg per bottle (1 mg/mL)."),
    spray("nad-liquid-spray", "nad-liquid-spray", "NAD+ Liquid Spray", ["longevity"], [],
          "NAD+ pre-dissolved in a 10 mL metered spray bottle, 500 mg per bottle (50 mg/mL)."),
    spray("oxytocin-liquid-spray", "oxytocin-liquid-spray", "Oxytocin Liquid Spray", ["hormone"], ["oxytocin-acetate"],
          "Oxytocin pre-dissolved in a 10 mL metered spray bottle, 10 mg per bottle (1 mg/mL)."),
    spray("pinealon-liquid-spray", "pinealon-liquid-spray", "Pinealon Liquid Spray", ["bioregulator"], [],
          "Pinealon (Glu-Asp-Arg) pre-dissolved in a 10 mL metered spray bottle, 10 mg per bottle (1 mg/mL)."),
    spray("pt-141-liquid-spray", "pt-141-liquid-spray", "PT-141 Liquid Spray", ["hormone"], [],
          "PT-141 pre-dissolved in a 10 mL metered spray bottle, 10 mg per bottle (1 mg/mL)."),
    spray("selank-semax-liquid-spray", "selank-semax-liquid-spray", "Selank + Semax Liquid Spray", ["neuro"], ["semax"],
          "Selank and Semax pre-dissolved in a 10 mL metered spray bottle, 10 mg combined per bottle."),
    # Dissolving strips
    strips("nad-dissolving-strips", "melts-nad-dissolving-strips", "NAD+ Dissolving Strips", ["longevity"], [],
           "NAD+ in a thin-film matrix that disperses in aqueous media. 30 strips per tin."),
    strips("paraxanthine-blend-dissolving-strips", "melts-brain-fuel-dissolving-strips", "Paraxanthine Blend Dissolving Strips", ["neuro"], [],
           "Paraxanthine, L-theanine, alpha-GPC, huperzine A, vinpocetine and vitamins B3, B9 and B12 in a thin-film matrix. 30 strips per tin."),
    strips("selank-semax-dissolving-strips", "melts-pro-selank-semax-dissolving-strips", "Selank + Semax Dissolving Strips", ["neuro"], ["semax"],
           "Selank and Semax in a thin-film matrix that disperses in aqueous media. 20 strips per box."),
    # Research sets (AP bundles, named by contents)
    research_set("set-retatrutide-ghk-cu-melanotan-1", "bundle-ascend", ["metabolic", "cosmetic"],
                 [("Retatrutide", "10 mg", "retatrutide"), ("GHK-Cu", "50 mg", "ghk-cu"), ("Melanotan-1", "10 mg", "melanotan-1")]),
    research_set("set-glow-ghk-cu-kpv", "bundle-glow", ["tissue", "cosmetic"],
                 [("GLOW", "70 mg", "glow"), ("GHK-Cu", "100 mg", "ghk-cu"), ("KPV", "10 mg", "kpv")]),
    research_set("set-thymosin-alpha-1-thymalin-mots-c", "bundle-immuno", ["immune"],
                 [("Thymosin Alpha-1", "10 mg", "thymosin-alpha-1"), ("Thymalin", "20 mg", "thymalin"), ("MOT-C", "10 mg", "mot-c")]),
    research_set("set-cjc-ipamorelin-tb-500-bpc-157", "bundle-ironclad", ["growth", "tissue"],
                 [("CJC-1295 (No DAC) + Ipamorelin", "10 mg", "cjc-1295-ipamorelin"), ("TB-500", "10 mg", "tb-500"),
                  ("BPC-157", "5 mg", "bpc-157"), ("Ipamorelin", "5 mg", "ipamorelin")]),
    research_set("set-klow-bpc-157-ghk-cu", "bundle-klow-elite", ["tissue"],
                 [("KLOW", "80 mg", "klow"), ("BPC-157", "5 mg", "bpc-157"), ("GHK-Cu", "100 mg", "ghk-cu")]),
    research_set("set-nad-mots-c-ss-31", "bundle-nad-stack", ["longevity"],
                 [("NAD+", "1000 mg", "nad"), ("MOT-C", "10 mg", "mot-c"), ("SS-31", "10 mg", "ss-31")]),
    research_set("set-semax-selank-pinealon", "bundle-neuro", ["neuro"],
                 [("Semax", "10 mg", "semax"), ("Selank", "10 mg", "selank"), ("Pinealon", "20 mg", "pinealon")]),
    research_set("set-thymosin-alpha-1-nad-mots-c", "bundle-prime", ["immune", "longevity"],
                 [("Thymosin Alpha-1", "10 mg", "thymosin-alpha-1"), ("NAD+", "500 mg", "nad"), ("MOT-C", "10 mg", "mot-c")]),
    research_set("set-cjc-ipamorelin-sermorelin-ipamorelin", "bundle-pulse", ["growth"],
                 [("CJC-1295 (No DAC) + Ipamorelin", "10 mg", "cjc-1295-ipamorelin"), ("Sermorelin", "5 mg", "sermorelin"),
                  ("Ipamorelin", "5 mg", "ipamorelin")]),
    research_set("set-wolverine-kpv-ghk-cu", "bundle-repair-plus", ["tissue"],
                 [("Wolverine (BPC-157 + TB-500)", "20 mg", "wolverine"), ("KPV", "10 mg", "kpv"), ("GHK-Cu", "50 mg", "ghk-cu")]),
    research_set("set-bpc-157-tb-500-ipamorelin", "bundle-wolverine", ["tissue", "growth"],
                 [("BPC-157", "5 mg", "bpc-157"), ("TB-500", "10 mg", "tb-500"), ("Ipamorelin", "5 mg", "ipamorelin")]),
]


def fetch_offers(slug: str) -> list[dict]:
    url = AP_BASE + slug
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (noviq-build-ap-catalog/1.0)"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        body = resp.read().decode("utf-8", errors="replace")
    for block in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', body, re.S):
        try:
            data = json.loads(block)
        except json.JSONDecodeError:
            continue
        if isinstance(data, dict) and data.get("@type") == "Product":
            offers = data.get("offers") or []
            return offers if isinstance(offers, list) else [offers]
    raise RuntimeError(f"No Product JSON-LD at {url}")


def num(value: str) -> str:
    f = float(value)
    return str(int(f)) if f == int(f) else str(f)


def normalize_label(raw: str, category: str) -> tuple[str, float | int | None]:
    s = re.sub(r"\s+", " ", raw).strip()
    if m := re.fullmatch(r"([\d.]+) ?mg", s, re.I):
        label = f"{num(m.group(1))}mg"
        amount = float(m.group(1))
        amount = int(amount) if amount == int(amount) else amount
        return (f"{label} / 10 mL", None) if category == "sprays" else (label, amount)
    if m := re.fullmatch(r"([\d.]+) ?(?:mg)? ?/ ?([\d.]+) ?mg", s, re.I):
        return f"{num(m.group(1))}mg + {num(m.group(2))}mg", None
    if m := re.fullmatch(r"(\d+)-Vial Bundle", s, re.I):
        return f"{m.group(1)} vials", None
    if m := re.fullmatch(r"(\d+) Strips", s, re.I):
        return f"{m.group(1)} strips", None
    if m := re.fullmatch(r"([\d.]+) ?mL", s, re.I):
        return f"{num(m.group(1))} mL", None
    raise RuntimeError(f"Unrecognised AP variant label {raw!r}")


def sku_suffix(label: str) -> str:
    return re.sub(r"[^A-Z0-9.]", "", label.upper())


def build_row(entry: dict, rank: int) -> dict:
    family = "DEV-" + entry["handle"].upper()
    variants = []
    sizes = []
    for offer in fetch_offers(entry["ap"]):
        raw = str(offer.get("name", ""))
        label, amount = normalize_label(raw, entry["category"])
        price = offer.get("price")
        if price is None:
            raise RuntimeError(f"Missing price for {entry['ap']} variant {raw!r}")
        sizes.append((label, amount, round(float(price) * 100) + entry["surcharge_cents"]))
    if not sizes:
        raise RuntimeError(f"No offers for {entry['ap']}")
    for label, amount, price_cents in sizes + entry["extra"]:
        variants.append(
            {
                "sku": f"{family}-{sku_suffix(label)}",
                "label": label,
                "amount_mg": amount,
                "price_cents": price_cents,
            }
        )
    variants = apply_min_mg({"handle": entry["handle"], "variants": variants})["variants"]
    return {
        "handle": entry["handle"],
        "sku": family,
        "title": entry["title"],
        "kicker": entry["kicker"],
        "category": entry["category"],
        "research_areas": entry["research_areas"],
        "compounds": entry["compounds"],
        "summary": entry["summary"],
        "type": "variable" if len(variants) > 1 else "simple",
        "attribute": entry["attribute"],
        "variants": variants,
        "tiers": [],
        "addons": [],
        "bundle_of": entry["bundle_of"],
        "featured": False,
        "rank": rank,
    }


def main() -> int:
    products = [p for p in json.loads(PRODUCTS.read_text()) if p["handle"] not in RETIRED]
    index = {p["handle"]: i for i, p in enumerate(products)}
    managed = {e["handle"] for e in CATALOG}
    rank = max((p.get("rank", 0) for p in products if p["handle"] not in managed), default=0)
    for p in products:
        if p["handle"] not in managed:
            apply_min_mg(p)

    for entry in CATALOG:
        rank += 1
        try:
            row = build_row(entry, rank)
        except Exception as exc:
            print(f"ERROR {entry['handle']} ({entry['ap']}): {exc}", file=sys.stderr)
            return 1
        print(f"  {entry['handle']}: {len(row['variants'])} variant(s) from {entry['ap']}", file=sys.stderr)
        if entry["handle"] in index:
            products[index[entry["handle"]]] = row
        else:
            index[entry["handle"]] = len(products)
            products.append(row)

    PRODUCTS.write_text(json.dumps(products, indent=2) + "\n")
    print(f"Wrote {len(products)} products ({len(CATALOG)} from AP) → {PRODUCTS}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())

# 00 Overview

Status: draft. Architecture settled, client details pending.

## The three sites

| Domain | Sells | Platform | Brand | State |
| --- | --- | --- | --- | --- |
| noviqpeptides.com | RUO peptides | WordPress + WooCommerce | Noviq Peptides | Built |
| bacwatermarket.com | Bacteriostatic water, 1-pack and 5-pack | Shopify | TBD, see spec 01 | Not started |
| fastpeptidetesting.com | HPLC peptide testing service | Shopify | March Analytics | Not started |

Two Shopify stores, not three. A single Shopify store cannot serve three
different websites: extra domains attached to a store redirect to its primary
domain and share one catalog, one theme, and one checkout. Shopify Markets only
varies currency, language, and pricing by region, not identity.

## Why the split across platforms

The client put peptides on WordPress deliberately, and it is the correct call.

Shopify's Acceptable Use Policy treats research peptides as prohibited, under
research chemicals and pseudo-pharmaceuticals. Shopify Payments runs on Stripe,
whose restricted list names peptides and research chemicals directly. "Research
use only" labelling does not exempt them. The failure mode is termination after
launch, funds held 90 to 180 days, and possible MATCH-list placement that blocks
future processor approvals for years.

Keeping peptides on WooCommerce moves that risk off Shopify entirely. What
remains on Shopify is bacteriostatic water and a lab service, which is a far
smaller exposure.

## Constraint: keep the peptide site separate

This is the most important rule in the repository and it constrains design, not
just compliance paperwork.

FDA warning letters have cited sellers for offering bacteriostatic water
alongside peptide products, treating the pairing as evidence that the water is
intended for injection with those peptides. Payment processors apply the same
reasoning when they underwrite. March Analytics also loses its entire
commercial value if it does not read as an independent lab.

Therefore:

1. No links from either Shopify store to noviqpeptides.com.
2. No shared logo, wordmark, tagline, colour system, or "our other brands"
   section between the peptide site and the Shopify stores.
3. A different owner email per Shopify store. Linked Shopify accounts can
   cascade a termination from one store to the other.
4. A different support email per domain.
5. Links pointing from noviqpeptides.com to the Shopify sites are lower risk
   than the reverse, but stay out until the client accepts the tradeoff in
   writing.

The three sites should read as three unrelated companies to a visitor, a
processor, and a regulator.

## Payments

| Site | Processor | Notes |
| --- | --- | --- |
| noviqpeptides.com | TBD | High-risk gateway required. Client's responsibility. |
| bacwatermarket.com | TBD | Shopify Payments is not viable long term. See below. |
| fastpeptidetesting.com | Shopify Payments | A lab service, no restricted product. |

Bacteriostatic water is a USP injectable diluent, which lands inside Stripe's
prescription and pseudo-pharmaceutical bucket, and Stripe underwrites Shopify
Payments. Plan a third-party high-risk gateway for bacwatermarket from day one
rather than discovering the problem after the first frozen payout. Shopify adds
a third-party gateway surcharge on top of the gateway's own fees: 2% on Basic,
1% on Grow.

## Costs to the client

| Item | Cost |
| --- | --- |
| Shopify Basic, per store | $39/mo, or $29/mo billed annually |
| Two Shopify stores | $78/mo, or $58/mo billed annually |
| WordPress hosting | Existing |
| Third-party gateway surcharge, bacwatermarket | 2% on Basic, 1% on Grow |

Shopify Basic in 2026 includes no staff accounts, owner login only. If a
separate admin login is needed alongside the client's, that store needs Grow at
$105/mo. Collaborator access from a Partner account does not consume a staff
seat, so this is usually avoidable.

## Repository layout

```
noviq-sites/
├── PROMPT.md
├── specs/
├── bacwatermarket/          self-contained Dawn theme
│   ├── shopify.theme.toml
│   ├── .shopifyignore
│   └── assets/ config/ layout/ locales/ sections/ snippets/ templates/
├── fastpeptidetesting/      self-contained Dawn theme
├── noviqpeptides/           notes and exports only; WP lives on the host
└── reference/               visual specs, never deployed
```

## Theme decisions

Dawn, not Skeleton. `shopify theme init` now clones Skeleton, a minimal theme
with almost no prebuilt sections. Dawn ships with `featured-product`,
`multicolumn`, `collapsible-content`, `rich-text`, and `contact-form` already
working, which covers most of both stores. Scaffold with:

```bash
shopify theme init <site> --clone-url https://github.com/Shopify/dawn
```

Two independent theme copies rather than a shared branch or a sync script. The
only genuinely shared custom code is three files, and duplicating three files
costs less than maintaining merge discipline forever. Revisit if shared custom
code grows past roughly ten files.

Shared custom files, duplicated into both themes:

- `sections/icons-with-content.liquid`, Material Symbols ligature icons
- `sections/section-divider.liquid`
- `snippets/icon-symbol.liquid`

## brand_name

Both themes carry a `brand_name` setting under Theme settings, Brand
information. It replaces `shop.name` in the header wordmark, footer copyright,
page titles, Open Graph tags, and Organization schema, so the storefront reads
correctly regardless of what the store is called in the admin. Blank falls back
to `shop.name`.

Checkout, notification emails, and the admin still use the store name from
Settings, Store details. Rename each store there before launch as well.

## Theme editor drift

The Shopify theme editor writes into the store's remote `config/settings_data.json`.
A later push overwrites those edits. Since the two stores are independent
directories this is a per-store problem rather than a cross-store one, but the
rule still applies: pull before you push if the client has been in the editor.

```bash
shopify theme pull --only config/settings_data.json
```

Review the diff before pushing back.

## Open architecture questions

None. The architecture is settled. Outstanding items are client inputs, tracked
in `specs/10-intake.md`.

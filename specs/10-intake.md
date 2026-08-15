# 10 Intake

Everything still needed from the client or from you. Fill answers in place and
delete the TBD. When a block is fully answered, mirror the values into the
relevant site spec so the specs stay the source of truth.

Ordered by what blocks the most work.

---

## A. Access

Blocks all Shopify work.

| # | Item | Answer |
| --- | --- | --- |
| A1 | Existing store handle, `<name>.myshopify.com` | TBD |
| A2 | Which site that store is, bacwatermarket or fastpeptidetesting | TBD |
| A3 | Collaborator request code, from Settings, Users and permissions, Security | TBD |
| A4 | Confirmation the collaborator account has the Manage themes permission | TBD |
| A5 | WordPress admin login for noviqpeptides.com | TBD |
| A6 | Registrar or DNS access for all three domains, or the contact who has it | Done. Client granted GoDaddy account access with domain management. Confirm all three domains live in that account. |

Once A1 to A4 land, most of block C can be pulled from the store directly
rather than transcribed. See "Self-serve" at the bottom.

## B. Brand assets

Three separate brands. Do not reuse one set across sites.

| # | Item | Answer |
| --- | --- | --- |
| B1 | Logo per site, SVG preferred, PNG at 2x acceptable | TBD |
| B2 | Exact wordmark spelling and capitalisation per site | TBD |
| B3 | Favicon per site, 512x512 PNG minimum | TBD |
| B4 | Brand colours per site, hex | TBD |
| B5 | Confirmation the three should look unrelated, not like siblings | TBD |

Recommendation on B5 is unrelated, for the reasons in `specs/00-overview.md`.

If assets live in Figma, the Figma MCP can pull them once authenticated.

## C. Product data

### bacwatermarket

| # | Item | Answer |
| --- | --- | --- |
| C1 | Vial size and pack contents | 10 mL vials, 1-pack and 5-pack |
| C2 | Price, 1-pack and 5-pack | $10 and $45 |
| C3 | Shipped weight in grams, each variant | TBD |
| C4 | Product photography, 2000px square | AI-generated acceptable, client requested AI renders |
| C5 | Ship-from address, carrier, flat rate or calculated | TBD |
| C6 | States or countries the client will not ship to | TBD |

### fastpeptidetesting

| # | Item | Answer |
| --- | --- | --- |
| C7 | Which tests the lab actually runs | Base test = Purity, Potency, Identity. Add-ons = Heavy metal, Sterility, Endotoxin. Confirm lab runs all. |
| C8 | Price per test | Two offers, see spec 02. Add-ons: heavy metal $100, sterility $75, endotoxin $100. |
| C9 | Turnaround per test, business days | 3 days standard, 1 day +$300, same day +$500 |
| C10 | Whether turnaround starts at payment or at sample receipt | TBD |
| C11 | Lab receiving address for samples | TBD |
| C12 | What the customer ships and any packaging requirements | TBD |
| C13 | How results are delivered | TBD |
| C14 | Sample intake fields to capture at checkout | TBD |

### noviqpeptides

Blocks launch of the peptide site. Any prices, SKUs, or catalog data used during
the build are placeholders until these land.

| # | Item | Answer |
| --- | --- | --- |
| C15 | Confirmed price list, per compound and vial size | TBD |
| C16 | Real SKU scheme | TBD |
| C17 | Whether volume/bulk discounts apply, and at what thresholds | TBD |
| C18 | Whether availability is wired to real inventory before launch | TBD |
| C19 | COA issuer and format, and expected first release lot and date | TBD |
| C20 | Whether SDS documents are supplied alongside COAs | TBD |
| C21 | Independent lab we may name once contracted | TBD |
| C22 | Full catalog: category list, compounds, and which content migrates at launch | TBD |
| C23 | Shipping carriers, rates, flat vs live, and any same-day dispatch cutoff | TBD |
| C24 | International shipping, and to which countries (compliance question) | TBD |
| C25 | Cold-chain requirements for any SKU | TBD |

## D. Legal and operational

| # | Item | Answer |
| --- | --- | --- |
| D1 | Legal entity name and business address per site | TBD |
| D2 | Support email per domain, three different addresses | TBD |
| D3 | Who writes terms, privacy, refund, and shipping policies | TBD |
| D4 | RUO disclaimer text, verbatim | TBD |
| D5 | Where order notification emails go, per store | Noviq: orders@noviqpeptides.com, not yet set up. Shopify stores need different addresses, see D2/D7. |
| D6 | High-risk gateway for bacwatermarket, if selected | TBD |
| D7 | Owner email per Shopify store, must differ | TBD |
| D8 | Tax nexus states, and manual rates vs a tax service | TBD |
| D9 | High-risk processor for noviqpeptides, and whether the category is disclosed in writing | TBD |

## E. Decisions the client must make

| # | Question | Answer |
| --- | --- | --- |
| E1 | Accepts two Shopify subscriptions, $78/mo | TBD |
| E2 | Accepts that Shopify Payments is not viable for bacwatermarket | TBD |
| E3 | Accepts the no-cross-linking constraint | TBD |
| E4 | Scope for noviqpeptides: alignment only or rebuild | TBD, recommend build, see spec 03 |
| E5 | Wants Shopify stores transferred to his ownership at launch | TBD |
| E6 | Sell bacteriostatic water or injection consumables on the peptide site, or keep them off given the separate water store | TBD, recommend off, see spec 03 |
| E7 | Subscribe and save: licence WooCommerce Subscriptions, drop the feature, or defer | TBD |
| E8 | Post-launch reviews programme, covering material quality only, never personal outcomes | TBD |
| E9 | Analytics: GA4, a privacy-preserving alternative, or none (ad-platform restrictions apply to remarketing tags) | TBD |
| E10 | WordPress admin accounts: who needs access and at what role | TBD |
| E11 | If a commercial parent theme licence is already owned, which one (affects template strategy, see spec 03) | TBD |

---

## Self-serve

Once collaborator access exists, most of block C for an existing store can be
read directly instead of asked for:

```bash
shopify store auth --store <store>.myshopify.com --scopes read_products
shopify store execute --store <store>.myshopify.com --query '...'
```

Covers products, variants, prices, weights, images, shipping profiles, and
locations. Saves roughly an hour of transcription and removes copy errors.

Does not cover anything for a store that does not exist yet, and never covers
brand assets, legal text, WordPress, DNS, or payment configuration.

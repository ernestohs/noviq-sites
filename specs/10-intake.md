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
| A6 | Registrar or DNS access for all three domains, or the contact who has it | TBD |

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
| C1 | Vial size and pack contents | TBD |
| C2 | Price, 1-pack and 5-pack | TBD |
| C3 | Shipped weight in grams, each variant | TBD |
| C4 | Product photography, 2000px square | Original 10 mL vial graphic (`Bacwater main.png`). Theme fallback is `bacwatermarket/assets/bacwater-main.png`. Rebrand off Noviq Bio is still open. |
| C5 | Ship-from address, carrier, flat rate or calculated | TBD |
| C6 | States or countries the client will not ship to | TBD |

### fastpeptidetesting

| # | Item | Answer |
| --- | --- | --- |
| C7 | Which tests the lab actually runs | TBD |
| C8 | Price per test | TBD |
| C9 | Turnaround per test, business days | TBD |
| C10 | Whether turnaround starts at payment or at sample receipt | TBD |
| C11 | Lab receiving address for samples | TBD |
| C12 | What the customer ships and any packaging requirements | TBD |
| C13 | How results are delivered | TBD: emailed PDF and/or private portal. Never public. |
| C14 | Sample intake fields to capture at checkout | Compound name, batch or lot number, quantity supplied, customer return address. No public COA consent field. |

## D. Legal and operational

**Status (Mar 2026):** Approved terms, privacy, refund, and shipping policies
are uploaded to all three storefronts (Shopify **Settings → Policies** plus
matching Online Store pages for bac and FPT; Noviq policy pages on the host).
Repo seed stubs are obsolete for legal copy. Transcribe D1 and D2 from the live
policies into this table when auditing intake.

| # | Item | Answer |
| --- | --- | --- |
| D1 | Legal entity name and business address per site | TBD (in uploaded policies) |
| D2 | Support email per domain, three different addresses | TBD (in uploaded policies) |
| D3 | Who writes terms, privacy, refund, and shipping policies | Client counsel; approved and uploaded Mar 2026 |
| D4 | RUO disclaimer text, verbatim | TBD |
| D5 | Where order notification emails go, per store | TBD |
| D6 | High-risk gateway for bacwatermarket, if selected | TBD |
| D7 | Owner email per Shopify store, must differ | TBD |

## E. Decisions the client must make

| # | Question | Answer |
| --- | --- | --- |
| E1 | Accepts two Shopify subscriptions, $78/mo | TBD |
| E2 | Accepts that Shopify Payments is not viable for bacwatermarket | TBD |
| E3 | Accepts the no-cross-linking constraint | TBD |
| E4 | Scope for noviqpeptides: alignment only or rebuild | TBD |
| E5 | Wants Shopify stores transferred to his ownership at launch | TBD |

## F. Analytics and measurement (fastpeptidetesting)

Separate from noviqpeptides and bacwatermarket. Execute
[docs/fpt-analytics.md](../docs/fpt-analytics.md) once these are filled.

| # | Item | Answer |
| --- | --- | --- |
| F1 | Google account for March Analytics / fastpeptidetesting only | TBD |
| F2 | GA4 measurement ID (`G-…`) | TBD |
| F3 | GTM container ID (`GTM-…`), if used | TBD |
| F4 | Microsoft Clarity project ID | TBD |
| F5 | Google Ads tag / conversion IDs, if ads pursued | TBD |
| F6 | Meta Pixel ID, if ads pursued | TBD |

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

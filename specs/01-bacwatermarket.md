# 01 bacwatermarket.com

Status: draft. Blocked on client product data and brand assets.

Platform: Shopify, Dawn theme in `bacwatermarket/`.
Store handle: TBD
Brand name: TBD, the working assumption is "Bac Water Market"

## What it sells

Bacteriostatic water in two pack sizes, a 1-pack and a 5-pack. That is the
entire catalog. Build accordingly: this is a single-product store with a
variant, not a catalog store with two items in it.

## Product model

One product with a pack-size variant is preferred over two separate products.
It keeps a single product page, a single review surface, and a single URL
earning search authority, and it lets the 5-pack be presented as the better
value on the same page.

| Field | Value |
| --- | --- |
| Title | TBD |
| Vial size | TBD, for example 30ml |
| Variant 1 | 1-pack, price TBD, shipped weight TBD grams |
| Variant 2 | 5-pack, price TBD, shipped weight TBD grams |
| SKUs | TBD |
| Physical product | Yes |
| Inventory tracking | TBD, on unless the client says otherwise |

## Homepage

No collection page. Two variants of one product do not justify a catalog grid,
and an extra click before the buy button costs conversions.

Build the homepage on Dawn's `featured-product` section so the buy button sits
above the fold. Section order:

1. `featured-product`, the product itself, with variant picker visible
2. `icons-with-content`, three or four trust points, for example sterility,
   USP grade, batch testing, shipping speed
3. `image-with-text` or `multicolumn`, what the product is, factually
4. `collapsible-content`, FAQ
5. `section-divider` between blocks as needed

Remove catalog and search links from the header menu. They lead nowhere useful.

## Pages

Create with these exact handles:

| Handle | Purpose |
| --- | --- |
| `about` | Who the company is. No mention of peptides or the other sites. |
| `shipping` | Ship-from, carriers, transit times, restricted destinations |
| `contact-us` | Dawn `contact-form` section |
| `terms` | TBD, client supplies |
| `privacy` | TBD, client supplies |
| `refund-policy` | TBD, client supplies |

## Shipping

| Field | Value |
| --- | --- |
| Ship-from address | TBD |
| Carrier | TBD |
| Rate model | TBD, flat rate or calculated |
| Restricted states or countries | TBD |

Shipped weight per variant is required before shipping rates can be configured.
Water is heavy and the 5-pack will not be five times the 1-pack rate.

## Payments

Do not enable Shopify Payments as the long-term processor. See the payments
section in `specs/00-overview.md`. The client configures a third-party
high-risk gateway under Settings, Payments, Third-party providers.

Shopify Payments may be used temporarily on a development store for test
orders, since dev stores use Shopify's test gateway and process nothing real.

## Copy constraints

No health claims. No dosage guidance. No reconstitution instructions. No
mention of what the water might be mixed with. No peptide vocabulary anywhere
on the site, including alt text, meta descriptions, and structured data.

That last point is not cosmetic. Reconstitution instructions and peptide
adjacency are precisely what FDA warning letters have cited.

## Brand assets needed

| Asset | Status |
| --- | --- |
| Logo, SVG preferred | TBD |
| Favicon, 512x512 PNG | TBD |
| Exact wordmark spelling | TBD |
| Brand colours, hex | TBD |
| Product photography, 2000px square | TBD |

Must not visually resemble Noviq Peptides or March Analytics.

## Definition of done

- One product, two variants, correct weights, shipping rates verified against a
  real destination address.
- Homepage buy button visible above the fold at 375px.
- All six pages exist with the handles above.
- No peptide vocabulary anywhere in the rendered storefront or its metadata.
- Test order completes end to end.

# 02 fastpeptidetesting.com

Status: draft. Blocked on service catalog and lab logistics.

Platform: Shopify, Dawn theme in `fastpeptidetesting/`.
Store handle: TBD
Brand name: March Analytics

Build this site first. It sells a laboratory service, carries no restricted
product, and works on Shopify Payments, so it validates the whole pipeline
without risking a terminated store.

## What it sells

Analytical testing of customer-supplied samples, primarily HPLC purity. The
customer buys a test, ships a sample to the lab, and receives a report.

## The critical Shopify configuration

Each service is a product with **"This is a physical product" unchecked** and
**inventory tracking off**.

If this is missed, Shopify demands a shipping address and quotes shipping rates
at checkout on a service where the customer is the one shipping something to
the lab. It also blocks the order when no rate matches. This is the single most
common way this kind of store ships broken.

## Service catalog

| Service | Price | Turnaround, business days | Status |
| --- | --- | --- | --- |
| HPLC purity | TBD | TBD | TBD |
| Mass spectrometry identity | TBD | TBD | Confirm whether offered |
| Sterility | TBD | TBD | Confirm whether offered |
| Endotoxin | TBD | TBD | Confirm whether offered |

Confirm which of these the lab actually runs before building product pages for
them. Advertising a test the lab cannot perform is worse than a thin catalog.

## Sample intake

Sample details are captured at checkout using cart line item properties on the
product page, so they arrive attached to the order. No app required; this is a
form in the product template plus the `properties[...]` input naming
convention.

Default fields, pending client confirmation:

1. Compound name
2. Batch or lot number
3. Quantity supplied
4. Customer return address

Mark required fields as required in the markup. A test with no batch number
generates a support email for every order.

## Lab logistics

| Field | Value |
| --- | --- |
| Receiving address for samples | TBD |
| What the customer ships, and packaging requirements | TBD |
| Result delivery method | TBD: emailed PDF and/or private portal. Never public. |
| Turnaround clock start, on payment or on sample receipt | TBD |

The turnaround clock question matters for the copy. "3 day turnaround" means
something different measured from checkout than from sample arrival, and the
difference generates chargebacks.

## Homepage

1. `image-banner` or `slideshow`, what the lab does and turnaround time
2. `featured-collection` or `multicolumn`, the service list with prices
3. `icons-with-content`, methodology trust points, for example HPLC, in-house
   instrumentation, chain of custody, independent
4. `multirow`, how it works as a numbered process: order, ship sample, receive
   report
5. `collapsible-content`, FAQ

## Pages

| Handle | Purpose |
| --- | --- |
| `how-it-works` | Ordering and sample submission process |
| `methods` | Instrumentation and methodology, factual |
| `turnaround` | Timing and what starts the clock |
| `about` | Independent lab positioning |
| `attestation` | Research-use attestation |
| `contact-us` | Dawn `contact-form` section |
| `terms` | Uploaded Mar 2026; verify Settings → Policies matches `/pages/terms` |
| `privacy` | Uploaded Mar 2026; verify Settings → Policies matches `/pages/privacy` |
| `refund-policy` | Uploaded Mar 2026; service refund policy; verify matches Settings → Policies |

## Independence

March Analytics reads as an independent testing lab. That independence is the
product. No links to noviqpeptides.com, no shared branding, no language
implying common ownership with any peptide seller.

If the client wants Noviq products tested by March Analytics, that is a
commercial relationship the sites do not advertise.

## SEO and AI discovery

Theme already emits titles, meta descriptions, canonicals, Open Graph / Twitter
tags, and product structured data. Custom agent instructions live in
`fastpeptidetesting/templates/agents.md.liquid` and are served at `/agents.md`,
`/llms.txt`, and `/llms-full.txt`.

Product body HTML in the seed still says identity confirmation is included.
That claim is pending lab confirmation (see Mass spectrometry identity in the
service catalog). Search engine listing meta deliberately omits it. Do not put
identity inclusion into Admin SEO fields until intake confirms it.

Offline gate: `npm run seo-audit:fpt` after regenerating `catalog.json`.

### Admin checklist (before and after password removal)

1. **Online Store → Preferences:** homepage title and meta description for March
   Analytics. Do not invent final copy until intake allows; structure is enough
   for preview.
2. **Search engine listing** on each product, the `order-testing` collection,
   and each page. Re-run `fastpeptidetesting/seed/import.mjs` after
   `extract.mjs` to push seed meta: products and the collection use Admin
   `seo`; pages use metafields `global.title_tag` and `global.description_tag`.
3. **Theme assets that affect SEO and AI surfaces** (still TBD in Brand assets):
   favicon, logo (Organization JSON-LD `logo` only renders when `settings.logo`
   is set), and a social share image. `config/settings_data.json` currently has
   no favicon or logo bound and all social links blank.
4. After the storefront password is off and `fastpeptidetesting.com` is primary:
   - Open `/robots.txt` and confirm crawlers are not blocked.
   - Open `/sitemap.xml`.
   - Open `/llms.txt` and `/agents.md`; confirm lab-service wording and no
     cross-brand references.
5. **Google Search Console:** verify the live domain, submit `sitemap.xml`.
6. **Google Analytics 4:** install Google & YouTube channel, connect one GA4
   property for this store only. Optional ads pixels via **Settings → Customer
   events**, not theme scripts.
7. Confirm footer, meta, and agent files never link to other client brands.

Sitemap and robots stay Shopify-managed. Do not add a custom `robots.txt.liquid`
unless there is a concrete crawl rule to change.

## Brand assets needed

| Asset | Status |
| --- | --- |
| Logo, SVG preferred | TBD |
| Favicon, 512x512 PNG | TBD |
| Brand colours, hex | TBD |
| Any lab or instrument photography | TBD |

Should look like a laboratory, not like an ecommerce brand. Restrained
typography, minimal colour, no lifestyle imagery.

## Definition of done

- Every service product is non-physical with inventory tracking off, and
  checkout never asks for a shipping method.
- Sample intake fields appear on the order in the admin.
- Turnaround language matches whatever the client confirms about the clock.
- All nine pages exist with the handles above.
- Test order completes end to end with intake fields populated.

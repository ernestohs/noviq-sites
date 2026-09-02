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

Commerce uses a single configurable product. Compound-specific URLs may remain
for SEO; purchase CTAs point at Peptide Test.

| Service | Price | Notes | Status |
| --- | --- | --- | --- |
| Peptide Test (`peptide-testing`) | $250 per vial (1–5 vials) | Per-vial peptide dropdown; no differentiated/non-differentiated UI | Confirmed pricing |
| Endotoxin add-on | +$75 per vial | Configurator checkbox | Confirmed preview pricing |
| Sterility add-on | +$75 per vial | Configurator checkbox | Confirmed preview pricing |
| Heavy metals add-on | +$100 per vial | Configurator checkbox | Confirmed preview pricing |
| Karl Fischer add-on | +$250 per vial | Configurator checkbox | Confirmed preview pricing |
| Vial vacuum add-on | +$25 per vial | Configurator checkbox | Confirmed preview pricing |
| Next-Day turnaround | +$199 per order | Configurator select | Confirmed preview pricing |
| Same-Day turnaround | +$449 per order | Configurator select | Confirmed preview pricing |
| Custom analytical | Quote only | `/pages/custom-analytical` contact form; not self-serve checkout | Confirmed |
| Mass spectrometry identity | TBD | Confirm whether included in base Peptide Test | Confirm before SEO claims |

Standard turnaround is 3 business days, included. Clock start (payment vs
sample receipt) remains intake C10.

Confirm which optional screens the lab actually runs before launch. Advertising
a test the lab cannot perform is worse than a thin catalog.

## Sample intake

Sample details are captured as cart line item properties on the Peptide Test
product page, so they arrive attached to the order. No app required.

Configurator fields:

1. Number of vials (1–5) as product variants at $250 × n
2. Peptide name per vial (required dropdowns; list from `snippets/peptide-option-list.liquid` / catalog compounds)
3. Optional screens and turnaround (priced via helper products added with the order)
4. Batch or lot number
5. Quantity supplied
6. Customer return address

Mark required fields as required in the markup. A test with no batch number
generates a support email for every order.

## Lab logistics

| Field | Value |
| --- | --- |
| Receiving address for samples | TBD |
| What the customer ships, and packaging requirements | Protective packaging and tracked carrier for preview workflow; lab-specific requirements TBD |
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
| `custom-analytical` | Quote request for custom analytical work (not checkout) |
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
tags, and product structured data (including homepage Organization / WebSite /
Service JSON-LD).

Agent surfaces (Shopify-native paths plus OpenAPI):

| Path | Source |
| --- | --- |
| `/agents.md` | `templates/agents.md.liquid` (when-to-use, permissions, API) |
| `/llms.txt` | `templates/llms.txt.liquid` (short discovery) |
| `/llms-full.txt` | Falls back to `agents.md.liquid` |
| `/openapi.json` | URL redirect → `/pages/openapi` (`templates/page.openapi.liquid`) |
| `/pages/llms-txt`, `/pages/agents-md` | Page mirrors of the agent files |

Shopify serves `/llms.txt` and `/agents.md` as `text/markdown` with `Vary: Accept`.
`/pages/openapi` returns a valid OpenAPI 3.1 body but Shopify still labels it
`text/html`; deploy `fastpeptidetesting/edge/` (Cloudflare Worker) to set
`Content-Type: application/json` and reinforce `Vary: Accept`. See
[docs/fpt-agentic-readiness.md](../docs/fpt-agentic-readiness.md).

Verify live: `npm run verify-agentic:fpt`.

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
7. **Measurement runbook:** execute
   [docs/fpt-analytics.md](../docs/fpt-analytics.md) for GA4, Search Console,
   GTM (custom pixel), Microsoft Clarity, and optional ad pixels. All IDs are
   intake block F in `specs/10-intake.md`; do not embed IDs in the theme.
8. Confirm footer, meta, and agent files never link to other client brands.

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

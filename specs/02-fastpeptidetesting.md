# 02 fastpeptidetesting.com

Status: draft. Blocked on service catalog and lab logistics.

Platform: Shopify, Dawn theme in `fastpeptidetesting/`.
Store handle: TBD
Brand name: March Analytics

Build this site first. It sells a laboratory service, carries no restricted
product, and works on Shopify Payments, so it validates the whole pipeline
without risking a terminated store.

## What it sells

Analytical testing of customer-supplied samples. The customer buys a test,
ships a sample to the lab, and receives a report.

The client's model is one base test, priced add-ons, quantity bundles, and
turnaround upsells. It is not four equal standalone tests. Build the product
model to match this shape.

## Reference sites and intent

The client named three references and one hard rule about them:

- `vanguardlaboratory.com`, called "the best template for testing, but even
  simpler is ok".
- `peptidetest.com`, a testing-service storefront that leads with an "Order
  Testing" call to action.
- `freedomdiagnosticstesting.com`, "basically this".

The client's complaint about all of them: "they never have the pricing" up
front, and they bury the service behind "a bunch of shit no one cares about".

So the storefront rules are:

1. The landing page is the product. First thing on screen: services offered,
   the price, and a buy control. No hero essay before the price.
2. An "Order Testing" call to action is prominent on every page.
3. A top banner reads exactly: FAST AFFORDABLE RELIABLE.

## The critical Shopify configuration

Each service is a product with **"This is a physical product" unchecked** and
**inventory tracking off**.

If this is missed, Shopify demands a shipping address and quotes shipping rates
at checkout on a service where the customer is the one shipping something to
the lab. It also blocks the order when no rate matches. This is the single most
common way this kind of store ships broken.

## Service catalog

Base test, one product. It covers three measurements together:

| Base test includes | Price |
| --- | --- |
| Purity, Potency, Identity | $299 (see price conflict below) |

Add-ons, sold on top of the base test. Model as line-item options or variants
on the base product, not as separate products:

| Add-on | Price |
| --- | --- |
| Heavy metal testing | $100 |
| Sterility | $75 |
| Endotoxins | $100 |

Turnaround upsells, applied to the base test:

| Turnaround | Price |
| --- | --- |
| 3 business days | Standard, included |
| 1 business day | +$300 |
| Same day | +$500 |

### Two pricing offers

The client gave two price lists and confirmed they are two separate offers, not
a conflict to reconcile. Model them as two distinct products so a buyer picks
one path, not a blended cart.

| Offer | Single | Bundles | Notes |
| --- | --- | --- | --- |
| A, itemised | $299 | 5 tests $1250, 10 tests $2000 | Base test plus the add-ons and turnaround upsells above |
| B, flat bundle | $300 | 3 tests $820, 10 tests $2500 | Simple all-in bundles, "fast affordable reliable" framing |

Open risk: both offers include a 10-test tier at different prices ($2000 vs
$2500) and a near-identical single price ($299 vs $300). Side by side on one page
this reads as inconsistent pricing for the same thing. Give each offer a distinct
name and page section, or have the client decide which is the headline offer.

Base test covers Purity, Potency, and Identity as one purchase; Heavy metal,
Sterility, and Endotoxin are the only add-ons. Confirmed by the client. The
client said "Identity", not "mass spectrometry"; do not advertise a specific
instrument for identity until the lab confirms the method.

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
5. Whether the customer consents to the result being published as a public COA

Mark required fields as required in the markup. A test with no batch number
generates a support email for every order.

## Lab logistics

| Field | Value |
| --- | --- |
| Receiving address for samples | TBD |
| What the customer ships, and packaging requirements | TBD |
| Result delivery method | TBD, emailed PDF, portal login, or public COA page |
| Turnaround clock start, on payment or on sample receipt | TBD |

The turnaround clock question matters for the copy. "3 day turnaround" means
something different measured from checkout than from sample arrival, and the
difference generates chargebacks.

## Homepage

Pricing and the buy control must be above the fold. Do not open with a hero
essay; the client rejected that pattern on every reference site.

1. Thin top banner: FAST AFFORDABLE RELIABLE
2. `featured-product` for the base test, price and buy control visible above the
   fold, with add-ons and turnaround selectable inline
3. `icons-with-content`, methodology trust points, for example HPLC, in-house
   instrumentation, chain of custody, independent
4. `multirow`, how it works as a numbered process: order, ship sample, receive
   report
5. `collapsible-content`, FAQ

Keep a persistent "Order Testing" call to action in the header on every page.

## Pages

| Handle | Purpose |
| --- | --- |
| `how-it-works` | Ordering and sample submission process |
| `methods` | Instrumentation and methodology, factual |
| `turnaround` | Timing and what starts the clock |
| `contact-us` | Dawn `contact-form` section |
| `terms` | TBD, client supplies |
| `privacy` | TBD, client supplies |
| `refund-policy` | TBD, client supplies. Services need a different refund policy than goods. |

## Independence

March Analytics reads as an independent testing lab. That independence is the
product. No links to noviqpeptides.com, no shared branding, no language
implying common ownership with any peptide seller.

If the client wants Noviq products tested by March Analytics, that is a
commercial relationship the sites do not advertise.

## Brand assets needed

| Asset | Status |
| --- | --- |
| Logo, SVG preferred | TBD |
| Favicon, 512x512 PNG | TBD |
| Brand colours, hex | Use the shared palette in `specs/00-overview.md`, "Design system" |
| Any lab or instrument photography | Provided by the client; RUO graphics coming. Until delivered, gray "IMAGE TBD" placeholders, no stock imagery. |

Follow the shared minimalist design system in `specs/00-overview.md`. Only the
name, logo, and images differ from the other sites. The restrained monochrome
palette already suits a laboratory look; no lifestyle imagery.

## Definition of done

- Every service product is non-physical with inventory tracking off, and
  checkout never asks for a shipping method.
- Base test price and buy control are above the fold at 375px, with add-ons and
  turnaround selectable on the same screen.
- FAST AFFORDABLE RELIABLE banner present; "Order Testing" call to action on
  every page.
- Sample intake fields appear on the order in the admin.
- Turnaround language matches whatever the client confirms about the clock.
- All seven pages exist with the handles above.
- Test order completes end to end with intake fields populated.

# Policy redline for counsel (noviqpeptides.com)

Date: 2026-09-09  
Audience: Client counsel (live host policy pages) and engineering (dev seed in `plugin/data/noviq/pages.json`).  
Scope: US-only storefront. Disclosure-only cookies; no consent CMP.

Per `specs/03-noviqpeptides.md`, live policy HTML on the production host is the source of truth and must not be overwritten by rsync. Apply the redlines below on the host. Matching copy is already in the repo seed for local Docker.

---

## 1. Privacy Policy (`/policies/privacy/`)

### Replace: cart / automatic collection

**Remove** language that says cart contents are stored only in the browser's local storage and are not transmitted until an order is submitted.

**Replace with** (substance of the seed):

- Cart and checkout sessions are held **server-side** by WooCommerce, keyed by necessary session cookies.
- Cart contents are transmitted when items are added, not only at order submit.
- Product favourites use **local storage only** (`nq_favorites`) and are not sent to the server.
- Newsletter: email plus consent timestamp when the visitor opts in; no subscriber IP is stored.
- Cookies detail lives on `/policies/cookies/` (link from the cookies section).

### Why

The previous wording contradicted WooCommerce behaviour and was the highest-liability copy defect on the site.

---

## 2. New page: Cookies (`/policies/cookies/`)

Create a child page under Policies. Footer nav already links to it in the seed.

Must disclose exactly:

| Name / mechanism | Purpose | Lifetime | Notes |
| --- | --- | --- | --- |
| WooCommerce cart / session cookies | Cart and checkout | Session / Woo defaults | Strictly necessary |
| `noviq_age_verified` | Age confirmation (21+) | 1 year | Readable by JS on this site |
| `noviq_qty_style` | Quantity UI preference | 1 year | Only non-essential first-party cookie |
| `nq_favorites` (localStorage) | Favourites | Until cleared | Never transmitted |

State explicitly: no GA4, GTM, Meta Pixel, Hotjar, Clarity, ad pixels, or marketing iframes.

---

## 3. Accessibility statement (`/policies/accessibility/`)

### Soften absolute conformance claims

**Do not** claim that every interactive element has a visible focus indicator, that all text meets AA, or that all motion is disabled under reduced motion, as blanket facts.

**Do** say:

- The site **targets** WCAG 2.1 AA.
- The statement is a **self-assessment**, not an independent audit.
- List known limitations honestly, including:
  - Age gate does not close on Escape (intentional compliance gate).
  - Desktop nav dropdowns are hover/focus-within, with `aria-expanded` on parents, not a full disclosure widget.
  - Wide research tables, sequence strings, and third-party COA PDFs (unchanged).

Engineering has fixed the concrete defects that previously made the old absolute claims false (contrast on CTA blues and footer muted text, mobile cart/account names, search focus ring, reduced-motion coverage, carousel/dose ARIA roles, cart live region).

---

## 4. Shipping & Returns / Cancellation / Terms

No wording defects found in the seed. Refund rules live in Shipping & Returns and Cancellation; there is **no** standalone `/policies/refund` page.

**Counsel confirmation requested:** confirm that merging refunds into Shipping & Returns (plus Cancellation) remains intentional for the live host.

---

## 5. Client questions (do not invent answers)

These are inconsistent in the repo. Confirm and we will update `specs/10-intake.md` and the About page seed.

| # | Question | Current conflict |
| --- | --- | --- |
| Q1 | Legal entity for noviqpeptides.com? | Profile: `Lava Goat Wholesale LLC`. Plugin header author: `Noviq Labs, Inc.` Intake D1: still TBD. |
| Q2 | Business address and phone for public footer / contact / policies? | Profile: `4030 W 1st Ave, Suite 100, Eugene, OR 97402`, `+1 541-515-1510`. About page seed still says details are "being finalised". |
| Q3 | WooCommerce store country / state for tax and shipping defaults? | Seed sets `US:TX` while the profile address is Oregon. |
| Q4 | Support / wholesale / partners emails still correct? | Profile: `support@`, `wholesale@`, `partners@noviqpeptides.com`. |
| Q5 | EIN / tax ID needed anywhere on-storefront? | Not present in repo; confirm if counsel wants it on invoices only. |

Until Q1–Q3 are answered, do not change production About copy or Woo store address from this repo alone.

---

## 6. Engineering changes already in the plugin/theme (for counsel awareness)

- Newsletter no longer stores subscriber IP; stores consent timestamp; consent checkbox required; WP privacy exporter/eraser registered for `noviq_subscriber`.
- `woocommerce_privacy_policy_page_id` and `wp_page_for_privacy_policy` wired to `/policies/privacy/` on seed.
- Researcher attestation on **block** checkout now records the same `_noviq_attestation`, `_noviq_attestation_text`, and `_noviq_attestation_at` meta as classic checkout.
- No analytics or third-party embeds added.

---

## 7. Apply checklist (host)

1. Update Privacy Policy body per section 1.
2. Publish Cookies page; add to Policies nav.
3. Update Accessibility statement per section 3.
4. Confirm refund page structure (section 4).
5. Answer Q1–Q5; sync About page and intake.
6. Do **not** rsync `pages.json` over live policies.

# 03 noviqpeptides.com

Status: draft. Client is actively building this out and answering build intake
questions, so treat it as in scope. Confirm the exact scope, alignment vs
rebuild, and price before starting.

Platform: WordPress + WooCommerce, hosted externally.
Brand name: Noviq Peptides

## Client-provided facts

| Item | Value |
| --- | --- |
| Registrar | GoDaddy, client can transfer |
| Products at launch | ~25 |
| People managing catalog and orders | 3 |
| Payment methods | "All methods". Client needs the live site to get final CC-processor approval. |
| Fulfilment | Shipping exclusively, no pickup or local delivery |
| Notifications | Wants CRM integration; fulfilment software undecided, open to suggestions |
| Order notification email | orders@noviqpeptides.com, not yet set up |
| White label | Vials say the actual peptide name, not "injectable peptide" |

## Copy constraint

Do not use the word "injectable" anywhere, per the client. The white-label vial
art carries the peptide name in place of "injectable peptide". Apply the same
clinical, RUO-only tone as the other sites: no health claims, no dosage, no
therapeutic language.

## Design

Follow the shared minimalist design system in `specs/00-overview.md`: monochrome
base, single green accent, clean and uncluttered. Only the name, logo, and images
differ from the other sites. This is WordPress, not Dawn, so the palette and
principles carry over even though the theme mechanics differ. Use gray "IMAGE
TBD" placeholders until the designer delivers assets.

## Scope question

Open, and it needs an answer before any work happens here: are we alignment
only, or are we taking over the build?

| Scope | What it means | Estimate |
| --- | --- | --- |
| Alignment only | CSS and content adjustments on the existing theme | 2 to 3 days |
| Rebuild | New theme, template work, migration | 1.5 to 2 weeks, price separately |

Do not start until this is settled and priced.

## Access needed

| Item | Status |
| --- | --- |
| WordPress admin login | TBD |
| Theme or page builder in use, for example Elementor, Blocksy, Kadence, custom | TBD |
| Hosting or SFTP access, if theme files need editing | TBD |
| Whether the site is live and taking orders, or staging | TBD |
| Staging environment available | TBD |

Nothing in this repository deploys to WordPress. If we take on template work,
add a `noviqpeptides/` directory holding only the child theme or the specific
files we own, never a full WordPress copy.

## Payments

The client's responsibility, and the largest financial exposure across the
three sites. RUO peptides require a high-risk merchant account; mainstream
processors will terminate on detection. We do not select, configure, or hold
credentials for it.

Record here once known: TBD

## Constraint inherited from the overview

This site must not link to bacwatermarket.com or fastpeptidetesting.com, and
must not share branding with them. See `specs/00-overview.md`.

Outbound links from here to the Shopify sites are lower risk than the reverse,
but stay out until the client accepts the tradeoff in writing.

## What this repository holds for this site

`noviqpeptides/` contains notes, exports, and any child theme files we own.
It does not contain a WordPress installation.

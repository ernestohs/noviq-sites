# 03 noviqpeptides.com

Status: draft. Site already built by the client. Scope of our involvement is
undecided.

Platform: WordPress + WooCommerce, hosted externally.
Brand name: Noviq Peptides

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

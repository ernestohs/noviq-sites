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
credentials in git.

Storefront path: Tagada hosted payment gateway (`tagada-gateway`, WooCommerce
gateway id `tagada`, plugin 1.1.6 or newer). The shopper completes Woo checkout
(address, shipping, tax, researcher attestation, account gate), then redirects
to Tagada's hosted pay page. Order confirmation is server-side via webhook;
an optional return with `?tgd_success=1` can show the Woo thank-you page.

The vendor zip is generated per store and funnel (`funnelId` / `stepId` baked
in) from the Tagada CRM funnel editor → WooCommerce step → Download Gateway
Plugin. Install through wp-admin only; do not commit the zip or put it on the
rsync path. Re-download after any funnel change. Do not also install
`tagada-checkout` (it hijacks `/checkout` before Woo gateways run) or enable
Inline card alongside hosted.

The CRM access token (`sk_crm_…`) is entered only under WooCommerce → Settings
→ Payments → Tagada Pay in WordPress admin. Never commit that value.

Tagada re-prices from its synced catalogue and may add a
`wc_total_reconciliation` line. Volume breaks and VIP coupons live in our
plugin and must be parity-tested against the hosted page amount before go-live.
See `noviqpeptides/docs/tagada-gateway-runbook.md`.

After removing the former PayPal invoice gateway, run
`wp noviq cancel_payment_reminders` once per environment to clear orphaned
Action Scheduler rows and delete stored PayPal options (including the REST
Client Secret).

## Accounts

Tagada requires visitors to be registered before they see products. The plugin
enforces this with `Compliance\RegistrationGate`: logged-out visitors get a
standalone "Researcher access" screen (register or sign in) instead of any
catalog, cart, or home content. Product HTML is never sent underneath an
overlay.

Allowlisted without an account: `policies/*` (terms, privacy, shipping,
cancellation, cookies, accessibility), WooCommerce lost-password / reset
endpoints, `wp-login.php`, `admin-post.php`, and non-catalog REST (so Tagada
webhooks keep working). Store API product routes, product REST, product feeds,
and product/CPT sitemaps are closed for guests. The site is effectively
de-indexed for catalog content.

Registration collects email, password, field of research, a 21+ researcher
attestation (same wording family as checkout), and Terms/Privacy acceptance.
Evidence is stored as user meta (`_noviq_reg_*`). Accounts are self-serve and
can browse immediately; no admin approval step. There is no signup discount
unless the client decides one later.

Guest checkout stays off and is still enforced by `Compliance\BuyerAccounts`
as a second line at checkout. The researcher attestation on the order remains
the purchase-time compliance record and is unchanged.

The cookie-only age overlay (`Compliance\AgeGate`) is off for this profile;
the registration attestation replaces it.

## Constraint inherited from the overview

This site must not link to bacwatermarket.com or fastpeptidetesting.com, and
must not share branding with them. See `specs/00-overview.md`.

Outbound links from here to the Shopify sites are lower risk than the reverse,
but stay out until the client accepts the tradeoff in writing.

## Policy pages

Terms, privacy, shipping, and cancellation policies are uploaded on the
production host (Mar 2026). Local seed content in `plugin/data/noviq/pages.json`
is for dev only; do not rsync it over live policy pages.

## What this repository holds for this site

`noviqpeptides/` contains notes, exports, and any child theme files we own.
It does not contain a WordPress installation.

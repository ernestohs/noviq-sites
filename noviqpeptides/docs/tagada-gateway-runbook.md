# Tagada hosted gateway runbook (noviqpeptides)

Staging first, then the same sequence on production. Processor and banking
setup stay on the Tagada / client side. Credentials never enter git.

## Pre-check: on-hold PayPal orders

PayPal is suspended. Any on-hold orders that still expect a PayPal invoice link
cannot be paid that way. List them and reconcile or cancel by hand before
cutting over:

```bash
wp wc shop_order list --status=on-hold --user=1
# or, if the WC CLI package is unavailable:
wp post list --post_type=shop_order --post_status=wc-on-hold --fields=ID,post_date,post_title
```

Historical order meta (`_noviq_paypal_invoice_id`, `_noviq_paypal_payment_url`)
is left in place as an audit trail. Admin may show the raw method id
`noviq_paypal_invoice` on those orders; that is expected.

## Cleanup after deploying this plugin revision

Once the updated `noviq-peptides` plugin is on the host (no PayPal gateway
code), cancel orphaned reminder actions and delete stored PayPal options
(including the REST Client Secret):

```bash
wp noviq cancel_payment_reminders --dry-run
wp noviq cancel_payment_reminders
wp noviq cancel_payment_reminders --dry-run   # expect zero pending, no options
```

## Install Tagada hosted gateway

Do this on **staging** first. Repeat on production only after the pricing parity
matrix passes.

### 1. Tagada CRM: store + funnel

1. Confirm a processor is active for USD and the countries you sell to.
2. Create (or select) the store for this site.
3. Create a funnel that includes a **WooCommerce** step. The gateway zip cannot
   be generated without a funnel; `funnelId` and `stepId` are baked into the zip.

### 2. Download the gateway zip

1. Funnel editor → WooCommerce step → **Download Gateway Plugin**
   (`format: 'gateway'`).
2. Require plugin **1.1.6 or newer**. Older builds only emptied the Woo cart when
   the shopper returned with `?tgd_success=1`, so anyone who stopped on the
   Tagada thank-you page came back to a full cart.
3. Do **not** download or install the Full Checkout Redirect zip
   (`tagada-checkout`). That plugin hijacks `/checkout` before Woo gateways run
   and would skip attestation and the account gate.

### 3. WordPress: upload and activate

1. wp-admin → Plugins → Add New → Upload → activate `tagada-gateway`.
2. Confirm `tagada-checkout` is **not** installed.
3. WooCommerce → Settings → Payments → enable **Tagada Pay** only (gateway id
   `tagada`). Do **not** also enable Inline card.
4. Paste the CRM access token (`sk_crm_…`, shown once at creation under Tagada
   Settings → Access Tokens). This is not a Partner Hub `tp_sk_…` key and not a
   card-network secret.
5. Save.

### 4. Webhooks

1. Register the Tagada webhook endpoint for this store so paid sessions mark the
   Woo order processing/completed server-side.
2. Place a test order and confirm a delivery lands (Tagada CRM webhook log and
   Woo order status).
3. Optional return with `?tgd_success=1` may show the Woo thank-you page; do not
   rely on the browser return alone for confirmation.

Local Docker (`http://localhost:8080`) cannot receive Tagada webhooks. Use
staging for confirmation testing.

### 5. Re-download after funnel changes

Whenever the funnel (or redirect / WooCommerce step settings) changes,
re-download the gateway zip from the funnel editor and replace the plugin on
the host. The zip is store- and funnel-specific. This coupling did not exist
under PayPal; treat it as a required ops step.

## Pricing parity matrix (blocking)

Tagada re-prices from its synced catalogue and may add
`wc_total_reconciliation`. Our volume breaks and VIP coupons change the Woo
order total. For each case below, compare the amount on the Tagada hosted page
to `$order->get_total()` to the cent:

| Case | Expect |
| --- | --- |
| Qty 1, no tier | Base price |
| Qty 3 | 6% volume tier |
| Qty 5 | 10% volume tier |
| Qty 10 | 16% volume tier |
| 10 units across 5 variants | No volume tier (per-variant rule) |
| VIP referral coupon alone | Coupon discount on Woo total |
| VIP coupon + qty 5 | Stacked coupon and 10% tier |

If any row diverges, stop go-live and switch to Inline card (`tagada_inline`),
which charges `$order->get_total()` directly, or fix catalogue sync on the
Tagada side.

## Smoke path after install

Age gate → shop → cart → login or register at checkout → researcher
attestation → Tagada Pay selected → Place order → hosted pay page → webhook
marks order paid → empty `/coa` still empty until real lots exist.

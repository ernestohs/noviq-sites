# 20 Launch

Per-store go-live procedure. Run in order. Out of order means the client sees a
broken site on his own domain, or a store takes a real order it cannot fulfil.

Roughly half a day per store, most of it waiting on the client.

## Before you start

Sign-off on the preview link. Not "looks good on the call", an explicit yes on
the shared preview URL.

## Sequence

### 1. Transfer and plan, client

The development store is transferred to the client's ownership, and he selects
a plan. Basic at $39/mo is sufficient. A development store cannot take real
orders until this happens.

Use a different owner email per store. See `specs/00-overview.md`.

### 2. Store details, client

Settings, Store details. The store name here drives checkout, notification
emails, and the admin. The `brand_name` theme setting only covers the
storefront, so both must be set.

### 3. Payments, client

Settings, Payments. For fastpeptidetesting, Shopify Payments is fine. For
bacwatermarket, connect the third-party high-risk gateway under Third-party
providers and leave Shopify Payments disabled.

Business verification usually takes a day or more. Start it early.

### 4. Legal pages

Terms, privacy, refund policy, and shipping policy live and linked in the
footer. Shopify also has policy fields under Settings, Policies, which populate
the checkout footer. Fill both.

### 5. Taxes and regions, client

Settings, Taxes and duties. Settings, Shipping and delivery for physical goods.
Confirm the restricted destinations from the site spec are actually blocked,
not just documented.

### 6. Theme publish

```bash
shopify theme list
shopify theme push --unpublished
```

Review the preview, then publish from the admin or with an explicit theme ID.
Never a bare push.

### 7. Domain

Only now. Settings, Domains, Connect existing domain. Use the exact records the
connect flow displays. Do not copy IP addresses or CNAME targets from
documentation, blog posts, or a previous project; Shopify has changed them.

Allow for DNS propagation before declaring anything broken.

### 8. Remove the password

Online Store, Preferences. Removing the storefront password is the actual
moment of going live. Everything above should be done first.

### 9. Verify

1. Place a real order with a real card, smallest possible amount.
2. Confirm the order appears in the admin with any custom fields populated.
3. Confirm the notification email arrives at the address in `specs/10-intake.md`.
4. Refund the test order.
5. Check the storefront at 375px, 768px, and 1440px.
6. Confirm no page links to the other client sites.

Step 6 is not a formality. A stray link added during content entry undoes the
separation the whole architecture depends on.

## Post-launch

- Submit the sitemap in Google Search Console.
- Confirm the store is not blocking crawlers in `robots.txt`, which happens
  automatically when the password is removed but is worth checking.
- For fastpeptidetesting, also verify `/llms.txt` and `/agents.md` (see SEO and
  AI discovery in `specs/02-fastpeptidetesting.md`).
- Record the live theme ID in the site spec, so a future push targets the right
  theme.

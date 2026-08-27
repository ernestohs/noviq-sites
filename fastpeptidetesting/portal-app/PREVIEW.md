# March Analytics order portal (preview)

Theme + custom app work for Accumark-style ordering on March Analytics only.

## Theme (`fastpeptidetesting/`)

- Login gate on Peptide Test (`snippets/order-login-gate.liquid`)
- 5-step wizard in `snippets/peptide-test-configurator.liquid` + `assets/peptide-test-configurator.js`
- Cart RUO attestation (`snippets/cart-ruo-attestation.liquid`)
- Certificate QR + verify deep-link (`sections/main-metaobject-certificate.liquid`, `sections/main-page-verify.liquid`)
- Header **Portal** link for signed-in customers → `/apps/portal`

## App (`fastpeptidetesting/portal-app/`)

App Proxy at `/apps/portal`. See `portal-app/README.md` and `STAGE-AUTOMATION.md`.

Metafields (created by `seed/setup-portal-metafields.mjs`):

- `order.march_analytics.lab_stage`
- `order.march_analytics.packing_notes`
- `order.march_analytics.certificate_ids`
- `order.march_analytics.vials_required`
- `customer.march_analytics.coa_profiles`

## Preview checklist

1. Theme pushed to `fpt-preview` (development store `srgkrj-ij.myshopify.com`)
2. Password-protect storefront in Admin → Preferences (if sharing outside the team)
3. Use Shopify test gateway (Bogus) for a paid test order after signing in
4. Install/tunnel the portal app before relying on `/apps/portal`
5. `npm run theme-check:fpt` should report no errors

Do not attach production DNS or real payments for this workstream.

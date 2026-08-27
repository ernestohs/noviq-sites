# Stage automation

Paid orders start at **Order Submitted** via the portal app `orders/paid` webhook
(`POST /webhooks/orders-paid`), which writes metafield `march_analytics.lab_stage`.

## Advance stages

### Option A — Portal admin API

```bash
curl -X POST "https://YOUR_TUNNEL/proxy/admin/orders/ORDER_ID/stage" \
  -H "Content-Type: application/json" \
  -H "x-portal-admin-secret: $PORTAL_ADMIN_SECRET" \
  -d '{"stage":"Sample Received"}'
```

Valid stages:

1. Order Submitted
2. Sample Received
3. Analyzing
4. Under Review
5. Complete

### Option B — Shopify Flow

Trigger: Order metafield updated or manual staff action.

Action: Update order metafield

- Namespace: `march_analytics`
- Key: `lab_stage`
- Value: one of the stages above

Optional: send customer email notification when stage changes (Flow email action).

### Option C — Admin manual

Shopify Admin → Order → Metafields → Lab stage.

## Customer visibility

The App Proxy portal (`/apps/portal`) reads `lab_stage` and renders the pipeline.
No Noviq or Accumark branding in notifications or portal copy.

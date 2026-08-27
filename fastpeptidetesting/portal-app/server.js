import { createHmac, timingSafeEqual } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import express from 'express';
import { createPortalRouter, DEFAULT_STAGE } from './routes/portal.js';
import { setOrderStage, env } from './lib/shopify.js';

const DIR = dirname(fileURLToPath(import.meta.url));

function loadDotEnv() {
  const path = join(DIR, '.env');
  if (!existsSync(path)) return;
  for (const line of readFileSync(path, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq < 1) continue;
    const key = trimmed.slice(0, eq).trim();
    let value = trimmed.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (!process.env[key]) process.env[key] = value;
  }
}

loadDotEnv();

const app = express();
const port = Number(process.env.PORT || 3456);

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'march-analytics-portal' });
});

function verifyWebhookHmac(req, res, buf) {
  const secret = env().apiSecret;
  if (!secret) return;
  const hmacHeader = req.get('x-shopify-hmac-sha256');
  if (!hmacHeader) {
    res.status(401).send('Missing HMAC');
    throw new Error('Missing HMAC');
  }
  const digest = createHmac('sha256', secret).update(buf).digest('base64');
  const valid = timingSafeEqual(Buffer.from(digest), Buffer.from(hmacHeader));
  if (!valid) {
    res.status(401).send('Invalid HMAC');
    throw new Error('Invalid HMAC');
  }
}

app.post(
  '/webhooks/orders-paid',
  express.raw({ type: '*/*' }),
  async (req, res) => {
    try {
      verifyWebhookHmac(req, res, req.body);
      const payload = JSON.parse(req.body.toString('utf8'));
      const orderId = payload?.admin_graphql_api_id || `gid://shopify/Order/${payload?.id}`;
      await setOrderStage(orderId, DEFAULT_STAGE);
      console.log(`Set ${orderId} → ${DEFAULT_STAGE}`);
      res.status(200).send('ok');
    } catch (err) {
      console.error('orders/paid webhook failed', err);
      if (!res.headersSent) res.status(500).send('error');
    }
  }
);

app.use('/proxy', createPortalRouter());

app.listen(port, () => {
  console.log(`March Analytics portal listening on :${port}`);
  console.log('App Proxy path: /apps/portal → /proxy');
});

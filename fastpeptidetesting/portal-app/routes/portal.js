import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { randomUUID } from 'node:crypto';
import express from 'express';
import QRCode from 'qrcode';
import {
  findCustomerByLoggedInId,
  listCustomerOrders,
  getOrder,
  getCoaProfiles,
} from '../lib/orders.js';
import {
  env,
  verifyAppProxySignature,
  setOrderStage,
  setCustomerCoaProfiles,
  certificatePublicUrl,
  STAGES,
  DEFAULT_STAGE,
} from '../lib/shopify.js';
import {
  renderLoginRequired,
  renderOrderList,
  renderOrderDetail,
  renderPackingSlip,
  renderProfiles,
  renderAdditionalCoa,
} from '../views/html.js';

const __dirname = dirname(fileURLToPath(import.meta.url));

export function createPortalRouter() {
  const router = express.Router();

  router.use((req, res, next) => {
    const { apiSecret } = env();
    if (!verifyAppProxySignature(req.query, apiSecret)) {
      return res.status(401).send('Invalid app proxy signature');
    }
    next();
  });

  router.get('/assets/portal.css', (_req, res) => {
    res.sendFile(join(__dirname, '../public/portal.css'));
  });

  router.get('/', async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.type('html').send(renderLoginRequired());
      const orders = await listCustomerOrders(customer.id);
      res.type('html').send(renderOrderList(orders));
    } catch (err) {
      console.error(err);
      res.status(500).send('Portal error loading orders');
    }
  });

  router.get('/orders/:id', async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.type('html').send(renderLoginRequired());
      const order = await getOrder(req.params.id);
      if (!order || order.customer?.id !== customer.id) {
        return res.status(404).send('Order not found');
      }
      res.type('html').send(renderOrderDetail(order));
    } catch (err) {
      console.error(err);
      res.status(500).send('Portal error loading order');
    }
  });

  router.get('/orders/:id/packing-slip', async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.type('html').send(renderLoginRequired());
      const order = await getOrder(req.params.id);
      if (!order || order.customer?.id !== customer.id) {
        return res.status(404).send('Order not found');
      }
      res.type('html').send(renderPackingSlip(order));
    } catch (err) {
      console.error(err);
      res.status(500).send('Portal error loading packing slip');
    }
  });

  router.get('/orders/:id/additional-coa', async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.type('html').send(renderLoginRequired());
      const order = await getOrder(req.params.id);
      if (!order || order.customer?.id !== customer.id) {
        return res.status(404).send('Order not found');
      }
      res.type('html').send(renderAdditionalCoa(order));
    } catch (err) {
      console.error(err);
      res.status(500).send('Portal error');
    }
  });

  router.get('/orders/:id/qr/:certId.png', async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.status(401).send('Sign in required');
      const order = await getOrder(req.params.id);
      if (!order || order.customer?.id !== customer.id) {
        return res.status(404).send('Order not found');
      }
      const target = certificatePublicUrl(req.params.certId);
      const png = await QRCode.toBuffer(target, { margin: 1, width: 256 });
      res.type('png').send(png);
    } catch (err) {
      console.error(err);
      res.status(500).send('QR error');
    }
  });

  router.get('/profiles', async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.type('html').send(renderLoginRequired());
      res.type('html').send(renderProfiles(getCoaProfiles(customer)));
    } catch (err) {
      console.error(err);
      res.status(500).send('Portal error loading profiles');
    }
  });

  router.post('/profiles', express.urlencoded({ extended: false }), async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.type('html').send(renderLoginRequired());
      const profiles = getCoaProfiles(customer);
      profiles.push({
        id: randomUUID(),
        company: String(req.body.company || '').trim(),
        website: String(req.body.website || '').trim(),
        email: String(req.body.email || '').trim(),
        phone: String(req.body.phone || '').trim(),
        address: String(req.body.address || '').trim(),
      });
      await setCustomerCoaProfiles(customer.id, profiles.filter((p) => p.company));
      res.redirect('/apps/portal/profiles');
    } catch (err) {
      console.error(err);
      res.status(500).send('Failed to save profile');
    }
  });

  router.post('/profiles/:id/delete', express.urlencoded({ extended: false }), async (req, res) => {
    try {
      const customer = await findCustomerByLoggedInId(req.query.logged_in_customer_id);
      if (!customer) return res.type('html').send(renderLoginRequired());
      const next = getCoaProfiles(customer).filter((p) => p.id !== req.params.id);
      await setCustomerCoaProfiles(customer.id, next);
      res.redirect('/apps/portal/profiles');
    } catch (err) {
      console.error(err);
      res.status(500).send('Failed to delete profile');
    }
  });

  // Shared-secret admin endpoint for Flow / ops to advance stages
  router.post('/admin/orders/:id/stage', express.json(), async (req, res) => {
    const { adminSecret } = env();
    if (!adminSecret || req.get('x-portal-admin-secret') !== adminSecret) {
      return res.status(401).json({ error: 'unauthorized' });
    }
    const stage = req.body?.stage;
    if (!STAGES.includes(stage)) {
      return res.status(400).json({ error: 'invalid_stage', stages: STAGES });
    }
    try {
      const gid = String(req.params.id).startsWith('gid://')
        ? req.params.id
        : `gid://shopify/Order/${req.params.id}`;
      await setOrderStage(gid, stage);
      res.json({ ok: true, stage });
    } catch (err) {
      console.error(err);
      res.status(500).json({ error: String(err.message || err) });
    }
  });

  return router;
}

export { DEFAULT_STAGE, STAGES };

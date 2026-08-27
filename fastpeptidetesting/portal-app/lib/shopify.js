import { createHmac, timingSafeEqual } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const schema = JSON.parse(readFileSync(join(DIR, '..', 'metafield-schema.json'), 'utf8'));

export const NS = schema.namespace;
export const STAGES = schema.stages;
export const DEFAULT_STAGE = STAGES[0];

const API_VERSION = '2026-04';

function loadEnv() {
  return {
    shop: (process.env.SHOPIFY_SHOP || '').replace(/^https?:\/\//, '').replace(/\/$/, ''),
    token: process.env.SHOPIFY_ADMIN_TOKEN || '',
    apiSecret: process.env.SHOPIFY_API_SECRET || process.env.APP_PROXY_SHARED_SECRET || '',
    publicOrigin: (process.env.PUBLIC_STORE_ORIGIN || '').replace(/\/$/, ''),
    adminSecret: process.env.PORTAL_ADMIN_SECRET || '',
  };
}

export function env() {
  return loadEnv();
}

export async function adminGql(query, variables = {}) {
  const { shop, token } = loadEnv();
  if (!shop || !token) {
    throw new Error('SHOPIFY_SHOP and SHOPIFY_ADMIN_TOKEN are required');
  }
  const res = await fetch(`https://${shop}/admin/api/${API_VERSION}/graphql.json`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Shopify-Access-Token': token,
    },
    body: JSON.stringify({ query, variables }),
  });
  const json = await res.json();
  if (!res.ok || json.errors) {
    throw new Error(`Admin GraphQL failed: ${JSON.stringify(json.errors || json)}`);
  }
  return json.data;
}

/**
 * Verify Shopify App Proxy query signature.
 * https://shopify.dev/docs/apps/build/online-store/display-dynamic-data#verify-proxy-requests
 */
export function verifyAppProxySignature(query, secret) {
  if (!secret) return process.env.NODE_ENV !== 'production';
  const params = { ...query };
  const signature = params.signature;
  delete params.signature;
  if (!signature) return false;

  const message = Object.keys(params)
    .sort()
    .map((key) => `${key}=${Array.isArray(params[key]) ? params[key].join(',') : params[key]}`)
    .join('');

  const digest = createHmac('sha256', secret).update(message).digest('hex');
  try {
    return timingSafeEqual(Buffer.from(digest, 'utf8'), Buffer.from(String(signature), 'utf8'));
  } catch {
    return false;
  }
}

export function metafieldValue(metafields, key) {
  const nodes = metafields?.nodes || metafields || [];
  const hit = nodes.find((m) => m.namespace === NS && m.key === key);
  return hit?.value ?? null;
}

export function parseJson(value, fallback) {
  if (value == null || value === '') return fallback;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

export function stageIndex(stage) {
  const idx = STAGES.indexOf(stage);
  return idx >= 0 ? idx : 0;
}

export async function setOrderStage(orderGid, stage) {
  if (!STAGES.includes(stage)) {
    throw new Error(`Invalid stage: ${stage}`);
  }
  const data = await adminGql(
    `mutation SetStage($metafields: [MetafieldsSetInput!]!) {
      metafieldsSet(metafields: $metafields) {
        metafields { id key value }
        userErrors { field message }
      }
    }`,
    {
      metafields: [
        {
          ownerId: orderGid,
          namespace: NS,
          key: 'lab_stage',
          type: 'single_line_text_field',
          value: stage,
        },
      ],
    }
  );
  const errors = data?.metafieldsSet?.userErrors || [];
  if (errors.length) throw new Error(errors.map((e) => e.message).join('; '));
  return data.metafieldsSet.metafields;
}

export async function setCustomerCoaProfiles(customerGid, profiles) {
  const data = await adminGql(
    `mutation SetProfiles($metafields: [MetafieldsSetInput!]!) {
      metafieldsSet(metafields: $metafields) {
        metafields { id }
        userErrors { field message }
      }
    }`,
    {
      metafields: [
        {
          ownerId: customerGid,
          namespace: NS,
          key: 'coa_profiles',
          type: 'json',
          value: JSON.stringify(profiles),
        },
      ],
    }
  );
  const errors = data?.metafieldsSet?.userErrors || [];
  if (errors.length) throw new Error(errors.map((e) => e.message).join('; '));
  return profiles;
}

export function certificatePublicUrl(handleOrId) {
  const { publicOrigin, shop } = loadEnv();
  const origin = publicOrigin || (shop ? `https://${shop}` : '');
  if (String(handleOrId).startsWith('http')) return handleOrId;
  return `${origin}/pages/certificates/${encodeURIComponent(handleOrId)}`;
}

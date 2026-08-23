#!/usr/bin/env node
/**
 * Upload generated test product images from scripts/generated_test_images into the
 * March Analytics preview store. Matches images to products by title using the
 * same filename rules as scripts/ftp-image-gen.py.
 *
 * Auth: SHOPIFY_ADMIN_TOKEN in seed/.env, or Shopify CLI store session.
 *
 * Usage:
 *   node upload-product-images.mjs
 *   node upload-product-images.mjs --dry-run
 *
 * Required scopes: read_products, write_products
 */
import { execFile } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const DIR = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = join(DIR, '..', '..');
const IMAGES_DIR = join(REPO_ROOT, 'scripts', 'generated_test_images');
const API_VERSION = '2026-04';
const DEFAULT_STORE = 'srgkrj-ij.myshopify.com';
const DRY_RUN = process.argv.includes('--dry-run');

function loadEnv(path) {
  if (!existsSync(path)) return;
  for (const line of readFileSync(path, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq < 1) continue;
    const key = trimmed.slice(0, eq).trim();
    let value = trimmed.slice(eq + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    if (!process.env[key]) process.env[key] = value;
  }
}

loadEnv(join(DIR, '.env'));
loadEnv(join(DIR, '..', '.env'));

const STORE = (process.env.SHOPIFY_STORE || DEFAULT_STORE).replace(/^https?:\/\//, '').replace(/\/$/, '');
const TOKEN = process.env.SHOPIFY_ADMIN_TOKEN || process.env.SHOPIFY_ACCESS_TOKEN || '';

const PRODUCTS_BY_HANDLE = `query ProductsByHandle($query: String!) {
  products(first: 1, query: $query) {
    nodes {
      id
      handle
      title
      media(first: 20) {
        nodes {
          id
          mediaContentType
        }
      }
    }
  }
}`;

const STAGED = `mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
  stagedUploadsCreate(input: $input) {
    stagedTargets { url resourceUrl parameters { name value } }
    userErrors { field message }
  }
}`;

const DELETE_MEDIA = `mutation productDeleteMedia($mediaIds: [ID!]!, $productId: ID!) {
  productDeleteMedia(mediaIds: $mediaIds, productId: $productId) {
    mediaUserErrors { field message }
  }
}`;

const CREATE_MEDIA = `mutation productCreateMedia($media: [CreateMediaInput!]!, $productId: ID!) {
  productCreateMedia(media: $media, productId: $productId) {
    media { id status alt }
    mediaUserErrors { field message }
    product { id handle }
  }
}`;

function userErrors(payload, path, key = 'userErrors') {
  const errors = payload?.[key] || [];
  if (errors.length) {
    const text = errors.map((err) => `${(err.field || []).join('.')}: ${err.message}`).join('; ');
    throw new Error(`${path}: ${text}`);
  }
}

async function gqlHttp(query, variables) {
  const res = await fetch(`https://${STORE}/admin/api/${API_VERSION}/graphql.json`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Shopify-Access-Token': TOKEN,
    },
    body: JSON.stringify({ query, variables }),
  });
  const json = await res.json();
  if (!res.ok || json.errors) {
    throw new Error(`GraphQL HTTP ${res.status}: ${JSON.stringify(json.errors || json)}`);
  }
  return json.data;
}

async function gqlCli(query, variables) {
  const dir = mkdtempSync(join(tmpdir(), 'fpt-images-'));
  const queryFile = join(dir, 'query.graphql');
  const outFile = join(dir, 'out.json');
  writeFileSync(queryFile, query);
  const args = [
    'shopify',
    'store',
    'execute',
    '--store',
    STORE,
    '--query-file',
    queryFile,
    '--output-file',
    outFile,
    '--json',
    '--no-color',
    '--version',
    API_VERSION,
  ];
  if (variables && Object.keys(variables).length) {
    const variableFile = join(dir, 'variables.json');
    writeFileSync(variableFile, JSON.stringify(variables));
    args.push('--variable-file', variableFile);
  }
  if (/\bmutation\b/.test(query)) args.push('--allow-mutations');
  const env = {
    ...process.env,
    SHOPIFY_CLI_AGENT_INFO: 'n:cursor|v:none|p:none|m:none',
    SHOPIFY_FLAG_NO_COLOR: '1',
  };
  try {
    await execFileAsync('npx', args, {
      cwd: join(DIR, '..'),
      env,
      maxBuffer: 20 * 1024 * 1024,
    });
    const json = JSON.parse(readFileSync(outFile, 'utf8'));
    if (json.errors) {
      throw new Error(`GraphQL CLI: ${JSON.stringify(json.errors)}`);
    }
    return json.data || json;
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

const gql = TOKEN ? gqlHttp : gqlCli;

function imageFilenameForTitle(title) {
  const normalized = title
    .trim()
    .toUpperCase()
    .replace(/[^A-Za-z0-9 ]+/g, '')
    .trim();
  return `${normalized.replace(/\s+/g, '_').toLowerCase()}.jpg`;
}

async function getProductByHandle(handle) {
  const data = await gql(PRODUCTS_BY_HANDLE, { query: `handle:${handle}` });
  return data.products?.nodes?.[0] || null;
}

async function deleteExistingImages(product) {
  const mediaIds = (product.media?.nodes || [])
    .filter((node) => node.mediaContentType === 'IMAGE')
    .map((node) => node.id);
  if (mediaIds.length === 0) return;
  if (DRY_RUN) {
    console.log(`  would delete ${mediaIds.length} existing image(s)`);
    return;
  }
  const data = await gql(DELETE_MEDIA, { productId: product.id, mediaIds });
  userErrors(data.productDeleteMedia, `productDeleteMedia ${product.handle}`, 'mediaUserErrors');
}

async function uploadProductImage(product, imagePath, filename) {
  const buffer = readFileSync(imagePath);
  if (DRY_RUN) {
    console.log(`  would upload ${filename} (${buffer.length} bytes)`);
    return;
  }

  const staged = await gql(STAGED, {
    input: [
      {
        filename,
        mimeType: 'image/jpeg',
        resource: 'PRODUCT_IMAGE',
        fileSize: String(buffer.length),
        httpMethod: 'POST',
      },
    ],
  });
  userErrors(staged.stagedUploadsCreate, 'stagedUploadsCreate');
  const target = staged.stagedUploadsCreate.stagedTargets[0];

  const form = new FormData();
  for (const param of target.parameters) {
    form.append(param.name, param.value);
  }
  form.append('file', new Blob([buffer], { type: 'image/jpeg' }), filename);

  const uploadRes = await fetch(target.url, { method: 'POST', body: form });
  if (!uploadRes.ok) {
    throw new Error(`Upload failed for ${filename}: ${uploadRes.status} ${await uploadRes.text()}`);
  }

  const created = await gql(CREATE_MEDIA, {
    productId: product.id,
    media: [
      {
        alt: product.title,
        mediaContentType: 'IMAGE',
        originalSource: target.resourceUrl,
      },
    ],
  });
  userErrors(created.productCreateMedia, `productCreateMedia ${product.handle}`, 'mediaUserErrors');
}

async function main() {
  if (!existsSync(IMAGES_DIR)) {
    throw new Error(`Missing image directory: ${IMAGES_DIR}`);
  }

  const catalog = JSON.parse(readFileSync(join(DIR, 'catalog.json'), 'utf8'));
  console.log(
    `${DRY_RUN ? 'Dry run' : 'Uploading'} product images to ${STORE} via ${TOKEN ? 'Admin token' : 'Shopify CLI'}`,
  );

  let uploaded = 0;
  let skipped = 0;

  for (const product of catalog.products) {
    const filename = imageFilenameForTitle(product.title);
    const imagePath = join(IMAGES_DIR, filename);
    if (!existsSync(imagePath)) {
      console.warn(`skip ${product.handle}: missing ${filename}`);
      skipped += 1;
      continue;
    }

    const existing = await getProductByHandle(product.handle);
    if (!existing) {
      console.warn(`skip ${product.handle}: product not found in store`);
      skipped += 1;
      continue;
    }

    console.log(`${product.handle} <- ${filename}`);
    await deleteExistingImages(existing);
    await uploadProductImage(existing, imagePath, filename);
    uploaded += 1;
  }

  console.log(`Done. ${uploaded} uploaded, ${skipped} skipped.`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});

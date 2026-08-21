#!/usr/bin/env node
/**
 * Idempotent upsert of catalog.json into the March Analytics preview store.
 *
 * Auth: SHOPIFY_ADMIN_TOKEN in seed/.env, or Shopify CLI store session.
 *
 * Usage:
 *   node import.mjs
 *
 * Required scopes: write_products, write_content, write_online_store_pages,
 * write_online_store_navigation, write_publications. Products are published
 * to the Online Store channel after upsert.
 */
import { execFile } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const DIR = dirname(fileURLToPath(import.meta.url));
const API_VERSION = '2026-04';
const DEFAULT_STORE = 'srgkrj-ij.myshopify.com';

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

const PRODUCT_SET = `mutation ProductSet($identifier: ProductSetIdentifiers, $input: ProductSetInput!) {
  productSet(identifier: $identifier, input: $input) {
    product { id handle title }
    userErrors { field message }
  }
}`;

const COLLECTION_CREATE = `mutation CollectionCreate($input: CollectionInput!) {
  collectionCreate(input: $input) {
    collection { id handle }
    userErrors { field message }
  }
}`;

const COLLECTION_UPDATE = `mutation CollectionUpdate($input: CollectionInput!) {
  collectionUpdate(input: $input) {
    collection { id handle }
    userErrors { field message }
  }
}`;

const COLLECTION_ADD = `mutation CollectionAddProducts($id: ID!, $productIds: [ID!]!) {
  collectionAddProducts(id: $id, productIds: $productIds) {
    collection { id }
    userErrors { field message }
  }
}`;

const PAGE_CREATE = `mutation PageCreate($page: PageCreateInput!) {
  pageCreate(page: $page) {
    page { id handle }
    userErrors { field message }
  }
}`;

const PAGE_UPDATE = `mutation PageUpdate($id: ID!, $page: PageUpdateInput!) {
  pageUpdate(id: $id, page: $page) {
    page { id handle }
    userErrors { field message }
  }
}`;

const MENU_UPDATE = `mutation MenuUpdate($id: ID!, $title: String!, $items: [MenuItemUpdateInput!]!) {
  menuUpdate(id: $id, title: $title, items: $items) {
    menu { id handle }
    userErrors { field message }
  }
}`;

const MENU_CREATE = `mutation MenuCreate($title: String!, $handle: String!, $items: [MenuItemCreateInput!]!) {
  menuCreate(title: $title, handle: $handle, items: $items) {
    menu { id handle }
    userErrors { field message }
  }
}`;

const LOOKUPS = `query SeedLookups($collectionQuery: String!) {
  collections(first: 5, query: $collectionQuery) {
    nodes { id handle }
  }
  pages(first: 50) {
    nodes { id handle }
  }
  menus(first: 20) {
    nodes { id handle title }
  }
}`;

const PUBLICATIONS = `query {
  publications(first: 20) {
    nodes { id name }
  }
}`;

const PUBLICATION_UPDATE = `mutation PublicationUpdate($id: ID!, $input: PublicationUpdateInput!) {
  publicationUpdate(id: $id, input: $input) {
    userErrors { field message }
  }
}`;

const PUBLISHABLE_PUBLISH = `mutation PublishablePublish($id: ID!, $input: [PublicationInput!]!) {
  publishablePublish(id: $id, input: $input) {
    userErrors { field message }
  }
}`;

function userErrors(payload, path) {
  const errors = payload?.userErrors || [];
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
  const dir = mkdtempSync(join(tmpdir(), 'fpt-seed-'));
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
      maxBuffer: 10 * 1024 * 1024,
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

function productInput(product) {
  const multi = Boolean(product.option_name);
  const optionName = multi ? product.option_name : 'Title';
  const input = {
    title: product.title,
    handle: product.handle,
    descriptionHtml: product.description_html,
    vendor: product.vendor,
    productType: product.product_type,
    status: product.status,
    tags: product.tags,
    variants: product.variants.map((variant) => ({
      optionValues: [
        {
          optionName,
          name: multi ? variant.option_value : 'Default Title',
        },
      ],
      price: variant.price,
      sku: variant.sku,
      inventoryPolicy: 'CONTINUE',
      inventoryItem: {
        sku: variant.sku,
        tracked: false,
        requiresShipping: false,
      },
    })),
    productOptions: [
      {
        name: optionName,
        values: multi
          ? [...new Set(product.variants.map((variant) => variant.option_value))].map((name) => ({ name }))
          : [{ name: 'Default Title' }],
      },
    ],
  };
  if (product.seo_title || product.seo_description) {
    input.seo = {
      title: product.seo_title || null,
      description: product.seo_description || null,
    };
  }
  return input;
}

function menuItems(spec, ids) {
  return spec.map((item) => {
    if (item.type === 'COLLECTION') {
      return { title: item.title, type: 'COLLECTION', resourceId: ids.collections[item.handle] };
    }
    if (item.type === 'PAGE') {
      return { title: item.title, type: 'PAGE', resourceId: ids.pages[item.handle] };
    }
    return { title: item.title, type: 'HTTP', url: item.url || '/' };
  });
}

async function upsertProduct(product) {
  const data = await gql(PRODUCT_SET, {
    identifier: { handle: product.handle },
    input: productInput(product),
  });
  userErrors(data.productSet, `productSet ${product.handle}`);
  return data.productSet.product;
}

async function upsertCollection(spec, productIds) {
  const lookups = await gql(LOOKUPS, { collectionQuery: `handle:${spec.handle}` });
  const existing = lookups.collections.nodes.find((node) => node.handle === spec.handle);
  if (!existing) {
    const created = await gql(COLLECTION_CREATE, {
      input: {
        title: spec.title,
        handle: spec.handle,
        descriptionHtml: spec.body_html,
        products: productIds,
      },
    });
    userErrors(created.collectionCreate, 'collectionCreate');
    return created.collectionCreate.collection;
  }
  const updated = await gql(COLLECTION_UPDATE, {
    input: {
      id: existing.id,
      title: spec.title,
      descriptionHtml: spec.body_html,
    },
  });
  userErrors(updated.collectionUpdate, 'collectionUpdate');
  const added = await gql(COLLECTION_ADD, { id: existing.id, productIds });
  const addErrors = added.collectionAddProducts?.userErrors || [];
  const unexpected = addErrors.filter((err) => !/already/i.test(err.message));
  if (unexpected.length) {
    throw new Error(`collectionAddProducts: ${unexpected.map((err) => err.message).join('; ')}`);
  }
  return updated.collectionUpdate.collection;
}

async function upsertPages(pages) {
  const lookups = await gql(LOOKUPS, { collectionQuery: 'handle:order-testing' });
  const byHandle = Object.fromEntries((lookups.pages.nodes || []).map((page) => [page.handle, page]));
  const ids = {};
  for (const page of pages) {
    const input = {
      title: page.title,
      handle: page.handle,
      body: page.body_html,
      isPublished: true,
      templateSuffix: page.template_suffix || null,
    };
    if (page.seo_title || page.seo_description) {
      input.seo = {
        title: page.seo_title || null,
        description: page.seo_description || null,
      };
    }
    if (byHandle[page.handle]) {
      const updated = await gql(PAGE_UPDATE, { id: byHandle[page.handle].id, page: input });
      userErrors(updated.pageUpdate, `pageUpdate ${page.handle}`);
      ids[page.handle] = updated.pageUpdate.page.id;
    } else {
      const created = await gql(PAGE_CREATE, { page: input });
      userErrors(created.pageCreate, `pageCreate ${page.handle}`);
      ids[page.handle] = created.pageCreate.page.id;
    }
  }
  return ids;
}

async function publishOnlineStore(productIds, collectionId) {
  const data = await gql(PUBLICATIONS);
  const publication = (data.publications?.nodes || []).find((node) => node.name === 'Online Store');
  if (!publication) {
    throw new Error('Online Store publication not found. Products would stay admin-only.');
  }
  const products = await gql(PUBLICATION_UPDATE, {
    id: publication.id,
    input: { publishablesToAdd: productIds },
  });
  userErrors(products.publicationUpdate, 'publicationUpdate');
  const collection = await gql(PUBLISHABLE_PUBLISH, {
    id: collectionId,
    input: [{ publicationId: publication.id }],
  });
  userErrors(collection.publishablePublish, 'publishablePublish collection');
}

async function upsertMenu(handle, title, items) {
  const lookups = await gql(LOOKUPS, { collectionQuery: 'handle:order-testing' });
  const existing = (lookups.menus.nodes || []).find((menu) => menu.handle === handle);
  if (existing) {
    const updated = await gql(MENU_UPDATE, { id: existing.id, title, items });
    userErrors(updated.menuUpdate, `menuUpdate ${handle}`);
    return updated.menuUpdate.menu;
  }
  const created = await gql(MENU_CREATE, { title, handle, items });
  userErrors(created.menuCreate, `menuCreate ${handle}`);
  return created.menuCreate.menu;
}

async function main() {
  const catalog = JSON.parse(readFileSync(join(DIR, 'catalog.json'), 'utf8'));
  console.log(`Importing ${catalog.products.length} products into ${STORE} via ${TOKEN ? 'Admin token' : 'Shopify CLI'}`);

  const productIds = [];
  for (const product of catalog.products) {
    const saved = await upsertProduct(product);
    productIds.push(saved.id);
    console.log(`product ${saved.handle}`);
  }

  const collection = await upsertCollection(catalog.collection, productIds);
  console.log(`collection ${collection.handle}`);

  await publishOnlineStore(productIds, collection.id);
  console.log('published to Online Store');

  const pageIds = await upsertPages(catalog.pages);
  console.log(`pages ${Object.keys(pageIds).length}`);

  const ids = {
    collections: { [catalog.collection.handle]: collection.id },
    pages: pageIds,
  };
  await upsertMenu('main-menu', 'Main menu', menuItems(catalog.menus.main, ids));
  await upsertMenu('footer', 'Footer menu', menuItems(catalog.menus.footer, ids));
  console.log('menus main-menu, footer');

  console.log('Import complete.');
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});

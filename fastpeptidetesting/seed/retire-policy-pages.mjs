#!/usr/bin/env node
/**
 * Retire placeholder policy pages that duplicate Shopify Settings → Policies.
 *
 * Deletes /pages/terms, /pages/privacy, /pages/refund-policy and creates
 * redirects to the matching /policies/... URLs.
 *
 * Usage:
 *   node retire-policy-pages.mjs
 *   node retire-policy-pages.mjs --cli
 *
 * Auth: SHOPIFY_ADMIN_TOKEN in seed/.env, or Shopify CLI store session (--cli).
 * Required scopes: write_online_store_pages, write_online_store_navigation
 *   (urlRedirectCreate needs write_online_store_navigation or write_content).
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

const RETIRE = [
  { handle: 'terms', path: '/pages/terms', target: '/policies/terms-of-service' },
  { handle: 'privacy', path: '/pages/privacy', target: '/policies/privacy-policy' },
  { handle: 'refund-policy', path: '/pages/refund-policy', target: '/policies/refund-policy' },
];

function loadEnv(path) {
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

loadEnv(join(DIR, '.env'));

const USE_CLI =
  process.argv.includes('--cli') ||
  process.env.SHOPIFY_USE_CLI === '1' ||
  process.env.SHOPIFY_USE_CLI === 'true';

const STORE = (process.env.SHOPIFY_STORE || 'srgkrj-ij.myshopify.com')
  .replace(/^https?:\/\//, '')
  .replace(/\/$/, '');
const TOKEN = USE_CLI ? '' : process.env.SHOPIFY_ADMIN_TOKEN || '';

if (!TOKEN && !USE_CLI) {
  console.error('Missing SHOPIFY_ADMIN_TOKEN in seed/.env (or pass --cli)');
  process.exit(1);
}

async function gqlHttp(query, variables = {}) {
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
    throw new Error(JSON.stringify(json.errors || json, null, 2));
  }
  return json.data;
}

async function gqlCli(query, variables = {}) {
  const dir = mkdtempSync(join(tmpdir(), 'fpt-retire-'));
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
  try {
    await execFileAsync('npx', args, {
      cwd: join(DIR, '..'),
      env: {
        ...process.env,
        SHOPIFY_CLI_AGENT_INFO: 'n:cursor|v:none|p:none|m:none',
        SHOPIFY_FLAG_NO_COLOR: '1',
      },
      maxBuffer: 10 * 1024 * 1024,
    });
    const json = JSON.parse(readFileSync(outFile, 'utf8'));
    if (json.errors) throw new Error(JSON.stringify(json.errors, null, 2));
    return json.data || json;
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

const gql = TOKEN ? gqlHttp : gqlCli;

function assertOk(payload, label) {
  const errors = payload?.userErrors || [];
  if (errors.length) {
    throw new Error(`${label}: ${errors.map((e) => e.message).join('; ')}`);
  }
}

async function findPage(handle) {
  const data = await gql(
    `query ($q: String!) {
      pages(first: 5, query: $q) { nodes { id handle } }
    }`,
    { q: `handle:${handle}` }
  );
  return data.pages.nodes.find((p) => p.handle === handle) || null;
}

async function deletePage(id, handle) {
  const result = await gql(
    `mutation ($id: ID!) {
      pageDelete(id: $id) {
        deletedPageId
        userErrors { field message }
      }
    }`,
    { id }
  );
  assertOk(result.pageDelete, `pageDelete:${handle}`);
  console.log(`Deleted page: /pages/${handle}`);
}

async function findRedirect(path) {
  const data = await gql(
    `query ($q: String!) {
      urlRedirects(first: 5, query: $q) {
        nodes { id path target }
      }
    }`,
    { q: `path:${path}` }
  );
  return (data.urlRedirects?.nodes || []).find((r) => r.path === path) || null;
}

async function ensureRedirect(path, target) {
  const existing = await findRedirect(path);
  if (existing) {
    if (existing.target === target) {
      console.log(`Redirect exists: ${path} → ${target}`);
      return;
    }
    const updated = await gql(
      `mutation ($id: ID!, $urlRedirect: UrlRedirectInput!) {
        urlRedirectUpdate(id: $id, urlRedirect: $urlRedirect) {
          urlRedirect { id path target }
          userErrors { field message }
        }
      }`,
      { id: existing.id, urlRedirect: { path, target } }
    );
    assertOk(updated.urlRedirectUpdate, `urlRedirectUpdate:${path}`);
    console.log(`Updated redirect: ${path} → ${target}`);
    return;
  }

  const created = await gql(
    `mutation ($urlRedirect: UrlRedirectInput!) {
      urlRedirectCreate(urlRedirect: $urlRedirect) {
        urlRedirect { id path target }
        userErrors { field message }
      }
    }`,
    { urlRedirect: { path, target } }
  );
  assertOk(created.urlRedirectCreate, `urlRedirectCreate:${path}`);
  console.log(`Created redirect: ${path} → ${target}`);
}

async function main() {
  console.log(`Store: ${STORE} via ${TOKEN ? 'Admin token' : 'Shopify CLI'}`);

  for (const item of RETIRE) {
    const page = await findPage(item.handle);
    if (page) {
      await deletePage(page.id, item.handle);
    } else {
      console.log(`Page already gone: /pages/${item.handle}`);
    }
    await ensureRedirect(item.path, item.target);
  }

  console.log(`
Done.
${RETIRE.map((r) => `${r.path} → ${r.target}`).join('\n')}
`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

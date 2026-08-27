#!/usr/bin/env node
/**
 * Create portal metafield definitions + extend certificate metaobject for order stages,
 * COA branding profiles, and QR/verify fields.
 *
 * Namespace: march_analytics
 *
 * Order metafields:
 *   lab_stage — Submitted | Sample Received | Analyzing | Under Review | Complete
 *   packing_notes — free text for packing slip
 *   certificate_ids — list of cert IDs linked to this order
 *
 * Customer metafields:
 *   coa_profiles — JSON array of {id, company, website, email, phone, address}
 *
 * Certificate metaobject additions (if definition exists):
 *   order_id, lot_code, verification_code, qr_target_url
 *
 * Usage:
 *   cd fastpeptidetesting/seed && node setup-portal-metafields.mjs
 *   cd fastpeptidetesting/seed && node setup-portal-metafields.mjs --cli
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
const NS = 'march_analytics';

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
loadEnv(join(DIR, '..', '.env'));

const USE_CLI =
  process.argv.includes('--cli') ||
  process.env.SHOPIFY_USE_CLI === '1' ||
  process.env.SHOPIFY_USE_CLI === 'true';

const STORE = (process.env.SHOPIFY_STORE || DEFAULT_STORE)
  .replace(/^https?:\/\//, '')
  .replace(/\/$/, '');
const TOKEN = USE_CLI ? '' : process.env.SHOPIFY_ADMIN_TOKEN || process.env.SHOPIFY_ACCESS_TOKEN || '';

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
  const dir = mkdtempSync(join(tmpdir(), 'fpt-portal-meta-'));
  const queryFile = join(dir, 'query.graphql');
  const outFile = join(dir, 'out.json');
  writeFileSync(queryFile, query);
  try {
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
    await execFileAsync('npx', args, {
      cwd: join(DIR, '..'),
      env: {
        ...process.env,
        SHOPIFY_CLI_AGENT_INFO: 'n:cursor|v:none|p:none|m:none',
        SHOPIFY_FLAG_NO_COLOR: '1',
      },
      maxBuffer: 10 * 1024 * 1024,
    });
    return JSON.parse(readFileSync(outFile, 'utf8')).data;
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

async function gql(query, variables = {}) {
  if (TOKEN) return gqlHttp(query, variables);
  return gqlCli(query, variables);
}

function assertNoUserErrors(payload, label) {
  const errors = payload?.userErrors || [];
  if (errors.length) {
    throw new Error(
      `${label}: ${errors.map((e) => `${(e.field || []).join('.')}: ${e.message}`).join('; ')}`
    );
  }
}

const CREATE_METAFIELD_DEF = `mutation CreateMetafieldDef($definition: MetafieldDefinitionInput!) {
  metafieldDefinitionCreate(definition: $definition) {
    createdDefinition { id namespace key name }
    userErrors { field message code }
  }
}`;

const LIST_METAFIELD_DEFS = `query ListDefs($ownerType: MetafieldOwnerType!, $namespace: String!) {
  metafieldDefinitions(first: 50, ownerType: $ownerType, namespace: $namespace) {
    nodes { id namespace key name }
  }
}`;

const DEF_BY_TYPE = `query DefByType($type: String!) {
  metaobjectDefinitionByType(type: $type) {
    id
    type
    fieldDefinitions { key name }
  }
}`;

const METAOBJECT_FIELD_CREATE = `mutation AddField($definitionId: ID!, $definition: MetaobjectFieldDefinitionCreateInput!) {
  metaobjectDefinitionUpdate(
    id: $definitionId
    definition: { fieldDefinitions: [{ create: $definition }] }
  ) {
    metaobjectDefinition { id }
    userErrors { field message code }
  }
}`;

const ORDER_FIELDS = [
  {
    name: 'Lab stage',
    key: 'lab_stage',
    type: 'single_line_text_field',
    description:
      'Order pipeline stage: Order Submitted | Sample Received | Analyzing | Under Review | Complete',
  },
  {
    name: 'Packing notes',
    key: 'packing_notes',
    type: 'multi_line_text_field',
    description: 'Notes printed on the packing slip',
  },
  {
    name: 'Certificate IDs',
    key: 'certificate_ids',
    type: 'list.single_line_text_field',
    description: 'Public certificate IDs linked to this order',
  },
  {
    name: 'Vials required',
    key: 'vials_required',
    type: 'number_integer',
    description: 'How many vials the customer should ship',
  },
];

const CUSTOMER_FIELDS = [
  {
    name: 'COA profiles',
    key: 'coa_profiles',
    type: 'json',
    description: 'Saved COA branding profiles JSON array',
  },
];

const CERT_EXTRA_FIELDS = [
  { key: 'order_id', name: 'Shopify order ID', type: 'single_line_text_field' },
  { key: 'lot_code', name: 'Lot code', type: 'single_line_text_field' },
  { key: 'verification_code', name: 'Verification code', type: 'single_line_text_field' },
  { key: 'qr_target_url', name: 'QR target URL', type: 'url' },
];

async function ensureMetafieldDefs(ownerType, fields) {
  const existing = await gql(LIST_METAFIELD_DEFS, { ownerType, namespace: NS });
  const have = new Set((existing?.metafieldDefinitions?.nodes || []).map((n) => n.key));

  for (const field of fields) {
    if (have.has(field.key)) {
      console.log(`Exists: ${ownerType}.${NS}.${field.key}`);
      continue;
    }
    const created = await gql(CREATE_METAFIELD_DEF, {
      definition: {
        name: field.name,
        namespace: NS,
        key: field.key,
        description: field.description || '',
        type: field.type,
        ownerType,
        access: {
          storefront: ownerType === 'CUSTOMER' ? 'PUBLIC_READ' : 'PUBLIC_READ',
        },
      },
    });
    assertNoUserErrors(created?.metafieldDefinitionCreate, `metafield:${ownerType}.${field.key}`);
    console.log(`Created: ${ownerType}.${NS}.${field.key}`);
  }
}

async function extendCertificateMetaobject() {
  const existing = await gql(DEF_BY_TYPE, { type: 'certificate' });
  const def = existing?.metaobjectDefinitionByType;
  if (!def?.id) {
    console.log('Skip certificate field extensions (run setup-metaobjects.mjs first)');
    return;
  }
  const have = new Set((def.fieldDefinitions || []).map((f) => f.key));
  for (const field of CERT_EXTRA_FIELDS) {
    if (have.has(field.key)) {
      console.log(`Exists: certificate.${field.key}`);
      continue;
    }
    const updated = await gql(METAOBJECT_FIELD_CREATE, {
      definitionId: def.id,
      definition: {
        key: field.key,
        name: field.name,
        type: field.type,
        required: false,
      },
    });
    assertNoUserErrors(updated?.metaobjectDefinitionUpdate, `certificate.${field.key}`);
    console.log(`Added: certificate.${field.key}`);
  }
}

async function main() {
  console.log(`Store: ${STORE}`);
  console.log(TOKEN ? 'Auth: Admin token' : 'Auth: Shopify CLI session');

  await ensureMetafieldDefs('ORDER', ORDER_FIELDS);
  await ensureMetafieldDefs('CUSTOMER', CUSTOMER_FIELDS);
  await extendCertificateMetaobject();

  // Persist a machine-readable schema copy for the portal app
  const schemaPath = join(DIR, '..', 'portal-app', 'metafield-schema.json');
  writeFileSync(
    schemaPath,
    JSON.stringify(
      {
        namespace: NS,
        stages: [
          'Order Submitted',
          'Sample Received',
          'Analyzing',
          'Under Review',
          'Complete',
        ],
        order: ORDER_FIELDS,
        customer: CUSTOMER_FIELDS,
        certificate_extra: CERT_EXTRA_FIELDS,
      },
      null,
      2
    )
  );
  console.log(`Wrote ${schemaPath}`);

  console.log(`
Next:
1. Portal app reads order.metafields.${NS}.lab_stage
2. Customer COA profiles: customer.metafields.${NS}.coa_profiles
3. Default new paid orders to "Order Submitted" via Flow or portal-app webhook
`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

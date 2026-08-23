#!/usr/bin/env node
/**
 * Create merchant-owned metaobject definitions for SEO certificate + compound pages.
 *
 * Scopes: write_metaobject_definitions, read_metaobject_definitions
 *
 * Usage:
 *   cd fastpeptidetesting/seed && node setup-metaobjects.mjs
 *
 * Public URLs:
 *   /pages/certificates/{handle}
 *   /pages/compounds/{handle}
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

const STORE = (process.env.SHOPIFY_STORE || DEFAULT_STORE)
  .replace(/^https?:\/\//, '')
  .replace(/\/$/, '');
const TOKEN = process.env.SHOPIFY_ADMIN_TOKEN || process.env.SHOPIFY_ACCESS_TOKEN || '';

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
  const dir = mkdtempSync(join(tmpdir(), 'fpt-meta-'));
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
    await execFileAsync('npx', args, { maxBuffer: 10 * 1024 * 1024 });
    return JSON.parse(readFileSync(outFile, 'utf8')).data;
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

async function gql(query, variables = {}) {
  if (TOKEN) return gqlHttp(query, variables);
  return gqlCli(query, variables);
}

const DEF_BY_TYPE = `query DefByType($type: String!) {
  metaobjectDefinitionByType(type: $type) {
    id
    type
    name
  }
}`;

const CREATE_DEF = `mutation CreateDef($definition: MetaobjectDefinitionCreateInput!) {
  metaobjectDefinitionCreate(definition: $definition) {
    metaobjectDefinition { id type name }
    userErrors { field message code }
  }
}`;

function assertNoUserErrors(payload, label) {
  const errors = payload?.userErrors || [];
  if (errors.length) {
    throw new Error(
      `${label}: ${errors.map((e) => `${(e.field || []).join('.')}: ${e.message}`).join('; ')}`
    );
  }
}

const CAPABILITIES = {
  publishable: { enabled: true },
  renderable: {
    enabled: true,
    data: { metaTitleKey: 'seo_title', metaDescriptionKey: 'seo_description' },
  },
};

function compoundInput() {
  return {
    name: 'Compound',
    type: 'compound',
    description: 'Analytical compound reference pages for SEO and test linking.',
    access: { storefront: 'PUBLIC_READ' },
    displayNameKey: 'name',
    capabilities: {
      ...CAPABILITIES,
      onlineStore: { enabled: true, data: { urlHandle: 'compounds' } },
    },
    fieldDefinitions: [
      { key: 'name', name: 'Name', type: 'single_line_text_field', required: true },
      { key: 'seo_title', name: 'SEO title', type: 'single_line_text_field', required: true },
      {
        key: 'seo_description',
        name: 'SEO description',
        type: 'multi_line_text_field',
        required: true,
      },
      { key: 'sequence', name: 'Sequence', type: 'multi_line_text_field' },
      { key: 'molecular_formula', name: 'Molecular formula', type: 'single_line_text_field' },
      { key: 'monoisotopic_mass', name: 'Monoisotopic mass', type: 'number_decimal' },
      { key: 'average_mass', name: 'Average mass', type: 'number_decimal' },
      { key: 'detection_wavelength', name: 'Detection wavelength (nm)', type: 'number_integer' },
      {
        key: 'typical_sample_amount_mg',
        name: 'Typical sample amount (mg)',
        type: 'number_decimal',
      },
      {
        key: 'common_synthesis_impurities',
        name: 'Common synthesis impurities',
        type: 'rich_text_field',
      },
      { key: 'linked_tests', name: 'Linked tests', type: 'list.product_reference' },
    ],
  };
}

function certificateInput(compoundDefinitionId) {
  return {
    name: 'Certificate',
    type: 'certificate',
    description: 'Public certificate of analysis pages vendors can link to.',
    access: { storefront: 'PUBLIC_READ' },
    displayNameKey: 'cert_id',
    capabilities: {
      ...CAPABILITIES,
      onlineStore: { enabled: true, data: { urlHandle: 'certificates' } },
    },
    fieldDefinitions: [
      { key: 'cert_id', name: 'Certificate ID', type: 'single_line_text_field', required: true },
      { key: 'seo_title', name: 'SEO title', type: 'single_line_text_field', required: true },
      {
        key: 'seo_description',
        name: 'SEO description',
        type: 'multi_line_text_field',
        required: true,
      },
      {
        key: 'compound',
        name: 'Compound',
        type: 'metaobject_reference',
        required: true,
        validations: [{ name: 'metaobject_definition_id', value: compoundDefinitionId }],
      },
      { key: 'sample_received', name: 'Sample received', type: 'date' },
      { key: 'reported', name: 'Reported', type: 'date' },
      { key: 'hplc_purity', name: 'HPLC purity (%)', type: 'number_decimal', required: true },
      { key: 'observed_mass', name: 'Observed mass', type: 'number_decimal' },
      { key: 'expected_mass', name: 'Expected mass', type: 'number_decimal' },
      { key: 'method_summary', name: 'Method summary', type: 'single_line_text_field' },
      { key: 'chromatogram', name: 'Chromatogram', type: 'file_reference' },
      { key: 'pdf', name: 'PDF certificate', type: 'file_reference' },
      {
        key: 'submitter_type',
        name: 'Submitter type',
        type: 'single_line_text_field',
        description: 'vendor | researcher | private',
      },
      { key: 'display_submitter', name: 'Display submitter', type: 'boolean' },
      { key: 'submitter_name', name: 'Submitter name', type: 'single_line_text_field' },
    ],
  };
}

async function ensureDefinition(definition) {
  const existing = await gql(DEF_BY_TYPE, { type: definition.type });
  if (existing?.metaobjectDefinitionByType?.id) {
    console.log(`Exists: ${definition.type} (${existing.metaobjectDefinitionByType.id})`);
    return existing.metaobjectDefinitionByType.id;
  }
  const created = await gql(CREATE_DEF, { definition });
  assertNoUserErrors(created?.metaobjectDefinitionCreate, `create:${definition.type}`);
  const id = created.metaobjectDefinitionCreate.metaobjectDefinition.id;
  console.log(`Created: ${definition.type} (${id})`);
  return id;
}

async function main() {
  console.log(`Store: ${STORE}`);
  console.log(TOKEN ? 'Auth: Admin token' : 'Auth: Shopify CLI session');

  const compoundId = await ensureDefinition(compoundInput());
  await ensureDefinition(certificateInput(compoundId));

  console.log(`
Next:
1. Theme already includes templates/metaobject/certificate.json and compound.json
2. Admin → Content → Metaobjects → add Active entries (handle = public URL segment)
3. Create Online Store pages: verify (template page.verify), sample-coa (template page.sample-coa)
4. URLs: /pages/certificates/{handle}, /pages/compounds/{handle}
5. Vendor badge: theme asset vendor-coa-badge.svg
`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

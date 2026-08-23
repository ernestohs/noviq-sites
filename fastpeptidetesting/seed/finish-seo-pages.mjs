#!/usr/bin/env node
/**
 * Finish Phase 6 store setup: verify + sample-coa pages, sample compound + certificate.
 * Uses SHOPIFY_ADMIN_TOKEN from seed/.env
 */
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const API_VERSION = '2026-04';

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

const STORE = (process.env.SHOPIFY_STORE || 'srgkrj-ij.myshopify.com')
  .replace(/^https?:\/\//, '')
  .replace(/\/$/, '');
const TOKEN = process.env.SHOPIFY_ADMIN_TOKEN || '';

if (!TOKEN) {
  console.error('Missing SHOPIFY_ADMIN_TOKEN in seed/.env');
  process.exit(1);
}

async function gql(query, variables = {}) {
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

function assertOk(payload, label) {
  const errors = payload?.userErrors || [];
  if (errors.length) {
    throw new Error(`${label}: ${errors.map((e) => e.message).join('; ')}`);
  }
}

async function ensurePage({ handle, title, body, templateSuffix, seoTitle, seoDescription }) {
  const existing = await gql(
    `query ($q: String!) {
      pages(first: 5, query: $q) { nodes { id handle } }
    }`,
    { q: `handle:${handle}` }
  );
  const found = existing.pages.nodes.find((p) => p.handle === handle);

  const pageInput = {
    title,
    handle,
    body,
    isPublished: true,
    templateSuffix,
  };

  let pageId;
  if (found) {
    const updated = await gql(
      `mutation ($id: ID!, $page: PageUpdateInput!) {
        pageUpdate(id: $id, page: $page) {
          page { id handle }
          userErrors { field message }
        }
      }`,
      { id: found.id, page: pageInput }
    );
    assertOk(updated.pageUpdate, `pageUpdate:${handle}`);
    pageId = updated.pageUpdate.page.id;
    console.log(`Updated page: /pages/${handle}`);
  } else {
    const created = await gql(
      `mutation ($page: PageCreateInput!) {
        pageCreate(page: $page) {
          page { id handle }
          userErrors { field message }
        }
      }`,
      { page: pageInput }
    );
    assertOk(created.pageCreate, `pageCreate:${handle}`);
    pageId = created.pageCreate.page.id;
    console.log(`Created page: /pages/${handle}`);
  }

  // Page SEO uses metafields global.title_tag / global.description_tag
  const meta = await gql(
    `mutation ($metafields: [MetafieldsSetInput!]!) {
      metafieldsSet(metafields: $metafields) {
        metafields { id key }
        userErrors { field message }
      }
    }`,
    {
      metafields: [
        {
          ownerId: pageId,
          namespace: 'global',
          key: 'title_tag',
          type: 'single_line_text_field',
          value: seoTitle,
        },
        {
          ownerId: pageId,
          namespace: 'global',
          key: 'description_tag',
          type: 'single_line_text_field',
          value: seoDescription,
        },
      ],
    }
  );
  assertOk(meta.metafieldsSet, `seo:${handle}`);
  return pageId;
}

async function upsertMetaobject(type, handle, fields) {
  const result = await gql(
    `mutation ($handle: MetaobjectHandleInput!, $metaobject: MetaobjectUpsertInput!) {
      metaobjectUpsert(handle: $handle, metaobject: $metaobject) {
        metaobject { id handle type capabilities { publishable { status } } }
        userErrors { field message code }
      }
    }`,
    {
      handle: { type, handle },
      metaobject: {
        fields,
        capabilities: {
          publishable: { status: 'ACTIVE' },
        },
      },
    }
  );
  assertOk(result.metaobjectUpsert, `metaobjectUpsert:${type}/${handle}`);
  const obj = result.metaobjectUpsert.metaobject;
  console.log(`Upserted ${type}: /pages/${type === 'certificate' ? 'certificates' : 'compounds'}/${obj.handle}`);
  return obj;
}

async function main() {
  console.log(`Store: ${STORE}`);

  await ensurePage({
    handle: 'verify',
    title: 'Verify a certificate',
    templateSuffix: 'verify',
    body: '<p>Enter a certificate ID to open the public certificate page. Vendors and researchers can confirm that a report was issued by Fast Peptide Testing.</p>',
    seoTitle: 'Verify a Certificate of Analysis | Fast Peptide Testing',
    seoDescription:
      'Look up a Fast Peptide Testing certificate of analysis by certificate ID.',
  });

  await ensurePage({
    handle: 'sample-coa',
    title: 'Sample certificate of analysis',
    templateSuffix: 'sample-coa',
    body: '<p>Example peptide certificate of analysis from Fast Peptide Testing. Shows purity by peak area, chromatogram, peak table, method summary, and verification ID.</p>',
    seoTitle: 'Sample Peptide Certificate of Analysis (PDF) | Fast Peptide Testing',
    seoDescription:
      'View a sample Fast Peptide Testing certificate of analysis with chromatogram and method summary.',
  });

  const compound = await upsertMetaobject('compound', 'bpc-157', [
    { key: 'name', value: 'BPC-157' },
    {
      key: 'seo_title',
      value: 'BPC-157 Purity Testing: HPLC Analytical Reference | Fast Peptide Testing',
    },
    {
      key: 'seo_description',
      value:
        'Analytical reference for BPC-157 peptide testing: sequence, mass, detection wavelength, and HPLC sample requirements.',
    },
    {
      key: 'sequence',
      value: 'GEPPPGKPADDAGLV',
    },
    { key: 'molecular_formula', value: 'C62H98N16O22' },
    { key: 'monoisotopic_mass', value: '1418.7' },
    { key: 'average_mass', value: '1419.5' },
    { key: 'detection_wavelength', value: '214' },
    { key: 'typical_sample_amount_mg', value: '2' },
    {
      key: 'common_synthesis_impurities',
      value: JSON.stringify({
        type: 'root',
        children: [
          {
            type: 'paragraph',
            children: [
              {
                type: 'text',
                value:
                  'Common SPPS-related impurities for this sequence class include deletion sequences, incomplete deprotection, and oxidation of susceptible residues. Report only impurities observed for the tested lot.',
              },
            ],
          },
        ],
      }),
    },
  ]);

  await upsertMetaobject('certificate', 'fpt-demo-bpc157-001', [
    { key: 'cert_id', value: 'FPT-DEMO-BPC157-001' },
    {
      key: 'seo_title',
      value: 'COA FPT-DEMO-BPC157-001: BPC-157 HPLC Purity | Fast Peptide Testing',
    },
    {
      key: 'seo_description',
      value:
        'Public certificate of analysis for BPC-157 sample FPT-DEMO-BPC157-001. HPLC purity reported as measured by Fast Peptide Testing.',
    },
    { key: 'compound', value: compound.id },
    { key: 'sample_received', value: '2026-08-01' },
    { key: 'reported', value: '2026-08-04' },
    { key: 'hplc_purity', value: '98.7' },
    { key: 'observed_mass', value: '1418.6' },
    { key: 'expected_mass', value: '1418.7' },
    { key: 'method_summary', value: 'RP-HPLC-UV at 214 nm' },
    { key: 'submitter_type', value: 'vendor' },
    { key: 'display_submitter', value: 'false' },
    { key: 'submitter_name', value: '' },
  ]);

  console.log(`
Done.
Verify:     https://${STORE}/pages/verify
Sample COA: https://${STORE}/pages/sample-coa
Compound:   https://${STORE}/pages/compounds/bpc-157
Certificate:https://${STORE}/pages/certificates/fpt-demo-bpc157-001
`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

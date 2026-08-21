#!/usr/bin/env node
/**
 * Offline SEO gate for fastpeptidetesting/seed/catalog.json.
 * Exits non-zero when catalog meta would be unsafe to import.
 *
 * Usage: node audit-seo.mjs
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const TITLE_MAX = 60;
const DESCRIPTION_MAX = 155;
const FORBIDDEN =
  /noviq|bacwater|\$\d|C(?:8|9|10|11|12|13)|D[1-9]|TBD|Demo pricing|before launch|…|\.\.\./i;

const catalog = JSON.parse(readFileSync(join(DIR, 'catalog.json'), 'utf8'));
const errors = [];

function checkMeta(kind, handle, { seo_title, seo_description }) {
  const label = `${kind} ${handle}`;
  if (!seo_title) errors.push(`${label}: missing seo_title`);
  if (!seo_description) errors.push(`${label}: missing seo_description`);
  if (seo_title && seo_title.length > TITLE_MAX) {
    errors.push(`${label}: seo_title length ${seo_title.length} > ${TITLE_MAX}`);
  }
  if (seo_description && seo_description.length > DESCRIPTION_MAX) {
    errors.push(`${label}: seo_description length ${seo_description.length} > ${DESCRIPTION_MAX}`);
  }
  for (const [field, value] of [
    ['seo_title', seo_title],
    ['seo_description', seo_description],
  ]) {
    if (value && FORBIDDEN.test(value)) {
      errors.push(`${label}: ${field} contains forbidden content: ${value}`);
    }
  }
}

function scanBlob(label, value) {
  if (typeof value === 'string' && /noviq|bacwater/i.test(value)) {
    errors.push(`${label}: cross-brand term found`);
  } else if (Array.isArray(value)) {
    value.forEach((item, index) => scanBlob(`${label}[${index}]`, item));
  } else if (value && typeof value === 'object') {
    for (const [key, child] of Object.entries(value)) {
      scanBlob(`${label}.${key}`, child);
    }
  }
}

checkMeta('collection', catalog.collection.handle, catalog.collection);
for (const product of catalog.products) {
  checkMeta('product', product.handle, product);
}
for (const page of catalog.pages) {
  checkMeta('page', page.handle, page);
}
scanBlob('catalog', catalog);

if (errors.length) {
  console.error(`SEO audit failed with ${errors.length} issue(s):`);
  for (const error of errors) console.error(`- ${error}`);
  process.exit(1);
}

const rows = [
  ['collection', catalog.collection.handle, catalog.collection.seo_title, catalog.collection.seo_description],
  ...catalog.products.map((product) => ['product', product.handle, product.seo_title, product.seo_description]),
  ...catalog.pages.map((page) => ['page', page.handle, page.seo_title, page.seo_description]),
];
console.log('SEO audit passed.');
console.log(`Checked ${rows.length} resources (1 collection, ${catalog.products.length} products, ${catalog.pages.length} pages).`);
console.log('type\thandle\ttitle_len\tdesc_len\ttitle');
for (const [type, handle, title, description] of rows) {
  console.log(`${type}\t${handle}\t${title.length}\t${description.length}\t${title}`);
}

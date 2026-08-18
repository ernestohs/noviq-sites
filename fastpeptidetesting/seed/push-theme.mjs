#!/usr/bin/env node
/**
 * Upsert theme files onto the unpublished fpt-preview theme via Admin API.
 * Used when Shopify CLI theme push cannot access the store.
 */
import { execFile } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const DIR = dirname(fileURLToPath(import.meta.url));
const THEME_ROOT = join(DIR, '..');
const STORE = (process.env.SHOPIFY_STORE || 'srgkrj-ij.myshopify.com').replace(/^https?:\/\//, '').replace(/\/$/, '');
const THEME_NAME = process.env.SHOPIFY_THEME_NAME || 'fpt-preview';
const BATCH = 15;
const SKIP_DIRS = new Set(['.git', 'node_modules', 'seed', '.shopify']);
const SKIP_FILES = new Set([
  'shopify.theme.toml',
  'README.md',
  'LICENSE.md',
  'release-notes.md',
  '.prettierrc.json',
  '.theme-check.yml',
  '.shopifyignore',
  '.gitignore',
]);
const BINARY_EXT = new Set(['.gif', '.png', '.jpg', '.jpeg', '.webp', '.woff', '.woff2', '.ttf', '.eot']);

const THEMES = `query {
  themes(first: 20) {
    nodes { id name role }
  }
}`;

const UPSERT = `mutation ThemeFilesUpsert($themeId: ID!, $files: [OnlineStoreThemeFilesUpsertFileInput!]!) {
  themeFilesUpsert(themeId: $themeId, files: $files) {
    upsertedThemeFiles { filename }
    job { id done }
    userErrors { field message }
  }
}`;

async function gql(query, variables) {
  const dir = mkdtempSync(join(tmpdir(), 'fpt-theme-'));
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
    '--allow-mutations',
  ];
  const variableFile = join(dir, 'variables.json');
  writeFileSync(variableFile, JSON.stringify(variables));
  args.push('--variable-file', variableFile);
  try {
    await execFileAsync('npx', args, {
      cwd: THEME_ROOT,
      env: { ...process.env, SHOPIFY_CLI_AGENT_INFO: 'n:cursor|v:none|p:none|m:none' },
      maxBuffer: 20 * 1024 * 1024,
    });
    return JSON.parse(readFileSync(outFile, 'utf8'));
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

function listFiles(dir, acc = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    const rel = relative(THEME_ROOT, full);
    if (SKIP_DIRS.has(name)) continue;
    const st = statSync(full);
    if (st.isDirectory()) {
      listFiles(full, acc);
      continue;
    }
    if (SKIP_FILES.has(name) || name.startsWith('.')) continue;
    acc.push(rel.split('\\').join('/'));
  }
  return acc;
}

function fileInput(rel) {
  const abs = join(THEME_ROOT, rel);
  const ext = rel.slice(rel.lastIndexOf('.')).toLowerCase();
  if (BINARY_EXT.has(ext)) {
    return {
      filename: rel,
      body: { type: 'BASE64', value: readFileSync(abs).toString('base64') },
    };
  }
  return {
    filename: rel,
    body: { type: 'TEXT', value: readFileSync(abs, 'utf8') },
  };
}

async function main() {
  const files = listFiles(THEME_ROOT).sort();
  const themeData = await gql(THEMES, {});
  const theme = (themeData.themes?.nodes || []).find((node) => node.name === THEME_NAME);
  if (!theme) {
    throw new Error(`Theme ${THEME_NAME} not found on ${STORE}`);
  }
  console.log(`Pushing ${files.length} files to ${THEME_NAME} (${theme.id}) on ${STORE}`);
  for (let i = 0; i < files.length; i += BATCH) {
    const batch = files.slice(i, i + BATCH).map(fileInput);
    const data = await gql(UPSERT, { themeId: theme.id, files: batch });
    const result = data.themeFilesUpsert || data;
    const errors = result.userErrors || [];
    if (errors.length) {
      throw new Error(errors.map((err) => err.message).join('; '));
    }
    const names = (result.upsertedThemeFiles || []).map((file) => file.filename);
    console.log(`upserted ${i + 1}-${Math.min(i + BATCH, files.length)} (${names.length} confirmed)`);
  }
  console.log('Theme push complete.');
  const themeNumericId = String(theme.id).split('/').pop();
  console.log(`Preview: https://${STORE}/?preview_theme_id=${themeNumericId}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});

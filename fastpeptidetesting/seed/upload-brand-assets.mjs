#!/usr/bin/env node
/**
 * Upload March Analytics logo + favicon to Shopify Files and bind them in the
 * live theme settings (logo + favicon image_picker fields).
 */
import { execFile } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const DIR = dirname(fileURLToPath(import.meta.url));
const THEME_ROOT = join(DIR, '..');
const STORE = (process.env.SHOPIFY_STORE || 'srgkrj-ij.myshopify.com').replace(/^https?:\/\//, '').replace(/\/$/, '');
const THEME_ID = process.env.SHOPIFY_THEME_ID || 'gid://shopify/OnlineStoreTheme/190297342025';
const LOGO_PATH = join(THEME_ROOT, 'assets/march-analytics-logo.svg');
const FAVICON_PATH = join(THEME_ROOT, 'assets/favicon.png');

const STAGED = `mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
  stagedUploadsCreate(input: $input) {
    stagedTargets { url resourceUrl parameters { name value } }
    userErrors { field message }
  }
}`;

const FILE_CREATE = `mutation fileCreate($files: [FileCreateInput!]!) {
  fileCreate(files: $files) {
    files { id alt fileStatus ... on MediaImage { image { url } } ... on GenericFile { url } }
    userErrors { field message }
  }
}`;

const THEME_FILES = `query ThemeSettings($id: ID!) {
  theme(id: $id) {
    files(filenames: ["config/settings_data.json"], first: 1) {
      nodes { body { ... on OnlineStoreThemeFileBodyText { content } } }
    }
  }
}`;

const UPSERT = `mutation ThemeFilesUpsert($themeId: ID!, $files: [OnlineStoreThemeFilesUpsertFileInput!]!) {
  themeFilesUpsert(themeId: $themeId, files: $files) {
    upsertedThemeFiles { filename }
    userErrors { field message }
  }
}`;

async function gql(query, variables = {}, allowMutations = false) {
  const dir = mkdtempSync(join(tmpdir(), 'fpt-brand-'));
  const queryFile = join(dir, 'query.graphql');
  const outFile = join(dir, 'out.json');
  const variableFile = join(dir, 'variables.json');
  writeFileSync(queryFile, query);
  writeFileSync(variableFile, JSON.stringify(variables));
  const args = [
    'shopify',
    'store',
    'execute',
    '--store',
    STORE,
    '--query-file',
    queryFile,
    '--variable-file',
    variableFile,
    '--output-file',
    outFile,
    '--json',
    '--no-color',
  ];
  if (allowMutations) args.push('--allow-mutations');
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

async function uploadShopFile({ filename, mimeType, buffer }) {
  const staged = await gql(STAGED, {
    input: [
      {
        filename,
        mimeType,
        resource: 'FILE',
        fileSize: String(buffer.length),
        httpMethod: 'POST',
      },
    ],
  }, true);
  const target = staged.stagedUploadsCreate?.stagedTargets?.[0];
  const errors = staged.stagedUploadsCreate?.userErrors || [];
  if (!target || errors.length) {
    throw new Error(errors.map((e) => e.message).join('; ') || 'stagedUploadsCreate failed');
  }

  const form = new FormData();
  for (const param of target.parameters) {
    form.append(param.name, param.value);
  }
  form.append('file', new Blob([buffer], { type: mimeType }), filename);

  const uploadRes = await fetch(target.url, { method: 'POST', body: form });
  if (!uploadRes.ok) {
    throw new Error(`Upload failed for ${filename}: ${uploadRes.status} ${await uploadRes.text()}`);
  }

  const created = await gql(FILE_CREATE, {
    files: [{ alt: filename, contentType: 'IMAGE', originalSource: target.resourceUrl }],
  }, true);
  const fileErrors = created.fileCreate?.userErrors || [];
  if (fileErrors.length) {
    throw new Error(fileErrors.map((e) => e.message).join('; '));
  }
  const file = created.fileCreate?.files?.[0];
  if (!file?.id) throw new Error(`fileCreate returned no file for ${filename}`);
  return { filename, fileId: file.id, url: file.image?.url || file.url };
}

function patchSettingsData(content, { logoRef, faviconRef }) {
  const header = content.match(/^\/\*[\s\S]*?\*\/\s*/)?.[0] || '';
  const body = content.slice(header.length);
  const data = JSON.parse(body);
  const presetKey = data.current;
  const preset = data.presets[presetKey];
  preset.logo = logoRef;
  preset.favicon = faviconRef;
  preset.logo_width = preset.logo_width || 90;
  return header + JSON.stringify(data, null, 2) + '\n';
}

async function main() {
  const logoBuffer = readFileSync(LOGO_PATH);
  const faviconBuffer = readFileSync(FAVICON_PATH);

  console.log('Uploading logo and favicon to Shopify Files...');
  const logo = await uploadShopFile({
    filename: 'march-analytics-logo.svg',
    mimeType: 'image/svg+xml',
    buffer: logoBuffer,
  });
  const favicon = await uploadShopFile({
    filename: 'march-analytics-favicon.png',
    mimeType: 'image/png',
    buffer: faviconBuffer,
  });

  const logoRef = `shopify://shop_images/${logo.filename}`;
  const faviconRef = `shopify://shop_images/${favicon.filename}`;
  console.log('Logo ref:', logoRef);
  console.log('Favicon ref:', faviconRef);

  const themeData = await gql(THEME_FILES, { id: THEME_ID });
  const content = themeData.theme?.files?.nodes?.[0]?.body?.content;
  if (!content) throw new Error('Could not read config/settings_data.json from theme');

  const updated = patchSettingsData(content, { logoRef, faviconRef });
  const upsert = await gql(
    UPSERT,
    {
      themeId: THEME_ID,
      files: [
        {
          filename: 'config/settings_data.json',
          body: { type: 'TEXT', value: updated },
        },
      ],
    },
    true,
  );
  const upsertErrors = upsert.themeFilesUpsert?.userErrors || [];
  if (upsertErrors.length) {
    throw new Error(upsertErrors.map((e) => e.message).join('; '));
  }

  console.log('Theme settings updated on', THEME_ID);
  console.log('Preview:', `https://${STORE}/`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});

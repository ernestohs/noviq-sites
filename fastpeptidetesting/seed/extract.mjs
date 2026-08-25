#!/usr/bin/env node
/**
 * Pulls the public peptidetest.com catalog, keeps testing services only,
 * and writes rewritten March Analytics copy to catalog.json and pages.json.
 *
 * Source HTML is never copied. Prices are retained as demo placeholders
 * pending intake C8–C13.
 *
 * Usage: node extract.mjs
 */
import { writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const SOURCE = 'https://peptidetest.com';
const KEEP_TYPES = new Set(['Compounds For Purity Test', 'Ancillary Tests']);
const DEMO_NOTE =
  '<p><em>Demo pricing for client review. Confirm before launch (intake C8-C13).</em></p>';

const HANDLE_OVERRIDES = {
  'metals-analysis-epa-methods': 'heavy-metals-testing',
  'karl-fischer-water-analysis': 'karl-fischer-testing',
  'custom-analytical-service': 'custom-analytical-service',
  'vial-vacuum-test-add-on-test': 'vial-vacuum-testing',
  'endotoxin-test': 'endotoxin-testing',
  'sterility-test': 'sterility-testing',
  'glow-blend-assay-ghk-cu-bpc-157-and-tb-500-tb4-purity-and-mass-testing': 'glow-blend-testing',
  'kpv-ghk-cu-bpc-157-and-tb-500-tb4-purity-and-mass-testing': 'klow-blend-testing',
};

const TITLE_OVERRIDES = {
  'glow-blend-testing': 'GLOW Blend Testing',
  'klow-blend-testing': 'KLOW Blend Testing',
};

const ADDON_BLURBS = {
  'heavy-metals-testing':
    'Elemental screen for specified metals on the submitted sample. Ordered as a standalone service or alongside a compound purity test.',
  'sterility-testing':
    'Sterility screen for the submitted sample. Ordered as a standalone service or alongside a compound purity test.',
  'endotoxin-testing':
    'Endotoxin screen for the submitted sample. Ordered as a standalone service or alongside a compound purity test.',
  'karl-fischer-testing':
    'Karl Fischer moisture determination for the submitted sample.',
  'vial-vacuum-testing':
    'Vial vacuum integrity check. Typically added to a compound purity order.',
  'custom-analytical-service':
    'Scoped analytical work after intake. The listed amount is a demo placeholder, not a final quote.',
};

async function fetchJson(url) {
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) {
    throw new Error(`${url} failed: ${res.status}`);
  }
  return res.json();
}

async function fetchAllProducts() {
  const products = [];
  for (let page = 1; page <= 10; page += 1) {
    const data = await fetchJson(`${SOURCE}/products.json?limit=250&page=${page}`);
    const batch = data.products || [];
    if (batch.length === 0) break;
    products.push(...batch);
  }
  return products;
}

function kindFor(product) {
  return product.product_type === 'Ancillary Tests' ? 'addon' : 'compound';
}

function marchHandle(product, kind) {
  if (HANDLE_OVERRIDES[product.handle]) return HANDLE_OVERRIDES[product.handle];
  let handle = product.handle
    .replace(/-copy$/, '')
    .replace(/-purity-and-mass-testing$/, '')
    .replace(/^blend-assay-/, '')
    .replace(/-assay$/, '');
  if (kind === 'compound' && !handle.endsWith('-testing')) {
    handle = `${handle}-testing`;
  }
  return handle;
}

function marchTitle(title, kind, handle) {
  if (TITLE_OVERRIDES[handle]) return TITLE_OVERRIDES[handle];
  if (kind === 'compound') {
    return title.replace(/\s*-\s*Purity and Mass Testing\s*$/i, ' Testing');
  }
  return title.replace(/\s*-\s*Add on test\s*$/i, '').trim();
}

function compoundName(title) {
  return title.replace(/\s*-\s*Purity and Mass Testing\s*$/i, '').trim();
}

function skuBase(handle) {
  return handle.replace(/-testing$/, '').replace(/-/g, '').slice(0, 12).toUpperCase();
}

function compoundBody(name, basePrice) {
  return [
    `<p>HPLC purity and potency analysis for ${escapeHtml(name)}. Identity confirmation is included. Methods follow USP &lt;1225&gt; validation principles. Specific instrument names are published after lab confirmation.</p>`,
    `<p>The first vial is $${basePrice}. Additional vials from the same lot may be added for batch variation testing. Every additional vial must match compound, label claim, manufacturer, and packaging. Differentiated results list each vial on the certificate of analysis. Non-differentiated results combine the lot into one report.</p>`,
    '<p>Complete sample details on this page, then add to cart. After checkout you receive shipping instructions for sending the sample. Results are issued as a certificate of analysis.</p>',
    '<p>Samples are submitted for research and analytical purposes only. Not for human or animal use.</p>',
    DEMO_NOTE,
  ].join('\n');
}

function addonBody(handle, title) {
  const blurb = ADDON_BLURBS[handle] || `${title} for the submitted sample.`;
  return [
    `<p>${escapeHtml(blurb)}</p>`,
    '<p>Complete sample details on this page, then add to cart. After checkout you receive shipping instructions for sending the sample. Results are issued as a certificate of analysis.</p>',
    '<p>Samples are submitted for research and analytical purposes only. Not for human or animal use.</p>',
    DEMO_NOTE,
  ].join('\n');
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function pickMeta(candidates, max) {
  for (const candidate of candidates) {
    const text = String(candidate).replace(/\s+/g, ' ').trim();
    if (text && text.length <= max) return text;
  }
  throw new Error(`No SEO candidate fits within ${max} characters: ${candidates.join(' | ')}`);
}

function seoTitle(title) {
  return pickMeta([`${title} | March Analytics`, title], 60);
}

function productSeo(title, kind, compound) {
  const seo_title = seoTitle(title);
  const seo_description =
    kind === 'compound'
      ? pickMeta(
          [
            `HPLC purity and potency analysis for ${compound}. Ship your sample to March Analytics after checkout and receive a certificate of analysis.`,
            `HPLC purity and potency analysis for ${compound}. Ship your sample after checkout and receive a certificate of analysis.`,
            `HPLC purity and potency analysis for ${compound}. Certificate of analysis issued after testing.`,
            `HPLC purity and potency analysis for ${compound}.`,
          ],
          155,
        )
      : pickMeta(
          [
            `${title} for customer-supplied research samples. Order online, ship the sample after checkout, and receive a certificate of analysis from March Analytics.`,
            `${title} for customer-supplied research samples. Ship your sample after checkout and receive a certificate of analysis.`,
            `${title} for customer-supplied research samples. Certificate of analysis issued after testing.`,
            `${title} for customer-supplied research samples.`,
          ],
          155,
        );
  return { seo_title, seo_description };
}

function pageSeo(handle, title) {
  const descriptions = {
    'how-it-works':
      'Order a March Analytics test, ship your sample to the lab, and receive a certificate of analysis. Checkout is for the service only.',
    methods:
      'HPLC purity and potency methods used by March Analytics, plus optional add-on screens for research samples.',
    turnaround: 'Standard and expedited laboratory turnaround options for March Analytics testing services.',
    'contact-us': 'Contact March Analytics about an order, sample submission, or certificate of analysis.',
    about:
      'March Analytics is an independent laboratory for analytical testing of customer-supplied research samples.',
    attestation:
      'Research-use attestation for March Analytics testing orders: samples are for research and analytical purposes only.',
  };
  return {
    seo_title: seoTitle(title),
    seo_description: pickMeta([descriptions[handle] || `${title} | March Analytics`], 155),
  };
}

function mapProduct(product) {
  const kind = kindFor(product);
  const handle = marchHandle(product, kind);
  const title = marchTitle(product.title, kind, handle);
  let variants = product.variants || [];
  if (handle === 'custom-analytical-service' && variants.length > 1) {
    variants = [variants[0]];
  }
  const basePrice = variants[0]?.price || '0.00';
  const optionValues = [...new Set(variants.map((variant) => variant.title))];
  const multi = variants.length > 1 && optionValues[0] !== 'Default Title';
  let optionName = product.options?.[0]?.name || 'Title';
  if (multi && (optionName === 'Title' || !optionName)) {
    optionName = 'Number of Vials';
  }
  const name = compoundName(product.title);
  const seo = productSeo(title, kind, name);

  return {
    source_handle: product.handle,
    handle,
    title,
    vendor: 'March Analytics',
    product_type: 'Testing Service',
    status: 'ACTIVE',
    tags: ['testing', kind],
    description_html: kind === 'compound' ? compoundBody(name, basePrice) : addonBody(handle, title),
    seo_title: seo.seo_title,
    seo_description: seo.seo_description,
    option_name: multi ? optionName : null,
    variants: variants.map((variant, index) => ({
      title: variant.title,
      price: variant.price,
      sku: `MA-${skuBase(handle)}-${index + 1}`,
      option_value: multi ? variant.title : null,
      requires_shipping: false,
      tracked: false,
    })),
  };
}

function pages() {
  const list = [
    {
      handle: 'how-it-works',
      title: 'How it works',
      template_suffix: 'how-it-works',
      body_html: [
        '<p>March Analytics tests customer-supplied samples and returns a certificate of analysis. Checkout does not ship anything to you. After payment you receive the lab receiving address and packaging notes for the sample you send in.</p>',
        '<ol><li>Select a compound test or add-on. Fill in compound name, batch or lot number, quantity supplied, and a return address.</li><li>Complete checkout. Service products are non-physical, so no shipping method is charged on the order.</li><li>Ship the sample to the address in the confirmation email. Do not require a signature on delivery. Mark the order number on the outside of the package.</li><li>Results are delivered as a certificate of analysis by email to the customer only. Certificates are never published publicly. Whether a private portal is also used is confirmed before launch (intake C13).</li></ol>',
        '<p>The receiving street address is issued after checkout (intake C11). Packaging requirements are confirmed with the lab (intake C12).</p>',
      ].join('\n'),
    },
    {
      handle: 'methods',
      title: 'Methods',
      template_suffix: 'methods',
      body_html: [
        '<p>Base compound tests measure purity, potency, and identity. HPLC is the primary purity and potency method. Methods follow USP &lt;1225&gt; validation principles. Specific identity instruments are named only after the lab confirms them.</p>',
        '<p>Optional add-on services: heavy metal screen, sterility screen, endotoxin screen, Karl Fischer moisture, and vial vacuum integrity. These can be ordered with a compound test or as standalone services.</p>',
        '<p>Reports translate instrument output into a certificate of analysis. Photography of instrumentation is pending; storefront images remain IMAGE TBD until client assets arrive.</p>',
      ].join('\n'),
    },
    {
      handle: 'turnaround',
      title: 'Turnaround',
      template_suffix: 'turnaround',
      body_html: [
        '<p>Standard turnaround is 3 business days and is included in the test price.</p>',
        '<p>1 business day: +$300.</p>',
        '<p>Same day: +$500.</p>',
        '<p>Whether the clock starts at payment or at sample receipt is pending client confirmation (intake C10). Storefront copy will match that decision before launch.</p>',
        DEMO_NOTE,
      ].join('\n'),
    },
    {
      handle: 'contact-us',
      title: 'Contact',
      template_suffix: 'contact',
      body_html: [
        '<p>Questions about an order, a sample, or a report can be sent with the form below.</p>',
        '<p>Legal entity: Lavagoat Wholesale LLC. Support email is pending client intake (D2). Do not treat any address on this page as a live lab location until the business address is published.</p>',
      ].join('\n'),
    },
    {
      handle: 'about',
      title: 'About',
      template_suffix: 'about',
      body_html: [
        '<p>March Analytics is an independent testing laboratory. We analyse customer-supplied peptides and related research compounds and return a certificate of analysis.</p>',
        '<p>The work is analytical quality control: purity, potency, and identity, with optional add-on screens. Reports are written so a researcher can read the result without guessing what was measured.</p>',
        '<p>March Analytics is operated independently from any seller of research compounds. We do not market, validate, or endorse products that were not in the vial we tested.</p>',
      ].join('\n'),
    },
    {
      handle: 'attestation',
      title: 'Attestation',
      template_suffix: 'attestation',
      body_html: [
        '<p>By placing an order you confirm:</p>',
        '<ul>',
        '<li>The sample is submitted for research and analytical purposes only.</li>',
        '<li>It is not for human or animal use.</li>',
        '<li>The certificate of analysis is valid only for the tested sample and may not be reused for any other material.</li>',
        '<li>Certificates may not be used to market, sell, or validate untested products.</li>',
        '<li>You will comply with all applicable laws.</li>',
        '<li>You indemnify Lavagoat Wholesale LLC and its laboratory partners from liability related to misuse, misrepresentation, or unlawful submission.</li>',
        '</ul>',
        '<p>Legal entity: Lavagoat Wholesale LLC. Public brand: Fast Peptide Testing.</p>',
      ].join('\n'),
    },
  ];
  return list.map((page) => ({ ...page, ...pageSeo(page.handle, page.title) }));
}

function collection() {
  return {
    handle: 'order-testing',
    title: 'Order Testing',
    body_html:
      '<p>Select a compound test or an add-on service. Each purchase is a laboratory analysis of a sample you ship to March Analytics after checkout.</p>',
    seo_title: 'Order Testing | March Analytics',
    seo_description:
      'Browse March Analytics compound tests and add-on screens. Order online, ship your sample after checkout, and receive a certificate of analysis.',
  };
}

function menus() {
  return {
    main: [
      { title: 'Order Testing', type: 'COLLECTION', handle: 'order-testing' },
      { title: 'How it works', type: 'PAGE', handle: 'how-it-works' },
      { title: 'Methods', type: 'PAGE', handle: 'methods' },
      { title: 'Turnaround', type: 'PAGE', handle: 'turnaround' },
      { title: 'About', type: 'PAGE', handle: 'about' },
      { title: 'Contact', type: 'PAGE', handle: 'contact-us' },
    ],
    footer: [
      { title: 'How it works', type: 'PAGE', handle: 'how-it-works' },
      { title: 'Methods', type: 'PAGE', handle: 'methods' },
      { title: 'Attestation', type: 'PAGE', handle: 'attestation' },
      { title: 'Contact', type: 'PAGE', handle: 'contact-us' },
    ],
  };
}

async function main() {
  const dir = dirname(fileURLToPath(import.meta.url));
  const sourceProducts = await fetchAllProducts();
  const testing = sourceProducts.filter((product) => KEEP_TYPES.has(product.product_type));
  if (testing.length === 0) {
    throw new Error('No testing products returned from peptidetest.com');
  }

  const catalog = {
    generated_at: new Date().toISOString(),
    source: SOURCE,
    note: 'Demo catalog for March Analytics preview. Prices copied as placeholders pending intake C8. Copy is original, not a Peptide Test reprint.',
    collection: collection(),
    products: testing.map(mapProduct).sort((a, b) => a.title.localeCompare(b.title)),
    pages: pages(),
    menus: menus(),
  };

  const catalogPath = join(dir, 'catalog.json');
  await writeFile(catalogPath, `${JSON.stringify(catalog, null, 2)}\n`);
  console.log(`Wrote ${catalog.products.length} products to ${catalogPath}`);
  console.log('Next: node apply-configurable-catalog.mjs  # Peptide Test commerce model');
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});

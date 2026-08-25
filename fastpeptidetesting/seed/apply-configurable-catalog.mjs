#!/usr/bin/env node
/**
 * Transforms catalog.json for the single configurable Peptide Test commerce model.
 * Keeps compound products for SEO/data; collection lists only the primary product.
 * Writes snippets/peptide-option-list.liquid for the storefront dropdown.
 *
 * Usage: node apply-configurable-catalog.mjs
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = dirname(fileURLToPath(import.meta.url));
const THEME_DIR = join(DIR, '..');
const PRICE_PER_VIAL = 199;
const ADDON_UNIT_PRICES = {
  'endotoxin-testing': 75,
  'sterility-testing': 75,
  'heavy-metals-testing': 100,
  'karl-fischer-testing': 250,
  'vial-vacuum-testing': 25,
};
const ADDON_TITLES = {
  'endotoxin-testing': 'Endotoxin Testing',
  'sterility-testing': 'Sterility Testing',
  'heavy-metals-testing': 'Heavy Metals Testing',
  'karl-fischer-testing': 'Karl Fischer Testing',
  'vial-vacuum-testing': 'Vial Vacuum Testing',
};

function compoundDisplayName(product) {
  return product.title.replace(/\s+Testing\s*$/i, '').trim();
}

function compoundHandleFromProduct(product) {
  return product.handle.replace(/-testing$/, '');
}

function primaryProduct() {
  const variants = [];
  for (let n = 1; n <= 5; n += 1) {
    const label = n === 1 ? '1 Vial' : `${n} Vials`;
    variants.push({
      title: label,
      price: (PRICE_PER_VIAL * n).toFixed(2),
      sku: `MA-PEPTIDETEST-${n}`,
      option_value: label,
      requires_shipping: false,
      tracked: false,
    });
  }
  return {
    source_handle: null,
    handle: 'peptide-testing',
    title: 'Peptide Test',
    vendor: 'March Analytics',
    product_type: 'Testing Service',
    status: 'ACTIVE',
    tags: ['testing', 'primary'],
    template_suffix: 'peptide-testing',
    description_html: [
      `<p>HPLC purity and potency analysis for customer-supplied peptide samples. Select how many vials to test, choose the peptide for each vial, optional screens, and turnaround.</p>`,
      `<p>Base testing is $${PRICE_PER_VIAL}.00 per vial. Optional screens are priced per vial. Expedited turnaround is priced per order.</p>`,
      `<p>After checkout you receive shipping instructions for sending the sample. Results are issued as a certificate of analysis.</p>`,
      `<p>Samples are submitted for research and analytical purposes only. Not for human or animal use.</p>`,
    ].join('\n'),
    seo_title: 'Peptide Test | March Analytics',
    seo_description:
      'Order HPLC peptide testing online. Configure vials, optional screens, and turnaround, then ship your sample after checkout.',
    option_name: 'Number of Vials',
    variants,
    collection: true,
  };
}

function rushProducts() {
  return [
    {
      source_handle: null,
      handle: 'checkout-rush-next-day',
      title: 'Rush Turnaround — Next Day',
      vendor: 'March Analytics',
      product_type: 'Testing Service',
      status: 'ACTIVE',
      tags: ['testing', 'checkout-addon'],
      description_html:
        '<p>Next business day turnaround fee for a Peptide Test order. Added from the product configurator.</p>',
      seo_title: 'Rush Turnaround Next Day | March Analytics',
      seo_description: 'Next business day rush fee for March Analytics peptide testing orders.',
      option_name: null,
      variants: [
        {
          title: 'Default Title',
          price: '199.00',
          sku: 'MA-RUSH-NEXT',
          option_value: null,
          requires_shipping: false,
          tracked: false,
        },
      ],
      collection: false,
    },
    {
      source_handle: null,
      handle: 'checkout-rush-same-day',
      title: 'Rush Turnaround — Same Day',
      vendor: 'March Analytics',
      product_type: 'Testing Service',
      status: 'ACTIVE',
      tags: ['testing', 'checkout-addon'],
      description_html:
        '<p>Same-day turnaround fee for a Peptide Test order. Added from the product configurator.</p>',
      seo_title: 'Rush Turnaround Same Day | March Analytics',
      seo_description: 'Same-day rush fee for March Analytics peptide testing orders.',
      option_name: null,
      variants: [
        {
          title: 'Default Title',
          price: '449.00',
          sku: 'MA-RUSH-SAME',
          option_value: null,
          requires_shipping: false,
          tracked: false,
        },
      ],
      collection: false,
    },
  ];
}

function normalizeAddon(product) {
  const unit = ADDON_UNIT_PRICES[product.handle];
  if (unit == null) return product;
  const title = ADDON_TITLES[product.handle] || product.title;
  return {
    ...product,
    title,
    tags: ['testing', 'addon', 'checkout-addon'],
    description_html: [
      `<p>${title} for the submitted sample. When ordered with Peptide Test, this screen is selected in the product configurator and charged per vial.</p>`,
      `<p>Unit price: $${unit.toFixed(2)} per vial.</p>`,
      `<p>Samples are submitted for research and analytical purposes only. Not for human or animal use.</p>`,
    ].join('\n'),
    seo_title: `${title} | March Analytics`,
    seo_description: `${title} add-on for March Analytics peptide testing. Priced per vial when ordered with Peptide Test.`,
    option_name: null,
    variants: [
      {
        title: 'Default Title',
        price: unit.toFixed(2),
        sku: `MA-${product.handle.replace(/-testing$/, '').replace(/-/g, '').slice(0, 12).toUpperCase()}-1`,
        option_value: null,
        requires_shipping: false,
        tracked: false,
      },
    ],
    collection: false,
  };
}

function writePeptideOptionsSnippet(compounds) {
  const lines = [
    '{%- comment -%}',
    '  Generated by seed/apply-configurable-catalog.mjs — do not edit by hand.',
    '  Peptide dropdown options for the Peptide Test configurator.',
    '{%- endcomment -%}',
  ];
  for (const compound of compounds) {
    lines.push(
      `<option value="${escapeAttr(compound.name)}" data-compound-handle="${escapeAttr(compound.handle)}">${escapeHtml(compound.name)}</option>`
    );
  }
  writeFileSync(join(THEME_DIR, 'snippets', 'peptide-option-list.liquid'), `${lines.join('\n')}\n`);
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function escapeAttr(value) {
  return escapeHtml(value);
}

function main() {
  const catalogPath = join(DIR, 'catalog.json');
  const catalog = JSON.parse(readFileSync(catalogPath, 'utf8'));

  const compounds = catalog.products
    .filter((p) => p.tags?.includes('compound'))
    .map((p) => ({
      name: compoundDisplayName(p),
      handle: compoundHandleFromProduct(p),
      product_handle: p.handle,
    }))
    .sort((a, b) => a.name.localeCompare(b.name));

  const keptCompounds = catalog.products
    .filter((p) => p.tags?.includes('compound'))
    .map((p) => ({
      ...p,
      collection: false,
      description_html: [
        `<p>HPLC purity and potency analysis for ${escapeHtml(compoundDisplayName(p))}. Prefer the single Peptide Test product to configure vials, add-ons, and turnaround.</p>`,
        `<p>Base testing is $${PRICE_PER_VIAL}.00 per vial via <a href="/products/peptide-testing?compound=${compoundHandleFromProduct(p)}">Peptide Test</a>.</p>`,
        `<p>Samples are submitted for research and analytical purposes only. Not for human or animal use.</p>`,
      ].join('\n'),
    }));

  const addons = catalog.products
    .filter((p) => Object.keys(ADDON_UNIT_PRICES).includes(p.handle))
    .map(normalizeAddon);

  // Custom analytical: draft, not in collection, not in configurator
  const custom = catalog.products.find((p) => p.handle === 'custom-analytical-service');
  const customProduct = custom
    ? {
        ...custom,
        status: 'DRAFT',
        collection: false,
        tags: ['testing', 'addon', 'quote-only'],
        description_html: [
          '<p>Custom analytical work is quoted manually. Use the Custom Analytical Services page to request a quote.</p>',
          '<p><a href="/pages/custom-analytical">Request a custom analytical quote</a></p>',
        ].join('\n'),
      }
    : null;

  const products = [
    primaryProduct(),
    ...rushProducts(),
    ...addons,
    ...keptCompounds,
    ...(customProduct ? [customProduct] : []),
  ].sort((a, b) => {
    if (a.handle === 'peptide-testing') return -1;
    if (b.handle === 'peptide-testing') return 1;
    return a.title.localeCompare(b.title);
  });

  catalog.generated_at = new Date().toISOString();
  catalog.note =
    'March Analytics catalog. Commerce uses Peptide Test ($199/vial). Compound products retained for SEO. Rush and screens are checkout helpers.';
  catalog.collection = {
    handle: 'order-testing',
    title: 'Order Testing',
    body_html:
      '<p>Configure Peptide Test: choose vials, peptides, optional screens, and turnaround. Ship your sample to March Analytics after checkout.</p>',
    seo_title: 'Order Testing | March Analytics',
    seo_description:
      'Order HPLC peptide testing. Configure vials, optional screens, and turnaround online, then ship your sample after checkout.',
    product_handles: ['peptide-testing'],
  };
  catalog.products = products;
  catalog.compounds = compounds;

  const pages = catalog.pages.filter((p) => p.handle !== 'custom-analytical');
  pages.push({
    handle: 'custom-analytical',
    title: 'Custom Analytical Services',
    template_suffix: 'custom-analytical',
    body_html: [
      '<p>Need a method outside the standard Peptide Test configurator? Describe the compound, methods, and timeline. The lab replies with a quote. Custom work is not available as self-serve checkout.</p>',
      '<p>For standard HPLC peptide testing, use <a href="/products/peptide-testing">Peptide Test</a>.</p>',
    ].join('\n'),
    seo_title: 'Custom Analytical Services | March Analytics',
    seo_description:
      'Request a quote for custom analytical work from March Analytics. Standard peptide testing uses the Peptide Test product.',
  });

  const howItWorks = pages.find((p) => p.handle === 'how-it-works');
  if (howItWorks) {
    howItWorks.body_html = [
      '<p>March Analytics tests customer-supplied samples and returns a certificate of analysis. Checkout does not ship anything to you. After payment you receive the lab receiving address and packaging notes for the sample you send in.</p>',
      '<ol><li>Open Peptide Test. Choose vial count, select a peptide for each vial, optional screens, and turnaround. Provide batch or lot number, quantity supplied, and a return address.</li><li>Complete checkout. Service products are non-physical, so no shipping method is charged on the order.</li><li>Ship the sample to the address in the confirmation email. Do not require a signature on delivery. Mark the order number on the outside of the package.</li><li>Results are delivered as a certificate of analysis by email to the customer only. Certificates are never published publicly. Whether a private portal is also used is confirmed before launch (intake C13).</li></ol>',
      '<p>The receiving street address is issued after checkout (intake C11). Packaging requirements are confirmed with the lab (intake C12).</p>',
    ].join('\n');
  }

  const turnaround = pages.find((p) => p.handle === 'turnaround');
  if (turnaround) {
    turnaround.body_html = [
      '<p>Standard turnaround is 3 business days and is included in the Peptide Test price.</p>',
      '<p>Next-Day: +$199 per order.</p>',
      '<p>Same-Day: +$449 per order.</p>',
      '<p>Select turnaround on the Peptide Test product page before adding to cart.</p>',
      '<p>Whether the clock starts at payment or at sample receipt is pending client confirmation (intake C10). Storefront copy will match that decision before launch.</p>',
    ].join('\n');
  }

  const methods = pages.find((p) => p.handle === 'methods');
  if (methods) {
    methods.body_html = [
      '<p>Base Peptide Test measures purity, potency, and identity. HPLC is the primary purity and potency method. Methods follow USP &lt;1225&gt; validation principles. Specific identity instruments are named only after the lab confirms them.</p>',
      '<p>Optional add-on screens (priced per vial): heavy metals, sterility, endotoxin, Karl Fischer moisture, and vial vacuum integrity.</p>',
      '<p>Custom analytical requests outside this list use the <a href="/pages/custom-analytical">Custom Analytical Services</a> quote form.</p>',
      '<p>Reports translate instrument output into a certificate of analysis. Photography of instrumentation is pending; storefront images remain IMAGE TBD until client assets arrive.</p>',
    ].join('\n');
  }

  catalog.pages = pages;
  catalog.menus = {
    main: [
      { title: 'Order Testing', type: 'PRODUCT', handle: 'peptide-testing' },
      { title: 'How it works', type: 'PAGE', handle: 'how-it-works' },
      { title: 'Methods', type: 'PAGE', handle: 'methods' },
      { title: 'Turnaround', type: 'PAGE', handle: 'turnaround' },
      { title: 'Custom analytical', type: 'PAGE', handle: 'custom-analytical' },
      { title: 'About', type: 'PAGE', handle: 'about' },
      { title: 'Contact', type: 'PAGE', handle: 'contact-us' },
    ],
    footer: [
      { title: 'How it works', type: 'PAGE', handle: 'how-it-works' },
      { title: 'Methods', type: 'PAGE', handle: 'methods' },
      { title: 'Custom analytical', type: 'PAGE', handle: 'custom-analytical' },
      { title: 'Attestation', type: 'PAGE', handle: 'attestation' },
      { title: 'Contact', type: 'PAGE', handle: 'contact-us' },
    ],
  };

  writeFileSync(catalogPath, `${JSON.stringify(catalog, null, 2)}\n`);
  writePeptideOptionsSnippet(compounds);

  console.log(
    `Wrote ${products.length} products (${compounds.length} compounds), collection product_handles=[peptide-testing], peptide-option-list.liquid`
  );
}

main();

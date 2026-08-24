# noviq-sites

Three related but deliberately separate commercial sites for one client. You own the code. The client owns business decisions, money, domains, and payment credentials.

| Folder | Domain | Platform | Brand |
| --- | --- | --- | --- |
| `bacwatermarket/` | bacwatermarket.com | Shopify (Dawn) | TBD (`brand_name` blank until client decides) |
| `fastpeptidetesting/` | fastpeptidetesting.com | Shopify (Dawn) | March Analytics |
| `noviqpeptides/` | noviqpeptides.com | WordPress + WooCommerce | Noviq Peptides |

Specs are the contract; chat history is not. Agent instructions live in [`AGENTS.md`](AGENTS.md). Build prompt: [`PROMPT.md`](PROMPT.md).

## Hard rules

1. **Never link or co-brand the Shopify stores with noviqpeptides.com.** No cross-links, shared logo, "our other brands", or shared support email. FDA / processors treat bac water sold alongside peptides as evidence of intended human use.
2. **Never run a bare `shopify theme push`.** Always `--unpublished`, always review the preview first.
3. **Never touch payment, banking, or registrar credentials.** Client configures those.
4. **Never connect a production domain** until the store is signed off, on a paid plan, and has a working gateway.
5. **Never invent TBD values** (prices, SKUs, COAs, shipping rates, brand names, legal wording). Structure and empty states ship; real values wait on intake (`specs/10-intake.md`).

The three sites must read as three unrelated companies to a visitor, a processor, and a regulator.

Copy tone: clinical and factual. No health claims, dosage guidance, therapeutic language, or before-and-after framing.

**noviqpeptides only:** never use the word *injectable*; RUO tone from the plugin (not hardcoded theme strings); no bac water / injection consumables; empty `/coa` and `/verify` until real lots exist.

**bacwatermarket only:** no peptide vocabulary, no reconstitution / health-claim framing.

## Repository layout

```
noviq-sites/
├── AGENTS.md / PROMPT.md    # agent + build instructions
├── specs/                   # build contracts
├── docs/                    # ops notes (preview, FPT DNS, analytics, policies)
├── bacwatermarket/          # self-contained Dawn theme
├── fastpeptidetesting/      # self-contained Dawn theme
│   ├── seed/                # catalog import, SEO audit, metaobjects
│   └── edge/                # Cloudflare Worker (agentic Content-Type fixes)
├── noviqpeptides/
│   ├── plugin/              # CPTs, compliance, pricing, seeder
│   ├── theme/               # navy storefront (no parent theme)
│   ├── local/               # Docker WP + Woo at localhost:8080
│   └── deploy/              # rsync + GoDaddy notes (theme + plugin only)
├── reference/               # visual refs; never ship to Shopify
├── scripts/                 # one-off tooling (not storefront runtime)
└── package.json             # Shopify CLI + theme-check / FPT scripts
```

### Shopify conventions

- Theme dirs (`assets/`, `config/`, `layout/`, `locales/`, `sections/`, `snippets/`, `templates/`) live at the **site folder root**, with that site's `shopify.theme.toml` and `.shopifyignore`.
- `cd` into the site folder for Shopify CLI. No repo-root `shopify.theme.toml`. Do not rely on a `path` key in theme toml.
- Two independent Dawn copies. Shared custom files are **duplicated** (not synced): `snippets/icon-symbol.liquid`, `sections/icons-with-content.liquid`, `sections/section-divider.liquid`, plus `snippets/image-tbd.liquid`. Do not add a sync script.
- Store identity: `settings.brand_name | default: shop.name`. Never hardcode `shop.name` as the brand.
- `reference/` is in `.shopifyignore`. Copy layout structure only; never trademarks, logos, photography, or proprietary copy.
- Preview stores (theme toml `development`): bac `0pa8dd-ec.myshopify.com` / theme `bac-preview`; FPT `srgkrj-ij.myshopify.com` / theme `fpt-preview`.

### WordPress / WooCommerce conventions

- Plugin slug / theme slug: `noviq-peptides`. Plugin owns data and compliance; theme owns presentation and Woo templates.
- Do not commit WordPress core, uploads, or MySQL dumps. `noviqpeptides/local/.env` and `noviqpeptides/deploy/.env` stay gitignored.
- Deploy copies **theme + plugin only** onto a host that already has WP + Woo + HTTPS. See [`noviqpeptides/deploy/README.md`](noviqpeptides/deploy/README.md).

## Specs (read before building)

1. [`specs/00-overview.md`](specs/00-overview.md) — architecture and cross-site constraints
2. [`specs/10-intake.md`](specs/10-intake.md) — TBDs; do not invent answers
3. Site spec: [`01-bacwatermarket`](specs/01-bacwatermarket.md), [`02-fastpeptidetesting`](specs/02-fastpeptidetesting.md), or [`03-noviqpeptides`](specs/03-noviqpeptides.md)

## Docs

| Doc | Purpose |
| --- | --- |
| [`docs/client-preview.md`](docs/client-preview.md) | Private demo vanities (`*.demo-purposes-only.com`); not production |
| [`docs/fpt-policies-and-documents.md`](docs/fpt-policies-and-documents.md) | FPT go-live policy / document checklist |
| [`docs/fpt-shopify-dns.md`](docs/fpt-shopify-dns.md) | FPT domain / Cloudflare / Shopify DNS |
| [`docs/fpt-analytics.md`](docs/fpt-analytics.md) | FPT analytics setup |
| [`docs/fpt-agentic-readiness.md`](docs/fpt-agentic-readiness.md) | Agent discovery / OpenAPI / remaining brand SEO |

Site-specific READMEs: [`noviqpeptides/README.md`](noviqpeptides/README.md), [`fastpeptidetesting/seed/README.md`](fastpeptidetesting/seed/README.md), [`fastpeptidetesting/edge/README.md`](fastpeptidetesting/edge/README.md).

## Prerequisites

- Node.js + npm (repo root: `npm install` for Shopify CLI)
- [Shopify CLI](https://shopify.dev/docs/api/shopify-cli) (via `@shopify/cli` in this package)
- Docker (noviqpeptides local store)
- For FPT seed / Admin scripts: `fastpeptidetesting/seed/.env` from `.env.example` with `SHOPIFY_ADMIN_TOKEN`
- For FPT edge worker: Cloudflare account + `wrangler`

## Commands

### Shopify theme check

```bash
npm install
npm run theme-check:bac
npm run theme-check:fpt
npm run theme-check
# or: cd bacwatermarket && npx shopify theme check
```

### Shopify theme push (unpublished only)

```bash
cd bacwatermarket   # or fastpeptidetesting
npx shopify theme push --unpublished
```

If the client has edited the theme in Admin, pull settings first:

```bash
npx shopify theme pull --only config/settings_data.json
```

Review the diff before pushing back.

### Fast Peptide Testing extras

```bash
# Catalog / SEO / metaobjects (see seed/README.md)
cd fastpeptidetesting/seed
cp .env.example .env   # add SHOPIFY_ADMIN_TOKEN
node extract.mjs
node import.mjs
node setup-metaobjects.mjs
node finish-seo-pages.mjs

# From repo root
npm run seo-audit:fpt
npm run verify-agentic:fpt
npm run test:agentic-edge

# Cloudflare Worker (OpenAPI Content-Type on www)
cd fastpeptidetesting/edge
npx wrangler deploy
```

### Noviq Peptides local store

```bash
cd noviqpeptides/local
cp .env.example .env   # once
docker compose up -d
./setup.sh
```

- Store: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (defaults in `.env.example`)

Theme and plugin are bind-mounted. After a plugin/theme swap that collides on CPT slugs:

```bash
docker compose down -v
./setup.sh
```

Seed prices and SKUs are local-dev placeholders. Do not copy them to production.

### Deploy Noviq Peptides

```bash
cd noviqpeptides/deploy
cp .env.example .env   # fill SSH; never commit
./rsync-own-server.sh
```

GoDaddy zip/SFTP steps: [`noviqpeptides/deploy/README.md`](noviqpeptides/deploy/README.md). Do not attach production DNS unless asked.

## Design system

**Shopify** (bacwatermarket, fastpeptidetesting): white `#FFFFFF`, section band `#ECECEC`, text `#121212`, muted `#6B6B6B`, primary button solid near-black, accent `#16A34A` on icons only. Minimalist; no gradients, heavy shadows, or stock art. Missing images use a gray **IMAGE TBD** placeholder.

**noviqpeptides:** client-approved navy (`#0A4DA8` / `#042F73`, Space Grotesk / Inter). Do not restyle it to the Shopify tokens unless the client asks.

## Workflow

1. Confirm the relevant spec covers the change. If TBD, stop and ask.
2. Change only the site folder in scope.
3. Validate: `theme check` for Shopify; localhost click-through for WP (age gate, shop, cart, checkout, empty `/coa`).
4. Stage for review. Do not invent commits on behalf of others unless asked.
5. When a client decision lands, update the matching spec so the file stays the source of truth.

## Out of scope unless explicitly asked

- Inventing intake TBD values
- Bare theme push to a live Shopify theme
- Committing WordPress core or DB dumps
- Production DNS, registrar, or payment gateway setup
- Syncing or merging the two Shopify themes into one store
- Linking or co-branding peptide ↔ Shopify storefronts

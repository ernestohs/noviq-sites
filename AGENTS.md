# AGENTS.md

Instructions for coding agents working in this repository.

## What this repo is

Three related but deliberately separate commercial sites for one client:

| Folder | Domain | Platform | Brand |
| --- | --- | --- | --- |
| `bacwatermarket/` | bacwatermarket.com | Shopify (Dawn) | TBD (`brand_name` blank until client decides) |
| `fastpeptidetesting/` | fastpeptidetesting.com | Shopify (Dawn) | March Analytics |
| `noviqpeptides/` | noviqpeptides.com | WordPress + WooCommerce | Noviq Peptides |

You own the code. The client owns business decisions, money, domains, and payment credentials.

## Read first

When these files exist, read them in order before building:

1. `specs/00-overview.md` (architecture and cross-site constraints)
2. `specs/10-intake.md` (TBDs; do not invent answers)
3. Site spec: `specs/01-bacwatermarket.md`, `specs/02-fastpeptidetesting.md`, or `specs/03-noviqpeptides.md`

If `PROMPT.md` or `specs/` are missing from the working tree, restore them from git before inventing architecture. Specs are the contract; chat history is not.

## Hard constraints (do not violate)

1. **Never link or co-brand the Shopify stores with noviqpeptides.com.** No cross-links, shared logo, "our other brands", or shared support email. FDA / processors treat bac water sold alongside peptides as evidence of intended human use.
2. **Never touch payment, banking, or registrar credentials.** Client configures those.
3. **Never connect a production domain** until the store is signed off, on a paid plan, and has a working gateway.
4. **Never invent TBD values** (prices, SKUs, COAs, shipping rates, brand names, legal wording). Structure and empty states ship; real values wait on intake.
5. **Never commit** unless the user explicitly asks. Stage for review. Do not use `git commit`.
6. **Never edit Shopify themes when the task is noviqpeptides**, and vice versa, unless the user explicitly asks for both.

## Copy and compliance tone

Clinical and factual. No health claims, no dosage guidance, no therapeutic language, no before-and-after framing.

**noviqpeptides only:**

- Never use the word **injectable**
- RUO tone only; RUO notice comes from the plugin, not hardcoded theme strings
- Bac water / injection consumables stay **off** this catalog
- Empty `/coa` and `/verify` until real lot documents exist

**bacwatermarket only:** no peptide vocabulary, no reconstitution / health-claim framing.

The three sites must read as three unrelated companies to a visitor, a processor, and a regulator.

## Design system

Shopify stores (bacwatermarket, fastpeptidetesting):

| Token | Value |
| --- | --- |
| Background | `#FFFFFF` |
| Section band | `#ECECEC` |
| Text | `#121212` |
| Muted | `#6B6B6B` |
| Primary button | `#121212` solid, white label |
| Secondary button | white fill, near-black border |
| Accent | `#16A34A` on icons only, never large fills |

Minimalist, whitespace-heavy. No gradients, no heavy shadows, no stock art. Missing images use a gray **IMAGE TBD** placeholder.

**noviqpeptides** uses the client-approved navy storefront (primary `#0A4DA8`, deep navy `#042F73`, Space Grotesk / Inter), not the tokens above. Do not restyle it to the Shopify system unless the client asks.

## Repository layout

```
noviq-sites/
├── bacwatermarket/          # self-contained Dawn theme
├── fastpeptidetesting/      # self-contained Dawn theme
├── noviqpeptides/
│   ├── plugin/              # store brain (CPTs, compliance, pricing)
│   ├── theme/               # store face (no parent theme)
│   ├── local/               # Docker WP + Woo for localhost:8080
│   └── deploy/              # rsync + GoDaddy notes (theme+plugin only)
├── reference/               # visual refs; never ship to Shopify
├── specs/                   # build contracts (when present)
└── package.json             # Shopify CLI + theme-check scripts
```

### Shopify conventions

- Theme dirs (`assets/`, `config/`, `layout/`, `locales/`, `sections/`, `snippets/`, `templates/`) live at the **site folder root**, with that site's `shopify.theme.toml` and `.shopifyignore`.
- `cd` into the site folder for Shopify CLI commands. No repo-root `shopify.theme.toml`. Do not rely on a `path` key in theme toml.
- Two independent Dawn copies. Three shared custom files are **duplicated** (not synced): `snippets/icon-symbol.liquid`, `sections/icons-with-content.liquid`, `sections/section-divider.liquid`. Also share `snippets/image-tbd.liquid` by duplication. Do not add a sync script.
- Store identity: `settings.brand_name | default: shop.name`. Never hardcode `shop.name` as the brand.
- `reference/` is in `.shopifyignore`. Copy layout structure only; never trademarks, logos, photography, or proprietary copy.

### WordPress / WooCommerce conventions

- Plugin slug / theme slug: `noviq-peptides`. Plugin owns data and compliance; theme owns presentation and Woo templates. No commercial parent theme.
- Do not commit WordPress core, uploads, or MySQL dumps. Docker volumes and `noviqpeptides/local/.env` / `noviqpeptides/deploy/.env` stay gitignored.
- Deploy copies **theme + plugin only** onto a host that already has WP + Woo + HTTPS. See `noviqpeptides/deploy/README.md`.

## Commands

### Shopify

```bash
npm run theme-check:bac
npm run theme-check:fpt
npm run theme-check
# or: cd bacwatermarket && npx shopify theme check
```

Push only when store access exists and the user asks:

```bash
cd <site> && npx shopify theme push
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

Needs Docker. Theme and plugin are bind-mounted; `setup.sh` makes mounts readable by www-data.

### Deploy Noviq Peptides

```bash
cd noviqpeptides/deploy
cp .env.example .env   # fill SSH; never commit
./rsync-own-server.sh
```

GoDaddy zip/SFTP steps: `noviqpeptides/deploy/README.md`. Do not log into production or attach DNS unless asked.

## Workflow

1. Confirm the relevant spec covers the change. If TBD, stop and ask.
2. Change only the site folder in scope.
3. Validate: `theme check` for Shopify; localhost click-through for WP (age gate, shop, cart, checkout, empty `/coa`).
4. Stage for the user. Do not commit.
5. When a client decision lands, update the matching spec so the file stays the source of truth.

## Definition of done (per site)

**Shopify:** `shopify theme check` has no errors; pages/handles from the spec exist (or are documented as store-admin steps); storefront readable at 375 / 768 / 1440; no invented TBDs; no peptide↔Shopify co-branding.

**noviqpeptides:** `docker compose up` + `setup.sh` serves localhost; age gate, shop, cart, checkout attestation path, empty `/coa` work; plugin owns compliance without the theme; deploy README + rsync script present; no bac water, no "injectable", no Shopify links from this work unless explicitly required.

## Out of scope unless explicitly asked

- Inventing C15–C25 / D4 / D9 intake values
- Committing WordPress core or DB dumps
- Production DNS, registrar, or payment gateway setup
- Syncing or merging the two Shopify themes into one store

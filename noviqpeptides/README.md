# noviqpeptides.com

WordPress + WooCommerce storefront. The theme is the client-approved navy shop. The plugin owns catalog data, compliance, and seeding.

```
noviqpeptides/
├── plugin/    store brain (data, compliance, pricing, seeder)
├── theme/     store face (navy UI, Woo templates)
├── local/     Docker WordPress at http://localhost:8080
│   └── seed-images/  client product PNGs (sideloaded on seed)
└── deploy/    rsync + GoDaddy notes
```

## Local test

```bash
cd noviqpeptides/local
cp .env.example .env
docker compose up -d
./setup.sh
```

`setup.sh` installs WordPress and WooCommerce, activates the theme and plugin, then runs `wp noviq seed`. That seed is idempotent: 28 simple products (DEV-* SKUs, $10 placeholders), 24 compound records, client photos from `local/seed-images/`, pages, and menus.

Open http://localhost:8080 (store) and http://localhost:8080/wp-admin (admin / noviq-local-dev).

After a plugin/theme swap, wipe volumes so CPT slugs do not collide:

```bash
cd noviqpeptides/local
docker compose down -v
./setup.sh
```

Prices and SKUs in the seed are local-dev placeholders. Do not copy them to production.

## Catalog rules

- No bacteriostatic water, syringes, prep pads, or reconstitution kit
- RUO, age gate, and checkout attestation come from the plugin
- `/coa` and `/verify` stay empty until real lots exist

## Deploy

See `deploy/README.md`. Copy theme + plugin only. WordPress core stays on the host. Product images live in the WordPress media library after seed; rsync does not copy `seed-images/`.

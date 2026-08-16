# noviqpeptides.com

Custom WooCommerce **store** rebuild. Runnable locally. Deployable to a host.

```
noviqpeptides/
├── plugin/    store brain (data, compliance, pricing)
├── theme/     store face (Woo templates + pages)
├── local/     Docker WordPress at http://localhost:8080
└── deploy/    rsync + GoDaddy notes
```

## Local test

```bash
cd noviqpeptides/local
cp .env.example .env
docker compose up -d
./setup.sh
```

Open http://localhost:8080 (store) and http://localhost:8080/wp-admin (admin / noviq-local-dev).

## Deploy

See `deploy/README.md`. Copy theme + plugin only. WordPress core stays on the host.

Contract: `specs/03-noviqpeptides.md`.

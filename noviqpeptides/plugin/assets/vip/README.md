# VIP Open Graph images

Place per-creator cards here. Referenced from `share.image` in
`data/{profile}/vip-coupons.json` as paths relative to `plugin/assets/`.

## Spec

- Size: 1200 x 630 px
- Format: JPG or PNG, under ~300 KB
- Naming: `{code-lowercase}.jpg` (e.g. `lele10.jpg` for code `LELE10`)
- Tone: RUO only. No health claims, no "injectable", no before/after.

## Fallback

`default.jpg` is used when `share.image` is missing or the file is not readable.
Creator-specific photos require client approval before production deploy.

## Local / staging checks

With the store running (e.g. `http://localhost:8080`) and VIP coupons seeded:

```bash
# Crawler: expect 200 HTML with og: tags (not a redirect)
curl -sI -A "facebookexternalhit/1.1" http://localhost:8080/LELE10
curl -s -A "facebookexternalhit/1.1" http://localhost:8080/LELE10 | grep 'og:'

# Human: expect 302 to shop
curl -sI http://localhost:8080/LELE10

# Audit share readiness
wp noviq vip_share_audit
```

After deploy, re-scrape with:

- [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/)
- [LinkedIn Post Inspector](https://www.linkedin.com/post-inspector/)
- Paste test in Discord / iMessage

Confirm each creator URL shows the right title, description, and image, and that a normal browser click still applies the coupon at checkout.

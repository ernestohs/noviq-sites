# Affiliate commissions

Noviq Peptides only. The module lives in `noviq-core` under `plugin/src/Affiliates/` and is on when the profile sets `'affiliates' => true`. There is no public signup and no automated payout. Administrators pay from a monthly CSV.

## Setup

Tables are created with `dbDelta` and tracked by the `noviq_affiliates_db_version` option. `Schema::maybe_migrate()` runs on `init`, so an rsync deploy creates the tables on the next request. Plugin activation installs them as well and registers the My Account `affiliate-dashboard` endpoint.

Roles:

| Role | Who | Capability |
| --- | --- | --- |
| Administrator | Store admin | `noviq_manage_affiliates` and the dashboard capability |
| `noviq_recruiter` | Recruiter | `noviq_view_affiliate_dashboard` |
| `noviq_rep` | Sales rep | `noviq_view_affiliate_dashboard` |

WooCommerce blocks wp-admin for these customer-type roles. Reps and recruiters use My Account. A person who is both (Lele, Jess) uses the recruiter dashboard.

### Seed the client roster

`plugin/data/noviq/affiliates.json` is the mapping. Seed links existing 10% VIP coupons. It does not create WordPress users and does not invent email addresses.

```bash
wp noviq affiliates seed --dry-run
wp noviq affiliates seed
wp noviq affiliates attach-user LELE10 person@example.com --recruiter
```

`attach-user` creates the account if needed, assigns `noviq_rep`, and sends the password setup through WordPress. `--recruiter` also links that user to the rep's current recruiter. When the rep name and the recruiter name match (Lele, Jess), the recruiter link is added without the flag.

Seed is idempotent on code. A code an administrator has already moved is left on its current recruiter.

Recruiters, each with their own selling code (own sales earn 25% and 7%):

- Lele — `LELE10`
- Jess — `JESS10`

Jess's reps: `BUNNY10`, `MALIA10`, `LILLIE10`, `MUSHY10`, `JAELYNN10`, `MAGICAL10`, `ROSE10`, `LANDYN10`, `KENDALL10`, `EMILY10`.

House reps, with no recruiter and no 7% line: `BRIANNA10`, `KEISHA10`, `SORIAH10`, `KALEEN10`.

`TRIZZY10`, `TIM10`, `SUMMER10`, and `LUPE10` stay plain 10% VIP promotions until an administrator links them to a rep.

## Admin operations

WooCommerce → Affiliates (`noviq_manage_affiliates`). Every form posts to `admin-post.php` with a nonce and a capability check.

- Reports — month or date range, recruiter, and rep. Ledger rows, with negative amounts in red.
- Reps — create a rep (optional login, which sends the password email), generate or link a 10% individual-use code, assign a recruiter or house, move, activate, or deactivate.
- Recruiters — create, activate, deactivate, and add that recruiter's own selling code.
- Customers — search by email and reassign. The change applies to future orders. Past ledger rows stay with the original rep. Each move writes `noviq_aff_customer_history`.
- Orders — attributed orders.
- Monthly payout — net per payee for `YYYY-MM`, then Download CSV.

Deactivating a rep keeps existing customer ownership and stops new attribution and commission until the rep is active again. The coupon is rejected by `woocommerce_coupon_is_valid`. The coupon row is not deleted. Deactivating a recruiter omits that recruiter from new order snapshots. Earn rows already written are unchanged.

Codes are unique and are not reused.

## Attribution and promotions

A code can arrive as an applied affiliate coupon, the optional checkout field Referral code (classic field `noviq_aff_referral`, block field `noviq/referral_code`), or a `/CODE` or `/go/CODE` link. `ReferralCoupon::stash()` fires `noviq_referral_code_stashed` so the code is kept when the coupon itself cannot apply.

At order creation the owner is resolved by customer id, then by the SHA-256 of the normalized billing email. An existing owner keeps the order. Another rep's code does not move the customer, and checkout shows: "This account already has a representative. The code you entered will not change that." With no owner, an active code attributes the order. The rep and that rep's current recruiter are stored on the order (HPOS-safe meta) and in `noviq_aff_orders`.

Ownership is written when the order is paid, first write wins. An email match that later has a customer id fills that column. If the customer id and the email point at different reps, the customer id wins.

Coupon conflicts:

- An affiliate code entered while another coupon is applied is recorded as the referral and the affiliate coupon is not added. Notice: "A store promotion is already applied, so the representative discount was not added. Your referral code is still recorded."
- Another coupon entered while an affiliate code is applied replaces the affiliate discount. Notice: "The representative discount was removed so the store promotion can apply. Your referral code is still recorded."

Volume breaks stay base pricing. The 10% affiliate discount stacks on that price. Non-stacking applies to other coupons only.

## Commission formula

The base is the sum of product line `get_total()` values after discounts. Tax, shipping, and fees are excluded, including a Tagada `wc_total_reconciliation` fee.

Amounts are integer cents. Production store pricing uses 0 decimal places; the ledger still stores and displays two decimals, so $22.50 stays $22.50.

```
rep       = round_half_up(base_cents * 2500 / 10000)
recruiter = round_half_up(base_cents * 700 / 10000)
```

Both rates use the same base. A $100 line with the 10% code is a $90 base: $22.50 rep and $6.30 recruiter. A house order writes the rep row only. A recruiter's own sales write both rows.

Earn runs on `woocommerce_payment_complete`, `woocommerce_order_status_processing`, and `woocommerce_order_status_completed`, and only when the order `is_paid()`. The write takes `GET_LOCK('noviq_aff_order_<id>')` and inserts a unique idempotency key: `earn:<order>:rep`, `earn:<order>:recruiter`, `refund:<refund_id>:<payee>`, `reversal:<order>:<payee>`.

The period is `YYYY-MM` in the site timezone, from the paid date for earns and from the event time for refunds and reversals.

Unpaid cancelled or failed orders earn nothing and do not create ownership.

## Refunds and reversals

Original rows are never edited or deleted. Refunds and reversals are new negative rows, shown in red (`.noviq-aff-neg`).

On `woocommerce_order_refunded`, the refunded base is the absolute sum of the refund's product line totals. An amount-only refund is `refund amount / order total * original base`. The negative commission is that share of the original earn, capped at the amount still unreversed. When the refunded base reaches the full base, the remainder is reversed exactly so rounding cannot drift.

A paid order that moves to cancelled or failed reverses whatever is still unreversed, once. The `refunded` status is not hooked, so a full refund is not reversed a second time.

## Dashboards

My Account → Affiliate is visible with `noviq_view_affiliate_dashboard`.

- Rep — own net sales and commission, and an order table with a date filter and pagination. The table shows order number and date. It does not show the customer name, email, or the recruiter's 7%.
- Recruiter — current team with per-rep totals, team totals, orders whose snapshot `recruiter_id` is theirs, and their own commission history. A `rep_id` drill-down is checked server-side against that recruiter's assignment history and orders.

Administrators see every row in wp-admin and get a link there from My Account.

## CSV reconciliation

Monthly payout → Download CSV. Columns: `period`, `payee_type`, `payee_id`, `payee_name`, `earned`, `adjustments`, `net`, `currency`. `net` is earned plus adjustments (adjustments are negative). Cells that start with `=`, `+`, `-`, or `@` are prefixed so a spreadsheet does not treat them as formulas. Pay from this file manually. The export does not mark rows paid and does not contact a processor.

## Backfill

Orders placed before the module is installed are left alone until an explicit backfill. The command uses the same idempotency keys, so repeating it does not double-pay.

```bash
wp noviq affiliates backfill --since=YYYY-MM-DD --dry-run
wp noviq affiliates backfill --since=YYYY-MM-DD
```

`--since` is inclusive on the order created date. Orders with no rep code and no saved owner are skipped. Run it on staging before production.

## Deployment

1. `cd noviqpeptides/deploy && ./backup-production.sh`
2. `./rsync-own-server.sh` (theme and plugin). Tables migrate on the next request.
3. On the server: `wp noviq affiliates seed --dry-run`, then `wp noviq affiliates seed`.
4. Attach logins when the client supplies them: `wp noviq affiliates attach-user CODE email --recruiter` where needed.
5. Optional: `wp noviq affiliates backfill --since=YYYY-MM-DD --dry-run`, then without `--dry-run`.
6. Run a paid-order webhook test on staging. Local Docker cannot receive Tagada webhooks.

Do not run plain `wp noviq seed` on production. That command reloads dev catalog prices. Affiliate seed is the separate `wp noviq affiliates seed` command.

## Limitations

- The commissionable base is whatever WooCommerce stored on the line items. With 0-decimal production prices, Woo's own rounding is part of the base.
- Concurrent payment webhooks are serialized with `GET_LOCK` plus the unique ledger key. The test harness runs two CLI processes; it approximates two webhooks and does not reproduce the Tagada HTTP path.
- The repo has no PHPCS or PHPStan config. `noviqpeptides/tests/affiliates/run-affiliate-tests.sh` runs `php -l`, the unit suite, the integration suite on the current order storage and the other HPOS mode, then the concurrency check.

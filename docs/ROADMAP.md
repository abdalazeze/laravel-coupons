# Roadmap after v0.1

Planning document for the "Later" list in `CLAUDE.md`. Nothing here is built yet. Each milestone keeps the v0.1 rules: test first for redemption logic, concurrency proven on MySQL and PostgreSQL, no README claim without a test.

## Order and why

| Release | Scope | Tier | Why this position |
|---|---|---|---|
| 0.1.2 | Hardening: Laravel 11 in CI, table names from config | Free | Fixes claims v0.1 already makes but does not prove |
| 0.2.0 | Stacking rules | Free | Small, and it extends the concurrency story to a new race |
| 0.3.0 | Product and category scoping | Free | The feature most coupon packages are asked for; biggest pull for adoption |
| Pro 1 | Filament admin | Paid | Turns the free package into something a non-developer can operate |
| Pro 2 | Cashback and wallet | Paid | Largest scope; builds on the admin for support tooling |

Free features first because goals 1 and 2 (portfolio, lead generation) need adoption before a paid tier has buyers.

---

## 0.1.2 — Hardening

**Laravel 11 is claimed but untested.** `composer.lock` is gitignored, so every CI job resolves the newest versions: Laravel 12 with Testbench 10. Laravel 11 (Testbench 9) never runs.

- Add a `laravel: [11, 12]` matrix to the SQLite job, pinning with `composer require "illuminate/support:^11" "orchestra/testbench:^9" --no-update` before install.
- Add a `--prefer-lowest` run, so the lower bounds in `composer.json` are tested too.
- Add PHP 8.4 to the matrix.

**Table names come from config only half the time.** The models read `coupons.table_names`, but both migrations hard-code `coupons` and `coupon_redemptions`, and `foreignId('coupon_id')->constrained()` infers the table. `CONTRIBUTING.md` says table names always come from config. Fix the migrations to read config, and add a test that renames both tables and redeems.

**Model swapping is in the config but untested.** `coupons.models.*` exists; `Coupon::redemptions()` and `CouponRedemption::coupon()` still hard-code the default classes. Either make the relations read config and test a swapped model, or remove the option from the config until it is real. Recommendation: make it real, it is a few lines.

**Local MySQL suite is slow.** The full suite took about 9 minutes against MySQL in Docker locally, almost all of it outside the concurrency tests (which take 20 s). Find out whether Testbench is re-running migrations per test before it becomes a CI problem.

---

## 0.2.0 — Stacking rules

The `stackable` column exists but nothing reads it. Today any number of coupons can be redeemed on one order.

### Rules

- A non-stackable coupon cannot be redeemed on an order that already has an applied redemption, and once applied, no other coupon can join it.
- Stackable coupons combine with each other, never with a non-stackable one.
- Each new coupon is calculated on the amount left after earlier discounts: `getCouponAmount() - sum(applied discounts)`. The total discount can never exceed the order amount.
- New typed exception: `CouponNotStackable` carrying the coupon and the conflicting redemption.

### The new race

The unique index covers one coupon per order. Two *different* non-stackable coupons applied to the same order in parallel would both pass a "no other redemption exists" check. The coupon-row lock does not help, because they are different coupons.

The fix is to serialize per redeemable: lock the order row (`SELECT ... FOR UPDATE` on the host app's table) before checking existing redemptions.

- **Lock order matters.** Redeemable first, then coupon, everywhere, including `reverse()`. Two transactions taking the same two locks in opposite orders is the classic deadlock.
- **Trade-off:** we now lock a row in the host app's table. Document it; it is held only for the redemption transaction.
- **Remaining amount** must be read after the redeemable lock, for the same MySQL `REPEATABLE READ` snapshot reason as the per-user limit.

### Tests

- Unit: each rule, remaining-amount calculation, cap at order amount.
- Concurrency: 2 non-stackable coupons on one order, 30 parallel attempts each → exactly 1 applied. Several stackable coupons in parallel on one order → total discount never exceeds the order amount.

### Decision needed

Enforcing stacking changes behaviour for anyone who redeems several coupons per order today, since `stackable` defaults to `false`. Options:

1. Enforce by default, call it out under "Changed" in the changelog. Fine under 0.x semver.
2. Add `coupons.enforce_stacking` defaulting to `false` in 0.2, flip it in 1.0.

Recommendation: option 1. The package is days old and the current behaviour is a bug, not a feature anyone chose.

---

## 0.3.0 — Product and category scoping

### Data model

- `coupon_scopes`: `coupon_id`, `scopeable` (morph: a product, a category, anything), `mode` (`include` | `exclude`).
- No scopes means the whole order is eligible, which keeps v0.1 behaviour.

### Contract

The order needs to expose its lines. New optional contract, so `Redeemable` does not break:

```php
interface HasCouponLines
{
    /** @return iterable<CouponLine> */
    public function getCouponLines(): iterable;
}
```

`CouponLine` is a readonly DTO: amount (minor units) and the models it belongs to (product, categories).

### Calculation

- Eligible amount = sum of lines matching an include scope and no exclude scope.
- The `DiscountCalculator` contract stays as is: it receives the eligible amount instead of the order amount. Custom calculators keep working.
- New exception: `CouponNotApplicable` when the eligible amount is zero.

### Decisions needed

- Does `min_order_amount` compare against the whole order or the eligible amount? Recommendation: whole order, it is what shop owners expect ("spend 50, get 10% off shoes").
- Per-line discount allocation (needed for partial refunds and accounting) is out of scope for 0.3. Store only the total, as now.

---

## Pro 1 — Filament admin

Separate package (`abdalazeze/laravel-coupons-filament`), so the free package never depends on Filament.

- Coupon resource: create, edit, deactivate; usage shown as `redemptions_count / max_redemptions`.
- Redemptions list per coupon, with a "Reverse" action calling `Coupons::reverse()`.
- Widgets: redemptions over time, top coupons, discount given.
- Bulk code generation (e.g. 500 single-use codes for a campaign), unique codes generated in batches with the unique index as the final guard.

### Decisions needed

- Which Filament major to target. Recommendation: the current major only; supporting two doubles the test matrix for a paid product with no users yet.
- How to sell and distribute a private Composer package: a licence-key Composer repository (e.g. Anystack, Privato) versus a private Satis behind your own auth. This decision also covers Pro 2.

---

## Pro 2 — Cashback and wallet

Based on the production engine the package came from. Largest piece; plan it in its own document before starting.

### Shape

- A cashback coupon gives no discount at checkout. It credits the redeemer's wallet after the order is fulfilled.
- **Lifecycle:** `pending` at redemption → `available` after a hold period (the refund window) → `reversed` if the order is refunded inside the window.
- **Wallet ledger:** append-only `wallet_transactions` (credit, debit, expiry, reversal), each with an idempotency key. A cached `balance` on `wallets` for reads.
- **Spending** uses the same pattern as the global limit, an atomic conditional update: `UPDATE wallets SET balance = balance - ? WHERE id = ? AND balance >= ?`. Zero rows means insufficient funds, with no read-then-write.
- **Expiry** of credits, oldest first.

### Concurrency tests to plan for

- Parallel spends against one wallet never take the balance below zero.
- A refund racing with the pending-to-available transition never leaves the credit both available and reversed.
- Replaying the same webhook (same idempotency key) credits once.

---

## Not planned

Gift cards and multi-tenancy stay out unless a paying user asks.

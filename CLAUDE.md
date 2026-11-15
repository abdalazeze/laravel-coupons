# laravel-coupons

Open-source Laravel package: a coupons, discounts and cashback engine whose redemption is safe under concurrency. Package name `abdalazeze/laravel-coupons`, namespace `Abdalazeze\Coupons`.

## Why it exists

Most coupon packages handle promo codes and a usage counter, and break under load: a coupon limited to 100 uses gets redeemed 103 times during a flash sale. This package is built around correct redemption first, rules second. It is based on a real vouchers/cashback engine the author built in production (Deemples, golf booking platform).

Goals, in order:
1. Portfolio piece and interview material. The author must be able to explain and defend every line.
2. Lead generation for paid integration work.
3. Later: a paid Pro tier (cashback/wallet, Filament admin). Not now.

## Working agreements

- The author is a senior Laravel engineer. Skip fundamentals, explain the trade-off or the gotcha.
- Explain each non-obvious design decision in one or two lines when you make it. He will be asked about it in interviews.
- Small, focused commits. One concern per commit.
- Ask before adding any dependency.
- Write the test first for redemption logic.
- Do not claim behaviour in the README that a test does not prove.

## Stack and standards

- PHP 8.2+, Laravel 12 and 13 (support both; Laravel 13 needs PHP 8.3). Laravel 11 was dropped in 0.1.2: it is end-of-life with unpatched advisories, and Composer blocks it by default.
- Framework floors (`^12.71|^13.40`) are the first releases with every known advisory fixed. Raise them when a new advisory lands, or CI's `--prefer-lowest` job will fail to resolve.
- Orchestra Testbench for package tests, Pest as the runner.
- PHPStan (Larastan) level 8 (`phpstan.neon.dist`), Laravel Pint with the Laravel preset plus strict types and sorted imports (`pint.json`).
- Code style, naming, errors, tests and commit format: follow `CONTRIBUTING.md`. It is the source of truth for style.
- GitHub Actions: tests on MySQL 8 and PostgreSQL 16, PHPStan, Pint check.
- SQLite is fine for unit tests, but concurrency tests must run on MySQL and PostgreSQL (SQLite has no `SELECT ... FOR UPDATE`).

## Domain model (v0.1)

- `coupons`: code (unique, case-insensitive), type (`fixed` | `percentage`), value, currency (nullable, for fixed), max_redemptions (nullable = unlimited), redemptions_count, max_per_user (nullable), min_order_amount (nullable), starts_at, expires_at, is_active, stackable (bool), metadata (json).
- `coupon_redemptions`: coupon_id, redeemer (morph: who), redeemable (morph: what, e.g. order), discount_amount, status (`applied` | `reversed`), timestamps.
- Money as integers in minor units. No floats anywhere.

## Redemption rules (the core)

The redemption flow runs in one DB transaction:

1. Validate rules that need no lock (active, dates, min order, currency). Fail fast with a typed exception per rule.
2. Global limit: atomic conditional update
   `UPDATE coupons SET redemptions_count = redemptions_count + 1 WHERE id = ? AND (max_redemptions IS NULL OR redemptions_count < max_redemptions)`
   Zero affected rows means the limit is reached. No read-then-write.
3. Per-user limit: lock the coupon row (`lockForUpdate`) before counting this redeemer's applied redemptions, so two parallel requests from the same user serialize.
4. Idempotency: unique index on (`coupon_id`, `redeemable_type`, `redeemable_id`). Applying the same coupon to the same order twice returns the existing redemption, it does not double count.
5. Insert the redemption, fire `CouponRedeemed`.

Reversal (order cancelled/refunded): mark redemption `reversed`, decrement the counter in the same transaction, fire `CouponRedemptionReversed`.

Every rule above needs a test. The headline test: 150 parallel redemption attempts on a coupon with `max_redemptions = 100` must produce exactly 100 applied redemptions. Run attempts in separate processes (pcntl_fork or a spawned artisan command per attempt), each with its own DB connection.

## Public API (target)

```php
Coupons::validate('SUMMER10', $order, $user);          // returns result object or throws
Coupons::redeem('SUMMER10', $order, $user);            // returns CouponRedemption
Coupons::reverse($redemption);
$order->applyCoupon('SUMMER10');                       // via HasCoupons trait
```

Discount calculation lives behind a `DiscountCalculator` contract so apps can swap it.

## Milestones

- M1: package skeleton, config, migrations, models, Testbench + Pest + CI.
- M2: rule validation with typed exceptions, discount calculation.
- M3: concurrency-safe redemption, idempotency, reversal, events. Concurrency tests on MySQL and PostgreSQL.
- M4: README that tells the race-condition story with the test as proof, CHANGELOG, tag v0.1.0, publish on Packagist.
- Later: stacking rules, scoped coupons (products/categories), Filament plugin, cashback/wallet (Pro).

## Out of scope for v0.1

Admin UI, cashback, multi-tenant, product/category scoping, gift cards.

# laravel-coupons

[![Tests](https://github.com/abdalazeze/laravel-coupons/actions/workflows/tests.yml/badge.svg)](https://github.com/abdalazeze/laravel-coupons/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/abdalazeze/laravel-coupons.svg)](https://packagist.org/packages/abdalazeze/laravel-coupons)
[![License](https://img.shields.io/github/license/abdalazeze/laravel-coupons.svg)](LICENSE)

Coupons and discounts for Laravel whose redemption limits hold under concurrency.

A coupon limited to 100 uses should be used 100 times, not 103, even when a flash sale sends 150 checkouts at the same second. This package is built around that guarantee first and rules second, and the guarantee is tested with real parallel processes against MySQL and PostgreSQL on every push.

## The race condition

Most coupon code checks the limit and then increments the counter:

```php
if ($coupon->redemptions_count >= $coupon->max_redemptions) {
    throw new CouponLimitReached;
}

$coupon->increment('redemptions_count');
```

Two requests arrive together. Both read `99`, both pass the check, both increment. The coupon ends at `101`. As an experiment I swapped this package's global limit for exactly that read-then-write and ran the concurrency test below against MySQL: **150 of 150** attempts were applied on a coupon limited to 100.

Wrapping it in a transaction does not help. A transaction does not stop two connections from reading the same row at the same time.

## How redemption works

`redeem()` runs in one database transaction:

1. **Rules that need no lock** fail fast with a typed exception: active, start date, expiry, minimum order, currency.
2. **Global limit** is one conditional `UPDATE`. The database checks and increments in the same statement, so there is no window between read and write:
   ```sql
   UPDATE coupons SET redemptions_count = redemptions_count + 1
   WHERE id = ? AND (max_redemptions IS NULL OR redemptions_count < max_redemptions)
   ```
   Zero affected rows means the limit is reached.
3. **Per-user limit** has to count this user's redemptions first, which is a read-then-write. So the coupon row is locked (`SELECT ... FOR UPDATE`) before counting, and only when the coupon has a per-user limit.
4. **Idempotency**: a unique index on (coupon, redeemable) means redeeming the same coupon for the same order twice returns the first redemption instead of counting twice. That includes two requests racing each other: the loser's transaction rolls back, counter increment included, and it returns the winner's row.
5. The redemption is inserted and `CouponRedeemed` is dispatched, after the outermost transaction commits.

A few details that matter in production:

- **Counter before insert.** The redemption's foreign key takes a shared lock on the coupon row. Inserting first and then upgrading to an exclusive lock for the counter would deadlock two parallel requests on MySQL.
- **Lock first when counting.** On MySQL's default `REPEATABLE READ`, a transaction's snapshot is taken at its first plain `SELECT`. The per-user lock is the first statement, so the count after it sees what the previous lock holder committed.
- **Events after commit.** If your checkout wraps `redeem()` in its own transaction and payment then fails, listeners never hear about a redemption that was rolled back.

## Proof

[`tests/Concurrency/RedemptionRaceTest.php`](tests/Concurrency/RedemptionRaceTest.php) runs on MySQL 8 and PostgreSQL 16 in CI:

| Scenario | Result |
|---|---|
| 150 parallel redemptions, `max_redemptions = 100` | exactly 100 applied, 50 refused |
| 30 parallel redemptions by one user, `max_per_user = 3` | exactly 3 applied |
| The same order redeemed 30 times in parallel | one redemption, every attempt gets it back |

Each attempt is a separate PHP process with its own database connection. Every worker boots and connects, then waits at a barrier; the test releases them together once all are ready. Without the barrier, process start-up time spreads the attempts out and the race never happens.

## Requirements

- PHP 8.2+ (8.3+ on Laravel 13)
- Laravel 12.71+ or 13.40+. Earlier releases, Laravel 11 included, have unpatched security advisories. Apps still on Laravel 11 can use v0.1.1.
- MySQL 8 or PostgreSQL 16 for the concurrency guarantees. The package runs on SQLite, but the concurrency tests do not.

## Installation

```bash
composer require abdalazeze/laravel-coupons
php artisan vendor:publish --tag=coupons-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=coupons-config
```

## Usage

### Money

All amounts are integers in minor units: `1500` is 15.00. Percentages are basis points: `1000` is 10%, `1250` is 12.5%. No floats anywhere. Percentage discounts round down, so rounding never costs you money. Fixed discounts are capped at the order amount.

### Create a coupon

```php
use Abdalazeze\Coupons\Enums\CouponType;
use Abdalazeze\Coupons\Models\Coupon;

Coupon::create([
    'code' => 'SUMMER10',          // stored uppercase, looked up case-insensitively
    'type' => CouponType::Percentage,
    'value' => 1000,               // 10%
    'max_redemptions' => 100,      // null = unlimited
    'max_per_user' => 1,           // null = unlimited
    'min_order_amount' => 5000,    // 50.00, null = no minimum
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
]);
```

A `fixed` coupon takes `value` in minor units and an optional `currency`. When a currency is set, it only applies to orders in that currency.

### Make your order redeemable

```php
use Abdalazeze\Coupons\Concerns\HasCoupons;
use Abdalazeze\Coupons\Contracts\Redeemable;

class Order extends Model implements Redeemable
{
    use HasCoupons;

    public function getCouponAmount(): int
    {
        return $this->subtotal; // minor units
    }

    public function getCouponCurrency(): ?string
    {
        return $this->currency; // ISO 4217, e.g. 'USD'
    }
}
```

### Validate, redeem, reverse

```php
use Abdalazeze\Coupons\Facades\Coupons;

// Check the rules and get the discount, without redeeming. Use it for a cart preview.
$result = Coupons::validate('SUMMER10', $order, $user);
$result->discountAmount; // int, minor units

// Redeem. Returns a CouponRedemption, or throws.
$redemption = Coupons::redeem('SUMMER10', $order, $user);
$redemption->discount_amount;

// Same thing through the trait.
$redemption = $order->applyCoupon('SUMMER10', $user);
$order->couponRedemptions;

// Order cancelled or refunded: release the slot.
Coupons::reverse($redemption);
```

The redeemer is always passed in, never read from `auth()`, because redemptions also run from queued jobs and webhooks.

Calling `redeem()` again for the same coupon and order returns the existing redemption, so retries are safe. `reverse()` is safe to call twice: parallel refund webhooks release the slot once.

### Errors

Every failure is a typed exception extending `CouponException`, carrying the data you need for a message:

| Exception | When | Data |
|---|---|---|
| `CouponNotFound` | no coupon with this code | `couponCode` |
| `CouponInactive` | `is_active` is false | `coupon` |
| `CouponNotYetActive` | before `starts_at` | `coupon` |
| `CouponExpired` | after `expires_at` | `coupon` |
| `MinimumOrderNotMet` | order below `min_order_amount` | `coupon`, `minimum`, `given` |
| `CurrencyMismatch` | fixed coupon, other currency | `coupon`, `expected`, `given` |
| `CouponLimitReached` | `max_redemptions` used up | `coupon`, `limit` |
| `UserCouponLimitReached` | this user hit `max_per_user` | `coupon`, `limit` |
| `RedemptionAlreadyReversed` | redeeming again for an order whose redemption was reversed | `redemption` |

```php
use Abdalazeze\Coupons\Exceptions\CouponException;

try {
    $order->applyCoupon($request->code, $request->user());
} catch (CouponException $e) {
    return back()->withErrors(['code' => $e->getMessage()]);
}
```

Messages are in English; translate them in your app.

### Events

- `CouponRedeemed` with `$event->redemption`
- `CouponRedemptionReversed` with `$event->redemption`

Both are dispatched after the database transaction commits.

### Custom discount calculation

Bind your own implementation of the `DiscountCalculator` contract:

```php
use Abdalazeze\Coupons\Contracts\DiscountCalculator;

$this->app->bind(DiscountCalculator::class, MyDiscountCalculator::class);
```

### In your app's tests

The package ships a factory with states for the common cases:

```php
Coupon::factory()->percentage()->withLimit(100)->create();
Coupon::factory()->fixed('EUR')->create(['value' => 500]);
Coupon::factory()->expired()->create();
Coupon::factory()->notStarted()->create();
Coupon::factory()->inactive()->create();
```

## What v0.1 does not do

Be clear about these before you rely on the package:

- **Stacking is not enforced.** The `stackable` column exists, but nothing stops an order from taking several coupons.
- **No product or category scoping.** A coupon applies to the whole order amount.
- **Reversal is final** for a coupon and order pair. Redeeming the same coupon on that order again throws `RedemptionAlreadyReversed`.
- No admin UI, cashback, gift cards or multi-tenancy.

## Testing

```bash
composer test       # SQLite; the concurrency suite is skipped
composer analyse    # PHPStan level 8
composer format     # Pint
```

To run the concurrency suite, point the tests at MySQL or PostgreSQL. The suite holds 150 connections open at once, so raise the server's limit:

```bash
docker run -d --rm -p 33306:3306 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=testing \
    mysql:8.0 --max-connections=500

DB_CONNECTION=mysql DB_PORT=33306 DB_PASSWORD=root vendor/bin/pest
```

```bash
docker run -d --rm -p 55432:5432 -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=testing \
    postgres:16 -c max_connections=300

DB_CONNECTION=pgsql DB_PORT=55432 DB_PASSWORD=postgres vendor/bin/pest
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security issues: see [SECURITY.md](SECURITY.md), please do not open a public issue.

## Need help integrating?

This package comes from a vouchers and cashback engine I built and ran in production for a booking platform. If you need coupons, promotions or a wallet wired into your Laravel app, [get in touch through GitHub](https://github.com/abdalazeze).

## License

MIT. See [LICENSE](LICENSE).

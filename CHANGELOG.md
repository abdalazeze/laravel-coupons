# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.2] - 2026-11-15

### Fixed

- The migrations now create the tables under the names in `coupons.table_names`. They hard-coded both names before, and the foreign key inferred `coupons`. Set the names before migrating and do not change them afterwards. Migrations you already published are your own copies and are not affected.
- The unique index on redemptions is named after its table. A fixed name collided on SQLite and PostgreSQL, where index names are schema-wide.
- `Coupon::redemptions()` and `CouponRedemption::coupon()` use the models in `coupons.models`. A swapped coupon model also broke `redemptions()`, because Laravel guessed the foreign key from the subclass name.

### Changed

- CI tests Laravel 11 and 12 on PHP 8.2, 8.3 and 8.4, plus a run on the lowest allowed dependency versions. Laravel 11 support was claimed before but never tested.

### Fixed

- `Coupon::factory()` threw a class-not-found error in apps that installed the package: the factory namespace was only in `autoload-dev`. It is now autoloaded in production, and CI checks it with a `--no-dev` autoloader.

## [0.1.0] - 2026-11-15

First release.

### Added

- `Coupon` and `CouponRedemption` models with publishable migrations and config.
- `Redeemable` contract — interface for the order object; provides `getCouponAmount()` and `getCouponCurrency()`.
- `DiscountCalculator` contract — swappable discount engine bound to `DefaultDiscountCalculator`.
- `DefaultDiscountCalculator` — fixed coupons cap at order amount; percentage uses basis points with floor rounding.
- `ValidationResult` readonly DTO — carries the resolved `Coupon` and the `discountAmount`.
- `CouponsManager::validate()` — runs all pre-lock rules (active, started, not expired, minimum order, currency) and returns a `ValidationResult`, or throws on the first failed rule.
- Typed exceptions: `CouponNotFound`, `CouponInactive`, `CouponExpired`, `CouponNotYetActive`, `MinimumOrderNotMet`, `CurrencyMismatch`, `CouponLimitReached`, `UserCouponLimitReached` — all extend `CouponException`.
- `CouponsManager::redeem()` — validates, then redeems in one transaction. The global limit is an atomic conditional `UPDATE`; the per-user limit locks the coupon row; redeeming the same coupon for the same redeemable again returns the existing redemption.
- `CouponsManager::reverse()` — marks a redemption `reversed` and releases its slot on the counter; reversing twice is a no-op.
- `RedemptionAlreadyReversed` exception — reversal is terminal for a coupon and redeemable pair.
- `CouponRedeemed` and `CouponRedemptionReversed` events, dispatched after the outermost transaction commits.
- `HasCoupons` trait — `$order->applyCoupon($code, $redeemer)` and a `couponRedemptions()` relation.
- Concurrency test suite: 150 parallel processes against a coupon limited to 100 apply exactly 100; also covers the per-user limit and parallel retries of the same order.

[Unreleased]: https://github.com/abdalazeze/laravel-coupons/compare/v0.1.2...HEAD
[0.1.2]: https://github.com/abdalazeze/laravel-coupons/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/abdalazeze/laravel-coupons/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/abdalazeze/laravel-coupons/releases/tag/v0.1.0

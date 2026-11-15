# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/abdalazeze/laravel-coupons/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/abdalazeze/laravel-coupons/releases/tag/v0.1.0

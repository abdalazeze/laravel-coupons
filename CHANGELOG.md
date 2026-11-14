# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `Redeemable` contract — interface for the order object; provides `getCouponAmount()` and `getCouponCurrency()`.
- `DiscountCalculator` contract — swappable discount engine bound to `DefaultDiscountCalculator`.
- `DefaultDiscountCalculator` — fixed coupons cap at order amount; percentage uses basis points with floor rounding.
- `ValidationResult` readonly DTO — carries the resolved `Coupon` and the `discountAmount`.
- `CouponsManager::validate()` — runs all pre-lock rules (active, started, not expired, minimum order, currency) and returns a `ValidationResult`, or throws on the first failed rule.
- Typed exceptions: `CouponNotFound`, `CouponInactive`, `CouponExpired`, `CouponNotYetActive`, `MinimumOrderNotMet`, `CurrencyMismatch`, `CouponLimitReached`, `UserCouponLimitReached` — all extend `CouponException`.

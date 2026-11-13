# Contributing and code style

## Before you push

```bash
composer format      # Pint fixes style
composer analyse     # PHPStan level 8, must be clean
composer test        # Pest
```

CI runs the same three. A red check blocks the merge.

## PHP

- `declare(strict_types=1);` in every PHP file (Pint enforces it).
- Every parameter, property and return has a type. Use PHPDoc only for what PHP types cannot say: generics (`Collection<int, Coupon>`), array shapes, `@throws`.
- Classes are `final` by default. Exceptions: models, and anything the config lets the app replace. Those stay open and are documented as extension points.
- `readonly` for value objects and DTOs. Constructor property promotion where it reads cleanly.
- Enums for fixed sets of values (`CouponType`, `RedemptionStatus`). No string constants.
- Early returns over nested `if`. No `else` after `return` or `throw`.
- No `env()` outside `config/`. Read settings through `config('coupons.*')`.

## Money and numbers

- Money is an `int` in minor units (cents). Never a float, never a decimal column read as float.
- Percentages are basis points: `1250` means 12.5%.
- Rounding happens in one place, the discount calculator, and it is tested.

## Laravel inside a package

- No facades inside `src/`. Inject contracts through the constructor. The `Coupons` facade exists only as public sugar for users of the package.
- No request-bound helpers (`auth()`, `request()`, `session()`): the redeemer is always passed in explicitly. Time comes from `Carbon::now()` so tests can freeze it with `Carbon::setTestNow()`.
- Table and model names always come from config, never hard-coded.
- Every write that touches a counter runs inside `DB::transaction()` on the model's connection.
- Migrations are published, not auto-loaded.

## Errors

- One typed exception per business rule: `CouponExpired`, `CouponLimitReached`, `MinimumOrderNotMet`, and so on. All extend `CouponException`.
- Exceptions carry the data the caller needs to show a message (the coupon, the limit, the minimum). Messages are in English; apps translate them.
- Never catch an exception just to return `false`.

## Naming

- Classes: nouns (`DiscountCalculator`, `CouponRedemption`). Actions: verb phrases (`RedeemCoupon`, `ReverseRedemption`).
- Methods that throw on failure: `redeem()`, `validate()`. Methods that return a bool: `isActive()`, `hasExpired()`, `canBeRedeemedBy()`.
- Events in past tense: `CouponRedeemed`, `CouponRedemptionReversed`.
- Database columns in `snake_case`, timestamps end in `_at`, counters end in `_count`.

## Tests

- Pest, `it('...')` sentences that read as behaviour: `it('refuses a coupon after its expiry date')`.
- Redemption logic is written test first.
- One behaviour per test. Arrange with factories, not raw inserts.
- Concurrency tests live in `tests/Concurrency`, run against MySQL and PostgreSQL only, and are skipped on SQLite.
- A README claim needs a test that proves it.

## Commits

[Conventional Commits](https://www.conventionalcommits.org/), small and focused, one concern each:

```
feat: add per-user redemption limit
fix: release counter when redemption is reversed
test: cover 150 parallel redemptions on MySQL
docs: explain the race condition in README
chore: bump testbench
refactor: extract discount rounding
```

Subject in the imperative, under 72 characters. The body says why, when the why is not obvious.

## Pull requests

- One feature or fix per PR.
- Describe the behaviour change and how it is tested.
- Update `CHANGELOG.md` under `Unreleased`.

<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Contracts\Redeemable;
use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Data\ValidationResult;
use Abdalazeze\Coupons\Exceptions\CouponExpired;
use Abdalazeze\Coupons\Exceptions\CouponInactive;
use Abdalazeze\Coupons\Exceptions\CouponNotFound;
use Abdalazeze\Coupons\Exceptions\CouponNotYetActive;
use Abdalazeze\Coupons\Exceptions\CurrencyMismatch;
use Abdalazeze\Coupons\Exceptions\MinimumOrderNotMet;
use Abdalazeze\Coupons\Models\Coupon;
use Abdalazeze\Coupons\Tests\Models\User;
use Carbon\Carbon;

// Anonymous Redeemable so tests do not need a full Eloquent model for the order.
function makeRedeemable(int $amount, ?string $currency = null): Redeemable
{
    return new class($amount, $currency) implements Redeemable
    {
        public function __construct(
            private readonly int $amount,
            private readonly ?string $currency,
        ) {}

        public function getCouponAmount(): int
        {
            return $this->amount;
        }

        public function getCouponCurrency(): ?string
        {
            return $this->currency;
        }
    };
}

it('refuses a coupon code that does not exist', function (): void {
    expect(fn () => app(CouponsManager::class)->validate('NOSUCHCODE', makeRedeemable(1000), new User))
        ->toThrow(CouponNotFound::class);
});

it('refuses an inactive coupon', function (): void {
    $coupon = Coupon::factory()->inactive()->create();

    expect(fn () => app(CouponsManager::class)->validate($coupon->code, makeRedeemable(1000), new User))
        ->toThrow(CouponInactive::class);
});

it('refuses an expired coupon', function (): void {
    Carbon::setTestNow(now());
    $coupon = Coupon::factory()->expired()->create();

    expect(fn () => app(CouponsManager::class)->validate($coupon->code, makeRedeemable(1000), new User))
        ->toThrow(CouponExpired::class);

    Carbon::setTestNow();
});

it('refuses a coupon that has not started yet', function (): void {
    Carbon::setTestNow(now());
    $coupon = Coupon::factory()->notStarted()->create();

    expect(fn () => app(CouponsManager::class)->validate($coupon->code, makeRedeemable(1000), new User))
        ->toThrow(CouponNotYetActive::class);

    Carbon::setTestNow();
});

it('refuses when the order amount is below the coupon minimum', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['min_order_amount' => 1000]);

    expect(fn () => app(CouponsManager::class)->validate($coupon->code, makeRedeemable(999), new User))
        ->toThrow(MinimumOrderNotMet::class);
});

it('accepts when the order amount exactly meets the minimum', function (): void {
    $coupon = Coupon::factory()->fixed('USD')->create(['min_order_amount' => 1000, 'value' => 100]);

    $result = app(CouponsManager::class)->validate($coupon->code, makeRedeemable(1000, 'USD'), new User);

    expect($result)->toBeInstanceOf(ValidationResult::class)
        ->and($result->discountAmount)->toBe(100);
});

it('refuses a fixed coupon when the order currency does not match', function (): void {
    $coupon = Coupon::factory()->fixed('USD')->create();

    expect(fn () => app(CouponsManager::class)->validate($coupon->code, makeRedeemable(1000, 'EUR'), new User))
        ->toThrow(CurrencyMismatch::class);
});

it('refuses a fixed coupon when the order provides no currency', function (): void {
    $coupon = Coupon::factory()->fixed('USD')->create();

    expect(fn () => app(CouponsManager::class)->validate($coupon->code, makeRedeemable(1000, null), new User))
        ->toThrow(CurrencyMismatch::class);
});

it('skips the currency check for percentage coupons', function (): void {
    $coupon = Coupon::factory()->percentage()->create();

    $result = app(CouponsManager::class)->validate($coupon->code, makeRedeemable(5000, 'EUR'), new User);

    expect($result)->toBeInstanceOf(ValidationResult::class);
});

it('skips the currency check for fixed coupons with no currency restriction', function (): void {
    $coupon = Coupon::factory()->fixed()->create(['currency' => null]);

    $result = app(CouponsManager::class)->validate($coupon->code, makeRedeemable(5000, 'EUR'), new User);

    expect($result)->toBeInstanceOf(ValidationResult::class);
});

it('returns a ValidationResult carrying the coupon and calculated discount', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['value' => 1000]); // 10 %

    $result = app(CouponsManager::class)->validate($coupon->code, makeRedeemable(5000), new User);

    expect($result->coupon->id)->toBe($coupon->id)
        ->and($result->discountAmount)->toBe(500);
});

it('looks up coupon codes case-insensitively', function (): void {
    Coupon::factory()->percentage()->create(['code' => 'SUMMER10']);

    $result = app(CouponsManager::class)->validate('summer10', makeRedeemable(1000), new User);

    expect($result)->toBeInstanceOf(ValidationResult::class);
});

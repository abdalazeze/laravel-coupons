<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Contracts\DiscountCalculator;
use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Models\Coupon;

it('uses the discount calculator the app binds', function (): void {
    app()->bind(DiscountCalculator::class, fn () => new class implements DiscountCalculator
    {
        public function calculate(Coupon $coupon, int $orderAmount): int
        {
            return 42;
        }
    });
    $coupon = Coupon::factory()->percentage()->create();

    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    expect($redemption->discount_amount)->toBe(42);
});

<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Calculators\DefaultDiscountCalculator;
use Abdalazeze\Coupons\Models\Coupon;

it('calculates a fixed discount', function (): void {
    $coupon = Coupon::factory()->fixed()->create(['value' => 500]);
    $calculator = new DefaultDiscountCalculator;

    expect($calculator->calculate($coupon, 2000))->toBe(500);
});

it('caps a fixed discount at the order amount', function (): void {
    $coupon = Coupon::factory()->fixed()->create(['value' => 2000]);
    $calculator = new DefaultDiscountCalculator;

    expect($calculator->calculate($coupon, 500))->toBe(500);
});

it('calculates a percentage discount in basis points', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['value' => 1000]); // 10 %
    $calculator = new DefaultDiscountCalculator;

    expect($calculator->calculate($coupon, 5000))->toBe(500);
});

it('floors the percentage calculation — caller bears rounding risk', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['value' => 1000]); // 10 %
    $calculator = new DefaultDiscountCalculator;

    // 10 % of 999 = 99.9 → floor → 99
    expect($calculator->calculate($coupon, 999))->toBe(99);
});

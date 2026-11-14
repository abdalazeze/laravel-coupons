<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Calculators;

use Abdalazeze\Coupons\Contracts\DiscountCalculator;
use Abdalazeze\Coupons\Enums\CouponType;
use Abdalazeze\Coupons\Models\Coupon;

final class DefaultDiscountCalculator implements DiscountCalculator
{
    public function calculate(Coupon $coupon, int $orderAmount): int
    {
        return match ($coupon->type) {
            // Cap at order amount so the total can never go negative.
            CouponType::Fixed => min($coupon->value, $orderAmount),
            // Floor the result: the caller bears rounding risk, never the customer.
            CouponType::Percentage => (int) floor($orderAmount * $coupon->value / 10_000),
        };
    }
}

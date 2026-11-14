<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Contracts;

use Abdalazeze\Coupons\Models\Coupon;

interface DiscountCalculator
{
    /** Returns the discount amount in minor units. */
    public function calculate(Coupon $coupon, int $orderAmount): int;
}

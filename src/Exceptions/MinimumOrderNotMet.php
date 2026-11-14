<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Exceptions;

use Abdalazeze\Coupons\Models\Coupon;

final class MinimumOrderNotMet extends CouponException
{
    public function __construct(
        public readonly Coupon $coupon,
        public readonly int $minimum,
        public readonly int $given,
    ) {
        parent::__construct(
            "Order amount {$given} does not meet the minimum of {$minimum} required by coupon '{$coupon->code}'."
        );
    }
}

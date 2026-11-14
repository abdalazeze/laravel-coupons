<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Exceptions;

use Abdalazeze\Coupons\Models\Coupon;

final class CouponLimitReached extends CouponException
{
    public function __construct(
        public readonly Coupon $coupon,
        public readonly int $limit,
    ) {
        parent::__construct(
            "Coupon '{$coupon->code}' has reached its redemption limit of {$limit}."
        );
    }
}

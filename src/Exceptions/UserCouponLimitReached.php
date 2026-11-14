<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Exceptions;

use Abdalazeze\Coupons\Models\Coupon;

final class UserCouponLimitReached extends CouponException
{
    public function __construct(
        public readonly Coupon $coupon,
        public readonly int $limit,
    ) {
        parent::__construct(
            "You have already used coupon '{$coupon->code}' the maximum of {$limit} time(s)."
        );
    }
}

<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Exceptions;

use Abdalazeze\Coupons\Models\Coupon;

final class CouponInactive extends CouponException
{
    public function __construct(public readonly Coupon $coupon)
    {
        parent::__construct("Coupon '{$coupon->code}' is not active.");
    }
}

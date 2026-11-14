<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Exceptions;

final class CouponNotFound extends CouponException
{
    public function __construct(public readonly string $couponCode)
    {
        parent::__construct("Coupon '{$couponCode}' not found.");
    }
}

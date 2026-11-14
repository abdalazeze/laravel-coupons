<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Exceptions;

use Abdalazeze\Coupons\Models\Coupon;

final class CurrencyMismatch extends CouponException
{
    public function __construct(
        public readonly Coupon $coupon,
        public readonly string $expected,
        public readonly ?string $given,
    ) {
        $givenLabel = $given ?? 'none';

        parent::__construct(
            "Coupon '{$coupon->code}' requires currency {$expected}, got {$givenLabel}."
        );
    }
}

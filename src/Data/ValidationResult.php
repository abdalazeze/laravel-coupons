<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Data;

use Abdalazeze\Coupons\Models\Coupon;

final readonly class ValidationResult
{
    public function __construct(
        public Coupon $coupon,
        public int $discountAmount,
    ) {}
}

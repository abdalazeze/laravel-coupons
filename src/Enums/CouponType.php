<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Enums;

enum CouponType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}

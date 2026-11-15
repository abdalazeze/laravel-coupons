<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Events;

use Abdalazeze\Coupons\Models\CouponRedemption;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class CouponRedemptionReversed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public CouponRedemption $redemption,
    ) {}
}

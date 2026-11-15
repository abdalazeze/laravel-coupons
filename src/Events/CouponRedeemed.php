<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Events;

use Abdalazeze\Coupons\Models\CouponRedemption;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

// After-commit: if the app wraps redeem() in its own transaction that later rolls
// back, listeners (emails, analytics) must never hear about a redemption that did not happen.
final readonly class CouponRedeemed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public CouponRedemption $redemption,
    ) {}
}

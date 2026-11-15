<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Exceptions;

use Abdalazeze\Coupons\Models\CouponRedemption;

// Reversal is terminal for a coupon and redeemable pair: the unique index allows one
// row per pair, and reviving it in place would need its own race handling for no
// real use case (reversal means the order was cancelled or refunded).
final class RedemptionAlreadyReversed extends CouponException
{
    public function __construct(
        public readonly CouponRedemption $redemption,
    ) {
        parent::__construct(
            "Coupon redemption #{$redemption->getKey()} was reversed and cannot be applied to the same order again."
        );
    }
}

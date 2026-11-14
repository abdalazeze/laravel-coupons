<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Contracts;

interface Redeemable
{
    /** Order amount in minor units (e.g. 1500 = $15.00). */
    public function getCouponAmount(): int;

    /** ISO 4217 currency code, or null for currency-agnostic amounts. */
    public function getCouponCurrency(): ?string;
}

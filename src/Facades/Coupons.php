<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Facades;

use Abdalazeze\Coupons\Contracts\Redeemable;
use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Data\ValidationResult;
use Abdalazeze\Coupons\Models\CouponRedemption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @see CouponsManager
 *
 * @method static ValidationResult validate(string $code, Redeemable $redeemable, Model $redeemer)
 * @method static CouponRedemption redeem(string $code, Model&Redeemable $redeemable, Model $redeemer)
 */
final class Coupons extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'coupons';
    }
}

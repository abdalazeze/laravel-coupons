<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Concerns;

use Abdalazeze\Coupons\Contracts\Redeemable;
use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Exceptions\CouponException;
use Abdalazeze\Coupons\Models\CouponRedemption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * For the model a coupon is applied to (an order, a booking). The model must implement Redeemable.
 *
 * @mixin Model
 */
trait HasCoupons
{
    /**
     * The redeemer is explicit, never read from auth(): redemptions also run in
     * queued jobs and webhooks, where there is no authenticated user.
     *
     * @throws CouponException
     */
    public function applyCoupon(string $code, Model $redeemer): CouponRedemption
    {
        if (! $this instanceof Redeemable) {
            throw new LogicException(static::class.' must implement '.Redeemable::class.' to use HasCoupons.');
        }

        // Models cannot take constructor injection, so the container is the only way in.
        return app(CouponsManager::class)->redeem($code, $this, $redeemer);
    }

    /** @return MorphMany<CouponRedemption, $this> */
    public function couponRedemptions(): MorphMany
    {
        /** @var class-string<CouponRedemption> $model */
        $model = config('coupons.models.redemption', CouponRedemption::class);

        return $this->morphMany($model, 'redeemable');
    }
}

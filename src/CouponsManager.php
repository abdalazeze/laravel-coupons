<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons;

use Abdalazeze\Coupons\Contracts\DiscountCalculator;
use Abdalazeze\Coupons\Contracts\Redeemable;
use Abdalazeze\Coupons\Data\ValidationResult;
use Abdalazeze\Coupons\Enums\CouponType;
use Abdalazeze\Coupons\Exceptions\CouponException;
use Abdalazeze\Coupons\Exceptions\CouponExpired;
use Abdalazeze\Coupons\Exceptions\CouponInactive;
use Abdalazeze\Coupons\Exceptions\CouponNotFound;
use Abdalazeze\Coupons\Exceptions\CouponNotYetActive;
use Abdalazeze\Coupons\Exceptions\CurrencyMismatch;
use Abdalazeze\Coupons\Exceptions\MinimumOrderNotMet;
use Abdalazeze\Coupons\Models\Coupon;
use Illuminate\Database\Eloquent\Model;

final class CouponsManager
{
    public function __construct(
        private readonly DiscountCalculator $calculator,
    ) {}

    /**
     * Runs all pre-lock validation rules and returns the calculated discount.
     * Throws a typed CouponException on the first rule that fails.
     *
     * @throws CouponException
     */
    public function validate(string $code, Redeemable $redeemable, Model $redeemer): ValidationResult
    {
        $coupon = $this->findCoupon($code);

        $this->assertActive($coupon);
        $this->assertStarted($coupon);
        $this->assertNotExpired($coupon);
        $this->assertMinimumOrder($coupon, $redeemable);
        $this->assertCurrencyMatch($coupon, $redeemable);

        $discount = $this->calculator->calculate($coupon, $redeemable->getCouponAmount());

        return new ValidationResult($coupon, $discount);
    }

    private function findCoupon(string $code): Coupon
    {
        /** @var class-string<Coupon> $model */
        $model = config('coupons.models.coupon', Coupon::class);

        $coupon = $model::query()
            ->where('code', mb_strtoupper($code))
            ->first();

        if (! $coupon instanceof Coupon) {
            throw new CouponNotFound($code);
        }

        return $coupon;
    }

    private function assertActive(Coupon $coupon): void
    {
        if (! $coupon->isActive()) {
            throw new CouponInactive($coupon);
        }
    }

    private function assertStarted(Coupon $coupon): void
    {
        if (! $coupon->hasStarted()) {
            throw new CouponNotYetActive($coupon);
        }
    }

    private function assertNotExpired(Coupon $coupon): void
    {
        if ($coupon->hasExpired()) {
            throw new CouponExpired($coupon);
        }
    }

    private function assertMinimumOrder(Coupon $coupon, Redeemable $redeemable): void
    {
        if ($coupon->min_order_amount !== null && $redeemable->getCouponAmount() < $coupon->min_order_amount) {
            throw new MinimumOrderNotMet($coupon, $coupon->min_order_amount, $redeemable->getCouponAmount());
        }
    }

    private function assertCurrencyMatch(Coupon $coupon, Redeemable $redeemable): void
    {
        if ($coupon->type !== CouponType::Fixed || $coupon->currency === null) {
            return;
        }

        $given = $redeemable->getCouponCurrency();

        if ($given !== $coupon->currency) {
            throw new CurrencyMismatch($coupon, $coupon->currency, $given);
        }
    }
}

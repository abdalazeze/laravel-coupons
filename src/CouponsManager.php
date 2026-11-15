<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons;

use Abdalazeze\Coupons\Contracts\DiscountCalculator;
use Abdalazeze\Coupons\Contracts\Redeemable;
use Abdalazeze\Coupons\Data\ValidationResult;
use Abdalazeze\Coupons\Enums\CouponType;
use Abdalazeze\Coupons\Enums\RedemptionStatus;
use Abdalazeze\Coupons\Events\CouponRedeemed;
use Abdalazeze\Coupons\Exceptions\CouponException;
use Abdalazeze\Coupons\Exceptions\CouponExpired;
use Abdalazeze\Coupons\Exceptions\CouponInactive;
use Abdalazeze\Coupons\Exceptions\CouponLimitReached;
use Abdalazeze\Coupons\Exceptions\CouponNotFound;
use Abdalazeze\Coupons\Exceptions\CouponNotYetActive;
use Abdalazeze\Coupons\Exceptions\CurrencyMismatch;
use Abdalazeze\Coupons\Exceptions\MinimumOrderNotMet;
use Abdalazeze\Coupons\Exceptions\UserCouponLimitReached;
use Abdalazeze\Coupons\Models\Coupon;
use Abdalazeze\Coupons\Models\CouponRedemption;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

final class CouponsManager
{
    public function __construct(
        private readonly DiscountCalculator $calculator,
        private readonly Dispatcher $events,
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

    /**
     * Validates and redeems the coupon for the given redeemable, safe under concurrency.
     * Redeeming the same coupon for the same redeemable again returns the existing redemption.
     *
     * @throws CouponException
     */
    public function redeem(string $code, Model&Redeemable $redeemable, Model $redeemer): CouponRedemption
    {
        $result = $this->validate($code, $redeemable, $redeemer);

        try {
            $redemption = $result->coupon->getConnection()->transaction(
                fn (): CouponRedemption => $this->redeemInTransaction($result, $redeemable, $redeemer),
            );
        } catch (UniqueConstraintViolationException $e) {
            // A parallel request for the same redeemable inserted first. Our transaction,
            // counter increment included, was rolled back, so we return the winner's row.
            // Caught outside the closure: on PostgreSQL a failed statement poisons the transaction.
            return $this->findRedemption($result->coupon, $redeemable) ?? throw $e;
        }

        if ($redemption->wasRecentlyCreated) {
            $this->events->dispatch(new CouponRedeemed($redemption));
        }

        return $redemption;
    }

    /** @throws CouponException */
    private function redeemInTransaction(ValidationResult $result, Model&Redeemable $redeemable, Model $redeemer): CouponRedemption
    {
        $coupon = $result->coupon;

        // Lock only when a per-user limit exists: it is the one rule that needs read-then-write.
        // It must be the transaction's first statement. On MySQL REPEATABLE READ the snapshot
        // is taken at the first plain SELECT, and a snapshot older than the lock would miss
        // redemptions committed by the transaction we just waited for.
        if ($coupon->max_per_user !== null) {
            $coupon = $this->lockCoupon($coupon);
        }

        // Fast path for retries. Checked before the limits, so a retry after the coupon
        // ran out still returns the redemption the caller already has.
        $existing = $this->findRedemption($coupon, $redeemable);

        if ($existing !== null) {
            return $existing;
        }

        $this->assertUserLimit($coupon, $redeemer);

        // Claim the slot before inserting. The insert's foreign key takes a shared lock
        // on the coupon row; taking it first and upgrading to exclusive in the UPDATE
        // would deadlock two parallel transactions on MySQL.
        $this->claimGlobalSlot($coupon);

        return $this->redemptionModel()::query()->create([
            'coupon_id' => $coupon->getKey(),
            'redeemer_type' => $redeemer->getMorphClass(),
            'redeemer_id' => $redeemer->getKey(),
            'redeemable_type' => $redeemable->getMorphClass(),
            'redeemable_id' => $redeemable->getKey(),
            'discount_amount' => $result->discountAmount,
            'status' => RedemptionStatus::Applied,
        ]);
    }

    private function lockCoupon(Coupon $coupon): Coupon
    {
        /** @var Coupon */
        return $coupon->newQuery()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
    }

    private function findRedemption(Coupon $coupon, Model $redeemable): ?CouponRedemption
    {
        return $this->redemptionModel()::query()
            ->where('coupon_id', $coupon->getKey())
            ->whereMorphedTo('redeemable', $redeemable)
            ->first();
    }

    /** @throws UserCouponLimitReached */
    private function assertUserLimit(Coupon $coupon, Model $redeemer): void
    {
        if ($coupon->max_per_user === null) {
            return;
        }

        $used = $this->redemptionModel()::query()
            ->where('coupon_id', $coupon->getKey())
            ->whereMorphedTo('redeemer', $redeemer)
            ->where('status', RedemptionStatus::Applied)
            ->count();

        if ($used >= $coupon->max_per_user) {
            throw new UserCouponLimitReached($coupon, $coupon->max_per_user);
        }
    }

    /**
     * One atomic conditional UPDATE: the database checks the limit and increments in
     * the same statement, so there is no window between reading the count and writing it.
     *
     * @throws CouponLimitReached
     */
    private function claimGlobalSlot(Coupon $coupon): void
    {
        $claimed = $coupon->newQuery()
            ->whereKey($coupon->getKey())
            ->where(fn (Builder $query) => $query
                ->whereNull('max_redemptions')
                ->orWhereColumn('redemptions_count', '<', 'max_redemptions'))
            ->increment('redemptions_count');

        if ($claimed === 0) {
            throw new CouponLimitReached($coupon, (int) $coupon->max_redemptions);
        }
    }

    /** @return class-string<CouponRedemption> */
    private function redemptionModel(): string
    {
        return config('coupons.models.redemption', CouponRedemption::class);
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

<?php

declare(strict_types=1);

use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Enums\RedemptionStatus;
use Abdalazeze\Coupons\Events\CouponRedeemed;
use Abdalazeze\Coupons\Exceptions\CouponExpired;
use Abdalazeze\Coupons\Exceptions\CouponLimitReached;
use Abdalazeze\Coupons\Exceptions\UserCouponLimitReached;
use Abdalazeze\Coupons\Models\Coupon;
use Abdalazeze\Coupons\Models\CouponRedemption;
use Illuminate\Support\Facades\Event;

it('records an applied redemption with the calculated discount', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['value' => 1000]); // 10 %
    $order = createOrder(5000);
    $user = createUser();

    $redemption = app(CouponsManager::class)->redeem($coupon->code, $order, $user);

    expect($redemption->exists)->toBeTrue()
        ->and($redemption->coupon_id)->toBe($coupon->id)
        ->and($redemption->status)->toBe(RedemptionStatus::Applied)
        ->and($redemption->discount_amount)->toBe(500)
        ->and($redemption->redeemable->is($order))->toBeTrue()
        ->and($redemption->redeemer->is($user))->toBeTrue();
});

it('increments the coupon redemption counter', function (): void {
    $coupon = Coupon::factory()->percentage()->create();

    app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    expect($coupon->fresh()?->redemptions_count)->toBe(1);
});

it('runs the validation rules before redeeming', function (): void {
    $coupon = Coupon::factory()->percentage()->expired()->create();

    expect(fn () => app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser()))
        ->toThrow(CouponExpired::class);

    expect(CouponRedemption::query()->count())->toBe(0);
});

it('refuses a redemption once the global limit is reached', function (): void {
    $coupon = Coupon::factory()->percentage()->withLimit(1)->create();
    app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    expect(fn () => app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser()))
        ->toThrow(CouponLimitReached::class);

    expect($coupon->fresh()?->redemptions_count)->toBe(1)
        ->and(CouponRedemption::query()->count())->toBe(1);
});

it('allows any number of redemptions when the coupon has no global limit', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['max_redemptions' => null]);

    foreach (range(1, 5) as $ignored) {
        app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());
    }

    expect($coupon->fresh()?->redemptions_count)->toBe(5);
});

it('refuses a redemption once the redeemer reached the per-user limit', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['max_per_user' => 2]);
    $user = createUser();
    app(CouponsManager::class)->redeem($coupon->code, createOrder(), $user);
    app(CouponsManager::class)->redeem($coupon->code, createOrder(), $user);

    expect(fn () => app(CouponsManager::class)->redeem($coupon->code, createOrder(), $user))
        ->toThrow(UserCouponLimitReached::class);

    expect($coupon->fresh()?->redemptions_count)->toBe(2);
});

it('applies the per-user limit to each redeemer separately', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['max_per_user' => 1]);
    app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    expect($redemption->status)->toBe(RedemptionStatus::Applied);
});

it('returns the existing redemption when the same coupon is applied to the same order twice', function (): void {
    $coupon = Coupon::factory()->percentage()->create();
    $order = createOrder();
    $user = createUser();

    $first = app(CouponsManager::class)->redeem($coupon->code, $order, $user);
    $second = app(CouponsManager::class)->redeem($coupon->code, $order, $user);

    expect($second->id)->toBe($first->id)
        ->and($coupon->fresh()?->redemptions_count)->toBe(1)
        ->and(CouponRedemption::query()->count())->toBe(1);
});

it('returns the existing redemption on a retry even after the global limit is reached', function (): void {
    $coupon = Coupon::factory()->percentage()->withLimit(1)->create();
    $order = createOrder();
    $user = createUser();
    $first = app(CouponsManager::class)->redeem($coupon->code, $order, $user);

    $retry = app(CouponsManager::class)->redeem($coupon->code, $order, $user);

    expect($retry->id)->toBe($first->id);
});

it('returns the existing redemption on a retry even after the per-user limit is reached', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['max_per_user' => 1]);
    $order = createOrder();
    $user = createUser();
    $first = app(CouponsManager::class)->redeem($coupon->code, $order, $user);

    $retry = app(CouponsManager::class)->redeem($coupon->code, $order, $user);

    expect($retry->id)->toBe($first->id);
});

it('fires CouponRedeemed once for a new redemption', function (): void {
    Event::fake([CouponRedeemed::class]);
    $coupon = Coupon::factory()->percentage()->create();
    $order = createOrder();
    $user = createUser();

    $redemption = app(CouponsManager::class)->redeem($coupon->code, $order, $user);
    app(CouponsManager::class)->redeem($coupon->code, $order, $user);

    Event::assertDispatchedTimes(CouponRedeemed::class, 1);
    Event::assertDispatched(
        CouponRedeemed::class,
        fn (CouponRedeemed $event): bool => $event->redemption->is($redemption),
    );
});

it('does not fire CouponRedeemed when the redemption is refused', function (): void {
    Event::fake([CouponRedeemed::class]);
    $coupon = Coupon::factory()->percentage()->withLimit(0)->create();

    try {
        app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());
    } catch (CouponLimitReached) {
    }

    Event::assertNotDispatched(CouponRedeemed::class);
});

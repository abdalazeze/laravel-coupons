<?php

declare(strict_types=1);

use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Enums\RedemptionStatus;
use Abdalazeze\Coupons\Events\CouponRedemptionReversed;
use Abdalazeze\Coupons\Exceptions\RedemptionAlreadyReversed;
use Abdalazeze\Coupons\Models\Coupon;
use Illuminate\Support\Facades\Event;

it('marks the redemption reversed and releases its slot on the counter', function (): void {
    $coupon = Coupon::factory()->percentage()->create();
    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    $reversed = app(CouponsManager::class)->reverse($redemption);

    expect($reversed->status)->toBe(RedemptionStatus::Reversed)
        ->and($coupon->fresh()?->redemptions_count)->toBe(0);
});

it('fires CouponRedemptionReversed', function (): void {
    Event::fake([CouponRedemptionReversed::class]);
    $coupon = Coupon::factory()->percentage()->create();
    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    app(CouponsManager::class)->reverse($redemption);

    Event::assertDispatched(
        CouponRedemptionReversed::class,
        fn (CouponRedemptionReversed $event): bool => $event->redemption->is($redemption),
    );
});

it('treats reversing an already reversed redemption as a no-op', function (): void {
    Event::fake([CouponRedemptionReversed::class]);
    $coupon = Coupon::factory()->percentage()->create();
    app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());
    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    app(CouponsManager::class)->reverse($redemption);
    app(CouponsManager::class)->reverse($redemption);

    expect($coupon->fresh()?->redemptions_count)->toBe(1);
    Event::assertDispatchedTimes(CouponRedemptionReversed::class, 1);
});

it('lets another order use the slot a reversal released', function (): void {
    $coupon = Coupon::factory()->percentage()->withLimit(1)->create();
    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());
    app(CouponsManager::class)->reverse($redemption);

    $next = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    expect($next->status)->toBe(RedemptionStatus::Applied);
});

it('does not count reversed redemptions towards the per-user limit', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['max_per_user' => 1]);
    $user = createUser();
    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), $user);
    app(CouponsManager::class)->reverse($redemption);

    $next = app(CouponsManager::class)->redeem($coupon->code, createOrder(), $user);

    expect($next->status)->toBe(RedemptionStatus::Applied);
});

it('refuses to redeem a coupon again for an order whose redemption was reversed', function (): void {
    $coupon = Coupon::factory()->percentage()->create();
    $order = createOrder();
    $user = createUser();
    app(CouponsManager::class)->reverse(app(CouponsManager::class)->redeem($coupon->code, $order, $user));

    expect(fn () => app(CouponsManager::class)->redeem($coupon->code, $order, $user))
        ->toThrow(RedemptionAlreadyReversed::class);

    expect($coupon->fresh()?->redemptions_count)->toBe(0);
});

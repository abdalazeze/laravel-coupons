<?php

declare(strict_types=1);

use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Models\Coupon;
use Abdalazeze\Coupons\Tests\Models\CustomCoupon;
use Abdalazeze\Coupons\Tests\Models\CustomRedemption;

beforeEach(function (): void {
    config()->set('coupons.models', [
        'coupon' => CustomCoupon::class,
        'redemption' => CustomRedemption::class,
    ]);
});

it('looks up coupons and records redemptions with the configured models', function (): void {
    $coupon = Coupon::factory()->percentage()->create();

    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    expect($redemption)->toBeInstanceOf(CustomRedemption::class)
        ->and($redemption->coupon)->toBeInstanceOf(CustomCoupon::class);
});

it('resolves redemptions from a configured coupon model', function (): void {
    $coupon = Coupon::factory()->percentage()->create();
    app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    $redemptions = CustomCoupon::query()->findOrFail($coupon->id)->redemptions;

    expect($redemptions)->toHaveCount(1)
        ->and($redemptions->first())->toBeInstanceOf(CustomRedemption::class);
});

it('exposes the configured redemption model through HasCoupons', function (): void {
    $order = createOrder();
    $order->applyCoupon(Coupon::factory()->percentage()->create()->code, createUser());

    expect($order->couponRedemptions()->first())->toBeInstanceOf(CustomRedemption::class);
});

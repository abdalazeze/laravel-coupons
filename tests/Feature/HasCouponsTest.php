<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Enums\RedemptionStatus;
use Abdalazeze\Coupons\Models\Coupon;

it('redeems a coupon for the model it is applied to', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['value' => 1000]); // 10 %
    $order = createOrder(5000);
    $user = createUser();

    $redemption = $order->applyCoupon($coupon->code, $user);

    expect($redemption->status)->toBe(RedemptionStatus::Applied)
        ->and($redemption->discount_amount)->toBe(500)
        ->and($redemption->redeemable->is($order))->toBeTrue()
        ->and($redemption->redeemer->is($user))->toBeTrue();
});

it('exposes the redemptions applied to the model', function (): void {
    $order = createOrder();
    $user = createUser();
    $order->applyCoupon(Coupon::factory()->percentage()->create()->code, $user);
    $order->applyCoupon(Coupon::factory()->percentage()->create()->code, $user);
    createOrder()->applyCoupon(Coupon::factory()->percentage()->create()->code, $user);

    expect($order->couponRedemptions()->count())->toBe(2);
});

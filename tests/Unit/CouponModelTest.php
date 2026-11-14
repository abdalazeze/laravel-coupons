<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Enums\CouponType;
use Abdalazeze\Coupons\Models\Coupon;
use Carbon\Carbon;

it('stores and retrieves a coupon', function (): void {
    $coupon = Coupon::factory()->fixed()->create(['code' => 'SAVE20']);

    expect($coupon->fresh())
        ->code->toBe('SAVE20')
        ->type->toBe(CouponType::Fixed)
        ->is_active->toBeTrue();
});

it('normalises the coupon code to uppercase on write', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'summer10']);

    expect($coupon->code)->toBe('SUMMER10');
    expect(Coupon::query()->where('code', 'SUMMER10')->exists())->toBeTrue();
});

it('casts the type column to a CouponType enum', function (): void {
    $coupon = Coupon::factory()->percentage()->create();

    expect($coupon->type)->toBe(CouponType::Percentage);
});

it('casts value and counters to integers', function (): void {
    $coupon = Coupon::factory()->fixed()->create([
        'value' => 500,
        'max_redemptions' => 10,
        'redemptions_count' => 0,
    ]);

    expect($coupon->value)->toBe(500)
        ->and($coupon->max_redemptions)->toBe(10)
        ->and($coupon->redemptions_count)->toBe(0);
});

it('detects an expired coupon', function (): void {
    Carbon::setTestNow(now());
    $coupon = Coupon::factory()->expired()->create();

    expect($coupon->hasExpired())->toBeTrue();

    Carbon::setTestNow();
});

it('detects a coupon that has not yet expired', function (): void {
    Carbon::setTestNow(now());
    $coupon = Coupon::factory()->create(['expires_at' => now()->addDay()]);

    expect($coupon->hasExpired())->toBeFalse();

    Carbon::setTestNow();
});

it('treats a coupon with no expiry as never expired', function (): void {
    $coupon = Coupon::factory()->create(['expires_at' => null]);

    expect($coupon->hasExpired())->toBeFalse();
});

it('detects a coupon that has not started yet', function (): void {
    Carbon::setTestNow(now());
    $coupon = Coupon::factory()->notStarted()->create();

    expect($coupon->hasStarted())->toBeFalse();

    Carbon::setTestNow();
});

it('treats a coupon with no start date as already started', function (): void {
    $coupon = Coupon::factory()->create(['starts_at' => null]);

    expect($coupon->hasStarted())->toBeTrue();
});

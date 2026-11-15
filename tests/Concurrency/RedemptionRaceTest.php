<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Enums\RedemptionStatus;
use Abdalazeze\Coupons\Models\Coupon;
use Abdalazeze\Coupons\Models\CouponRedemption;
use Abdalazeze\Coupons\Tests\Concurrency\Support\ParallelRedeemer;

/**
 * @param  list<string>  $outcomes
 * @return array<string, int> outcome kind => count, with every unexpected error listed
 */
function tally(array $outcomes): array
{
    $tally = [];

    foreach ($outcomes as $outcome) {
        $kind = str_starts_with($outcome, 'error:') ? $outcome : explode(':', $outcome)[0];
        $tally[$kind] = ($tally[$kind] ?? 0) + 1;
    }

    ksort($tally);

    return $tally;
}

it('applies exactly 100 of 150 parallel redemptions on a coupon limited to 100', function (): void {
    $coupon = Coupon::factory()->percentage()->withLimit(100)->create();

    $attempts = [];
    foreach (range(1, 150) as $ignored) {
        $attempts[] = ['code' => $coupon->code, 'order' => createOrder()->id, 'user' => createUser()->id];
    }

    $outcomes = ParallelRedeemer::run($attempts);

    expect(tally($outcomes))->toBe(['applied' => 100, 'limit' => 50])
        ->and($coupon->fresh()?->redemptions_count)->toBe(100)
        ->and(CouponRedemption::query()->where('status', RedemptionStatus::Applied)->count())->toBe(100);
});

it('applies exactly max_per_user of 30 parallel redemptions by one user', function (): void {
    $coupon = Coupon::factory()->percentage()->create(['max_per_user' => 3]);
    $user = createUser();

    $attempts = [];
    foreach (range(1, 30) as $ignored) {
        $attempts[] = ['code' => $coupon->code, 'order' => createOrder()->id, 'user' => $user->id];
    }

    $outcomes = ParallelRedeemer::run($attempts);

    expect(tally($outcomes))->toBe(['applied' => 3, 'user_limit' => 27])
        ->and($coupon->fresh()?->redemptions_count)->toBe(3);
});

it('records one redemption when the same order is redeemed 30 times in parallel', function (): void {
    $coupon = Coupon::factory()->percentage()->create();
    $order = createOrder();
    $user = createUser();

    $attempts = array_fill(0, 30, ['code' => $coupon->code, 'order' => $order->id, 'user' => $user->id]);

    $outcomes = ParallelRedeemer::run($attempts);

    $redemption = CouponRedemption::query()->sole();
    expect(array_unique($outcomes))->toBe(["applied:{$redemption->id}"])
        ->and($coupon->fresh()?->redemptions_count)->toBe(1);
});

<?php

declare(strict_types=1);

use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** @return list<Migration> */
function packageMigrations(): array
{
    return [
        require __DIR__.'/../../database/migrations/2024_01_01_000001_create_coupons_table.php',
        require __DIR__.'/../../database/migrations/2024_01_01_000002_create_coupon_redemptions_table.php',
    ];
}

beforeEach(function (): void {
    config()->set('coupons.table_names', [
        'coupons' => 'shop_coupons',
        'coupon_redemptions' => 'shop_coupon_redemptions',
    ]);

    foreach (packageMigrations() as $migration) {
        $migration->up();
    }
});

afterEach(function (): void {
    foreach (array_reverse(packageMigrations()) as $migration) {
        $migration->down();
    }

    // Testbench rolls back the default tables after this hook, and down() reads the config.
    config()->set('coupons.table_names', [
        'coupons' => 'coupons',
        'coupon_redemptions' => 'coupon_redemptions',
    ]);
});

it('creates the tables under the configured names', function (): void {
    expect(Schema::hasTable('shop_coupons'))->toBeTrue()
        ->and(Schema::hasTable('shop_coupon_redemptions'))->toBeTrue();
});

it('redeems against the configured tables', function (): void {
    $coupon = Coupon::factory()->percentage()->withLimit(1)->create();

    $redemption = app(CouponsManager::class)->redeem($coupon->code, createOrder(), createUser());

    expect($redemption->getTable())->toBe('shop_coupon_redemptions')
        ->and($redemption->coupon->is($coupon))->toBeTrue()
        ->and($coupon->fresh()?->redemptions_count)->toBe(1);
});

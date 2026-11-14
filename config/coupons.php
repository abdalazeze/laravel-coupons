<?php

declare(strict_types=1);
use Abdalazeze\Coupons\Models\Coupon;
use Abdalazeze\Coupons\Models\CouponRedemption;

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Rename these if your app uses a table prefix or the defaults collide with
    | an existing table. Publish and edit the migrations to match.
    |
    */

    'table_names' => [
        'coupons' => 'coupons',
        'coupon_redemptions' => 'coupon_redemptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Classes
    |--------------------------------------------------------------------------
    |
    | Swap in your own models if you need to extend the defaults. Any
    | replacement must extend the original class.
    |
    */

    'models' => [
        'coupon' => Coupon::class,
        'redemption' => CouponRedemption::class,
    ],

];

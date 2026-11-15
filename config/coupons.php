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
    | Rename these if the defaults collide with an existing table. The
    | migrations read these names, so set them before you migrate and do not
    | change them afterwards: rolling back would look for the new names.
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

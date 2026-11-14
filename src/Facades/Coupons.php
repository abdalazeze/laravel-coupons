<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Facades;

use Abdalazeze\Coupons\CouponsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @see CouponsManager
 */
final class Coupons extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'coupons';
    }
}

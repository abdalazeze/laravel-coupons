<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons;

use Abdalazeze\Coupons\Calculators\DefaultDiscountCalculator;
use Abdalazeze\Coupons\Contracts\DiscountCalculator;
use Illuminate\Support\ServiceProvider;

class CouponsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/coupons.php', 'coupons');

        $this->app->bind(DiscountCalculator::class, DefaultDiscountCalculator::class);

        $this->app->singleton(CouponsManager::class, fn () => new CouponsManager(
            calculator: $this->app->make(DiscountCalculator::class),
        ));

        // Keep the 'coupons' key so the Facade continues to resolve correctly.
        $this->app->alias(CouponsManager::class, 'coupons');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/coupons.php' => config_path('coupons.php'),
            ], 'coupons-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'coupons-migrations');
        }
    }
}

<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons;

use Illuminate\Support\ServiceProvider;

class CouponsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/coupons.php', 'coupons');

        $this->app->singleton('coupons', fn () => new CouponsManager);
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

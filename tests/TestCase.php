<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Tests;

use Abdalazeze\Coupons\CouponsServiceProvider;
use Abdalazeze\Coupons\Tests\Support\TestDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CouponsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        TestDatabase::configure($app['config']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}

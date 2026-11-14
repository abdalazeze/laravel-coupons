<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Tests;

use Abdalazeze\Coupons\CouponsServiceProvider;
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
        $connection = (string) env('DB_CONNECTION', 'sqlite');
        $app['config']->set('database.default', $connection);

        if ($connection === 'sqlite') {
            $app['config']->set('database.connections.sqlite.database', ':memory:');

            return;
        }

        $config = [
            'driver' => $connection,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (string) env('DB_PORT', $connection === 'mysql' ? '3306' : '5432'),
            'database' => env('DB_DATABASE', 'testing'),
            'username' => env('DB_USERNAME', $connection === 'mysql' ? 'root' : 'postgres'),
            'password' => (string) env('DB_PASSWORD', ''),
            'prefix' => '',
        ];

        if ($connection === 'mysql') {
            $config['charset'] = 'utf8mb4';
            $config['collation'] = 'utf8mb4_unicode_ci';
        } elseif ($connection === 'pgsql') {
            $config['charset'] = 'utf8';
            $config['search_path'] = 'public';
        }

        $app['config']->set("database.connections.{$connection}", $config);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

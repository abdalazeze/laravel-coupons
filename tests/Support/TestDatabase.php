<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Tests\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Database config shared by the test case and the concurrency worker processes,
 * so both connect to the same database from the same environment variables.
 */
final class TestDatabase
{
    public static function configure(Repository $config): void
    {
        $connection = self::driver();
        $config->set('database.default', $connection);

        if ($connection === 'sqlite') {
            $config->set('database.connections.sqlite.database', ':memory:');

            return;
        }

        $settings = [
            'driver' => $connection,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (string) env('DB_PORT', $connection === 'mysql' ? '3306' : '5432'),
            'database' => env('DB_DATABASE', 'testing'),
            'username' => env('DB_USERNAME', $connection === 'mysql' ? 'root' : 'postgres'),
            'password' => (string) env('DB_PASSWORD', ''),
            'prefix' => '',
        ];

        if ($connection === 'mysql') {
            $settings['charset'] = 'utf8mb4';
            $settings['collation'] = 'utf8mb4_unicode_ci';
        } elseif ($connection === 'pgsql') {
            $settings['charset'] = 'utf8';
            $settings['search_path'] = 'public';
        }

        $config->set("database.connections.{$connection}", $settings);
    }

    public static function driver(): string
    {
        return (string) env('DB_CONNECTION', 'sqlite');
    }
}

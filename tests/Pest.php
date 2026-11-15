<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Tests\Models\Order;
use Abdalazeze\Coupons\Tests\Models\User;
use Abdalazeze\Coupons\Tests\Support\TestDatabase;
use Abdalazeze\Coupons\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Unit', 'Feature');

// No RefreshDatabase: these tests run DDL, which commits implicitly on MySQL and would
// break the wrapping transaction. They create and drop their own tables instead.
uses(TestCase::class)->in('Configuration');

// No RefreshDatabase: worker processes have their own connections and cannot see rows
// created inside the test's uncommitted transaction. Without a database trait, Testbench's
// loadMigrationsFrom() migrates before each test and rolls back after it, on committed data.
// (DatabaseMigrations would run migrate:fresh afterwards and drop the package tables.)
// SQLite is skipped: it has no row locks, writers simply serialize on the whole file.
uses(TestCase::class)
    ->group('concurrency')
    ->beforeEach(function (): void {
        if (TestDatabase::driver() === 'sqlite') {
            $this->markTestSkipped('Concurrency tests need MySQL or PostgreSQL.');
        }
    })
    ->in('Concurrency');

function createOrder(int $amount = 10000, ?string $currency = 'USD'): Order
{
    return Order::query()->create(['amount' => $amount, 'currency' => $currency]);
}

function createUser(): User
{
    return User::query()->create();
}

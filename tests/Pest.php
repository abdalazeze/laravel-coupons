<?php

declare(strict_types=1);

use Abdalazeze\Coupons\Tests\Models\Order;
use Abdalazeze\Coupons\Tests\Models\User;
use Abdalazeze\Coupons\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Unit', 'Feature');

function createOrder(int $amount = 10000, ?string $currency = 'USD'): Order
{
    return Order::query()->create(['amount' => $amount, 'currency' => $currency]);
}

function createUser(): User
{
    return User::query()->create();
}

<?php

declare(strict_types=1);

/*
 * One redemption attempt in its own process with its own database connection.
 * Usage: php worker.php <code> <order id> <user id>
 *
 * Protocol: prints "ready" once booted and connected, waits for a line on stdin,
 * redeems, then prints one outcome line: applied:<redemption id> | limit | user_limit | error:<details>.
 */

use Abdalazeze\Coupons\CouponsManager;
use Abdalazeze\Coupons\CouponsServiceProvider;
use Abdalazeze\Coupons\Exceptions\CouponLimitReached;
use Abdalazeze\Coupons\Exceptions\UserCouponLimitReached;
use Abdalazeze\Coupons\Tests\Models\Order;
use Abdalazeze\Coupons\Tests\Models\User;
use Abdalazeze\Coupons\Tests\Support\TestDatabase;
use Orchestra\Testbench\Foundation\Application;

require __DIR__.'/../../../vendor/autoload.php';

[, $code, $orderId, $userId] = $argv;

$app = Application::create(options: [
    'extra' => ['providers' => [CouponsServiceProvider::class], 'dont-discover' => ['*']],
]);
TestDatabase::configure($app['config']);
$app['db']->purge();

$order = Order::query()->findOrFail((int) $orderId);
$user = User::query()->findOrFail((int) $userId);
$manager = $app->make(CouponsManager::class);
$app['db']->connection()->getPdo();

fwrite(STDOUT, "ready\n");
fgets(STDIN);

try {
    $redemption = $manager->redeem($code, $order, $user);
    fwrite(STDOUT, "applied:{$redemption->getKey()}\n");
} catch (CouponLimitReached) {
    fwrite(STDOUT, "limit\n");
} catch (UserCouponLimitReached) {
    fwrite(STDOUT, "user_limit\n");
} catch (Throwable $e) {
    fwrite(STDOUT, 'error:'.$e::class.': '.str_replace(["\r", "\n"], ' ', $e->getMessage())."\n");
}

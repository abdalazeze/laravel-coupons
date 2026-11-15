<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Tests\Models;

use Abdalazeze\Coupons\Contracts\Redeemable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $amount
 * @property string|null $currency
 */
final class Order extends Model implements Redeemable
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function getCouponAmount(): int
    {
        return $this->amount;
    }

    public function getCouponCurrency(): ?string
    {
        return $this->currency;
    }
}

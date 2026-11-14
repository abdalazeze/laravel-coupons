<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Models;

use Abdalazeze\Coupons\Enums\RedemptionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $coupon_id
 * @property string $redeemer_type
 * @property int|string $redeemer_id
 * @property string $redeemable_type
 * @property int|string $redeemable_id
 * @property int $discount_amount
 * @property RedemptionStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Coupon $coupon
 * @property-read Model $redeemer
 * @property-read Model $redeemable
 */
class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id',
        'redeemer_type',
        'redeemer_id',
        'redeemable_type',
        'redeemable_id',
        'discount_amount',
        'status',
    ];

    public function getTable(): string
    {
        return config('coupons.table_names.coupon_redemptions', parent::getTable());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
            'status' => RedemptionStatus::class,
        ];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return MorphTo<Model, $this> */
    public function redeemer(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function redeemable(): MorphTo
    {
        return $this->morphTo();
    }
}

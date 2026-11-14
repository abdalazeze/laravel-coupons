<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Models;

use Abdalazeze\Coupons\Database\Factories\CouponFactory;
use Abdalazeze\Coupons\Enums\CouponType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property string|null $currency
 * @property int|null $max_redemptions
 * @property int $redemptions_count
 * @property int|null $max_per_user
 * @property int|null $min_order_amount
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property bool $stackable
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CouponRedemption> $redemptions
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'currency',
        'max_redemptions',
        'redemptions_count',
        'max_per_user',
        'min_order_amount',
        'starts_at',
        'expires_at',
        'is_active',
        'stackable',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('coupons.table_names.coupons', parent::getTable());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'max_per_user' => 'integer',
            'min_order_amount' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'stackable' => 'boolean',
            'metadata' => 'array',
        ];
    }

    // All codes are stored and queried as uppercase to make lookups case-insensitive.
    /** @return Attribute<string, string> */
    protected function code(): Attribute
    {
        return Attribute::make(
            get: static fn (string $value): string => mb_strtoupper($value),
            set: static fn (string $value): string => mb_strtoupper($value),
        );
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || ! $this->starts_at->isFuture();
    }

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }
}

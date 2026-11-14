<?php

declare(strict_types=1);

namespace Abdalazeze\Coupons\Database\Factories;

use Abdalazeze\Coupons\Enums\CouponType;
use Abdalazeze\Coupons\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        /** @var CouponType $type */
        $type = $this->faker->randomElement(CouponType::cases());

        return [
            'code' => strtoupper($this->faker->unique()->lexify('??????')),
            'type' => $type,
            'value' => $type === CouponType::Percentage
                ? $this->faker->numberBetween(100, 5000)   // 1 % – 50 %
                : $this->faker->numberBetween(100, 10000), // $1.00 – $100.00
            'currency' => $type === CouponType::Fixed ? 'USD' : null,
            'is_active' => true,
            'stackable' => false,
            'redemptions_count' => 0,
        ];
    }

    public function percentage(): static
    {
        return $this->state([
            'type' => CouponType::Percentage,
            'currency' => null,
        ]);
    }

    public function fixed(string $currency = 'USD'): static
    {
        return $this->state([
            'type' => CouponType::Fixed,
            'currency' => $currency,
        ]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function notStarted(): static
    {
        return $this->state(['starts_at' => now()->addDay()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withLimit(int $max): static
    {
        return $this->state(['max_redemptions' => $max]);
    }
}

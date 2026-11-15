<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            // constrained() without a table name would infer 'coupons' from the column name.
            $table->foreignId('coupon_id')
                ->constrained(config('coupons.table_names.coupons', 'coupons'))
                ->cascadeOnDelete();
            $table->morphs('redeemer');
            $table->morphs('redeemable');
            $table->unsignedBigInteger('discount_amount');
            $table->string('status');
            $table->timestamps();

            // Idempotency guard: the same coupon cannot be applied twice to the same redeemable.
            // Named explicitly: Laravel's generated name exceeds MySQL's 64-character limit.
            // Prefixed with the table because SQLite and PostgreSQL index names are schema-wide.
            $table->unique(
                ['coupon_id', 'redeemable_type', 'redeemable_id'],
                $this->table().'_coupon_redeemable_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('coupons.table_names.coupon_redemptions', 'coupon_redemptions');
    }
};

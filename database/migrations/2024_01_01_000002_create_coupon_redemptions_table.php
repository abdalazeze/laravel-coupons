<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->morphs('redeemer');
            $table->morphs('redeemable');
            $table->unsignedBigInteger('discount_amount');
            $table->string('status');
            $table->timestamps();

            // Idempotency guard: the same coupon cannot be applied twice to the same redeemable.
            $table->unique(['coupon_id', 'redeemable_type', 'redeemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};

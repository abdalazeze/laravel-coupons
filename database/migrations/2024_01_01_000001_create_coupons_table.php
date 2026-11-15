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
            $table->string('code')->unique();
            $table->string('type');
            // value is minor units for fixed (e.g. 500 = $5.00) and basis points for percentage (1000 = 10%)
            $table->unsignedBigInteger('value');
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->unsignedInteger('max_per_user')->nullable();
            $table->unsignedBigInteger('min_order_amount')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('stackable')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('coupons.table_names.coupons', 'coupons');
    }
};

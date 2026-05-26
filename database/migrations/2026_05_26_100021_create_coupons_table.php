<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->string('type', 16); // percent | fixed
            $table->unsignedInteger('value'); // percent: 1..100 (whole percent); fixed: amount in cents
            $table->string('currency', 3)->nullable(); // required when type=fixed
            $table->string('duration', 16); // once | repeating | forever
            $table->unsignedInteger('duration_in_months')->nullable(); // required when duration=repeating
            $table->jsonb('applies_to_plans')->nullable(); // null = all; or array of plan IDs
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->jsonb('gateway_ids')->default('{}'); // { 'stripe': 'coupon_xxx' }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['is_active', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

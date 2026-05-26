<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_period', 16); // day | week | month | year | one_time
            $table->unsignedInteger('billing_interval')->default(1); // every N periods
            $table->unsignedInteger('trial_days')->default(0);
            $table->jsonb('features')->default('{}'); // freeform: { 'seats': 5, 'storage_gb': 10, 'flags': ['api_access'] }
            $table->jsonb('gateway_ids')->default('{}'); // { 'stripe': 'price_xxx', 'paypal': 'P-xxx' }
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true); // false = invite-only / legacy plans
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['is_active', 'is_public']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

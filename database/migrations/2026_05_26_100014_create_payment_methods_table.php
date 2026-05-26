<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('gateway_pm_id'); // gateway-side payment method id
            $table->string('type', 32); // card | bank_account | wallet | fpx | mada | knet | apple_pay | google_pay
            $table->string('brand', 32)->nullable(); // visa | mastercard | mada | grabpay | ...
            $table->string('last4', 8)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->string('holder_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway', 'gateway_pm_id']);
            $table->index(['tenant_id', 'is_default']);
        });

        // Postgres partial unique index: one default payment method per tenant
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX payment_methods_one_default_per_tenant ON payment_methods (tenant_id) WHERE is_default = true AND deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS payment_methods_one_default_per_tenant');
        }
        Schema::dropIfExists('payment_methods');
    }
};

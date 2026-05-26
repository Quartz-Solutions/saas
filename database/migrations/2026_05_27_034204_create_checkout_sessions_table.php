<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            $table->string('intent', 16);            // subscription | one_time
            $table->string('status', 24);            // pending | awaiting_payment | completed | failed | canceled | expired

            $table->string('gateway', 32)->nullable();              // null until user picks
            $table->string('gateway_session_id')->nullable();       // id at the gateway
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount_cents');

            // Polymorphic next-step
            $table->string('result_kind', 16)->nullable();          // redirect | form_post | iframe | widget | kiosk_ref
            $table->jsonb('result_payload')->nullable();

            // FKs filled on completion
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['gateway', 'gateway_session_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};

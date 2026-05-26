<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('gateway_event_id'); // gateway's own event id; unique per gateway
            $table->string('event_type'); // gateway-specific: invoice.payment_succeeded, etc.
            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();
            $table->string('signature', 256)->nullable();
            // received | processing | processed | failed | ignored
            $table->string('status', 32)->default('received');
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); // resolved post-dispatch
            $table->timestamps();

            $table->unique(['gateway', 'gateway_event_id']);
            $table->index(['gateway', 'event_type']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};

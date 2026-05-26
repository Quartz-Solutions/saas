<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('event_id', 64); // app-side event id, deduplication
            $table->jsonb('payload');
            $table->string('signature', 128); // signature sent in header for receiver to verify
            $table->unsignedInteger('attempt')->default(1);
            $table->string('status', 32)->default('pending'); // pending | succeeded | failed | abandoned
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['outbound_webhook_id', 'status']);
            $table->index('next_retry_at');
            $table->index(['event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhook_deliveries');
    }
};

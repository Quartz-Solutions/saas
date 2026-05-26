<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('url');
            $table->string('description')->nullable();
            $table->string('secret', 128); // HMAC signing secret, shown once at creation
            $table->jsonb('events'); // array of event types subscribed to, e.g. ['payment.succeeded', 'subscription.canceled']
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failure_count')->default(0); // consecutive failures
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamp('disabled_at')->nullable(); // auto-disabled after N consecutive failures
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhooks');
    }
};

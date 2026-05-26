<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('outcome', 32); // succeeded | failed | requires_action | pending
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('gateway_request')->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamp('next_retry_at')->nullable();

            $table->unique(['payment_id', 'attempt_number']);
            $table->index('next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};

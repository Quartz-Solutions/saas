<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable(); // captured even when no matching user (failed attempts)
            $table->string('outcome', 32); // succeeded | failed | locked | two_factor_required | two_factor_failed
            $table->string('method', 32)->nullable(); // password | magic_link | social_google | social_github | api_token
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->jsonb('context')->nullable(); // freeform: device fingerprint, risk score, etc.
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index(['email', 'outcome']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};

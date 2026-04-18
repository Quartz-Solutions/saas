<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page');
            $table->string('name')->default('Default');
            $table->jsonb('value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'page', 'name']);
            $table->index(['user_id', 'page', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};

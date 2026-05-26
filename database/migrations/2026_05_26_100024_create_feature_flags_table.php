<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled_globally')->default(false);
            $table->jsonb('rules')->nullable(); // segmentation: { 'plans': ['pro'], 'percent_rollout': 25 }
            $table->timestamps();

            $table->index('enabled_globally');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};

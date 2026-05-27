<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_not_found_log', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('referer')->nullable();
            $table->timestamps();

            $table->index('last_hit_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_not_found_log');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('cms_forms', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name');
            if ($driver === 'pgsql') {
                $table->jsonb('fields')->default('[]');
            } else {
                $table->json('fields')->nullable();
            }
            $table->text('success_message')->nullable();
            $table->string('notify_email')->nullable();
            $table->string('webhook_url')->nullable();
            $table->boolean('store_submissions')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cms_form_submissions', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('cms_form_id')->constrained()->cascadeOnDelete();
            if ($driver === 'pgsql') {
                $table->jsonb('payload')->default('{}');
            } else {
                $table->json('payload')->nullable();
            }
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referer', 2048)->nullable();
            $table->timestamps();

            $table->index(['cms_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_form_submissions');
        Schema::dropIfExists('cms_forms');
    }
};

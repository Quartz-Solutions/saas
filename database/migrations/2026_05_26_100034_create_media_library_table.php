<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = global / admin
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable(); // images
            $table->unsignedInteger('height')->nullable();
            $table->string('hash', 64)->nullable(); // sha256 for dedup
            $table->jsonb('metadata')->default('{}'); // alt text, focal point, conversions, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'mime_type']);
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library');
    }
};

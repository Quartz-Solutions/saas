<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type'); // polymorphic — App\Models\CmsPage, App\Models\Tenant, etc.
            $table->unsignedBigInteger('seoable_id');
            $table->string('locale', 8)->default('en');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('og_type', 32)->nullable();
            $table->jsonb('schema_org')->nullable(); // JSON-LD payload
            $table->boolean('no_index')->default(false);
            $table->boolean('no_follow')->default(false);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_meta_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};

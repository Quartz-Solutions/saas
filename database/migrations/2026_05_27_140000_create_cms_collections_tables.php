<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable content collections referenced from blocks via *_ids /
 * group_slug.
 *
 * - cms_features      → feature_grid blocks
 * - cms_testimonials  → testimonials blocks
 * - cms_faqs          → faq blocks (grouped by group_slug)
 * - cms_logos         → logo_cloud blocks (grouped by group_slug)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_features', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable(); // lucide icon name
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('cms_testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('quote');
            $table->string('author_name');
            $table->string('author_role')->nullable();
            $table->string('company')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('cms_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('group_slug', 120)->default('default');
            $table->string('question');
            $table->text('answer_html')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_slug', 'is_active', 'sort_order']);
        });

        Schema::create('cms_logos', function (Blueprint $table) {
            $table->id();
            $table->string('group_slug', 120)->default('default');
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_slug', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_logos');
        Schema::dropIfExists('cms_faqs');
        Schema::dropIfExists('cms_testimonials');
        Schema::dropIfExists('cms_features');
    }
};

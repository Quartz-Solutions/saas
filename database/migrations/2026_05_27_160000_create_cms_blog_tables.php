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

        Schema::create('cms_blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cms_blog_posts', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title');
            $table->string('locale', 8)->default('en');
            $table->text('excerpt')->nullable();
            $table->string('cover_image_url')->nullable();
            if ($driver === 'pgsql') {
                $table->jsonb('body_blocks')->nullable();
            } else {
                $table->json('body_blocks')->nullable();
            }
            $table->text('body_html')->nullable();
            $table->string('status', 16)->default('draft'); // draft | published | archived
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('reading_minutes')->nullable();
            $table->boolean('no_index')->default(false);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('cms_blog_post_category', function (Blueprint $table) {
            $table->foreignId('cms_blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cms_blog_category_id')->constrained('cms_blog_categories')->cascadeOnDelete();
            $table->primary(['cms_blog_post_id', 'cms_blog_category_id'], 'cms_post_cat_pk');
        });

        Schema::create('cms_blog_post_tag', function (Blueprint $table) {
            $table->foreignId('cms_blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cms_blog_tag_id')->constrained('cms_blog_tags')->cascadeOnDelete();
            $table->primary(['cms_blog_post_id', 'cms_blog_tag_id'], 'cms_post_tag_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_blog_post_tag');
        Schema::dropIfExists('cms_blog_post_category');
        Schema::dropIfExists('cms_blog_posts');
        Schema::dropIfExists('cms_blog_tags');
        Schema::dropIfExists('cms_blog_categories');
    }
};

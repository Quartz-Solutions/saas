<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_preset')->default(false);   // seeded presets: clone-only, never deleted
            $table->string('mode_hint', 10)->default('both'); // light | dark | both
            $table->jsonb('tokens')->nullable();             // { "light": {...}, "dark": {...} }
            $table->string('radius', 16)->default('0.625rem');
            $table->string('font_family', 120)->nullable();  // overrides --font-sans when matching faces exist
            $table->string('custom_css_path')->nullable();   // uploaded/edited CSS escape hatch
            $table->string('compiled_css_path')->nullable(); // cached compiled artifact (tokens+fonts+custom)
            $table->timestamp('compiled_at')->nullable();
            $table->string('preview_image_path')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};

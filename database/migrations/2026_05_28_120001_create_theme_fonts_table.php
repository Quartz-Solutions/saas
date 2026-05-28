<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_fonts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('family', 120);               // parsed from filename, e.g. "Roboto"
            $table->string('weight', 16)->default('400'); // 100..900 / normal / bold / "100 900" (variable)
            $table->string('style', 16)->default('normal'); // normal | italic
            $table->string('format', 16);                // woff2 | woff | ttf | otf
            $table->string('path');                      // storage path under themes/{id}/fonts/...
            $table->string('original_filename');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();

            $table->index(['theme_id', 'family']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_fonts');
    }
};

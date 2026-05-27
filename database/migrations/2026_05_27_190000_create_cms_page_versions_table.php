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

        Schema::create('cms_page_versions', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            if ($driver === 'pgsql') {
                $table->jsonb('snapshot');
            } else {
                $table->json('snapshot');
            }
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cms_page_id', 'version_no'], 'cms_page_versions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_versions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Block-based content upgrade for cms_pages.
 *
 * Additive: legacy body_markdown/body_html remain valid until a page is
 * resaved through the new block editor (M2). The public renderer prefers
 * body_blocks when present and falls back to body_html otherwise.
 *
 * - body_blocks   jsonb array of typed blocks (M1+)
 * - parent_id     nullable self-FK for nested URL paths (e.g. /docs/foo/bar)
 * - path          materialised full URL path, unique per locale
 * - route_name    optional named slot (home, pricing, contact) so a CMS
 *                 page can claim a known controller's output
 * - publish_at /
 *   unpublish_at  scheduled state transitions (M11 will read these)
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::table('cms_pages', function (Blueprint $table) use ($driver) {
            // Postgres has native jsonb; SQLite test env falls back to JSON text.
            if ($driver === 'pgsql') {
                $table->jsonb('body_blocks')->nullable()->after('body_html');
            } else {
                $table->json('body_blocks')->nullable()->after('body_html');
            }

            $table->foreignId('parent_id')->nullable()->after('locale')
                ->constrained('cms_pages')->nullOnDelete();

            $table->string('path')->nullable()->after('parent_id');
            $table->string('route_name', 64)->nullable()->after('path');

            $table->timestamp('publish_at')->nullable()->after('published_at');
            $table->timestamp('unpublish_at')->nullable()->after('publish_at');

            $table->index('parent_id');
            $table->index('route_name');
            $table->unique(['path', 'locale'], 'cms_pages_path_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropUnique('cms_pages_path_locale_unique');
            $table->dropIndex(['route_name']);
            $table->dropIndex(['parent_id']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'body_blocks',
                'parent_id',
                'path',
                'route_name',
                'publish_at',
                'unpublish_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Phase 3.5 — per-tenant default payment gateway. Falls back to
            // config('billing.default_gateway') when null. Pre-selected on
            // the checkout picker when offered to the user.
            $table->string('preferred_gateway', 64)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('preferred_gateway');
        });
    }
};

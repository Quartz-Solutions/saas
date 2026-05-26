<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
            $table->string('locale', 8)->default('en')->after('avatar_path');
            $table->string('timezone', 64)->default('UTC')->after('locale');
            $table->ipAddress('last_login_ip')->nullable()->after('timezone');
            $table->timestamp('last_login_at')->nullable()->after('last_login_ip');
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
            $table->foreignId('current_tenant_id')->nullable()->after('last_seen_at')->constrained('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_tenant_id');
            $table->dropColumn([
                'avatar_path',
                'locale',
                'timezone',
                'last_login_ip',
                'last_login_at',
                'last_seen_at',
            ]);
        });
    }
};

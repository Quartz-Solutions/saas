<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64);                 // app | mail | oauth | stripe | sentry | slack | aws
            $table->string('key')->unique();             // matches catalog env name (e.g. MAIL_HOST)
            $table->text('value')->nullable();           // encrypted by Eloquent cast when is_secret
            $table->boolean('is_secret')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};

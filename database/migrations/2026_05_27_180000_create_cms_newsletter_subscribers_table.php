<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 8)->default('en');
            $table->string('source', 64)->nullable(); // newsletter_block | contact_form | manual | csv
            $table->string('provider', 32)->default('database');
            $table->string('provider_id')->nullable(); // upstream id from Mailchimp/Resend/ConvertKit
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('locale');
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_newsletter_subscribers');
    }
};

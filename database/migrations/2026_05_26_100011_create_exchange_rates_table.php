<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('target_currency', 3);
            $table->decimal('rate', 18, 8); // exception to the no-decimal rule: FX rates need fractional precision, never used for money totals
            $table->string('source', 32)->default('manual'); // manual | exchangerate-api | openexchangerates | ecb
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamps();

            $table->foreign('base_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('target_currency')->references('code')->on('currencies')->restrictOnDelete();

            $table->index(['base_currency', 'target_currency', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};

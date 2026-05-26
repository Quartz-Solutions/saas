<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32); // stripe | paypal | paymob | fawry | paytabs | geidea | aps | telr | hyperpay | myfatoorah | hitpay | billplz | ipay88
            $table->string('gateway_customer_id'); // ID at the gateway
            $table->string('email')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway']);
            $table->unique(['gateway', 'gateway_customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_customers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_provider', 30)->default('paymongo')->after('order_id');
            $table->string('provider_checkout_id')->nullable()->unique()->after('payment_provider');
            $table->string('provider_payment_intent_id')->nullable()->index()->after('provider_checkout_id');
            $table->string('checkout_url', 2048)->nullable()->after('provider_payment_intent_id');
            $table->string('reference_number', 100)->nullable()->unique()->after('checkout_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_checkout_id']);
            $table->dropIndex(['provider_payment_intent_id']);
            $table->dropUnique(['reference_number']);
            $table->dropColumn([
                'payment_provider',
                'provider_checkout_id',
                'provider_payment_intent_id',
                'checkout_url',
                'reference_number',
            ]);
        });
    }
};

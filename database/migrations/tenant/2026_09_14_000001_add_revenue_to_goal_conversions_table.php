<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goal_conversions', function (Blueprint $table) {
            // Stored in the currency it was transacted in, never normalised.
            // Reporting groups by currency rather than summing across them —
            // an FX rate that changes between the order and the report would
            // otherwise quietly rewrite a finished experiment's result.
            $table->decimal('revenue', 12, 2)->nullable()->after('variation_id');
            $table->char('currency', 3)->nullable()->after('revenue');

            // The order/transaction id from the source platform. Shopify,
            // WooCommerce and Magento all retry webhooks, so this is what
            // stops a redelivered order being counted twice.
            $table->string('external_id')->nullable()->after('currency');

            // MySQL treats NULLs as distinct in a unique index, so browser
            // beacons (which carry no external_id) are unaffected while
            // server-side order events dedupe on it.
            $table->unique(['goal_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('goal_conversions', function (Blueprint $table) {
            $table->dropUnique(['goal_id', 'external_id']);
            $table->dropColumn(['revenue', 'currency', 'external_id']);
        });
    }
};

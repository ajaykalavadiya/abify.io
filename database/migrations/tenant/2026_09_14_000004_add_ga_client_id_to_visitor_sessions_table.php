<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GA4's own client id for this visit.
     *
     * Kept so abify's data can be joined to the merchant's GA4 — and, more
     * practically, so a conversion that arrives server-side with no browser
     * attached can still be reported into GA4 against the right user.
     */
    public function up(): void
    {
        Schema::table('visitor_sessions', function (Blueprint $table) {
            $table->string('ga_client_id', 64)->nullable()->after('referrer');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_sessions', function (Blueprint $table) {
            $table->dropColumn('ga_client_id');
        });
    }
};

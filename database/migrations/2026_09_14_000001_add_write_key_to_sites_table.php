<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-to-server ingestion credential, separate from `public_id`.
     *
     * `public_id` ships inside the snippet on every page of the customer's
     * site, so it authenticates nothing — fine for browser beacons, useless
     * for the order webhooks that carry revenue. Without a secret here
     * anyone who viewed a merchant's source could post fabricated purchases
     * into their experiment results.
     *
     * Only the SHA-256 of the key is stored. It is indexed rather than
     * bcrypt-verified so a request can look the site up directly; the key is
     * 32 random bytes, so it has none of the brute-force exposure that makes
     * a slow hash necessary for human-chosen passwords.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->char('write_key_hash', 64)->nullable()->unique()->after('public_id');
            $table->timestamp('write_key_created_at')->nullable()->after('write_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique(['write_key_hash']);
            $table->dropColumn(['write_key_hash', 'write_key_created_at']);
        });
    }
};

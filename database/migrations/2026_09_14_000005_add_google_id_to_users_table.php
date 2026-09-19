<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Google's stable subject id for a user.
     *
     * Matched on in preference to the email address: a Google account's email
     * can change, and matching on email alone would mean a changed address
     * silently creating a second account rather than signing the same person
     * in.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');

            // Users who only ever sign in with Google have no password, and
            // the column is NOT NULL by default.
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};

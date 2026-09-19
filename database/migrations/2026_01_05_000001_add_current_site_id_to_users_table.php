<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The site the user is currently working in. Sites live in the
            // per-company tenant database, so this can't be a foreign key —
            // it is validated against the resolved company on every request
            // (see App\Http\Middleware\ResolveAccountFromRoute).
            $table->unsignedBigInteger('current_site_id')->nullable()->after('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('current_site_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('experiments', function (Blueprint $table) {
            // Nullable at the DB layer for backward compatibility with any
            // pre-existing rows — a null/empty url_pattern matches every
            // page (see TrackingScriptRenderer's matchesPage()). The
            // dashboard's create form makes it required for new tests.
            $table->string('url_match_type')->default('contains')->after('type');
            $table->string('url_pattern')->nullable()->after('url_match_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('experiments', function (Blueprint $table) {
            $table->dropColumn(['url_match_type', 'url_pattern']);
        });
    }
};

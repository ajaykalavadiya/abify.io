<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-variation heatmaps resolve "which visitors saw this variation" as a
     * subquery against this table on every map that is drawn.
     *
     * The existing unique index leads with experiment_id, so it cannot serve
     * a lookup by variation alone — without this the subquery scans every
     * assignment the company has ever made, which on a long-running test is
     * the largest table in the tenant after the events themselves.
     */
    public function up(): void
    {
        Schema::table('visitor_assignments', function (Blueprint $table) {
            // Covering: the subquery selects visitor_uuid and filters on
            // variation_id, so this answers it from the index alone.
            $table->index(['variation_id', 'visitor_uuid'], 'visitor_assignments_variation_visitor_index');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_assignments', function (Blueprint $table) {
            $table->dropIndex('visitor_assignments_variation_visitor_index');
        });
    }
};

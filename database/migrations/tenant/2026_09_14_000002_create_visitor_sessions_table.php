<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A visit, as opposed to a page load.
     *
     * `heatmap_sessions` already holds a row per session id, but it is scoped
     * to one URL's heatmap and is only created when heatmap events arrive, so
     * it cannot answer how long a visit lasted or how many pages it covered.
     * This is the visit itself, written for every session whether or not the
     * visitor ever moved their mouse.
     *
     * Matters for commerce specifically: "how many visits before a purchase"
     * and "did this variation deepen the visit" are session questions, and
     * the attribution window for an order is measured in visits.
     */
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->char('session_id', 36)->unique();
            $table->char('visitor_uuid', 36);

            // Where the visit began and where it left off. Entry url is
            // written once; exit url moves with every batch.
            $table->string('entry_url', 2048);
            $table->string('exit_url', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();

            $table->string('device_type')->nullable();
            $table->unsignedSmallInteger('viewport_width')->nullable();

            $table->unsignedInteger('pageview_count')->default(0);
            $table->unsignedInteger('event_count')->default(0);

            $table->timestamp('started_at');
            // Moved forward on every batch, so duration is last_seen - started
            // without needing a reliable "session ended" signal from a browser
            // that may simply have been closed.
            $table->timestamp('last_seen_at');

            $table->index(['site_id', 'started_at']);
            $table->index(['visitor_uuid', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_sessions');
    }
};

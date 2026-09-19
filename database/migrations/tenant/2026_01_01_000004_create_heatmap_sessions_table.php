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
        Schema::create('heatmap_sessions', function (Blueprint $table) {
            $table->id();
            // References the Site row in the CENTRAL database — plain indexed
            // column, no FK constraint.
            $table->unsignedBigInteger('site_id');
            $table->char('session_id', 36)->unique();
            $table->char('visitor_uuid', 36);
            $table->string('url', 2048);
            // sha256(url) — 'url' itself is too long to index (utf8mb4 4-byte
            // chars push it past InnoDB's index key length limits), so lookups
            // filter/group by this fixed-length hash instead.
            $table->char('url_hash', 64);
            $table->unsignedSmallInteger('viewport_width');
            $table->unsignedSmallInteger('viewport_height');
            $table->string('device_type')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->index(['site_id', 'url_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heatmap_sessions');
    }
};

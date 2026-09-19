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
        Schema::create('heatmap_events', function (Blueprint $table) {
            $table->id();
            // No FK to heatmap_sessions on purpose: site_id/url are denormalized
            // here so the hot "points for site X + url Y" query never joins,
            // and bulk inserts stay cheap. Validated at write time in the job.
            $table->unsignedBigInteger('heatmap_session_id');
            $table->unsignedBigInteger('site_id');
            $table->string('url', 2048);
            // sha256(url) — see heatmap_sessions.url_hash for why 'url' itself
            // isn't indexed directly.
            $table->char('url_hash', 64);
            $table->string('type');
            $table->unsignedSmallInteger('x_pct')->nullable();
            $table->unsignedSmallInteger('y_pct')->nullable();
            $table->unsignedSmallInteger('scroll_depth_pct')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'url_hash', 'type']);
            $table->index('heatmap_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heatmap_events');
    }
};

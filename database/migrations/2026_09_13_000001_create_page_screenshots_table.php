<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Screenshots live in the CENTRAL database even though the heatmap points
     * they back are tenant data: the row is about a Site (central) and a
     * capture job, holds no visitor data, and keeping it here means the queue
     * worker doesn't need a tenant connection to record a failure.
     */
    public function up(): void
    {
        Schema::create('page_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            // sha256(url) — mirrors heatmap_events.url_hash, which is what
            // joins a screenshot to its points.
            $table->char('url_hash', 64);
            $table->string('device', 32);
            $table->string('status', 16)->default('pending');
            $table->string('disk', 32)->nullable();
            $table->string('path')->nullable();
            // CSS pixels of the captured document. 'height' is what the image
            // actually covers; 'full_height' is how tall the page really was,
            // so a truncated capture can say so instead of silently
            // misplacing every point below the cut.
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('full_height')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url_hash', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_screenshots');
    }
};

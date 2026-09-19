<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The index for one recorded visit. The replay payload itself lives on a
     * disk (see config/recordings.php) — this row only says where.
     */
    public function up(): void
    {
        Schema::create('session_recordings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');

            // One recording per visit — the same session id carried by
            // visitor_sessions, so a recording joins to the visit's device,
            // entry URL and (through visitor_assignments) the variation the
            // visitor was shown.
            $table->char('session_id', 36)->unique();
            $table->char('visitor_uuid', 36);

            $table->string('status', 16)->default('recording');

            // Mirrors the PageScreenshot pattern: the disk is stored per
            // recording, so moving the default from local to s3 later does
            // not strand anything already written.
            $table->string('disk', 32);
            $table->string('path_prefix');

            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('event_count')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);

            // Frustration signals, denormalised onto the row because they are
            // what the recordings list is filtered by. A thousand recordings
            // nobody can triage is the same as no recordings.
            $table->boolean('has_rage_click')->default(false);
            $table->boolean('has_error')->default(false);

            $table->timestamp('started_at');
            $table->timestamp('last_chunk_at')->nullable();

            // Set at creation from the retention window, so pruning is an
            // index scan rather than a date calculation across the table.
            $table->timestamp('expires_at')->index();

            $table->index(['site_id', 'started_at']);
            $table->index(['site_id', 'has_rage_click']);
            $table->index('visitor_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_recordings');
    }
};

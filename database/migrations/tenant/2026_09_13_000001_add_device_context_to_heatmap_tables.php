<?php

use App\Support\Analytics\DeviceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Rows updated per statement while backfilling, to keep locks short. */
    protected const CHUNK = 20000;

    /**
     * Heatmaps are read one device at a time — a click at 40% across the page
     * is a different element on a 390px column than on a 1440px grid, so
     * mixing them produces a map of nowhere.
     *
     * device_type is denormalized onto heatmap_events (rather than joined
     * from the session) for the same reason site_id and url_hash already are:
     * the aggregate query is the hot path and must stay a single indexed
     * GROUP BY with no join.
     */
    public function up(): void
    {
        Schema::table('heatmap_sessions', function (Blueprint $table) {
            // Full document size in CSS pixels, as measured on the visitor's
            // own device. Used to show the real page aspect ratio and to sanity
            // check a capture against the layout visitors actually saw.
            $table->unsignedInteger('doc_width')->nullable()->after('viewport_height');
            $table->unsignedInteger('doc_height')->nullable()->after('doc_width');
        });

        Schema::table('heatmap_events', function (Blueprint $table) {
            $table->string('device_type', 16)->nullable()->after('type');
            // Kept per event, not just per session: a visitor who rotates a
            // tablet or drags a desktop window across a breakpoint mid-session
            // is producing clicks on two different layouts.
            $table->unsignedSmallInteger('viewport_width')->nullable()->after('device_type');

            $table->index(
                ['site_id', 'url_hash', 'device_type', 'type'],
                'heatmap_events_site_url_device_type_index'
            );
        });

        $this->backfill();
    }

    /**
     * Classify the history as well as new traffic.
     *
     * Done once here rather than with a COALESCE fallback in the read path:
     * a fallback would make every heatmap query carry an OR against an
     * unindexed column for the lifetime of the table, and a customer opening
     * their heatmap on the day of deploy would otherwise find it empty.
     */
    protected function backfill(): void
    {
        // Sessions already recorded the viewport, so they can classify
        // themselves.
        foreach (DeviceType::all() as $device) {
            [$minimum, $maximum] = DeviceType::widthRange($device);

            DB::table('heatmap_sessions')
                ->whereNull('device_type')
                ->when(
                    $maximum !== null,
                    fn ($query) => $query->whereBetween('viewport_width', [$minimum, $maximum]),
                    fn ($query) => $query->where('viewport_width', '>=', $minimum),
                )
                ->update(['device_type' => $device]);
        }

        // Anything with no usable width recorded lands in the catch-all
        // bucket, matching DeviceType::fromViewportWidth().
        DB::table('heatmap_sessions')
            ->whereNull('device_type')
            ->update(['device_type' => DeviceType::DESKTOP]);

        // Events inherit from their session. Chunked by primary key: this
        // table is the largest in the tenant database and a single UPDATE
        // across all of it would hold locks for minutes.
        $maxId = (int) DB::table('heatmap_events')->max('id');

        // A freshly provisioned tenant has nothing to inherit — without this
        // the loop below still runs one pointless UPDATE across the whole
        // (empty) table on every new tenant database.
        if ($maxId > 0) {
            // MySQL's multi-table UPDATE is the fast path in production;
            // SQLite (used by the test suite's tenant connection) cannot
            // parse it and needs correlated subqueries instead.
            $sql = DB::connection()->getDriverName() === 'sqlite'
                ? 'UPDATE heatmap_events
                       SET device_type = (SELECT s.device_type FROM heatmap_sessions s WHERE s.id = heatmap_events.heatmap_session_id),
                           viewport_width = (SELECT s.viewport_width FROM heatmap_sessions s WHERE s.id = heatmap_events.heatmap_session_id)
                     WHERE device_type IS NULL
                       AND id > ? AND id <= ?
                       AND EXISTS (SELECT 1 FROM heatmap_sessions s WHERE s.id = heatmap_events.heatmap_session_id)'
                : 'UPDATE heatmap_events e
                        JOIN heatmap_sessions s ON s.id = e.heatmap_session_id
                        SET e.device_type = s.device_type,
                            e.viewport_width = s.viewport_width
                      WHERE e.device_type IS NULL
                        AND e.id > ? AND e.id <= ?';

            for ($from = 0; $from <= $maxId; $from += self::CHUNK) {
                DB::statement($sql, [$from, $from + self::CHUNK]);
            }
        }

        // Events whose session row is gone still have to be readable.
        DB::table('heatmap_events')
            ->whereNull('device_type')
            ->update(['device_type' => DeviceType::DESKTOP]);
    }

    public function down(): void
    {
        Schema::table('heatmap_events', function (Blueprint $table) {
            $table->dropIndex('heatmap_events_site_url_device_type_index');
            $table->dropColumn(['device_type', 'viewport_width']);
        });

        Schema::table('heatmap_sessions', function (Blueprint $table) {
            $table->dropColumn(['doc_width', 'doc_height']);
        });
    }
};

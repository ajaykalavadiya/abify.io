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
     * Context the site overview reports on: which device a pageview came from
     * and where the visitor arrived from. Both are cheap to collect at
     * pageview time and impossible to reconstruct afterwards.
     */
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->string('device_type', 16)->nullable()->after('url');
            // Full referrer URL; the overview groups it down to a host.
            $table->string('referrer', 2048)->nullable()->after('device_type');

            $table->index(['site_id', 'device_type', 'occurred_at'], 'analytics_events_site_device_index');
        });

        $this->backfillDevices();
    }

    /**
     * Recover the device for existing pageviews from the heatmap session
     * recorded in the same visit — the tracking script has always sent a
     * viewport with its heatmap events, so the information exists, just on
     * the other table.
     *
     * Worth the one-off cost: without it every customer's device breakdown
     * would read "100% desktop" across their entire history on the day this
     * deploys, which is not a gap in the chart but a wrong answer in it.
     *
     * Referrer has no such source — it was never collected — so historical
     * rows stay null and report as Direct.
     */
    protected function backfillDevices(): void
    {
        $maxId = (int) DB::table('analytics_events')->max('id');

        // Nothing to recover on a freshly provisioned tenant.
        if ($maxId > 0) {
            // MySQL's multi-table UPDATE is the fast path in production;
            // SQLite (the test suite's tenant connection) cannot parse it.
            $sql = DB::connection()->getDriverName() === 'sqlite'
                ? 'UPDATE analytics_events
                       SET device_type = (SELECT s.device_type FROM heatmap_sessions s WHERE s.session_id = analytics_events.session_id AND s.device_type IS NOT NULL)
                     WHERE device_type IS NULL
                       AND id > ? AND id <= ?
                       AND EXISTS (SELECT 1 FROM heatmap_sessions s WHERE s.session_id = analytics_events.session_id AND s.device_type IS NOT NULL)'
                : 'UPDATE analytics_events a
                        JOIN heatmap_sessions s ON s.session_id = a.session_id
                        SET a.device_type = s.device_type
                      WHERE a.device_type IS NULL
                        AND s.device_type IS NOT NULL
                        AND a.id > ? AND a.id <= ?';

            for ($from = 0; $from <= $maxId; $from += self::CHUNK) {
                DB::statement($sql, [$from, $from + self::CHUNK]);
            }
        }

        // Visits with no heatmap session to learn from fall to the catch-all
        // bucket, matching DeviceType::fromViewportWidth().
        DB::table('analytics_events')
            ->whereNull('device_type')
            ->update(['device_type' => DeviceType::DESKTOP]);
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex('analytics_events_site_device_index');
            $table->dropColumn(['device_type', 'referrer']);
        });
    }
};

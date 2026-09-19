<?php

namespace App\Actions\Tracking;

use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Claims one of a site's daily recording slots, or refuses.
 *
 * The check and the increment have to be a single statement. Doing it as a
 * SELECT followed by an UPDATE lets two visitors arriving together both read
 * 99, both decide they are under the limit, and both record — which on a busy
 * store is not a rare race but the normal case, and it would overbill the
 * customer.
 */
class ReserveRecordingSlot
{
    /**
     * @return bool true when a slot was claimed and recording may begin
     */
    public function reserve(Site $site): bool
    {
        if (! $this->enabledFor($site)) {
            return false;
        }

        $limit = $this->dailyLimitFor($site);

        if ($limit <= 0) {
            return false;
        }

        $today = now()->toDateString();
        $now = now();

        // The affected-row count IS the answer. Both dialects below leave the
        // row untouched once the limit is reached, reporting zero rows
        // changed, and the unique index on (site_id, usage_date) serialises
        // concurrent callers so only one can be the hundredth of the day.
        $affected = DB::connection()->getDriverName() === 'sqlite'
            ? DB::affectingStatement(
                'INSERT INTO recording_usage
                    (company_id, site_id, usage_date, sessions_recorded, bytes_stored, created_at, updated_at)
                 VALUES (?, ?, ?, 1, 0, ?, ?)
                 ON CONFLICT (site_id, usage_date) DO UPDATE SET
                    sessions_recorded = sessions_recorded + 1,
                    updated_at = excluded.updated_at
                 WHERE sessions_recorded < ?',
                [$site->company_id, $site->id, $today, $now, $now, $limit]
            )
            : DB::affectingStatement(
                'INSERT INTO recording_usage
                    (company_id, site_id, usage_date, sessions_recorded, bytes_stored, created_at, updated_at)
                 VALUES (?, ?, ?, 1, 0, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    sessions_recorded = IF(sessions_recorded < ?, sessions_recorded + 1, sessions_recorded),
                    updated_at = IF(sessions_recorded < ?, VALUES(updated_at), updated_at)',
                [$site->company_id, $site->id, $today, $now, $now, $limit, $limit]
            );

        return $affected > 0;
    }

    /**
     * Record how much a recording has cost in storage. Kept separate from the
     * slot claim: the slot is taken once when the visit starts, while bytes
     * accumulate over every chunk that follows.
     */
    public function addBytes(Site $site, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        DB::table('recording_usage')
            ->where('site_id', $site->id)
            ->where('usage_date', now()->toDateString())
            ->increment('bytes_stored', $bytes);
    }

    public function enabledFor(Site $site): bool
    {
        return (bool) ($site->settings['recording']['enabled'] ?? false);
    }

    public function dailyLimitFor(Site $site): int
    {
        return (int) ($site->settings['recording']['daily_limit']
            ?? config('recordings.daily_limit'));
    }

    /**
     * Percentage of visits that even attempt to record. Thins traffic before
     * it reaches the quota above, so a day's recordings are spread across the
     * day rather than all captured from whoever arrives first.
     */
    public function sampleRateFor(Site $site): int
    {
        $rate = (int) ($site->settings['recording']['sample_rate']
            ?? config('recordings.sample_rate'));

        return max(0, min(100, $rate));
    }

    /**
     * How many slots are left today — for the dashboard, not the hot path.
     */
    public function remainingToday(Site $site): int
    {
        $used = (int) DB::table('recording_usage')
            ->where('site_id', $site->id)
            ->where('usage_date', now()->toDateString())
            ->value('sessions_recorded');

        return max(0, $this->dailyLimitFor($site) - $used);
    }
}

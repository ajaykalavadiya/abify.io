<?php

namespace Tests\Feature\Api;

use App\Jobs\Tracking\StoreTrackingEventsJob;
use App\Models\Site;
use App\Models\Tenant\AnalyticsEvent;
use App\Models\Tenant\VisitorSession;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

/**
 * Timestamps arrive from a browser (or a merchant's store) as ISO-8601 and
 * are written with insert()/raw fills that bypass Eloquent's datetime cast.
 *
 * These assert on the stored value rather than on the write succeeding: the
 * test suite's tenant connection is SQLite, which happily stores
 * "2026-09-14T06:09:48.868Z" as a string, while MySQL rejects it outright.
 * Asserting the format is what makes this meaningful on both.
 */
class TrackingTimestampTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Site $site;

    private string $visitor = '99999999-0000-4000-8000-000000000000';

    private string $session = '88888888-0000-4000-8000-000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantDatabase();

        $company = $this->tenantCompany();
        $this->site = Site::factory()->for($company)->create();

        app(TenantManager::class)->forCompany($company);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantDatabase();

        parent::tearDown();
    }

    private function ingest(?string $occurredAt): void
    {
        (new StoreTrackingEventsJob(
            $this->site->company_id,
            $this->site->id,
            [[
                'type' => 'pageview',
                'url' => 'https://shop.test/',
                'viewport_width' => 1440,
                'occurred_at' => $occurredAt,
            ]],
            $this->visitor,
            $this->session,
        ))->handle(app(TenantManager::class));
    }

    /**
     * The raw column value, bypassing the model's datetime cast — the cast
     * would paper over exactly the format problem being tested.
     */
    private function storedOccurredAt(): string
    {
        return (string) DB::connection('tenant')
            ->table('analytics_events')
            ->value('occurred_at');
    }

    /**
     * The reported failure: MySQL rejects the tracking script's
     * `new Date().toISOString()` output with "Incorrect datetime value".
     */
    public function test_an_iso_8601_timestamp_is_stored_in_a_format_the_database_accepts(): void
    {
        $this->ingest('2026-09-14T06:09:48.868Z');

        $stored = $this->storedOccurredAt();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $stored,
            "occurred_at was stored as [{$stored}] — MySQL rejects anything with a T or Z."
        );
        $this->assertSame('2026-09-14 06:09:48', $stored);
    }

    /**
     * Shopify and the other platforms send the shop's UTC offset. Keeping the
     * wall-clock reading would file the event hours from when it happened.
     */
    public function test_an_offset_timestamp_is_converted_to_utc_not_truncated(): void
    {
        $this->ingest('2026-09-14T06:09:48+05:30');

        $this->assertSame('2026-09-14 00:39:48', $this->storedOccurredAt());
    }

    public function test_a_missing_timestamp_falls_back_to_now(): void
    {
        $this->ingest(null);

        $this->assertEqualsWithDelta(
            now()->timestamp,
            AnalyticsEvent::first()->occurred_at->timestamp,
            5
        );
    }

    /**
     * The clock belongs to the visitor's device, not to us. A machine set to
     * next year would otherwise file events above every report's date range.
     */
    public function test_a_future_timestamp_is_clamped_to_now(): void
    {
        $this->ingest('2035-01-01T00:00:00.000Z');

        $this->assertEqualsWithDelta(
            now()->timestamp,
            AnalyticsEvent::first()->occurred_at->timestamp,
            5
        );
    }

    public function test_an_implausibly_old_timestamp_is_clamped_to_now(): void
    {
        $this->ingest('2000-01-01T00:00:00.000Z');

        $this->assertEqualsWithDelta(
            now()->timestamp,
            AnalyticsEvent::first()->occurred_at->timestamp,
            5
        );
    }

    public function test_unparseable_input_does_not_fail_the_batch(): void
    {
        $this->ingest('not a date at all');

        $this->assertSame(1, AnalyticsEvent::count());
    }

    /**
     * The session row is written through the same helper and had the same bug.
     */
    public function test_session_started_at_is_also_normalised(): void
    {
        $this->ingest('2026-09-14T06:09:48.868Z');

        $stored = (string) DB::connection('tenant')
            ->table('visitor_sessions')
            ->value('started_at');

        $this->assertSame('2026-09-14 06:09:48', $stored);
        $this->assertSame(1, VisitorSession::count());
    }
}

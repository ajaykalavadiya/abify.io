<?php

namespace Tests\Feature\Api;

use App\Jobs\Tracking\StoreTrackingEventsJob;
use App\Models\Site;
use App\Models\Tenant\VisitorSession;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

class VisitorSessionTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Site $site;

    private string $visitor = 'ffffffff-0000-4000-8000-000000000000';

    private string $session = '11111111-0000-4000-8000-000000000000';

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

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function ingest(array $events, ?string $session = null): void
    {
        (new StoreTrackingEventsJob(
            $this->site->company_id,
            $this->site->id,
            $events,
            $this->visitor,
            $session ?? $this->session,
        ))->handle(app(TenantManager::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageview(string $url, ?string $referrer = null): array
    {
        return [
            'type' => 'pageview',
            'url' => $url,
            'referrer' => $referrer,
            'viewport_width' => 1440,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_it_opens_a_session_on_the_first_batch(): void
    {
        $this->ingest([$this->pageview('https://shop.test/', 'https://google.com/')]);

        $session = VisitorSession::first();

        $this->assertNotNull($session);
        $this->assertSame($this->session, $session->session_id);
        $this->assertSame($this->visitor, $session->visitor_uuid);
        $this->assertSame('https://shop.test/', $session->entry_url);
        $this->assertSame('https://google.com/', $session->referrer);
        $this->assertSame('desktop', $session->device_type);
        $this->assertSame(1, $session->pageview_count);
    }

    /**
     * The regression this guards: the tracking script used to mint a fresh
     * session id on every page load, so a four-page visit was recorded as
     * four separate one-page sessions and pages-per-session was always 1.
     */
    public function test_a_multi_page_visit_stays_one_session(): void
    {
        $this->ingest([$this->pageview('https://shop.test/')]);
        $this->ingest([$this->pageview('https://shop.test/collections/all')]);
        $this->ingest([$this->pageview('https://shop.test/products/hat')]);

        $this->assertSame(1, VisitorSession::count());

        $session = VisitorSession::first();

        $this->assertSame(3, $session->pageview_count);
        $this->assertSame('https://shop.test/', $session->entry_url, 'Entry URL must not move.');
        $this->assertSame('https://shop.test/products/hat', $session->exit_url);
        $this->assertFalse($session->isBounce());
    }

    public function test_a_single_page_visit_is_a_bounce(): void
    {
        $this->ingest([$this->pageview('https://shop.test/')]);

        $this->assertTrue(VisitorSession::first()->isBounce());
    }

    public function test_separate_sessions_are_recorded_separately(): void
    {
        $this->ingest([$this->pageview('https://shop.test/')]);
        $this->ingest([$this->pageview('https://shop.test/')], '22222222-0000-4000-8000-000000000000');

        $this->assertSame(2, VisitorSession::count());
    }

    public function test_non_pageview_events_count_but_do_not_inflate_pageviews(): void
    {
        $this->ingest([
            $this->pageview('https://shop.test/'),
            ['type' => 'custom', 'url' => 'https://shop.test/', 'name' => 'add_to_cart', 'props' => []],
        ]);

        $session = VisitorSession::first();

        $this->assertSame(1, $session->pageview_count);
        $this->assertSame(2, $session->event_count);
    }

    public function test_duration_is_measured_from_first_to_last_activity(): void
    {
        $this->ingest([$this->pageview('https://shop.test/')]);

        $this->travel(90)->seconds();

        $this->ingest([$this->pageview('https://shop.test/cart')]);

        $this->assertGreaterThanOrEqual(89, VisitorSession::first()->durationSeconds());
    }
}

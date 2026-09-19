<?php

namespace Tests\Feature\Analytics;

use App\Actions\Tracking\RecordConversion;
use App\Jobs\Analytics\SendGa4ConversionJob;
use App\Models\Experiment;
use App\Models\ExperimentGoal;
use App\Models\ExperimentVariation;
use App\Models\Integration;
use App\Models\Site;
use App\Models\Tenant\VisitorAssignment;
use App\Models\Tenant\VisitorSession;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

class Ga4MeasurementProtocolTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Site $site;

    private string $visitor = '7b7b7b7b-0000-4000-8000-000000000000';

    private string $clientId = '1234567890.1700000000';

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

    private function connectGa4(): Integration
    {
        return Integration::create([
            'company_id' => $this->site->company_id,
            'site_id' => $this->site->id,
            'provider' => Integration::GA4,
            'external_id' => 'G-TEST12345',
            'status' => Integration::STATUS_ACTIVE,
            'credentials' => ['api_secret' => 'mp-secret'],
            'connected_at' => now(),
        ]);
    }

    private function visitorSession(?string $clientId): void
    {
        VisitorSession::create([
            'site_id' => $this->site->id,
            'session_id' => (string) Str::uuid(),
            'visitor_uuid' => $this->visitor,
            'entry_url' => 'https://shop.test/',
            'ga_client_id' => $clientId,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function experimentWithGoal(string $key = 'purchase'): void
    {
        $experiment = Experiment::factory()->for($this->site)->create(['status' => 'running']);
        $variation = ExperimentVariation::factory()->for($experiment)->create();
        ExperimentGoal::factory()->for($experiment)->create(['key' => $key, 'type' => 'custom_event']);

        VisitorAssignment::create([
            'experiment_id' => $experiment->id,
            'variation_id' => $variation->id,
            'visitor_uuid' => $this->visitor,
        ]);
    }

    private function convert(string $key = 'purchase', ?string $orderId = 'order-1'): int
    {
        return app(RecordConversion::class)->byKey(
            site: $this->site,
            visitorUuid: $this->visitor,
            goalKey: $key,
            externalId: $orderId,
            revenue: 99.50,
            currency: 'usd',
        );
    }

    public function test_a_conversion_is_reported_to_ga4(): void
    {
        Queue::fake();

        $this->connectGa4();
        $this->visitorSession($this->clientId);
        $this->experimentWithGoal();

        $this->convert();

        Queue::assertPushed(SendGa4ConversionJob::class, function (SendGa4ConversionJob $job) {
            return $job->gaClientId === $this->clientId
                && $job->eventName === 'abify_conversion'
                && $job->params['value'] === 99.50
                && $job->params['currency'] === 'USD';
        });
    }

    /**
     * The merchant made one sale. Fanning it out per experiment would
     * multiply their reported revenue by however many tests happened to be
     * running.
     */
    public function test_one_sale_is_reported_once_however_many_experiments_it_converts(): void
    {
        Queue::fake();

        $this->connectGa4();
        $this->visitorSession($this->clientId);

        foreach (range(1, 3) as $ignored) {
            $this->experimentWithGoal();
        }

        $this->assertSame(3, $this->convert(), 'Should record against all three experiments.');

        Queue::assertPushed(SendGa4ConversionJob::class, 1);
    }

    /**
     * A visitor GA4 never set a cookie for cannot be addressed, and inventing
     * a client id would attach the sale to a stranger.
     */
    public function test_nothing_is_sent_without_a_ga_client_id(): void
    {
        Queue::fake();

        $this->connectGa4();
        $this->visitorSession(null);
        $this->experimentWithGoal();

        $this->convert();

        Queue::assertNotPushed(SendGa4ConversionJob::class);
    }

    public function test_a_duplicate_order_is_not_reported_again(): void
    {
        Queue::fake();

        $this->connectGa4();
        $this->visitorSession($this->clientId);
        $this->experimentWithGoal();

        $this->convert();
        $this->convert();

        Queue::assertPushed(SendGa4ConversionJob::class, 1);
    }

    public function test_the_job_posts_to_the_measurement_protocol(): void
    {
        Http::fake(['www.google-analytics.com/*' => Http::response('', 204)]);

        $this->connectGa4();

        (new SendGa4ConversionJob(
            siteId: $this->site->id,
            gaClientId: $this->clientId,
            eventName: 'abify_conversion',
            params: ['value' => 99.5, 'currency' => 'USD'],
        ))->handle();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'measurement_id=G-TEST12345')
                && str_contains($request->url(), 'api_secret=mp-secret')
                && $body['client_id'] === $this->clientId
                && $body['events'][0]['name'] === 'abify_conversion';
        });
    }

    public function test_the_job_does_nothing_when_ga4_is_not_connected(): void
    {
        Http::fake();

        (new SendGa4ConversionJob(
            siteId: $this->site->id,
            gaClientId: $this->clientId,
            eventName: 'abify_conversion',
        ))->handle();

        Http::assertNothingSent();
    }
}

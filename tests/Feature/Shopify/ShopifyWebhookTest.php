<?php

namespace Tests\Feature\Shopify;

use App\Models\Experiment;
use App\Models\ExperimentGoal;
use App\Models\ExperimentVariation;
use App\Models\Integration;
use App\Models\Site;
use App\Models\Tenant\GoalConversion;
use App\Models\Tenant\VisitorAssignment;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private const SECRET = 'shpss_test_secret';

    private const SHOP = 'demo-store.myshopify.com';

    private Site $site;

    private Experiment $experiment;

    private ExperimentVariation $variation;

    private string $visitor = '5a5a5a5a-0000-4000-8000-000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        config(['shopify.secret' => self::SECRET]);

        $this->setUpTenantDatabase();

        $company = $this->tenantCompany();
        $this->site = Site::factory()->for($company)->create();

        app(TenantManager::class)->forCompany($company);

        $this->experiment = Experiment::factory()->for($this->site)->create(['status' => 'running']);
        $this->variation = ExperimentVariation::factory()->for($this->experiment)->create();
        ExperimentGoal::factory()->for($this->experiment)->create([
            'key' => 'purchase',
            'type' => 'custom_event',
        ]);

        Integration::create([
            'company_id' => $company->id,
            'site_id' => $this->site->id,
            'provider' => Integration::SHOPIFY,
            'external_id' => self::SHOP,
            'status' => Integration::STATUS_ACTIVE,
            'credentials' => ['access_token' => 'shpat_test'],
            'connected_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantDatabase();

        parent::tearDown();
    }

    private function enroll(): void
    {
        VisitorAssignment::create([
            'experiment_id' => $this->experiment->id,
            'variation_id' => $this->variation->id,
            'visitor_uuid' => $this->visitor,
        ]);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function send(array $order, string $topic = 'orders/paid', ?string $signature = null): TestResponse
    {
        $body = json_encode($order);

        return $this->call(
            'POST',
            '/shopify/webhook',
            server: [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature
                    ?? base64_encode(hash_hmac('sha256', $body, self::SECRET, true)),
                'HTTP_X_SHOPIFY_TOPIC' => $topic,
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => self::SHOP,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function order(string $id = '450789469', float $total = 129.95): array
    {
        return [
            'id' => $id,
            'order_number' => 1001,
            'total_price' => (string) $total,
            'currency' => 'GBP',
            'processed_at' => '2026-09-14T06:09:48+05:30',
            'line_items' => [['id' => 1], ['id' => 2]],
            'note_attributes' => [
                ['name' => 'other', 'value' => 'ignored'],
                ['name' => '_abify_vid', 'value' => $this->visitor],
            ],
        ];
    }

    public function test_a_paid_order_is_recorded_as_revenue(): void
    {
        $this->enroll();

        $this->send($this->order())->assertOk();

        $conversion = GoalConversion::first();

        $this->assertNotNull($conversion);
        $this->assertSame($this->variation->id, $conversion->variation_id);
        $this->assertSame('129.95', (string) $conversion->revenue);
        $this->assertSame('GBP', $conversion->currency);
        $this->assertSame('shopify:450789469', $conversion->external_id);
    }

    /**
     * The shop's timestamp carries its own UTC offset; storing the wall clock
     * would file the order hours from when it happened.
     */
    public function test_the_order_timestamp_is_converted_to_utc(): void
    {
        $this->enroll();

        $this->send($this->order())->assertOk();

        $this->assertSame('2026-09-14 00:39:48', GoalConversion::first()->converted_at->toDateTimeString());
    }

    /**
     * Without this anyone who learns a shop domain could post fabricated
     * orders into a merchant's results and change which variation they ship.
     */
    public function test_an_unsigned_or_forged_webhook_is_rejected(): void
    {
        $this->enroll();

        $this->send($this->order(), signature: 'not-a-real-signature')->assertStatus(401);
        $this->send($this->order(), signature: '')->assertStatus(401);

        $this->assertSame(0, GoalConversion::count());
    }

    /**
     * Shopify retries webhooks, and orders/create and orders/paid can both
     * fire for the same order.
     */
    public function test_a_redelivered_order_is_not_counted_twice(): void
    {
        $this->enroll();

        $this->send($this->order())->assertOk();
        $this->send($this->order())->assertOk();
        $this->send($this->order(), topic: 'orders/create')->assertOk();

        $this->assertSame(1, GoalConversion::count());
    }

    public function test_an_order_with_no_visitor_attribute_is_ignored(): void
    {
        $this->enroll();

        $order = $this->order();
        $order['note_attributes'] = [];

        $this->send($order)->assertOk();

        $this->assertSame(0, GoalConversion::count());
    }

    /**
     * A non-2xx tells Shopify to retry, and it will keep retrying for days
     * over something no retry can fix.
     */
    public function test_an_unknown_shop_is_acknowledged_not_retried(): void
    {
        $body = json_encode($this->order());

        $this->call('POST', '/shopify/webhook', server: [
            'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, self::SECRET, true)),
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/paid',
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'someone-else.myshopify.com',
            'CONTENT_TYPE' => 'application/json',
        ], content: $body)->assertOk();
    }

    public function test_uninstall_revokes_the_credential_but_keeps_the_record(): void
    {
        $this->send([], topic: 'app/uninstalled')->assertOk();

        $integration = Integration::where('external_id', self::SHOP)->first();

        $this->assertNotNull($integration, 'The integration row should survive an uninstall.');
        $this->assertSame(Integration::STATUS_REVOKED, $integration->status);
        $this->assertNull($integration->accessToken());
    }

    /**
     * Shopify will not approve a listing without all three.
     */
    public function test_the_mandatory_privacy_topics_are_acknowledged(): void
    {
        foreach (['shop/redact', 'customers/redact', 'customers/data_request'] as $topic) {
            $this->send(['shop_domain' => self::SHOP], topic: $topic)->assertOk();
        }
    }
}

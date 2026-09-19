<?php

namespace Tests\Feature\Api;

use App\Models\Experiment;
use App\Models\ExperimentGoal;
use App\Models\ExperimentVariation;
use App\Models\Site;
use App\Models\Tenant\GoalConversion;
use App\Models\Tenant\VisitorAssignment;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

class ConversionApiTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Site $site;

    private string $writeKey;

    private string $visitor = 'aaaaaaaa-0000-4000-8000-000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantDatabase();

        $company = $this->tenantCompany();
        $this->site = Site::factory()->for($company)->create();
        $this->writeKey = $this->site->generateWriteKey();

        app(TenantManager::class)->forCompany($company);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantDatabase();

        parent::tearDown();
    }

    /**
     * A running experiment with one variation and a goal keyed $goalKey.
     *
     * @return array{0: Experiment, 1: ExperimentVariation, 2: ExperimentGoal}
     */
    private function runningExperiment(string $goalKey = 'purchase'): array
    {
        $experiment = Experiment::factory()->for($this->site)->create(['status' => 'running']);
        $variation = ExperimentVariation::factory()->for($experiment)->create();
        $goal = ExperimentGoal::factory()->for($experiment)->create([
            'key' => $goalKey,
            'name' => 'Purchase',
            'type' => 'custom_event',
        ]);

        return [$experiment, $variation, $goal];
    }

    private function enroll(Experiment $experiment, ExperimentVariation $variation): void
    {
        VisitorAssignment::create([
            'experiment_id' => $experiment->id,
            'variation_id' => $variation->id,
            'visitor_uuid' => $this->visitor,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postConversion(array $overrides = [], ?string $key = null): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.($key ?? $this->writeKey))
            ->postJson('/api/v1/conversions', array_merge([
                'visitor_uuid' => $this->visitor,
                'goal' => 'purchase',
                'external_id' => 'order-1001',
                'revenue' => 149.95,
                'currency' => 'usd',
            ], $overrides));
    }

    public function test_it_rejects_a_request_with_no_write_key(): void
    {
        $this->postJson('/api/v1/conversions', ['visitor_uuid' => $this->visitor, 'goal' => 'purchase'])
            ->assertStatus(401);
    }

    public function test_it_rejects_an_unknown_write_key(): void
    {
        $this->postConversion(key: 'abk_totallyinvalidkeyvaluegoeshere000000000')
            ->assertStatus(401);
    }

    public function test_it_records_revenue_for_an_enrolled_visitor(): void
    {
        [$experiment, $variation] = $this->runningExperiment();
        $this->enroll($experiment, $variation);

        $this->postConversion()->assertStatus(202)->assertJson(['recorded' => 1]);

        $conversion = GoalConversion::first();

        $this->assertNotNull($conversion);
        $this->assertSame($variation->id, $conversion->variation_id);
        $this->assertSame('149.95', (string) $conversion->revenue);
        $this->assertSame('USD', $conversion->currency, 'Currency should be normalised to upper case.');
        $this->assertSame('order-1001', $conversion->external_id);
    }

    public function test_it_ignores_a_visitor_who_was_never_enrolled(): void
    {
        $this->runningExperiment();

        $this->postConversion()->assertStatus(202)->assertJson(['recorded' => 0]);

        $this->assertSame(0, GoalConversion::count());
    }

    /**
     * Shopify, WooCommerce and Magento all retry webhooks. A redelivered
     * order must not move the experiment's numbers.
     */
    public function test_it_deduplicates_a_redelivered_order(): void
    {
        [$experiment, $variation] = $this->runningExperiment();
        $this->enroll($experiment, $variation);

        $this->postConversion()->assertJson(['recorded' => 1]);
        $this->postConversion()->assertJson(['recorded' => 0]);
        $this->postConversion()->assertJson(['recorded' => 0]);

        $this->assertSame(1, GoalConversion::count());
    }

    public function test_a_different_order_is_not_treated_as_a_duplicate(): void
    {
        [$experiment, $variation] = $this->runningExperiment();
        $this->enroll($experiment, $variation);

        $this->postConversion(['external_id' => 'order-1001'])->assertJson(['recorded' => 1]);
        $this->postConversion(['external_id' => 'order-1002'])->assertJson(['recorded' => 1]);

        $this->assertSame(2, GoalConversion::count());
    }

    /**
     * One store event, many concurrent tests: a merchant running three
     * experiments expects the purchase to count against all three.
     */
    public function test_one_event_fans_out_across_every_running_experiment(): void
    {
        foreach (range(1, 3) as $ignored) {
            [$experiment, $variation] = $this->runningExperiment();
            $this->enroll($experiment, $variation);
        }

        $this->postConversion()->assertJson(['recorded' => 3]);

        $this->assertSame(3, GoalConversion::count());
    }

    public function test_it_ignores_experiments_that_are_not_running(): void
    {
        [$running, $runningVariation] = $this->runningExperiment();
        $this->enroll($running, $runningVariation);

        $paused = Experiment::factory()->for($this->site)->create(['status' => 'paused']);
        $pausedVariation = ExperimentVariation::factory()->for($paused)->create();
        ExperimentGoal::factory()->for($paused)->create(['key' => 'purchase', 'type' => 'custom_event']);
        $this->enroll($paused, $pausedVariation);

        $this->postConversion()->assertJson(['recorded' => 1]);
    }

    public function test_it_ignores_a_goal_key_no_experiment_measures(): void
    {
        [$experiment, $variation] = $this->runningExperiment('purchase');
        $this->enroll($experiment, $variation);

        $this->postConversion(['goal' => 'newsletter-signup'])->assertJson(['recorded' => 0]);
    }

    public function test_it_validates_the_payload(): void
    {
        $this->postConversion(['visitor_uuid' => 'not-a-uuid'])->assertStatus(422);
        $this->postConversion(['currency' => 'DOLLARS'])->assertStatus(422);
        $this->postConversion(['revenue' => -5])->assertStatus(422);
    }

    public function test_revenue_is_optional_for_non_purchase_goals(): void
    {
        [$experiment, $variation] = $this->runningExperiment('add-to-cart');
        $this->enroll($experiment, $variation);

        $this->postConversion([
            'goal' => 'add-to-cart',
            'revenue' => null,
            'currency' => null,
            'external_id' => null,
        ])->assertJson(['recorded' => 1]);

        $this->assertNull(GoalConversion::first()->revenue);
    }
}

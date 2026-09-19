<?php

namespace Tests\Feature\Api;

use App\Actions\Tracking\RecordConversion;
use App\Models\Experiment;
use App\Models\ExperimentGoal;
use App\Models\ExperimentVariation;
use App\Models\Site;
use App\Models\Tenant\GoalConversion;
use App\Models\Tenant\VisitorAssignment;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

class ConversionMetricsTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Site $site;

    private Experiment $experiment;

    private ExperimentVariation $variation;

    private ExperimentGoal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantDatabase();

        $company = $this->tenantCompany();
        $this->site = Site::factory()->for($company)->create();
        app(TenantManager::class)->forCompany($company);

        $this->experiment = Experiment::factory()->for($this->site)->create(['status' => 'running']);
        $this->variation = ExperimentVariation::factory()->for($this->experiment)->create();
        $this->goal = ExperimentGoal::factory()->for($this->experiment)->create([
            'key' => 'purchase',
            'type' => 'custom_event',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantDatabase();

        parent::tearDown();
    }

    private function buy(string $visitor, string $orderId, float $revenue): void
    {
        VisitorAssignment::firstOrCreate([
            'experiment_id' => $this->experiment->id,
            'visitor_uuid' => $visitor,
        ], ['variation_id' => $this->variation->id]);

        app(RecordConversion::class)->byKey(
            site: $this->site,
            visitorUuid: $visitor,
            goalKey: 'purchase',
            externalId: $orderId,
            revenue: $revenue,
            currency: 'USD',
        );
    }

    /**
     * A repeat customer converts once but spends twice. Counting rows for the
     * rate would report a 200% conversion rate off one impression.
     */
    public function test_a_repeat_buyer_counts_once_for_rate_and_twice_for_revenue(): void
    {
        $visitor = 'bbbbbbbb-0000-4000-8000-000000000000';

        $this->buy($visitor, 'order-1', 100.00);
        $this->buy($visitor, 'order-2', 50.00);

        $row = GoalConversion::where('experiment_id', $this->experiment->id)
            ->select(
                DB::raw('count(distinct visitor_uuid) as converters'),
                DB::raw('count(*) as conversions'),
                DB::raw('sum(revenue) as revenue'),
            )->first();

        $this->assertSame(1, (int) $row->converters, 'One person bought, twice.');
        $this->assertSame(2, (int) $row->conversions);
        $this->assertSame(150.0, (float) $row->revenue);
    }

    public function test_revenue_sums_across_distinct_visitors(): void
    {
        $this->buy('cccccccc-0000-4000-8000-000000000000', 'order-3', 25.50);
        $this->buy('dddddddd-0000-4000-8000-000000000000', 'order-4', 74.50);

        $this->assertSame(
            100.0,
            (float) GoalConversion::where('experiment_id', $this->experiment->id)->sum('revenue')
        );
    }

    public function test_a_duplicate_order_does_not_inflate_revenue(): void
    {
        $visitor = 'eeeeeeee-0000-4000-8000-000000000000';

        $this->buy($visitor, 'order-5', 200.00);
        $this->buy($visitor, 'order-5', 200.00);

        $this->assertSame(
            200.0,
            (float) GoalConversion::where('experiment_id', $this->experiment->id)->sum('revenue')
        );
    }
}

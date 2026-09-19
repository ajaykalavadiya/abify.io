<?php

namespace Tests\Feature\Api;

use App\Models\Experiment;
use App\Models\ExperimentVariation;
use App\Models\Site;
use App\Models\Tenant\HeatmapEvent;
use App\Models\Tenant\HeatmapSession;
use App\Models\Tenant\VisitorAssignment;
use App\Support\Analytics\HeatmapAggregator;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

/**
 * Heatmaps segmented by the variation a visitor saw — "why did B convert
 * worse" is only answerable if the map shows B's visitors and nobody else's.
 */
class VariationHeatmapTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Site $site;

    private Experiment $experiment;

    private ExperimentVariation $control;

    private ExperimentVariation $variant;

    private string $url = 'https://shop.test/pricing';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantDatabase();

        $company = $this->tenantCompany();
        $this->site = Site::factory()->for($company)->create();

        app(TenantManager::class)->forCompany($company);

        $this->experiment = Experiment::factory()->for($this->site)->create(['status' => 'running']);
        $this->control = ExperimentVariation::factory()->for($this->experiment)
            ->create(['key' => 'control', 'name' => 'Control', 'is_control' => true]);
        $this->variant = ExperimentVariation::factory()->for($this->experiment)
            ->create(['key' => 'variant-b', 'name' => 'Variation B', 'is_control' => false]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantDatabase();

        parent::tearDown();
    }

    /**
     * A visitor bucketed into $variation who clicked $clicks times.
     */
    private function visitor(?ExperimentVariation $variation, int $clicks): string
    {
        $visitorUuid = (string) Str::uuid();

        if ($variation) {
            VisitorAssignment::create([
                'experiment_id' => $this->experiment->id,
                'variation_id' => $variation->id,
                'visitor_uuid' => $visitorUuid,
            ]);
        }

        $session = HeatmapSession::create([
            'site_id' => $this->site->id,
            'session_id' => (string) Str::uuid(),
            'visitor_uuid' => $visitorUuid,
            'url' => $this->url,
            'url_hash' => hash('sha256', $this->url),
            'viewport_width' => 1440,
            'viewport_height' => 900,
            'doc_width' => 1440,
            'doc_height' => 3000,
            'device_type' => 'desktop',
            'started_at' => now(),
        ]);

        for ($i = 0; $i < $clicks; $i++) {
            HeatmapEvent::insert([
                'heatmap_session_id' => $session->id,
                'site_id' => $this->site->id,
                'url' => $this->url,
                'url_hash' => hash('sha256', $this->url),
                'type' => 'click',
                'device_type' => 'desktop',
                'viewport_width' => 1440,
                'x_pct' => 5000,
                'y_pct' => 2000 + ($i * 10),
                'created_at' => now(),
            ]);
        }

        return $visitorUuid;
    }

    private function aggregator(?int $variationId): HeatmapAggregator
    {
        return app(HeatmapAggregator::class)->forVariation($variationId);
    }

    private function clicksFor(?int $variationId): int
    {
        $points = $this->aggregator($variationId)
            ->points($this->site, hash('sha256', $this->url), 'desktop', 'click');

        return (int) array_sum(array_column($points, 'weight'));
    }

    public function test_a_variation_heatmap_only_shows_that_variation_s_visitors(): void
    {
        $this->visitor($this->control, clicks: 3);
        $this->visitor($this->variant, clicks: 7);

        $this->assertSame(3, $this->clicksFor($this->control->id));
        $this->assertSame(7, $this->clicksFor($this->variant->id));
    }

    public function test_the_unfiltered_map_shows_everyone(): void
    {
        $this->visitor($this->control, clicks: 3);
        $this->visitor($this->variant, clicks: 7);
        $this->visitor(null, clicks: 5);

        $this->assertSame(15, $this->clicksFor(null));
    }

    /**
     * Someone who was never in the test has no variation, and must not be
     * counted into either side of it.
     */
    public function test_unassigned_visitors_are_excluded_from_every_variation(): void
    {
        $this->visitor(null, clicks: 9);

        $this->assertSame(0, $this->clicksFor($this->control->id));
        $this->assertSame(0, $this->clicksFor($this->variant->id));
    }

    public function test_session_counts_are_segmented_too(): void
    {
        $this->visitor($this->control, clicks: 1);
        $this->visitor($this->variant, clicks: 1);
        $this->visitor($this->variant, clicks: 1);

        $controlSummary = $this->aggregator($this->control->id)
            ->summary($this->site, hash('sha256', $this->url), 'desktop');
        $variantSummary = $this->aggregator($this->variant->id)
            ->summary($this->site, hash('sha256', $this->url), 'desktop');

        $this->assertSame(1, $controlSummary['sessions']);
        $this->assertSame(2, $variantSummary['sessions']);
    }

    public function test_scroll_depth_is_segmented_too(): void
    {
        $this->visitor($this->control, clicks: 2);

        $variantScroll = $this->aggregator($this->variant->id)
            ->scrollDepth($this->site, hash('sha256', $this->url), 'desktop');

        $this->assertSame(0, $variantScroll['sessions'], 'Variant has no sessions on this page.');
    }

    public function test_the_page_list_is_segmented_too(): void
    {
        $this->visitor($this->control, clicks: 4);

        $this->assertCount(1, $this->aggregator($this->control->id)->pages($this->site));
        $this->assertCount(0, $this->aggregator($this->variant->id)->pages($this->site));
    }
}

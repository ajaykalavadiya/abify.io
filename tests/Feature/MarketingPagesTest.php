<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every feature advertised on the index must have a page behind it.
 *
 * The regression this guards: three features were added to the features list
 * without detail pages, so the marketing site linked to its own 404s for a
 * while before anyone noticed.
 */
class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function featureSlugs(): array
    {
        return array_map(fn (string $slug) => [$slug], [
            'ab-testing',
            'url-targeting',
            'split-url-tests',
            'heatmaps',
            'variation-heatmaps',
            'revenue-tracking',
            'server-side-conversions',
            'session-recording',
            'shopify',
            'analytics',
            'live-preview-debugging',
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function postSlugs(): array
    {
        return array_map(fn (string $slug) => [$slug], [
            'conversion-rate-is-not-revenue',
            'heatmaps-by-variation',
            'why-shopify-checkout-cannot-be-tested',
        ]);
    }

    #[DataProvider('featureSlugs')]
    public function test_every_feature_page_resolves(string $slug): void
    {
        $this->get("/features/{$slug}")->assertOk();
    }

    #[DataProvider('postSlugs')]
    public function test_every_new_blog_post_resolves(string $slug): void
    {
        $this->get("/blog/{$slug}")->assertOk();
    }

    public function test_an_unknown_feature_is_a_404(): void
    {
        $this->get('/features/does-not-exist')->assertNotFound();
    }

    /**
     * The pricing page reads its limits from config so the advertised numbers
     * cannot drift from the ones actually enforced.
     */
    public function test_the_pricing_page_advertises_the_growth_plan(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pricing.growthMonthly', 50)
                ->where('pricing.growthYearly', 500)
                ->where('pricing.growth.heatmaps_per_month', 10000)
                ->where('pricing.growth.label', 'Growth'));
    }
}

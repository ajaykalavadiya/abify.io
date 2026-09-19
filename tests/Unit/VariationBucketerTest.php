<?php

namespace Tests\Unit;

use App\Models\ExperimentVariation;
use App\Support\Tracking\VariationBucketer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class VariationBucketerTest extends TestCase
{
    private VariationBucketer $bucketer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bucketer = new VariationBucketer;
    }

    /**
     * @param  array<int, int>  $weights  variation id => traffic weight
     * @return Collection<int, ExperimentVariation>
     */
    private function variations(array $weights): Collection
    {
        return collect($weights)->map(function (int $weight, int $id) {
            $variation = new ExperimentVariation(['traffic_weight' => $weight]);
            $variation->forceFill(['id' => $id, 'traffic_weight' => $weight]);

            return $variation;
        })->values();
    }

    /** @return array<int, string> */
    private function visitors(int $count): array
    {
        return array_map(
            fn (int $i) => sprintf('%08x-0000-4000-8000-000000000000', $i),
            range(1, $count)
        );
    }

    public function test_full_allocation_buckets_every_visitor(): void
    {
        $variations = $this->variations([1 => 50, 2 => 50]);

        foreach ($this->visitors(200) as $visitor) {
            $this->assertNotNull($this->bucketer->bucket($visitor, 7, $variations, 100));
        }
    }

    public function test_zero_allocation_buckets_nobody(): void
    {
        $variations = $this->variations([1 => 50, 2 => 50]);

        foreach ($this->visitors(50) as $visitor) {
            $this->assertNull($this->bucketer->bucket($visitor, 7, $variations, 0));
        }
    }

    public function test_partial_allocation_admits_roughly_that_share_of_visitors(): void
    {
        $variations = $this->variations([1 => 50, 2 => 50]);
        $visitors = $this->visitors(4000);

        $admitted = count(array_filter(
            $visitors,
            fn (string $v) => $this->bucketer->bucket($v, 7, $variations, 25) !== null
        ));

        $share = $admitted / count($visitors) * 100;
        $this->assertEqualsWithDelta(25, $share, 3.0, "Expected ~25% admitted, got {$share}%");
    }

    /**
     * The regression this guards: sharing one hash between the allocation
     * gate and the variation bucket would confine a part-traffic test to the
     * variations sitting in the low buckets, so a 50/50 split would come out
     * 100/0 the moment the test ran below full traffic.
     */
    public function test_variation_split_stays_even_under_partial_allocation(): void
    {
        $variations = $this->variations([1 => 50, 2 => 50]);

        $counts = [1 => 0, 2 => 0];

        foreach ($this->visitors(4000) as $visitor) {
            $variation = $this->bucketer->bucket($visitor, 7, $variations, 50);

            if ($variation) {
                $counts[$variation->id]++;
            }
        }

        $total = array_sum($counts);
        $this->assertGreaterThan(0, $total);

        $controlShare = $counts[1] / $total * 100;
        $this->assertEqualsWithDelta(
            50, $controlShare, 4.0,
            "Variation split skewed under partial allocation: {$counts[1]} vs {$counts[2]}"
        );
    }

    public function test_bucketing_is_deterministic_for_a_visitor(): void
    {
        $variations = $this->variations([1 => 50, 2 => 50]);
        $visitor = 'a1b2c3d4-0000-4000-8000-000000000000';

        $first = $this->bucketer->bucket($visitor, 7, $variations, 60);

        for ($i = 0; $i < 20; $i++) {
            $repeat = $this->bucketer->bucket($visitor, 7, $variations, 60);
            $this->assertSame($first?->id, $repeat?->id);
        }
    }

    public function test_allocation_is_independent_per_experiment(): void
    {
        $variations = $this->variations([1 => 100]);
        $visitors = $this->visitors(500);

        $inSeven = array_filter($visitors, fn ($v) => $this->bucketer->isEligible($v, 7, 50));
        $inEight = array_filter($visitors, fn ($v) => $this->bucketer->isEligible($v, 8, 50));

        $this->assertNotSame(
            array_keys($inSeven), array_keys($inEight),
            'Two experiments at the same allocation admitted an identical visitor set.'
        );
    }
}

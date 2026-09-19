<?php

namespace App\Support\Tracking;

use App\Models\ExperimentVariation;
use Illuminate\Support\Collection;

/**
 * Deterministic, stateless visitor bucketing. Used both server-side
 * (Tracking\TrackingAssignController) and mirrored in the JS tracking script
 * template so client and server agree without a network round trip gating
 * the anti-flicker reveal.
 */
class VariationBucketer
{
    /**
     * @param  Collection<int, ExperimentVariation>  $variations  ordered by id
     * @param  int  $trafficAllocation  percentage of visitors eligible (1-100)
     */
    public function bucket(string $visitorUuid, int $experimentId, Collection $variations, int $trafficAllocation = 100): ?ExperimentVariation
    {
        if (! $this->isEligible($visitorUuid, $experimentId, $trafficAllocation)) {
            return null;
        }

        $bucket = crc32("{$visitorUuid}-{$experimentId}") % 100;

        $cumulative = 0;

        foreach ($variations->sortBy('id') as $variation) {
            $cumulative += $variation->traffic_weight;

            if ($bucket < $cumulative) {
                return $variation;
            }
        }

        return null;
    }

    /**
     * Is this visitor inside the experiment's traffic allocation at all?
     *
     * Salted differently from the variation bucket on purpose. Sharing one
     * hash would tie the two decisions together — at 50% allocation only the
     * variations sitting in buckets 0-49 could ever be handed out, so the
     * split would silently skew the moment a test ran below full traffic.
     */
    public function isEligible(string $visitorUuid, int $experimentId, int $trafficAllocation): bool
    {
        if ($trafficAllocation >= 100) {
            return true;
        }

        if ($trafficAllocation <= 0) {
            return false;
        }

        return crc32("{$visitorUuid}-{$experimentId}-alloc") % 100 < $trafficAllocation;
    }
}

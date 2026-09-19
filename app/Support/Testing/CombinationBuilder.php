<?php

namespace App\Support\Testing;

use Illuminate\Support\Str;

/**
 * Expands a multivariate test's factors into the combinations that actually
 * get served.
 *
 * A factor is one element under test (a headline, a button, a hero image),
 * given as a CSS selector plus an action; its options are the values to try.
 * Every combination of one option per factor becomes a normal variation whose
 * payload carries one element change per factor — which is why the tracking
 * script needs no concept of "factors" at all: it already applies a list of
 * changes per variation.
 *
 * Factor shape: ['name' => 'Headline', 'selector' => '#hero h1',
 *                'action' => 'text', 'options' => [['label' => 'H1-A', 'value' => '...'], ...]]
 */
class CombinationBuilder
{
    /**
     * Hard ceiling on generated combinations. Combinations multiply, so three
     * factors with four options each is already 64 variations splitting the
     * same traffic — far past the point any of them reach significance.
     */
    public const MAX_COMBINATIONS = 24;

    /**
     * @param  array<int, array<string, mixed>>  $factors
     */
    public function countCombinations(array $factors): int
    {
        $total = 1;

        foreach ($factors as $factor) {
            $options = is_array($factor['options'] ?? null) ? $factor['options'] : [];
            $total *= max(1, count($options));
        }

        return $factors === [] ? 0 : $total;
    }

    /**
     * Build one variation payload per combination.
     *
     * The first combination (every factor's first option) is the control: it
     * is the page as it stands, so it carries no changes.
     *
     * @param  array<int, array<string, mixed>>  $factors
     * @return array<int, array{name: string, key: string, is_control: bool, traffic_weight: int, payload: array<string, mixed>|null}>
     */
    public function build(array $factors): array
    {
        $optionSets = [];

        foreach ($factors as $factor) {
            $options = is_array($factor['options'] ?? null) ? array_values($factor['options']) : [];

            if ($options === []) {
                continue;
            }

            $optionSets[] = ['factor' => $factor, 'options' => $options];
        }

        if ($optionSets === []) {
            return [];
        }

        $combinations = [[]];

        foreach ($optionSets as $index => $set) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($set['options'] as $optionIndex => $option) {
                    $next[] = [...$combination, $index => ['option' => $option, 'index' => $optionIndex]];
                }
            }

            $combinations = $next;
        }

        $weights = $this->splitEvenly(count($combinations));

        return array_map(function (array $combination, int $position) use ($optionSets, $weights): array {
            $isControl = ! array_filter($combination, fn (array $choice): bool => $choice['index'] !== 0);

            $labels = [];
            $changes = [];

            foreach ($combination as $factorIndex => $choice) {
                $factor = $optionSets[$factorIndex]['factor'];
                $option = $choice['option'];

                $labels[] = (string) ($option['label'] ?? 'Option '.($choice['index'] + 1));

                // The control combination leaves the page untouched.
                if (! $isControl) {
                    $changes[] = [
                        'selector' => (string) ($factor['selector'] ?? ''),
                        'action' => (string) ($factor['action'] ?? 'text'),
                        'value' => $option['value'] ?? null,
                    ];
                }
            }

            $name = $isControl ? 'Control' : implode(' + ', $labels);

            return [
                'name' => $name,
                'key' => $isControl ? 'control' : Str::slug($name).'-'.Str::random(4),
                'is_control' => $isControl,
                'traffic_weight' => $weights[$position],
                'payload' => $changes === [] ? null : ['changes' => $changes],
            ];
        }, $combinations, array_keys($combinations));
    }

    /**
     * Split 100% across n combinations, giving the remainder to the earliest
     * ones so the weights always total exactly 100.
     *
     * @return array<int, int>
     */
    protected function splitEvenly(int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $base = intdiv(100, $count);
        $remainder = 100 % $count;

        return array_map(
            fn (int $index): int => $base + ($index < $remainder ? 1 : 0),
            range(0, $count - 1),
        );
    }
}

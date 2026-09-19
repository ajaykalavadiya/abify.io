<?php

namespace Database\Factories;

use App\Models\Experiment;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Experiment>
 */
class ExperimentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'site_id' => Site::factory(),
            'key' => Str::slug($name),
            'name' => $name,
            'status' => 'draft',
            'type' => 'ab',
            'traffic_allocation' => 100,
        ];
    }

    /**
     * Derive company_id from the resolved site once site_id is known.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Experiment $experiment) {
            if (! $experiment->company_id && $experiment->site_id) {
                $experiment->company_id = Site::find($experiment->site_id)?->company_id;
            }
        });
    }
}

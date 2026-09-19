<?php

namespace Database\Factories;

use App\Models\Experiment;
use App\Models\ExperimentVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperimentVariation>
 */
class ExperimentVariationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'experiment_id' => Experiment::factory(),
            'key' => 'control',
            'name' => 'Control',
            'is_control' => true,
            'traffic_weight' => 100,
            'payload' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Experiment;
use App\Models\ExperimentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperimentGoal>
 */
class ExperimentGoalFactory extends Factory
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
            'key' => 'pageview-goal',
            'name' => 'Pageview goal',
            'type' => 'pageview',
            'match_type' => 'exact',
            'target' => '/',
        ];
    }
}

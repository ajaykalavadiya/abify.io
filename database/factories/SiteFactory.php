<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->domainWord(),
            'domain' => fake()->domainName(),
            'status' => 'active',
            'settings' => ['timeout_ms' => 2000],
        ];
    }
}

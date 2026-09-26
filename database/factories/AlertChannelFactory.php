<?php

namespace Database\Factories;

use App\Models\AlertChannel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertChannel>
 */
class AlertChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => '#'.fake()->word(),
            'webhook_url' => 'https://hooks.slack.com/services/T000/B000/'.fake()->regexify('[A-Za-z0-9]{24}'),
        ];
    }
}

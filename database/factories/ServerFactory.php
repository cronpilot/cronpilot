<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server>
 */
class ServerFactory extends Factory
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
            'name' => ucwords(fake()->words(3, true)),
            'hostname' => (rand(0,100) < 10) ? fake()->ipv4() : fake()->domainWord() . '.' . fake()->domainWord() . '.' . fake()->domainName(),
            'ssh_port' => 22,
        ];
    }
}

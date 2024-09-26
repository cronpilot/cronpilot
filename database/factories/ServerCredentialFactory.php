<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerCredential>
 */
class ServerCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->word(),
            'username' => fake()->userName(),
            'ssh_private_key' => fake()->regexify('[A-Za-z0-9]{256}'),
        ];
    }
}

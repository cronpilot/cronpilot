<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Parameter>
 */
class ParameterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([
            'string',
            'integer',
        ]);

        return [
            'name' => fake()->word(),
            'type' => $type,
            'description' => fake()->sentence(),
            'default' => fake()->boolean()
                ? $type === 'string' ? fake()->word() : fake()->randomNumber()
                : null,
            'options' => fake()->boolean()
                ? json_encode(fake()->words())
                : null,
            'nullable' => fake()->boolean(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Parameter;
use App\Models\Run;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RunParameter>
 */
class RunParameterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::class,
            'parameter_id' => Parameter::class,
            'value' => json_encode(fake()->word()),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Parameter;
use App\Models\Run;
use App\Models\Tenant;
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
            'tenant_id' => Tenant::factory(),
            'run_id' => Run::factory(),
            'parameter_id' => Parameter::factory(),
            'value' => json_encode(fake()->word()),
        ];
    }
}

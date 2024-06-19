<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Run>
 */
class RunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $triggerable = $this->triggerable();

        return [
            'task_id' => Task::factory(),
            'status' => RunStatus::cases(),
            'output' => fake()->sentence(),
            'run_time' => fake()->numberBetween(1, 30),
            'triggerable_id' => $triggerable?->factory(),
            'triggerable_type' => $triggerable,
        ];
    }

    public function triggerable()
    {
        return fake()->randomElement([
            User::class,
            Task::class,
            null,
        ]);
    }
}

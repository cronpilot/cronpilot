<?php

namespace Database\Seeders;

use App\Models\Parameter;
use App\Models\Run;
use App\Models\RunParameter;
use App\Models\Server;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Tenant::factory(3)->create()->each(function (Tenant $tenant): void {
            $users = User::factory(5)->create()->each(fn (User $user) => $user->tenants()->attach($tenant));

            Server::factory(5)
                ->create(['tenant_id' => $tenant->id])
                ->each(function (Server $server): void {
                    Task::factory(fake()->numberBetween(5, 15))
                        ->create(['tenant_id' => $server->tenant->id])
                        ->each(function (Task $task): void {
                            $parameters = Parameter::factory(fake()->numberBetween(0, 3))->create([
                                'tenant_id' => $task->tenant->id,
                                'task_id' => $task->id,
                            ]);

                            Run::factory(fake()->numberBetween(5, 20))
                                ->create(function () use ($task): array {
                                    $triggerableClass = Run::factory()->getRandomTriggerableClass();

                                    $triggerable = match ($triggerableClass) {
                                        User::class => User::whereHas(
                                            'tenants',
                                            fn (Builder $query): Builder => $query->where('id', $task->tenant_id)
                                        )->inRandomOrder()->first(),
                                        Task::class => Task::whereTenantId($task->tenant_id)->inRandomOrder()->first(),
                                        default => null,
                                    };

                                    return [
                                        'tenant_id' => $task->tenant_id,
                                        'task_id' => $task->id,
                                        'triggerable_type' => $triggerableClass,
                                        'triggerable_id' => $triggerable?->id,
                                    ];
                                })
                                ->each(function (Run $run) use ($parameters): void {
                                    foreach ($parameters as $parameter) {
                                        RunParameter::factory()->create([
                                            'tenant_id' => $parameter->tenant_id,
                                            'run_id' => $run->id,
                                            'parameter_id' => $parameter->id,
                                        ]);
                                    }
                                });
                        });
                });
        });
    }
}

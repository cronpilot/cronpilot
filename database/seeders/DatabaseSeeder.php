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
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Tenant::factory()
            ->has(User::factory(5))
            ->has(
                Server::factory(3)
                    ->has(
                        Task::factory(10)
                            ->has(
                                Parameter::factory(3)
                                    ->has(RunParameter::factory(), 'runs')
                            )
                            ->has(
                                Run::factory(10)
                                    ->has(RunParameter::factory(), 'parameters')
                            )
                    )
            )
            ->create();
    }
}

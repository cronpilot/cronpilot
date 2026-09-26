<?php

use App\Actions\RunAllReadyTasks;
use App\Enums\TaskStatus;
use App\Jobs\RunTaskJob;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

it('dispatches a job for every task that is ready to run', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $ready = Task::factory()->for($tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'paused' => false,
        'schedule' => 'FREQ=HOURLY',
        'next_run_at' => now()->subMinute(),
    ]);
    Task::factory()->for($tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'paused' => false,
        'schedule' => 'FREQ=HOURLY',
        'next_run_at' => now()->addHour(),
    ]);

    (new RunAllReadyTasks)();

    Queue::assertPushed(RunTaskJob::class, 1);
    Queue::assertPushed(fn (RunTaskJob $job) => $job->taskId === $ready->id);
});

it('does not queue a task again while its run is still waiting on a worker', function () {
    Queue::fake();

    $task = Task::factory()->for(Tenant::factory())->create([
        'status' => TaskStatus::ACTIVE,
        'paused' => false,
        'schedule' => 'FREQ=HOURLY',
        'next_run_at' => now()->subMinute(),
    ]);

    (new RunAllReadyTasks)();
    $this->travel(1)->minute();
    (new RunAllReadyTasks)();

    Queue::assertPushed(RunTaskJob::class, 1);
    expect($task->fresh()->next_run_at)->toBeGreaterThan(now()->toDateTimeString());
});

it('does not dispatch paused tasks', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    Task::factory()->for($tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'paused' => true,
        'next_run_at' => now()->subMinute(),
    ]);

    (new RunAllReadyTasks)();

    Queue::assertNothingPushed();
});

it('does not dispatch disabled tasks', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    Task::factory()->for($tenant)->create([
        'status' => TaskStatus::DISABLED,
        'paused' => false,
        'next_run_at' => now()->subMinute(),
    ]);

    (new RunAllReadyTasks)();

    Queue::assertNothingPushed();
});

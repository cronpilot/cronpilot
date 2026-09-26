<?php

use App\Actions\RunTask;
use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use App\Models\Run;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

// These tasks have no server, so a run that isn't skipped is recorded as
// FAILED — enough to tell the two apart without an SSH connection.
function lockTestTask(array $attributes = []): Task
{
    return Task::factory()->for(Tenant::factory())->create([
        'status' => TaskStatus::ACTIVE,
        'schedule' => 'FREQ=HOURLY',
        ...$attributes,
    ]);
}

function holdLock(Task $task): void
{
    Cache::lock("tasks.{$task->id}.running", RunTask::LOCK_SECONDS)->get();
}

it('records a skipped run when a previous run still holds the lock', function () {
    $task = lockTestTask();
    holdLock($task);

    (new RunTask)->handle($task->id);

    $run = Run::where('task_id', $task->id)->sole();
    expect($run->status)->toBe(RunStatus::SKIPPED)
        ->and($run->output)->toContain('Previous run still in progress');
});

it('runs a task that allows overlapping even while the lock is held', function () {
    $task = lockTestTask(['allow_overlapping' => true]);
    holdLock($task);

    (new RunTask)->handle($task->id);

    expect(Run::where('task_id', $task->id)->sole()->status)->toBe(RunStatus::FAILED);
});

it('runs a forced run even while the lock is held', function () {
    $task = lockTestTask();
    holdLock($task);

    (new RunTask)->handle($task->id, ignoreLock: true);

    expect(Run::where('task_id', $task->id)->sole()->status)->toBe(RunStatus::FAILED);
});

it('releases the lock once a run finishes', function () {
    $task = lockTestTask();

    (new RunTask)->handle($task->id);
    (new RunTask)->handle($task->id);

    expect(Run::where('task_id', $task->id)->pluck('status')->all())
        ->toBe([RunStatus::FAILED, RunStatus::FAILED]);
});

it('knows when a task has a run in progress', function () {
    $task = lockTestTask();
    expect($task->isRunning())->toBeFalse();

    Run::factory()->for($task)->create([
        'tenant_id' => $task->tenant_id,
        'status' => RunStatus::RUNNING,
    ]);

    expect($task->isRunning())->toBeTrue();
});

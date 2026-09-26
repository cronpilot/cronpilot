<?php

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Task;
use App\Models\Tenant;

function runFor(Task $task, RunStatus $status, string $createdAt): Run
{
    return Run::factory()->for($task)->create([
        'tenant_id' => $task->tenant_id,
        'status' => $status,
        'created_at' => $createdAt,
    ]);
}

it('reports the status of the most recent finished run', function () {
    $task = Task::factory()->for(Tenant::factory())->create();
    runFor($task, RunStatus::SUCCESSFUL, '2026-09-01 10:00:00');
    runFor($task, RunStatus::FAILED, '2026-09-02 10:00:00');

    expect($task->lastRunStatus)->toBe(RunStatus::FAILED);
});

it('ignores runs that are still running or were skipped', function () {
    $task = Task::factory()->for(Tenant::factory())->create();
    runFor($task, RunStatus::SUCCESSFUL, '2026-09-01 10:00:00');
    runFor($task, RunStatus::FAILED, '2026-09-02 10:00:00');
    runFor($task, RunStatus::SKIPPED, '2026-09-03 10:00:00');
    runFor($task, RunStatus::RUNNING, '2026-09-04 10:00:00');

    expect($task->lastRunStatus)->toBe(RunStatus::FAILED);
});

it('breaks ties between runs created in the same second by id', function () {
    $task = Task::factory()->for(Tenant::factory())->create();
    runFor($task, RunStatus::SUCCESSFUL, '2026-09-01 10:00:00');
    runFor($task, RunStatus::FAILED, '2026-09-01 10:00:00');

    expect($task->lastRunStatus)->toBe(RunStatus::FAILED);
});

it('has no last run status before any run has finished', function () {
    $task = Task::factory()->for(Tenant::factory())->create();
    runFor($task, RunStatus::RUNNING, '2026-09-01 10:00:00');

    expect($task->lastRunStatus)->toBeNull();
});

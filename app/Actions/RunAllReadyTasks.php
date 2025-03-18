<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class RunAllReadyTasks
{
    public function __invoke(): void
    {
        Log::info('Running all ready tasks');
        $tasks = Task::readyToRun()->get();

        foreach ($tasks as $task) {
            Log::info("Running task: {$task->id}");
            (new RunTask())->handle($task->id);
        }
    }
}

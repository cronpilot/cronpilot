<?php

namespace App\Actions;

use App\Models\Task;

class RunAllReadyTasks
{
    public function __invoke(): void
    {
        $tasks = Task::where('next_run_at', '<=', now())->get();
        foreach ($tasks as $task) {
            (new RunTask())->handle($task->id);
        }
    }
}

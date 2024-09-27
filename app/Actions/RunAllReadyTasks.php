<?php

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Models\Task;

class RunAllReadyTasks
{
    public function __invoke(): void
    {
        $tasks = Task::readyToRun()->get();


        foreach ($tasks as $task) {
            (new RunTask())->handle($task->id);
        }
    }
}

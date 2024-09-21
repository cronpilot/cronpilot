<?php

namespace App\Console\Commands;

use App\Actions\RunAllReadyTasks as RunAllReadyTasksAction;
use Exception;
use Illuminate\Console\Command;

class RunAllReadyTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-all-ready-tasks';

    protected $description = 'Run all tasks set to run now.';

    /**
     * @throws Exception
     */
    public function handle(RunAllReadyTasksAction $runAllReadyTasksAction): ?int
    {
        $runAllReadyTasksAction();

        return 0;
    }
}

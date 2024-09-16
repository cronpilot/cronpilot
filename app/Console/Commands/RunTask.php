<?php

namespace App\Console\Commands;

use App\Actions\RunTask as RunTaskAction;
use Exception;
use Illuminate\Console\Command;

class RunTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-task
                            {taskId : The task ID to execute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a task with the specified id.';

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle(RunTaskAction $runTask): ?int
    {
        $runTask->handle($this->argument('taskId'));

        return 0;
    }
}

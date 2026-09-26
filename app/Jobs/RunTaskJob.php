<?php

namespace App\Jobs;

use App\Actions\RunTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a single task on the queue.
 *
 * Task execution opens an SSH connection and blocks for the lifetime of the
 * remote command, so it cannot run serially inside the scheduler's one-minute
 * tick without holding up every other due task.
 */
class RunTaskJob implements ShouldQueue
{
    use Queueable;

    /**
     * A slow remote command should not be killed by the queue's default
     * timeout, and a failed run is already recorded on the Run row rather
     * than being something to retry blindly.
     */
    public int $timeout = RunTask::LOCK_SECONDS;

    public int $tries = 1;

    public function __construct(public int $taskId) {}

    public function handle(RunTask $runTask): void
    {
        $runTask->handle($this->taskId);
    }
}

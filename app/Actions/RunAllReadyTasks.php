<?php

namespace App\Actions;

use App\Jobs\RunTaskJob;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

class RunAllReadyTasks
{
    /**
     * Queue every due task.
     *
     * Each task is dispatched rather than executed here, so one slow remote
     * command no longer holds up every other task due in the same tick.
     */
    public function __invoke(): void
    {
        Log::info('Running all ready tasks');

        Task::readyToRun()->get()->each(function (Task $task): void {
            if (! $this->claim($task)) {
                return;
            }

            Log::info("Queueing task: {$task->id}");

            RunTaskJob::dispatch($task->id);
        });
    }

    /**
     * Advance the task's next run before it is queued.
     *
     * RunTask only moves next_run_at forward once the command finishes, so
     * without this a task still waiting on a worker would be due again on the
     * next tick and queued a second time. The update is conditional on the
     * next_run_at we read, so overlapping scheduler processes can't both
     * claim the same occurrence.
     */
    private function claim(Task $task): bool
    {
        $claimedRunAt = $task->getRawOriginal('next_run_at');

        $task->scheduleNextRun(now());

        return Task::whereKey($task->id)
            ->where('next_run_at', $claimedRunAt)
            ->update([
                'next_run_at' => $task->next_run_at,
                'status' => $task->status,
            ]) === 1;
    }
}

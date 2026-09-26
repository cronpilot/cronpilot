<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Migrations\Migration;

/**
 * Next run times used to be calculated as if every schedule were in UTC,
 * and the form stored them as local time. Recalculate them all with the
 * fixed, timezone-aware calculation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Task::query()
            ->whereNotNull('schedule')
            ->where('paused', false)
            ->where('status', '!=', TaskStatus::DISABLED)
            ->each(function (Task $task): void {
                $task->scheduleNextRun(now());
                $task->saveQuietly();
            });
    }

    public function down(): void
    {
        //
    }
};

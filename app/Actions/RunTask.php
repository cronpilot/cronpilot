<?php

namespace App\Actions;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Task;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Throwable;

class RunTask
{
    /**
     * How long a task's run lock is held before it expires on its own, so a
     * crashed worker can't block the task forever. Matches RunTaskJob's timeout.
     */
    public const LOCK_SECONDS = 3600;

    /**
     * Run a task, unless a previous run of it is still in progress.
     *
     * Tasks that allow overlapping runs, and manual runs the user has chosen
     * to force, skip the lock entirely.
     *
     * @throws Exception
     */
    public function handle(int $taskId, bool $ignoreLock = false): void
    {
        $task = Task::find($taskId);
        if (! $task) {
            Log::warning("Task not found: {$taskId}");
            return;
        }

        if ($task->allow_overlapping || $ignoreLock) {
            $this->run($task);

            return;
        }

        $lock = Cache::lock("tasks.{$task->id}.running", self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->recordSkipped($task);

            return;
        }

        try {
            $this->run($task);
        } finally {
            $lock->release();
        }
    }

    private function recordSkipped(Task $task): void
    {
        Log::info("Skipping task {$task->id}: previous run still in progress");

        $run = new Run;
        $run->tenant_id = $task->tenant->id;
        $run->task_id = $task->id;
        $run->status = RunStatus::SKIPPED;
        $run->output = '[SKIPPED] Previous run still in progress.';
        $run->duration = 0;
        $run->save();
    }

    /**
     * @throws Exception
     */
    private function run(Task $task): void
    {
        $run = new Run;
        $run->tenant_id = $task->tenant->id;
        $run->task_id = $task->id;
        $run->status = RunStatus::RUNNING;
        $run->output = '';
        $run->duration = 0;
        $run->save();
        $start = now();

        try {
            $server = $task->server;
            if (! $server) {
                throw new Exception('Server not found');
            }

            $credential = $task->serverCredential;

            if (! $credential) {
                throw new Exception('Server credential not found');
            }

            $key = $credential->passphrase
                ? PublicKeyLoader::load($credential->ssh_private_key, $credential->passphrase)
                : PublicKeyLoader::load($credential->ssh_private_key);
            $ssh = new SSH2($server->hostname, $server->ssh_port, 0);

            if (! $ssh->login($credential->username, $key)) {
                throw new Exception('Login failed');
            }

            $output = $ssh->exec($task->command);
            $exitStatus = $ssh->getExitStatus();

            $run->duration = $start->diffInSeconds(now());
            $run->output = $output;
            if ($exitStatus === false) {
                $run->status = RunStatus::FAILED;
                $run->output .= "\n[ERROR] Unable to determine exit status.";
            } elseif ($exitStatus !== 0) {
                $run->status = RunStatus::FAILED;
                $run->output .= "\n[ERROR] Command failed with exit status: {$exitStatus}";
            } else {
                $run->status = RunStatus::SUCCESSFUL;
            }
        } catch (Throwable $e) {
            $run->status = RunStatus::FAILED;
            if ($run->output !== '') {
                $run->output .= "\n";
            }
            $run->output .= "[ERROR] {$e->getMessage()}";
        }

        $run->save();

        $task->scheduleNextRun(now());
        $task->save();
    }
}

<?php

namespace App\Actions;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Task;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Throwable;

class RunTask
{
    /**
     * @throws Exception
     */
    public function handle(int $taskId): void
    {
        $task = Task::find($taskId);
        if (! $task) {
            Log::warning("Task not found: {$taskId}");
            return;
        }

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

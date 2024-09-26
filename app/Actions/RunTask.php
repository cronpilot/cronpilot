<?php

namespace App\Actions;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Task;
use Exception;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class RunTask
{
    /**
     * @throws Exception
     */
    public function handle(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $server = $task->server;

        if (! $server) {
            throw new Exception('Server not found');
        }

        $credential = $task->serverCredential;

        if (! $credential) {
            throw new Exception('Server credential not found');
        }

        $key = PublicKeyLoader::load($credential->ssh_private_key, $credential->passphrase);
        $ssh = new SSH2($server->hostname);

        if (! $ssh->login($credential->username, $key)) {
            throw new Exception('Login failed');
        }

        $run = new Run;
        $run->tenant_id = $task->tenant->id;
        $run->task_id = $task->id;
        $run->status = RunStatus::RUNNING;
        $run->duration = 0;
        $run->save();
        $start = now();

        $output = $ssh->exec($task->command);

        $run->duration = $start->diffInSeconds(now());
        $run->output = $output;
        $run->status = RunStatus::SUCCESSFUL;
        $run->save();

        // @todo: this is just fake for now. Implement properly once we have the rrules figured out
        $task->scheduleNextRun(now());
        $task->save();
    }
}

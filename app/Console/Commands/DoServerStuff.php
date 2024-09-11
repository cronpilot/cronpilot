<?php

namespace App\Console\Commands;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\Task;
use Exception;
use Illuminate\Console\Command;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class DoServerStuff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:do-server-stuff';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ssh into a remote server and do some stuff.';

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle(): ?int
    {
        $task = Task::find(1);
        $server = $task->server;
        $credential = $task->serverCredential;
        $key = PublicKeyLoader::load($credential->ssh_private_key, $credential->passphrase);
        $ssh = new SSH2($server->hostname);
        if (!$ssh->login($credential->username, $key)) {
            throw new Exception('Login failed');
        }

        $run = new Run();
        $run->tenant_id = $task->tenant->id;
        $run->task_id = $task->id;
        $run->status = RunStatus::RUNNING;
        $run->duration = 0;
        $run->save();
        $start = now();

        $this->info("Executing: {$task->command}");
        $output = $ssh->exec($task->command);

        $run->duration = $start->diffInSeconds(now());
        $run->output = $output;
        $run->status = RunStatus::SUCCESSFUL;
        $run->save();

        return 0;
    }
}

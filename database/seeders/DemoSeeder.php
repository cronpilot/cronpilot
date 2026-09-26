<?php

namespace Database\Seeders;

use App\Enums\RunStatus;
use App\Enums\TaskStatus;
use App\Models\Run;
use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A realistic, fictional tenant for exploring Cron Pilot and taking screenshots.
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Safe to rerun: the previous demo tenant and everything in it is replaced.
 * The servers don't exist, so running a demo task for real records a failed
 * run rather than executing anything.
 */
class DemoSeeder extends Seeder
{
    public const TENANT_NAME = 'Acme';

    public const USER_EMAIL = 'demo@cronpilot.test';

    public const USER_PASSWORD = 'password';

    private const TIMEZONE = 'America/New_York';

    private CarbonImmutable $now;

    private Tenant $tenant;

    private User $user;

    public function run(): void
    {
        $this->now = CarbonImmutable::now();

        DB::transaction(function (): void {
            $this->removePreviousDemo();

            $this->tenant = Tenant::create(['name' => self::TENANT_NAME]);

            $this->user = User::updateOrCreate(
                ['email' => self::USER_EMAIL],
                [
                    'name' => 'Demo Pilot',
                    'password' => Hash::make(self::USER_PASSWORD),
                    'timezone' => self::TIMEZONE,
                ],
            );
            $this->user->tenants()->syncWithoutDetaching($this->tenant);

            // Let the local admin see the demo tenant without switching accounts.
            User::where('email', config('auth.local_admin_user.email'))
                ->where('email', '!=', '')
                ->first()
                ?->tenants()->syncWithoutDetaching($this->tenant);

            $this->seedTasks();
        });
    }

    private function removePreviousDemo(): void
    {
        Tenant::withTrashed()->where('name', self::TENANT_NAME)->get()->each(function (Tenant $tenant): void {
            Run::withTrashed()->where('tenant_id', $tenant->id)->forceDelete();
            Task::withTrashed()->where('tenant_id', $tenant->id)->forceDelete();
            Server::withTrashed()->where('tenant_id', $tenant->id)->forceDelete();
            ServerCredential::withTrashed()->where('tenant_id', $tenant->id)->forceDelete();
            $tenant->users()->detach();
            $tenant->forceDelete();
        });
    }

    private function seedTasks(): void
    {
        $web = $this->server('web-01', 'web-01.acme.internal');
        $db = $this->server('db-01', 'db-01.acme.internal');
        $worker = $this->server('worker-01', 'worker-01.acme.internal');
        $deploy = $this->credential('Deploy key', 'deploy');
        $backup = $this->credential('Backup key', 'backup');

        // Nightly, and it always works.
        $task = $this->task($db, $backup, 'Database backup',
            'Dump the production database, compress it and upload it to object storage.',
            '/usr/local/bin/backup-db --gzip --upload s3://acme-backups/nightly',
            'FREQ=DAILY;DTSTART=20260101T023000;INTERVAL=1');
        foreach (range(7, 1) as $daysAgo) {
            $this->addRun($task, RunStatus::SUCCESSFUL, $this->at($daysAgo, '02:30'), rand(212, 268), $this->backupOutput());
        }

        // Hourly, with one failure yesterday.
        $task = $this->task($worker, $deploy, 'Invoice sync',
            'Pull new and updated invoices from the billing provider.',
            'php artisan invoices:sync --since=1h',
            'FREQ=HOURLY;DTSTART=20260101T000500;INTERVAL=1');
        foreach (range(36, 1) as $hoursAgo) {
            $startedAt = $this->now->subHours($hoursAgo)->setTime($this->now->subHours($hoursAgo)->hour, 5);
            $hoursAgo === 20
                ? $this->addRun($task, RunStatus::FAILED, $startedAt, 30, $this->invoiceTimeoutOutput())
                : $this->addRun($task, RunStatus::SUCCESSFUL, $startedAt, rand(4, 11), $this->invoiceOutput());
        }

        // Weekly, and last Monday's run failed.
        $task = $this->task($worker, $deploy, 'Weekly reports',
            'Build the weekly revenue and usage reports and email them to the leadership team.',
            'php artisan reports:generate --weekly --email=leadership',
            'FREQ=WEEKLY;DTSTART=20260105T060000;INTERVAL=1;BYDAY=MO');
        $lastMonday = $this->now->setTimezone(self::TIMEZONE)->previous('Monday')->setTime(6, 0)->utc();
        foreach ([3, 2, 1] as $weeksAgo) {
            $this->addRun($task, RunStatus::SUCCESSFUL, $lastMonday->subWeeks($weeksAgo), rand(95, 130), $this->reportOutput());
        }
        $this->addRun($task, RunStatus::FAILED, $lastMonday, 41, $this->reportFailureOutput());

        // Weekday mornings, one of them triggered by hand.
        $task = $this->task($worker, $deploy, 'Trial reminder emails',
            'Email customers whose trial ends in the next three days.',
            'php artisan trials:remind --days=3',
            'FREQ=WEEKLY;DTSTART=20260105T090000;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR');
        foreach (range(7, 1) as $daysAgo) {
            $day = $this->now->setTimezone(self::TIMEZONE)->subDays($daysAgo);
            if ($day->isWeekend()) {
                continue;
            }
            $this->addRun($task, RunStatus::SUCCESSFUL, $this->at($daysAgo, '09:00'), rand(2, 6), $this->reminderOutput());
        }
        $this->addRun($task, RunStatus::SUCCESSFUL, $this->now->subHours(3), 4, $this->reminderOutput(), $this->user);

        // Every 15 minutes, running right now, and long enough to overlap itself.
        $task = $this->task($worker, $deploy, 'Customer import',
            'Import new customers and contact changes from the CRM.',
            'php artisan customers:import --source=crm',
            'FREQ=MINUTELY;DTSTART=20260101T000000;INTERVAL=15');
        foreach (range(16, 1) as $slot) {
            $startedAt = $this->now->subMinutes(15 * $slot + 5);
            in_array($slot, [9, 4], true)
                ? $this->addRun($task, RunStatus::SKIPPED, $startedAt, 0, '[SKIPPED] Previous run still in progress.')
                : $this->addRun($task, RunStatus::SUCCESSFUL, $startedAt, rand(180, 1_150), $this->importOutput());
        }
        $this->addRun($task, RunStatus::RUNNING, $this->now->subMinutes(4), 0, '');

        // Nightly housekeeping.
        $task = $this->task($web, $deploy, 'Clean up temp files',
            'Delete uploads and exports older than a day from the temp directory.',
            'find /var/www/acme/storage/tmp -type f -mtime +1 -print -delete | wc -l',
            'FREQ=DAILY;DTSTART=20260101T040000;INTERVAL=1');
        foreach (range(7, 1) as $daysAgo) {
            $this->addRun($task, RunStatus::SUCCESSFUL, $this->at($daysAgo, '04:00'), rand(1, 3), (string) rand(40, 900));
        }

        // Nightly, and last night's failure was fixed with a manual rerun.
        $task = $this->task($web, $deploy, 'Renew TLS certificates',
            'Renew any certificates due to expire in the next 30 days.',
            'sudo certbot renew --quiet --deploy-hook "systemctl reload nginx"',
            'FREQ=DAILY;DTSTART=20260101T031500;INTERVAL=1');
        foreach (range(4, 2) as $daysAgo) {
            $this->addRun($task, RunStatus::SUCCESSFUL, $this->at($daysAgo, '03:15'), rand(3, 8), '');
        }
        $this->addRun($task, RunStatus::FAILED, $this->at(1, '03:15'), 12, $this->certbotFailureOutput());
        $this->addRun($task, RunStatus::SUCCESSFUL, $this->at(1, '09:40'), 9, 'Congratulations, all renewals succeeded.', $this->user);

        // Paused while the search cluster is upgraded.
        $task = $this->task($worker, $deploy, 'Rebuild search index',
            'Reindex products into the search cluster. Paused during the cluster upgrade.',
            'php artisan scout:import "App\\Models\\Product"',
            'FREQ=HOURLY;DTSTART=20260101T000000;INTERVAL=6',
            paused: true);
        foreach (range(5, 2) as $daysAgo) {
            $this->addRun($task, RunStatus::SUCCESSFUL, $this->at($daysAgo, '12:00'), rand(300, 420), "Imported [App\\Models\\Product] models up to ID: 48213\nAll [App\\Models\\Product] records have been imported.");
        }
    }

    private function server(string $name, string $hostname): Server
    {
        return Server::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'hostname' => $hostname,
            'ssh_port' => 22,
        ]);
    }

    private function credential(string $title, string $username): ServerCredential
    {
        return ServerCredential::create([
            'tenant_id' => $this->tenant->id,
            'title' => $title,
            'username' => $username,
            'ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nDEMO KEY, NOT A REAL KEY\n-----END OPENSSH PRIVATE KEY-----",
        ]);
    }

    private function task(Server $server, ServerCredential $credential, string $name, string $description, string $command, string $schedule, bool $paused = false): Task
    {
        $task = new Task([
            'server_id' => $server->id,
            'server_credential_id' => $credential->id,
            'name' => $name,
            'description' => $description,
            'status' => TaskStatus::ACTIVE,
            'command' => $command,
            'schedule' => $schedule,
            'timezone' => self::TIMEZONE,
            'paused' => $paused,
        ]);
        $task->tenant_id = $this->tenant->id;
        $task->scheduleNextRun($this->now);
        $task->save();

        return $task;
    }

    private function addRun(Task $task, RunStatus $status, CarbonImmutable $startedAt, int $duration, string $output, ?User $triggeredBy = null): void
    {
        $run = new Run;
        $run->tenant_id = $this->tenant->id;
        $run->task_id = $task->id;
        $run->status = $status;
        $run->output = $output;
        $run->duration = $duration;
        $run->triggerable_type = $triggeredBy ? User::class : null;
        $run->triggerable_id = $triggeredBy?->id;
        $run->created_at = $startedAt;
        $run->updated_at = $startedAt->addSeconds($duration);
        $run->save();
    }

    /** A time of day, $daysAgo days back, in the demo timezone. */
    private function at(int $daysAgo, string $time): CarbonImmutable
    {
        [$hour, $minute] = explode(':', $time);

        return $this->now->setTimezone(self::TIMEZONE)->subDays($daysAgo)->setTime((int) $hour, (int) $minute)->utc();
    }

    private function backupOutput(): string
    {
        $size = number_format(rand(1_810, 1_990) / 1000, 2);

        return implode("\n", [
            '[backup-db] Dumping acme_production (pg_dump 16.4)...',
            '[backup-db] 412 tables, 38,912,044 rows',
            "[backup-db] Compressed to {$size} GB",
            '[backup-db] Uploading to s3://acme-backups/nightly/acme_production.sql.gz',
            '[backup-db] Upload complete, checksum verified',
            '[backup-db] Pruned 1 backup older than 30 days',
            '[backup-db] Done.',
        ]);
    }

    private function invoiceOutput(): string
    {
        $created = rand(0, 14);
        $updated = rand(3, 40);

        return "Fetching invoices changed since the last sync...\nCreated: {$created}  Updated: {$updated}  Unchanged: ".rand(200, 260)."\nSync complete.";
    }

    private function invoiceTimeoutOutput(): string
    {
        return implode("\n", [
            'Fetching invoices changed since the last sync...',
            '',
            '   Illuminate\Http\Client\ConnectionException',
            '',
            '  cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received',
            '  (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.billing.example/v2/invoices?updated_since=...',
            '',
            '[ERROR] Command failed with exit status: 1',
        ]);
    }

    private function reportOutput(): string
    {
        return "Building revenue report... done (4 charts)\nBuilding usage report... done (6 charts)\nRendering PDF... done (18 pages)\nEmailed 5 recipients on the leadership list.";
    }

    private function reportFailureOutput(): string
    {
        return implode("\n", [
            'Building revenue report... done (4 charts)',
            'Building usage report...',
            '',
            '   Illuminate\Database\QueryException',
            '',
            "  SQLSTATE[HY000]: General error: 1021 Disk full (/tmp/#sql_1f3a_0.MAI); waiting for someone to free some space...",
            '  (Connection: mysql, SQL: select date(created_at) as day, count(*) as sessions from `sessions` where ...)',
            '',
            '[ERROR] Command failed with exit status: 1',
        ]);
    }

    private function reminderOutput(): string
    {
        return 'Found '.rand(3, 19).' trials ending in the next 3 days. Queued reminder emails.';
    }

    private function importOutput(): string
    {
        return "Connecting to CRM... ok\nNew customers: ".rand(0, 25).'  Updated contacts: '.rand(10, 140)."\nImport finished.";
    }

    private function certbotFailureOutput(): string
    {
        return implode("\n", [
            'Failed to renew certificate app.acme.example with error: Some challenges have failed.',
            'The following renewals failed:',
            '  /etc/letsencrypt/live/app.acme.example/fullchain.pem (failure)',
            '1 renew failure(s), 0 parse failure(s)',
            '',
            '[ERROR] Command failed with exit status: 1',
        ]);
    }
}

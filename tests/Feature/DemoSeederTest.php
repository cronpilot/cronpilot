<?php

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;

function demoTask(string $name): Task
{
    return Task::whereHas('tenant', fn ($query) => $query->where('name', DemoSeeder::TENANT_NAME))
        ->where('name', $name)
        ->sole();
}

it('seeds a demo tenant with a user who can sign in', function () {
    $this->seed(DemoSeeder::class);

    $tenant = Tenant::where('name', DemoSeeder::TENANT_NAME)->sole();
    $user = User::where('email', DemoSeeder::USER_EMAIL)->sole();

    expect($user->tenants->pluck('id'))->toContain($tenant->id)
        ->and(Hash::check(DemoSeeder::USER_PASSWORD, $user->password))->toBeTrue()
        ->and($tenant->tasks()->count())->toBe(8)
        ->and($tenant->servers()->count())->toBe(3);
});

it('seeds every run state the product shows', function () {
    $this->seed(DemoSeeder::class);

    expect(demoTask('Weekly reports')->lastRunStatus)->toBe(RunStatus::FAILED)
        ->and(demoTask('Database backup')->lastRunStatus)->toBe(RunStatus::SUCCESSFUL)
        ->and(demoTask('Customer import')->isRunning())->toBeTrue()
        ->and(demoTask('Customer import')->runs()->where('status', RunStatus::SKIPPED)->count())->toBe(2)
        ->and(Run::whereNotNull('triggerable_id')->count())->toBe(2);
});

it('schedules active tasks and leaves the paused one without a next run', function () {
    $this->seed(DemoSeeder::class);

    $paused = demoTask('Rebuild search index');
    expect($paused->paused)->toBeTrue()
        ->and($paused->next_run_at)->toBeNull();

    Task::where('paused', false)->get()->each(
        fn (Task $task) => expect($task->next_run_at)->toBeGreaterThan(now()->toDateTimeString())
    );
});

it('replaces the previous demo data when run again', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Tenant::withTrashed()->where('name', DemoSeeder::TENANT_NAME)->count())->toBe(1)
        ->and(Task::withTrashed()->count())->toBe(8)
        ->and(User::where('email', DemoSeeder::USER_EMAIL)->count())->toBe(1);
});

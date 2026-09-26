<?php

use App\Enums\TaskStatus;
use App\Filament\Resources\TaskResource\Pages\CreateTask;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Recurr\Frequency;

// 9 PM on Friday Sep 25 in New York, while daylight saving time is in effect.
const NOW_UTC = '2026-09-26 01:00:00';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(NOW_UTC, 'UTC'));

    $this->tenant = Tenant::factory()->create();
});

function scheduledTask(array $attributes): Task
{
    return Task::factory()->for(test()->tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'timezone' => 'America/New_York',
        ...$attributes,
    ]);
}

function signInToPanel(): void
{
    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $user->tenants()->attach(test()->tenant);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant(test()->tenant);
}

it('runs a 9 AM New York task at 9 AM New York, stored in UTC', function () {
    $task = scheduledTask(['schedule' => 'FREQ=DAILY;DTSTART=20260105T090000;INTERVAL=1']);

    $task->scheduleNextRun(now());

    // 9 AM EDT is 13:00 UTC.
    expect((string) $task->next_run_at)->toBe('2026-09-26 13:00:00');
});

it('keeps a task at the same local time across a daylight saving change', function () {
    $task = scheduledTask(['schedule' => 'FREQ=DAILY;DTSTART=20260105T090000;INTERVAL=1']);

    // Daylight saving time ends on Nov 1, 2026, so 9 AM EST is 14:00 UTC.
    $task->scheduleNextRun(CarbonImmutable::parse('2026-11-02 00:00:00', 'UTC'));

    expect((string) $task->next_run_at)->toBe('2026-11-02 14:00:00');
});

it('shows the next run at the task\'s local time', function () {
    $task = scheduledTask(['schedule' => 'FREQ=DAILY;DTSTART=20260105T090000;INTERVAL=1']);
    $task->scheduleNextRun(now());

    expect($task->nextRunAtCarbon->timezone('America/New_York')->format('Y-m-d H:i'))->toBe('2026-09-26 09:00')
        ->and($task->upcomingRunTimes->map->timezone('America/New_York')->map->format('D H:i')->all())
        ->toBe(['Sat 09:00', 'Sun 09:00', 'Mon 09:00']);
});

it('schedules frequent tasks whose start date is long ago', function () {
    $task = scheduledTask(['schedule' => 'FREQ=MINUTELY;DTSTART=20250101T000000;INTERVAL=15']);

    $task->scheduleNextRun(now());

    expect($task->status)->toBe(TaskStatus::ACTIVE)
        ->and((string) $task->next_run_at)->toBe('2026-09-26 01:15:00');
});

it('runs a schedule without a start date on the hour', function () {
    $task = scheduledTask(['schedule' => 'FREQ=HOURLY;INTERVAL=1']);

    $task->scheduleNextRun(CarbonImmutable::parse('2026-09-26 01:23:45', 'UTC'));

    expect((string) $task->next_run_at)->toBe('2026-09-26 02:00:00');
});

it('stops a task after its end date', function () {
    $task = scheduledTask(['schedule' => 'FREQ=DAILY;DTSTART=20260105T090000;INTERVAL=1;UNTIL=20260927T235959']);

    $task->scheduleNextRun(now());
    expect((string) $task->next_run_at)->toBe('2026-09-26 13:00:00');

    $task->scheduleNextRun(CarbonImmutable::parse('2026-09-28 00:00:00', 'UTC'));
    expect($task->status)->toBe(TaskStatus::DISABLED)
        ->and($task->next_run_at)->toBeNull();
});

it('saves the time entered in the form as the task\'s local time', function () {
    signInToPanel();

    Livewire::test(CreateTask::class)
        ->fillForm([
            'name' => 'Morning report',
            'status' => TaskStatus::ACTIVE->value,
            'command' => 'php artisan report',
            'has_schedule' => true,
            'frequency' => Frequency::DAILY,
            'interval' => 1,
            'start_date' => '2026-01-05 09:00:00',
            'end_date' => '2026-12-31 00:00:00',
            'timezone' => 'America/New_York',
        ])
        ->assertSee('Sat, Sep 26, 2026 9:00 AM')
        ->call('create')
        ->assertHasNoFormErrors();

    $task = Task::where('name', 'Morning report')->sole();

    expect((string) $task->next_run_at)->toBe('2026-09-26 13:00:00')
        ->and($task->schedule)->toContain('DTSTART=20260105T090000')
        ->and($task->schedule)->toContain('UNTIL=20261231T000000');
});

it('fills the edit form with the saved start and end dates', function () {
    signInToPanel();
    $task = scheduledTask(['schedule' => 'FREQ=DAILY;DTSTART=20260105T090000;INTERVAL=1;UNTIL=20261231T000000']);

    Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
        ->assertFormSet(function (array $state): array {
            expect(CarbonImmutable::parse($state['start_date'])->format('Y-m-d H:i'))->toBe('2026-01-05 09:00')
                ->and(CarbonImmutable::parse($state['end_date'])->format('Y-m-d H:i'))->toBe('2026-12-31 00:00');

            return [];
        });
});

it('recalculates existing next run times when migrated', function () {
    $task = scheduledTask(['schedule' => 'FREQ=DAILY;DTSTART=20260105T090000;INTERVAL=1']);
    Task::whereKey($task->id)->update(['next_run_at' => '2026-09-26 09:00:00']); // the old, UTC-based value
    $paused = scheduledTask(['schedule' => 'FREQ=DAILY;DTSTART=20260105T090000;INTERVAL=1', 'paused' => true]);

    (require database_path('migrations/2026_09_26_000000_recalculate_task_next_run_times.php'))->up();

    expect((string) $task->fresh()->next_run_at)->toBe('2026-09-26 13:00:00')
        ->and($paused->fresh()->next_run_at)->toBeNull();
});

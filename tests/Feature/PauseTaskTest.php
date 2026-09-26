<?php

use App\Enums\TaskStatus;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\TaskResource\Pages\ViewTask;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $user->tenants()->attach($this->tenant);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($this->tenant);

    $this->task = Task::factory()->for($this->tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'schedule' => 'FREQ=HOURLY',
        'timezone' => 'America/New_York',
        'paused' => false,
    ]);
});

it('pauses and resumes a task from the task table', function () {
    Livewire::test(ListTasks::class)
        ->call('updateTableColumnState', 'paused', (string) $this->task->getKey(), true);

    expect($this->task->fresh()->paused)->toBeTrue();

    Livewire::test(ListTasks::class)
        ->call('updateTableColumnState', 'paused', (string) $this->task->getKey(), false);

    expect($this->task->fresh()->paused)->toBeFalse();
});

it('pauses a task from the edit form', function () {
    Livewire::test(EditTask::class, ['record' => $this->task->getRouteKey()])
        ->assertFormFieldExists('paused')
        ->fillForm(['paused' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->task->fresh()->paused)->toBeTrue();
});

it('marks a paused task on its view page', function () {
    $this->task->update(['paused' => true]);

    Livewire::test(ViewTask::class, ['record' => $this->task->getRouteKey()])
        ->assertSee('Paused');
});

it('does not mark an active task as paused on its view page', function () {
    Livewire::test(ViewTask::class, ['record' => $this->task->getRouteKey()])
        ->assertDontSee('Paused');
});

it('waits for the next scheduled time when a task is resumed from the table', function () {
    $this->task->update(['paused' => true]);
    expect($this->task->fresh()->next_run_at)->toBeNull();

    Livewire::test(ListTasks::class)
        ->call('updateTableColumnState', 'paused', (string) $this->task->getKey(), false);

    $task = $this->task->fresh();
    expect($task->paused)->toBeFalse()
        ->and($task->status)->toBe(TaskStatus::ACTIVE)
        ->and($task->next_run_at)->toBeGreaterThan(now()->toDateTimeString());
});

it('clears the next run when a task is paused', function () {
    $this->task->update(['next_run_at' => now()->addHour()]);

    $this->task->update(['paused' => true]);

    expect($this->task->fresh()->next_run_at)->toBeNull();
});

it('keeps the next run clear when a paused task is saved from the edit form', function () {
    $this->task->update(['paused' => true]);

    Livewire::test(EditTask::class, ['record' => $this->task->getRouteKey()])
        ->fillForm(['name' => 'renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->task->fresh())
        ->name->toBe('renamed')
        ->next_run_at->toBeNull();
});

it('schedules the next run when a task is resumed from the edit form', function () {
    $this->task->update(['paused' => true]);

    Livewire::test(EditTask::class, ['record' => $this->task->getRouteKey()])
        ->fillForm(['paused' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->task->fresh()->next_run_at)->toBeGreaterThan(now()->toDateTimeString());
});

it('does not disable an unscheduled task when it is resumed', function () {
    $task = Task::factory()->for($this->tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'schedule' => null,
        'next_run_at' => null,
        'paused' => true,
    ]);

    $task->update(['paused' => false]);

    expect($task->fresh())
        ->status->toBe(TaskStatus::ACTIVE)
        ->next_run_at->toBeNull();
});

it('does not move the next run of a newly created task', function () {
    $task = Task::factory()->for($this->tenant)->create([
        'status' => TaskStatus::ACTIVE,
        'schedule' => 'FREQ=HOURLY',
        'paused' => false,
        'next_run_at' => '2026-01-01 00:00:00',
    ]);

    expect($task->fresh()->next_run_at)->toBe('2026-01-01 00:00:00');
});

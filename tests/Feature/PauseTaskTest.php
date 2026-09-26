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

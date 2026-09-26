<?php

use App\Enums\TaskStatus;
use App\Filament\Resources\RunResource\Pages\ListRuns;
use App\Filament\Resources\ServerCredentialResource\Pages\ListServerCredentials;
use App\Filament\Resources\ServerResource\Pages\ListServers;
use App\Filament\Resources\TaskResource\Pages\CreateTask;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Run;
use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

// Two tenants with the same kinds of records. Everything a member of "mine"
// can see or choose must come from "mine" only.
beforeEach(function () {
    $this->mine = Tenant::factory()->create();
    $this->theirs = Tenant::factory()->create();

    $this->user = User::factory()->create(['timezone' => 'America/New_York']);
    $this->user->tenants()->attach($this->mine);
    $this->outsider = User::factory()->create();
    $this->outsider->tenants()->attach($this->theirs);

    foreach (['mine', 'theirs'] as $who) {
        $tenant = $this->{$who};
        $this->{"{$who}Server"} = Server::factory()->for($tenant)->create(['name' => "{$who}-server"]);
        $this->{"{$who}Credential"} = ServerCredential::factory()->for($tenant)->create(['title' => "{$who}-credential"]);
        $this->{"{$who}Task"} = Task::factory()->for($tenant)->create([
            'name' => "{$who}-task",
            'status' => TaskStatus::ACTIVE,
            'schedule' => 'FREQ=HOURLY',
            'timezone' => 'America/New_York',
            'server_id' => $this->{"{$who}Server"}->id,
        ]);
        $this->{"{$who}Run"} = Run::factory()->for($this->{"{$who}Task"})->create(['tenant_id' => $tenant->id]);
    }

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($this->mine);
});

function optionsFor(string $field, $component): array
{
    $options = [];
    $component->assertFormFieldExists($field, function ($select) use (&$options): bool {
        $options = $select->getOptions();

        return true;
    });

    return $options;
}

it('only lists the current tenant\'s records on each page', function () {
    Livewire::test(ListTasks::class)->assertCanSeeTableRecords([$this->mineTask])->assertCanNotSeeTableRecords([$this->theirsTask]);
    Livewire::test(ListServers::class)->assertCanSeeTableRecords([$this->mineServer])->assertCanNotSeeTableRecords([$this->theirsServer]);
    Livewire::test(ListServerCredentials::class)->assertCanSeeTableRecords([$this->mineCredential])->assertCanNotSeeTableRecords([$this->theirsCredential]);
    Livewire::test(ListRuns::class)->assertCanSeeTableRecords([$this->mineRun])->assertCanNotSeeTableRecords([$this->theirsRun]);
    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$this->user])->assertCanNotSeeTableRecords([$this->outsider]);
});

it('only offers the current tenant\'s servers and credentials on the task form', function () {
    foreach ([Livewire::test(CreateTask::class), Livewire::test(EditTask::class, ['record' => $this->mineTask->getRouteKey()])] as $form) {
        expect(optionsFor('server_id', $form))->toBe([$this->mineServer->id => 'mine-server'])
            ->and(optionsFor('server_credential_id', $form))->toBe([$this->mineCredential->id => 'mine-credential']);
    }
});

it('refuses another tenant\'s server or credential even when submitted directly', function () {
    Livewire::test(EditTask::class, ['record' => $this->mineTask->getRouteKey()])
        ->fillForm([
            'server_id' => $this->theirsServer->id,
            'server_credential_id' => $this->theirsCredential->id,
        ])
        ->call('save')
        ->assertHasFormErrors(['server_id', 'server_credential_id']);

    expect($this->mineTask->fresh())
        ->server_id->toBe($this->mineServer->id)
        ->server_credential_id->toBeNull();
});

it('only offers the current tenant\'s records in the table filters', function () {
    $filterOptions = function ($component, string $field): array {
        $options = [];
        $component->assertFormFieldExists("{$field}.values", 'tableFiltersForm', function ($select) use (&$options): bool {
            $options = $select->getOptions();

            return true;
        });

        return $options;
    };

    expect($filterOptions(Livewire::test(ListTasks::class), 'server'))->toBe([$this->mineServer->id => 'mine-server'])
        ->and($filterOptions(Livewire::test(ListRuns::class), 'task'))->toBe([$this->mineTask->id => 'mine-task']);
});
